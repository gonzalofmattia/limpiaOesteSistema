<?php
$prospects = $prospects ?? [];
$threads = $threads ?? [];
$suggestions = $suggestions ?? [];
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
$intentMeta = [
    'interesado' => ['Interesado', 'bg-green-100 text-green-800'],
    'pregunta_precio' => ['Pregunta por precio', 'bg-blue-100 text-blue-800'],
    'pregunta_producto' => ['Pregunta por producto', 'bg-blue-100 text-blue-800'],
    'reagendar' => ['Quiere reagendar', 'bg-amber-100 text-amber-800'],
    'rechazo' => ['Rechazo', 'bg-red-100 text-red-800'],
    'otro' => ['Otro', 'bg-slate-100 text-slate-700'],
];
?>
<div class="space-y-5">
    <?php if ($prospects === []): ?>
        <div class="lo-card p-8 text-center text-slate-500">No hay respuestas pendientes. Buen trabajo.</div>
    <?php endif; ?>

    <?php foreach ($prospects as $p): ?>
        <?php
        $pid = (int) $p['id'];
        $sugg = $suggestions[$pid] ?? null;
        $hasSuggestion = $sugg !== null && !empty($sugg['ai_suggested_reply']);
        $intentKey = $hasSuggestion ? (string) ($sugg['ai_intent'] ?? 'otro') : null;
        $intentLabel = $intentKey !== null ? ($intentMeta[$intentKey][0] ?? ucfirst($intentKey)) : '';
        $intentBadge = $intentKey !== null ? ($intentMeta[$intentKey][1] ?? 'bg-slate-100 text-slate-700') : '';
        ?>
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

            <?php if ($hasSuggestion): ?>
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Sugerencia de respuesta
                        </span>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($intentBadge) ?>"><?= e($intentLabel) ?></span>
                    </div>
                    <textarea id="ai-reply-<?= $pid ?>" rows="3" class="w-full rounded-lg border border-indigo-200 px-3 py-2 text-sm bg-white"><?= e((string) $sugg['ai_suggested_reply']) ?></textarea>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="copyAndOpenWhatsapp('ai-reply-<?= $pid ?>', '<?= e($waLink((string) $p['phone'])) ?>')" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Copiar y abrir WhatsApp</button>
                        <?php if ($intentKey === 'rechazo'): ?>
                            <form method="post" action="<?= e(url('/prospeccion/prospectos/' . $pid . '/estado')) ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="status" value="no_interesado">
                                <button type="submit" class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">Pasar a No interesado</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/prospeccion/bandeja/' . $pid . '/descartar-sugerencia')) ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="px-3 py-2 rounded-lg text-sm text-slate-500 hover:text-slate-700">Descartar sugerencia</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($sugg !== null && $sugg['ai_processed_at'] !== null): ?>
                <p class="text-xs text-slate-400 italic">No se pudo generar una sugerencia para este mensaje — respondé manualmente.</p>
            <?php endif; ?>

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
<script>
function copyAndOpenWhatsapp(textareaId, waUrl) {
    var el = document.getElementById(textareaId);
    var text = el ? el.value : '';
    function openWa() { window.open(waUrl, '_blank'); }
    function fallbackCopy() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(openWa).catch(function () { fallbackCopy(); openWa(); });
    } else {
        fallbackCopy();
        openWa();
    }
}
</script>
