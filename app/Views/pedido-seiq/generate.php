<?php
/** @var list<array<string,mixed>> $acceptedQuotes */
/** @var array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int} $bundle */
/** @var list<array{supplier: array<string,mixed>, bundle: array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int}}> $supplierBundles */
?>
<div class="max-w-6xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <p class="text-sm font-medium text-gray-800">Presupuestos aceptados incluidos: <?= count($acceptedQuotes) ?></p>
        <ul class="mt-2 space-y-1 text-sm text-gray-700 list-disc list-inside">
            <?php foreach ($acceptedQuotes as $q): ?>
                <li>
                    <span class="font-mono"><?= e($q['quote_number']) ?></span>
                    — <?= e($q['client_name'] ?? '—') ?>
                    (<span class="whitespace-nowrap"><?= formatPrice((float) $q['total']) ?></span>)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="post" action="<?= e(url('/pedidos-proveedor')) ?>" class="space-y-6">
        <?= csrfField() ?>
    <?php foreach ($supplierBundles as $supplierBundle): ?>
    <?php $supplier = $supplierBundle['supplier']; $rows = $supplierBundle['bundle']['consolidated']; ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Pedido a <?= e((string) $supplier['name']) ?></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs table-auto lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-2 py-2">Cód.</th>
                        <th class="text-left px-2 py-2 w-[28%]">Producto</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Vendido</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Stock</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Comp.</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Disp.</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Falt.</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Pedir</th>
                        <th class="text-left px-2 py-2 whitespace-nowrap">Rem.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r):
                        $group = (string) ($r['sort_group'] ?? '');
                        $pack = strtolower(trim((string) ($r['sale_unit_label'] ?? ''))) ?: 'caja';
                        $qb = (int) $r['qty_boxes_sold'];
                        $qu = (int) $r['qty_units_sold'];
                        $vendidoLines = [];
                        if ($qb > 0) {
                            $vendidoLines[] = $qb . ' ' . $pack . ($qb !== 1 ? '' : '');
                        }
                        if ($qu > 0) {
                            $vendidoLines[] = $qu . ' un.';
                        }
                        $vendidoBody = implode(' + ', $vendidoLines);
                        $productId = (int) ($r['product_id'] ?? 0);
                        $stockUnits = max(0, (int) ($r['stock_units'] ?? 0));
                        $committedUnits = max(0, (int) ($r['stock_committed_units'] ?? 0));
                        $stockAvailable = (int) ($r['stock_available_units'] ?? max(0, $stockUnits - $committedUnits));
                        $shortageUnits = max(0, (int) ($r['units_to_order_after_stock'] ?? $r['total_units_needed'] ?? 0));
                        $defaultBoxes = max(0, (int) ($r['boxes_to_order'] ?? 0));
                        ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-2 py-2 align-top font-mono text-xs whitespace-nowrap"><?= e($r['code']) ?></td>
                            <td class="px-2 py-2 align-top"><span class="block truncate max-w-[220px]" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></span></td>
                            <td class="px-2 py-2 align-top text-gray-800 whitespace-nowrap">
                                <div><?= e($vendidoBody) ?></div>
                                <div class="text-[11px] text-gray-500">= <?= (int) $r['total_units_needed'] ?></div>
                                <?php $demandDetails = is_array($r['demand_details'] ?? null) ? $r['demand_details'] : []; ?>
                                <?php if ($demandDetails !== []): ?>
                                    <details class="mt-1">
                                        <summary class="text-xs text-[#1a6b3c] cursor-pointer">Ver presupuestos que lo demandan</summary>
                                        <ul class="mt-1 space-y-1 text-xs text-gray-600">
                                            <?php foreach ($demandDetails as $d): ?>
                                                <li>
                                                    <span class="font-mono"><?= e((string) ($d['quote_number'] ?? ('#' . (int) ($d['quote_id'] ?? 0)))) ?></span>
                                                    — <?= e((string) ($d['client_name'] ?? '—')) ?>
                                                    — <?= (int) ($d['units'] ?? 0) ?>
                                                    <?php if (($d['source_type'] ?? '') === 'combo'): ?>
                                                        (combo: <?= e((string) ($d['combo_name'] ?? 'sin nombre')) ?>)
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 align-top text-gray-700 whitespace-nowrap"><?= (int) $stockUnits ?></td>
                            <td class="px-2 py-2 align-top whitespace-nowrap <?= $committedUnits > 0 ? 'text-amber-600 font-medium' : 'text-gray-500' ?>"><?= (int) $committedUnits ?></td>
                            <td class="px-2 py-2 align-top font-semibold whitespace-nowrap <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= (int) $stockAvailable ?></td>
                            <td class="px-2 py-2 align-top text-gray-700 whitespace-nowrap"><?= (int) $shortageUnits ?></td>
                            <td class="px-2 py-2 align-top whitespace-nowrap">
                                <label class="sr-only" for="boxes_to_order_<?= $productId ?>">Cajas a pedir</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        id="boxes_to_order_<?= $productId ?>"
                                        type="number"
                                        min="0"
                                        step="1"
                                        name="boxes_to_order[<?= $productId ?>]"
                                        value="<?= (int) $defaultBoxes ?>"
                                        class="w-20 border border-gray-300 rounded-lg px-2 py-1 text-xs font-medium text-[#1a6b3c] focus:ring-2 focus:ring-[#1a6b3c]"
                                    >
                                    <span class="text-[11px] text-gray-500"><?= e($pack) ?> (×<?= (int) $r['units_per_box'] ?>)</span>
                                </div>
                            </td>
                            <td class="px-2 py-2 align-top whitespace-nowrap"><?= e(seiqRemainderLabel($r)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 text-sm text-gray-700">
            Subtotal <?= e((string) $supplier['name']) ?>: <strong><?= (int) $supplierBundle['bundle']['total_boxes'] ?></strong> cajas/packs
        </div>
    </div>
    <?php endforeach; ?>

    <p class="text-sm text-gray-700">
        <strong>Total:</strong> <?= (int) $bundle['total_products'] ?> productos distintos —
        <strong><?= (int) $bundle['total_boxes'] ?></strong> cajas/packs a pedir
    </p>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]" placeholder="Observaciones internas del pedido"></textarea>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar pedidos y PDFs (<?= count($supplierBundles) ?>)</button>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Volver</a>
        </div>
    </div>
    </form>
</div>
