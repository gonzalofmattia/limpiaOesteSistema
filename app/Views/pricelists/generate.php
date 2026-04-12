<?php
use App\Helpers\PricingEngine;
$allFields = [
    'precio_lista_unitario', 'precio_lista_caja', 'precio_lista_bidon',
    'precio_lista_litro', 'precio_lista_bulto', 'precio_lista_sobre',
];
?>
<div class="max-w-3xl bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <form method="post" action="<?= e(url('/listas/preview')) ?>" class="space-y-6">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la lista</label>
            <input type="text" name="name" required placeholder="Lista Mayorista Abril 2026"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
            <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Categorías a incluir</p>
            <div class="grid sm:grid-cols-2 gap-2">
                <?php foreach ($categories as $c): ?>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" checked>
                        <?= e($c['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup para esta lista (%)</label>
                <input type="text" name="custom_markup" placeholder="<?= e(setting('default_markup', '60')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">Vacío = usa reglas de producto/categoría/global.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Campo de precio base</label>
                <select name="price_field" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($allFields as $f): ?>
                        <option value="<?= e($f) ?>"><?= e(PricingEngine::priceFieldLabel($f)) ?> (<?= e($f) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Si un producto no tiene ese precio, se usa el principal de su categoría.</p>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_iva" value="1"> Incluir IVA en precio mostrado
        </label>
        <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1565C0] text-white text-sm font-medium">Vista previa</button>
            <a href="<?= e(url('/listas')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
