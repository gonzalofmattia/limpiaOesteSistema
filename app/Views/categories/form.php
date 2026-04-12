<?php
$isEdit = $category !== null;
$action = url($isEdit ? '/categorias/' . (int) $category['id'] : '/categorias');
$c = $category ?? [];
?>
<div class="max-w-2xl">
    <form method="post" action="<?= e($action) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="name" required value="<?= e($c['name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]"><?= e($c['description'] ?? '') ?></textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descuento Seiq default (%)</label>
                <input type="text" name="default_discount" value="<?= e((string) ($c['default_discount'] ?? '0')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup default (%)</label>
                <input type="text" name="default_markup" placeholder="Vacío = global <?= e(setting('default_markup', '60')) ?>%"
                       value="<?= $c['default_markup'] !== null && $c['default_markup'] !== '' ? e((string) $c['default_markup']) : '' ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Info presentación</label>
            <input type="text" name="presentation_info" value="<?= e($c['presentation_info'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
            <input type="number" name="sort_order" value="<?= e((string) ($c['sort_order'] ?? 0)) ?>"
                   class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" <?= !isset($c['is_active']) || $c['is_active'] ? 'checked' : '' ?>>
            <label for="is_active" class="text-sm text-gray-700">Categoría activa</label>
        </div>
        <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Guardar</button>
            <a href="<?= e(url('/categorias')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
