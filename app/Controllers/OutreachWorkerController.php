<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\OutreachScheduler;
use App\Helpers\SettingsCache;
use App\Models\Database;

final class OutreachWorkerController extends Controller
{
    public function queue(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT oq.*, p.name AS prospect_name, p.phone, c.name AS campaign_name
             FROM outreach_queue oq
             INNER JOIN prospects p ON p.id = oq.prospect_id
             LEFT JOIN outreach_campaigns c ON c.id = oq.campaign_id
             WHERE oq.scheduled_for = CURDATE()
             ORDER BY oq.created_at DESC"
        );
        $worker = $db->fetch('SELECT * FROM outreach_worker_status WHERE id = 1');
        $this->view('outreach_worker/queue', [
            'title' => 'Cola de envíos de hoy',
            'rows' => $rows,
            'worker' => $worker,
            'dailyCap' => OutreachScheduler::dailyCap(),
        ]);
    }

    public function togglePause(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion');
        }
        $db = Database::getInstance();
        $current = OutreachScheduler::isGloballyPaused();
        $db->query(
            'UPDATE settings SET setting_value = ? WHERE setting_key = ?',
            [$current ? '0' : '1', 'outreach_global_pause']
        );
        SettingsCache::forget();
        flash('success', $current ? 'Envíos reanudados.' : 'Envíos pausados.');
        redirect('/prospeccion');
    }
}
