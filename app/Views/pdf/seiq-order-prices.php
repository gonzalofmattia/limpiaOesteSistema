<?php
/** @var array{order_number:string,created_at:string,total_boxes:int,total_amount:float,supplier_name?:string,cliente_id?:string,cliente_nombre?:string,condicion_pago?:string,observaciones?:string} $order */
/** @var list<array{code:string,name:string,presentation?:string,content?:string,sale_unit_description?:string,boxes_to_order:int,price_per_box:float,line_total:float}> $lines */
$logoPath = defined('PUBLIC_PATH') ? realpath(PUBLIC_PATH . '/assets/img/logoLimpiaOeste.png') : false;
$logoSrc = '';
if ($logoPath && is_readable($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
}
$fecha = $order['created_at'] ?? '';
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $fecha, $m)) {
    $fecha = $m[3] . '/' . $m[2] . '/' . $m[1];
}
$wa = setting('empresa_whatsapp', '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { color: #1a6b3c; font-size: 16px; margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #1565C0; color: #fff; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .right { text-align: right; }
        .hdr-logo { margin-bottom: 10px; }
        .meta { font-size: 10px; margin-bottom: 8px; }
        .meta strong { color: #1a6b3c; }
        .total { margin-top: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <?php if ($logoSrc !== ''): ?>
        <div class="hdr-logo"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="" style="height:50px;width:auto;"></div>
    <?php endif; ?>
    <h1>PEDIDO CON COSTOS</h1>
    <p class="meta">
        <strong>Pedido N°:</strong> <?= htmlspecialchars($order['order_number']) ?>
        &nbsp;&nbsp;&nbsp;
        <strong>Fecha:</strong> <?= htmlspecialchars($fecha) ?>
    </p>
    <p class="meta">
        <strong>Proveedor:</strong> <?= htmlspecialchars((string) ($order['supplier_name'] ?? '')) ?><br>
        <strong>Cliente ID:</strong> <?= htmlspecialchars((string) ($order['cliente_id'] ?? '')) ?><br>
        <strong>Nombre:</strong> <?= htmlspecialchars((string) ($order['cliente_nombre'] ?? '')) ?><br>
        <strong>Condición:</strong> <?= htmlspecialchars((string) ($order['condicion_pago'] ?? '')) ?><br>
        <strong>Observaciones:</strong> <?= htmlspecialchars((string) ($order['observaciones'] ?? '')) ?>
    </p>
    <table>
        <thead>
            <tr>
                <th style="width:14%">Código</th>
                <th>Descripción</th>
                <th class="right" style="width:10%">Cant.</th>
                <th class="right" style="width:15%">Costo caja</th>
                <th class="right" style="width:16%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line):
                $name = trim((string) ($line['name'] ?? ''));
                $extra = trim((string) ($line['presentation'] ?? ''));
                if ($extra === '') {
                    $extra = trim((string) ($line['sale_unit_description'] ?? ''));
                }
                if ($extra === '') {
                    $extra = trim((string) ($line['content'] ?? ''));
                }
                $desc = $extra !== '' ? $name . ' (' . $extra . ')' : $name;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($line['code'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($desc) ?></td>
                    <td class="right"><?= (int) ($line['boxes_to_order'] ?? 0) ?></td>
                    <td class="right">$ <?= number_format((float) ($line['price_per_box'] ?? 0), 2, ',', '.') ?></td>
                    <td class="right">$ <?= number_format((float) ($line['line_total'] ?? 0), 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="total right"><strong>Total costo pedido:</strong> $ <?= number_format((float) ($order['total_amount'] ?? 0), 2, ',', '.') ?></p>
    <p class="right"><strong>Total bultos:</strong> <?= (int) ($order['total_boxes'] ?? 0) ?></p>
    <p style="margin-top:16px;font-size:9px;color:#6B7280;">Contacto: WhatsApp <?= htmlspecialchars((string) $wa) ?></p>
</body>
</html>

