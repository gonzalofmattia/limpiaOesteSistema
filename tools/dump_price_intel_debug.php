<?php

declare(strict_types=1);

$envPath = dirname(__DIR__) . '/.env';
$vars = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $vars[trim($k)] = trim($v, " \t\"'");
}
$remotePath = rtrim($vars['FTP_PATH'] ?? '/public_html/sistema', '/');
$ftp = @ftp_ssl_connect($vars['FTP_HOST'], 21, 45) ?: @ftp_connect($vars['FTP_HOST'], 21, 45);
ftp_login($ftp, $vars['FTP_USER'], $vars['FTP_PASS']);
ftp_pasv($ftp, true);

$localLog = sys_get_temp_dir() . '/ml_errors_full.log';
ftp_get($ftp, $localLog, $remotePath . '/storage/logs/ml_errors.log', FTP_BINARY);

foreach ([17, 20, 23] as $lid) {
    $tmp = sys_get_temp_dir() . "/cache_{$lid}.json";
    if (@ftp_get($ftp, $tmp, $remotePath . "/storage/cache/ml_price_intel_{$lid}.json", FTP_BINARY)) {
        echo "=== Cache listing {$lid} ===\n";
        echo file_get_contents($tmp) . "\n\n";
    }
}
ftp_close($ftp);

$lines = file($localLog, FILE_IGNORE_NEW_LINES) ?: [];
$matched = array_values(array_filter($lines, fn($l) => stripos($l, 'searchCompetitors') !== false));
echo "=== searchCompetitors en log: " . count($matched) . " lineas ===\n";
foreach (array_slice($matched, -20) as $l) echo $l . "\n";

// Probar API ML localmente
foreach (['apresto seiq', 'bentol 10 seiq'] as $q) {
    $url = 'https://api.mercadolibre.com/sites/MLA/search?' . http_build_query(['q' => $q, 'limit' => 10]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    $total = is_array($data) ? count($data['results'] ?? []) : 0;
    echo "\n=== ML API test q=\"{$q}\" HTTP {$code} results={$total} ===\n";
    if ($total > 0 && is_array($data['results'][0] ?? null)) {
        $r = $data['results'][0];
        echo "  1er: " . ($r['title'] ?? '') . " | price=" . ($r['price'] ?? '') . " | mode=" . ($r['buying_mode'] ?? '') . " | seller=" . ($r['seller']['id'] ?? '') . "\n";
    }
}
