<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\SettingsCache;
use App\Models\Database;

final class SettingsController extends Controller
{
    private const KEYS = [
        'empresa_nombre', 'empresa_tagline', 'empresa_instagram', 'empresa_whatsapp', 'empresa_zona',
        'default_markup', 'iva_rate', 'lista_seiq_numero', 'lista_seiq_fecha', 'moneda',
        'mostrar_iva', 'quote_prefix', 'quote_validity_days',
        'seiq_cliente_id', 'seiq_cliente_nombre', 'seiq_condicion_pago', 'seiq_observaciones',
    ];

    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r;
        }
        $this->view('settings/index', ['title' => 'Configuración', 'settings' => $settings]);
    }

    public function update(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/settings');
        }
        $db = Database::getInstance();
        foreach (self::KEYS as $key) {
            if (!array_key_exists('s_' . $key, $_POST)) {
                continue;
            }
            $val = (string) $this->input('s_' . $key, '');
            $db->query(
                'UPDATE settings SET setting_value = ? WHERE setting_key = ?',
                [$val, $key]
            );
        }
        SettingsCache::forget();
        flash('success', 'Configuración guardada.');
        redirect('/settings');
    }
}
