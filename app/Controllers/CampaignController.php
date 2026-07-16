<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\OutreachScheduler;
use App\Models\Database;

final class CampaignController extends Controller
{
    private const ALLOWED_TRANSITIONS = [
        'borrador' => ['activa'],
        'activa' => ['pausada', 'finalizada'],
        'pausada' => ['activa', 'finalizada'],
        'finalizada' => [],
    ];

    public function index(): void
    {
        $db = Database::getInstance();
        $campaigns = $db->fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM outreach_queue oq WHERE oq.campaign_id = c.id AND oq.status = 'sent') AS total_sent,
                    (SELECT COUNT(*) FROM outreach_queue oq WHERE oq.campaign_id = c.id AND oq.status IN ('queued', 'claimed')) AS total_pending
             FROM outreach_campaigns c
             ORDER BY FIELD(c.status, 'activa', 'pausada', 'borrador', 'finalizada'), c.created_at DESC"
        );
        $this->view('campaigns/index', [
            'title' => 'Campañas',
            'campaigns' => $campaigns,
            'businessTypeLabels' => ProspectController::businessTypeLabels(),
        ]);
    }

    public function create(): void
    {
        $this->view('campaigns/form', [
            'title' => 'Nueva campaña',
            'businessTypeLabels' => ProspectController::businessTypeLabels(),
            'statusLabels' => ProspectController::statusLabels(),
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/campanas/crear');
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/prospeccion/campanas/crear');
        }
        unset($data['errors']);
        $db = Database::getInstance();
        $id = $db->insert('outreach_campaigns', $data);
        flash('success', 'Campaña creada en borrador. Revisá el dry-run antes de activarla.');
        redirect('/prospeccion/campanas/' . $id);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $campaign = $db->fetch('SELECT * FROM outreach_campaigns WHERE id = ?', [(int) $id]);
        if (!$campaign) {
            flash('error', 'Campaña no encontrada.');
            redirect('/prospeccion/campanas');
            return;
        }

        $dryRun = null;
        $queueStats = null;
        if ($campaign['status'] === 'borrador') {
            $matching = OutreachScheduler::matchingProspects($db, $campaign);
            $count = count($matching);
            $sample = array_slice($matching, 0, 20);
            $dryRun = [
                'count' => $count,
                'sample' => array_map(
                    static function (array $p) use ($db) {
                        $template = OutreachScheduler::findTemplateForStage($db, 'primer_contacto', (string) $p['business_type']);
                        $body = $template !== null ? OutreachScheduler::renderTemplate((string) $template['body'], $p) : '(sin plantilla disponible para este rubro)';

                        return ['prospect' => $p, 'rendered_body' => $body];
                    },
                    $sample
                ),
                'projected_days' => $count > 0 ? (int) ceil($count / max(1, (int) $campaign['daily_limit'])) : 0,
            ];
        } else {
            $queueStats = $db->fetch(
                "SELECT
                    SUM(status = 'sent') AS sent,
                    SUM(status = 'failed') AS failed,
                    SUM(status IN ('queued', 'claimed')) AS pending,
                    SUM(status = 'sent' AND DATE(sent_at) = CURDATE()) AS sent_today
                 FROM outreach_queue WHERE campaign_id = ?",
                [(int) $id]
            );
            $queueStats['remaining_matching'] = count(OutreachScheduler::matchingProspects($db, $campaign));
        }

        $this->view('campaigns/show', [
            'title' => (string) $campaign['name'],
            'campaign' => $campaign,
            'dryRun' => $dryRun,
            'queueStats' => $queueStats,
            'allowedTransitions' => self::ALLOWED_TRANSITIONS[$campaign['status']] ?? [],
        ]);
    }

    public function changeStatus(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/campanas/' . $id);
        }
        $db = Database::getInstance();
        $campaign = $db->fetch('SELECT * FROM outreach_campaigns WHERE id = ?', [(int) $id]);
        if (!$campaign) {
            flash('error', 'Campaña no encontrada.');
            redirect('/prospeccion/campanas');
            return;
        }
        $target = (string) $this->input('status', '');
        $allowed = self::ALLOWED_TRANSITIONS[$campaign['status']] ?? [];
        if (!in_array($target, $allowed, true)) {
            flash('error', "No se puede pasar de '{$campaign['status']}' a '{$target}'.");
            redirect('/prospeccion/campanas/' . $id);
            return;
        }
        $db->update('outreach_campaigns', ['status' => $target], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado de la campaña actualizado.');
        redirect('/prospeccion/campanas/' . $id);
    }

    /** @return array<string, mixed> */
    private function validate(): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $businessType = trim((string) $this->input('filter_business_type', ''));
        if ($businessType !== '' && !array_key_exists($businessType, ProspectController::businessTypeLabels())) {
            $businessType = '';
        }
        $city = trim((string) $this->input('filter_city', ''));
        $status = trim((string) $this->input('filter_status', 'nuevo'));
        if (!array_key_exists($status, ProspectController::statusLabels())) {
            $status = 'nuevo';
        }
        $dailyLimit = (int) $this->input('daily_limit', 20);
        $dailyLimit = max(1, min(OutreachScheduler::dailyCap(), $dailyLimit));

        return [
            'errors' => $errors,
            'name' => $name,
            'filter_business_type' => $businessType !== '' ? $businessType : null,
            'filter_city' => $city !== '' ? $city : null,
            'filter_status' => $status,
            'daily_limit' => $dailyLimit,
        ];
    }
}
