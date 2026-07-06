<?php

declare(strict_types=1);

/**
 * Scaffolding manual de publicaciones ML activas sin fila en el sistema (huérfanos).
 * Nunca crea productos automáticamente en una corrida desatendida: hay que correr este
 * script a mano, por ítem, eligiendo la categoría interna real (no hay forma confiable
 * de inferirla automáticamente para un huérfano).
 *
 * Uso:
 *   php scaffold_from_ml.php --list                                   Lista huérfanos (solo lectura)
 *   php scaffold_from_ml.php --item-id=MLA123456789 --category-id=8   Dry-run de un huérfano puntual
 *   php scaffold_from_ml.php --item-id=MLA123456789 --category-id=8 --apply   Crea el producto + listing
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
use App\Helpers\MlImageImporter;
use App\Models\Database;

function scaffoldLog(string $line): void
{
    $dir = STORAGE_PATH . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/ml_scaffold.log', '[' . date('Y-m-d H:i:s') . "] {$line}\n", FILE_APPEND | LOCK_EX);
}

function scaffoldMapStatus(string $mlStatus): string
{
    return match ($mlStatus) {
        'active' => 'active',
        'paused' => 'paused',
        default => 'closed',
    };
}

/** @return list<array{ml_item_id:string,title:string,price:mixed,status:string,ml_permalink:string}> */
function scaffoldFindOrphans(): array
{
    $db = Database::getInstance();
    $linkedIds = [];
    foreach ($db->fetchAll("SELECT ml_item_id FROM ml_listings WHERE ml_item_id IS NOT NULL AND TRIM(ml_item_id) <> ''") as $row) {
        $linkedIds[trim((string) $row['ml_item_id'])] = true;
    }

    $fetch = MercadoLibreService::fetchSellerItemsForLinking();
    if (!$fetch['success']) {
        echo 'No se pudo traer el listado del vendedor: ' . $fetch['error'] . "\n";

        return [];
    }

    $orphans = [];
    foreach ($fetch['items'] as $item) {
        $id = trim((string) ($item['ml_item_id'] ?? ''));
        if ($id !== '' && !isset($linkedIds[$id])) {
            $orphans[] = $item;
        }
    }

    return $orphans;
}

$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$key, $value] = explode('=', substr($arg, 2), 2);
        $args[$key] = $value;
    } elseif (str_starts_with($arg, '--')) {
        $args[substr($arg, 2)] = true;
    }
}

$apply = isset($args['apply']);
$itemId = isset($args['item-id']) ? trim((string) $args['item-id']) : '';
$categoryId = isset($args['category-id']) ? (int) $args['category-id'] : 0;

if (isset($args['list']) || $itemId === '') {
    $orphans = scaffoldFindOrphans();
    echo count($orphans) . " publicaciones ML activas sin fila en el sistema:\n";
    foreach ($orphans as $o) {
        echo '  ' . $o['ml_item_id'] . ' | ' . $o['title'] . ' | $' . $o['price'] . ' | status=' . $o['status'] . "\n";
    }
    echo "\nPara scaffoldear una: php scaffold_from_ml.php --item-id=MLAxxxx --category-id=N [--apply]\n";
    exit(0);
}

$orphans = scaffoldFindOrphans();
$target = null;
foreach ($orphans as $o) {
    if ($o['ml_item_id'] === $itemId) {
        $target = $o;
        break;
    }
}
if ($target === null) {
    echo "'{$itemId}' no está en la lista de huérfanos (ya tiene fila en el sistema, no existe en ML, o no es del vendedor conectado).\n";
    exit(1);
}

$state = MercadoLibreService::fetchCurrentState($itemId);
if ($state === null) {
    echo "No se pudo leer el ítem {$itemId} desde ML.\n";
    exit(1);
}

echo "=== {$itemId} ===\n";
echo 'Título ML: ' . $state['title'] . "\n";
echo 'Precio ML: $' . $state['price'] . "\n";
echo 'Stock ML: ' . $state['available_quantity'] . "\n";
echo 'Categoría ML: ' . $state['category_id'] . "\n";
echo 'Fotos: ' . count($state['pictures']) . "\n";

if ($categoryId <= 0) {
    echo "\nFalta --category-id=N (id de categoría INTERNA del sistema, no de ML). No hay forma\n";
    echo "confiable de inferir la categoría de un huérfano automáticamente — elegí una a mano.\n";
    exit($apply ? 1 : 0);
}

$db = Database::getInstance();
$category = $db->fetch('SELECT id, name FROM categories WHERE id = ?', [$categoryId]);
if ($category === null) {
    echo "La categoría interna --category-id={$categoryId} no existe.\n";
    exit(1);
}
echo 'Categoría interna elegida: ' . $category['name'] . " (id={$categoryId})\n";

if (!$apply) {
    echo "\nDry-run: no se creó nada. Agregá --apply para crear el producto + listing.\n";
    exit(0);
}

$pdo = $db->getPdo();
$pdo->beginTransaction();
try {
    $normalizedDescription = trim(preg_replace('/\s+/', ' ', $state['description_text']) ?? $state['description_text']);

    $productId = $db->insert('products', [
        'category_id' => $categoryId,
        'code' => $itemId,
        'name' => $state['title'],
        'full_description' => $state['description_text'] !== '' ? $state['description_text'] : null,
        'units_per_box' => 1,
        'stock_units' => 0,
        'stock_committed_units' => 0,
        'is_active' => 0,
        'is_published' => 0,
        'notes' => 'Creado por scaffold_from_ml.php desde ' . $itemId . ' — revisar precios de lista, stock y categoría real.',
    ]);

    $listingId = $db->insert('ml_listings', [
        'product_id' => $productId,
        'ml_item_id' => $itemId,
        'ml_category_id' => $state['category_id'],
        'title' => mb_substr($state['title'], 0, 60),
        'status' => scaffoldMapStatus($state['status']),
        'price' => round((float) $state['price'], 2),
        'available_quantity_override' => max(0, (int) $state['available_quantity']),
        'ml_permalink' => $state['permalink'],
        'last_synced_at' => date('Y-m-d H:i:s'),
    ]);

    $importer = new MlImageImporter($db);
    $imagesResult = $importer->syncProductImagesFromMl((int) $productId, $state['pictures']);

    // Snapshot inicial: refleja el estado recién scaffoldeado para que la primera corrida
    // de ml_sync.php no marque un falso conflicto (ver Fase 7).
    $db->insert('ml_sync_snapshots', [
        'ml_listing_id' => $listingId,
        'title' => trim(mb_substr($state['title'], 0, 60)),
        'price' => round((float) $state['price'], 2),
        'available_quantity' => max(0, (int) $state['available_quantity']),
        'description_hash' => md5($normalizedDescription),
        'category_id' => $state['category_id'],
        'images_id_list' => json_encode(array_map(static fn (array $p): string => $p['id'], $state['pictures']), JSON_UNESCAPED_UNICODE),
        'last_synced_at' => date('Y-m-d H:i:s'),
    ]);

    $pdo->commit();

    echo "\nCreado: product_id={$productId} ml_listing_id={$listingId} imágenes(added={$imagesResult['added']})\n";
    echo "Producto creado INACTIVO y sin publicar en catálogo (is_active=0, is_published=0) — revisar\n";
    echo "precios de lista / stock real / categoría antes de activarlo.\n";
    scaffoldLog("OK item_id={$itemId} product_id={$productId} ml_listing_id={$listingId} category_id={$categoryId} imagenes_added={$imagesResult['added']}");
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "\nError, no se creó nada: " . $e->getMessage() . "\n";
    scaffoldLog("ERROR item_id={$itemId}: " . $e->getMessage());
    exit(1);
}
