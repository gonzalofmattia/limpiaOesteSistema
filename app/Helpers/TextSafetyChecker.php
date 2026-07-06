<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Detecta lenguaje de advertencia de seguridad que ML puede clasificar como riesgo.
 */
final class TextSafetyChecker
{
    /** @var list<string>|null */
    private static ?array $patterns = null;

    /**
     * @return list<string> Términos/patrones encontrados (vacío si el texto está limpio)
     */
    public static function containsBannedMercadoEnviosTerms(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $found = [];
        foreach (self::patterns() as $pattern) {
            if (@preg_match('/' . $pattern . '/iu', $text, $matches) === 1) {
                $found[] = $matches[0];
            }
        }

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    private static function patterns(): array
    {
        if (self::$patterns !== null) {
            return self::$patterns;
        }

        $file = dirname(__DIR__) . '/config/ml_banned_terms.php';
        $loaded = is_file($file) ? require $file : [];

        self::$patterns = is_array($loaded) ? array_values(array_filter($loaded, 'is_string')) : [];

        return self::$patterns;
    }
}
