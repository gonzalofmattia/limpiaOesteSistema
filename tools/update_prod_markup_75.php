<?php

declare(strict_types=1);

/** Actualiza ml_default_markup en producción vía FTP + aviso para ejecutar SQL si hay panel DB. */
$root = dirname(__DIR__);
require_once $root . '/app/Helpers/Env.php';
\App\Helpers\Env::load($root . '/.env');

$sql = "UPDATE settings SET setting_value = '75' WHERE setting_key = 'ml_default_markup';";
$sqlFile = $root . '/database/migrations/2026_05_19_ml_markup_75.sql';
file_put_contents($sqlFile, $sql . "\n");

$host = trim(\App\Helpers\Env::get('FTP_HOST', ''));
$user = trim(\App\Helpers\Env::get('FTP_USER', ''));
$pass = \App\Helpers\Env::get('FTP_PASS', '');
$path = rtrim(trim(\App\Helpers\Env::get('FTP_PATH', '/public_html/sistema')), '/');
$mode = strtolower(trim(\App\Helpers\Env::get('FTP_MODE', 'ssl')));

$conn = ($mode === 'ssl' || $mode === 'ftps') ? @ftp_ssl_connect($host, 21, 30) : @ftp_connect($host, 21, 30);
if ($conn && @ftp_login($conn, $user, $pass)) {
    ftp_pasv($conn, true);
    $remote = $path . '/database/migrations/2026_05_19_ml_markup_75.sql';
    @ftp_put($conn, $remote, $sqlFile, FTP_ASCII);
    ftp_close($conn);
    echo "Migración subida a {$remote}\n";
}

echo "Ejecutá en phpMyAdmin producción:\n{$sql}\n";
