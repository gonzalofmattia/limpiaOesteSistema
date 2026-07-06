<?php

declare(strict_types=1);

/**
 * Auditoría de categorías ML asignadas al catálogo.
 * Recorre ml_listings con ml_category_id asignado, consulta cada categoría única
 * contra la API de ML (GET /categories/{id}) y reporta cuáles NO son hoja
 * (children_categories no vacío). Solo lectura: no modifica productos ni listings.
 *
 * Uso CLI: php audit_ml_categorias.php
 */

if (file_exists(__DIR__ . '/.production')) {
    die('Este script no debe ejecutarse en producción.');
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

require_once BASE_PATH . '/vendor/autoload.php';
require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';

use App\Helpers\MercadoLibreService;
use App\Models\Database;

function auditMlCatLog(string $line): void
{
    $dir = STORAGE_PATH . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $stamp = date('Y-m-d H:i:s');
    file_put_contents($dir . '/ml_category_leaf_audit.log', "[{$stamp}] {$line}\n", FILE_APPEND | LOCK_EX);
}

$db = Database::getInstance();

$listings = $db->fetchAll(
    "SELECT ml.id AS listing_id, ml.product_id, ml.combo_id, ml.ml_item_id, ml.ml_category_id, ml.status,
            COALESCE(p.name, co.name) AS item_name, p.code
     FROM ml_listings ml
     LEFT JOIN products p ON p.id = ml.product_id
     LEFT JOIN combos co ON co.id = ml.combo_id
     WHERE ml.ml_category_id IS NOT NULL AND ml.ml_category_id <> ''
     ORDER BY ml.id ASC"
);

if ($listings === []) {
    echo "No hay listings con ml_category_id asignado.\n";
    auditMlCatLog('Sin listings con categoría asignada.');
    exit(0);
}

$uniqueCategoryIds = array_values(array_unique(array_map(
    static fn (array $row): string => trim((string) $row['ml_category_id']),
    $listings
)));

echo 'Consultando ' . count($uniqueCategoryIds) . " categorías únicas contra la API de ML...\n";

$categoryRecords = [];
foreach ($uniqueCategoryIds as $categoryId) {
    $categoryRecords[$categoryId] = MercadoLibreService::fetchCategoryRecord($categoryId);
}

$nonLeaf = [];
$leafCount = 0;
foreach ($listings as $row) {
    $categoryId = trim((string) $row['ml_category_id']);
    $record = $categoryRecords[$categoryId] ?? null;
    if ($record === null) {
        continue;
    }

    if ($record['is_leaf']) {
        $leafCount++;
        continue;
    }

    $nonLeaf[] = [
        'listing_id' => (int) $row['listing_id'],
        'product_id' => $row['product_id'] !== null ? (int) $row['product_id'] : null,
        'combo_id' => $row['combo_id'] !== null ? (int) $row['combo_id'] : null,
        'code' => $row['code'],
        'item_name' => $row['item_name'],
        'ml_item_id' => $row['ml_item_id'],
        'status' => $row['status'],
        'category_id' => $categoryId,
        'category_name' => $record['name'],
        'category_path' => $record['path_string'],
        'children_count' => $record['children_count'],
    ];
}

echo "\n=== RESUMEN ===\n";
echo 'Listings con categoría asignada: ' . count($listings) . "\n";
echo "Categorías únicas consultadas: " . count($uniqueCategoryIds) . "\n";
echo "Listings en categoría hoja (OK): {$leafCount}\n";
echo 'Listings en categoría NO hoja (requieren revisión): ' . count($nonLeaf) . "\n";

auditMlCatLog(
    'Resumen: ' . count($listings) . ' listings, ' . count($uniqueCategoryIds) . ' categorías únicas, '
    . $leafCount . ' en hoja, ' . count($nonLeaf) . ' en categoría NO hoja.'
);

if ($nonLeaf !== []) {
    echo "\n=== LISTINGS EN CATEGORÍA NO HOJA ===\n";
    foreach ($nonLeaf as $item) {
        $label = $item['product_id'] !== null
            ? "producto_id={$item['product_id']} code=" . ($item['code'] ?? '-')
            : "combo_id={$item['combo_id']}";
        $line = "listing_id={$item['listing_id']} {$label} nombre=\"{$item['item_name']}\" "
            . "ml_item_id=" . ($item['ml_item_id'] ?: '(sin publicar)') . " status={$item['status']} "
            . "categoria={$item['category_id']} ({$item['category_name']}) "
            . "hijos={$item['children_count']} path=\"{$item['category_path']}\"";
        echo $line . "\n";
        auditMlCatLog($line);
    }

    $reportFile = STORAGE_PATH . '/logs/ml_category_leaf_audit_report.json';
    file_put_contents(
        $reportFile,
        json_encode(['generated_at' => date('c'), 'non_leaf_listings' => $nonLeaf], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    echo "\nReporte JSON: {$reportFile}\n";
    echo "Log: " . STORAGE_PATH . "/logs/ml_category_leaf_audit.log\n";
    echo "\nNota: este script no modifica nada. Para cada caso, usar domain_discovery para encontrar la hoja correcta.\n";
} else {
    echo "\nTodos los listings están en categorías hoja. Nada para revisar.\n";
}
