<?php

declare(strict_types=1);

$url = 'https://seiqgroupsa.com.ar/seiq/desengrasantes/';
$outFile = dirname(__DIR__) . '/storage/cache/seiq_desengrasantes.html';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$body = (string) $body;
@mkdir(dirname($outFile), 0775, true);
file_put_contents($outFile, $body);

echo "HTTP: {$code}\n";
echo 'Body length: ' . strlen($body) . "\n\n";

// Buscar clases repetidas que podrían ser cards de producto
preg_match_all('/class="([^"]{5,120})"/i', $body, $classMatches);
$classCounts = [];
foreach ($classMatches[1] as $classAttr) {
    foreach (preg_split('/\s+/', $classAttr) as $cls) {
        if ($cls === '' || str_starts_with($cls, 'fusion-') && str_contains($cls, 'icon')) {
            continue;
        }
        if (preg_match('/^(fa-|icon-|wp-|menu-|nav-|btn-|col-|row-|active|clearfix|hide-|show-|screen-reader)/', $cls)) {
            continue;
        }
        $classCounts[$cls] = ($classCounts[$cls] ?? 0) + 1;
    }
}
arsort($classCounts);
echo "=== Top clases repetidas (>= 3) ===\n";
$n = 0;
foreach ($classCounts as $cls => $cnt) {
    if ($cnt < 3) {
        break;
    }
    echo sprintf("%4d  %s\n", $cnt, $cls);
    if (++$n >= 25) {
        break;
    }
}

// Patrones comunes Avada/WordPress product grid
$patterns = [
    'fusion-portfolio' => '/fusion-portfolio/i',
    'fusion-imageframe' => '/fusion-imageframe/i',
    'fusion-column' => '/class="[^"]*fusion-column[^"]*"/i',
    'portfolio-item' => '/portfolio-item/i',
    'product-title' => '/<h[234][^>]*>.*?<\/h[234]>/is',
    'img uploads' => '/<img[^>]+uploads[^>]+>/i',
];

echo "\n=== Conteos de patrones ===\n";
foreach ($patterns as $label => $pat) {
    preg_match_all($pat, $body, $m);
    echo sprintf("%-20s %d\n", $label . ':', count($m[0]));
}

// Intentar extraer bloques fusion-portfolio-post o similares
$candidatePatterns = [
    'fusion-portfolio-post' => '/<div[^>]*class="[^"]*fusion-portfolio-post[^"]*"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>/is',
    'fusion_builder_column' => '/<div[^>]*class="[^"]*fusion-builder-column[^"]*"[^>]*>.*?<\/div>\s*<\/div>/is',
    'fusion-layout-column' => '/<div[^>]*class="[^"]*fusion-layout-column[^"]*"[^>]*>.*?<\/div>\s*<\/div>/is',
];

echo "\n=== Extracción de bloques candidatos ===\n";
foreach ($candidatePatterns as $name => $pat) {
    preg_match_all($pat, $body, $blocks);
    echo "{$name}: " . count($blocks[0]) . " bloques\n";
}

// Buscar imágenes en uploads con contexto
echo "\n=== Primeras 5 imágenes wp-content/uploads con contexto (500 chars) ===\n";
preg_match_all('/.{0,250}<img[^>]+src=["\']([^"\']*uploads[^"\']+)["\'][^>]*>.{0,250}/is', $body, $imgCtx, PREG_SET_ORDER);
foreach (array_slice($imgCtx, 0, 5) as $i => $match) {
    echo "\n--- Imagen " . ($i + 1) . " ---\n";
    echo html_entity_decode(strip_tags($match[0])) . "\n";
    echo "src: {$match[1]}\n";
}

// Extraer bloques por fusion-imageframe wrapper (común en Avada)
preg_match_all(
    '/(<div[^>]*class="[^"]*fusion-imageframe[^"]*"[^>]*>.*?<\/div>\s*(?:<div[^>]*class="[^"]*fusion-title[^"]*"[^>]*>.*?<\/div>)?)/is',
    $body,
    $frameBlocks
);

if ($frameBlocks[1] !== []) {
    echo "\n=== Bloques fusion-imageframe (+ title opcional): " . count($frameBlocks[1]) . " ===\n";
    foreach (array_slice($frameBlocks[1], 0, 3) as $i => $block) {
        echo "\n===== PRODUCTO EJEMPLO " . ($i + 1) . " =====\n";
        echo trim($block) . "\n";
    }
}

// Alternativa: columnas con img + heading
preg_match_all(
    '/(<div[^>]*class="[^"]*fusion-layout-column[^"]*"[^>]*>\s*(?:<div[^>]*>\s*)*<div[^>]*fusion-imageframe[^>]*>.*?<\/div>\s*<div[^>]*fusion-title[^>]*>.*?<\/div>.*?<\/div>)/is',
    $body,
    $colBlocks
);

if ($colBlocks[1] !== []) {
    echo "\n=== Bloques fusion-layout-column con imagen+título: " . count($colBlocks[1]) . " ===\n";
    foreach (array_slice($colBlocks[1], 0, 3) as $i => $block) {
        echo "\n===== COLUMNA PRODUCTO " . ($i + 1) . " =====\n";
        echo trim($block) . "\n";
    }
}
