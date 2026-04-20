<?php

declare(strict_types=1);

/**
 * Convierte un export erróneo de phpMyAdmin (CREATE TABLE + INSERT sql_line...)
 * en un .sql limpio solo con UPDATE de slug.
 *
 * Uso:
 *   php database/migrations/tools_extract_slug_updates.php "C:/Users/gonza/Downloads/products.sql"
 */

$in = $argv[1] ?? '';
if ($in === '' || !is_file($in)) {
    fwrite(STDERR, "Uso: php database/migrations/tools_extract_slug_updates.php <ruta/products.sql>\n");
    exit(1);
}

$outPath = __DIR__ . '/2026_04_18_slugs_updates_only.sql';

$out = fopen($outPath, 'wb');
if ($out === false) {
    fwrite(STDERR, "No se pudo escribir: {$outPath}\n");
    exit(1);
}

fwrite($out, "-- Solo UPDATE de slug (extraído del export; ejecutar en producción tras columna slug)\n");
fwrite($out, "SET NAMES utf8mb4;\n");
fwrite($out, "START TRANSACTION;\n");

foreach (file($in) as $line) {
    $t = trim($line);
    if (!str_starts_with($t, "('UPDATE")) {
        continue;
    }
    $s = substr($t, 2);
    if (str_ends_with($s, "'),")) {
        $s = substr($s, 0, -3);
    } elseif (str_ends_with($s, "');")) {
        $s = substr($s, 0, -3);
    }
    $s = str_replace("\\'", "'", $s);
    fwrite($out, $s . "\n");
}

fwrite($out, "COMMIT;\n");
fclose($out);

echo "Generado: {$outPath}\n";
