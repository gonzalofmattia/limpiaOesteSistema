<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Database;
use PDOException;

final class UserController extends Controller
{
    private const ROLES = ['admin', 'revendedor'];

    public function index(): void
    {
        $db = Database::getInstance();
        $users = $db->fetchAll(
            'SELECT id, username, full_name, role, cost_multiplier, is_active, last_login FROM admin_users ORDER BY username'
        );
        $this->view('users/index', [
            'title' => 'Usuarios',
            'users' => $users,
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', ['title' => 'Nuevo usuario', 'user' => null]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/usuarios/crear');
        }
        $data = $this->validate(null);
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/usuarios/crear');
        }
        unset($data['errors']);
        $password = (string) $this->input('password', '');
        if ($password === '') {
            flash('error', 'La contraseña es obligatoria.');
            redirect('/usuarios/crear');
        }
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $db = Database::getInstance();
        try {
            $db->insert('admin_users', $data);
        } catch (PDOException) {
            flash('error', 'Ya existe un usuario con ese nombre de usuario.');
            redirect('/usuarios/crear');
            return;
        }
        flash('success', 'Usuario creado.');
        redirect('/usuarios');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT id, username, full_name, role, cost_multiplier, is_active FROM admin_users WHERE id = ?',
            [(int) $id]
        );
        if (!$user) {
            flash('error', 'No encontrado.');
            redirect('/usuarios');
            return;
        }
        $this->view('users/form', ['title' => 'Editar usuario', 'user' => $user]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/usuarios/' . $id . '/editar');
        }
        $db = Database::getInstance();
        if (!$db->fetch('SELECT id FROM admin_users WHERE id = ?', [(int) $id])) {
            flash('error', 'No encontrado.');
            redirect('/usuarios');
        }
        $data = $this->validate((int) $id);
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/usuarios/' . $id . '/editar');
        }
        unset($data['errors']);
        $password = trim((string) $this->input('password', ''));
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        try {
            $db->update('admin_users', $data, 'id = :id', ['id' => (int) $id]);
        } catch (PDOException) {
            flash('error', 'Ya existe un usuario con ese nombre de usuario.');
            redirect('/usuarios/' . $id . '/editar');
            return;
        }
        flash('success', 'Usuario actualizado.');
        redirect('/usuarios');
    }

    /** @return array<string, mixed> */
    private function validate(?int $editingId): array
    {
        $errors = [];
        $username = trim((string) $this->input('username', ''));
        if ($username === '') {
            $errors[] = 'El usuario es obligatorio.';
        }
        $fullName = trim((string) $this->input('full_name', ''));
        $role = (string) $this->input('role', 'revendedor');
        if (!in_array($role, self::ROLES, true)) {
            $role = 'revendedor';
        }
        $costMultiplierRaw = trim((string) $this->input('cost_multiplier', '1.1900'));
        $costMultiplier = $costMultiplierRaw === '' ? 1.19 : parseAmount($costMultiplierRaw);
        if ($costMultiplier < 1.0 || $costMultiplier > 2.0) {
            $errors[] = 'El multiplicador de costo debe estar entre 1.0000 y 2.0000.';
        }
        $isActive = $this->input('is_active') ? 1 : 0;

        if ($editingId !== null && $editingId === Auth::userId() && $isActive === 0) {
            $errors[] = 'No podés desactivar tu propio usuario.';
        }

        return [
            'errors' => $errors,
            'username' => $username,
            'full_name' => $fullName !== '' ? $fullName : null,
            'role' => $role,
            'cost_multiplier' => round($costMultiplier, 4),
            'is_active' => $isActive,
        ];
    }
}
