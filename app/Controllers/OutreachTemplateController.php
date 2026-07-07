<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class OutreachTemplateController extends Controller
{
    private const STAGES = ['primer_contacto', 'seguimiento_7d', 'recontacto'];

    private const STAGE_LABELS = [
        'primer_contacto' => 'Primer contacto',
        'seguimiento_7d' => 'Seguimiento (7 días)',
        'recontacto' => 'Recontacto',
    ];

    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT * FROM outreach_templates ORDER BY stage, business_type, name');
        $this->view('outreach_templates/index', [
            'title' => 'Plantillas de mensajes',
            'templates' => $rows,
            'businessTypeLabels' => $this->businessTypeLabels(),
            'stageLabels' => self::STAGE_LABELS,
        ]);
    }

    public function create(): void
    {
        $this->view('outreach_templates/form', [
            'title' => 'Nueva plantilla',
            'template' => null,
            'businessTypeLabels' => $this->businessTypeLabels(),
            'stageLabels' => self::STAGE_LABELS,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/plantillas/crear');
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/prospeccion/plantillas/crear');
        }
        unset($data['errors']);
        Database::getInstance()->insert('outreach_templates', $data);
        flash('success', 'Plantilla creada.');
        redirect('/prospeccion/plantillas');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $template = $db->fetch('SELECT * FROM outreach_templates WHERE id = ?', [(int) $id]);
        if (!$template) {
            flash('error', 'Plantilla no encontrada.');
            redirect('/prospeccion/plantillas');
            return;
        }
        $this->view('outreach_templates/form', [
            'title' => 'Editar plantilla',
            'template' => $template,
            'businessTypeLabels' => $this->businessTypeLabels(),
            'stageLabels' => self::STAGE_LABELS,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/plantillas/' . $id . '/editar');
        }
        $db = Database::getInstance();
        if (!$db->fetch('SELECT id FROM outreach_templates WHERE id = ?', [(int) $id])) {
            flash('error', 'Plantilla no encontrada.');
            redirect('/prospeccion/plantillas');
            return;
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/prospeccion/plantillas/' . $id . '/editar');
        }
        unset($data['errors']);
        $db->update('outreach_templates', $data, 'id = :id', ['id' => (int) $id]);
        flash('success', 'Plantilla actualizada.');
        redirect('/prospeccion/plantillas');
    }

    public function toggle(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/plantillas');
        }
        $db = Database::getInstance();
        $template = $db->fetch('SELECT id, active FROM outreach_templates WHERE id = ?', [(int) $id]);
        if (!$template) {
            flash('error', 'No encontrada.');
            redirect('/prospeccion/plantillas');
            return;
        }
        $db->update('outreach_templates', ['active' => $template['active'] ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado actualizado.');
        redirect('/prospeccion/plantillas');
    }

    /** @return array<string, string> */
    private function businessTypeLabels(): array
    {
        return ['todos' => 'Todos'] + ProspectController::businessTypeLabels();
    }

    /** @return array<string, mixed> */
    private function validate(): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $businessType = (string) $this->input('business_type', 'todos');
        if (!array_key_exists($businessType, $this->businessTypeLabels())) {
            $businessType = 'todos';
        }
        $stage = (string) $this->input('stage', '');
        if (!in_array($stage, self::STAGES, true)) {
            $errors[] = 'La etapa seleccionada no es válida.';
        }
        $body = trim((string) $this->input('body', ''));
        if ($body === '') {
            $errors[] = 'El mensaje es obligatorio.';
        }
        $active = $this->input('active') ? 1 : 0;

        return [
            'errors' => $errors,
            'name' => $name,
            'business_type' => $businessType,
            'stage' => $stage,
            'body' => $body,
            'active' => $active,
        ];
    }
}
