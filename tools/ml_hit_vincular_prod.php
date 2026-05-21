<?php

declare(strict_types=1);

$base = 'http://sistema.limpiaoeste.com.ar/public';
$user = $argv[1] ?? 'admin';
$pass = $argv[2] ?? 'limpiaOeste2026';

$cookieFile = sys_get_temp_dir() . '/ml_prod_cookies.txt';
@unlink($cookieFile);

function http(string $url, string $cookieFile, array $opts = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 180,
    ]);
    if (($opts['post'] ?? '') !== '') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post']);
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP {$code} {$url}\n";

    return $body;
}

$loginHtml = http("{$base}/login", $cookieFile);
if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginHtml, $m)) {
    fwrite(STDERR, "No se encontró CSRF en login\n");
    exit(1);
}
$csrf = $m[1];

http("{$base}/login", $cookieFile, [
    'post' => http_build_query([
        'username' => $user,
        'password' => $pass,
        '_csrf' => $csrf,
    ]),
]);

$start = microtime(true);
$page = http("{$base}/mercadolibre/vincular-existentes", $cookieFile);
$elapsed = round(microtime(true) - $start, 1);
echo "Tiempo: {$elapsed}s | bytes: " . strlen($page) . "\n";

if (str_contains($page, 'Vincular publicaciones ML')) {
    echo "OK: página vincular cargada\n";
} elseif (str_contains($page, 'Ingresar')) {
    echo "FAIL: redirigió a login\n";
    exit(1);
}

if (preg_match('/Sin vincular[^0-9]*(\d+)/u', $page, $m)) {
    echo "Sin vincular: {$m[1]}\n";
}
