<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/Env.php';
\App\Helpers\Env::load($root . '/.env');
$cfg = require $root . '/app/config/database.php';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['database']),
    $cfg['username'],
    $cfg['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$quote = $pdo->query(
    "SELECT id, quote_number, sale_number, ml_order_id, ml_sale_total, ml_net_amount, total, subtotal
     FROM quotes
     WHERE quote_number = 'LO-2026-0051' OR sale_number = 'LO-2026-0051'
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    fwrite(STDERR, "No se encontró la venta LO-2026-0051 en la base local.\n");
    exit(1);
}

echo "=== VENTA ===\n";
echo json_encode($quote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$sql = <<<'SQL'
SELECT 
    p.code,
    p.name,
    qi.quantity,
    qi.unit_price as precio_ml,
    ROUND(p.precio_lista_bidon * (1 - 0.35), 2) as costo_bidon,
    ROUND(p.precio_lista_caja * (1 - 0.35), 2) as costo_caja,
    ROUND(qi.unit_price - (p.precio_lista_bidon * (1 - 0.35)), 2) as ganancia_unitaria,
    ROUND(((qi.unit_price / NULLIF(p.precio_lista_bidon * (1 - 0.35), 0)) - 1) * 100, 1) as markup_real_pct,
    ROUND(qi.quantity * qi.unit_price, 2) as subtotal_ml,
    ROUND(qi.quantity * (p.precio_lista_bidon * (1 - 0.35)), 2) as costo_total_linea
FROM quote_items qi
JOIN products p ON p.id = qi.product_id
WHERE qi.quote_id = :quote_id
ORDER BY qi.sort_order
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute(['quote_id' => (int) $quote['id']]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== DESGLOSE POR LÍNEA ===\n";
foreach ($lines as $line) {
    echo str_repeat('-', 80) . "\n";
    foreach ($line as $k => $v) {
        echo sprintf("%-20s %s\n", $k . ':', $v);
    }
}

$totalMl = 0.0;
$totalCosto = 0.0;
foreach ($lines as $line) {
    $totalMl += (float) $line['subtotal_ml'];
    $totalCosto += (float) $line['costo_total_linea'];
}

$mlSaleTotal = (float) ($quote['ml_sale_total'] ?? $quote['total'] ?? $totalMl);
$mlNetAmount = (float) ($quote['ml_net_amount'] ?? 0);
$gananciaBruta = round($totalMl - $totalCosto, 2);
$gananciaReal = round($mlNetAmount - $totalCosto, 2);

echo "\n=== TOTALES VENTA ===\n";
echo sprintf("Total ML (ítems):     $ %s\n", number_format($totalMl, 2, '.', ''));
echo sprintf("Total ML (quote):     $ %s\n", number_format($mlSaleTotal, 2, '.', ''));
echo sprintf("Total costos:         $ %s\n", number_format($totalCosto, 2, '.', ''));
echo sprintf("Neto MP/ML:           $ %s\n", number_format($mlNetAmount, 2, '.', ''));
echo sprintf("Ganancia bruta (ML - costo): $ %s\n", number_format($gananciaBruta, 2, '.', ''));
echo sprintf("Ganancia real (neto - costo): $ %s\n", number_format($gananciaReal, 2, '.', ''));
