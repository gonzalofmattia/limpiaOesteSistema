<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class InboxController extends Controller
{
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

        $this->view('inbox/index', [
            'title' => 'Bandeja',
            'subtitle' => 'Respuestas de prospectos por atender',
            'prospects' => $prospects,
            'threads' => $threads,
            'statusLabels' => ProspectController::statusLabels(),
        ]);
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
