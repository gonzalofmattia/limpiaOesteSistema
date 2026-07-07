<?php
$template = $template ?? null;
$businessTypeLabels = $businessTypeLabels ?? [];
$stageLabels = $stageLabels ?? [];
$isEdit = $template !== null;
$action = $isEdit ? url('/prospeccion/plantillas/' . (int) $template['id']) : url('/prospeccion/plantillas');
$templateBodyJson = json_encode(
    (string) ($template['body'] ?? ''),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?: '""';
$xData = "{ body: {$templateBodyJson}, get preview() { return this.body.replaceAll('{{nombre}}', 'Parrilla Don José').replaceAll('{{ciudad}}', 'Luján'); } }";
$xDataAttr = htmlspecialchars($xData, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 max-w-4xl" x-data="<?= $xDataAttr ?>">
    <form method="post" action="<?= e($action) ?>" class="lo-card p-5 space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la plantilla</label>
            <input type="text" name="name" required value="<?= e((string) ($template['name'] ?? '')) ?>" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Rubro</label>
                <select name="business_type" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <?php foreach ($businessTypeLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($template['business_type'] ?? 'todos') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Etapa</label>
                <select name="stage" required class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <option value="">Elegir etapa...</option>
                    <?php foreach ($stageLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($template['stage'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Mensaje</label>
            <textarea name="body" x-model="body" rows="8" required placeholder="Hola, te escribo de Limpia Oeste... Variables: {{nombre}} {{ciudad}}" class="w-full rounded-xl border border-lo-border px-3 py-2 text-sm font-mono"></textarea>
            <p class="text-xs text-slate-400 mt-1">Variables disponibles: <code>{{nombre}}</code>, <code>{{ciudad}}</code></p>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="active" value="1" <?= (($template['active'] ?? 1)) ? 'checked' : '' ?> class="rounded border-slate-300">
            Plantilla activa
        </label>
        <div class="flex justify-end gap-2 pt-2">
            <a href="<?= e(url('/prospeccion/plantillas')) ?>" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-slate-600">Cancelar</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"><?= $isEdit ? 'Guardar cambios' : 'Crear plantilla' ?></button>
        </div>
    </form>

    <div class="lo-card p-5">
        <p class="text-sm font-semibold text-slate-800 mb-3">Vista previa (datos de ejemplo)</p>
        <div class="rounded-xl bg-[#DCF8C6] p-4 text-sm text-slate-800 whitespace-pre-line min-h-[10rem]" x-text="preview || 'Escribí el mensaje para ver la vista previa...'"></div>
    </div>
</div>
