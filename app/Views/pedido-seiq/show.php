<?php
/** @var array<string,mixed> $order */
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $includedQuotes */
/** @var string $waMessage */
/** @var float $suggestedReceivedAmount */
/** @var list<array<string,mixed>> $companionOrders */
/** @var array<string,mixed>|null $mainOrder */
/** @var list<array<string,mixed>> $siblingOrders */
$statusBadge = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-800',
    'received' => 'bg-green-100 text-green-800',
];
$remainderRows = array_values(array_filter($items, static fn ($it) => (int) ($it['units_remainder'] ?? 0) > 0));
$orderStatus = (string) ($order['status'] ?? '');
$invoiceNumber = trim((string) ($order['invoice_number'] ?? ''));
$invoiceAmount = isset($order['invoice_amount']) ? (float) $order['invoice_amount'] : 0.0;
$invoiceDateStored = trim((string) ($order['invoice_date'] ?? ''));
$invoiceDateDisplay = $invoiceDateStored !== '' ? date('d/m/Y', strtotime($invoiceDateStored)) : '';
$companionOrders = $companionOrders ?? [];
$siblingOrders = $siblingOrders ?? [];
$mainOrder = $mainOrder ?? null;
?>
<div class="max-w-6xl space-y-6" x-data>
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

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-wrap items-center gap-3">
        <div class="text-sm text-gray-700 font-medium">Estado del pedido:</div>
        <span class="inline-flex px-2 py-1 rounded-full text-xs <?= $statusBadge[$orderStatus] ?? 'bg-gray-100' ?>"><?= e($orderStatus) ?></span>

        <?php if ($orderStatus === 'draft'): ?>
            <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/status')) ?>" class="inline-flex">
                <?= csrfField() ?>
                <input type="hidden" name="status" value="sent">
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs bg-blue-600 text-white hover:bg-blue-700">Marcar como enviado</button>
            </form>
        <?php endif; ?>

        <?php if ($orderStatus === 'sent'): ?>
            <button type="button"
                    @click="$dispatch('abrir-recepcion-pedido')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs bg-[#1a6b3c] text-white hover:bg-[#155a31]">
                <i data-lucide="package-check" class="w-4 h-4"></i>
                Marcar como recibido
            </button>
            <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/status')) ?>" class="inline-flex">
                <?= csrfField() ?>
                <input type="hidden" name="status" value="draft">
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-gray-300 hover:bg-gray-50">Volver a borrador</button>
            </form>
        <?php endif; ?>

        <?php if ($orderStatus === 'received'): ?>
            <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/status')) ?>" class="inline-flex"
                  onsubmit="return confirm('¿Revertir la recepción? Se descontará del stock lo recibido en este pedido. El registro de factura en cuenta corriente no se elimina automáticamente.')">
                <?= csrfField() ?>
                <input type="hidden" name="status" value="sent">
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs border border-amber-300 text-amber-800 hover:bg-amber-50">Revertir recepción</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="text-xs text-gray-600 -mt-2 mb-2">Al recibir, se suma al <strong>stock</strong> de cada producto la cantidad pedida (cajas × unidades por caja). El monto real de la factura es el que se registra en cuenta corriente del proveedor.</p>

    <?php if ($orderStatus === 'received' && ($invoiceNumber !== '' || $invoiceAmount > 0 || $invoiceDateDisplay !== '' || $companionOrders !== [] || $mainOrder !== null)): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-900 space-y-2">
            <p class="font-semibold flex items-center gap-2">
                <i data-lucide="receipt" class="w-4 h-4"></i>
                Factura del proveedor
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <div class="text-xs text-emerald-700">N° factura</div>
                    <div class="font-mono"><?= $invoiceNumber !== '' ? e($invoiceNumber) : '—' ?></div>
                </div>
                <div>
                    <div class="text-xs text-emerald-700">Fecha</div>
                    <div><?= $invoiceDateDisplay !== '' ? e($invoiceDateDisplay) : '—' ?></div>
                </div>
                <div>
                    <div class="text-xs text-emerald-700">Monto factura</div>
                    <div class="font-semibold">
                        <?php if ($mainOrder !== null && (float) ($mainOrder['invoice_amount'] ?? 0) > 0): ?>
                            <?= formatPrice((float) $mainOrder['invoice_amount']) ?>
                            <span class="text-xs font-normal text-emerald-700">(en pedido <a href="<?= e(url('/pedidos-proveedor/' . (int) $mainOrder['id'])) ?>" class="underline"><?= e((string) $mainOrder['order_number']) ?></a>)</span>
                        <?php elseif ($invoiceAmount > 0): ?>
                            <?= formatPrice($invoiceAmount) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($companionOrders !== []): ?>
                <div class="pt-2 border-t border-emerald-200">
                    <p class="text-xs text-emerald-700 mb-1">Esta factura también cubre los pedidos:</p>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        <?php foreach ($companionOrders as $c): ?>
                            <li>
                                <a href="<?= e(url('/pedidos-proveedor/' . (int) $c['id'])) ?>" class="font-mono px-2 py-0.5 rounded bg-white border border-emerald-300 text-emerald-900 hover:bg-emerald-100"><?= e((string) $c['order_number']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($mainOrder !== null): ?>
                <div class="pt-2 border-t border-emerald-200 text-xs text-emerald-800">
                    Recibido junto con el pedido principal
                    <a href="<?= e(url('/pedidos-proveedor/' . (int) $mainOrder['id'])) ?>" class="font-mono underline"><?= e((string) $mainOrder['order_number']) ?></a>
                    <?php if ($siblingOrders !== []): ?>
                        y también con:
                        <?php foreach ($siblingOrders as $i => $sb): ?>
                            <a href="<?= e(url('/pedidos-proveedor/' . (int) $sb['id'])) ?>" class="font-mono underline"><?= e((string) $sb['order_number']) ?></a><?= $i < count($siblingOrders) - 1 ? ',' : '' ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ((string) ($order['status'] ?? '') === 'draft'): ?>
    <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/delete')) ?>"
          onsubmit="return confirm('¿Eliminar este pedido? Los presupuestos asociados volverán a estar disponibles para nuevos pedidos.')">
        <?= csrfField() ?>
        <button type="submit" class="px-3 py-1 rounded-lg text-xs border border-red-300 text-red-700 hover:bg-red-50">Eliminar pedido</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($includedQuotes)): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Presupuestos incluidos</h3>
            <ul class="space-y-1 text-sm">
                <?php foreach ($includedQuotes as $q): ?>
                    <li>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-[#1565C0] hover:underline font-mono"><?= e($q['quote_number']) ?></a>
                        — <?= e($q['client_name'] ?? '—') ?>
                        <span class="ml-1 align-middle inline-block">
                            <?php $status = (string) ($q['status'] ?? ''); ?>
                            <?php include APP_PATH . '/Views/components/status_badge.php'; ?>
                        </span>
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

    <?php if ($orderStatus === 'sent'): ?>
        <?php
        $modalItems = array_values(array_filter($items, static fn ($r) => (int) ($r['boxes_to_order'] ?? 0) > 0));
        ?>
        <div x-data="receiveSupplierOrderModal({
                orderId: <?= (int) $order['id'] ?>,
                companionsUrl: '<?= e(url('/api/pedidos-proveedor/' . (int) $order['id'] . '/companions')) ?>',
                suggestedAmount: '<?= e(number_format($suggestedReceivedAmount, 2, ',', '.')) ?>'
             })"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
             @keydown.escape.window="open = false"
             @abrir-recepcion-pedido.window="openModal()">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl my-8" @click.away="open = false">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i data-lucide="package-check" class="w-5 h-5 text-[#1a6b3c]"></i>
                        Recibir pedido <?= e((string) $order['order_number']) ?>
                    </h3>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="post" action="<?= e(url('/pedidos-proveedor/' . (int) $order['id'] . '/recibir')) ?>" class="p-6 space-y-5">
                    <?= csrfField() ?>

                    <section>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">1. Detalle del pedido</h4>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="max-h-44 overflow-y-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-600 uppercase tracking-wide">
                                        <tr>
                                            <th class="text-left px-3 py-2">Código</th>
                                            <th class="text-left px-3 py-2">Producto</th>
                                            <th class="text-right px-3 py-2">Cajas</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($modalItems as $mi): ?>
                                            <tr>
                                                <td class="px-3 py-1.5 font-mono"><?= e((string) ($mi['code'] ?? '')) ?></td>
                                                <td class="px-3 py-1.5"><?= e((string) ($mi['product_name'] ?? '')) ?></td>
                                                <td class="px-3 py-1.5 text-right font-medium"><?= (int) ($mi['boxes_to_order'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="bg-gray-50 px-3 py-2 text-xs text-gray-700 border-t border-gray-200 flex items-center justify-between">
                                <span>Monto calculado por el sistema (referencia):</span>
                                <span class="font-semibold text-gray-900"><?= formatPrice($suggestedReceivedAmount) ?></span>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-700">2. Datos de la factura del proveedor</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">N° factura (opcional)</label>
                                <input type="text" name="invoice_number" x-model="invoiceNumber"
                                       placeholder="C-0001-00007632"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">Monto real factura <span class="text-red-600">*</span></label>
                                <input type="text" name="invoice_amount" x-model="invoiceAmount" required
                                       inputmode="decimal"
                                       placeholder="0,00"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <p class="text-[11px] text-gray-500 mt-1">Editá si difiere del calculado</p>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">Fecha factura</label>
                                <input type="date" name="invoice_date" x-model="invoiceDate"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-700">3. Recepción conjunta</h4>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
                            <input type="checkbox" x-model="showCompanions" @change="onTogglePartners()"
                                   class="rounded border-gray-300 text-[#1a6b3c] focus:ring-[#1a6b3c]">
                            <span>Esta factura cubre otros pedidos además de este</span>
                        </label>
                        <div x-show="showCompanions" x-cloak class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                            <p x-show="loadingCompanions" class="text-xs text-gray-500">Cargando pedidos enviados…</p>
                            <p x-show="!loadingCompanions && companions.length === 0" class="text-xs text-gray-500">No hay otros pedidos en estado <strong>sent</strong> para este proveedor.</p>
                            <template x-if="!loadingCompanions && companions.length > 0">
                                <div class="space-y-2 max-h-44 overflow-y-auto">
                                    <template x-for="c in companions" :key="c.id">
                                        <label class="flex items-center justify-between gap-3 bg-white border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <input type="checkbox" name="companion_orders[]" :value="c.id"
                                                       x-model="selectedCompanions"
                                                       class="rounded border-gray-300 text-[#1a6b3c] focus:ring-[#1a6b3c]">
                                                <div class="min-w-0">
                                                    <div class="font-mono text-sm" x-text="c.order_number"></div>
                                                    <div class="text-[11px] text-gray-500">
                                                        <span x-text="c.total_products"></span> productos ·
                                                        <span x-text="c.total_boxes"></span> cajas
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-600 whitespace-nowrap">
                                                Calc: $<span x-text="c.suggested_amount_formatted"></span>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </template>
                            <p x-show="showCompanions && selectedCompanions.length > 0" class="text-[11px] text-amber-700 mt-2">
                                <strong x-text="selectedCompanions.length + 1"></strong> pedidos se marcarán como recibidos y se aplicará stock a todos. Solo se generará <strong>un registro</strong> en cuenta corriente con el monto de la factura.
                            </p>
                        </div>
                    </section>

                    <section class="flex gap-3 pt-2 border-t border-gray-200">
                        <button type="button" @click="open = false" class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="flex-1 py-2 px-4 bg-[#1a6b3c] text-white rounded-lg text-sm font-medium hover:bg-[#155a31]">Confirmar recepción</button>
                    </section>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
function enviarPedidoWhatsApp() {
    const text = <?= json_encode($waMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}

function receiveSupplierOrderModal(config) {
    return {
        open: false,
        orderId: config.orderId,
        companionsUrl: config.companionsUrl,
        invoiceNumber: '',
        invoiceAmount: config.suggestedAmount || '',
        invoiceDate: new Date().toISOString().slice(0, 10),
        showCompanions: false,
        loadingCompanions: false,
        companions: [],
        selectedCompanions: [],
        openModal() {
            this.open = true;
            this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
        },
        async onTogglePartners() {
            if (!this.showCompanions) {
                this.selectedCompanions = [];
                return;
            }
            if (this.companions.length > 0) {
                return;
            }
            this.loadingCompanions = true;
            try {
                const res = await fetch(this.companionsUrl, { credentials: 'same-origin' });
                const data = await res.json();
                this.companions = Array.isArray(data.orders) ? data.orders : [];
            } catch (e) {
                this.companions = [];
            } finally {
                this.loadingCompanions = false;
            }
        }
    };
}
</script>
