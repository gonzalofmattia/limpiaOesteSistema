<?php
/** Elimina ml_diagnose_once.php del servidor FTP de producción. */
$root = dirname(__DIR__);
require_once $root . '/app/Helpers/Env.php';
\App\Helpers\Env::load($root . '/.env');

$host = trim(\App\Helpers\Env::get('FTP_HOST', ''));
$user = trim(\App\Helpers\Env::get('FTP_USER', ''));
$pass = \App\Helpers\Env::get('FTP_PASS', '');
$path = rtrim(trim(\App\Helpers\Env::get('FTP_PATH', '/public_html/sistema')), '/');
$mode = strtolower(trim(\App\Helpers\Env::get('FTP_MODE', 'ssl')));

$remote = $path . '/public/ml_diagnose_once.php';
$conn = ($mode === 'ssl' || $mode === 'ftps') ? @ftp_ssl_connect($host, 21, 30) : @ftp_connect($host, 21, 30);
if ($conn && @ftp_login($conn, $user, $pass)) {
    ftp_pasv($conn, true);
    @ftp_delete($conn, $remote);
    ftp_close($conn);
    echo "Eliminado {$remote}\n";
}
