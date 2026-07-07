<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ArgentinePhoneNormalizer;
use App\Helpers\OutreachScheduler;
use App\Models\Database;

/**
 * API para el worker externo de WhatsApp (worker/whatsapp_worker.py). Rutas publicas
 * (sin sesion admin) autenticadas por header X-Outreach-Token contra el setting
 * outreach_api_token.
 */
final class OutreachApiController extends Controller
{
    public function nextBatch(): void
    {
        if (!$this->authenticate()) {
            return;
        }
        $db = Database::getInstance();
        $result = OutreachScheduler::nextBatch($db, 5);
        $this->logApi('next-batch', ['paused' => $result['paused'], 'count' => count($result['messages'])]);
        $this->json([
            'paused' => $result['paused'],
            'messages' => $result['messages'],
            'settings' => [
                'min_delay' => (int) (setting('outreach_min_delay_seconds', '60') ?? '60'),
                'max_delay' => (int) (setting('outreach_max_delay_seconds', '180') ?? '180'),
            ],
        ]);
    }

    public function report(): void
    {
        if (!$this->authenticate()) {
            return;
        }
        $payload = $this->jsonInput();
        $uuid = trim((string) ($payload['uuid'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $error = isset($payload['error']) ? trim((string) $payload['error']) : null;
        if ($uuid === '' || !in_array($status, ['sent', 'failed'], true)) {
            $this->json(['success' => false, 'error' => 'Payload inválido.'], 422);
            return;
        }

        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM outreach_queue WHERE uuid = ?', [$uuid]);
        if (!$row) {
            $this->logApi('report', ['uuid' => $uuid, 'status' => $status, 'result' => 'not_found']);
            $this->json(['success' => false, 'error' => 'Mensaje no encontrado.'], 404);
            return;
        }
        if ($row['status'] === 'sent') {
            $this->json(['success' => true, 'idempotent' => true]);
            return;
        }

        if ($status === 'sent') {
            $db->update(
                'outreach_queue',
                ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => (int) $row['id']]
            );
            $campaignId = $row['campaign_id'] !== null ? (int) $row['campaign_id'] : null;
            $this->applySentCascade($db, (int) $row['prospect_id'], $campaignId);
        } else {
            $db->update(
                'outreach_queue',
                ['status' => 'failed', 'error' => $error],
                'id = :id',
                ['id' => (int) $row['id']]
            );
            $this->logProspectEvent($db, (int) $row['prospect_id'], 'mensaje_fallido', $error);
        }

        $this->logApi('report', ['uuid' => $uuid, 'status' => $status]);
        $this->json(['success' => true]);
    }

    public function heartbeat(): void
    {
        if (!$this->authenticate()) {
            return;
        }
        $payload = $this->jsonInput();
        $version = trim((string) ($payload['version'] ?? ''));
        $sentToday = (int) ($payload['sent_today'] ?? 0);
        $lastError = trim((string) ($payload['last_error'] ?? ''));

        Database::getInstance()->update('outreach_worker_status', [
            'last_heartbeat' => date('Y-m-d H:i:s'),
            'worker_version' => $version !== '' ? $version : null,
            'messages_sent_today' => $sentToday,
            'last_error' => $lastError !== '' ? $lastError : null,
        ], 'id = 1');

        $this->logApi('heartbeat', ['version' => $version, 'sent_today' => $sentToday]);
        $this->json(['success' => true]);
    }

    /**
     * Recibe respuestas leidas por el worker (chats no leidos de WhatsApp Web).
     * Si el telefono no matchea ningun prospecto, se ignora sin guardar nada
     * (puede ser un chat personal del usuario — no es asunto del sistema).
     */
    public function responses(): void
    {
        if (!$this->authenticate()) {
            return;
        }
        $items = $this->jsonInputList();
        $db = Database::getInstance();
        $matched = 0;
        $optedOut = 0;
        $unmatched = 0;
        $invalid = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $invalid++;
                continue;
            }
            $phoneRaw = trim((string) ($item['phone'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));
            if ($phoneRaw === '' || $body === '') {
                $invalid++;
                continue;
            }
            $phone = ArgentinePhoneNormalizer::normalize($phoneRaw);
            if ($phone === null) {
                $invalid++;
                continue;
            }

            $prospect = $db->fetch('SELECT * FROM prospects WHERE phone = ?', [$phone]);
            if (!$prospect) {
                $unmatched++;
                continue;
            }

            $prospectId = (int) $prospect['id'];
            $receivedAt = $this->parseReceivedAt((string) ($item['received_at'] ?? ''));
            $db->insert('prospect_responses', [
                'prospect_id' => $prospectId,
                'body' => $body,
                'received_at' => $receivedAt,
                'processed' => 0,
            ]);
            $this->logProspectEvent($db, $prospectId, 'respondio', mb_substr($body, 0, 200));

            if (in_array($prospect['status'], ['contactado', 'sin_respuesta'], true)) {
                $db->update('prospects', ['status' => 'respondio'], 'id = :id', ['id' => $prospectId]);
            }
            $db->query(
                "UPDATE outreach_queue SET status = 'cancelled' WHERE prospect_id = ? AND status IN ('queued', 'claimed')",
                [$prospectId]
            );

            if ($this->containsOptOutKeyword($body)) {
                $db->update(
                    'prospects',
                    ['status' => 'no_interesado', 'blacklisted' => 1],
                    'id = :id',
                    ['id' => $prospectId]
                );
                $this->logProspectEvent($db, $prospectId, 'opt_out', null);
                $optedOut++;
            }

            $matched++;
        }

        $this->logApi('responses', [
            'matched' => $matched,
            'opted_out' => $optedOut,
            'unmatched' => $unmatched,
            'invalid' => $invalid,
        ]);
        $this->json(['success' => true, 'matched' => $matched, 'opted_out' => $optedOut, 'unmatched' => $unmatched]);
    }

    private function parseReceivedAt(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return date('Y-m-d H:i:s');
        }
        $ts = strtotime($raw);

        return $ts === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $ts);
    }

    private function containsOptOutKeyword(string $body): bool
    {
        $normalized = $this->stripAccents(mb_strtolower($body));
        $raw = (string) (setting('outreach_optout_keywords', '') ?? '');
        foreach (explode(',', $raw) as $keyword) {
            $keyword = trim($this->stripAccents(mb_strtolower($keyword)));
            if ($keyword !== '' && str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function stripAccents(string $s): string
    {
        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);
    }

    private function applySentCascade(Database $db, int $prospectId, ?int $campaignId): void
    {
        $prospect = $db->fetch('SELECT * FROM prospects WHERE id = ?', [$prospectId]);
        if (!$prospect) {
            return;
        }
        $update = [
            'last_contacted_at' => date('Y-m-d H:i:s'),
            'contact_attempts' => (int) $prospect['contact_attempts'] + 1,
        ];
        if ($prospect['status'] === 'nuevo') {
            $update['status'] = 'contactado';
        }
        $db->update('prospects', $update, 'id = :id', ['id' => $prospectId]);
        $this->logProspectEvent(
            $db,
            $prospectId,
            'mensaje_enviado',
            $campaignId !== null ? "Campaña #{$campaignId}" : 'Envío manual'
        );
    }

    private function logProspectEvent(Database $db, int $prospectId, string $eventType, ?string $detail): void
    {
        $db->insert('prospect_events', [
            'prospect_id' => $prospectId,
            'event_type' => $eventType,
            'detail' => $detail,
        ]);
    }

    private function authenticate(): bool
    {
        $header = $_SERVER['HTTP_X_OUTREACH_TOKEN'] ?? '';
        $expected = (string) (setting('outreach_api_token', '') ?? '');
        if ($expected === '' || !is_string($header) || $header === '' || !hash_equals($expected, (string) $header)) {
            $this->logApi('auth_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '?']);
            $this->json(['success' => false, 'error' => 'unauthorized'], 401);

            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<mixed> */
    private function jsonInputList(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param array<string, mixed> $context */
    private function logApi(string $action, array $context): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $action . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents(STORAGE_PATH . '/logs/outreach_api.log', $line, FILE_APPEND | LOCK_EX);
    }
}
