#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera slugs únicos para productos existentes (no modifica is_published).
 *
 * Uso (desde la raíz del proyecto):
 *   php database/migrations/2026_04_18_generate_slugs.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');

require_once BASE_PATH . '/vendor/autoload.php';
require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';

$db = \App\Models\Database::getInstance();

$rows = $db->fetchAll('SELECT id, name, slug FROM products ORDER BY id ASC');
$used = [];
foreach ($rows as $r) {
    $s = trim((string) ($r['slug'] ?? ''));
    if ($s !== '') {
        $used[strtolower($s)] = true;
    }
}

$updated = 0;
foreach ($rows as $r) {
    $id = (int) $r['id'];
    $current = trim((string) ($r['slug'] ?? ''));
    if ($current !== '') {
        continue;
    }
    $name = (string) ($r['name'] ?? '');
    $base = slugify($name);
    if ($base === '') {
        $base = 'producto';
    }
    $base = mb_substr($base, 0, 240);
    $candidate = $base;
    $n = 1;
    while (isset($used[strtolower($candidate)])) {
        $suffix = '-' . $n++;
        $candidate = mb_substr($base, 0, max(1, 240 - mb_strlen($suffix))) . $suffix;
    }
    $used[strtolower($candidate)] = true;
    $db->update('products', ['slug' => $candidate], 'id = :id', ['id' => $id]);
    $updated++;
    fwrite(STDOUT, "id={$id} → {$candidate}\n");
}

fwrite(STDOUT, "Listo. Slugs asignados: {$updated}\n");
