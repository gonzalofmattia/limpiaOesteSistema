<?php
$b = $bundle;
$pdfSections = $b['pdf_sections'] ?? [];
$isMinoristaLayout = !empty($is_minorista_layout ?? false);
?>
<div class="mb-6 flex flex-wrap gap-3 justify-between items-center">
    <div>
        <h2 class="text-lg font-semibold text-gray-900"><?= e($b['name']) ?></h2>
        <p class="text-sm text-gray-500"><?= ((int) ($b['include_iva'] ?? 0) === 1) ? 'Precios con IVA' : 'Precios sin IVA' ?> · Markup lista: <?= $b['markup'] !== null ? formatPercent($b['markup']) : 'según producto/categoría' ?></p>
    </div>
    <form method="post" action="<?= e(url('/listas')) ?>" class="flex gap-2">
        <?= csrfField() ?>
        <input type="hidden" name="name" value="<?= e($b['name']) ?>">
        <input type="hidden" name="description" value="<?= e($b['description']) ?>">
        <input type="hidden" name="custom_markup" value="<?= e($b['markup'] !== null ? (string) $b['markup'] : '') ?>">
        <input type="hidden" name="include_iva" value="<?= ((int) ($b['include_iva'] ?? 0) === 1) ? '1' : '0' ?>">
        <input type="hidden" name="price_field" value="<?= e($b['price_field']) ?>">
        <input type="hidden" name="supplier" value="<?= e((string) ($b['supplier'] ?? '')) ?>">
        <input type="hidden" name="list_type" value="<?= e((string) ($b['list_type'] ?? '')) ?>">
        <?php foreach ($b['category_ids'] as $cid): ?>
            <input type="hidden" name="category_ids[]" value="<?= (int) $cid ?>">
        <?php endforeach; ?>
        <?php foreach (($b['product_ids'] ?? []) as $pid): ?>
            <input type="hidden" name="product_ids[]" value="<?= (int) $pid ?>">
        <?php endforeach; ?>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar PDF y guardar</button>
        <a href="<?= e(url('/listas/generar')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm inline-flex items-center">Volver</a>
    </form>
</div>

<?php foreach ($pdfSections as $sec): ?>
    <div class="mb-4 text-sm font-semibold text-gray-800 uppercase tracking-wide border-b border-gray-200 pb-1"><?= e($sec['parent']) ?></div>
    <?php foreach ($sec['blocks'] as $block): ?>
        <?php if (!empty($block['subtitle'])): ?>
            <p class="text-xs font-semibold text-gray-600 mb-2 pl-1"><?= e($block['subtitle']) ?></p>
        <?php endif; ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <?php if ($isMinoristaLayout): ?>
                            <th class="text-left px-4 py-2">Producto</th>
                            <th class="text-left px-4 py-2">Presentación</th>
                            <th class="text-right px-4 py-2">Precio</th>
                        <?php else: ?>
                            <th class="text-left px-4 py-2">Código</th>
                            <th class="text-left px-4 py-2">Producto</th>
                            <th class="text-left px-4 py-2">Presentación</th>
                            <th class="text-right px-4 py-2">P. unitario</th>
                            <th class="text-right px-4 py-2">Precio caja/bulto</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($block['lines'] as $line): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($isMinoristaLayout): ?>
                                <td class="px-4 py-2"><?= e($line['product']['name']) ?></td>
                                <td class="px-4 py-2 text-gray-600"><?= e(productMinoristaPresentation($line['product'])) ?></td>
                                <td class="px-4 py-2 text-right font-medium"><?= formatPrice((float) ($line['individual_venta'] ?? 0)) ?></td>
                            <?php else: ?>
                                <td class="px-4 py-2 font-mono text-xs"><?= e($line['product']['code']) ?></td>
                                <td class="px-4 py-2"><?= e($line['product']['name']) ?></td>
                                <td class="px-4 py-2 text-gray-600"><?= e(productListPresentation($line['product'])) ?></td>
                                <td class="px-4 py-2 text-right text-gray-600"><?= formatPrice((float) ($line['individual_venta'] ?? 0)) ?></td>
                                <td class="px-4 py-2 text-right font-medium">
                                    <?= formatPrice((float) ($line['pack_venta'] ?? 0)) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<p class="text-xs text-gray-500 mt-4"><?= e(priceIvaLegendLine((int) ($b['include_iva'] ?? 0) === 1)) ?></p>
