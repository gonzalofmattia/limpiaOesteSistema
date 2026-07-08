<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\OutreachAiAssistant;
use App\Models\Database;

final class InboxController extends Controller
{
    private const AI_BATCH_LIMIT = 5;

    public function index(): void
    {
        $db = Database::getInstance();
        $prospects = $db->fetchAll(
            "SELECT p.*,
                    (SELECT MAX(pr.received_at) FROM prospect_responses pr WHERE pr.prospect_id = p.id AND pr.processed = 0) AS last_pending_at
             FROM prospects p
             WHERE EXISTS (SELECT 1 FROM prospect_responses pr WHERE pr.prospect_id = p.id AND pr.processed = 0)
             ORDER BY last_pending_at ASC"
        );

        $threads = [];
        foreach ($prospects as $p) {
            $threads[(int) $p['id']] = $db->fetchAll(
                "(SELECT 'out' AS direction, sent_at AS ts, rendered_body AS body FROM outreach_queue WHERE prospect_id = ? AND status = 'sent')
                 UNION ALL
                 (SELECT 'in' AS direction, received_at AS ts, body FROM prospect_responses WHERE prospect_id = ?)
                 ORDER BY ts ASC",
                [(int) $p['id'], (int) $p['id']]
            );
        }

        $this->processUnclassifiedResponses($db, $threads);

        $suggestions = [];
        foreach ($prospects as $p) {
            $suggestions[(int) $p['id']] = $db->fetch(
                "SELECT * FROM prospect_responses WHERE prospect_id = ? AND processed = 0 ORDER BY received_at DESC LIMIT 1",
                [(int) $p['id']]
            );
        }

        $this->view('inbox/index', [
            'title' => 'Bandeja',
            'subtitle' => 'Respuestas de prospectos por atender',
            'prospects' => $prospects,
            'threads' => $threads,
            'suggestions' => $suggestions,
            'statusLabels' => ProspectController::statusLabels(),
        ]);
    }

    public function discardSuggestion(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/bandeja');
        }
        Database::getInstance()->query(
            "UPDATE prospect_responses SET ai_intent = NULL, ai_suggested_reply = NULL
             WHERE prospect_id = ? AND processed = 0",
            [(int) $id]
        );
        redirect('/prospeccion/bandeja');
    }

    /** @param array<int, list<array<string, mixed>>> $threads */
    private function processUnclassifiedResponses(Database $db, array $threads): void
    {
        $pending = $db->fetchAll(
            "SELECT * FROM prospect_responses WHERE processed = 0 AND ai_processed_at IS NULL
             ORDER BY received_at ASC LIMIT " . self::AI_BATCH_LIMIT
        );
        foreach ($pending as $resp) {
            $prospectId = (int) $resp['prospect_id'];
            $prospect = $db->fetch('SELECT * FROM prospects WHERE id = ?', [$prospectId]);
            if (!$prospect) {
                continue;
            }
            $result = OutreachAiAssistant::classifyAndDraft($prospect, $threads[$prospectId] ?? []);
            $db->update(
                'prospect_responses',
                [
                    'ai_intent' => $result['success'] ? $result['intent'] : null,
                    'ai_suggested_reply' => $result['success'] ? $result['reply'] : null,
                    'ai_processed_at' => date('Y-m-d H:i:s'),
                ],
                'id = :id',
                ['id' => (int) $resp['id']]
            );
        }
    }

    public function markResponded(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/bandeja');
        }
        $db = Database::getInstance();
        $prospectId = (int) $id;
        $db->query(
            'UPDATE prospect_responses SET processed = 1 WHERE prospect_id = ? AND processed = 0',
            [$prospectId]
        );
        $db->insert('prospect_events', [
            'prospect_id' => $prospectId,
            'event_type' => 'respondido_manual',
            'detail' => null,
        ]);
        flash('success', 'Marcado como respondido.');
        redirect('/prospeccion/bandeja');
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(DISTINCT prospect_id) FROM prospect_responses WHERE processed = 0'
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
