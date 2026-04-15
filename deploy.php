#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * LIMPIA OESTE — Deploy a Producción
 *
 * Uso:
 *   php deploy.php                 → Deploy completo (incluye vendor/; corré composer install antes)
 *   php deploy.php --dry-run       → Simular sin subir
 *   php deploy.php --no-vendor     → No sube vendor/
 *   php deploy.php --only=app      → Solo carpeta app/
 *   php deploy.php --only=public   → Solo carpeta public/
 *   php deploy.php --force-root    → Permite subir a /public_html (bloqueado por seguridad por defecto)
 *
 * Subdominio (sistema.limpiaoeste.com.ar): FTP_PATH = carpeta remota de ESE subdominio
 * (la que muestra el FTP/cPanel al abrir el sitio, no siempre es la misma que el dominio principal).
 */

/**
 * @return array<string, string>
 */
function deployLoadEnv(string $path): array
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

function deployColor(string $msg, string $color): string
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
    return deployColor($msg, 'green');
}

function red(string $msg): string
{
    return deployColor($msg, 'red');
}

function yellow(string $msg): string
{
    return deployColor($msg, 'yellow');
}

/**
 * @param list<string> $exclude
 * @return array<string, string> local absolute path => relative path with /
 */
function collectFiles(string $baseDir, array $exclude, bool $noVendor, ?string $only): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);

        if (deployIsExcluded($relativePath, $exclude, $noVendor)) {
            continue;
        }

        if ($only !== null && $only !== '' && !str_starts_with($relativePath, rtrim($only, '/') . '/') && $relativePath !== rtrim($only, '/')) {
            continue;
        }

        $files[$file->getPathname()] = $relativePath;
    }

    return $files;
}

/**
 * @param list<string> $exclude
 */
function deployIsExcluded(string $relativePath, array $exclude, bool $noVendor): bool
{
    if ($noVendor && (str_starts_with($relativePath, 'vendor/') || $relativePath === 'vendor')) {
        return true;
    }

    foreach ($exclude as $pattern) {
        $pattern = trim($pattern);
        if ($pattern === '') {
            continue;
        }
        if (strpbrk($pattern, '*?[') !== false) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
            continue;
        }
        $p = rtrim($pattern, '/');
        if ($relativePath === $p || str_starts_with($relativePath, $p . '/')) {
            return true;
        }
    }

    return false;
}

function ftpMkdirRecursive($ftp, string $dir): void
{
    $dir = str_replace('\\', '/', $dir);
    $dir = trim($dir, '/');
    if ($dir === '') {
        return;
    }
    $parts = explode('/', $dir);
    $current = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $current .= '/' . $part;
        @ftp_mkdir($ftp, $current);
    }
}

$baseDir = __DIR__;
$env = deployLoadEnv($baseDir . '/.env');

$host = $env['FTP_HOST'] ?? '';
$user = $env['FTP_USER'] ?? '';
$pass = $env['FTP_PASS'] ?? '';
$remotePath = $env['FTP_PATH'] ?? '/public_html/sistema';

$dryRun = in_array('--dry-run', $argv, true);
$noVendor = in_array('--no-vendor', $argv, true);
$forceRoot = in_array('--force-root', $argv, true);
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    }
}

if (rtrim($remotePath, '/') === '/public_html' && !$forceRoot) {
    fwrite(STDERR, red("Error: FTP_PATH está en /public_html (raíz del dominio principal).\n"));
    fwrite(STDERR, yellow("Para este proyecto usá /public_html/sistema en .env.\n"));
    fwrite(STDERR, yellow("Si querés subir intencionalmente a /public_html, corré con --force-root.\n"));
    exit(1);
}

if (!$dryRun) {
    if (!extension_loaded('ftp')) {
        fwrite(STDERR, red("Error: PHP no tiene la extensión FTP habilitada.\n"));
        fwrite(STDERR, yellow("En Laragon: Menú → PHP → php.ini → descomentá la línea ") . "extension=ftp\n");
        fwrite(STDERR, yellow("O en php.ini: ") . "extension=ftp\n");
        fwrite(STDERR, yellow("Reiniciá Apache/Nginx después.\n"));
        fwrite(STDERR, yellow("Tip: `php deploy.php --dry-run` lista archivos sin necesitar FTP.\n"));
        exit(1);
    }
    if ($host === '' || $user === '' || $pass === '') {
        fwrite(STDERR, red("Error: Configurá FTP_HOST, FTP_USER y FTP_PASS en .env\n"));
        exit(1);
    }
}

$exclude = [
    '.git',
    '.gitignore',
    '.env',
    'deploy.php',
    'install.php',
    'db_export.php',
    'db_import.php',
    'deploy.bat',
    'deploy.sh',
    'node_modules',
    'storage/logs/*.log',
    'storage/pdfs/*.pdf',
    'database/export_*.sql',
    '.env.example',
    'composer.lock',
    'composer.json',
    '*.md',
];

$ftp = null;
if (!$dryRun) {
    $ftp = @ftp_connect($host, 21, 30);
    if ($ftp === false || !@ftp_login($ftp, $user, $pass)) {
        fwrite(STDERR, red("Error: No se pudo conectar al FTP\n"));
        exit(1);
    }
    ftp_pasv($ftp, true);
    echo green("Conectado a {$host}\n");
    echo 'Remote path: ' . $remotePath . "\n\n";
} else {
    echo yellow("Modo --dry-run: sin conexión FTP") . "\n";
    echo 'Remote path (referencia): ' . $remotePath . "\n\n";
}

$files = collectFiles($baseDir, $exclude, $noVendor, $only);
$total = count($files);
$uploaded = 0;
$errors = 0;

echo "Archivos a subir: {$total}\n\n";

foreach ($files as $localFile => $remoteFile) {
    $remoteFull = rtrim($remotePath, '/') . '/' . $remoteFile;

    if ($dryRun) {
        echo "  [DRY] {$remoteFile}\n";
        $uploaded++;
        continue;
    }

    $remoteDir = str_replace('\\', '/', dirname($remoteFull));
    ftpMkdirRecursive($ftp, $remoteDir);

    if (@ftp_put($ftp, $remoteFull, $localFile, FTP_BINARY)) {
        $uploaded++;
        if ($uploaded % 10 === 0 || $uploaded === $total) {
            echo green("  [{$uploaded}/{$total}] ") . "{$remoteFile}\n";
        }
    } else {
        $errors++;
        fwrite(STDERR, red("  ERROR: {$remoteFile}\n"));
    }
}

if ($ftp !== null) {
    ftp_close($ftp);
}

echo "\n";
echo green("Deploy completado!\n");
echo "  Subidos: {$uploaded}\n";
if ($errors > 0) {
    echo red("  Errores: {$errors}\n");
}
if ($dryRun) {
    echo yellow("  (modo simulación, no se subió nada)\n");
}
echo "\n";
$appUrl = $env['APP_URL'] ?? '';
echo 'APP_URL en tu .env (solo referencia; en local suele ser localhost): ' . ($appUrl !== '' ? $appUrl : '(vacío)') . "\n";
echo 'En el servidor, el .env debe tener: https://sistema.limpiaoeste.com.ar' . "\n";

if (!$noVendor && !is_file($baseDir . '/vendor/autoload.php')) {
    echo "\n" . yellow('Aviso: no hay vendor/autoload.php. Corré `composer install` antes del deploy o usá --no-vendor si subís dependencias por otro medio.') . "\n";
}
