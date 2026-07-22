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
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $db->query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');

        $attempts = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$ip]
        );
        if ($attempts >= 5) {
            flash('error', 'Demasiados intentos. Esperá 15 minutos.');
            redirect('/login');
        }

        $row = $db->fetch(
            'SELECT id, password_hash, role, cost_multiplier, full_name, is_active FROM admin_users WHERE username = ?',
            [$user]
        );
        if (!$row || !password_verify($pass, $row['password_hash'])) {
            $db->query('INSERT INTO login_attempts (ip_address) VALUES (?)', [$ip]);
            flash('error', 'Credenciales incorrectas.');
            redirect('/login');
        }

        if ((int) ($row['is_active'] ?? 1) === 0) {
            $db->query('INSERT INTO login_attempts (ip_address) VALUES (?)', [$ip]);
            flash('error', 'Usuario deshabilitado. Contactá al administrador.');
            redirect('/login');
        }

        $db->query('DELETE FROM login_attempts WHERE ip_address = ?', [$ip]);
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $row['id'];
        $_SESSION['admin_username'] = $user;
        $_SESSION['admin_role'] = (string) ($row['role'] ?? 'admin');
        $_SESSION['cost_multiplier'] = (float) ($row['cost_multiplier'] ?? 1.0);
        $_SESSION['admin_full_name'] = $row['full_name'] !== null && $row['full_name'] !== ''
            ? (string) $row['full_name']
            : $user;
        $db->query('UPDATE admin_users SET last_login = NOW() WHERE id = ?', [(int) $row['id']]);
        redirect('/');
    }

    public function logout(): void
    {
        unset(
            $_SESSION['admin_user_id'],
            $_SESSION['admin_username'],
            $_SESSION['admin_role'],
            $_SESSION['cost_multiplier'],
            $_SESSION['admin_full_name']
        );
        flash('success', 'Sesión cerrada.');
        redirect('/login');
    }
}
