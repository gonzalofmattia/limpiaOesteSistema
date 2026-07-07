<?php
$prospect = $prospect ?? null;
$businessTypeLabels = $businessTypeLabels ?? [];
$isEdit = $prospect !== null;
$action = $isEdit ? url('/prospeccion/prospectos/' . (int) $prospect['id']) : url('/prospeccion/prospectos');
?>
<div class="max-w-2xl">
    <form method="post" action="<?= e($action) ?>" class="lo-card p-5 space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
            <input type="text" name="name" required value="<?= e((string) ($prospect['name'] ?? '')) ?>" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Rubro</label>
                <select name="business_type" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <?php foreach ($businessTypeLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($prospect['business_type'] ?? 'otro') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
                <input type="text" name="phone" required placeholder="11 2233 4455" value="<?= e((string) ($prospect['phone'] ?? '')) ?>" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ciudad</label>
                <input type="text" name="city" value="<?= e((string) ($prospect['city'] ?? '')) ?>" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fuente</label>
                <input type="text" name="source" placeholder="Ej: recorrida, referido, redes" value="<?= e((string) ($prospect['source'] ?? '')) ?>" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notas</label>
            <textarea name="notes" rows="3" class="w-full rounded-xl border border-lo-border px-3 py-2 text-sm"><?= e((string) ($prospect['notes'] ?? '')) ?></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="<?= e(url('/prospeccion/prospectos')) ?>" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-slate-600">Cancelar</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"><?= $isEdit ? 'Guardar cambios' : 'Crear prospecto' ?></button>
        </div>
    </form>
</div>
