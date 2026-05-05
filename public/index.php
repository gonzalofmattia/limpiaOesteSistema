<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');

/**
 * Logger de emergencia para entornos sin acceso a logs del hosting.
 */
function appEmergencyLog(string $message): void
{
    $logDir = STORAGE_PATH . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @error_log($line, 3, $logDir . '/php_fatal.log');
}

if (\App\Helpers\Env::get('APP_DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

set_exception_handler(static function (\Throwable $e): void {
    appEmergencyLog(
        'UNCAUGHT EXCEPTION: ' . $e->getMessage()
        . ' | file=' . $e->getFile()
        . ' | line=' . $e->getLine()
    );
    http_response_code(500);
    if (\App\Helpers\Env::get('APP_DEBUG', 'false') === 'true') {
        echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return;
    }
    echo 'Error interno del servidor.';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    appEmergencyLog("PHP ERROR [{$severity}]: {$message} | file={$file} | line={$line}");
    return false;
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
        appEmergencyLog(
            'FATAL SHUTDOWN: ' . ($err['message'] ?? 'unknown')
            . ' | file=' . ($err['file'] ?? 'unknown')
            . ' | line=' . (int) ($err['line'] ?? 0)
        );
    }
});

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
