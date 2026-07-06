<?php

declare(strict_types=1);

/**
 * Auditoría de reconciliación ML <-> Sistema.
 * Compara, para cada listing vinculado, lo que ML tiene realmente publicado
 * (título, descripción, precio, stock, categoría, cantidad de fotos, estado)
 * contra lo que el sistema tiene guardado. Detecta también:
 *   - publicaciones ML sin contraparte en el sistema
 *   - listings del sistema cuyo ml_item_id ya no aparece en ML
 * Solo lectura: no modifica productos, listings ni nada en ML.
 *
 * Uso CLI: php audit_ml_reconciliacion.php
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

function auditReconLog(string $line): void
{
    $dir = STORAGE_PATH . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $stamp = date('Y-m-d H:i:s');
    file_put_contents($dir . '/ml_reconciliation_audit.log', "[{$stamp}] {$line}\n", FILE_APPEND | LOCK_EX);
}

function auditReconQuantity(array $listing): int
{
    if (isset($listing['available_quantity_override']) && $listing['available_quantity_override'] !== null) {
        return max(1, (int) $listing['available_quantity_override']);
    }

    return max(1, (int) (setting('ml_default_quantity', '12') ?? '12'));
}

$db = Database::getInstance();

$listings = $db->fetchAll(
    "SELECT ml.*, p.name AS product_name, p.description AS product_description,
            p.full_description, p.short_description, p.dilution, p.usage_cost,
            p.presentation, p.content,
            co.name AS combo_name, co.description AS combo_description
     FROM ml_listings ml
     LEFT JOIN products p ON p.id = ml.product_id
     LEFT JOIN combos co ON co.id = ml.combo_id
     WHERE ml.ml_item_id IS NOT NULL AND ml.ml_item_id <> ''
     ORDER BY ml.id ASC"
);

if ($listings === []) {
    echo "No hay listings publicados (con ml_item_id) para reconciliar.\n";
    auditReconLog('Sin listings publicados.');
    exit(0);
}

$itemIds = array_values(array_unique(array_map(
    static fn (array $row): string => trim((string) $row['ml_item_id']),
    $listings
)));

echo 'Trayendo detalle crudo de ' . count($itemIds) . " ítems ML (multiget)...\n";
$rawItems = MercadoLibreService::fetchRawItemsByIds($itemIds);

echo "Trayendo descripciones publicadas en ML (1 llamada por ítem, con pausa)...\n";
$descriptions = [];
foreach ($itemIds as $i => $itemId) {
    if (!isset($rawItems[$itemId])) {
        continue; // no existe en ML, no tiene sentido pedir descripción
    }
    $descriptions[$itemId] = MercadoLibreService::fetchItemDescriptionText($itemId);
    if (($i + 1) % 20 === 0) {
        echo '  ... ' . ($i + 1) . '/' . count($itemIds) . "\n";
    }
    usleep(150000);
}

$diffs = [];
$notFoundInMl = [];
$linkedIds = [];

foreach ($listings as $row) {
    $mlItemId = trim((string) $row['ml_item_id']);
    $linkedIds[$mlItemId] = true;
    $listingId = (int) $row['id'];
    $itemName = $row['product_name'] ?? $row['combo_name'] ?? '(sin nombre)';

    if (!isset($rawItems[$mlItemId])) {
        $notFoundInMl[] = [
            'listing_id' => $listingId,
            'ml_item_id' => $mlItemId,
            'item_name' => $itemName,
            'system_status' => $row['status'],
        ];
        continue;
    }

    $ml = $rawItems[$mlItemId];
    $fields = [];

    // --- Título (contenido: probablemente editado a mano en ML) ---
    $systemTitle = trim((string) ($row['title'] ?? ''));
    $mlTitle = trim((string) ($ml['title'] ?? ''));
    if ($systemTitle !== $mlTitle) {
        $fields['title'] = [
            'direction' => 'contenido_traer_de_ml',
            'sistema' => $systemTitle,
            'ml' => $mlTitle,
        ];
    }

    // --- Descripción (contenido: probablemente editado a mano en ML) ---
    $productLike = [
        'full_description' => $row['full_description'],
        'description' => $row['product_description'],
        'short_description' => $row['short_description'],
        'dilution' => $row['dilution'],
        'usage_cost' => $row['usage_cost'],
        'presentation' => $row['presentation'],
        'content' => $row['content'],
    ];
    $mlDescription = trim($descriptions[$mlItemId] ?? '');
    if ($row['product_id'] !== null) {
        $expectedDescription = trim(MercadoLibreService::buildDescription($productLike));
    } else {
        $expectedDescription = trim((string) ($row['combo_description'] ?? ''));
    }
    if ($expectedDescription !== '' && $mlDescription !== '' && $expectedDescription !== $mlDescription) {
        $fields['description'] = [
            'direction' => 'contenido_traer_de_ml',
            'sistema_esperado' => $expectedDescription,
            'ml' => $mlDescription,
        ];
    }

    // --- Precio (el sistema manda: PricingEngine / stock_effective) ---
    $expectedPrice = (float) ($row['price'] ?? 0);
    if ($expectedPrice <= 0 && $row['product_id'] !== null) {
        $markup = isset($row['ml_markup']) && $row['ml_markup'] !== null && $row['ml_markup'] !== ''
            ? (float) $row['ml_markup']
            : null;
        $expectedPrice = MercadoLibreService::calculateMlPrice((int) $row['product_id'], $markup);
    }
    $mlPrice = round((float) ($ml['price'] ?? 0), 2);
    if ($expectedPrice > 0 && abs($expectedPrice - $mlPrice) > 0.01) {
        $fields['price'] = [
            'direction' => 'sistema_manda_sync_a_ml',
            'sistema_esperado' => round($expectedPrice, 2),
            'ml' => $mlPrice,
        ];
    }

    // --- Stock / cantidad disponible (el sistema manda) ---
    $expectedQty = auditReconQuantity($row);
    $mlQty = max(0, (int) ($ml['available_quantity'] ?? 0));
    if ($expectedQty !== $mlQty) {
        $fields['available_quantity'] = [
            'direction' => 'sistema_manda_sync_a_ml',
            'sistema_esperado' => $expectedQty,
            'ml' => $mlQty,
        ];
    }

    // --- Categoría ---
    $systemCategory = trim((string) ($row['ml_category_id'] ?? ''));
    $mlCategory = trim((string) ($ml['category_id'] ?? ''));
    if ($systemCategory !== '' && $systemCategory !== $mlCategory) {
        $fields['category_id'] = [
            'direction' => 'revisar_manualmente',
            'sistema' => $systemCategory,
            'ml' => $mlCategory,
        ];
    }

    // --- Fotos (contenido: solo cantidad, no compara URL por URL) ---
    $systemPicturesCount = (int) ($row['ml_pictures_count'] ?? 0);
    $mlPictures = is_array($ml['pictures'] ?? null) ? $ml['pictures'] : [];
    $mlPicturesCount = count($mlPictures);
    if ($systemPicturesCount !== $mlPicturesCount) {
        $fields['pictures_count'] = [
            'direction' => 'contenido_revisar',
            'sistema' => $systemPicturesCount,
            'ml' => $mlPicturesCount,
            'ml_urls' => array_map(
                static fn (array $p): string => (string) ($p['secure_url'] ?? $p['url'] ?? ''),
                $mlPictures
            ),
        ];
    }

    // --- Estado (informativo) ---
    $systemStatus = trim((string) ($row['status'] ?? ''));
    $mlStatus = trim((string) ($ml['status'] ?? ''));
    $mappedMlStatus = match ($mlStatus) {
        'active' => 'active',
        'paused' => 'paused',
        'closed' => 'closed',
        default => $mlStatus,
    };
    if ($systemStatus !== $mappedMlStatus) {
        $fields['status'] = [
            'direction' => 'informativo',
            'sistema' => $systemStatus,
            'ml' => $mappedMlStatus,
        ];
    }

    if ($fields !== []) {
        $diffs[] = [
            'listing_id' => $listingId,
            'ml_item_id' => $mlItemId,
            'item_name' => $itemName,
            'product_id' => $row['product_id'] !== null ? (int) $row['product_id'] : null,
            'combo_id' => $row['combo_id'] !== null ? (int) $row['combo_id'] : null,
            'fields' => $fields,
        ];
    }
}

echo "Buscando publicaciones ML sin contraparte en el sistema (fetchSellerItemsForLinking)...\n";
$sellerResult = MercadoLibreService::fetchSellerItemsForLinking();
$onlyInMl = [];
if ($sellerResult['success']) {
    foreach ($sellerResult['items'] as $item) {
        $id = trim((string) ($item['ml_item_id'] ?? ''));
        if ($id !== '' && !isset($linkedIds[$id])) {
            $onlyInMl[] = [
                'ml_item_id' => $id,
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? null,
                'status' => $item['status'] ?? '',
                'ml_permalink' => $item['ml_permalink'] ?? '',
            ];
        }
    }
} else {
    echo 'No se pudo traer el listado completo del vendedor: ' . $sellerResult['error'] . "\n";
    auditReconLog('fetchSellerItemsForLinking falló: ' . $sellerResult['error']);
}

echo "\n=== RESUMEN ===\n";
echo 'Listings publicados en el sistema: ' . count($listings) . "\n";
echo 'Con diferencias detectadas: ' . count($diffs) . "\n";
echo 'No encontrados en ML (posiblemente borrados manualmente): ' . count($notFoundInMl) . "\n";
echo 'Publicaciones en ML sin contraparte en el sistema: ' . count($onlyInMl) . "\n";

auditReconLog(
    'Resumen: ' . count($listings) . ' listings, ' . count($diffs) . ' con diffs, '
    . count($notFoundInMl) . ' no encontrados en ML, ' . count($onlyInMl) . ' solo en ML.'
);

$report = [
    'generated_at' => date('c'),
    'listings_with_differences' => $diffs,
    'listings_missing_in_ml' => $notFoundInMl,
    'ml_items_missing_in_system' => $onlyInMl,
    'notes' => [
        'direction=contenido_traer_de_ml: Gonzalo probablemente editó esto directo en ML; considerar traer la versión de ML al sistema.',
        'direction=sistema_manda_sync_a_ml: el sistema (PricingEngine / stock_effective) es la fuente de verdad; correr syncItem para empujar el valor correcto a ML.',
        'direction=contenido_revisar / revisar_manualmente / informativo: no hay una dirección obvia, revisar caso por caso.',
        'pictures_count solo compara cantidad, no compara URL por URL contra product_images.',
        'Recordar: nunca mandar "pictures" en syncItem() (PUT) — solo en publishItem(). Este script no llama a ninguno de los dos, es de solo lectura.',
    ],
];

$reportFile = STORAGE_PATH . '/logs/ml_reconciliation_report.json';
file_put_contents($reportFile, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
echo "\nReporte JSON completo: {$reportFile}\n";
echo 'Log: ' . STORAGE_PATH . "/logs/ml_reconciliation_audit.log\n";

foreach ($diffs as $diff) {
    $fieldNames = implode(', ', array_keys($diff['fields']));
    auditReconLog("listing_id={$diff['listing_id']} ml_item_id={$diff['ml_item_id']} \"{$diff['item_name']}\" campos_con_diff={$fieldNames}");
}
foreach ($notFoundInMl as $missing) {
    auditReconLog("NO_ENCONTRADO_EN_ML listing_id={$missing['listing_id']} ml_item_id={$missing['ml_item_id']} \"{$missing['item_name']}\"");
}
foreach ($onlyInMl as $extra) {
    auditReconLog("SOLO_EN_ML ml_item_id={$extra['ml_item_id']} \"{$extra['title']}\" status={$extra['status']}");
}
