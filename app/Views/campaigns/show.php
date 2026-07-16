<?php
$campaign = $campaign ?? [];
$dryRun = $dryRun ?? null;
$queueStats = $queueStats ?? null;
$allowedTransitions = $allowedTransitions ?? [];
$transitionLabels = [
    'activa' => ['label' => 'Activar campaña', 'class' => 'bg-green-600 hover:bg-green-700'],
    'pausada' => ['label' => 'Pausar', 'class' => 'bg-amber-600 hover:bg-amber-700'],
    'finalizada' => ['label' => 'Finalizar', 'class' => 'bg-gray-600 hover:bg-gray-700'],
];
?>
<div class="space-y-5">
    <div class="lo-card p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm flex-1">
            <div><dt class="text-slate-500">Filtro</dt><dd class="font-medium text-slate-800"><?= e((string) ($campaign['filter_business_type'] ?? 'Cualquier rubro')) ?> · <?= e((string) ($campaign['filter_city'] ?? 'Cualquier ciudad')) ?></dd></div>
            <div><dt class="text-slate-500">Estado prospecto</dt><dd class="font-medium text-slate-800"><?= e((string) $campaign['filter_status']) ?></dd></div>
            <div><dt class="text-slate-500">Tope diario</dt><dd class="font-medium text-slate-800"><?= (int) $campaign['daily_limit'] ?></dd></div>
        </dl>
        <div class="flex gap-2 flex-wrap">
            <?php foreach ($allowedTransitions as $target): ?>
                <?php $meta = $transitionLabels[$target] ?? ['label' => $target, 'class' => 'bg-slate-600 hover:bg-slate-700']; ?>
                <form method="post" action="<?= e(url('/prospeccion/campanas/' . (int) $campaign['id'] . '/estado')) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="status" value="<?= e($target) ?>">
                    <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-semibold text-white <?= e($meta['class']) ?>"><?= e($meta['label']) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($dryRun !== null): ?>
        <div class="lo-card p-5">
            <p class="text-sm font-semibold text-slate-800 mb-1">Dry-run (todavía no se envió nada)</p>
            <p class="text-sm text-slate-600 mb-4">
                Matchean <strong><?= (int) $dryRun['count'] ?></strong> prospectos.
                <?php if ($dryRun['count'] > 0): ?>
                    Al tope diario de <?= (int) $campaign['daily_limit'] ?>, tardaría aproximadamente
                    <strong><?= (int) $dryRun['projected_days'] ?> día<?= (int) $dryRun['projected_days'] === 1 ? '' : 's' ?></strong> en contactarlos a todos.
                <?php endif; ?>
            </p>
            <?php if ($dryRun['sample'] !== []): ?>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Primeros <?= count($dryRun['sample']) ?> mensajes</p>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($dryRun['sample'] as $item): ?>
                        <div class="rounded-xl border border-slate-100 p-3">
                            <p class="text-sm font-medium text-slate-800"><?= e((string) $item['prospect']['name']) ?> <span class="text-slate-400 font-normal">· <?= e((string) $item['prospect']['phone']) ?></span></p>
                            <p class="text-sm text-slate-600 whitespace-pre-line mt-1"><?= e($item['rendered_body']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">No hay prospectos que matcheen este filtro ahora mismo.</p>
            <?php endif; ?>
        </div>
    <?php elseif ($queueStats !== null): ?>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="lo-card p-4"><p class="text-xs text-slate-500">Enviados (total)</p><p class="text-2xl font-semibold"><?= (int) ($queueStats['sent'] ?? 0) ?></p></div>
            <div class="lo-card p-4"><p class="text-xs text-slate-500">Enviados hoy</p><p class="text-2xl font-semibold"><?= (int) ($queueStats['sent_today'] ?? 0) ?></p></div>
            <div class="lo-card p-4"><p class="text-xs text-slate-500">Pendientes</p><p class="text-2xl font-semibold"><?= (int) ($queueStats['pending'] ?? 0) ?></p></div>
            <div class="lo-card p-4"><p class="text-xs text-slate-500">Fallidos</p><p class="text-2xl font-semibold text-red-600"><?= (int) ($queueStats['failed'] ?? 0) ?></p></div>
            <div class="lo-card p-4"><p class="text-xs text-slate-500">Destinatarios restantes</p><p class="text-2xl font-semibold"><?= (int) ($queueStats['remaining_matching'] ?? 0) ?></p></div>
        </div>
        <?php if ((int) ($queueStats['remaining_matching'] ?? 0) === 0): ?>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-800">
                No quedan prospectos nuevos que matcheen el filtro de esta campaña (rubro <?= e((string) ($campaign['filter_business_type'] ?? 'cualquiera')) ?> · ciudad <?= e((string) ($campaign['filter_city'] ?? 'cualquiera')) ?>). Si esperabas que quedaran más, revisá si el filtro es demasiado angosto (por ejemplo, una ciudad escrita distinto a como está cargada en los prospectos).
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
