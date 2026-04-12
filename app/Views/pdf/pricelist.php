<?php
/** @var string $listName */
/** @var string $generatedAt */
/** @var bool $includeIva */
/** @var array<string, list<array<string,mixed>>> $grouped */
$wa = setting('empresa_whatsapp', '');
$ig = setting('empresa_instagram', '');
$zona = setting('empresa_zona', '');
$logoPath = defined('PUBLIC_PATH') ? realpath(PUBLIC_PATH . '/assets/img/logoLimpiaOeste.png') : false;
$logoSrc = '';
if ($logoPath && is_readable($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
}
$leyendaIvaLista = priceIvaLegendLine($includeIva);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { color: #1a6b3c; font-size: 18px; margin: 0 0 4px 0; }
        .meta { font-size: 10px; color: #6B7280; margin-bottom: 16px; }
        .contact { font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #1a6b3c; color: #fff; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        .cat { background: #e5e7eb; font-weight: bold; padding: 6px 4px; margin-top: 8px; }
        .right { text-align: right; }
        .footer { margin-top: 20px; font-size: 9px; color: #6B7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .hdr { display: table; width: 100%; margin-bottom: 8px; }
        .hdr-logo { display: table-cell; vertical-align: middle; width: 140px; }
        .hdr-txt { display: table-cell; vertical-align: middle; padding-left: 10px; }
    </style>
</head>
<body>
    <div class="hdr">
        <?php if ($logoSrc !== ''): ?>
            <div class="hdr-logo"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="" style="height:50px;width:auto;"></div>
        <?php endif; ?>
        <div class="hdr-txt">
            <h1 style="margin:0;"><?= htmlspecialchars(setting('empresa_nombre', 'LIMPIA OESTE')) ?></h1>
            <p class="meta" style="margin:4px 0 0 0;"><?= htmlspecialchars(setting('empresa_tagline', '')) ?> · <?= htmlspecialchars($generatedAt) ?></p>
        </div>
    </div>
    <div class="contact">
        <?php if ($wa): ?>WhatsApp: <?= htmlspecialchars($wa) ?> · <?php endif; ?>
        <?php if ($ig): ?>Instagram: <?= htmlspecialchars($ig) ?> · <?php endif; ?>
        <?php if ($zona): ?><?= htmlspecialchars($zona) ?><?php endif; ?>
    </div>
    <h2 style="font-size:14px;color:#1a6b3c;"><?= htmlspecialchars($listName) ?></h2>
    <?php foreach ($grouped as $catName => $lines): ?>
        <div class="cat"><?= htmlspecialchars($catName) ?></div>
        <table>
            <thead>
                <tr>
                    <th style="width:12%">Código</th>
                    <th style="width:34%">Producto</th>
                    <th style="width:22%">Presentación</th>
                    <th class="right" style="width:14%">P. unitario</th>
                    <th class="right" style="width:18%">Precio venta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <?php
                    $calc = $line['calc'];
                    $p = $line['product'];
                    $price = $includeIva && $calc['precio_con_iva'] !== null ? $calc['precio_con_iva'] : $calc['precio_venta'];
                    $ind = (float) ($line['individual_venta'] ?? 0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($p['code']) ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars((string) ($p['presentation'] ?? $p['content'] ?? '')) ?></td>
                        <td class="right">$ <?= number_format($ind, 2, ',', '.') ?></td>
                        <td class="right">$ <?= number_format($price, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
    <div class="footer">
        <p style="font-style:italic;color:#4B5563;margin-bottom:6px;"><?= htmlspecialchars($leyendaIvaLista) ?></p>
        Entrega en 24hs — <?= htmlspecialchars($zona ?: 'Zona Oeste GBA') ?>.
    </div>
</body>
</html>
