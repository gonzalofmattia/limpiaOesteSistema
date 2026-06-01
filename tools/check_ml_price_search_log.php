<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$envPath = $baseDir . '/.env';
$vars = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $vars[trim($k)] = trim($v, " \t\"'");
}

$host = $vars['FTP_HOST'] ?? '';
$user = $vars['FTP_USER'] ?? '';
$pass = $vars['FTP_PASS'] ?? '';
$remotePath = rtrim($vars['FTP_PATH'] ?? '/public_html/sistema', '/');

$ftp = @ftp_ssl_connect($host, 21, 45) ?: @ftp_connect($host, 21, 45);
if ($ftp === false) {
    fwrite(STDERR, "No se pudo conectar FTP\n");
    exit(1);
}
if (!@ftp_login($ftp, $user, $pass)) {
    fwrite(STDERR, "Login FTP falló\n");
    exit(1);
}
ftp_pasv($ftp, true);

$localLog = sys_get_temp_dir() . '/ml_errors_prod_check.log';
$remoteLog = $remotePath . '/storage/logs/ml_errors.log';
if (!@ftp_get($ftp, $localLog, $remoteLog, FTP_BINARY)) {
    fwrite(STDERR, "No se pudo descargar {$remoteLog}\n");
    exit(1);
}

$remoteFile = $remotePath . '/app/Helpers/MlPriceIntelligence.php';
$remoteMtime = @ftp_mdtm($ftp, $remoteFile);
$remoteSize = @ftp_size($ftp, $remoteFile);
ftp_close($ftp);

$localFile = $baseDir . '/app/Helpers/MlPriceIntelligence.php';
$localMtime = (int) filemtime($localFile);
$localSize = (int) filesize($localFile);

echo "=== MlPriceIntelligence.php ===\n";
echo 'Local:  ' . date('Y-m-d H:i:s', $localMtime) . " ({$localSize} bytes)\n";
if ($remoteMtime > 0) {
    echo 'Remote: ' . date('Y-m-d H:i:s', $remoteMtime) . " ({$remoteSize} bytes)\n";
    $mtimeDiff = abs($remoteMtime - $localMtime);
    echo 'Diff mtime: ' . $mtimeDiff . "s\n";
    echo 'Size match: ' . ($remoteSize === $localSize ? 'YES' : 'NO') . "\n";
    echo 'Deploy OK: ' . ($remoteSize === $localSize && $mtimeDiff <= 300 ? 'YES' : 'VERIFY') . "\n";
} else {
    echo "Remote: no se pudo leer mtime (archivo ausente o sin permiso)\n";
}

echo "\n";

$lines = file($localLog, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    exit(1);
}

$matched = [];
foreach ($lines as $line) {
    if (stripos($line, 'searchCompetitors') !== false) {
        $matched[] = $line;
    }
}

$tail = array_slice($matched, -20);
echo '=== Últimas ' . count($tail) . ' líneas con searchCompetitors (de ' . count($matched) . ' totales) ===' . "\n";
if ($tail === []) {
    echo "(ninguna encontrada)\n";
} else {
    foreach ($tail as $line) {
        echo $line . "\n";
    }
}

echo "\n=== Marcadores en archivo remoto ===\n";
$remotePhp = sys_get_temp_dir() . '/MlPriceIntelligence_remote.php';
$ftp2 = @ftp_ssl_connect($host, 21, 45) ?: @ftp_connect($host, 21, 45);
if ($ftp2 && @ftp_login($ftp2, $user, $pass)) {
    ftp_pasv($ftp2, true);
    if (@ftp_get($ftp2, $remotePhp, $remoteFile, FTP_BINARY)) {
        $content = (string) file_get_contents($remotePhp);
        foreach (['buildSearchQuery', 'logSearchIfNeeded', 'clearAllCache', 'normalizeProductNameForSearch'] as $marker) {
            echo $marker . ': ' . (str_contains($content, $marker) ? 'YES' : 'NO') . "\n";
        }
    }
    $cacheList = @ftp_nlist($ftp2, $remotePath . '/storage/cache') ?: [];
    $intelCache = array_values(array_filter($cacheList, static fn ($f) => str_contains((string) $f, 'ml_price_intel_')));
    echo "\n=== Cache ml_price_intel en servidor: " . count($intelCache) . " archivo(s) ===\n";
    foreach (array_slice($intelCache, 0, 5) as $f) {
        echo basename((string) $f) . "\n";
    }
    if ($intelCache !== []) {
        $firstIds = [17, 20, 23];
        echo "\n=== Cache de los primeros 3 listings (17, 20, 23) ===\n";
        foreach ($firstIds as $lid) {
            $remoteCache = $remotePath . '/storage/cache/ml_price_intel_' . $lid . '.json';
            $localCache = sys_get_temp_dir() . '/ml_price_intel_' . $lid . '.json';
            if (@ftp_get($ftp2, $localCache, $remoteCache, FTP_BINARY)) {
                $sample = json_decode((string) file_get_contents($localCache), true);
                echo "listing {$lid}:\n";
                echo '  analyzed_at: ' . ($sample['analyzed_at'] ?? '—') . "\n";
                echo '  search_query: ' . ($sample['search_query'] ?? '(ausente)') . "\n";
                echo '  competitors_count: ' . ($sample['competitors_count'] ?? '—') . "\n";
                echo '  avg_competitor_price: ' . ($sample['avg_competitor_price'] ?? '—') . "\n";
            } else {
                echo "listing {$lid}: sin cache\n";
            }
        }
    }
    ftp_close($ftp2);
}

echo "\n=== Últimas 15 líneas de ml_errors.log (cualquier contenido) ===\n";
foreach (array_slice($lines, -15) as $line) {
    echo $line . "\n";
}
