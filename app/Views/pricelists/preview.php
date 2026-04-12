<?php
use App\Helpers\PricingEngine;
$b = $bundle;
$grouped = [];
foreach ($b['lines'] as $line) {
    $cn = $line['product']['category_name'];
    $grouped[$cn][] = $line;
}
?>
<div class="mb-6 flex flex-wrap gap-3 justify-between items-center">
    <div>
        <h2 class="text-lg font-semibold text-gray-900"><?= e($b['name']) ?></h2>
        <p class="text-sm text-gray-500"><?= $b['include_iva'] ? 'Precios con IVA' : 'Precios sin IVA' ?> · Markup lista: <?= $b['markup'] !== null ? formatPercent($b['markup']) : 'según producto/categoría' ?></p>
    </div>
    <form method="post" action="<?= e(url('/listas')) ?>" class="flex gap-2">
        <?= csrfField() ?>
        <input type="hidden" name="name" value="<?= e($b['name']) ?>">
        <input type="hidden" name="description" value="<?= e($b['description']) ?>">
        <input type="hidden" name="custom_markup" value="<?= e($b['markup'] !== null ? (string) $b['markup'] : '') ?>">
        <input type="hidden" name="include_iva" value="<?= $b['include_iva'] ? '1' : '0' ?>">
        <input type="hidden" name="price_field" value="<?= e($b['price_field']) ?>">
        <?php foreach ($b['category_ids'] as $cid): ?>
            <input type="hidden" name="category_ids[]" value="<?= (int) $cid ?>">
        <?php endforeach; ?>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar PDF y guardar</button>
        <a href="<?= e(url('/listas/generar')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm inline-flex items-center">Volver</a>
    </form>
</div>

<?php foreach ($grouped as $catName => $lines): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 font-semibold text-sm text-gray-800"><?= e($catName) ?></div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-2">Código</th>
                    <th class="text-left px-4 py-2">Producto</th>
                    <th class="text-left px-4 py-2">Presentación</th>
                    <th class="text-right px-4 py-2">P. unitario</th>
                    <th class="text-right px-4 py-2">Precio venta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($lines as $line): ?>
                    <?php $calc = $line['calc']; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-xs"><?= e($line['product']['code']) ?></td>
                        <td class="px-4 py-2"><?= e($line['product']['name']) ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= e($line['product']['presentation'] ?? $line['product']['content'] ?? '—') ?></td>
                        <td class="px-4 py-2 text-right text-gray-600"><?= formatPrice((float) ($line['individual_venta'] ?? 0)) ?></td>
                        <td class="px-4 py-2 text-right font-medium">
                            <?= formatPrice($b['include_iva'] && $calc['precio_con_iva'] !== null ? $calc['precio_con_iva'] : $calc['precio_venta']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<p class="text-xs text-gray-500 mt-4"><?= e(priceIvaLegendLine(!empty($b['include_iva']))) ?></p>
