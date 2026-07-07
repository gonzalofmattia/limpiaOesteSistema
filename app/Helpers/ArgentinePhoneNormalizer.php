<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Normaliza teléfonos argentinos a formato internacional (+549...) para poder
 * deduplicar contra prospects.phone / clients.phone de forma consistente,
 * usando el mismo criterio que enviar_negocios.py (script de WhatsApp).
 */
final class ArgentinePhoneNormalizer
{
    public static function normalize(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }
        if (!$hasPlus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '54')) {
            return '+' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '9')) {
            return '+54' . $digits;
        }
        if (strlen($digits) === 10) {
            return '+549' . $digits;
        }
        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            return '+' . $digits;
        }

        return null;
    }
}
