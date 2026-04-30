<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');

require_once APP_PATH . '/Helpers/Env.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/QuoteLinePricing.php';
require_once APP_PATH . '/Helpers/QuoteDeliveryStock.php';

use App\Helpers\Env;
use App\Helpers\QuoteDeliveryStock;
use App\Models\Database;

Env::load(BASE_PATH . '/.env');

$db = Database::getInstance();
$results = [];
$createdQuoteIds = [];

$assert = static function (string $label, bool $ok, string $detail = '') use (&$results): void {
    $results[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
};

$makeQuote = static function (Database $db, string $suffix): int {
    $number = sprintf('TS%s%s%02d', date('mdHis'), $suffix, random_int(10, 99));
    return $db->insert('quotes', [
        'quote_number' => $number,
        'client_id' => null,
        'title' => 'TEST STOCK FLOW',
        'notes' => null,
        'validity_days' => 7,
        'custom_markup' => null,
        'include_iva' => 0,
        'subtotal' => 0,
        'iva_amount' => 0,
        'total' => 0,
        'status' => 'draft',
        'delivery_stock_applied' => 0,
    ]);
};

$cleanupQuote = static function (Database $db, int $quoteId): void {
    $q = $db->fetch('SELECT id, status, delivery_stock_applied FROM quotes WHERE id = ?', [$quoteId]);
    if (!$q) {
        return;
    }
    try {
        if ((int) ($q['delivery_stock_applied'] ?? 0) === 1) {
            QuoteDeliveryStock::reverseDelivery($db, $quoteId);
        }
    } catch (\Throwable) {
        // Best effort cleanup.
    }
    try {
        if ((string) ($q['status'] ?? '') === 'accepted') {
            QuoteDeliveryStock::releaseCommittedStock($db, $quoteId);
        }
    } catch (\Throwable) {
        // Best effort cleanup.
    }
    $db->delete('quote_items', 'quote_id = :qid', ['qid' => $quoteId]);
    $db->delete('quotes', 'id = :id', ['id' => $quoteId]);
};

try {
    $product = $db->fetch(
        'SELECT id, code, name, stock_units, COALESCE(stock_committed_units,0) AS stock_committed_units
         FROM products
         WHERE is_active = 1
         ORDER BY stock_units DESC, id ASC
         LIMIT 1'
    );
    if (!$product) {
        throw new RuntimeException('No hay productos para prueba.');
    }

    $pid = (int) $product['id'];
    $baseStock = (int) ($product['stock_units'] ?? 0);
    $baseCommitted = (int) ($product['stock_committed_units'] ?? 0);

    // Flujo 1: draft -> accepted -> delivered.
    $q1 = $makeQuote($db, 'P');
    $createdQuoteIds[] = $q1;
    $db->insert('quote_items', [
        'quote_id' => $q1,
        'product_id' => $pid,
        'combo_id' => null,
        'quantity' => 2,
        'unit_type' => 'unidad',
        'unit_label' => 'Unidad',
        'unit_description' => (string) ($product['name'] ?? ''),
        'unit_price' => 1,
        'individual_unit_price' => 1,
        'subtotal' => 2,
        'sort_order' => 0,
    ]);

    QuoteDeliveryStock::commitStock($db, $q1);
    $db->update('quotes', ['status' => 'accepted'], 'id = :id', ['id' => $q1]);
    $afterAccepted = $db->fetch('SELECT stock_units, stock_committed_units FROM products WHERE id = ?', [$pid]) ?: [];
    $assert(
        'Producto simple: accepted sube comprometido',
        (int) ($afterAccepted['stock_committed_units'] ?? 0) === $baseCommitted + 2,
        'Esperado ' . ($baseCommitted + 2) . ', actual ' . (int) ($afterAccepted['stock_committed_units'] ?? 0)
    );

    QuoteDeliveryStock::markDelivered($db, $q1);
    $db->update('quotes', ['status' => 'delivered', 'delivery_stock_applied' => 1], 'id = :id', ['id' => $q1]);
    $afterDelivered = $db->fetch('SELECT stock_units, stock_committed_units FROM products WHERE id = ?', [$pid]) ?: [];
    $assert(
        'Producto simple: delivered libera comprometido',
        (int) ($afterDelivered['stock_committed_units'] ?? 0) === $baseCommitted,
        'Esperado ' . $baseCommitted . ', actual ' . (int) ($afterDelivered['stock_committed_units'] ?? 0)
    );
    $assert(
        'Producto simple: delivered baja stock físico',
        (int) ($afterDelivered['stock_units'] ?? 0) === $baseStock - 2,
        'Esperado ' . ($baseStock - 2) . ', actual ' . (int) ($afterDelivered['stock_units'] ?? 0)
    );

    // Flujo 2: accepted -> rejected.
    $q2 = $makeQuote($db, 'R');
    $createdQuoteIds[] = $q2;
    $db->insert('quote_items', [
        'quote_id' => $q2,
        'product_id' => $pid,
        'combo_id' => null,
        'quantity' => 1,
        'unit_type' => 'unidad',
        'unit_label' => 'Unidad',
        'unit_description' => (string) ($product['name'] ?? ''),
        'unit_price' => 1,
        'individual_unit_price' => 1,
        'subtotal' => 1,
        'sort_order' => 0,
    ]);
    QuoteDeliveryStock::commitStock($db, $q2);
    $db->update('quotes', ['status' => 'accepted'], 'id = :id', ['id' => $q2]);
    QuoteDeliveryStock::releaseCommittedStock($db, $q2);
    $db->update('quotes', ['status' => 'rejected'], 'id = :id', ['id' => $q2]);
    $afterRejected = $db->fetch('SELECT stock_committed_units FROM products WHERE id = ?', [$pid]) ?: [];
    $assert(
        'Producto simple: accepted->rejected libera comprometido',
        (int) ($afterRejected['stock_committed_units'] ?? 0) === $baseCommitted,
        'Esperado ' . $baseCommitted . ', actual ' . (int) ($afterRejected['stock_committed_units'] ?? 0)
    );

    // Flujo 3: combo accepted -> delivered.
    $combo = $db->fetch(
        'SELECT c.id, c.name
         FROM combos c
         JOIN combo_products cp ON cp.combo_id = c.id
         WHERE c.is_active = 1
         GROUP BY c.id, c.name
         ORDER BY c.id
         LIMIT 1'
    );
    if ($combo) {
        $q3 = $makeQuote($db, 'C');
        $createdQuoteIds[] = $q3;
        $db->insert('quote_items', [
            'quote_id' => $q3,
            'product_id' => null,
            'combo_id' => (int) $combo['id'],
            'quantity' => 1,
            'unit_type' => 'combo',
            'unit_label' => 'Combo',
            'unit_description' => (string) ($combo['name'] ?? 'Combo'),
            'unit_price' => 1,
            'individual_unit_price' => 1,
            'subtotal' => 1,
            'sort_order' => 0,
        ]);
        $comboUnits = QuoteDeliveryStock::unitsByProductForQuote($q3);
        $comboBefore = [];
        foreach (array_keys($comboUnits) as $cpid) {
            $comboBefore[(int) $cpid] = $db->fetch(
                'SELECT stock_units, stock_committed_units FROM products WHERE id = ?',
                [(int) $cpid]
            );
        }
        QuoteDeliveryStock::commitStock($db, $q3);
        $db->update('quotes', ['status' => 'accepted'], 'id = :id', ['id' => $q3]);
        $comboAcceptedOk = true;
        foreach ($comboUnits as $cpid => $u) {
            $after = $db->fetch('SELECT stock_committed_units FROM products WHERE id = ?', [(int) $cpid]) ?: [];
            $expected = (int) (($comboBefore[(int) $cpid]['stock_committed_units'] ?? 0) + $u);
            if ((int) ($after['stock_committed_units'] ?? 0) !== $expected) {
                $comboAcceptedOk = false;
                break;
            }
        }
        $assert('Combo: accepted compromete componentes', $comboAcceptedOk, 'Verificado contra unidades descompuestas del combo.');

        QuoteDeliveryStock::markDelivered($db, $q3);
        $db->update('quotes', ['status' => 'delivered', 'delivery_stock_applied' => 1], 'id = :id', ['id' => $q3]);
        $comboDeliveredOk = true;
        foreach ($comboUnits as $cpid => $u) {
            $after = $db->fetch('SELECT stock_units, stock_committed_units FROM products WHERE id = ?', [(int) $cpid]) ?: [];
            $expectedCommitted = (int) ($comboBefore[(int) $cpid]['stock_committed_units'] ?? 0);
            $expectedStock = (int) (($comboBefore[(int) $cpid]['stock_units'] ?? 0) - $u);
            if ((int) ($after['stock_committed_units'] ?? 0) !== $expectedCommitted || (int) ($after['stock_units'] ?? 0) !== $expectedStock) {
                $comboDeliveredOk = false;
                break;
            }
        }
        $assert('Combo: delivered libera y descuenta componentes', $comboDeliveredOk, 'Verificado por producto componente.');
    } else {
        $assert('Combo: flujo omitido', true, 'No hay combos activos con componentes para testear.');
    }
} catch (\Throwable $e) {
    $assert('Ejecución general', false, $e->getMessage());
} finally {
    foreach ($createdQuoteIds as $qid) {
        $cleanupQuote($db, (int) $qid);
    }
}

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    foreach ($results as $r) {
        echo ($r['ok'] ? 'PASS' : 'FAIL') . ' - ' . $r['label'];
        if (($r['detail'] ?? '') !== '') {
            echo ' :: ' . $r['detail'];
        }
        echo PHP_EOL;
    }
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Test stock flow</title></head><body>';
echo '<h2>Test de flujo de stock</h2><ul>';
foreach ($results as $r) {
    $color = $r['ok'] ? '#0f766e' : '#b91c1c';
    echo '<li style="color:' . $color . '"><strong>' . ($r['ok'] ? 'PASS' : 'FAIL') . '</strong> — ' . htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8');
    if (($r['detail'] ?? '') !== '') {
        echo ' <small>' . htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') . '</small>';
    }
    echo '</li>';
}
echo '</ul></body></html>';
