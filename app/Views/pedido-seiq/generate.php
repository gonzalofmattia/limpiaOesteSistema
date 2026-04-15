<?php
/** @var list<array<string,mixed>> $acceptedQuotes */
/** @var array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int} $bundle */
/** @var list<array{supplier: array<string,mixed>, bundle: array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int}}> $supplierBundles */
?>
<div class="max-w-6xl space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Generar pedidos a proveedores</h2>
        <p class="text-sm text-gray-600 mt-1">Se consolidan todos los presupuestos en estado <strong>accepted</strong>, se agrupan por proveedor y se redondea a cajas completas.</p>
    </div>

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

    <?php foreach ($supplierBundles as $supplierBundle): ?>
    <?php $supplier = $supplierBundle['supplier']; $rows = $supplierBundle['bundle']['consolidated']; ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Pedido a <?= e((string) $supplier['name']) ?></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-3 py-2">Categoría</th>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-left px-3 py-2">Vendido</th>
                        <th class="text-left px-3 py-2">Pedir</th>
                        <th class="text-left px-3 py-2">Remanente</th>
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
                        $pedir = (int) $r['boxes_to_order'] . ' ' . $pack . ' (×' . (int) $r['units_per_box'] . ' u.)';
                        ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-3 py-2 align-top text-gray-600 whitespace-nowrap">
                                <?= e($group) ?>
                                <?php if (!empty($r['subcategory'])): ?>
                                    <span class="text-gray-400">›</span> <?= e((string) $r['subcategory']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top font-mono text-xs"><?= e($r['code']) ?></td>
                            <td class="px-3 py-2 align-top"><?= e($r['name']) ?></td>
                            <td class="px-3 py-2 align-top text-gray-800">
                                <div><?= e($vendidoBody) ?></div>
                                <div class="text-xs text-gray-500">= <?= (int) $r['total_units_needed'] ?> un. totales</div>
                            </td>
                            <td class="px-3 py-2 align-top font-medium text-[#1a6b3c]"><?= e($pedir) ?></td>
                            <td class="px-3 py-2 align-top"><?= e(seiqRemainderLabel($r)) ?></td>
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

    <form method="post" action="<?= e(url('/pedidos-proveedor')) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]" placeholder="Observaciones internas del pedido"></textarea>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar pedidos y PDFs (<?= count($supplierBundles) ?>)</button>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Volver</a>
        </div>
    </form>
</div>
