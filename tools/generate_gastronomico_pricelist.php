<?php

declare(strict_types=1);

/**
 * Genera la "Lista Gastronómica" (restaurantes/parrillas) reusando el mismo motor de
 * precios y la misma plantilla PDF que el módulo de listas de precios (PriceListController).
 * No modifica PricingEngine ni el controlador: arma las mismas estructuras de datos
 * (lines/pdf_sections) que collectGenerateInput()/buildPricelistPdfSections() para que el
 * resultado sea indistinguible de una lista generada desde /listas/generar con el preset
 * "Generar Lista Gastronómica", pero agrupando en las secciones puntuales pedidas por rubro
 * (incluyendo "Baños y Sanitarios", que no existe como categoría propia en la DB).
 *
 * Uso:
 *   php tools/generate_gastronomico_pricelist.php
 */

if (file_exists(__DIR__ . '/../.production')) {
    die('Este script no debe ejecutarse en producción.' . PHP_EOL);
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}
require_once APP_PATH . '/Helpers/functions.php';

use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

$logDir = STORAGE_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/pricelist_gastronomico.log';
$log = static function (string $message) use ($logFile): void {
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
};

/**
 * Secciones pedidas por rubro para el cliente gastronómico (restaurante/parrilla).
 * Cada sección puede tener uno o más bloques con subtítulo (papelería se separa en 3).
 * Los códigos vienen de PriceListController::GASTRONOMICO_PRODUCT_CODES.
 */
$sections = [
    [
        'title' => 'Cuidado de la Cocina',
        'blocks' => [
            ['subtitle' => null, 'codes' => [
                '861017', '2026F', '861018', '861020', '250060', '861008 B', '261011', '861024',
                '398120', '250065', '262205', '260065', '28014',
            ]],
        ],
    ],
    [
        'title' => 'Limpiadores Desengrasantes',
        'blocks' => [
            ['subtitle' => null, 'codes' => ['382018', '861013', '861015', '250067', '250066', '2048']],
        ],
    ],
    [
        'title' => 'Limpiadores Desinfectantes',
        'blocks' => [
            ['subtitle' => null, 'codes' => ['260073', '861016', '861019', 'ECHL1', '464656']],
        ],
    ],
    [
        'title' => 'Cuidado de Manos',
        'blocks' => [
            ['subtitle' => null, 'codes' => ['861023', '291920', '861023 A', '861022', '100000', '260000']],
        ],
    ],
    [
        'title' => 'Limpieza y Tratamiento de Pisos',
        'blocks' => [
            ['subtitle' => null, 'codes' => ['861014', '861145', '861017 A', '861105']],
        ],
    ],
    [
        'title' => 'Baños y Sanitarios',
        'blocks' => [
            ['subtitle' => null, 'codes' => ['861012', '260072', '260070']],
        ],
    ],
    [
        'title' => 'Papelería Higienik',
        'blocks' => [
            ['subtitle' => 'Papel Higiénico', 'codes' => [
                'H8300P', 'H8300PG', 'H8200P', 'H8200PG', 'H8300A', 'H8300AG', 'H8200A', 'H8200AG',
                'H8300A ECO', 'H8300AG ECO', 'H3080A', 'HB100', 'H3080P',
            ]],
            ['subtitle' => 'Rollos de Cocina', 'codes' => [
                'R4x150P', 'R4x200P', 'R2x250P', 'R4x150A', 'R4x200A', 'R2x250A', 'R4x150B', 'R4x200B', 'R2x250B',
            ]],
            ['subtitle' => 'Toallas Intercaladas', 'codes' => ['IB2500', 'IB2000', 'E1500', 'IN2500', 'IN2000', 'EB1500']],
        ],
    ],
];

/**
 * Nombres de producto que mencionan la marca del proveedor (Seiq) y no deben salir así
 * en un documento para el cliente. Solo afecta el texto mostrado en este PDF — no se
 * toca products.name en la base.
 */
$displayNameOverrides = [
    '260070' => 'DESTAPACAÑERÍAS',
];

$allCodes = [];
foreach ($sections as $sec) {
    foreach ($sec['blocks'] as $block) {
        foreach ($block['codes'] as $code) {
            $allCodes[] = $code;
        }
    }
}

$db = Database::getInstance();

$segmentRow = $db->fetch(
    'SELECT default_markup FROM client_segment_config WHERE segment_key = ? AND is_active = 1',
    ['gastronomico']
);
$configuredMarkup = $segmentRow && $segmentRow['default_markup'] !== null ? (float) $segmentRow['default_markup'] : null;
$markup = 70.0; // Markup del segmento gastronómico pedido explícitamente para esta lista.
if ($configuredMarkup !== null && abs($configuredMarkup - $markup) > 0.001) {
    $log("ADVERTENCIA: client_segment_config.gastronomico.default_markup = {$configuredMarkup}% en la DB, "
        . "pero esta lista se generó forzando {$markup}% (pedido explícito). Revisar si el segmento en DB está desactualizado.");
}

$in = implode(',', array_fill(0, count($allCodes), '?'));
$rows = $db->fetchAll(
    "SELECT p.*,
            COALESCE(pc.slug, c.slug) AS category_slug,
            c.name AS category_name,
            c.presentation_info AS category_presentation_info,
            pc.name AS parent_category_name,
            c.default_discount,
            c.default_markup AS category_default_markup,
            c.markup_override AS category_markup_override,
            c.markup_locked AS category_markup_locked,
            c.markup_minorista AS category_markup_minorista,
            pc.default_discount AS parent_discount,
            pc.default_markup AS parent_default_markup,
            pc.markup_override AS parent_markup_override,
            pc.markup_locked AS parent_markup_locked,
            pc.markup_minorista AS parent_markup_minorista,
            COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
            s.name AS supplier_name,
            s.slug AS supplier_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN categories pc ON c.parent_id = pc.id
     LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
     WHERE p.is_active = 1 AND p.code IN ({$in})",
    $allCodes
);
$byCode = [];
foreach ($rows as $row) {
    $byCode[(string) $row['code']] = $row;
}

$missing = array_diff($allCodes, array_keys($byCode));
if ($missing !== []) {
    $log('ERROR: códigos no encontrados o inactivos: ' . implode(', ', $missing));
    fwrite(STDERR, 'Códigos faltantes: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$priceField = 'precio_lista_caja';
$includeIva = false;
$pdfSections = [];
$allLines = [];
$productCount = 0;

foreach ($sections as $sec) {
    $blocks = [];
    foreach ($sec['blocks'] as $block) {
        $lines = [];
        foreach ($block['codes'] as $code) {
            $p = $byCode[$code];
            if (isset($displayNameOverrides[$code])) {
                $p['name'] = $displayNameOverrides[$code];
            }
            $field = $priceField;
            if (empty($p[$field])) {
                $field = PricingEngine::getPrimaryPriceField((string) $p['category_slug']);
            }
            if (empty($p[$field])) {
                $log("AVISO: producto {$code} sin precio en campo {$field}, se omite de la lista.");
                continue;
            }
            $calc = PricingEngine::calculate($p, $field, $markup, $includeIva);
            $pp = QuoteLinePricing::priceListUnitAndPack($p, (string) $p['category_slug'], $markup, $includeIva, $calc);
            $line = [
                'product_id' => (int) $p['id'],
                'product' => $p,
                'field' => $field,
                'calc' => $calc,
                'individual_venta' => $pp['individual_venta'],
                'pack_venta' => $pp['pack_display'],
                'pack_venta_net' => $pp['pack_net'],
                'pack_venta_iva' => $pp['pack_con_iva'],
            ];
            $lines[] = $line;
            $allLines[] = $line;
            $productCount++;
        }
        $blocks[] = ['subtitle' => $block['subtitle'], 'lines' => $lines];
    }
    $pdfSections[] = ['parent' => $sec['title'], 'blocks' => $blocks];
}

$listName = 'Lista de Precios — Gastronómico (Restaurantes y Parrillas)';
$db->getPdo()->beginTransaction();
try {
    $listId = $db->insert('price_lists', [
        'name' => $listName,
        'description' => 'Segmento gastronómico — rubros: cocina, desengrasantes, desinfectantes, '
            . 'cuidado de manos, pisos de uso diario, baños y papelería. Excluye lavandería, '
            . 'automotor, aromatizantes de ropa y mantenimiento industrial pesado.',
        'custom_markup' => $markup,
        'include_iva' => $includeIva ? 1 : 0,
        'category_filter' => json_encode(['segment' => 'gastronomico', 'products' => array_values($allCodes)], JSON_THROW_ON_ERROR),
        'status' => 'active',
        'generated_at' => date('Y-m-d H:i:s'),
    ]);

    foreach ($allLines as $line) {
        $calc = $line['calc'];
        $db->insert('price_list_items', [
            'price_list_id' => $listId,
            'product_id' => $line['product_id'],
            'precio_base_usado' => $calc['precio_lista_seiq'],
            'costo_limpia_oeste' => $calc['costo'],
            'precio_venta' => $line['pack_venta_net'],
            'precio_venta_iva' => null,
            'markup_applied' => $calc['markup_percent'],
            'discount_applied' => $calc['discount_percent'],
            'price_field_used' => $line['field'],
        ]);
    }
    $db->getPdo()->commit();
} catch (\Throwable $e) {
    $db->getPdo()->rollBack();
    $log('ERROR al persistir price_lists/price_list_items: ' . $e->getMessage());
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

ob_start();
extract([
    'listName' => $listName,
    'generatedAt' => date('d/m/Y H:i'),
    'includeIva' => $includeIva,
    'pdfSections' => $pdfSections,
]);
require APP_PATH . '/Views/pdf/pricelist.php';
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($listName));
$file = 'lista-' . $listId . '-' . time() . '-' . substr((string) $slug, 0, 40) . '.pdf';
$dir = STORAGE_PATH . '/pdfs';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$path = $dir . '/' . $file;
file_put_contents($path, $dompdf->output());

$db->query('UPDATE price_lists SET pdf_path = ? WHERE id = ?', [$file, $listId]);

$log("Lista generada: id={$listId} productos={$productCount} markup={$markup}% pdf={$path}");

echo "Lista #{$listId} generada con {$productCount} productos.\n";
echo "PDF: {$path}\n";
