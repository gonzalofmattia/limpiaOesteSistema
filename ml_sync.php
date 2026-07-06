<?php

declare(strict_types=1);

/**
 * Motor de sincronización bidireccional ML <-> sistema (título, precio, stock, descripción,
 * categoría, imágenes). Por defecto corre en dry-run: no toca la base, solo reporta qué haría.
 *
 * Uso:
 *   php ml_sync.php                              Dry-run sobre todos los listings activos/pausados
 *   php ml_sync.php --listing-id=12,34            Dry-run solo sobre esos listings
 *   php ml_sync.php --apply                       Aplica PULL/PUSH sin conflicto; conflictos quedan
 *                                                  pendientes en /mercadolibre/sync/conflictos
 */

if (file_exists(__DIR__ . '/.production')) {
    die('Este script no debe ejecutarse en producción sin supervisión — correr manualmente y revisar el resumen.');
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

use App\Helpers\MlSyncEngine;

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
$listingIds = [];
if (isset($args['listing-id'])) {
    foreach (explode(',', (string) $args['listing-id']) as $id) {
        $id = (int) trim($id);
        if ($id > 0) {
            $listingIds[] = $id;
        }
    }
}

echo 'Modo: ' . ($apply ? 'APPLY (aplica cambios sin conflicto)' : 'DRY-RUN (no toca nada)') . "\n";
echo ($listingIds === [] ? 'Todos los listings activos/pausados vinculados' : 'Listings: ' . implode(',', $listingIds)) . "\n\n";

$result = MlSyncEngine::run($listingIds, $apply);

foreach ($result['details'] as $item) {
    if (!empty($item['blocked'])) {
        echo "  listing_id={$item['listing_id']} BLOQUEADO: {$item['block_reason']}\n";
        continue;
    }
    $fieldsSummary = [];
    foreach ($item['fields'] as $field => $action) {
        if ($action !== MlSyncEngine::NO_CHANGE) {
            $fieldsSummary[] = "{$field}={$action}";
        }
    }
    if ($fieldsSummary !== []) {
        echo "  listing_id={$item['listing_id']}: " . implode(', ', $fieldsSummary) . "\n";
    }
}

foreach ($result['errors'] as $error) {
    echo "  ERROR listing_id={$error['listing_id']}: {$error['error']}\n";
}

echo "\n=== RESUMEN ===\n";
echo 'Pull desde ML: ' . $result['pulled'] . "\n";
echo 'Push a ML: ' . $result['pushed'] . "\n";
echo 'Conflictos: ' . $result['conflicts'] . "\n";
echo 'Sin cambios: ' . $result['no_change'] . "\n";
echo 'Bloqueados: ' . $result['blocked'] . "\n";
echo 'Errores: ' . count($result['errors']) . "\n";
echo "\nLog completo: " . STORAGE_PATH . "/logs/ml_sync.log\n";
if ($result['conflicts'] > 0) {
    echo "Resolver conflictos manualmente en /mercadolibre/sync/conflictos\n";
}
