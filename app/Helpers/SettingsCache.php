<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

final class SettingsCache
{
    /** @var array<string, string>|null */
    private static ?array $all = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$all === null) {
            self::load();
        }
        return self::$all[$key] ?? $default;
    }

    public static function forget(): void
    {
        self::$all = null;
    }

    private static function load(): void
    {
        self::$all = [];
        try {
            $rows = Database::getInstance()->fetchAll('SELECT setting_key, setting_value FROM settings');
            foreach ($rows as $r) {
                self::$all[$r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (\Throwable) {
            self::$all = [];
        }
    }
}
