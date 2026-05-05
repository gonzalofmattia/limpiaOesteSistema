<?php
declare(strict_types=1);

/**
 * Auditoría de Stock por Producto
 * Uso CLI: php audit_stock.php 398120
 * Uso Web: /audit_stock.php?code=398120
 */

if (file_exists(__DIR__ . '/.production')) {
    die('Este script no debe ejecutarse en producción.');
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (is_file(__DIR__ . '/app/Helpers/Env.php')) {
    require_once __DIR__ . '/app/Helpers/Env.php';
}

if (class_exists('\App\Helpers\Env')) {
    \App\Helpers\Env::load(__DIR__ . '/.env');
}

/** @return array{fetch:callable,fetchAll:callable,fetchColumn:callable} */
function auditDb(): array
{
    if (class_exists('\App\Models\Database')) {
        try {
            $db = \App\Models\Database::getInstance();
            return [
                'fetch' => static fn(string $sql, array $params = []): ?array => $db->fetch($sql, $params),
                'fetchAll' => static fn(string $sql, array $params = []): array => $db->fetchAll($sql, $params),
                'fetchColumn' => static fn(string $sql, array $params = [], int $col = 0): mixed => $db->fetchColumn($sql, $params, $col),
            ];
        } catch (\Throwable) {
            // fallback PDO
        }
    }

    $cfg = parse_ini_file(__DIR__ . '/.env');
    if (!is_array($cfg)) {
        throw new RuntimeException('No se pudo leer .env');
    }
    $pdo = new PDO(
        'mysql:host=' . ($cfg['DB_HOST'] ?? 'localhost')
        . ';dbname=' . ($cfg['DB_NAME'] ?? '')
        . ';charset=utf8mb4',
        (string) ($cfg['DB_USER'] ?? ''),
        (string) ($cfg['DB_PASS'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return [
        'fetch' => static function (string $sql, array $params = []) use ($pdo): ?array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $row = $st->fetch();
            return $row === false ? null : $row;
        },
        'fetchAll' => static function (string $sql, array $params = []) use ($pdo): array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
            return is_array($rows) ? $rows : [];
        },
        'fetchColumn' => static function (string $sql, array $params = [], int $col = 0) use ($pdo): mixed {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchColumn($col);
        },
    ];
}

function isCli(): bool
{
    return PHP_SAPI === 'cli';
}

function ansi(string $text, string $color = '0'): string
{
    if (!isCli()) {
        return $text;
    }
    return "\033[" . $color . "m" . $text . "\033[0m";
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtDate(mixed $value): string
{
    $s = trim((string) $value);
    if ($s === '') {
        return '—';
    }
    $t = strtotime($s);
    return $t ? date('d/m/Y', $t) : $s;
}

function fmtNum(float|int $n): string
{
    return number_format((float) $n, 2, ',', '.');
}

function normalizeUnitType(string $type): string
{
    $t = strtolower(trim($type));
    return in_array($t, ['unidad', 'bidon', 'bidón', 'litro', 'sobre', 'bulto'], true) ? 'unidad' : 'caja';
}

/** @param array<string,mixed> $line */
function lineUnitsDirect(array $line): float
{
    $qty = (float) ($line['quantity'] ?? 0);
    $upb = max(1, (int) ($line['units_per_box'] ?? 1));
    $mode = normalizeUnitType((string) ($line['unit_type'] ?? 'caja'));
    return $mode === 'unidad' ? $qty : $qty * $upb;
}

/** @return array<int,array<string,mixed>> */
function findProducts(array $db, string $term): array
{
    $fetchAll = $db['fetchAll'];
    $term = trim($term);
    if ($term === '') {
        return [];
    }
    $exact = $fetchAll(
        'SELECT p.id, p.code, p.name, p.is_active
         FROM products p
         WHERE p.code = ?
         ORDER BY p.name',
        [$term]
    );
    if ($exact !== []) {
        return $exact;
    }
    return $fetchAll(
        'SELECT p.id, p.code, p.name, p.is_active
         FROM products p
         WHERE p.name LIKE ? OR p.code LIKE ?
         ORDER BY p.name
         LIMIT 30',
        ['%' . $term . '%', '%' . $term . '%']
    );
}

/** @return array<string,mixed> */
function auditProduct(array $db, int $productId): array
{
    $fetch = $db['fetch'];
    $fetchAll = $db['fetchAll'];
    $fetchColumn = $db['fetchColumn'];

    $product = $fetch(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?',
        [$productId]
    );
    if (!$product) {
        throw new RuntimeException('Producto no encontrado');
    }

    $purchases = $fetchAll(
        "SELECT so.order_number, so.status, so.created_at, so.received_at, so.receipt_stock_applied,
                soi.boxes_to_order, COALESCE(NULLIF(soi.units_per_box,0), NULLIF(p.units_per_box,0), 1) AS units_per_box
         FROM seiq_order_items soi
         JOIN seiq_orders so ON so.id = soi.seiq_order_id
         LEFT JOIN products p ON p.id = soi.product_id
         WHERE soi.product_id = ?
         ORDER BY so.created_at ASC, so.id ASC",
        [$productId]
    );

    $totalReceived = 0.0;
    foreach ($purchases as &$r) {
        $boxes = (float) ($r['boxes_to_order'] ?? 0);
        $upb = max(1, (int) ($r['units_per_box'] ?? 1));
        $r['total_units'] = $boxes * $upb;
        if ((string) ($r['status'] ?? '') === 'received' && (int) ($r['receipt_stock_applied'] ?? 0) === 1) {
            $totalReceived += (float) $r['total_units'];
        }
    }
    unset($r);

    $directRows = $fetchAll(
        "SELECT q.quote_number, q.status, q.created_at, c.name AS client_name,
                qi.quantity, qi.qty_delivered, qi.unit_type, qi.unit_price, qi.subtotal, p.units_per_box
         FROM quote_items qi
         JOIN quotes q ON q.id = qi.quote_id
         LEFT JOIN clients c ON c.id = q.client_id
         LEFT JOIN products p ON p.id = qi.product_id
         WHERE qi.product_id = ?
           AND q.status IN ('accepted','partially_delivered','delivered')
         ORDER BY q.created_at ASC, q.id ASC",
        [$productId]
    );

    $direct = [];
    $totalDirectSold = 0.0;
    $totalDirectDelivered = 0.0;
    foreach ($directRows as $r) {
        $sold = lineUnitsDirect($r);
        $deliveredSaleQty = (float) ($r['qty_delivered'] ?? 0);
        $tmp = $r;
        $tmp['quantity'] = $deliveredSaleQty;
        $delivered = lineUnitsDirect($tmp);
        $pending = max(0.0, $sold - $delivered);
        $totalDirectSold += $sold;
        $totalDirectDelivered += $delivered;
        $direct[] = $r + ['sold_units' => $sold, 'delivered_units' => $delivered, 'pending_units' => $pending];
    }

    $comboDefs = $fetchAll(
        "SELECT cp.combo_id, cp.quantity AS units_per_combo, cb.name AS combo_name
         FROM combo_products cp
         JOIN combos cb ON cb.id = cp.combo_id
         WHERE cp.product_id = ?
         ORDER BY cb.name",
        [$productId]
    );
    $comboById = [];
    foreach ($comboDefs as $d) {
        $comboById[(int) $d['combo_id']] = $d;
    }

    $comboSalesRows = [];
    if ($comboById !== []) {
        $comboIds = array_keys($comboById);
        $placeholders = implode(',', array_fill(0, count($comboIds), '?'));
        $comboSalesRows = $fetchAll(
            "SELECT q.quote_number, q.status, q.created_at, c.name AS client_name,
                    qi.combo_id, qi.quantity, qi.qty_delivered
             FROM quote_items qi
             JOIN quotes q ON q.id = qi.quote_id
             LEFT JOIN clients c ON c.id = q.client_id
             WHERE qi.combo_id IN ({$placeholders})
               AND q.status IN ('accepted','partially_delivered','delivered')
             ORDER BY q.created_at ASC, q.id ASC",
            $comboIds
        );
    }

    $comboSales = [];
    $totalComboSold = 0.0;
    $totalComboDelivered = 0.0;
    foreach ($comboSalesRows as $r) {
        $cid = (int) ($r['combo_id'] ?? 0);
        $def = $comboById[$cid] ?? null;
        if ($def === null) {
            continue;
        }
        $perCombo = (float) ($def['units_per_combo'] ?? 0);
        $comboQty = (float) ($r['quantity'] ?? 0);
        $comboDelivered = (float) ($r['qty_delivered'] ?? 0);
        $unitsSold = $comboQty * $perCombo;
        $unitsDelivered = $comboDelivered * $perCombo;
        $unitsPending = max(0.0, $unitsSold - $unitsDelivered);
        $totalComboSold += $unitsSold;
        $totalComboDelivered += $unitsDelivered;
        $comboSales[] = $r + [
            'combo_name' => (string) ($def['combo_name'] ?? ('Combo #' . $cid)),
            'units_per_combo' => $perCombo,
            'units_sold' => $unitsSold,
            'units_delivered' => $unitsDelivered,
            'units_pending' => $unitsPending,
        ];
    }

    $adjustments = [];
    $totalAdjustments = 0.0;
    $hasAdj = (int) $fetchColumn(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_adjustments'"
    ) > 0;
    if ($hasAdj) {
        $hasDiff = (int) $fetchColumn(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='stock_adjustments' AND COLUMN_NAME='difference'"
        ) > 0;
        if ($hasDiff) {
            $adjustments = $fetchAll(
                "SELECT created_at, difference AS quantity_change, notes AS reason
                 FROM stock_adjustments
                 WHERE product_id = ?
                 ORDER BY created_at ASC, id ASC",
                [$productId]
            );
        } else {
            $adjustments = $fetchAll(
                "SELECT created_at, quantity_change, reason
                 FROM stock_adjustments
                 WHERE product_id = ?
                 ORDER BY created_at ASC, id ASC",
                [$productId]
            );
        }
        foreach ($adjustments as $a) {
            $totalAdjustments += (float) ($a['quantity_change'] ?? 0);
        }
    }

    $pendingLines = [];
    foreach ($direct as $d) {
        if (in_array((string) $d['status'], ['accepted', 'partially_delivered'], true)
            && (float) ($d['pending_units'] ?? 0) > 0) {
            $pendingLines[] = [
                'quote_number' => (string) ($d['quote_number'] ?? ''),
                'status' => (string) ($d['status'] ?? ''),
                'client_name' => (string) ($d['client_name'] ?? '—'),
                'pending_units' => (float) ($d['pending_units'] ?? 0),
                'source' => 'directo',
            ];
        }
    }
    foreach ($comboSales as $c) {
        if (in_array((string) $c['status'], ['accepted', 'partially_delivered'], true)
            && (float) ($c['units_pending'] ?? 0) > 0) {
            $pendingLines[] = [
                'quote_number' => (string) ($c['quote_number'] ?? ''),
                'status' => (string) ($c['status'] ?? ''),
                'client_name' => (string) ($c['client_name'] ?? '—'),
                'pending_units' => (float) ($c['units_pending'] ?? 0),
                'source' => 'combo: ' . (string) ($c['combo_name'] ?? ''),
            ];
        }
    }
    usort($pendingLines, static fn(array $a, array $b): int => strcmp($a['quote_number'], $b['quote_number']));
    $pendingTotal = array_sum(array_map(static fn(array $x): float => (float) $x['pending_units'], $pendingLines));

    $stockTheory = $totalReceived - $totalDirectDelivered - $totalComboDelivered + $totalAdjustments;
    $committedTheory = $pendingTotal;
    $availableTheory = $stockTheory - $committedTheory;

    $stockSystem = (float) ($product['stock_units'] ?? 0);
    $committedSystem = (float) ($product['stock_committed_units'] ?? 0);
    $availableSystem = $stockSystem - $committedSystem;

    return [
        'product' => $product,
        'purchases' => $purchases,
        'total_received' => $totalReceived,
        'direct' => $direct,
        'direct_totals' => ['sold' => $totalDirectSold, 'delivered' => $totalDirectDelivered, 'pending' => max(0.0, $totalDirectSold - $totalDirectDelivered)],
        'combo_defs' => array_values($comboById),
        'combo_sales' => $comboSales,
        'combo_totals' => ['sold' => $totalComboSold, 'delivered' => $totalComboDelivered, 'pending' => max(0.0, $totalComboSold - $totalComboDelivered)],
        'adjustments' => $adjustments,
        'adjustments_total' => $totalAdjustments,
        'pending_lines' => $pendingLines,
        'pending_total' => $pendingTotal,
        'reconciliation' => [
            'stock_theory' => $stockTheory,
            'committed_theory' => $committedTheory,
            'available_theory' => $availableTheory,
            'stock_system' => $stockSystem,
            'committed_system' => $committedSystem,
            'available_system' => $availableSystem,
            'diff_stock' => $stockSystem - $stockTheory,
            'diff_committed' => $committedSystem - $committedTheory,
            'diff_available' => $availableSystem - $availableTheory,
        ],
    ];
}

/** @return list<string> */
function autoDiagnostics(array $report): array
{
    $out = [];
    $p = $report['product'];
    $r = $report['reconciliation'];
    if ((int) ($p['is_active'] ?? 0) !== 1) {
        $out[] = 'El producto está INACTIVO — puede no aparecer en listados comerciales.';
    }
    if (abs((float) $r['diff_stock']) > 0.0001) {
        $out[] = 'DIFERENCIA stock físico: sistema=' . fmtNum((float) $r['stock_system']) . ' vs teórico=' . fmtNum((float) $r['stock_theory']) . '.';
    }
    if (abs((float) $r['diff_committed']) > 0.0001) {
        $out[] = 'DIFERENCIA comprometido: sistema=' . fmtNum((float) $r['committed_system']) . ' vs teórico=' . fmtNum((float) $r['committed_theory']) . '.';
    }
    if ((float) ($report['pending_total'] ?? 0) > 0) {
        $out[] = 'Hay ' . fmtNum((float) $report['pending_total']) . ' unidades pendientes de entregar.';
    }
    if ($out === []) {
        $out[] = 'Stock consistente entre cálculo teórico y sistema.';
    }
    return $out;
}

function printCliReport(array $report): void
{
    $p = $report['product'];
    $r = $report['reconciliation'];
    echo ansi(str_repeat('═', 62), '1;37') . PHP_EOL;
    echo ansi(' AUDITORÍA DE STOCK — ' . ($p['code'] ?? '') . ' ' . ($p['name'] ?? ''), '1;36') . PHP_EOL;
    echo ansi(str_repeat('═', 62), '1;37') . PHP_EOL . PHP_EOL;

    echo ansi("📦 DATOS DEL PRODUCTO", '1;33') . PHP_EOL;
    echo "Código:      {$p['code']}" . PHP_EOL;
    echo "Nombre:      {$p['name']}" . PHP_EOL;
    echo "Categoría:   " . ($p['category_name'] ?? '—') . PHP_EOL;
    echo "Estado:      " . ((int) ($p['is_active'] ?? 0) === 1 ? ansi('✅ ACTIVO', '1;32') : ansi('❌ INACTIVO', '1;31')) . PHP_EOL;
    echo "Unidad venta:" . ' ' . ((string) ($p['sale_unit_type'] ?? 'caja')) . ' | unidades/caja: ' . (int) ($p['units_per_box'] ?? 1) . PHP_EOL;
    echo "Stock sistema: " . fmtNum((float) ($p['stock_units'] ?? 0)) . ' | comprometido: ' . fmtNum((float) ($p['stock_committed_units'] ?? 0)) . ' | disponible: ' . fmtNum((float) (($p['stock_units'] ?? 0) - ($p['stock_committed_units'] ?? 0))) . PHP_EOL . PHP_EOL;

    echo ansi("📥 COMPRAS AL PROVEEDOR", '1;34') . PHP_EOL;
    foreach ($report['purchases'] as $row) {
        echo sprintf(
            "- %-12s %-10s %10s  cajas:%6s  x%4s  total:%8s  recibido:%10s",
            (string) ($row['order_number'] ?? '—'),
            (string) ($row['status'] ?? '—'),
            fmtDate($row['created_at'] ?? null),
            fmtNum((float) ($row['boxes_to_order'] ?? 0)),
            (int) ($row['units_per_box'] ?? 1),
            fmtNum((float) ($row['total_units'] ?? 0)),
            fmtDate($row['received_at'] ?? null)
        ) . PHP_EOL;
    }
    echo 'TOTAL RECIBIDO (aplicado): ' . ansi(fmtNum((float) $report['total_received']) . ' unidades', '1;32') . PHP_EOL . PHP_EOL;

    echo ansi("📤 VENTAS DIRECTAS", '1;35') . PHP_EOL;
    foreach ($report['direct'] as $row) {
        echo sprintf(
            "- %-12s %-18s %-16s vend:%7s  entreg:%7s  pend:%7s",
            (string) ($row['quote_number'] ?? '—'),
            (string) ($row['status'] ?? '—'),
            (string) ($row['client_name'] ?? '—'),
            fmtNum((float) ($row['sold_units'] ?? 0)),
            fmtNum((float) ($row['delivered_units'] ?? 0)),
            fmtNum((float) ($row['pending_units'] ?? 0))
        ) . PHP_EOL;
    }
    echo 'TOTAL DIRECTO: vendido ' . fmtNum((float) $report['direct_totals']['sold']) . ' | entregado ' . fmtNum((float) $report['direct_totals']['delivered']) . ' | pendiente ' . fmtNum((float) $report['direct_totals']['pending']) . PHP_EOL . PHP_EOL;

    echo ansi("🎁 VENTAS VÍA COMBOS", '1;36') . PHP_EOL;
    if ($report['combo_defs'] === []) {
        echo "(no aparece en combos)" . PHP_EOL;
    } else {
        echo "Combos:" . PHP_EOL;
        foreach ($report['combo_defs'] as $d) {
            echo '• ' . ($d['combo_name'] ?? ('Combo #' . ($d['combo_id'] ?? ''))) . ' (' . fmtNum((float) ($d['units_per_combo'] ?? 0)) . " un/ combo)\n";
        }
        foreach ($report['combo_sales'] as $row) {
            echo sprintf(
                "- %-12s %-18s %-20s vend:%7s entreg:%7s pend:%7s",
                (string) ($row['quote_number'] ?? '—'),
                (string) ($row['status'] ?? '—'),
                (string) ($row['combo_name'] ?? '—'),
                fmtNum((float) ($row['units_sold'] ?? 0)),
                fmtNum((float) ($row['units_delivered'] ?? 0)),
                fmtNum((float) ($row['units_pending'] ?? 0))
            ) . PHP_EOL;
        }
    }
    echo 'TOTAL COMBOS: vendido ' . fmtNum((float) $report['combo_totals']['sold']) . ' | entregado ' . fmtNum((float) $report['combo_totals']['delivered']) . ' | pendiente ' . fmtNum((float) $report['combo_totals']['pending']) . PHP_EOL . PHP_EOL;

    echo ansi("🔧 AJUSTES MANUALES", '1;33') . PHP_EOL;
    if ($report['adjustments'] === []) {
        echo "(ninguno)\n";
    } else {
        foreach ($report['adjustments'] as $a) {
            $chg = (float) ($a['quantity_change'] ?? 0);
            echo sprintf(
                "- %10s  %8s  %s",
                fmtDate($a['created_at'] ?? null),
                ($chg >= 0 ? '+' : '') . fmtNum($chg),
                (string) ($a['reason'] ?? '—')
            ) . PHP_EOL;
        }
    }
    echo 'TOTAL AJUSTES: ' . fmtNum((float) $report['adjustments_total']) . PHP_EOL . PHP_EOL;

    echo ansi("⏳ STOCK COMPROMETIDO (pendiente)", '1;34') . PHP_EOL;
    foreach ($report['pending_lines'] as $pLine) {
        echo sprintf(
            "- %-12s %-18s %8s un. (%s)",
            (string) $pLine['quote_number'],
            (string) $pLine['client_name'],
            fmtNum((float) $pLine['pending_units']),
            (string) $pLine['source']
        ) . PHP_EOL;
    }
    echo 'TOTAL COMPROMETIDO TEÓRICO: ' . fmtNum((float) $report['pending_total']) . PHP_EOL . PHP_EOL;

    echo ansi("📊 RECONCILIACIÓN", '1;37') . PHP_EOL;
    echo sprintf("Stock físico:       teórico %-10s sistema %-10s diff %s\n",
        fmtNum((float) $r['stock_theory']),
        fmtNum((float) $r['stock_system']),
        abs((float) $r['diff_stock']) < 0.0001 ? ansi('✅ 0', '1;32') : ansi('⚠️ ' . fmtNum((float) $r['diff_stock']), '1;31')
    );
    echo sprintf("Comprometido:       teórico %-10s sistema %-10s diff %s\n",
        fmtNum((float) $r['committed_theory']),
        fmtNum((float) $r['committed_system']),
        abs((float) $r['diff_committed']) < 0.0001 ? ansi('✅ 0', '1;32') : ansi('⚠️ ' . fmtNum((float) $r['diff_committed']), '1;31')
    );
    echo sprintf("Disponible:         teórico %-10s sistema %-10s diff %s\n",
        fmtNum((float) $r['available_theory']),
        fmtNum((float) $r['available_system']),
        abs((float) $r['diff_available']) < 0.0001 ? ansi('✅ 0', '1;32') : ansi('⚠️ ' . fmtNum((float) $r['diff_available']), '1;31')
    );
    echo PHP_EOL . ansi("🔍 DIAGNÓSTICO", '1;33') . PHP_EOL;
    foreach (autoDiagnostics($report) as $d) {
        echo '- ' . $d . PHP_EOL;
    }
}

function printWeb(array $report): void
{
    $p = $report['product'];
    $r = $report['reconciliation'];
    $diag = autoDiagnostics($report);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<script src="https://cdn.tailwindcss.com"></script><title>Auditoría de stock</title></head><body class="bg-slate-100 text-slate-900">';
    echo '<div class="max-w-7xl mx-auto p-4 space-y-4">';
    echo '<div class="bg-white rounded-xl p-4 shadow"><h1 class="text-xl font-bold">Auditoría de Stock — ' . h((string) $p['code']) . ' ' . h((string) $p['name']) . '</h1>';
    echo '<p class="text-sm text-slate-600">Categoría: ' . h((string) ($p['category_name'] ?? '—')) . ' · Estado: ' . ((int) ($p['is_active'] ?? 0) === 1 ? '<span class="text-emerald-700 font-semibold">Activo</span>' : '<span class="text-red-700 font-semibold">Inactivo</span>') . '</p></div>';
    echo '<div class="bg-white rounded-xl p-4 shadow"><h2 class="font-semibold mb-2">Reconciliación</h2><div class="grid md:grid-cols-3 gap-2 text-sm">';
    $rows = [
        ['Stock', $r['stock_theory'], $r['stock_system'], $r['diff_stock']],
        ['Comprometido', $r['committed_theory'], $r['committed_system'], $r['diff_committed']],
        ['Disponible', $r['available_theory'], $r['available_system'], $r['diff_available']],
    ];
    foreach ($rows as $x) {
        $bad = abs((float) $x[3]) > 0.0001;
        echo '<div class="border rounded p-2 ' . ($bad ? 'border-red-300 bg-red-50' : 'border-emerald-300 bg-emerald-50') . '">';
        echo '<p class="font-semibold">' . h((string) $x[0]) . '</p>';
        echo '<p>Teórico: ' . h(fmtNum((float) $x[1])) . '</p><p>Sistema: ' . h(fmtNum((float) $x[2])) . '</p><p>Diff: ' . h(fmtNum((float) $x[3])) . '</p>';
        echo '</div>';
    }
    echo '</div></div>';
    echo '<div class="bg-white rounded-xl p-4 shadow"><h2 class="font-semibold mb-2">Diagnóstico</h2><ul class="list-disc pl-5 text-sm">';
    foreach ($diag as $d) {
        echo '<li>' . h($d) . '</li>';
    }
    echo '</ul></div>';
    echo '</div></body></html>';
}

/** @return array<int,array<string,mixed>> */
function summaryProducts(array $db): array
{
    $fetchAll = $db['fetchAll'];
    return $fetchAll(
        "SELECT p.id, p.code, p.name, p.stock_units, COALESCE(p.stock_committed_units,0) AS stock_committed_units, p.is_active
         FROM products p
         WHERE p.stock_units > 0 OR COALESCE(p.stock_committed_units,0) > 0
            OR EXISTS (
                SELECT 1
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                WHERE q.status IN ('accepted','partially_delivered') AND qi.product_id = p.id
            )
         ORDER BY p.stock_units DESC, p.name ASC
         LIMIT 200"
    );
}

$db = auditDb();
$input = isCli()
    ? (isset($argv[1]) ? trim((string) $argv[1]) : '')
    : trim((string) ($_GET['code'] ?? ''));

if ($input === '') {
    $list = summaryProducts($db);
    if (isCli()) {
        echo "Uso: php audit_stock.php <codigo|nombre>\n\n";
        echo "Productos con stock/compromiso:\n";
        foreach ($list as $r) {
            echo sprintf(
                "- [%d] %s | %s | stock=%s | comprometido=%s | %s\n",
                (int) $r['id'],
                (string) $r['code'],
                (string) $r['name'],
                fmtNum((float) ($r['stock_units'] ?? 0)),
                fmtNum((float) ($r['stock_committed_units'] ?? 0)),
                (int) ($r['is_active'] ?? 0) === 1 ? 'activo' : 'inactivo'
            );
        }
        exit(0);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 p-4">';
    echo '<div class="max-w-5xl mx-auto bg-white rounded-xl p-4 shadow"><h1 class="text-xl font-bold mb-3">Auditoría de stock</h1>';
    echo '<p class="text-sm text-slate-600 mb-4">Pasá ?code=CODIGO o ?code=nombre</p><ul class="text-sm space-y-1">';
    foreach ($list as $r) {
        $href = '?code=' . urlencode((string) $r['code']);
        echo '<li><a class="text-blue-700 hover:underline" href="' . h($href) . '">' . h((string) $r['code']) . ' — ' . h((string) $r['name']) . '</a></li>';
    }
    echo '</ul></div></body></html>';
    exit(0);
}

$candidates = findProducts($db, $input);
if ($candidates === []) {
    $msg = 'No se encontró producto para: ' . $input;
    if (isCli()) {
        fwrite(STDERR, $msg . PHP_EOL);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
    }
    exit(1);
}

$selected = null;
if (count($candidates) === 1) {
    $selected = $candidates[0];
} else {
    if (isCli()) {
        echo "Se encontraron múltiples productos:\n";
        foreach ($candidates as $idx => $c) {
            echo sprintf("%2d) [%s] %s\n", $idx + 1, (string) $c['code'], (string) $c['name']);
        }
        echo "Elegí número: ";
        $line = trim((string) fgets(STDIN));
        $n = (int) $line;
        if ($n < 1 || $n > count($candidates)) {
            fwrite(STDERR, "Selección inválida\n");
            exit(1);
        }
        $selected = $candidates[$n - 1];
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 p-4"><div class="max-w-4xl mx-auto bg-white rounded-xl p-4 shadow">';
        echo '<h1 class="text-lg font-bold mb-2">Múltiples resultados</h1><ul class="space-y-1">';
        foreach ($candidates as $c) {
            echo '<li><a class="text-blue-700 hover:underline" href="?code=' . urlencode((string) $c['code']) . '">' . h((string) $c['code']) . ' — ' . h((string) $c['name']) . '</a></li>';
        }
        echo '</ul></div></body></html>';
        exit(0);
    }
}

$report = auditProduct($db, (int) $selected['id']);
if (isCli()) {
    printCliReport($report);
} else {
    printWeb($report);
}

