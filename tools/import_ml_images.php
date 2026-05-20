<?php

declare(strict_types=1);

/**
 * Importación masiva de fotos desde MercadoLibre (API pública).
 * Uso: php tools/import_ml_images.php
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/ImageUploader.php';
require_once APP_PATH . '/Helpers/MlImageImporter.php';

$importer = new \App\Helpers\MlImageImporter();
$products = $importer->getActiveProductsWithoutPhotos();
$total = count($products);

echo "Productos activos sin foto: {$total}\n";
if ($total === 0) {
    echo "Nada que importar.\n";
    exit(0);
}

$imported = 0;
$notFound = 0;
$errors = 0;

foreach ($products as $i => $product) {
    $num = $i + 1;
    $pid = (int) ($product['id'] ?? 0);
    $name = (string) ($product['name'] ?? '');
    echo "[{$num}/{$total}] #{$pid} {$name} ... ";

    $result = $importer->importProduct($product);
    $status = $result['status'] ?? 'error';

    if ($status === 'ok') {
        $imported++;
        echo "OK ({$result['image_url']})\n";
    } elseif ($status === 'no_encontrado') {
        $notFound++;
        echo "sin match\n";
    } else {
        $errors++;
        echo "ERROR: {$result['message']}\n";
    }
}

echo "\n=== Resumen ===\n";
echo "Importadas: {$imported}\n";
echo "Sin match en ML: {$notFound}\n";
if ($errors > 0) {
    echo "Errores: {$errors}\n";
}
echo "Log: storage/logs/ml_image_import.log\n";

exit($errors > 0 ? 1 : 0);
