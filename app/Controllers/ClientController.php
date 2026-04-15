<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class ClientController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        try {
            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable) {
                $rows = $db->fetchAll(
                    "SELECT c.*,
                            COALESCE((
                                SELECT SUM(q.total)
                                FROM quotes q
                                WHERE q.client_id = c.id
                                  AND q.status IN ('accepted', 'delivered')
                            ), 0) AS quotes_accepted_total,
                            COALESCE((
                                SELECT SUM(at.amount)
                                FROM account_transactions at
                                WHERE at.account_type = 'client'
                                  AND at.account_id = c.id
                                  AND at.transaction_type = 'payment'
                            ), 0) AS account_payments_total,
                            COALESCE((
                                SELECT SUM(at.amount)
                                FROM account_transactions at
                                WHERE at.account_type = 'client'
                                  AND at.account_id = c.id
                                  AND at.transaction_type = 'adjustment'
                            ), 0) AS account_adjustments_total
                     FROM clients c
                     ORDER BY c.name"
                );
                foreach ($rows as &$row) {
                    $effective = (float) $row['quotes_accepted_total']
                        - (float) $row['account_payments_total']
                        + (float) $row['account_adjustments_total'];
                    $row['effective_balance'] = round($effective, 2);
                }
                unset($row);
            } else {
                $rows = $db->fetchAll('SELECT * FROM clients ORDER BY name');
            }
        } catch (\Throwable) {
            $rows = $db->fetchAll('SELECT * FROM clients ORDER BY name');
        }
        $this->view('clients/index', ['title' => 'Clientes', 'clients' => $rows]);
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
        try {
            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable) {
                $c = $db->fetch(
                    "SELECT c.*,
                            COALESCE((
                                SELECT SUM(q.total) FROM quotes q
                                WHERE q.client_id = c.id AND q.status IN ('accepted', 'delivered')
                            ), 0) AS quotes_accepted_total,
                            COALESCE((
                                SELECT SUM(at.amount) FROM account_transactions at
                                WHERE at.account_type = 'client' AND at.account_id = c.id AND at.transaction_type = 'payment'
                            ), 0) AS account_payments_total,
                            COALESCE((
                                SELECT SUM(at.amount) FROM account_transactions at
                                WHERE at.account_type = 'client' AND at.account_id = c.id AND at.transaction_type = 'adjustment'
                            ), 0) AS account_adjustments_total
                     FROM clients c
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
        $c['effective_balance'] = isset($c['quotes_accepted_total'])
            ? round(
                (float) ($c['quotes_accepted_total'] ?? 0)
                - (float) ($c['account_payments_total'] ?? 0)
                + (float) ($c['account_adjustments_total'] ?? 0),
                2
            )
            : (float) ($c['balance'] ?? 0);
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
