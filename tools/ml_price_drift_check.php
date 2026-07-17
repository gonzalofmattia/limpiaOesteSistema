<?php

declare(strict_types=1);

/**
 * Chequeo de deriva de precio ML tras un aumento de costo de proveedor: compara el precio
 * publicado que el sistema tiene guardado para cada ml_listing (último valor sincronizado)
 * contra el precio que el sistema calcularía HOY con PricingEngine + MercadoLibreService,
 * sin llamar a la API de ML (solo lectura local). No aplica nada.
 *
 * Uso: php tools/ml_price_drift_check.php
 */

if (file_exists(__DIR__ . '/../.production')) {
    die('Este script no debe ejecutarse en producción.' . PHP_EOL);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/functions.php';

use App\Helpers\MercadoLibreService;
use App\Models\Database;

$db = Database::getInstance();

$listings = $db->fetchAll(
    "SELECT ml.id AS listing_id, ml.ml_item_id, ml.status, ml.price AS ml_price_db, ml.ml_markup,
            ml.last_synced_at, p.id AS product_id, p.code, p.name
     FROM ml_listings ml
     JOIN products p ON p.id = ml.product_id
     WHERE ml.status IN ('active','paused') AND ml.ml_item_id IS NOT NULL AND TRIM(ml.ml_item_id) <> ''
     ORDER BY p.code"
);

echo '=== Chequeo de deriva de precio ML — ' . count($listings) . ' listings activos/pausados vinculados ===' . PHP_EOL . PHP_EOL;

$drift = [];
$ok = 0;
foreach ($listings as $l) {
    $markup = ($l['ml_markup'] !== null && $l['ml_markup'] !== '') ? (float) $l['ml_markup'] : null;
    $sysPrice = MercadoLibreService::calculateMlPrice((int) $l['product_id'], $markup);
    $mlPriceDb = (float) $l['ml_price_db'];

    if ($sysPrice <= 0) {
        continue;
    }

    $diff = $sysPrice - $mlPriceDb;
    $pct = $mlPriceDb > 0 ? ($diff / $mlPriceDb) * 100 : 0.0;

    if (abs($diff) > 1.0) {
        $drift[] = [
            'listing_id' => $l['listing_id'],
            'ml_item_id' => $l['ml_item_id'],
            'code' => $l['code'],
            'name' => $l['name'],
            'status' => $l['status'],
            'ml_price_db' => $mlPriceDb,
            'sys_price' => $sysPrice,
            'diff' => $diff,
            'pct' => $pct,
            'last_synced_at' => $l['last_synced_at'],
        ];
    } else {
        $ok++;
    }
}

echo "En línea (sistema == último precio sincronizado): {$ok}" . PHP_EOL;
echo 'Con deriva de precio: ' . count($drift) . PHP_EOL . PHP_EOL;

usort($drift, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);

foreach ($drift as $d) {
    printf(
        "  listing_id=%d %s | %-45s | ML(guardado)=%10.2f  sistema(hoy)=%10.2f  diff=%+9.2f (%+.1f%%)  status=%s  last_synced=%s\n",
        $d['listing_id'],
        $d['ml_item_id'],
        mb_substr($d['code'] . ' ' . $d['name'], 0, 45),
        $d['ml_price_db'],
        $d['sys_price'],
        $d['diff'],
        $d['pct'],
        $d['status'],
        $d['last_synced_at'] ?? '(nunca)'
    );
}
