<?php

declare(strict_types=1);

/**
 * Diagnóstico vincular-existentes — ejecutar: php tools/ml_diagnose_link_existing.php
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/SettingsCache.php';
require_once APP_PATH . '/Helpers/MercadoLibreTokenManager.php';
require_once APP_PATH . '/Helpers/MercadoLibreService.php';

echo "=== Diagnóstico ML vincular-existentes ===\n\n";

$db = \App\Models\Database::getInstance();
$mlUserIdSetting = trim((string) ($db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'ml_user_id'") ?? ''));
$hasToken = trim((string) ($db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'ml_access_token'") ?? '')) !== '';

echo "ml_user_id (settings): " . ($mlUserIdSetting !== '' ? $mlUserIdSetting : '(vacío)') . "\n";
echo "OAuth conectado: " . (\App\Helpers\MercadoLibreTokenManager::isConnected() ? 'sí' : 'no') . "\n";
echo "access_token presente: " . ($hasToken ? 'sí' : 'no') . "\n\n";

if (!$hasToken) {
    echo "ERROR: No hay token ML en la BD local. El diagnóstico necesita tokens válidos.\n";
    exit(1);
}

$report = \App\Helpers\MercadoLibreService::diagnoseSellerItemsForLinking();

echo "--- Resumen items/search status=active ---\n";
print_r(summarize($report['items_search_active'] ?? null));

echo "\n--- Resumen items/search status=inactive ---\n";
print_r(summarize($report['items_search_inactive'] ?? null));

echo "\n--- Resumen items/search status=paused ---\n";
print_r(summarize($report['items_search_paused'] ?? null));

if ($report['site_search_fallback'] !== null) {
    echo "\n--- Fallback sites/search ---\n";
    print_r(summarize($report['site_search_fallback']));
}

echo "\n--- fetchSellerItemsForLinking() ---\n";
$fetch = \App\Helpers\MercadoLibreService::fetchSellerItemsForLinking();
echo 'success: ' . ($fetch['success'] ? 'true' : 'false') . "\n";
echo 'items count: ' . count($fetch['items']) . "\n";
if (($fetch['error'] ?? '') !== '') {
    echo 'error: ' . $fetch['error'] . "\n";
}

echo "\n--- GET /items?ids= (muestra 2 IDs) ---\n";
$sampleIds = array_slice($report['items_search_active']['parsed_ids'] ?? ['MLA1796307269'], 0, 2);
$itemsProbe = probeItemsMultiGet($sampleIds);
print_r($itemsProbe);

echo "\n--- ml_listings ya vinculados (con ml_item_id) ---\n";
$linkedDb = $db->fetchAll("SELECT ml_item_id, product_id, title FROM ml_listings WHERE ml_item_id IS NOT NULL AND TRIM(ml_item_id) <> ''");
echo 'count=' . count($linkedDb) . "\n";
if ($linkedDb !== []) {
    echo json_encode(array_slice($linkedDb, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Últimas 30 líneas ml_errors.log ===\n";
$logFile = STORAGE_PATH . '/logs/ml_errors.log';
if (is_file($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if ($lines !== false) {
        echo implode("\n", array_slice($lines, -30)) . "\n";
    }
} else {
    echo "(no existe)\n";
}

/** @param array<string, mixed>|null $block */
function summarize(?array $block): array
{
    if ($block === null) {
        return ['note' => 'sin datos'];
    }

    return [
        'http_code' => $block['http_code'] ?? null,
        'success' => $block['success'] ?? null,
        'error' => $block['error'] ?? '',
        'top_level_keys' => $block['top_level_keys'] ?? [],
        'results_field_type' => $block['results_field_type'] ?? null,
        'results_count' => $block['results_count'] ?? 0,
        'parsed_ids_count' => $block['parsed_ids_count'] ?? count($block['parsed_ids'] ?? []),
        'parsed_ids_sample' => array_slice($block['parsed_ids'] ?? [], 0, 5),
        'paging' => $block['paging'] ?? null,
        'raw_json_length' => strlen((string) ($block['raw_json'] ?? '')),
    ];
}

/** @param list<string> $ids */
function probeItemsMultiGet(array $ids): array
{
    $token = \App\Helpers\MercadoLibreTokenManager::getValidAccessToken();
    $idsParam = implode(',', array_map('rawurlencode', $ids));
    $url = 'https://api.mercadolibre.com/items?ids=' . $idsParam;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 45,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $body, true);
    $first = is_array($decoded) ? ($decoded[0] ?? null) : null;

    return [
        'http_code' => $code,
        'top_level_type' => gettype($decoded),
        'count' => is_array($decoded) ? count($decoded) : 0,
        'first_keys' => is_array($first) ? array_keys($first) : [],
        'first_preview' => is_array($first) ? json_encode($first, JSON_UNESCAPED_UNICODE) : null,
        'raw_length' => strlen((string) $body),
    ];
}
