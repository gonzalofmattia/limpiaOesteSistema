<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Resuelve el markup efectivo para un cliente dado.
 *
 * Jerarquía:
 * 1. clients.default_markup (override individual del cliente)
 * 2. client_segment_config.default_markup (markup del segmento)
 * 3. settings.default_markup (fallback global, típicamente 60%)
 */
final class ClientMarkupResolver
{
    public static function resolve(int $clientId, Database $db): float
    {
        $client = $db->fetch(
            'SELECT client_type, default_markup FROM clients WHERE id = ?',
            [$clientId]
        );
        if (!$client) {
            return self::getGlobalDefault($db);
        }

        if ($client['default_markup'] !== null && $client['default_markup'] !== '') {
            return (float) $client['default_markup'];
        }

        $segmentKey = trim((string) ($client['client_type'] ?? ''));
        if ($segmentKey !== '') {
            try {
                $segment = $db->fetch(
                    'SELECT default_markup FROM client_segment_config WHERE segment_key = ? AND is_active = 1',
                    [$segmentKey]
                );
                if ($segment) {
                    return (float) $segment['default_markup'];
                }
            } catch (\Throwable) {
                // Si aún no corrió migración de segmentos, cae al global.
            }
        }

        return self::getGlobalDefault($db);
    }

    private static function getGlobalDefault(Database $db): float
    {
        $setting = $db->fetch(
            "SELECT setting_value FROM settings WHERE setting_key = 'default_markup'"
        );
        return $setting ? (float) $setting['setting_value'] : 60.0;
    }

    /** @return list<array<string,mixed>> */
    public static function getSegments(Database $db): array
    {
        try {
            return $db->fetchAll(
                'SELECT segment_key, segment_label, default_markup
                 FROM client_segment_config
                 WHERE is_active = 1
                 ORDER BY sort_order, id'
            );
        } catch (\Throwable) {
            return [
                ['segment_key' => 'mayorista', 'segment_label' => 'Mayorista', 'default_markup' => 60.0],
                ['segment_key' => 'minorista', 'segment_label' => 'Minorista', 'default_markup' => 100.0],
                ['segment_key' => 'barrio_cerrado', 'segment_label' => 'Barrio Cerrado', 'default_markup' => 90.0],
                ['segment_key' => 'gastronomico', 'segment_label' => 'Gastronómico', 'default_markup' => 70.0],
                ['segment_key' => 'mercadolibre', 'segment_label' => 'MercadoLibre', 'default_markup' => 60.0],
            ];
        }
    }

    public static function getSegmentLabel(string $segmentKey, Database $db): string
    {
        try {
            $segment = $db->fetch(
                'SELECT segment_label FROM client_segment_config WHERE segment_key = ?',
                [$segmentKey]
            );
            if ($segment) {
                return (string) $segment['segment_label'];
            }
        } catch (\Throwable) {
            // fallback textual
        }
        return ucfirst(str_replace('_', ' ', $segmentKey));
    }
}
