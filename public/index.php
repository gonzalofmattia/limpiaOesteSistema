<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');

// Base URL pública (p. ej. /limpiaoestesistema/public) para subcarpetas bajo localhost
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$baseDir = dirname($scriptName);
if ($baseDir === '/' || $baseDir === '.' || $baseDir === '\\') {
    define('BASE_URL', '');
} else {
    define('BASE_URL', rtrim($baseDir, '/'));
}

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once APP_PATH . '/Core/App.php';

\App\Core\App::run();
