<?php
/** @var array<string,mixed> $quote */
/** @var list<array<string,mixed>> $items */
$wa = setting('empresa_whatsapp', '');
$ig = setting('empresa_instagram', '');
$logoPath = defined('PUBLIC_PATH') ? realpath(PUBLIC_PATH . '/assets/img/logoLimpiaOeste.png') : false;
$logoSrc = '';
if ($logoPath && is_readable($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
}
$leyendaIvaPdf = priceIvaLegendLine(!empty($quote['include_iva']));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { color: #1a6b3c; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #1a6b3c; color: #fff; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .box { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 12px; }
        .hdr-logo { margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php if ($logoSrc !== ''): ?>
        <div class="hdr-logo"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="" style="height:50px;width:auto;"></div>
    <?php endif; ?>
    <p style="font-size:10px;">Presupuesto <strong><?= htmlspecialchars($quote['quote_number']) ?></strong> · <?= htmlspecialchars($quote['created_at']) ?></p>
    <div class="box">
        <strong>Cliente</strong><br>
        <?= htmlspecialchars($quote['client_name'] ?? '') ?><br>
        <?php if (!empty($quote['business_name'])): ?><?= htmlspecialchars($quote['business_name']) ?><br><?php endif; ?>
        <?php if (!empty($quote['address'])): ?><?= htmlspecialchars($quote['address']) ?><br><?php endif; ?>
        <?php if (!empty($quote['city'])): ?><?= htmlspecialchars($quote['city']) ?><?php endif; ?>
    </div>
    <?php if (!empty($quote['title'])): ?>
        <p><strong><?= htmlspecialchars($quote['title']) ?></strong></p>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Detalle</th>
                <th class="right">Cant.</th>
                <th class="right">P. unit.</th>
                <th class="right">Precio</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <?php $isCombo = (int) ($it['combo_id'] ?? 0) > 0; ?>
                <tr>
                    <td><?= $isCombo ? htmlspecialchars((string) ($it['combo_name'] ?? 'Combo')) : htmlspecialchars((string) ($it['code'] ?? '')) . ' — ' . htmlspecialchars((string) ($it['name'] ?? '')) ?></td>
                    <td><?= $isCombo ? 'Combo' : htmlspecialchars(quoteItemDetalleDisplay($it)) ?></td>
                    <td class="right"><?= (int) $it['quantity'] ?></td>
                    <td class="right">$ <?= number_format($isCombo ? (float) $it['unit_price'] : quoteItemIndividualUnitPrice($it, $quote), 2, ',', '.') ?></td>
                    <td class="right">$ <?= number_format((float) $it['unit_price'], 2, ',', '.') ?></td>
                    <td class="right">$ <?= number_format((float) $it['subtotal'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:9px;color:#6B7280;font-style:italic;margin-top:6px;"><?= htmlspecialchars($leyendaIvaPdf) ?></p>
    <p class="right"><strong>Subtotal:</strong> $ <?= number_format((float) $quote['subtotal'], 2, ',', '.') ?></p>
    <?php if ((float) $quote['iva_amount'] > 0): ?>
        <p class="right"><strong>IVA:</strong> $ <?= number_format((float) $quote['iva_amount'], 2, ',', '.') ?></p>
    <?php endif; ?>
    <?php if ((float) ($quote['discount_amount'] ?? 0) > 0): ?>
        <p class="right">
            <strong>Descuento<?= ($quote['discount_percentage'] ?? null) !== null ? ' (' . number_format((float) $quote['discount_percentage'], 2, ',', '.') . '%)' : '' ?>:</strong>
            - $ <?= number_format((float) $quote['discount_amount'], 2, ',', '.') ?>
        </p>
    <?php endif; ?>
    <p class="right" style="font-size:13px;color:#1a6b3c;"><strong>Total:</strong> $ <?= number_format((float) $quote['total'], 2, ',', '.') ?></p>
    <?php if (!empty($quote['notes'])): ?>
        <div class="box" style="margin-top:16px;">
            <strong>Condiciones</strong><br>
            <?= nl2br(htmlspecialchars($quote['notes'])) ?>
        </div>
    <?php endif; ?>
    <p style="margin-top:20px;font-size:9px;color:#6B7280;">
        Contacto: WhatsApp <?= htmlspecialchars($wa) ?> · <?= htmlspecialchars($ig) ?>
    </p>
</body>
</html>
