<?php

declare(strict_types=1);

namespace App\Helpers;

final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (preg_match('/^"(.*)"$/s', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = $m[1];
            }

            self::$vars[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::$vars[$key] ?? $_ENV[$key] ?? $default;
    }
}
