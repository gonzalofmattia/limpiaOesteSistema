<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class AuthController extends Controller
{
    protected bool $authRequired = false;

    public function showLogin(): void
    {
        if (!empty($_SESSION['admin_user_id'])) {
            redirect('/');
        }
        $this->view('auth/login', [], 'layout/auth');
    }

    public function login(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Sesión inválida. Reintentá.');
            redirect('/login');
        }
        $user = trim((string) $this->input('username', ''));
        $pass = (string) $this->input('password', '');
        if ($user === '' || $pass === '') {
            flash('error', 'Completá usuario y contraseña.');
            redirect('/login');
        }
        $db = Database::getInstance();
        $row = $db->fetch('SELECT id, password_hash FROM admin_users WHERE username = ?', [$user]);
        if (!$row || !password_verify($pass, $row['password_hash'])) {
            flash('error', 'Credenciales incorrectas.');
            redirect('/login');
        }
        $_SESSION['admin_user_id'] = (int) $row['id'];
        $_SESSION['admin_username'] = $user;
        $db->query('UPDATE admin_users SET last_login = NOW() WHERE id = ?', [(int) $row['id']]);
        redirect('/');
    }

    public function logout(): void
    {
        unset($_SESSION['admin_user_id'], $_SESSION['admin_username']);
        flash('success', 'Sesión cerrada.');
        redirect('/login');
    }
}
