<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ArgentinePhoneNormalizer;
use App\Helpers\OutreachScheduler;
use App\Models\Database;
use PDOException;

final class ProspectController extends Controller
{
    private const BUSINESS_TYPES = [
        'parrilla', 'panaderia', 'restaurante', 'bar', 'hotel',
        'clinica', 'escuela', 'revendedor', 'otro',
    ];

    private const BUSINESS_TYPE_LABELS = [
        'parrilla' => 'Parrilla',
        'panaderia' => 'Panadería',
        'restaurante' => 'Restaurante',
        'bar' => 'Bar',
        'hotel' => 'Hotel',
        'clinica' => 'Clínica',
        'escuela' => 'Escuela',
        'revendedor' => 'Revendedor',
        'otro' => 'Otro',
    ];

    private const STATUSES = [
        'nuevo', 'contactado', 'respondio', 'interesado', 'visita_agendada',
        'muestra_entregada', 'cotizado', 'cliente', 'no_interesado', 'sin_respuesta',
    ];

    private const STATUS_LABELS = [
        'nuevo' => 'Nuevo',
        'contactado' => 'Contactado',
        'respondio' => 'Respondió',
        'interesado' => 'Interesado',
        'visita_agendada' => 'Visita agendada',
        'muestra_entregada' => 'Muestra entregada',
        'cotizado' => 'Cotizado',
        'cliente' => 'Cliente',
        'no_interesado' => 'No interesado',
        'sin_respuesta' => 'Sin respuesta',
    ];

    public static function businessTypeLabels(): array
    {
        return self::BUSINESS_TYPE_LABELS;
    }

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public function instructions(): void
    {
        $this->view('prospects/instrucciones', [
            'title' => 'Instrucciones',
            'subtitle' => 'Cómo usar el módulo de Prospección',
        ]);
    }

    public function dashboard(): void
    {
        $db = Database::getInstance();

        $funnel = array_fill_keys(self::STATUSES, 0);
        $rows = $db->fetchAll('SELECT status, COUNT(*) AS total FROM prospects GROUP BY status');
        foreach ($rows as $r) {
            $st = (string) ($r['status'] ?? '');
            if (isset($funnel[$st])) {
                $funnel[$st] = (int) $r['total'];
            }
        }

        $contactedLast7 = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM prospects WHERE last_contacted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        $latestEventSub = '(SELECT pe2.event_type FROM prospect_events pe2
                             WHERE pe2.prospect_id = p.id
                             ORDER BY pe2.created_at DESC, pe2.id DESC LIMIT 1)';

        $pendingResponses = $db->fetchAll(
            "SELECT p.id, p.name, p.phone, p.city, p.updated_at
             FROM prospects p
             WHERE p.status = 'respondio'
               AND COALESCE({$latestEventSub}, '') <> 'nota'
             ORDER BY p.updated_at ASC
             LIMIT 50"
        );

        $overdueFollowups = $db->fetchAll(
            "SELECT p.id, p.name, p.phone, p.city, p.last_contacted_at
             FROM prospects p
             WHERE p.status = 'contactado'
               AND p.last_contacted_at IS NOT NULL
               AND p.last_contacted_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY p.last_contacted_at ASC
             LIMIT 50"
        );

        $worker = null;
        $sentToday = 0;
        $dailyCap = 0;
        $globalPaused = false;
        try {
            $worker = $db->fetch('SELECT * FROM outreach_worker_status WHERE id = 1');
            $sentToday = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM outreach_queue WHERE status = 'sent' AND DATE(sent_at) = CURDATE()"
            );
            $dailyCap = OutreachScheduler::dailyCap();
            $globalPaused = OutreachScheduler::isGloballyPaused();
        } catch (\Throwable) {
            // Motor de envios (Fase 2) todavia no esta migrado.
        }

        $this->view('prospects/dashboard', [
            'title' => 'Prospección',
            'subtitle' => 'Embudo de prospección B2B',
            'funnel' => $funnel,
            'statusLabels' => self::STATUS_LABELS,
            'contactedLast7' => $contactedLast7,
            'pendingResponses' => $pendingResponses,
            'overdueFollowups' => $overdueFollowups,
            'worker' => $worker,
            'sentToday' => $sentToday,
            'dailyCap' => $dailyCap,
            'globalPaused' => $globalPaused,
        ]);
    }

    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $status = trim((string) $this->query('status', ''));
        $businessType = trim((string) $this->query('business_type', ''));
        $city = trim((string) $this->query('city', ''));

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.phone LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }
        if ($businessType !== '' && in_array($businessType, self::BUSINESS_TYPES, true)) {
            $where[] = 'p.business_type = :business_type';
            $params['business_type'] = $businessType;
        }
        if ($city !== '') {
            $where[] = 'p.city = :city';
            $params['city'] = $city;
        }
        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM prospects p {$whereSql}", $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $db->fetchAll(
            "SELECT p.*, c.name AS client_name
             FROM prospects p
             LEFT JOIN clients c ON c.id = p.client_id
             {$whereSql}
             ORDER BY p.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $cities = $db->fetchAll(
            "SELECT DISTINCT city FROM prospects WHERE city IS NOT NULL AND city <> '' ORDER BY city"
        );

        $this->view('prospects/index', [
            'title' => 'Prospectos',
            'prospects' => $rows,
            'search' => $search,
            'status' => $status,
            'business_type' => $businessType,
            'city' => $city,
            'cities' => $cities,
            'statusLabels' => self::STATUS_LABELS,
            'businessTypeLabels' => self::BUSINESS_TYPE_LABELS,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function create(): void
    {
        $this->view('prospects/form', [
            'title' => 'Nuevo prospecto',
            'prospect' => null,
            'businessTypeLabels' => self::BUSINESS_TYPE_LABELS,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/prospectos/crear');
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/prospeccion/prospectos/crear');
        }
        unset($data['errors']);
        $db = Database::getInstance();
        try {
            $id = $db->insert('prospects', $data);
        } catch (PDOException $e) {
            $message = stripos($e->getMessage(), 'duplicate') !== false
                ? 'Ya existe un prospecto con ese teléfono.'
                : 'No se pudo crear el prospecto.';
            flash('error', $message);
            redirect('/prospeccion/prospectos/crear');
            return;
        }
        $this->logEvent($db, (int) $id, 'creado', 'Alta manual');
        flash('success', 'Prospecto creado.');
        redirect('/prospeccion/prospectos');
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $prospect = $db->fetch(
            'SELECT p.*, c.name AS client_name FROM prospects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [(int) $id]
        );
        if (!$prospect) {
            flash('error', 'Prospecto no encontrado.');
            redirect('/prospeccion/prospectos');
            return;
        }
        $events = $db->fetchAll(
            'SELECT * FROM prospect_events WHERE prospect_id = ? ORDER BY created_at DESC, id DESC',
            [(int) $id]
        );
        $this->view('prospects/show', [
            'title' => (string) $prospect['name'],
            'prospect' => $prospect,
            'events' => $events,
            'statuses' => self::STATUSES,
            'statusLabels' => self::STATUS_LABELS,
            'businessTypeLabels' => self::BUSINESS_TYPE_LABELS,
        ]);
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $prospect = $db->fetch('SELECT * FROM prospects WHERE id = ?', [(int) $id]);
        if (!$prospect) {
            flash('error', 'Prospecto no encontrado.');
            redirect('/prospeccion/prospectos');
            return;
        }
        $this->view('prospects/form', [
            'title' => 'Editar prospecto',
            'prospect' => $prospect,
            'businessTypeLabels' => self::BUSINESS_TYPE_LABELS,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/prospectos/' . $id . '/editar');
        }
        $db = Database::getInstance();
        $prospectId = (int) $id;
        if (!$db->fetch('SELECT id FROM prospects WHERE id = ?', [$prospectId])) {
            flash('error', 'Prospecto no encontrado.');
            redirect('/prospeccion/prospectos');
            return;
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/prospeccion/prospectos/' . $id . '/editar');
        }
        unset($data['errors']);
        try {
            $db->update('prospects', $data, 'id = :id', ['id' => $prospectId]);
        } catch (PDOException $e) {
            $message = stripos($e->getMessage(), 'duplicate') !== false
                ? 'Ya existe otro prospecto con ese teléfono.'
                : 'No se pudo actualizar el prospecto.';
            flash('error', $message);
            redirect('/prospeccion/prospectos/' . $id . '/editar');
            return;
        }
        flash('success', 'Prospecto actualizado.');
        redirect('/prospeccion/prospectos/' . $id);
    }

    public function changeStatus(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/prospectos/' . $id);
        }
        $status = (string) $this->input('status', '');
        if (!in_array($status, self::STATUSES, true)) {
            flash('error', 'Estado inválido.');
            redirect('/prospeccion/prospectos/' . $id);
        }
        $db = Database::getInstance();
        $prospectId = (int) $id;
        $prospect = $db->fetch('SELECT * FROM prospects WHERE id = ?', [$prospectId]);
        if (!$prospect) {
            flash('error', 'Prospecto no encontrado.');
            redirect('/prospeccion/prospectos');
            return;
        }
        $oldStatus = (string) $prospect['status'];
        if ($oldStatus === $status) {
            flash('info', 'El prospecto ya está en ese estado.');
            redirect('/prospeccion/prospectos/' . $id);
            return;
        }

        $update = ['status' => $status];
        if ($status === 'no_interesado') {
            $update['blacklisted'] = 1;
        }
        if ($status === 'contactado') {
            $update['last_contacted_at'] = date('Y-m-d H:i:s');
            $update['contact_attempts'] = (int) $prospect['contact_attempts'] + 1;
        }
        $db->update('prospects', $update, 'id = :id', ['id' => $prospectId]);

        $oldLabel = self::STATUS_LABELS[$oldStatus] ?? $oldStatus;
        $newLabel = self::STATUS_LABELS[$status] ?? $status;
        $this->logEvent($db, $prospectId, 'estado_cambiado', "{$oldLabel} → {$newLabel}");

        flash('success', 'Estado actualizado.');
        redirect('/prospeccion/prospectos/' . $id);
    }

    public function addNote(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/prospectos/' . $id);
        }
        $note = trim((string) $this->input('note', ''));
        if ($note === '') {
            flash('error', 'La nota no puede estar vacía.');
            redirect('/prospeccion/prospectos/' . $id);
        }
        $db = Database::getInstance();
        $prospectId = (int) $id;
        if (!$db->fetch('SELECT id FROM prospects WHERE id = ?', [$prospectId])) {
            flash('error', 'Prospecto no encontrado.');
            redirect('/prospeccion/prospectos');
            return;
        }
        $this->logEvent($db, $prospectId, 'nota', $note);
        flash('success', 'Nota agregada.');
        redirect('/prospeccion/prospectos/' . $id);
    }

    public function importForm(): void
    {
        $report = $_SESSION['prospect_import_report'] ?? null;
        unset($_SESSION['prospect_import_report']);
        $this->view('prospects/importar', [
            'title' => 'Importar prospectos',
            'report' => $report,
        ]);
    }

    public function import(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/importar');
        }
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            flash('error', 'PhpSpreadsheet no está instalado. Ejecutá composer install.');
            redirect('/prospeccion/importar');
        }
        if (!isset($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Seleccioná un archivo Excel (.xlsx).');
            redirect('/prospeccion/importar');
        }
        $tmp = $_FILES['xlsx']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            flash('error', 'Archivo inválido.');
            redirect('/prospeccion/importar');
        }
        $ext = strtolower((string) pathinfo((string) ($_FILES['xlsx']['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            flash('error', 'Solo se acepta formato .xlsx.');
            redirect('/prospeccion/importar');
        }
        if (!class_exists(\ZipArchive::class)) {
            flash('error', 'Falta la extensión PHP zip (ZipArchive), necesaria para leer archivos .xlsx.');
            redirect('/prospeccion/importar');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo leer el Excel: ' . $e->getMessage());
            redirect('/prospeccion/importar');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();
        if ($data === []) {
            flash('error', 'Archivo vacío.');
            redirect('/prospeccion/importar');
        }

        $headerRow = array_shift($data);
        $headers = array_map(fn ($h) => $this->normalizeHeader($h), $headerRow);
        $map = $this->mapImportHeaders($headers);
        if (!isset($map['name']) || !isset($map['phone'])) {
            flash('error', 'Faltan columnas obligatorias (nombre, telefono) en la fila 1.');
            redirect('/prospeccion/importar');
        }

        $db = Database::getInstance();

        $existingPhones = [];
        foreach ($db->fetchAll('SELECT phone FROM prospects') as $r) {
            $existingPhones[(string) $r['phone']] = true;
        }
        $clientPhones = [];
        foreach ($db->fetchAll("SELECT id, name, phone FROM clients WHERE phone IS NOT NULL AND phone <> ''") as $r) {
            $norm = ArgentinePhoneNormalizer::normalize((string) $r['phone']);
            if ($norm !== null) {
                $clientPhones[$norm] = (string) $r['name'];
            }
        }

        $imported = 0;
        $duplicated = 0;
        $existingClients = 0;
        $invalid = [];

        foreach ($data as $rowIdx => $row) {
            $lineNumber = $rowIdx + 2;
            $row = array_values($row);
            $get = static function (?int $idx) use ($row): string {
                if ($idx === null || !isset($row[$idx])) {
                    return '';
                }
                $v = $row[$idx];
                return is_scalar($v) ? trim((string) $v) : '';
            };

            $name = $get($map['name'] ?? null);
            $phoneRaw = $get($map['phone'] ?? null);
            $businessTypeRaw = $get($map['business_type'] ?? null);
            $city = $get($map['city'] ?? null);
            $source = $get($map['source'] ?? null);

            if ($name === '' && $phoneRaw === '') {
                continue;
            }
            if ($name === '') {
                $invalid[] = ['line' => $lineNumber, 'reason' => 'Falta nombre'];
                continue;
            }
            $phone = ArgentinePhoneNormalizer::normalize($phoneRaw);
            if ($phone === null) {
                $invalid[] = ['line' => $lineNumber, 'reason' => 'Teléfono inválido: ' . $phoneRaw];
                continue;
            }
            if (isset($clientPhones[$phone])) {
                $existingClients++;
                continue;
            }
            if (isset($existingPhones[$phone])) {
                $duplicated++;
                continue;
            }

            $businessType = $this->matchBusinessType($businessTypeRaw);

            try {
                $newId = $db->insert('prospects', [
                    'name' => $name,
                    'business_type' => $businessType,
                    'phone' => $phone,
                    'city' => $city !== '' ? $city : null,
                    'source' => $source !== '' ? $source : 'importacion',
                    'status' => 'nuevo',
                ]);
                $this->logEvent($db, (int) $newId, 'creado', 'Importado desde Excel');
                $existingPhones[$phone] = true;
                $imported++;
            } catch (\Throwable) {
                $invalid[] = ['line' => $lineNumber, 'reason' => 'Error al guardar el registro'];
            }
        }

        $_SESSION['prospect_import_report'] = [
            'imported' => $imported,
            'duplicated' => $duplicated,
            'existing_clients' => $existingClients,
            'invalid' => $invalid,
        ];
        flash('success', "Importación finalizada: {$imported} importados.");
        redirect('/prospeccion/importar');
    }

    /** @return array<string, mixed> */
    private function validate(): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $businessType = (string) $this->input('business_type', 'otro');
        if (!in_array($businessType, self::BUSINESS_TYPES, true)) {
            $businessType = 'otro';
        }
        $phoneRaw = trim((string) $this->input('phone', ''));
        $phone = $phoneRaw !== '' ? ArgentinePhoneNormalizer::normalize($phoneRaw) : null;
        if ($phoneRaw === '' || $phone === null) {
            $errors[] = 'El teléfono es obligatorio y debe ser un número argentino válido.';
        }

        return [
            'errors' => $errors,
            'name' => $name,
            'business_type' => $businessType,
            'phone' => $phone ?? '',
            'city' => $this->nullStr($this->input('city', '')),
            'source' => $this->nullStr($this->input('source', '')),
            'notes' => $this->nullStr($this->input('notes', '')),
        ];
    }

    private function logEvent(Database $db, int $prospectId, string $eventType, ?string $detail = null): void
    {
        $db->insert('prospect_events', [
            'prospect_id' => $prospectId,
            'event_type' => $eventType,
            'detail' => $detail,
        ]);
    }

    private function normalizeHeader(mixed $h): string
    {
        $s = strtolower(trim((string) $h));
        $s = str_replace([' ', '-'], '_', $s);
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);

        return $s;
    }

    /** @param list<string> $headers @return array<string, int> */
    private function mapImportHeaders(array $headers): array
    {
        $aliases = [
            'name' => ['nombre', 'name'],
            'business_type' => ['rubro', 'business_type', 'tipo_negocio', 'categoria'],
            'phone' => ['telefono', 'phone', 'celular'],
            'city' => ['ciudad', 'city', 'localidad'],
            'source' => ['fuente', 'source', 'origen'],
        ];
        $map = [];
        foreach ($headers as $idx => $h) {
            foreach ($aliases as $field => $names) {
                if (in_array($h, $names, true) && !isset($map[$field])) {
                    $map[$field] = $idx;
                }
            }
        }

        return $map;
    }

    private function matchBusinessType(string $raw): string
    {
        $normalized = $this->normalizeHeader($raw);
        foreach (self::BUSINESS_TYPES as $type) {
            if ($normalized === $type) {
                return $type;
            }
        }

        return 'otro';
    }

    private function nullStr(mixed $v): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
