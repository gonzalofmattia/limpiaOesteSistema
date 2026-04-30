<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
use App\Models\Database;
use PDOException;

final class ClientController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(c.name LIKE :search OR c.business_name LIKE :search OR c.phone LIKE :search OR c.city LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        try {
            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable) {
                $txAgg = ClientReceivableSummary::sqlTxAggByClientSubquery();
                $qAgg = ClientReceivableSummary::sqlQuotesAcceptedByClientSubquery();
                $hybrid = ClientReceivableSummary::sqlCaseHybridBalance();
                $total = (int) $db->fetchColumn(
                    "SELECT COUNT(*)
                     FROM clients c
                     {$whereSql}",
                    $params
                );
                $totalPages = max(1, (int) ceil($total / $perPage));
                if ($page > $totalPages) {
                    $page = $totalPages;
                }
                $offset = ($page - 1) * $perPage;
                $rows = $db->fetchAll(
                    "SELECT c.*, ROUND({$hybrid}, 2) AS effective_balance
                     FROM clients c
                     LEFT JOIN ({$txAgg}) tx ON tx.account_id = c.id
                     LEFT JOIN ({$qAgg}) q ON q.client_id = c.id
                     {$whereSql}
                     ORDER BY c.name
                     LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            } else {
                $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM clients c {$whereSql}", $params);
                $totalPages = max(1, (int) ceil($total / $perPage));
                if ($page > $totalPages) {
                    $page = $totalPages;
                }
                $offset = ($page - 1) * $perPage;
                $rows = $db->fetchAll(
                    "SELECT c.* FROM clients c {$whereSql} ORDER BY c.name LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            }
        } catch (\Throwable) {
            $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM clients c {$whereSql}", $params);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rows = $db->fetchAll(
                "SELECT c.* FROM clients c {$whereSql} ORDER BY c.name LIMIT {$perPage} OFFSET {$offset}",
                $params
            );
        }
        $this->view('clients/index', [
            'title' => 'Clientes',
            'clients' => $rows,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total ?? count($rows),
            'total_pages' => $totalPages ?? 1,
        ]);
    }

    public function create(): void
    {
        $this->view('clients/form', ['title' => 'Nuevo cliente', 'client' => null]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/clientes/crear');
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/clientes/crear');
        }
        unset($data['errors']);
        Database::getInstance()->insert('clients', $data);
        flash('success', 'Cliente creado.');
        redirect('/clientes');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $hasAccountTable = false;
        try {
            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable) {
                $txAgg = ClientReceivableSummary::sqlTxAggByClientSubquery();
                $qAgg = ClientReceivableSummary::sqlQuotesAcceptedByClientSubquery();
                $hybrid = ClientReceivableSummary::sqlCaseHybridBalance();
                $c = $db->fetch(
                    "SELECT c.*, ROUND({$hybrid}, 2) AS effective_balance
                     FROM clients c
                     LEFT JOIN ({$txAgg}) tx ON tx.account_id = c.id
                     LEFT JOIN ({$qAgg}) q ON q.client_id = c.id
                     WHERE c.id = ?",
                    [(int) $id]
                );
            } else {
                $c = $db->fetch('SELECT * FROM clients WHERE id = ?', [(int) $id]);
            }
        } catch (\Throwable) {
            $c = $db->fetch('SELECT * FROM clients WHERE id = ?', [(int) $id]);
        }
        if (!$c) {
            flash('error', 'No encontrado.');
            redirect('/clientes');
        }
        if ($hasAccountTable) {
            $c['effective_balance'] = round((float) ($c['effective_balance'] ?? 0), 2);
        } else {
            $c['effective_balance'] = (float) ($c['balance'] ?? 0);
        }
        $this->view('clients/form', ['title' => 'Editar cliente', 'client' => $c]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/clientes/' . $id . '/editar');
        }
        $db = Database::getInstance();
        if (!$db->fetch('SELECT id FROM clients WHERE id = ?', [(int) $id])) {
            flash('error', 'No encontrado.');
            redirect('/clientes');
        }
        $data = $this->validate();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/clientes/' . $id . '/editar');
        }
        unset($data['errors']);
        $db->update('clients', $data, 'id = :id', ['id' => (int) $id]);
        flash('success', 'Cliente actualizado.');
        redirect('/clientes');
    }

    public function delete(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/clientes');
            return;
        }
        $db = Database::getInstance();
        $clientId = (int) $id;
        $client = $db->fetch('SELECT id, name FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('/clientes');
            return;
        }

        $hasQuotes = (int) $db->fetchColumn('SELECT COUNT(*) FROM quotes WHERE client_id = ?', [$clientId]) > 0;
        if ($hasQuotes) {
            flash('error', 'No podés eliminar este cliente porque tiene presupuestos asociados.');
            redirect('/clientes');
            return;
        }

        try {
            $hasAccount = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccount) {
                $hasMovements = (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM account_transactions WHERE account_type = 'client' AND account_id = ?",
                    [$clientId]
                ) > 0;
                if ($hasMovements) {
                    flash('error', 'No podés eliminar este cliente porque tiene movimientos en cuenta corriente.');
                    redirect('/clientes');
                    return;
                }
            }
        } catch (\Throwable) {
            // Si no existe la tabla, continúa.
        }

        $db->delete('clients', 'id = :id', ['id' => $clientId]);
        flash('success', 'Cliente eliminado.');
        redirect('/clientes');
    }

    public function apiStore(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));

        if ($name === '') {
            $this->json(['success' => false, 'error' => 'El nombre es obligatorio.'], 422);
            return;
        }
        if ($phone === '') {
            $this->json(['success' => false, 'error' => 'El teléfono es obligatorio.'], 422);
            return;
        }

        $db = Database::getInstance();
        try {
            $id = $db->insert('clients', [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'city' => $city !== '' ? $city : null,
            ]);
            $client = $db->fetch('SELECT id, name, phone, city FROM clients WHERE id = ?', [(int) $id]);
            if (!$client) {
                $this->json(['success' => false, 'error' => 'No se pudo recuperar el cliente creado.'], 500);
                return;
            }
            $this->json([
                'success' => true,
                'client' => [
                    'id' => (int) $client['id'],
                    'name' => (string) $client['name'],
                    'phone' => $client['phone'],
                    'city' => $client['city'],
                ],
            ]);
        } catch (PDOException $e) {
            $message = stripos($e->getMessage(), 'duplicate') !== false
                || stripos($e->getMessage(), 'duplicado') !== false
                || stripos($e->getMessage(), '1062') !== false
                ? 'Ya existe un cliente con ese nombre.'
                : 'No se pudo crear el cliente.';
            $this->json(['success' => false, 'error' => $message], 422);
        } catch (\Throwable) {
            $this->json(['success' => false, 'error' => 'No se pudo crear el cliente.'], 500);
        }
    }

    /** @return array<string, mixed> */
    private function validate(): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        return [
            'errors' => $errors,
            'name' => $name,
            'business_name' => $this->nullStr($this->input('business_name', '')),
            'contact_person' => $this->nullStr($this->input('contact_person', '')),
            'phone' => $this->nullStr($this->input('phone', '')),
            'email' => $this->nullStr($this->input('email', '')),
            'address' => $this->nullStr($this->input('address', '')),
            'city' => $this->nullStr($this->input('city', '')),
            'notes' => $this->nullStr($this->input('notes', '')),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ];
    }

    private function nullStr(mixed $v): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
