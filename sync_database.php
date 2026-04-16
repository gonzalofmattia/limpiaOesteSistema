#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Helpers\DatabaseSynchronizer;

require __DIR__ . '/vendor/autoload.php';

/**
 * Uso:
 * php sync_database.php --direction=pull --remote-host=... --remote-db=... --remote-user=... --remote-pass=...
 * php sync_database.php --direction=push --remote-host=... --remote-db=... --remote-user=... --remote-pass=...
 */
$opts = getopt('', [
    'direction:',
    'remote-host:',
    'remote-db:',
    'remote-user:',
    'remote-pass::',
    'remote-charset::',
]);

$direction = (string) ($opts['direction'] ?? 'pull');
if (!in_array($direction, ['pull', 'push'], true)) {
    fwrite(STDERR, "Error: --direction debe ser pull o push.\n");
    exit(1);
}

$remoteHost = (string) ($opts['remote-host'] ?? '');
$remoteDb = (string) ($opts['remote-db'] ?? '');
$remoteUser = (string) ($opts['remote-user'] ?? '');
$remotePass = (string) ($opts['remote-pass'] ?? '');
$remoteCharset = (string) ($opts['remote-charset'] ?? 'utf8mb4');

if ($remoteHost === '' || $remoteDb === '' || $remoteUser === '') {
    fwrite(STDERR, "Error: faltan parámetros remotos. Requeridos: --remote-host --remote-db --remote-user\n");
    exit(1);
}

$configPath = __DIR__ . '/app/config/database.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Error: no se encontró app/config/database.php\n");
    exit(1);
}
/** @var array{host:string,database:string,username:string,password:string,charset:string} $local */
$local = require $configPath;
$remote = [
    'host' => $remoteHost,
    'database' => $remoteDb,
    'username' => $remoteUser,
    'password' => $remotePass,
    'charset' => $remoteCharset,
];

$source = $direction === 'pull' ? $remote : $local;
$target = $direction === 'pull' ? $local : $remote;

echo "Iniciando sincronización completa...\n";
echo "Origen: {$source['host']}/{$source['database']}\n";
echo "Destino: {$target['host']}/{$target['database']}\n";

try {
    $res = DatabaseSynchronizer::sync($source, $target, static function (string $msg): void {
        echo ' - ' . $msg . "\n";
    });
    echo "\nSincronización OK\n";
    echo "Tablas: {$res['tables']}\n";
    echo "Filas: {$res['rows']}\n";
    echo "Inicio: {$res['started_at']}\n";
    echo "Fin: {$res['finished_at']}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}

