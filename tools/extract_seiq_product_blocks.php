<?php

declare(strict_types=1);

$file = dirname(__DIR__) . '/storage/cache/seiq_desengrasantes.html';
$html = file_get_contents($file);
if ($html === false) {
    fwrite(STDERR, "No se pudo leer HTML\n");
    exit(1);
}

// Cada producto = bloque fusion-fullwidth fusion-builder-row-N (N >= 2)
preg_match_all(
    '/<div class="fusion-fullwidth fullwidth-box fusion-builder-row-(\d+)[^"]*"[^>]*>(.*?)(?=<div class="fusion-fullwidth fullwidth-box fusion-builder-row-|\Z)/is',
    $html,
    $rows,
    PREG_SET_ORDER
);

echo 'Bloques fusion-builder-row encontrados: ' . count($rows) . "\n\n";

$productRows = array_values(array_filter($rows, static fn ($r) => (int) $r[1] >= 2));
echo 'Filas producto (row >= 2): ' . count($productRows) . "\n\n";

foreach (array_slice($productRows, 0, 3) as $i => $row) {
    $block = $row[0];
    $rowNum = $row[1];

    preg_match('/<h2 class="content-box-heading"[^>]*>(.*?)<\/h2>/is', $block, $nameM);
    preg_match('/<div class="fusion-imageframe[^"]*"[^>]*>.*?<img[^>]+>/is', $block, $imgBlockM);
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $block, $allImgs);
    $productImgs = [];
    foreach ($allImgs[1] ?? [] as $src) {
        if (str_contains($src, 'icono_')) {
            continue;
        }
        if (str_contains($src, 'logo')) {
            continue;
        }
        $productImgs[] = $src;
    }

    echo "========== PRODUCTO " . ($i + 1) . " (fusion-builder-row-{$rowNum}) ==========\n";
    echo 'Nombre (h2.content-box-heading): ' . trim(strip_tags($nameM[1] ?? '')) . "\n";
    echo 'Imágenes producto (sin icono/logo): ' . implode(', ', $productImgs) . "\n\n";

    // Pretty-print bloque recortado: solo columnas relevantes
    if (preg_match('/<div\s+class="fusion-layout-column fusion_builder_column fusion_builder_column_1_2[^"]*fusion-one-half fusion-column-first.*?<\/div>\s*<\/div>\s*<\/div>\s*<div\s+class="fusion-layout-column fusion_builder_column fusion_builder_column_1_2[^"]*fusion-one-half fusion-column-last.*?<\/div>\s*<\/div>\s*<\/div>/is', $block, $cols)) {
        $snippet = $cols[0];
    } else {
        $snippet = substr($block, 0, 4000);
    }

    // Formatear un poco para legibilidad
    $snippet = preg_replace('/></', ">\n<", $snippet) ?? $snippet;
    echo $snippet . "\n\n";
}

// Resumen estructura
echo "=== RESUMEN ESTRUCTURA ===\n";
echo "- Contenedor producto: div.fusion-fullwidth.fullwidth-box.fusion-builder-row-N\n";
echo "- Layout: 2 columnas fusion-layout-column 1_2 (50% + 50%)\n";
echo "- Columna izq: div.fusion-imageframe > span.fusion-imageframe-image > img[src]\n";
echo "- Columna der: div.fusion-content-boxes > h2.content-box-heading (nombre)\n";
echo "- Icono categoría (NO producto): img en .heading .image (icono_*.jpg)\n";
