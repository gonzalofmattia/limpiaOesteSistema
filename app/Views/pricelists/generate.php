<?php
use App\Helpers\PricingEngine;
$allFields = [
    'precio_lista_unitario', 'precio_lista_caja', 'precio_lista_bidon',
    'precio_lista_litro', 'precio_lista_bulto', 'precio_lista_sobre',
];
?>
<div class="max-w-3xl bg-white rounded-xl border border-gray-200 shadow-sm p-6" x-data="{
    supplier: '',
    toggleChildren(rootId, checked) {
        document.querySelectorAll('input[type=checkbox][data-pl-parent=\'' + rootId + '\']').forEach(function (el) { el.checked = checked; });
    },
    isVisible(nodeSupplier) {
        return this.supplier === '' || this.supplier === nodeSupplier;
    }
}">
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
            <select name="supplier" x-model="supplier" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos los proveedores</option>
                <?php foreach (($suppliers ?? []) as $s): ?>
                    <option value="<?= e((string) $s['slug']) ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Categorías a incluir</p>
            <div class="space-y-2 text-sm border border-gray-100 rounded-lg p-3 bg-gray-50/50">
                <?php foreach ($categoryTree as $root): ?>
                    <?php $rootSupplier = (string) ($root['supplier_slug'] ?? ''); ?>
                    <div class="pl-0" x-show="isVisible('<?= e($rootSupplier) ?>')">
                        <label class="inline-flex items-center gap-2 font-medium text-gray-800">
                            <input type="checkbox" name="category_ids[]" value="<?= (int) $root['id'] ?>" checked
                                   @change="toggleChildren(<?= (int) $root['id'] ?>, $event.target.checked)">
                            <?= e($root['name']) ?>
                            <?php if (!empty($root['supplier_name'])): ?>
                                <span class="text-xs text-gray-500">(<?= e((string) $root['supplier_name']) ?>)</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($root['children'] as $ch): ?>
                            <label class="flex items-center gap-2 ml-6 text-gray-700" x-show="isVisible('<?= e((string) ($ch['supplier_slug'] ?? $rootSupplier)) ?>')">
                                <input type="checkbox" name="category_ids[]" value="<?= (int) $ch['id'] ?>" checked
                                       data-pl-parent="<?= (int) $root['id'] ?>">
                                <?= e($ch['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-500 mt-1">Al marcar o desmarcar una categoría principal se actualizan sus subcategorías; podés ajustar cada subcategoría por separado.</p>
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
