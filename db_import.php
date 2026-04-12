#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Importar base de datos desde archivo SQL (ejecutar en producción o local)
 *
 * Uso: php db_import.php database/export_2026-04-12_120000.sql
 */

/**
 * @return array<string, string>
 */
function dbImportLoadEnv(string $path): array
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

function dbImportColor(string $msg, string $color): string
{
    $codes = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'reset' => "\033[0m",
    ];

    return ($codes[$color] ?? '') . $msg . $codes['reset'];
}

function green(string $msg): string
{
    return dbImportColor($msg, 'green');
}

function red(string $msg): string
{
    return dbImportColor($msg, 'red');
}

$sqlFile = $argv[1] ?? null;
if ($sqlFile === null || $sqlFile === '') {
    echo "Uso: php db_import.php <archivo.sql>\n";
    exit(1);
}

if (!is_file($sqlFile)) {
    fwrite(STDERR, red("No existe el archivo: {$sqlFile}\n"));
    exit(1);
}

$env = dbImportLoadEnv(__DIR__ . '/.env');

$host = $env['DB_HOST'] ?? 'localhost';
$name = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';

if ($name === '' || $user === '') {
    fwrite(STDERR, red("Error: Configurá DB_NAME y DB_USER en .env\n"));
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($host, $user, $pass);
    $mysqli->set_charset('utf8mb4');

    $mysqli->query(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $mysqli->select_db($name);

    $sql = file_get_contents($sqlFile);
    if ($sql === false || $sql === '') {
        throw new RuntimeException('No se pudo leer el SQL o está vacío.');
    }

    if (!$mysqli->multi_query($sql)) {
        throw new RuntimeException($mysqli->error);
    }
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    $tablesRes = $mysqli->query('SHOW TABLES');
    $tableCount = 0;
    if ($tablesRes instanceof mysqli_result) {
        $tableCount = $tablesRes->num_rows;
        $tablesRes->free();
    }

    $productCount = 0;
    try {
        $pr = $mysqli->query('SELECT COUNT(*) AS c FROM products');
        if ($pr instanceof mysqli_result) {
            $row = $pr->fetch_assoc();
            $productCount = (int) ($row['c'] ?? 0);
            $pr->free();
        }
    } catch (Throwable) {
        // tabla opcional
    }

    echo green("Importación exitosa!\n");
    echo "  Tablas: {$tableCount}\n";
    echo "  Productos: {$productCount}\n";
} catch (Throwable $e) {
    fwrite(STDERR, red('Error: ' . $e->getMessage() . "\n"));
    exit(1);
}
