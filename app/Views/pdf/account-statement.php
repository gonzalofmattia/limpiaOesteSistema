<?php
/** @var string $entityType */
/** @var array<string,mixed> $entity */
/** @var list<array<string,mixed>> $rows */
/** @var float $currentBalance */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        .meta { margin-bottom: 12px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .num { text-align: right; }
        .total { margin-top: 10px; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <h1>ESTADO DE CUENTA</h1>
    <p class="muted">Fecha: <?= e(date('d/m/Y')) ?></p>
    <?php if ($entityType === 'client'): ?>
        <div class="meta">
            <div><strong>Cliente:</strong> <?= e((string) ($entity['name'] ?? '')) ?></div>
            <div><strong>Razón social:</strong> <?= e((string) ($entity['business_name'] ?? '—')) ?></div>
            <div><strong>Teléfono:</strong> <?= e((string) ($entity['phone'] ?? '—')) ?></div>
        </div>
    <?php else: ?>
        <div class="meta">
            <div><strong>Proveedor:</strong> <?= e((string) ($entity['name'] ?? '')) ?></div>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th class="num">Debe</th>
                <th class="num">Haber</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime((string) $row['transaction_date']))) ?></td>
                    <td><?= e((string) $row['description']) ?></td>
                    <td class="num"><?= (float) $row['debe'] > 0 ? e(formatPrice((float) $row['debe'])) : '' ?></td>
                    <td class="num"><?= (float) $row['haber'] > 0 ? e(formatPrice((float) $row['haber'])) : '' ?></td>
                    <td class="num"><?= e(formatPrice((float) $row['saldo'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">SALDO ACTUAL: <?= e(formatPrice((float) $currentBalance)) ?></div>
    <p class="muted">* Los montos expresados no incluyen IVA</p>
    <p class="muted">Contacto: WhatsApp <?= e((string) setting('empresa_whatsapp', '')) ?> · <?= e((string) setting('empresa_instagram', '@limpiaOeste')) ?></p>
</body>
</html>
