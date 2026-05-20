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

echo "=== DESCRIBE products ===\n\n";
$describe = $pdo->query('DESCRIBE products')->fetchAll(PDO::FETCH_ASSOC);
foreach ($describe as $col) {
    printf(
        "%-30s %-25s %-8s %-8s %-20s %s\n",
        $col['Field'],
        $col['Type'],
        $col['Null'],
        $col['Key'],
        $col['Default'] ?? 'NULL',
        $col['Extra'] ?? ''
    );
}

echo "\n=== 3 filas de ejemplo (SELECT *) ===\n\n";
$rows = $pdo->query('SELECT * FROM products ORDER BY id ASC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "(sin filas)\n";
    exit(0);
}

$fields = array_keys($rows[0]);

foreach ($rows as $i => $row) {
    echo "--- Producto #" . ($i + 1) . " (id={$row['id']}) ---\n";
    foreach ($fields as $field) {
        $val = $row[$field];
        if ($val === null) {
            $display = '[NULL]';
            $status = 'VACÍO';
        } elseif ($val === '') {
            $display = '[cadena vacía]';
            $status = 'VACÍO';
        } else {
            $str = (string) $val;
            $display = strlen($str) > 120 ? substr($str, 0, 120) . '…' : $str;
            $status = 'OK';
        }
        printf("  %-28s %-6s %s\n", $field, $status, $display);
    }
    echo "\n";
}

echo "=== Resumen: campos vacíos en estas 3 filas ===\n";
$emptyCount = array_fill_keys($fields, 0);
foreach ($rows as $row) {
    foreach ($fields as $field) {
        $v = $row[$field];
        if ($v === null || $v === '') {
            $emptyCount[$field]++;
        }
    }
}
foreach ($emptyCount as $field => $count) {
    if ($count > 0) {
        echo "  {$field}: vacío en {$count}/3 filas\n";
    }
}

echo "\n=== Totales en tabla (productos con campo NULL o '') ===\n";
$total = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
echo "Total productos: {$total}\n";
foreach ($fields as $field) {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
        continue;
    }
    $sql = "SELECT COUNT(*) FROM products WHERE `{$field}` IS NULL OR `{$field}` = ''";
    $empty = (int) $pdo->query($sql)->fetchColumn();
    if ($empty > 0) {
        $pct = $total > 0 ? round(100 * $empty / $total, 1) : 0;
        echo "  {$field}: {$empty}/{$total} vacíos ({$pct}%)\n";
    }
}
