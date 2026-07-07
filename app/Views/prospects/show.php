<?php
$prospect = $prospect ?? [];
$events = $events ?? [];
$statuses = $statuses ?? [];
$statusLabels = $statusLabels ?? [];
$businessTypeLabels = $businessTypeLabels ?? [];
$eventLabel = static function (string $type): string {
    return match ($type) {
        'creado' => 'Alta',
        'estado_cambiado' => 'Cambio de estado',
        'nota' => 'Nota',
        default => ucfirst($type),
    };
};
$eventIcon = static function (string $type): string {
    return match ($type) {
        'creado' => 'plus-circle',
        'estado_cambiado' => 'arrow-right-circle',
        'nota' => 'message-square',
        default => 'circle',
    };
};
$fmtFecha = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);
    return $t === false ? '—' : date('d/m/Y H:i', $t);
};
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-1 space-y-5">
        <div class="lo-card p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900"><?= e((string) $prospect['name']) ?></h2>
                    <p class="text-sm text-slate-500"><?= e($businessTypeLabels[$prospect['business_type']] ?? $prospect['business_type']) ?></p>
                </div>
                <a href="<?= e(url('/prospeccion/prospectos/' . (int) $prospect['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700" title="Editar"><i data-lucide="pencil" class="h-4 w-4"></i></a>
            </div>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-500">Teléfono</dt><dd class="text-slate-800"><?= e((string) $prospect['phone']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Ciudad</dt><dd class="text-slate-800"><?= e((string) ($prospect['city'] ?? '—')) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Fuente</dt><dd class="text-slate-800"><?= e((string) ($prospect['source'] ?? '—')) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Intentos de contacto</dt><dd class="text-slate-800"><?= (int) $prospect['contact_attempts'] ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Último contacto</dt><dd class="text-slate-800"><?= e($fmtFecha($prospect['last_contacted_at'] ?? null)) ?></dd></div>
                <?php if (!empty($prospect['client_name'])): ?>
                    <div class="flex justify-between"><dt class="text-slate-500">Cliente vinculado</dt><dd class="text-slate-800"><?= e((string) $prospect['client_name']) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($prospect['notes'])): ?>
                    <div class="pt-2 border-t border-slate-100">
                        <dt class="text-slate-500 mb-1">Notas</dt>
                        <dd class="text-slate-700 whitespace-pre-line"><?= e((string) $prospect['notes']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="lo-card p-5">
            <p class="text-sm font-semibold text-slate-800 mb-3">Cambiar estado</p>
            <form method="post" action="<?= e(url('/prospeccion/prospectos/' . (int) $prospect['id'] . '/estado')) ?>" class="flex gap-2">
                <?= csrfField() ?>
                <select name="status" class="flex-1 min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $prospect['status'] === $st ? 'selected' : '' ?>><?= e($statusLabels[$st] ?? $st) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Guardar</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-5">
        <div class="lo-card p-5">
            <p class="text-sm font-semibold text-slate-800 mb-3">Agregar nota</p>
            <form method="post" action="<?= e(url('/prospeccion/prospectos/' . (int) $prospect['id'] . '/nota')) ?>" class="space-y-2">
                <?= csrfField() ?>
                <textarea name="note" rows="2" required placeholder="Ej: llamé y quedó en confirmar la semana que viene" class="w-full rounded-xl border border-lo-border px-3 py-2 text-sm"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-900">Agregar nota</button>
                </div>
            </form>
        </div>

        <div class="lo-card p-5">
            <p class="text-sm font-semibold text-slate-800 mb-3">Historial</p>
            <?php if ($events === []): ?>
                <p class="text-sm text-slate-500">Todavía no hay eventos registrados.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($events as $ev): ?>
                        <div class="flex gap-3">
                            <i data-lucide="<?= e($eventIcon((string) $ev['event_type'])) ?>" class="h-4 w-4 text-slate-400 shrink-0 mt-0.5"></i>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800"><span class="font-medium"><?= e($eventLabel((string) $ev['event_type'])) ?></span><?= $ev['detail'] !== null && $ev['detail'] !== '' ? ': ' . e((string) $ev['detail']) : '' ?></p>
                                <p class="text-xs text-slate-400"><?= e($fmtFecha($ev['created_at'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
