#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Exportar base de datos local a SQL
 *
 * Uso: php db_export.php
 * Genera: database/export_YYYY-MM-DD_HHmmss.sql
 */

/**
 * @return array<string, string>
 */
function dbExportLoadEnv(string $path): array
{
    $vars = [];
    if (!is_file($path)) {
        return $vars;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $vars;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^"(.*)"$/s', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
            $value = $m[1];
        }
        $vars[$key] = $value;
    }

    return $vars;
}

function dbExportColor(string $msg, string $color): string
{
    $codes = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'reset' => "\033[0m",
    ];

    return ($codes[$color] ?? '') . $msg . $codes['reset'];
}

function green(string $msg): string
{
    return dbExportColor($msg, 'green');
}

function red(string $msg): string
{
    return dbExportColor($msg, 'red');
}

function yellow(string $msg): string
{
    return dbExportColor($msg, 'yellow');
}

/** Ruta a mysqldump.exe en Windows / Laragon si no está en el PATH. */
function resolveMysqldumpExecutable(): ?string
{
    if (stripos(PHP_OS, 'WIN') === false) {
        return null;
    }
    $bases = [];
    $laragon = getenv('LARAGON_ROOT');
    if (is_string($laragon) && $laragon !== '') {
        $bases[] = $laragon . '\\bin\\mysql';
    }
    $bases[] = 'C:\\laragon\\bin\\mysql';

    foreach ($bases as $mysqlBase) {
        if (!is_dir($mysqlBase)) {
            continue;
        }
        $pattern = $mysqlBase . DIRECTORY_SEPARATOR . 'mysql-*' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe';
        $found = glob($pattern) ?: [];
        foreach ($found as $exe) {
            if (is_file($exe)) {
                return $exe;
            }
        }
    }

    return null;
}

function printMysqlAuthPluginHelp(string $dbUser): void
{
    echo yellow("\n--- MySQL 8.4+ / plugin de autenticación ---\n");
    echo "Tu servidor ya no carga el plugin «mysql_native_password» y el usuario\n";
    echo "«{$dbUser}» sigue usándolo. Hay que pasar el usuario a caching_sha2_password.\n\n";
    echo "1) Laragon → botón «Base de datos» → se abre HeidiSQL / consola, o ejecutá:\n";
    echo "   laragon\\bin\\mysql\\mysql-VERSION\\bin\\mysql.exe -u root -p\n\n";
    echo "2) En la consola MySQL (ajustá usuario, host y contraseña):\n\n";
    echo "   ALTER USER '{$dbUser}'@'localhost' IDENTIFIED WITH caching_sha2_password BY 'TU_CLAVE';\n";
    echo "   FLUSH PRIVILEGES;\n\n";
    echo "   Si root no tiene clave: BY ''\n\n";
    echo "3) Volvé a ejecutar: php db_export.php\n";
}

function exportWithPHP(string $host, string $dbname, string $user, string $pass, string $outputFile): void
{
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "-- LIMPIA OESTE - Database Export\n";
    $sql .= '-- Fecha: ' . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Base: {$dbname}\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($tables)) {
        $tables = [];
    }

    foreach ($tables as $table) {
        $table = (string) $table;
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($create) || empty($create['Create Table'])) {
            continue;
        }
        $sql .= 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . "`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $values = array_map(static function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, $row);
            $cols = implode('`, `', array_keys($row));
            $vals = implode(', ', $values);
            $sql .= "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
        }
        if (count($rows) > 0) {
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($outputFile, $sql);

    $size = round(filesize($outputFile) / 1024, 1);
    echo green("Exportación PHP exitosa!\n");
    echo "  Archivo: {$outputFile}\n";
    echo "  Tamaño: {$size} KB\n";
    echo '  Tablas: ' . count($tables) . "\n";
}

$env = dbExportLoadEnv(__DIR__ . '/.env');

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? 'limpia_oeste_abm';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

$timestamp = date('Y-m-d_His');
$databaseDir = __DIR__ . '/database';
if (!is_dir($databaseDir) && !mkdir($databaseDir, 0755, true)) {
    fwrite(STDERR, red("Error: no se pudo crear la carpeta database/\n"));
    exit(1);
}

$outputFile = $databaseDir . "/export_{$timestamp}.sql";

$mysqldumpBin = 'mysqldump';
if (stripos(PHP_OS, 'WIN') === 0) {
    $resolved = resolveMysqldumpExecutable();
    if ($resolved !== null) {
        $mysqldumpBin = '"' . $resolved . '"';
    }
}

$passOpt = $dbPass !== '' ? '-p' . escapeshellarg($dbPass) : '';
$cmd = $mysqldumpBin . ' --no-tablespaces --routines --triggers -h ' . escapeshellarg($dbHost)
    . ' -u ' . escapeshellarg($dbUser)
    . ($passOpt !== '' ? ' ' . $passOpt : '')
    . ' ' . escapeshellarg($dbName)
    . ' > ' . escapeshellarg($outputFile);

exec($cmd . (stripos(PHP_OS, 'WIN') === 0 ? ' 2>&1' : ''), $output, $returnCode);

if ($returnCode === 0 && is_file($outputFile) && filesize($outputFile) > 0) {
    $size = round(filesize($outputFile) / 1024, 1);
    echo green("Exportación exitosa!\n");
    echo "  Archivo: database/export_{$timestamp}.sql\n";
    echo "  Tamaño: {$size} KB\n";
    echo "\nPara importar en producción:\n";
    echo yellow("  1. Subí el archivo .sql al hosting\n");
    echo yellow("  2. Importalo desde phpMyAdmin\n");
    echo yellow("  3. O ejecutá: php db_import.php database/export_{$timestamp}.sql\n");
    exit(0);
}

echo red("Error al exportar con mysqldump. ¿Está instalado y en el PATH?\n");
if ($output !== []) {
    echo yellow(implode("\n", $output) . "\n");
}
echo yellow("Intentando exportar con PHP (PDO)...\n");
try {
    exportWithPHP($dbHost, $dbName, $dbUser, $dbPass, $outputFile);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    fwrite(STDERR, red('Error: ' . $msg . "\n"));
    if (str_contains($msg, '1524') || str_contains($msg, 'mysql_native_password')) {
        printMysqlAuthPluginHelp($dbUser);
    }
    exit(1);
}
