<?php

declare(strict_types=1);

/** Descarga storage/logs/ml_errors.log del servidor FTP de producción. */
$root = dirname(__DIR__);
require_once $root . '/app/Helpers/Env.php';
\App\Helpers\Env::load($root . '/.env');

$host = trim(\App\Helpers\Env::get('FTP_HOST', ''));
$user = trim(\App\Helpers\Env::get('FTP_USER', ''));
$pass = \App\Helpers\Env::get('FTP_PASS', '');
$path = rtrim(trim(\App\Helpers\Env::get('FTP_PATH', '/public_html/sistema')), '/');
$mode = strtolower(trim(\App\Helpers\Env::get('FTP_MODE', 'ssl')));

$remote = $path . '/storage/logs/ml_errors.log';
$local = $root . '/storage/logs/ml_errors_prod.log';

$conn = ($mode === 'ssl' || $mode === 'ftps')
    ? @ftp_ssl_connect($host, 21, 30)
    : @ftp_connect($host, 21, 30);

if ($conn === false) {
    fwrite(STDERR, "No se pudo conectar FTP\n");
    exit(1);
}
if (!@ftp_login($conn, $user, $pass)) {
    fwrite(STDERR, "Login FTP falló\n");
    exit(1);
}
ftp_pasv($conn, true);

if (!@ftp_get($conn, $local, $remote, FTP_ASCII)) {
    fwrite(STDERR, "No se pudo descargar {$remote}\n");
    exit(1);
}
ftp_close($conn);

echo "Descargado a {$local}\n";
$lines = file($local, FILE_IGNORE_NEW_LINES);
if ($lines !== false) {
    echo implode("\n", array_slice($lines, -50)) . "\n";
}
