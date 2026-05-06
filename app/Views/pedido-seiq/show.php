<?php
/** @var array<string,mixed> $order */
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $includedQuotes */
/** @var string $waMessage */
$statusBadge = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-800',
    'received' => 'bg-green-100 text-green-800',
];
$remainderRows = array_values(array_filter($items, static fn ($it) => (int) ($it['units_remainder'] ?? 0) > 0));
?>
<div class="max-w-6xl space-y-6">
    <div class="flex flex-wrap justify-between gap-4 items-start">
        <div>
            <p class="text-sm text-gray-700 font-medium"><?= e($order['order_number']) ?> · <?= e((string) ($order['supplier_name'] ?? '—')) ?></p>
            <p class="text-sm text-gray-600 mt-1"><?= e($order['created_at']) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex px-2 py-1 rounded-full text-xs <?= $statusBadge[$order['status']] ?? 'bg-gray-100' ?>"><?= e($order['status']) ?></span>
            <a href="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/pdf')) ?>" class="px-3 py-1.5 rounded-lg bg-[#1a6b3c] text-white text-sm">PDF</a>
            <a href="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/pdf-precios')) ?>" class="px-3 py-1.5 rounded-lg bg-[#1565C0] text-white text-sm">PDF con precios</a>
            <button type="button" onclick="enviarPedidoWhatsApp()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#25D366] text-white rounded-lg hover:bg-[#1fb855] text-sm">Enviar por WhatsApp</button>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Volver</a>
        </div>
    </div>

    <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/status')) ?>" class="flex flex-wrap gap-3 items-end bg-white p-4 rounded-xl border border-gray-200">
        <?= csrfField() ?>
        <div class="text-sm text-gray-600">Estado del pedido:</div>
        <div class="min-w-[250px]">
            <label class="block text-xs text-gray-500 mb-1">Monto real remito/factura (al recibir)</label>
            <input type="text" name="received_amount" value="<?= e(number_format($suggestedReceivedAmount, 2, ',', '.')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <p class="text-xs text-gray-500 mt-1">Monto sugerido: <?= formatPrice($suggestedReceivedAmount) ?></p>
        </div>
        <div class="min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1">Fecha remito</label>
            <input type="date" name="received_date" value="<?= e(date('Y-m-d')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <?php foreach (['draft', 'sent', 'received'] as $s): ?>
            <button type="submit" name="status" value="<?= e($s) ?>" class="px-3 py-1 rounded-lg text-xs border border-gray-200 hover:bg-gray-50"><?= e($s) ?></button>
        <?php endforeach; ?>
    </form>
    <p class="text-xs text-gray-600 -mt-2 mb-2">Al pasar el pedido a <strong>received</strong>, se suma al <strong>stock</strong> de cada producto la cantidad recibida (cajas/packs pedidos × unidades por caja). Si volvés a <strong>draft</strong> o <strong>sent</strong>, se revierte esa suma.</p>

    <?php if (!empty($includedQuotes)): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Presupuestos incluidos</h3>
            <ul class="space-y-1 text-sm">
                <?php foreach ($includedQuotes as $q): ?>
                    <li>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-[#1565C0] hover:underline font-mono"><?= e($q['quote_number']) ?></a>
                        — <?= e($q['client_name'] ?? '—') ?>
                        <span class="text-gray-500">(<?= e($q['status']) ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/quotes-delivered')) ?>" class="mt-4">
                <?= csrfField() ?>
                <button type="submit" class="text-sm px-3 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100">
                    Marcar presupuestos como entregados
                </button>
            </form>
            <p class="text-xs text-gray-600 mt-2">Al marcar entregados se pasa el presupuesto a <strong>delivered</strong> y se <strong>descuenta stock</strong> como en la ficha del presupuesto.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($order['notes'])): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-800">
            <strong>Notas:</strong> <?= nl2br(e((string) $order['notes'])) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Detalle del pedido</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs table-auto lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-2 py-2">Cód.</th>
                        <th class="text-left px-2 py-2 w-[30%]">Producto</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Vendido</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Pedir</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Rem.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $r):
                        $group = !empty($r['parent_category_name'])
                            ? (string) $r['parent_category_name']
                            : (string) ($r['category_name'] ?? '');
                        $sub = !empty($r['parent_category_name']) ? (string) ($r['category_name'] ?? '') : '';
                        $pack = strtolower(trim((string) ($r['sale_unit_label'] ?? ''))) ?: 'caja';
                        $qb = (int) $r['qty_boxes_sold'];
                        $qu = (int) $r['qty_units_sold'];
                        $vendidoLines = [];
                        if ($qb > 0) {
                            $vendidoLines[] = $qb . ' ' . $pack;
                        }
                        if ($qu > 0) {
                            $vendidoLines[] = $qu . ' un.';
                        }
                        $vendidoBody = implode(' + ', $vendidoLines);
                        if ($vendidoBody === '') {
                            $vendidoBody = 'Reposición manual';
                        }
                        $pedir = (int) $r['boxes_to_order'] . ' ' . $pack . ' (×' . (int) $r['units_per_box'] . ' u.)';
                        $isManual = ((string) ($r['origin'] ?? 'auto')) === 'manual';
                        ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-2 py-2 align-top font-mono text-xs whitespace-nowrap"><?= e($r['code']) ?></td>
                            <td class="px-2 py-2 align-top">
                                <span class="block truncate max-w-[220px]" title="<?= e($r['product_name']) ?>"><?= e($r['product_name']) ?></span>
                                <?php if ($isManual): ?>
                                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-violet-100 text-violet-800">(reposición)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 align-top whitespace-nowrap">
                                <div><?= e($vendidoBody) ?></div>
                                <div class="text-[11px] text-gray-500">= <?= (int) $r['total_units_needed'] ?></div>
                            </td>
                            <td class="px-2 py-2 align-top font-medium text-[#1a6b3c] whitespace-nowrap"><?= e($pedir) ?></td>
                            <td class="px-2 py-2 align-top whitespace-nowrap"><?= e(seiqRemainderLabel($r)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 text-sm text-gray-700">
            Total: <strong><?= (int) $order['total_products'] ?></strong> productos —
            <strong><?= (int) $order['total_boxes'] ?></strong> cajas/bultos
        </div>
    </div>

    <?php if ($remainderRows !== []): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-amber-50/80">
                <h3 class="text-sm font-semibold text-gray-900">Remanente (stock que queda después de entregar)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs lo-table">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-3 py-2">Código</th>
                            <th class="text-left px-3 py-2">Producto</th>
                            <th class="text-left px-3 py-2">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($remainderRows as $r): ?>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs"><?= e($r['code']) ?></td>
                                <td class="px-3 py-2"><?= e($r['product_name']) ?></td>
                                <td class="px-3 py-2"><?= e(seiqRemainderLabel($r)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
function enviarPedidoWhatsApp() {
    const text = <?= json_encode($waMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}
</script>
