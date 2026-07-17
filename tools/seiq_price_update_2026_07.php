<?php

declare(strict_types=1);

/**
 * Aumento SEIQ julio 2026 (ácido sulfónico) — Lista de precios N° 24.
 * Aplica el precio de lista nuevo (tal cual las listas oficiales que mandó Rodrigo)
 * a los productos puntuales que subieron. Verificado contra las 5 listas completas
 * (bidones/sobres/aerosoles/masivo/alimenticia, 192 códigos): son los únicos que cambiaron,
 * el resto del catálogo Seiq vino idéntico.
 *
 * Uso:
 *   php tools/seiq_price_update_2026_07.php            (dry-run, no toca la DB)
 *   php tools/seiq_price_update_2026_07.php --apply     (aplica los cambios)
 */

if (file_exists(__DIR__ . '/../.production')) {
    die('Este script no debe ejecutarse en producción.' . PHP_EOL);
}

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (is_file(__DIR__ . '/../app/Helpers/Env.php')) {
    require_once __DIR__ . '/../app/Helpers/Env.php';
}
if (class_exists('\App\Helpers\Env')) {
    \App\Helpers\Env::load(__DIR__ . '/../.env');
}

$pdo = new PDO(
    'mysql:host=' . (\App\Helpers\Env::get('DB_HOST', 'localhost')) . ';dbname=' . (\App\Helpers\Env::get('DB_NAME', '')) . ';charset=utf8mb4',
    (string) \App\Helpers\Env::get('DB_USER', 'root'),
    (string) \App\Helpers\Env::get('DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$apply = in_array('--apply', $argv, true);

/**
 * code => ['caja' => [old, new], 'bidon' => [...], 'litro' => [...], 'unitario' => [...], 'sobre' => [...]]
 * old = valor esperado en DB antes del cambio (se verifica antes de tocar nada).
 * new = valor de la lista oficial Seiq N° 24 (julio 2026).
 */
$changes = [
    // Bidones
    '861022' => ['caja' => [41922.12, 47371.99], 'bidon' => [10480.53, 11843.00], 'litro' => [2096.11, 2368.60]], // SW 112
    '8256' => ['caja' => [39610.78, 43571.85], 'bidon' => [9902.69, 10892.96], 'litro' => [1980.54, 2178.59]], // DESCAC 90
    '861017' => ['caja' => [57481.80, 64954.44], 'bidon' => [14370.45, 16238.61], 'litro' => [2874.09, 3247.72]], // DX 110
    '2026F' => ['caja' => [57481.80, 64954.44], 'bidon' => [14370.45, 16238.61], 'litro' => [2874.09, 3247.72]], // DX 111
    '398120' => ['caja' => [28009.73, 31651.00], 'bidon' => [7002.43, 7912.75], 'litro' => [1400.49, 1582.55]], // Clean Outlet
    '861018' => ['caja' => [43195.13, 48810.50], 'bidon' => [10798.78, 12202.62], 'litro' => [2159.76, 2440.52]], // CP 130
    '861024' => ['caja' => [33744.89, 37119.38], 'bidon' => [8436.22, 9279.85], 'litro' => [1687.24, 1855.97]], // LA 111
    '1001' => ['caja' => [44995.45, 50394.91], 'bidon' => [11248.86, 12598.73], 'litro' => [2249.77, 2519.75]], // SEIQ Jabón líquido ropa
    '262215' => ['caja' => [62958.44, 69254.28], 'bidon' => [15739.61, 17313.57], 'litro' => [3147.92, 3462.71]], // Pisos plastificados
    '861009 A' => ['caja' => [39634.28, 44390.39], 'bidon' => [9908.57, 11097.60], 'litro' => [1981.71, 2219.52]], // Runax D 45
    '861000' => ['caja' => [69917.91, 79007.24], 'bidon' => [17479.48, 19751.81], 'litro' => [3495.90, 3950.36]], // Kiefer

    // Sobres
    '391739' => ['caja' => [93514.98, 104736.78], 'sobre' => [2337.87, 2618.42]], // Desengrasante
    '391746' => ['caja' => [47079.08, 52728.57], 'sobre' => [1176.98, 1318.21]], // Lavavajillas cítrico
    '391747' => ['caja' => [60049.85, 67255.83], 'sobre' => [1501.25, 1681.40]], // Jabón líquido ropa

    // Aerosoles
    'ECOAAI01' => ['unitario' => [3330.95, 3497.50]], // Abrillantador acero inox
    'ECOSP02' => ['unitario' => [3866.52, 4059.84]], // Secuestrante de polvo
    'ECOLTA06' => ['unitario' => [2965.20, 3113.46]], // Limpia tapizados y alfombras
    'ECOALM07' => ['unitario' => [3076.23, 3230.04]], // Aceite lubricante multiuso
    'ECOA09' => ['unitario' => [2553.73, 2655.88]], // Apresto

    // Masivo
    'ECOLL18' => ['unitario' => [1520.77, 1703.26], 'caja' => [18249.24, 20439.14]], // Lavavajillas limón
    'ECOLAV19' => ['unitario' => [1520.77, 1703.26], 'caja' => [18249.24, 20439.14]], // Lavavajillas aloe vera

    // Alimenticia
    'ALFRC2' => ['caja' => [62700.29, 72105.33], 'bidon' => [15675.07, 18026.33], 'litro' => [3135.01, 3605.27]], // Force
    'ALPWR4' => ['caja' => [85951.64, 98844.39], 'bidon' => [21487.91, 24711.10], 'litro' => [4297.58, 4942.22]], // Power Plus
];

$fieldToColumn = [
    'unitario' => 'precio_lista_unitario',
    'caja' => 'precio_lista_caja',
    'bidon' => 'precio_lista_bidon',
    'litro' => 'precio_lista_litro',
    'sobre' => 'precio_lista_sobre',
];

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/seiq_price_update_2026_07.log';

$errors = [];
$rows = [];

foreach ($changes as $code => $fields) {
    $product = $pdo->prepare('SELECT id, code, name, precio_lista_unitario, precio_lista_caja, precio_lista_bidon, precio_lista_litro, precio_lista_sobre FROM products WHERE code = ?');
    $product->execute([$code]);
    $p = $product->fetch();
    if (!$p) {
        $errors[] = "Código {$code} no existe en products.";
        continue;
    }
    foreach ($fields as $field => [$expectedOld, $new]) {
        $col = $fieldToColumn[$field];
        $current = $p[$col] === null ? null : (float) $p[$col];
        if ($current === null || abs($current - $expectedOld) > 0.01) {
            $errors[] = "Código {$code} ({$p['name']}) campo {$col}: esperaba {$expectedOld} pero DB tiene "
                . ($current === null ? 'NULL' : $current) . " — no se toca, revisar a mano.";
            continue;
        }
        $rows[] = [
            'id' => $p['id'],
            'code' => $code,
            'name' => $p['name'],
            'column' => $col,
            'old' => $current,
            'new' => $new,
        ];
    }
}

if ($errors !== []) {
    echo "=== ADVERTENCIAS (no se tocan estos campos) ===" . PHP_EOL;
    foreach ($errors as $e) {
        echo "  - {$e}" . PHP_EOL;
    }
    echo PHP_EOL;
}

echo '=== ' . ($apply ? 'APLICANDO' : 'DRY-RUN') . ' — ' . count($rows) . ' campos a actualizar ===' . PHP_EOL;
foreach ($rows as $r) {
    $pct = $r['old'] > 0 ? (($r['new'] / $r['old'] - 1) * 100) : 0;
    printf(
        "  id=%d %-10s %-45s %-22s %10.2f -> %10.2f (%+.2f%%)%s",
        $r['id'],
        $r['code'],
        mb_substr($r['name'], 0, 45),
        $r['column'],
        $r['old'],
        $r['new'],
        $pct,
        PHP_EOL
    );
}

if (!$apply) {
    echo PHP_EOL . 'Dry-run: no se modificó nada. Correr con --apply para confirmar.' . PHP_EOL;
    exit(0);
}

$pdo->beginTransaction();
try {
    $logLines = [];
    $logLines[] = '=== Aumento SEIQ julio 2026 — ' . date('Y-m-d H:i:s') . ' ===';
    foreach ($rows as $r) {
        $stmt = $pdo->prepare("UPDATE products SET {$r['column']} = ? WHERE id = ?");
        $stmt->execute([$r['new'], $r['id']]);
        $logLines[] = sprintf(
            'id=%d code=%s name=%s column=%s old=%.2f new=%.2f',
            $r['id'],
            $r['code'],
            $r['name'],
            $r['column'],
            $r['old'],
            $r['new']
        );
    }
    $pdo->commit();
    file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo PHP_EOL . 'Listo. ' . count($rows) . ' campos actualizados. Log: ' . $logFile . PHP_EOL;
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo PHP_EOL . 'ERROR, se hizo rollback: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
