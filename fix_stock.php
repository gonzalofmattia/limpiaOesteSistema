<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/QuoteLinePricing.php';
require_once APP_PATH . '/Helpers/QuoteDeliveryStock.php';

use App\Helpers\Env;
use App\Helpers\QuoteDeliveryStock;
use App\Models\Database;

Env::load(BASE_PATH . '/.env');
$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'America/Argentina/Buenos_Aires');
session_name($appConfig['session_name'] ?? 'limpia_oeste_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin_user_id'])) {
    redirect('/login');
}

$db = Database::getInstance();
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'report')));
$allowedModes = ['report', 'fix_committed', 'fix_physical_preview', 'fix_physical_apply'];
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'report';
}
$view = strtolower(trim((string) ($_GET['view'] ?? 'all')));
$allowedViews = ['all', 'diff_only', 'physical_only', 'issues_only'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'all';
}

$hasDeliveryApplied = (bool) $db->fetchColumn(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'delivery_stock_applied'"
);

$products = $db->fetchAll(
    'SELECT id, code, name, stock_units, COALESCE(stock_committed_units, 0) AS stock_committed_units
     FROM products
     ORDER BY name'
);
$productById = [];
foreach ($products as $p) {
    $productById[(int) $p['id']] = $p;
}

$acceptedQuotes = $db->fetchAll(
    "SELECT q.id, q.quote_number, q.status, c.name AS client_name
     FROM quotes q
     LEFT JOIN clients c ON c.id = q.client_id
     WHERE q.status = 'accepted'
     ORDER BY q.id"
);
$acceptedQuoteIds = array_map(static fn (array $q): int => (int) $q['id'], $acceptedQuotes);
$acceptedById = [];
foreach ($acceptedQuotes as $q) {
    $acceptedById[(int) $q['id']] = $q;
}
$acceptedUnitsByQuote = QuoteDeliveryStock::unitsByProductForQuotes($db, $acceptedQuoteIds);
$acceptedDemandRows = QuoteDeliveryStock::demandBreakdownForQuotes($db, $acceptedQuoteIds);

$expectedCommittedByProduct = [];
foreach ($acceptedUnitsByQuote as $rows) {
    foreach ($rows as $pid => $units) {
        $expectedCommittedByProduct[(int) $pid] = ($expectedCommittedByProduct[(int) $pid] ?? 0) + (int) $units;
    }
}
$acceptedDemandByProduct = [];
foreach ($acceptedDemandRows as $row) {
    $pid = (int) ($row['product_id'] ?? 0);
    $qid = (int) ($row['quote_id'] ?? 0);
    if ($pid <= 0 || $qid <= 0) {
        continue;
    }
    $q = $acceptedById[$qid] ?? null;
    $acceptedDemandByProduct[$pid][] = [
        'quote_id' => $qid,
        'quote_number' => (string) ($q['quote_number'] ?? ('#' . $qid)),
        'client_name' => (string) ($q['client_name'] ?? '—'),
        'units' => (int) ($row['units'] ?? 0),
        'source_type' => (string) ($row['source_type'] ?? 'product'),
        'combo_name' => (string) ($row['combo_name'] ?? ''),
    ];
}

$deliveredWhere = $hasDeliveryApplied
    ? "WHERE q.status = 'delivered' AND COALESCE(q.delivery_stock_applied, 0) = 0"
    : "WHERE q.status = 'delivered'";
$deliveredCandidates = $db->fetchAll(
    "SELECT q.id, q.quote_number, q.status, q.created_at, c.name AS client_name
     FROM quotes q
     LEFT JOIN clients c ON c.id = q.client_id
     {$deliveredWhere}
     ORDER BY q.id"
);
$deliveredCandidateIds = array_map(static fn (array $q): int => (int) $q['id'], $deliveredCandidates);
$deliveredById = [];
foreach ($deliveredCandidates as $q) {
    $deliveredById[(int) $q['id']] = $q;
}
$deliveredDemandRows = QuoteDeliveryStock::demandBreakdownForQuotes($db, $deliveredCandidateIds);
$physicalToSubtractByProduct = [];
$deliveredDemandByProduct = [];
foreach ($deliveredDemandRows as $row) {
    $pid = (int) ($row['product_id'] ?? 0);
    $qid = (int) ($row['quote_id'] ?? 0);
    $units = (int) ($row['units'] ?? 0);
    if ($pid <= 0 || $qid <= 0 || $units <= 0) {
        continue;
    }
    $physicalToSubtractByProduct[$pid] = ($physicalToSubtractByProduct[$pid] ?? 0) + $units;
    $q = $deliveredById[$qid] ?? null;
    $deliveredDemandByProduct[$pid][] = [
        'quote_id' => $qid,
        'quote_number' => (string) ($q['quote_number'] ?? ('#' . $qid)),
        'client_name' => (string) ($q['client_name'] ?? '—'),
        'units' => $units,
        'source_type' => (string) ($row['source_type'] ?? 'product'),
        'combo_name' => (string) ($row['combo_name'] ?? ''),
    ];
}

$rows = [];
$totalCommittedAbsDiff = 0;
foreach ($products as $p) {
    $pid = (int) $p['id'];
    $currentCommitted = (int) ($p['stock_committed_units'] ?? 0);
    $expectedCommitted = (int) ($expectedCommittedByProduct[$pid] ?? 0);
    $committedDiff = $expectedCommitted - $currentCommitted;
    $totalCommittedAbsDiff += abs($committedDiff);
    $rows[] = [
        'id' => $pid,
        'name' => (string) ($p['name'] ?? ''),
        'code' => (string) ($p['code'] ?? ''),
        'stock_units' => (int) ($p['stock_units'] ?? 0),
        'current_committed' => $currentCommitted,
        'expected_committed' => $expectedCommitted,
        'committed_diff' => $committedDiff,
        'accepted_demand' => $acceptedDemandByProduct[$pid] ?? [],
        'delivered_not_applied' => $deliveredDemandByProduct[$pid] ?? [],
        'physical_to_subtract' => (int) ($physicalToSubtractByProduct[$pid] ?? 0),
    ];
}

$operationMessage = '';
$operationLog = null;
if ($mode === 'fix_committed') {
    $changes = [];
    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            if ((int) $r['committed_diff'] === 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_committed_units = :v WHERE id = :pid',
                ['v' => (int) $r['expected_committed'], 'pid' => (int) $r['id']]
            );
            $changes[] = [
                'product_id' => (int) $r['id'],
                'code' => (string) $r['code'],
                'name' => (string) $r['name'],
                'before' => (int) $r['current_committed'],
                'after' => (int) $r['expected_committed'],
            ];
        }
        $pdo->commit();
        $operationMessage = 'Corrección de comprometido aplicada. Productos afectados: ' . count($changes) . '.';
        $operationLog = ['mode' => $mode, 'changes' => $changes];
        foreach ($rows as &$r) {
            $r['current_committed'] = (int) $r['expected_committed'];
            $r['committed_diff'] = 0;
        }
        unset($r);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $operationMessage = 'Error al aplicar fix_committed: ' . $e->getMessage();
    }
}

if ($mode === 'fix_physical_preview') {
    $tokenRaw = bin2hex(random_bytes(16));
    $ts = time();
    $_SESSION['_fix_stock_physical'] = [
        'token' => $tokenRaw,
        'issued_at' => $ts,
        'expires_at' => $ts + 900,
        'quote_ids' => $deliveredCandidateIds,
    ];
}

if ($mode === 'fix_physical_apply') {
    $providedToken = (string) ($_GET['token'] ?? '');
    $providedTs = (int) ($_GET['ts'] ?? 0);
    $guard = $_SESSION['_fix_stock_physical'] ?? null;
    $validGuard = is_array($guard)
        && ($guard['token'] ?? '') === $providedToken
        && (int) ($guard['issued_at'] ?? 0) === $providedTs
        && (int) ($guard['expires_at'] ?? 0) >= time();
    if (!$validGuard) {
        $operationMessage = 'Token inválido o vencido. Ejecutá primero fix_physical_preview.';
    } else {
        $changes = [];
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $delta = (int) ($r['physical_to_subtract'] ?? 0);
                if ($delta <= 0) {
                    continue;
                }
                $before = (int) ($r['stock_units'] ?? 0);
                $after = max(0, $before - $delta);
                $db->query(
                    'UPDATE products SET stock_units = :v WHERE id = :pid',
                    ['v' => $after, 'pid' => (int) $r['id']]
                );
                $changes[] = [
                    'product_id' => (int) $r['id'],
                    'code' => (string) $r['code'],
                    'name' => (string) $r['name'],
                    'before' => $before,
                    'after' => $after,
                    'subtracted' => $delta,
                ];
            }
            if ($hasDeliveryApplied && $deliveredCandidateIds !== []) {
                $ph = implode(',', array_fill(0, count($deliveredCandidateIds), '?'));
                $db->query("UPDATE quotes SET delivery_stock_applied = 1 WHERE id IN ({$ph})", $deliveredCandidateIds);
            }
            $pdo->commit();
            unset($_SESSION['_fix_stock_physical']);
            $operationMessage = 'Corrección física aplicada. Productos afectados: ' . count($changes) . '.';
            $operationLog = ['mode' => $mode, 'changes' => $changes, 'quote_ids' => $deliveredCandidateIds];
            foreach ($rows as &$r) {
                $r['stock_units'] = max(0, (int) $r['stock_units'] - (int) $r['physical_to_subtract']);
                $r['physical_to_subtract'] = 0;
                $r['delivered_not_applied'] = [];
            }
            unset($r);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $operationMessage = 'Error al aplicar fix_physical_apply: ' . $e->getMessage();
        }
    }
}

if ($operationLog !== null) {
    $logDir = STORAGE_PATH . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $username = (string) ($_SESSION['admin_username'] ?? 'admin');
    $line = json_encode([
        'at' => date('c'),
        'user' => $username,
        'mode' => $operationLog['mode'] ?? $mode,
        'changes' => $operationLog['changes'] ?? [],
        'meta' => $operationLog,
    ], JSON_UNESCAPED_UNICODE);
    if (is_string($line)) {
        @file_put_contents($logDir . '/fix_stock.log', $line . PHP_EOL, FILE_APPEND);
    }
}

$guard = $_SESSION['_fix_stock_physical'] ?? null;
$applyLink = null;
if (is_array($guard) && !empty($guard['token']) && !empty($guard['issued_at']) && (int) ($guard['expires_at'] ?? 0) >= time()) {
    $applyLink = '?mode=fix_physical_apply&token=' . urlencode((string) $guard['token']) . '&ts=' . (int) $guard['issued_at'];
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Fix Stock</title>';
echo '<style>body{font-family:Arial,sans-serif;margin:20px;color:#1f2937}a{color:#0f766e}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e5e7eb;padding:6px;font-size:12px;vertical-align:top}th{background:#f9fafb;position:sticky;top:0}.ok{color:#0f766e}.warn{color:#b45309}.err{color:#b91c1c}code{background:#f3f4f6;padding:1px 4px;border-radius:4px}</style>';
echo '</head><body>';
echo '<h2>Fix de stock</h2>';
echo '<p>Modo actual: <strong>' . e($mode) . '</strong></p>';
echo '<p><a href="?mode=report&view=' . e($view) . '">Reporte</a> | <a href="?mode=fix_committed&view=' . e($view) . '" onclick="return confirm(\'¿Aplicar recálculo de stock comprometido?\')">Aplicar fix_committed</a> | <a href="?mode=fix_physical_preview&view=' . e($view) . '">Preview fix_physical</a>';
if ($applyLink !== null) {
    echo ' | <a href="' . e($applyLink . '&view=' . urlencode($view)) . '" onclick="return confirm(\'Confirmación final: ¿aplicar descuento físico?\')">Aplicar fix_physical</a>';
}
echo '</p>';
echo '<p>Vista: ';
echo '<a href="?mode=' . e($mode) . '&view=all">Todos</a> | ';
echo '<a href="?mode=' . e($mode) . '&view=diff_only">Solo diferencia comprometido</a> | ';
echo '<a href="?mode=' . e($mode) . '&view=physical_only">Solo pendiente físico</a> | ';
echo '<a href="?mode=' . e($mode) . '&view=issues_only">Solo con issues</a>';
echo '</p>';
if ($operationMessage !== '') {
    $cls = str_contains(strtolower($operationMessage), 'error') ? 'err' : 'ok';
    echo '<p class="' . $cls . '"><strong>' . e($operationMessage) . '</strong></p>';
}
if ($mode === 'fix_physical_preview' && $applyLink !== null) {
    echo '<p class="warn">Preview generado. Tenés 15 minutos para aplicar con doble confirmación.</p>';
}
echo '<p>Presupuestos delivered candidatos a no descontar físico: <strong>' . count($deliveredCandidateIds) . '</strong> ';
echo $hasDeliveryApplied ? '(delivery_stock_applied = 0).' : '(sin bandera delivery_stock_applied; revisar manualmente).';
echo '</p>';
echo '<p>Suma absoluta diferencia comprometido: <strong>' . $totalCommittedAbsDiff . ' un.</strong></p>';

echo '<div style="max-height:72vh;overflow:auto"><table><thead><tr>';
echo '<th>Producto</th><th>Código</th><th>Stock físico actual</th><th>Comprometido actual</th><th>Comprometido correcto</th><th>Diferencia</th><th>Presupuestos accepted que lo usan</th><th>Presupuestos delivered sin descuento físico</th>';
echo '</tr></thead><tbody>';
foreach ($rows as $r) {
    $hasCommittedDiff = (int) ($r['committed_diff'] ?? 0) !== 0;
    $hasPhysicalPending = (int) ($r['physical_to_subtract'] ?? 0) > 0;
    $showRow = match ($view) {
        'diff_only' => $hasCommittedDiff,
        'physical_only' => $hasPhysicalPending,
        'issues_only' => $hasCommittedDiff || $hasPhysicalPending,
        default => true,
    };
    if (!$showRow) {
        continue;
    }
    $diff = (int) ($r['committed_diff'] ?? 0);
    $diffCls = $diff === 0 ? 'ok' : 'warn';
    echo '<tr>';
    echo '<td>' . e((string) $r['name']) . '</td>';
    echo '<td>' . e((string) $r['code']) . '</td>';
    echo '<td>' . (int) $r['stock_units'] . '</td>';
    echo '<td>' . (int) $r['current_committed'] . '</td>';
    echo '<td>' . (int) $r['expected_committed'] . '</td>';
    echo '<td class="' . $diffCls . '">' . sprintf('%+d', $diff) . '</td>';
    $acceptedList = $r['accepted_demand'] ?? [];
    if ($acceptedList === []) {
        echo '<td>—</td>';
    } else {
        echo '<td><ul style="margin:0;padding-left:16px">';
        foreach ($acceptedList as $d) {
            $extra = ($d['source_type'] ?? '') === 'combo' ? (' (combo: ' . ($d['combo_name'] ?: 'sin nombre') . ')') : '';
            echo '<li>' . e((string) ($d['quote_number'] ?? '')) . ' — ' . e((string) ($d['client_name'] ?? '—')) . ' — ' . (int) ($d['units'] ?? 0) . ' un.' . e($extra) . '</li>';
        }
        echo '</ul></td>';
    }
    $deliveredList = $r['delivered_not_applied'] ?? [];
    if ($deliveredList === []) {
        echo '<td>—</td>';
    } else {
        echo '<td><div>Restar: <strong>' . (int) ($r['physical_to_subtract'] ?? 0) . ' un.</strong></div><ul style="margin:0;padding-left:16px">';
        foreach ($deliveredList as $d) {
            $extra = ($d['source_type'] ?? '') === 'combo' ? (' (combo: ' . ($d['combo_name'] ?: 'sin nombre') . ')') : '';
            echo '<li>' . e((string) ($d['quote_number'] ?? '')) . ' — ' . e((string) ($d['client_name'] ?? '—')) . ' — ' . (int) ($d['units'] ?? 0) . ' un.' . e($extra) . '</li>';
        }
        echo '</ul></td>';
    }
    echo '</tr>';
}
echo '</tbody></table></div>';
echo '</body></html>';
