<?php
$businessTypeLabels = $businessTypeLabels ?? [];
$statusLabels = $statusLabels ?? [];
?>
<div class="max-w-2xl">
    <form method="post" action="<?= e(url('/prospeccion/campanas')) ?>" class="lo-card p-5 space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la campaña</label>
            <input type="text" name="name" required class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 text-xs text-slate-600">
            El mensaje de primer contacto se arma solo, según el rubro de cada prospecto
            (plantillas en <a href="<?= e(url('/prospeccion/plantillas')) ?>" class="underline">Plantillas</a>).
            No hace falta elegir una acá — si el rubro no tiene plantilla propia o no está
            cargado, usa un mensaje genérico.
        </div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 pt-1">Filtro de prospectos</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Rubro</label>
                <select name="filter_business_type" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <option value="">Cualquiera</option>
                    <?php foreach ($businessTypeLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ciudad</label>
                <input type="text" name="filter_city" placeholder="Cualquiera" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Estado del prospecto</label>
                <select name="filter_status" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $key === 'nuevo' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tope diario de esta campaña</label>
            <input type="number" name="daily_limit" value="20" min="1" max="25" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
            <p class="text-xs text-slate-400 mt-1">Se reparte con las demás campañas activas sin superar el tope global diario.</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="<?= e(url('/prospeccion/campanas')) ?>" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-slate-600">Cancelar</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Crear campaña (borrador)</button>
        </div>
    </form>
</div>
