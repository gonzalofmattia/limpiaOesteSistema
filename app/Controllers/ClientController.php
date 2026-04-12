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
        $rows = $db->fetchAll('SELECT * FROM clients ORDER BY name');
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
        $c = $db->fetch('SELECT * FROM clients WHERE id = ?', [(int) $id]);
        if (!$c) {
            flash('error', 'No encontrado.');
            redirect('/clientes');
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
