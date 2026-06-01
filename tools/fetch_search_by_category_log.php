<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$vars = [];
foreach (file($baseDir . '/.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
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

$ftp = @ftp_ssl_connect($host, 21, 45) ?: @ftp_connect($host, 21, 45);
if ($ftp === false || !@ftp_login($ftp, $user, $pass)) {
    fwrite(STDERR, "FTP falló\n");
    exit(1);
}
ftp_pasv($ftp, true);

$localLog = sys_get_temp_dir() . '/ml_errors_prod.log';
if (!@ftp_get($ftp, $localLog, $remotePath . '/storage/logs/ml_errors.log', FTP_BINARY)) {
    fwrite(STDERR, "No se pudo descargar ml_errors.log\n");
    exit(1);
}
ftp_close($ftp);

$lines = file($localLog, FILE_IGNORE_NEW_LINES) ?: [];
$matched = array_values(array_filter(
    $lines,
    static fn (string $l): bool => stripos($l, 'searchByCategory') !== false
));

echo 'Total searchByCategory: ' . count($matched) . PHP_EOL . PHP_EOL;
foreach ($matched as $line) {
    echo $line . PHP_EOL;
}
