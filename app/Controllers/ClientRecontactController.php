<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\OutreachScheduler;
use App\Helpers\SettingsCache;
use App\Models\Database;

/**
 * Recontacto automatico a CLIENTES (no prospectos): reposicion basada en
 * la ultima compra real de cada uno. Reusa la misma cola/worker de WhatsApp
 * que campanias — ver OutreachScheduler::enqueueClientRecontacts().
 */
final class ClientRecontactController extends Controller
{
    private const PREVIEW_LIMIT = 20;

    public function index(): void
    {
        $db = Database::getInstance();

        $eligible = OutreachScheduler::eligibleClientsForRecontact($db);
        $sample = array_slice($eligible, 0, self::PREVIEW_LIMIT);

        $preview = [];
        foreach ($sample as $client) {
            $items = OutreachScheduler::lastOrderItems($db, (int) $client['last_quote_id']);
            $preview[] = [
                'client' => $client,
                'rendered_body' => OutreachScheduler::buildClientRecontactBody($client, $items),
            ];
        }

        $products = [];
        if ($eligible !== []) {
            $quoteIds = array_map(static fn (array $c) => (int) $c['last_quote_id'], $eligible);
            $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
            $products = $db->fetchAll(
                "SELECT DISTINCT p.id, p.name, p.cross_sell_tip FROM quote_items qi
                 INNER JOIN products p ON p.id = qi.product_id
                 WHERE qi.quote_id IN ({$placeholders}) ORDER BY p.name",
                $quoteIds
            );
        }

        $this->view('client_recontact/index', [
            'title' => 'Recontacto de clientes',
            'subtitle' => 'Reposición automática para quienes ya te compraron',
            'enabled' => setting('client_recontact_enabled', '0') === '1',
            'days' => (int) (setting('client_recontact_days', '45') ?? '45'),
            'cooldownDays' => (int) (setting('client_recontact_cooldown_days', '60') ?? '60'),
            'dailyLimit' => (int) (setting('client_recontact_daily_limit', '5') ?? '5'),
            'count' => count($eligible),
            'preview' => $preview,
            'products' => $products,
        ]);
    }

    public function updateSettings(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/recontacto-clientes');
            return;
        }
        $db = Database::getInstance();
        $enabled = $this->input('enabled', '0') === '1' ? '1' : '0';
        $days = max(7, (int) $this->input('days', 45));
        $cooldown = max(7, (int) $this->input('cooldown_days', 60));
        $dailyLimit = max(1, min(25, (int) $this->input('daily_limit', 5)));

        $values = [
            'client_recontact_enabled' => $enabled,
            'client_recontact_days' => (string) $days,
            'client_recontact_cooldown_days' => (string) $cooldown,
            'client_recontact_daily_limit' => (string) $dailyLimit,
        ];
        foreach ($values as $key => $val) {
            $db->query('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$val, $key]);
        }
        SettingsCache::forget();
        flash('success', 'Configuración de recontacto actualizada.');
        redirect('/prospeccion/recontacto-clientes');
    }

    public function updateProductTip(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/prospeccion/recontacto-clientes');
            return;
        }
        $tip = trim((string) $this->input('cross_sell_tip', ''));
        Database::getInstance()->update(
            'products',
            ['cross_sell_tip' => $tip !== '' ? mb_substr($tip, 0, 255) : null],
            'id = :id',
            ['id' => (int) $id]
        );
        flash('success', 'Tip actualizado.');
        redirect('/prospeccion/recontacto-clientes');
    }
}
