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

        // Fase 3 agrega aca, antes de los primeros contactos, el llenado de
        // seguimientos y recontactos vencidos (mayor prioridad).
        self::enqueueFirstContactsRoundRobin($db, $remainingCap);
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
                self::enqueueMessage($db, $c, $prospect, (int) $c['template_id']);
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

    public static function renderTemplate(string $body, array $prospect): string
    {
        return str_replace(
            ['{{nombre}}', '{{ciudad}}'],
            [(string) ($prospect['name'] ?? ''), (string) ($prospect['city'] ?? '')],
            $body
        );
    }

    /**
     * Prospectos que matchean los filtros de la campania, excluyendo blacklist, cooldown,
     * mensajes ya pendientes, prospectos ya contactados por esta misma campania, y telefonos
     * que ya son de un cliente activo. Sin $limit, devuelve todos (para dry-run/conteo).
     *
     * @return list<array<string, mixed>>
     */
    public static function matchingProspects(Database $db, array $campaign, ?int $limit = null): array
    {
        $cooldownDays = (int) (setting('outreach_prospect_cooldown_days', '7') ?? '7');
        $where = [
            'p.blacklisted = 0',
            'p.status = :filter_status',
            '(p.last_contacted_at IS NULL OR p.last_contacted_at < DATE_SUB(NOW(), INTERVAL :cooldown DAY))',
            "NOT EXISTS (SELECT 1 FROM outreach_queue oq WHERE oq.prospect_id = p.id AND oq.status IN ('queued', 'claimed'))",
            'NOT EXISTS (SELECT 1 FROM outreach_queue oq2 WHERE oq2.prospect_id = p.id AND oq2.campaign_id = :campaign_id)',
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
