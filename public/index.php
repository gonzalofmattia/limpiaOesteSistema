<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');

if (\App\Helpers\Env::get('APP_DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Prefijo URI del front controller (p. ej. /limpiaOesteSistema/public o vacío en producción con docroot en public/)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$baseDir = dirname($scriptName);
if ($baseDir === '/' || $baseDir === '.' || $baseDir === '\\') {
    define('BASE_URL', '');
} else {
    define('BASE_URL', rtrim($baseDir, '/'));
}
define('BASE_URL_PATH', BASE_URL);

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once APP_PATH . '/Core/App.php';

\App\Core\App::run();
