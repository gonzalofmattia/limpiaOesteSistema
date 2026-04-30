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
        'sale_prefix',
        'catalog_markup_mayorista', 'catalog_markup_minorista',
    ];

    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r;
        }
        $suppliers = $db->fetchAll('SELECT * FROM suppliers ORDER BY name');
        $this->view('settings/index', ['title' => 'Configuración', 'settings' => $settings, 'suppliers' => $suppliers]);
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
        $supplierRows = $db->fetchAll('SELECT id FROM suppliers');
        foreach ($supplierRows as $row) {
            $sid = (int) $row['id'];
            $db->update('suppliers', [
                'cliente_id' => trim((string) $this->input('supplier_' . $sid . '_cliente_id', '')),
                'cliente_nombre' => trim((string) $this->input('supplier_' . $sid . '_cliente_nombre', '')),
                'condicion_pago' => trim((string) $this->input('supplier_' . $sid . '_condicion_pago', '')),
                'observaciones' => trim((string) $this->input('supplier_' . $sid . '_observaciones', '')),
            ], 'id = :id', ['id' => $sid]);
        }
        SettingsCache::forget();
        flash('success', 'Configuración guardada.');
        redirect('/settings');
    }
}
