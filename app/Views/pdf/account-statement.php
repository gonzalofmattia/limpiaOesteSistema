<?php
/** @var string $entityType */
/** @var array<string,mixed> $entity */
/** @var list<array<string,mixed>> $rows */
/** @var float $openingBalance */
/** @var float $currentBalance */
$wa = setting('empresa_whatsapp', '');
$ig = setting('empresa_instagram', '');
$logoPath = defined('PUBLIC_PATH') ? realpath(PUBLIC_PATH . '/assets/img/logoLimpiaOeste.png') : false;
$logoSrc = '';
if ($logoPath && is_readable($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #1a6b3c; color: #fff; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .box { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 12px; }
        .hdr-logo { margin-bottom: 10px; }
        .muted { color: #6b7280; }
        .opening td { background: #fef3c7; }
        .totals { margin-top: 14px; }
        .totals p { margin: 4px 0; }
        .strong-green { color: #1a6b3c; font-size: 13px; }
    </style>
</head>
<body>
    <?php if ($logoSrc !== ''): ?>
        <div class="hdr-logo"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="" style="height:50px;width:auto;"></div>
    <?php endif; ?>
    <p style="font-size:10px;">Estado de cuenta · <?= e(date('d/m/Y')) ?></p>
    <?php if ($entityType === 'client'): ?>
        <div class="box">
            <strong>Cliente</strong><br>
            <?= e((string) ($entity['name'] ?? '')) ?><br>
            <?php if (!empty($entity['business_name'])): ?><?= e((string) $entity['business_name']) ?><br><?php endif; ?>
            <?php if (!empty($entity['phone'])): ?>Tel: <?= e((string) $entity['phone']) ?><br><?php endif; ?>
            <?php if (!empty($entity['address'])): ?><?= e((string) $entity['address']) ?><br><?php endif; ?>
            <?php if (!empty($entity['city'])): ?><?= e((string) $entity['city']) ?><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="box">
            <strong>Proveedor</strong><br>
            <?= e((string) ($entity['name'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th class="right">Debe</th>
                <th class="right">Haber</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="<?= !empty($row['is_opening_balance']) ? 'opening' : '' ?>">
                    <td>
                        <?php if (!empty($row['transaction_date'])): ?>
                            <?= e(date('d/m/Y', strtotime((string) $row['transaction_date']))) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $row['description']) ?></td>
                    <td class="right"><?= (float) $row['debe'] > 0 ? e(formatPrice((float) $row['debe'])) : '' ?></td>
                    <td class="right"><?= (float) $row['haber'] > 0 ? e(formatPrice((float) $row['haber'])) : '' ?></td>
                    <td class="right"><?= e(formatPrice((float) $row['saldo'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals right">
        <?php if ($entityType === 'client' && (float) $openingBalance > 0): ?>
            <p><strong>Saldo inicial:</strong> <?= e(formatPrice((float) $openingBalance)) ?></p>
        <?php endif; ?>
        <p class="strong-green"><strong>Saldo actual:</strong> <?= e(formatPrice((float) $currentBalance)) ?></p>
    </div>
    <p class="muted">* Los montos expresados no incluyen IVA</p>
    <p class="muted">Contacto: WhatsApp <?= e((string) $wa) ?> · <?= e((string) $ig) ?></p>
</body>
</html>
