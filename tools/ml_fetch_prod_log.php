<?php

declare(strict_types=1);

$envPath = dirname(__DIR__) . '/.env';
$vars = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $vars[trim($k)] = trim($v, " \t\"'");
}

$host = $vars['FTP_HOST'] ?? '';
$user = $vars['FTP_USER'] ?? '';
$pass = $vars['FTP_PASS'] ?? '';
$remotePath = rtrim($vars['FTP_PATH'] ?? '/public_html/sistema', '/');
$remoteLog = $remotePath . '/storage/logs/ml_errors.log';
$localLog = sys_get_temp_dir() . '/ml_errors_prod.log';

$ftp = ftp_ssl_connect($host, 21, 45) ?: ftp_connect($host, 21, 45);
if ($ftp === false) {
    fwrite(STDERR, "No se pudo conectar FTP\n");
    exit(1);
}
if (!@ftp_login($ftp, $user, $pass)) {
    fwrite(STDERR, "Login FTP falló\n");
    exit(1);
}
ftp_pasv($ftp, true);

if (!@ftp_get($ftp, $localLog, $remoteLog, FTP_BINARY)) {
    fwrite(STDERR, "No se pudo descargar {$remoteLog}\n");
    exit(1);
}
ftp_close($ftp);

echo "Descargado: {$localLog} (" . filesize($localLog) . " bytes)\n\n";

$patterns = [
    'linkExisting',
    'diagnoseLinkExisting',
    'fetchSellerItemsForLinking',
    'searchUserItemIds',
    'fetchItemsDetailsForLinking',
];

$lines = file($localLog, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    exit(1);
}

$matched = [];
foreach ($lines as $line) {
    foreach ($patterns as $p) {
        if (stripos($line, $p) !== false) {
            $matched[] = $line;
            break;
        }
    }
}

$tail = array_slice($matched, -40);
echo "=== Últimas " . count($tail) . " líneas relevantes (de " . count($matched) . " totales) ===\n";
foreach ($tail as $line) {
    echo $line . "\n";
}
