<?php

declare(strict_types=1);

$url = $argv[1] ?? 'https://seiqgroupsa.com.ar/seiq/desengrasantes/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "URL: {$url}\n";
echo "HTTP: {$code}\n";
if ($err !== '') {
    echo "curl error: {$err}\n";
}
echo "\n--- Primeros 3000 caracteres del body ---\n\n";
echo substr((string) $body, 0, 3000);
