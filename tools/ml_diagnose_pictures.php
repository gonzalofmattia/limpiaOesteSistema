<?php

declare(strict_types=1);

/**
 * Diagnóstico fotos ML — ejecutar: php tools/ml_diagnose_pictures.php [product_id] [ml_item_id]
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/MercadoLibreService.php';
require_once APP_PATH . '/Helpers/MercadoLibreTokenManager.php';

$productId = isset($argv[1]) ? (int) $argv[1] : 51;
$mlItemId = isset($argv[2]) ? trim($argv[2]) : 'MLA1795371467';

echo "=== 1. ml_errors.log (últimas 50 líneas) ===\n";
$logFile = STORAGE_PATH . '/logs/ml_errors.log';
if (is_file($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if ($lines !== false) {
        $last = array_slice($lines, -50);
        echo implode("\n", $last) . "\n";
    }
} else {
    echo "(archivo no existe)\n";
}

echo "\n=== 2. product_images product_id={$productId} ===\n";
$db = \App\Models\Database::getInstance();
$rows = $db->fetchAll(
    'SELECT id, product_id, filename, is_cover, sort_order, mime_type FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC',
    [$productId]
);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== 2b. curl HEAD a URLs ===\n";
$base = 'https://limpiaoeste.com.ar/sistema/public';
$mlUrl = $base . '/assets/img/ML.jpg';
echo "HEAD {$mlUrl}\n";
echo headCheck($mlUrl) . "\n";

foreach ($rows as $row) {
    $fn = basename((string) ($row['filename'] ?? ''));
    if ($fn === '') {
        continue;
    }
    $url = $base . '/producto-imagen/' . $productId . '/' . $fn;
    echo "HEAD {$url}\n";
    echo headCheck($url) . "\n";
}

echo "\n=== 3. Payload pictures (buildPictures / syncItem) ===\n";
$pictures = \App\Helpers\MercadoLibreService::buildPictures($productId, 'ml_diagnose');
$syncPayload = [
    'price' => 0,
    'available_quantity' => 1,
];
if ($pictures !== []) {
    $syncPayload['pictures'] = $pictures;
}
echo json_encode($syncPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($pictures === []) {
    echo "\n(!) buildPictures devolvió array vacío — no se puede hacer PUT con fotos.\n";
    exit(1);
}

echo "\n=== 4. PUT manual a ML items/{$mlItemId} (solo pictures + price/qty mínimos) ===\n";
try {
    $token = \App\Helpers\MercadoLibreTokenManager::getValidAccessToken();
} catch (\Throwable $e) {
    echo "ERROR token: " . $e->getMessage() . "\n";
    exit(1);
}

$putPayload = ['pictures' => $pictures];
$json = json_encode($putPayload, JSON_UNESCAPED_UNICODE);
echo "Request body:\n{$json}\n\n";

$ch = curl_init('https://api.mercadolibre.com/items/' . rawurlencode($mlItemId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_TIMEOUT => 45,
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP {$httpCode}\n";
if ($err !== '') {
    echo "curl error: {$err}\n";
}
echo "Response:\n";
$decoded = json_decode((string) $response, true);
echo json_encode($decoded ?? $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

function headCheck(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'LimpiaOeste-ML-Diagnose/1.0',
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') {
        return "FAIL curl: {$err}";
    }

    return "HTTP {$code}" . ($type !== '' ? " Content-Type: {$type}" : '');
}
