<?php
$isEdit = $client !== null;
$action = url($isEdit ? '/clientes/' . (int) $client['id'] : '/clientes');
$c = $client ?? [];
?>
<div class="max-w-2xl bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <form method="post" action="<?= e($action) ?>" class="space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="name" required value="<?= e($c['name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Razón social</label>
            <input type="text" name="business_name" value="<?= e($c['business_name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contacto</label>
                <input type="text" name="contact_person" value="<?= e($c['contact_person'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" name="phone" value="<?= e($c['phone'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <textarea name="address" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($c['address'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
            <input type="text" name="city" value="<?= e($c['city'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($c['notes'] ?? '') ?></textarea>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" <?= !isset($c['is_active']) || $c['is_active'] ? 'checked' : '' ?>> Activo
        </label>
        <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar</button>
            <a href="<?= e(url('/clientes')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
