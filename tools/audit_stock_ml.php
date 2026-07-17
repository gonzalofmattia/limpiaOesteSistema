<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');

$host = \App\Helpers\Env::get('DB_HOST', 'localhost');
$name = \App\Helpers\Env::get('DB_NAME', '');
$user = \App\Helpers\Env::get('DB_USER', 'root');
$pass = \App\Helpers\Env::get('DB_PASS', '');

$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Base: {$name} ===\n\n";

echo "=== Ventas ML: distribución de status ===\n";
$rows = $pdo->query(
    "SELECT status, delivery_stock_applied, COUNT(*) AS n
     FROM quotes
     WHERE COALESCE(is_mercadolibre,0)=1
     GROUP BY status, delivery_stock_applied
     ORDER BY n DESC"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf("status=%-20s delivery_stock_applied=%-3s n=%s\n", $r['status'], $r['delivery_stock_applied'], $r['n']);
}

echo "\n=== Total ventas ML ===\n";
$total = $pdo->query("SELECT COUNT(*) FROM quotes WHERE COALESCE(is_mercadolibre,0)=1")->fetchColumn();
echo "Total: {$total}\n";

echo "\n=== Fecha de la venta ML mas vieja / mas nueva ===\n";
$r = $pdo->query("SELECT MIN(created_at) AS min_d, MAX(created_at) AS max_d FROM quotes WHERE COALESCE(is_mercadolibre,0)=1")->fetch(PDO::FETCH_ASSOC);
echo "Min: {$r['min_d']}  Max: {$r['max_d']}\n";

echo "\n=== Cuotas normales (no ML) por status (para comparar) ===\n";
$rows = $pdo->query(
    "SELECT status, delivery_stock_applied, COUNT(*) AS n
     FROM quotes
     WHERE COALESCE(is_mercadolibre,0)=0
     GROUP BY status, delivery_stock_applied
     ORDER BY n DESC"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf("status=%-20s delivery_stock_applied=%-3s n=%s\n", $r['status'], $r['delivery_stock_applied'], $r['n']);
}

echo "\n=== Unidades vendidas via ML (accepted, nunca delivered) por producto, top 20 ===\n";
$rows = $pdo->query(
    "SELECT p.code, p.name, p.stock_units, COALESCE(p.stock_committed_units,0) AS committed,
            SUM(CASE WHEN qi.unit_type='unidad' THEN qi.quantity ELSE qi.quantity * COALESCE(p.units_per_box,1) END) AS unidades_vendidas_ml
     FROM quote_items qi
     JOIN quotes q ON q.id = qi.quote_id
     JOIN products p ON p.id = qi.product_id
     WHERE COALESCE(q.is_mercadolibre,0) = 1
     GROUP BY p.id
     ORDER BY unidades_vendidas_ml DESC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf(
        "%-15s %-40s stock=%-6s committed=%-6s vendido_ml=%s\n",
        $r['code'], mb_substr($r['name'],0,38), $r['stock_units'], $r['committed'], $r['unidades_vendidas_ml']
    );
}

echo "\n=== Existe columna ml_order_id en quotes? ===\n";
$c = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='ml_order_id'")->fetchColumn();
echo $c > 0 ? "Si\n" : "No\n";

echo "\n=== stock_adjustments recientes (ultimos 15) ===\n";
try {
    $rows = $pdo->query(
        "SELECT sa.created_at, p.code, sa.previous_stock, sa.new_stock, sa.difference, sa.notes, sa.created_by
         FROM stock_adjustments sa
         JOIN products p ON p.id = sa.product_id
         ORDER BY sa.id DESC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        printf("%-20s %-12s %6s -> %-6s (%+d) by=%-8s %s\n", $r['created_at'], $r['code'], $r['previous_stock'], $r['new_stock'], $r['difference'], $r['created_by'], $r['notes']);
    }
} catch (Throwable $e) {
    echo "sin tabla stock_adjustments o error: " . $e->getMessage() . "\n";
}
