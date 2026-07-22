<?php

declare(strict_types=1);

namespace App\Helpers;

final class Auth
{
    public static function userId(): ?int
    {
        return isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
    }

    public static function role(): string
    {
        return (string) ($_SESSION['admin_role'] ?? 'admin');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isReseller(): bool
    {
        return self::role() === 'revendedor';
    }

    public static function costMultiplier(): float
    {
        $value = $_SESSION['cost_multiplier'] ?? null;
        if ($value === null || $value === '') {
            return 1.0;
        }
        return (float) $value;
    }

    public static function fullName(): ?string
    {
        $name = $_SESSION['admin_full_name'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Valida el patrón de ruta (tal como está declarado en config/routes.php) contra el
     * whitelist de app/config/permissions.php para el rol revendedor. Los admins siempre
     * tienen acceso. El método HTTP se toma de la request actual.
     */
    public static function canAccess(string $routePattern): bool
    {
        if (!self::isReseller()) {
            return true;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $whitelist = require APP_PATH . '/config/permissions.php';
        $rules = $whitelist['revendedor'] ?? [];

        foreach ($rules as $rule) {
            $ruleMethod = strtoupper((string) ($rule['method'] ?? '*'));
            if ($ruleMethod !== '*' && $ruleMethod !== $method) {
                continue;
            }
            $pattern = (string) ($rule['pattern'] ?? '');
            if ($pattern === $routePattern) {
                return true;
            }
            if (str_ends_with($pattern, '*') && str_starts_with($routePattern, rtrim($pattern, '*'))) {
                return true;
            }
        }

        return false;
    }
}
