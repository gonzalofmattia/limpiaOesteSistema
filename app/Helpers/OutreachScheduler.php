<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Motor de la cola de envios de WhatsApp. Se ejecuta de forma perezosa (lazy):
 * no hay cron, se dispara cuando el worker externo pide un batch via la API.
 *
 * Prioridad de llenado de la cola del dia (ver tambien Fase 3, que agrega
 * seguimientos/recontactos antes de los primeros contactos de campania):
 * recontactos > seguimientos > primeros contactos de campanias activas.
 */
final class OutreachScheduler
{
    private const STALE_CLAIM_MINUTES = 30;
    private const FILL_CANDIDATE_LIMIT = 500;

    public static function isGloballyPaused(): bool
    {
        return setting('outreach_global_pause', '0') === '1';
    }

    public static function isWithinSendingWindow(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable('now');
        $weekendsEnabled = setting('outreach_weekends_enabled', '0') === '1';
        $dayOfWeek = (int) $now->format('N'); // 1=lunes ... 7=domingo
        if (!$weekendsEnabled && $dayOfWeek >= 6) {
            return false;
        }
        $start = setting('outreach_window_start', '09:30') ?? '09:30';
        $end = setting('outreach_window_end', '18:30') ?? '18:30';
        $current = $now->format('H:i');

        return $current >= $start && $current <= $end;
    }

    public static function dailyCap(): int
    {
        $cap = (int) (setting('outreach_daily_cap', '15') ?? '15');

        return max(1, min(25, $cap));
    }

    public static function remainingCapToday(Database $db): int
    {
        $sentToday = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM outreach_queue WHERE status = 'sent' AND DATE(sent_at) = CURDATE()"
        );

        return max(0, self::dailyCap() - $sentToday);
    }

    /**
     * Punto de entrada usado por la API: recupera claims viejos, llena la cola del dia si
     * hace falta, y devuelve hasta $batchSize mensajes recien reclamados ('claimed').
     *
     * @return array{paused: bool, messages: list<array{uuid: string, phone: string, body: string}>}
     */
    public static function nextBatch(Database $db, int $batchSize = 5): array
    {
        self::recoverStaleClaims($db);

        if (self::isGloballyPaused()) {
            return ['paused' => true, 'messages' => []];
        }
        if (!self::isWithinSendingWindow()) {
            return ['paused' => false, 'messages' => []];
        }

        $remainingCap = self::remainingCapToday($db);
        if ($remainingCap > 0) {
            self::fillTodayQueueIfNeeded($db, $remainingCap);
        }

        $take = max(0, min($batchSize, $remainingCap));
        if ($take === 0) {
            return ['paused' => false, 'messages' => []];
        }

        $rows = $db->fetchAll(
            "SELECT oq.id, oq.uuid, p.phone, oq.rendered_body
             FROM outreach_queue oq
             INNER JOIN prospects p ON p.id = oq.prospect_id
             WHERE oq.status = 'queued' AND oq.scheduled_for <= CURDATE()
             ORDER BY oq.created_at ASC, oq.id ASC
             LIMIT {$take}"
        );
        if ($rows === []) {
            return ['paused' => false, 'messages' => []];
        }

        $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->query(
            "UPDATE outreach_queue SET status = 'claimed', claimed_at = NOW() WHERE id IN ({$placeholders}) AND status = 'queued'",
            $ids
        );

        $messages = [];
        foreach ($rows as $r) {
            $messages[] = [
                'uuid' => (string) $r['uuid'],
                'phone' => (string) $r['phone'],
                'body' => (string) $r['rendered_body'],
            ];
        }

        return ['paused' => false, 'messages' => $messages];
    }

    /** Filas 'claimed' hace mas de 30 min sin reporte del worker vuelven a 'queued'. */
    public static function recoverStaleClaims(Database $db): int
    {
        return $db->query(
            "UPDATE outreach_queue SET status = 'queued', claimed_at = NULL
             WHERE status = 'claimed' AND claimed_at < DATE_SUB(NOW(), INTERVAL " . self::STALE_CLAIM_MINUTES . " MINUTE)"
        )->rowCount();
    }

    private static function fillTodayQueueIfNeeded(Database $db, int $remainingCap): void
    {
        $alreadyQueuedToday = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM outreach_queue WHERE status IN ('queued', 'claimed') AND scheduled_for = CURDATE()"
        );
        if ($alreadyQueuedToday > 0) {
            return;
        }

        self::autoMarkStaleAsNoResponse($db);

        $remaining = $remainingCap;
        $remaining -= self::enqueueRecontacts($db, $remaining);
        if ($remaining > 0) {
            $remaining -= self::enqueueFollowups($db, $remaining);
        }
        if ($remaining > 0) {
            self::enqueueFirstContactsRoundRobin($db, $remaining);
        }
    }

    /**
     * Transiciones automaticas por inactividad: seguimiento ya enviado sin respuesta,
     * o recontactos agotados sin novedades. No dependen de que haya cupo de envio hoy.
     */
    private static function autoMarkStaleAsNoResponse(Database $db): void
    {
        $followupDays = (int) (setting('outreach_followup_days', '7') ?? '7');
        $db->query(
            "UPDATE prospects SET status = 'sin_respuesta'
             WHERE status = 'contactado' AND contact_attempts >= 2
               AND last_contacted_at IS NOT NULL
               AND last_contacted_at <= DATE_SUB(NOW(), INTERVAL :d DAY)",
            ['d' => $followupDays]
        );

        $recontactDays = (int) (setting('outreach_recontact_days', '45') ?? '45');
        $maxRecontacts = (int) (setting('outreach_max_recontacts', '2') ?? '2');
        $exhausted = $db->fetchAll(
            "SELECT p.id FROM prospects p
             WHERE p.status IN ('respondio', 'interesado')
               AND (SELECT COUNT(*) FROM prospect_events pe WHERE pe.prospect_id = p.id AND pe.event_type = 'recontacto') >= :max
               AND NOT EXISTS (
                   SELECT 1 FROM prospect_events pe2
                   WHERE pe2.prospect_id = p.id AND pe2.created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
               )",
            ['max' => $maxRecontacts, 'days' => $recontactDays]
        );
        foreach ($exhausted as $row) {
            $db->update('prospects', ['status' => 'sin_respuesta'], 'id = :id', ['id' => (int) $row['id']]);
        }
    }

    /** Seguimiento automatico: primer contacto sin respuesta hace >= followup_days. */
    private static function enqueueFollowups(Database $db, int $remainingCap): int
    {
        if ($remainingCap <= 0) {
            return 0;
        }
        $followupDays = (int) (setting('outreach_followup_days', '7') ?? '7');
        $rows = $db->fetchAll(
            "SELECT p.* FROM prospects p
             WHERE p.status = 'contactado' AND p.contact_attempts = 1 AND p.blacklisted = 0
               AND p.last_contacted_at IS NOT NULL
               AND p.last_contacted_at <= DATE_SUB(NOW(), INTERVAL :d DAY)
               AND NOT EXISTS (SELECT 1 FROM outreach_queue oq WHERE oq.prospect_id = p.id AND oq.status IN ('queued', 'claimed'))
             ORDER BY p.last_contacted_at ASC
             LIMIT " . self::FILL_CANDIDATE_LIMIT,
            ['d' => $followupDays]
        );

        return self::enqueueFromCandidates($db, $rows, 'seguimiento_7d', $remainingCap, null);
    }

    /** Recontacto automatico: sin novedades hace >= recontact_days, bajo el maximo permitido. */
    private static function enqueueRecontacts(Database $db, int $remainingCap): int
    {
        if ($remainingCap <= 0) {
            return 0;
        }
        $recontactDays = (int) (setting('outreach_recontact_days', '45') ?? '45');
        $maxRecontacts = (int) (setting('outreach_max_recontacts', '2') ?? '2');
        $rows = $db->fetchAll(
            "SELECT p.* FROM prospects p
             WHERE p.status IN ('respondio', 'interesado') AND p.blacklisted = 0
               AND (SELECT COUNT(*) FROM prospect_events pe WHERE pe.prospect_id = p.id AND pe.event_type = 'recontacto') < :max
               AND NOT EXISTS (
                   SELECT 1 FROM prospect_events pe2
                   WHERE pe2.prospect_id = p.id AND pe2.created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
               )
               AND NOT EXISTS (SELECT 1 FROM outreach_queue oq WHERE oq.prospect_id = p.id AND oq.status IN ('queued', 'claimed'))
             ORDER BY p.updated_at ASC
             LIMIT " . self::FILL_CANDIDATE_LIMIT,
            ['max' => $maxRecontacts, 'days' => $recontactDays]
        );

        return self::enqueueFromCandidates($db, $rows, 'recontacto', $remainingCap, 'recontacto');
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param string|null $logEventType Si no es null, registra ese evento ademas de encolar.
     */
    private static function enqueueFromCandidates(
        Database $db,
        array $candidates,
        string $stage,
        int $remainingCap,
        ?string $logEventType
    ): int {
        $activeClientPhones = self::activeClientPhones($db);
        $enqueued = 0;
        foreach ($candidates as $p) {
            if ($enqueued >= $remainingCap) {
                break;
            }
            if (isset($activeClientPhones[(string) $p['phone']])) {
                continue;
            }
            $template = self::findTemplateForStage($db, $stage, (string) $p['business_type']);
            if ($template === null) {
                continue;
            }
            self::enqueueMessage($db, null, $p, (int) $template['id']);
            if ($logEventType !== null) {
                $db->insert('prospect_events', [
                    'prospect_id' => (int) $p['id'],
                    'event_type' => $logEventType,
                    'detail' => null,
                ]);
            }
            $enqueued++;
        }

        return $enqueued;
    }

    /**
     * Resuelve la plantilla a usar segun el rubro del prospecto (con fallback
     * a business_type='todos' si no hay una especifica para ese rubro, o si el
     * rubro no esta normalizado). Publico: tambien lo usa CampaignController
     * para previsualizar el mensaje personalizado de cada prospecto en el dry-run.
     */
    public static function findTemplateForStage(Database $db, string $stage, string $businessType): ?array
    {
        $template = $db->fetch(
            "SELECT * FROM outreach_templates WHERE stage = ? AND business_type = ? AND active = 1 LIMIT 1",
            [$stage, $businessType]
        );
        if ($template) {
            return $template;
        }

        return $db->fetch(
            "SELECT * FROM outreach_templates WHERE stage = ? AND business_type = 'todos' AND active = 1 LIMIT 1",
            [$stage]
        );
    }

    private static function enqueueFirstContactsRoundRobin(Database $db, int $remainingCap): int
    {
        $campaigns = $db->fetchAll("SELECT * FROM outreach_campaigns WHERE status = 'activa' ORDER BY id");
        if ($campaigns === []) {
            return 0;
        }

        $countToday = [];
        foreach ($campaigns as $c) {
            $countToday[(int) $c['id']] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM outreach_queue WHERE campaign_id = ? AND scheduled_for = CURDATE()",
                [(int) $c['id']]
            );
        }

        $enqueued = 0;
        $exhausted = [];
        while ($enqueued < $remainingCap && count($exhausted) < count($campaigns)) {
            $progressed = false;
            foreach ($campaigns as $c) {
                if ($enqueued >= $remainingCap) {
                    break;
                }
                $campaignId = (int) $c['id'];
                if (isset($exhausted[$campaignId])) {
                    continue;
                }
                if ($countToday[$campaignId] >= (int) $c['daily_limit']) {
                    $exhausted[$campaignId] = true;
                    continue;
                }
                $prospect = self::nextMatchingProspect($db, $c);
                if ($prospect === null) {
                    $exhausted[$campaignId] = true;
                    continue;
                }
                // Personalizacion automatica: cada prospecto recibe la
                // plantilla de su propio rubro (o el fallback generico
                // 'todos'), no una plantilla fija por campania.
                $template = self::findTemplateForStage($db, 'primer_contacto', (string) $prospect['business_type']);
                if ($template === null) {
                    // No deberia pasar existiendo el fallback 'todos', pero si
                    // pasa cortamos esta campania en vez de loopear siempre
                    // sobre el mismo prospecto sin poder avanzar.
                    $exhausted[$campaignId] = true;
                    continue;
                }
                self::enqueueMessage($db, $c, $prospect, (int) $template['id']);
                $countToday[$campaignId]++;
                $enqueued++;
                $progressed = true;
            }
            if (!$progressed) {
                break;
            }
        }

        return $enqueued;
    }

    public static function enqueueMessage(Database $db, ?array $campaign, array $prospect, int $templateId): array
    {
        $template = $db->fetch('SELECT * FROM outreach_templates WHERE id = ?', [$templateId]);
        $body = self::renderTemplate((string) ($template['body'] ?? ''), $prospect);
        $uuid = self::generateUuid();
        $db->insert('outreach_queue', [
            'uuid' => $uuid,
            'campaign_id' => $campaign !== null ? (int) $campaign['id'] : null,
            'prospect_id' => (int) $prospect['id'],
            'template_id' => $templateId,
            'rendered_body' => $body,
            'status' => 'queued',
            'scheduled_for' => date('Y-m-d'),
        ]);

        return ['uuid' => $uuid, 'body' => $body];
    }

    /**
     * Encola una respuesta ya redactada (sin plantilla) para el worker mandarla
     * en el proximo ciclo — usado para el auto-reply de la bandeja (intents
     * de bajo riesgo, ver OutreachAiAssistant). No pasa por matchingProspects
     * (es una respuesta directa a un mensaje entrante puntual, no una
     * campaña), pero si respeta el estado de blacklist del prospecto.
     */
    public static function enqueueDirectReply(Database $db, array $prospect, string $body): ?string
    {
        if ((int) ($prospect['blacklisted'] ?? 0) === 1) {
            return null;
        }
        $uuid = self::generateUuid();
        $db->insert('outreach_queue', [
            'uuid' => $uuid,
            'campaign_id' => null,
            'prospect_id' => (int) $prospect['id'],
            'template_id' => null,
            'rendered_body' => $body,
            'status' => 'queued',
            'scheduled_for' => date('Y-m-d'),
        ]);

        return $uuid;
    }

    public static function renderTemplate(string $body, array $prospect): string
    {
        return str_replace(
            ['{{nombre}}', '{{ciudad}}'],
            [(string) ($prospect['name'] ?? ''), (string) ($prospect['city'] ?? '')],
            $body
        );
    }

    /** @return array{0: list<string>, 1: array<string, mixed>} */
    private static function matchingWhereClause(array $campaign): array
    {
        $cooldownDays = (int) (setting('outreach_prospect_cooldown_days', '7') ?? '7');
        $where = [
            'p.blacklisted = 0',
            'p.status = :filter_status',
            '(p.last_contacted_at IS NULL OR p.last_contacted_at < DATE_SUB(NOW(), INTERVAL :cooldown DAY))',
            "NOT EXISTS (SELECT 1 FROM outreach_queue oq WHERE oq.prospect_id = p.id AND oq.status IN ('queued', 'claimed'))",
            "NOT EXISTS (SELECT 1 FROM outreach_queue oq2 WHERE oq2.prospect_id = p.id AND oq2.campaign_id = :campaign_id AND oq2.status IN ('sent', 'queued', 'claimed'))",
        ];
        $params = [
            'filter_status' => (string) $campaign['filter_status'],
            'cooldown' => $cooldownDays,
            'campaign_id' => (int) $campaign['id'],
        ];
        if (!empty($campaign['filter_business_type'])) {
            $where[] = 'p.business_type = :business_type';
            $params['business_type'] = (string) $campaign['filter_business_type'];
        }
        if (!empty($campaign['filter_city'])) {
            $where[] = 'p.city = :city';
            $params['city'] = (string) $campaign['filter_city'];
        }

        return [$where, $params];
    }

    /**
     * Cuenta el total real de prospectos que matchean (sin el LIMIT interno de
     * matchingProspects) — para mostrar en el dry-run y "Destinatarios
     * restantes". matchingProspects() esta pensado para ir a buscar un lote
     * acotado a encolar, no para contar cuantos hay en total.
     */
    public static function countMatchingProspects(Database $db, array $campaign): int
    {
        [$where, $params] = self::matchingWhereClause($campaign);
        $rows = $db->fetchAll(
            'SELECT p.phone FROM prospects p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at ASC',
            $params
        );
        $activeClientPhones = self::activeClientPhones($db);
        $count = 0;
        foreach ($rows as $row) {
            if (!isset($activeClientPhones[(string) $row['phone']])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Prospectos que matchean los filtros de la campania, excluyendo blacklist, cooldown,
     * mensajes ya pendientes, prospectos ya contactados EXITOSAMENTE por esta misma campania
     * (un intento 'failed' anterior no lo excluye — se puede reintentar), y telefonos que ya
     * son de un cliente activo. Trae hasta FILL_CANDIDATE_LIMIT candidatos (pensado para
     * encolar un lote, no para contar el total — usar countMatchingProspects() para eso).
     *
     * @return list<array<string, mixed>>
     */
    public static function matchingProspects(Database $db, array $campaign, ?int $limit = null): array
    {
        [$where, $params] = self::matchingWhereClause($campaign);

        $rows = $db->fetchAll(
            'SELECT p.* FROM prospects p WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.created_at ASC LIMIT ' . self::FILL_CANDIDATE_LIMIT,
            $params
        );

        $activeClientPhones = self::activeClientPhones($db);
        $matching = [];
        foreach ($rows as $row) {
            if (isset($activeClientPhones[(string) $row['phone']])) {
                continue;
            }
            $matching[] = $row;
            if ($limit !== null && count($matching) >= $limit) {
                break;
            }
        }

        return $matching;
    }

    private static function nextMatchingProspect(Database $db, array $campaign): ?array
    {
        $rows = self::matchingProspects($db, $campaign, 1);

        return $rows[0] ?? null;
    }

    /** @return array<string, true> Telefonos normalizados de clientes activos (is_active=1). */
    private static function activeClientPhones(Database $db): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $db->fetchAll("SELECT phone FROM clients WHERE is_active = 1 AND phone IS NOT NULL AND phone <> ''");
        foreach ($rows as $r) {
            $norm = ArgentinePhoneNormalizer::normalize((string) $r['phone']);
            if ($norm !== null) {
                $cache[$norm] = true;
            }
        }

        return $cache;
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
