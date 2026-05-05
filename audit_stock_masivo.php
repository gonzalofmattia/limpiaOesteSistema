<?php
declare(strict_types=1);

/**
 * Auditoria masiva de stock (todos los productos).
 * Uso CLI: php audit_stock_masivo.php
 * Uso Web: /public/audit_stock_masivo.php (igual que audit_stock.php)
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

/** @return array{fetchAll:callable} */
function massAuditDb(): array
{
    if (class_exists('\App\Models\Database')) {
        try {
            $db = \App\Models\Database::getInstance();
            return [
                'fetchAll' => static fn(string $sql, array $params = []): array => $db->fetchAll($sql, $params),
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
        'fetchAll' => static function (string $sql, array $params = []) use ($pdo): array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
            return is_array($rows) ? $rows : [];
        },
    ];
}

function isCliMassAudit(): bool
{
    return PHP_SAPI === 'cli';
}

function hMass(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function toInt(mixed $v): int
{
    return (int) round((float) $v);
}

function signed(int $n): string
{
    return ($n > 0 ? '+' : '') . (string) $n;
}

function hasMovement(array $r): bool
{
    return toInt($r['total_comprado'] ?? 0) !== 0
        || toInt($r['entregado_directo'] ?? 0) !== 0
        || toInt($r['entregado_combos'] ?? 0) !== 0
        || toInt($r['ajustes_netos'] ?? 0) !== 0
        || toInt($r['comprometido_directo'] ?? 0) !== 0
        || toInt($r['comprometido_combos'] ?? 0) !== 0
        || toInt($r['stock_sistema'] ?? 0) !== 0
        || toInt($r['committed_sistema'] ?? 0) !== 0;
}

/** @return array<int,array<string,mixed>> */
function runMassAudit(array $db): array
{
    $fetchAll = $db['fetchAll'];
    $sql = "
        SELECT
            p.id,
            p.code,
            p.name,
            p.is_active,
            p.stock_units AS stock_sistema,
            p.stock_committed_units AS committed_sistema,
            (p.stock_units - p.stock_committed_units) AS disponible_sistema,
            p.units_per_box,
            cat.name AS category_name,

            COALESCE((
                SELECT SUM(soi.boxes_to_order * soi.units_per_box)
                FROM seiq_order_items soi
                JOIN seiq_orders so ON so.id = soi.seiq_order_id
                WHERE soi.product_id = p.id
                  AND so.status = 'received'
                  AND so.receipt_stock_applied = 1
            ), 0) AS total_comprado,

            COALESCE((
                SELECT SUM(qi.qty_delivered)
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                WHERE qi.product_id = p.id
                  AND q.status IN ('delivered', 'partially_delivered')
            ), 0) AS entregado_directo,

            COALESCE((
                SELECT SUM(qi.qty_delivered * cp.quantity)
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                JOIN combo_products cp ON cp.combo_id = qi.combo_id
                WHERE cp.product_id = p.id
                  AND q.status IN ('delivered', 'partially_delivered')
            ), 0) AS entregado_combos,

            COALESCE((
                SELECT SUM(difference)
                FROM stock_adjustments
                WHERE product_id = p.id
            ), 0) AS ajustes_netos,

            COALESCE((
                SELECT SUM(qi.quantity - qi.qty_delivered)
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                WHERE qi.product_id = p.id
                  AND q.status IN ('accepted', 'partially_delivered')
                  AND (qi.quantity - qi.qty_delivered) > 0
            ), 0) AS comprometido_directo,

            COALESCE((
                SELECT SUM((qi.quantity - qi.qty_delivered) * cp.quantity)
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                JOIN combo_products cp ON cp.combo_id = qi.combo_id
                WHERE cp.product_id = p.id
                  AND q.status IN ('accepted', 'partially_delivered')
                  AND (qi.quantity - qi.qty_delivered) > 0
            ), 0) AS comprometido_combos
        FROM products p
        LEFT JOIN categories cat ON cat.id = p.category_id
        ORDER BY p.code ASC
    ";

    $rows = $fetchAll($sql);
    $out = [];
    foreach ($rows as $row) {
        $stockTeorico = toInt($row['total_comprado'] ?? 0)
            - toInt($row['entregado_directo'] ?? 0)
            - toInt($row['entregado_combos'] ?? 0)
            + toInt($row['ajustes_netos'] ?? 0);
        $committedTeorico = toInt($row['comprometido_directo'] ?? 0) + toInt($row['comprometido_combos'] ?? 0);
        $disponibleTeorico = $stockTeorico - $committedTeorico;

        $stockSistema = toInt($row['stock_sistema'] ?? 0);
        $committedSistema = toInt($row['committed_sistema'] ?? 0);
        $diffStock = $stockTeorico - $stockSistema;
        $diffCommitted = $committedTeorico - $committedSistema;

        $out[] = $row + [
            'stock_teorico' => $stockTeorico,
            'committed_teorico' => $committedTeorico,
            'disponible_teorico' => $disponibleTeorico,
            'diff_stock' => $diffStock,
            'diff_committed' => $diffCommitted,
            'has_issue' => ($diffStock !== 0 || $diffCommitted !== 0),
            'has_movement' => hasMovement($row),
            'severity' => abs($diffStock) + abs($diffCommitted),
        ];
    }

    return $out;
}

/** @return array{issues:array,ok:array,no_movement:array} */
function splitGroups(array $audited): array
{
    $issues = [];
    $ok = [];
    $noMovement = [];
    foreach ($audited as $r) {
        if (!$r['has_movement']) {
            $noMovement[] = $r;
            continue;
        }
        if ($r['has_issue']) {
            $issues[] = $r;
        } else {
            $ok[] = $r;
        }
    }
    usort($issues, static fn(array $a, array $b): int => (int) ($b['severity'] <=> $a['severity']));
    usort($ok, static fn(array $a, array $b): int => strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));

    return ['issues' => $issues, 'ok' => $ok, 'no_movement' => $noMovement];
}

function buildFixQueries(array $issues): string
{
    $lines = [];
    $lines[] = '-- =====================================================';
    $lines[] = '-- Auditoría masiva de stock — ' . date('Y-m-d H:i:s');
    $lines[] = '-- Correcciones automáticas';
    $lines[] = '-- ⚠️ REVISAR cada línea antes de ejecutar';
    $lines[] = '-- =====================================================';
    $lines[] = '';
    $lines[] = '-- CORRECCIONES DE stock_units:';
    foreach ($issues as $r) {
        $diff = toInt($r['diff_stock'] ?? 0);
        if ($diff === 0) {
            continue;
        }
        $id = (int) ($r['id'] ?? 0);
        $code = str_replace("'", "''", (string) ($r['code'] ?? ''));
        $name = str_replace("\n", ' ', (string) ($r['name'] ?? ''));
        $name = str_replace("'", "''", $name);
        $actual = toInt($r['stock_sistema'] ?? 0);
        $teor = toInt($r['stock_teorico'] ?? 0);
        $lines[] = "UPDATE products SET stock_units = {$teor} WHERE id = {$id} AND code = '{$code}'; -- {$name} | era: {$actual}, teórico: {$teor}, diff: " . signed($diff);
    }
    $lines[] = '';
    $lines[] = '-- CORRECCIONES DE stock_committed_units:';
    foreach ($issues as $r) {
        $diff = toInt($r['diff_committed'] ?? 0);
        if ($diff === 0) {
            continue;
        }
        $id = (int) ($r['id'] ?? 0);
        $code = str_replace("'", "''", (string) ($r['code'] ?? ''));
        $name = str_replace("\n", ' ', (string) ($r['name'] ?? ''));
        $name = str_replace("'", "''", $name);
        $actual = toInt($r['committed_sistema'] ?? 0);
        $teor = toInt($r['committed_teorico'] ?? 0);
        $lines[] = "UPDATE products SET stock_committed_units = {$teor} WHERE id = {$id} AND code = '{$code}'; -- {$name} | era: {$actual}, teórico: {$teor}, diff: " . signed($diff);
    }

    return implode("\n", $lines);
}

function printCli(array $groups, array $audited): void
{
    $issues = $groups['issues'];
    $ok = $groups['ok'];
    $noMovement = $groups['no_movement'];
    echo "AUDITORIA MASIVA DE STOCK\n";
    echo "=========================\n";
    echo "Auditados:      " . count($audited) . "\n";
    echo "Con problemas:  " . count($issues) . "\n";
    echo "OK:             " . count($ok) . "\n";
    echo "Sin movimiento: " . count($noMovement) . "\n\n";

    if ($issues !== []) {
        echo "PRODUCTOS CON INCONSISTENCIAS\n";
        echo "-----------------------------\n";
        foreach ($issues as $r) {
            echo sprintf(
                "[%s] %s | stock %d/%d (diff %s) | comp %d/%d (diff %s)\n",
                (string) ($r['code'] ?? ''),
                (string) ($r['name'] ?? ''),
                toInt($r['stock_sistema'] ?? 0),
                toInt($r['stock_teorico'] ?? 0),
                signed(toInt($r['diff_stock'] ?? 0)),
                toInt($r['committed_sistema'] ?? 0),
                toInt($r['committed_teorico'] ?? 0),
                signed(toInt($r['diff_committed'] ?? 0))
            );
        }
        echo "\n";
    }
    echo buildFixQueries($issues) . "\n";
}

function printWeb(array $groups, array $audited): void
{
    $issues = $groups['issues'];
    $ok = $groups['ok'];
    $noMovement = $groups['no_movement'];
    $sumStockPos = 0;
    $sumStockNeg = 0;
    $sumCompPos = 0;
    $sumCompNeg = 0;
    foreach ($issues as $r) {
        $ds = toInt($r['diff_stock'] ?? 0);
        $dc = toInt($r['diff_committed'] ?? 0);
        if ($ds >= 0) {
            $sumStockPos += $ds;
        } else {
            $sumStockNeg += $ds;
        }
        if ($dc >= 0) {
            $sumCompPos += $dc;
        } else {
            $sumCompNeg += $dc;
        }
    }
    $queries = buildFixQueries($issues);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>';
    echo '<style>[x-cloak]{display:none!important;}</style>';
    echo '<title>Auditoría masiva de stock</title></head><body class="bg-slate-100 text-slate-900">';
    echo '<div class="max-w-6xl mx-auto p-6 space-y-6">';

    echo '<div><h1 class="text-2xl font-bold">Auditoría masiva de stock</h1><p class="text-sm text-slate-600 mt-1">Reconciliación de stock_units y stock_committed_units para todos los productos.</p></div>';

    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4">';
    echo '<div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-slate-500">Auditados</p><p class="text-2xl font-bold">' . count($audited) . ' productos</p></div>';
    echo '<div class="bg-white rounded-lg shadow p-4 ' . (count($issues) > 0 ? 'border border-red-200' : 'border border-emerald-200') . '"><p class="text-sm text-slate-500">⚠️ Con problemas</p><p class="text-2xl font-bold ' . (count($issues) > 0 ? 'text-red-700' : 'text-emerald-700') . '">' . count($issues) . ' productos</p></div>';
    echo '<div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-slate-500">✅ OK</p><p class="text-2xl font-bold text-emerald-700">' . count($ok) . ' productos</p></div>';
    echo '<div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-slate-500">Sin movimiento</p><p class="text-2xl font-bold text-slate-700">' . count($noMovement) . ' productos</p></div>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-5">';
    echo '<h2 class="text-lg font-semibold mb-3">⚠️ Productos con inconsistencias (' . count($issues) . ')</h2>';
    if ($issues === []) {
        echo '<p class="text-sm text-emerald-700">No se detectaron inconsistencias.</p>';
    } else {
        echo '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50"><tr>';
        foreach (['Código','Producto','Categoría','Activo','Stock Sist.','Stock Teór.','Diff Stock','Comp. Sist.','Comp. Teór.','Diff Comp.','Detalle'] as $h) {
            echo '<th class="text-left px-2 py-2 border-b">' . hMass($h) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($issues as $r) {
            $active = (int) ($r['is_active'] ?? 0) === 1;
            $ds = toInt($r['diff_stock'] ?? 0);
            $dc = toInt($r['diff_committed'] ?? 0);
            echo '<tr class="hover:bg-slate-50">';
            echo '<td class="px-2 py-2 border-b font-mono">' . hMass((string) ($r['code'] ?? '')) . '</td>';
            echo '<td class="px-2 py-2 border-b">' . hMass((string) ($r['name'] ?? '')) . '</td>';
            echo '<td class="px-2 py-2 border-b">' . hMass((string) ($r['category_name'] ?? '—')) . '</td>';
            echo '<td class="px-2 py-2 border-b">' . ($active
                ? '<span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">Activo</span>'
                : '<span class="inline-flex px-2 py-0.5 rounded text-xs bg-red-100 text-red-800">Inactivo</span>') . '</td>';
            echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['stock_sistema'] ?? 0) . '</td>';
            echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['stock_teorico'] ?? 0) . '</td>';
            echo '<td class="px-2 py-2 border-b text-right ' . ($ds !== 0 ? 'bg-red-100 text-red-800 font-bold' : 'bg-green-50 text-green-700') . '">' . hMass(signed($ds)) . '</td>';
            echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['committed_sistema'] ?? 0) . '</td>';
            echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['committed_teorico'] ?? 0) . '</td>';
            echo '<td class="px-2 py-2 border-b text-right ' . ($dc !== 0 ? 'bg-red-100 text-red-800 font-bold' : 'bg-green-50 text-green-700') . '">' . hMass(signed($dc)) . '</td>';
            echo '<td class="px-2 py-2 border-b"><a href="audit_stock.php?code=' . urlencode((string) ($r['code'] ?? '')) . '" target="_blank" class="text-blue-600 hover:underline text-sm">Ver historial →</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="mt-3 text-sm text-slate-700">';
        echo '<p>Suma de diferencias de stock: ' . signed($sumStockPos) . ' / ' . signed($sumStockNeg) . ' unidades</p>';
        echo '<p>Suma de diferencias de comprometido: ' . signed($sumCompPos) . ' / ' . signed($sumCompNeg) . ' unidades</p>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="bg-gray-900 rounded-lg p-5 mt-6 overflow-x-auto">';
    echo '<div class="flex justify-between items-center mb-3">';
    echo '<h3 class="text-white font-semibold">Queries de corrección SQL</h3>';
    echo '<button onclick="copyQueries(event)" class="bg-blue-600 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-700">📋 Copiar todo</button>';
    echo '</div>';
    echo '<pre id="fix-queries" class="text-green-400 text-sm font-mono whitespace-pre">' . hMass($queries) . '</pre>';
    echo '</div>';

    echo '<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-4">';
    echo '<h4 class="font-semibold text-yellow-800">⚠️ Antes de ejecutar las queries:</h4>';
    echo '<ol class="list-decimal ml-5 mt-2 text-yellow-700 text-sm space-y-1">';
    echo '<li>Estas correcciones asumen que los registros de compras, ventas y ajustes son correctos</li>';
    echo '<li>Si un pedido al proveedor no se marcó como received, el stock teórico será menor al real</li>';
    echo '<li>Si una entrega se hizo sin marcar el presupuesto como delivered, el stock teórico será mayor al real</li>';
    echo '<li>Para productos con diferencias grandes, usar <strong>audit_stock.php?code=XXX</strong> para ver el historial completo antes de corregir</li>';
    echo '<li>Hacer un backup de la BD antes: <code>php db_export.php</code></li>';
    echo '</ol></div>';

    echo '<div x-data="{ open: false }" class="mt-8 bg-white rounded-lg shadow p-5">';
    echo '<button @click="open = !open" class="text-sm text-gray-600 hover:text-gray-900 flex items-center gap-1">';
    echo '<span x-text="open ? \'▾\' : \'▸\'"></span> ✅ Productos OK (' . count($ok) . ') — stock y comprometido coinciden</button>';
    echo '<div x-show="open" x-cloak class="mt-3 overflow-x-auto">';
    echo '<table class="w-full text-sm"><thead class="bg-gray-50"><tr>';
    foreach (['Código','Producto','Stock','Comprometido','Disponible'] as $h) {
        echo '<th class="text-left px-2 py-2 border-b">' . hMass($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($ok as $r) {
        $disp = toInt($r['stock_sistema'] ?? 0) - toInt($r['committed_sistema'] ?? 0);
        echo '<tr class="hover:bg-slate-50">';
        echo '<td class="px-2 py-2 border-b font-mono">' . hMass((string) ($r['code'] ?? '')) . '</td>';
        echo '<td class="px-2 py-2 border-b">' . hMass((string) ($r['name'] ?? '')) . '</td>';
        echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['stock_sistema'] ?? 0) . '</td>';
        echo '<td class="px-2 py-2 border-b text-right">' . toInt($r['committed_sistema'] ?? 0) . '</td>';
        echo '<td class="px-2 py-2 border-b text-right">' . $disp . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';

    echo '</div>';
    echo '<script>
function copyQueries(event) {
    const text = document.getElementById("fix-queries").innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const old = btn.textContent;
        btn.textContent = "✅ Copiado!";
        setTimeout(() => btn.textContent = old, 2000);
    });
}
</script>';
    echo '</body></html>';
}

$db = massAuditDb();
$audited = runMassAudit($db);
$groups = splitGroups($audited);
if (isCliMassAudit()) {
    printCli($groups, $audited);
} else {
    printWeb($groups, $audited);
}

