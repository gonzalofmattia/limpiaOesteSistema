<?php
$prospects = $prospects ?? [];
$threads = $threads ?? [];
$statusLabels = $statusLabels ?? [];
$fmt = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);
    return $t === false ? '—' : date('d/m H:i', $t);
};
$waLink = static function (string $phone): string {
    return 'https://wa.me/' . ltrim($phone, '+');
};
?>
<div class="space-y-5">
    <?php if ($prospects === []): ?>
        <div class="lo-card p-8 text-center text-slate-500">No hay respuestas pendientes. Buen trabajo.</div>
    <?php endif; ?>

    <?php foreach ($prospects as $p): ?>
        <?php $pid = (int) $p['id']; ?>
        <div class="lo-card p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <a href="<?= e(url('/prospeccion/prospectos/' . $pid)) ?>" class="text-base font-semibold text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $p['name']) ?></a>
                    <p class="text-sm text-slate-500"><?= e((string) $p['phone']) ?> · <?= e((string) ($p['city'] ?? '—')) ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= e($waLink((string) $p['phone'])) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-slate-200 text-sm text-green-700 hover:bg-green-50">
                        <i data-lucide="message-circle" class="h-4 w-4"></i> Abrir WhatsApp
                    </a>
                    <form method="post" action="<?= e(url('/prospeccion/bandeja/' . $pid . '/marcar-respondido')) ?>">
                        <?= csrfField() ?>
                        <button type="submit" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Marcar respondido por mí</button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl bg-slate-50 p-3 max-h-72 overflow-y-auto space-y-2">
                <?php foreach ($threads[$pid] ?? [] as $msg): ?>
                    <?php $isOut = $msg['direction'] === 'out'; ?>
                    <div class="flex <?= $isOut ? 'justify-end' : 'justify-start' ?>">
                        <div class="max-w-[80%] rounded-xl px-3 py-2 text-sm <?= $isOut ? 'bg-[#DCF8C6] text-slate-800' : 'bg-white border border-slate-200 text-slate-800' ?>">
                            <p class="whitespace-pre-line"><?= e((string) $msg['body']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-1 text-right"><?= e($fmt($msg['ts'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <?php foreach (['interesado', 'visita_agendada', 'no_interesado'] as $quickStatus): ?>
                    <form method="post" action="<?= e(url('/prospeccion/prospectos/' . $pid . '/estado')) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="status" value="<?= e($quickStatus) ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-full border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50"><?= e($statusLabels[$quickStatus] ?? $quickStatus) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
