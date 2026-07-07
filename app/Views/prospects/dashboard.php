<?php
$funnel = $funnel ?? [];
$statusLabels = $statusLabels ?? [];
$funnelColors = [
    'nuevo' => 'bg-slate-100 text-slate-700',
    'contactado' => 'bg-blue-100 text-blue-800',
    'respondio' => 'bg-amber-100 text-amber-800',
    'interesado' => 'bg-sky-100 text-sky-900',
    'visita_agendada' => 'bg-indigo-100 text-indigo-800',
    'muestra_entregada' => 'bg-purple-100 text-purple-800',
    'cotizado' => 'bg-orange-100 text-orange-800',
    'cliente' => 'bg-green-100 text-green-800',
    'no_interesado' => 'bg-red-100 text-red-800',
    'sin_respuesta' => 'bg-gray-200 text-gray-600',
];
$fmtFecha = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);
    return $t === false ? '—' : date('d/m/Y', $t);
};
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <?php $uiBtnHref = url('/prospeccion/prospectos/crear'); $uiBtnLabel = 'Nuevo prospecto'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
            <?php $uiBtnHref = url('/prospeccion/importar'); $uiBtnLabel = 'Importar'; $uiOutlineIcon = 'upload'; require APP_PATH . '/Views/layout/partials/ui-btn-outline.php'; ?>
            <?php $uiBtnHref = url('/prospeccion/plantillas'); $uiBtnLabel = 'Plantillas'; $uiOutlineIcon = 'message-square-text'; require APP_PATH . '/Views/layout/partials/ui-btn-outline.php'; ?>
        </div>
        <a href="<?= e(url('/prospeccion/prospectos')) ?>" class="text-sm font-medium text-lo-blue hover:underline">Ver todos los prospectos →</a>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Embudo</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <?php foreach ($statusLabels as $key => $label): ?>
                <a href="<?= e(url('/prospeccion/prospectos?status=' . urlencode($key))) ?>" class="lo-card p-4 hover:shadow-sm transition">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($funnelColors[$key] ?? 'bg-slate-100 text-slate-700') ?>"><?= e($label) ?></span>
                    <p class="text-2xl font-semibold mt-2"><?= (int) ($funnel[$key] ?? 0) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Contactados (últimos 7 días)</p>
            <p class="text-2xl font-semibold"><?= (int) $contactedLast7 ?></p>
        </div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Respuestas pendientes de atender</p>
            <p class="text-2xl font-semibold"><?= count($pendingResponses ?? []) ?></p>
        </div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Seguimientos vencidos</p>
            <p class="text-2xl font-semibold"><?= count($overdueFollowups ?? []) ?></p>
        </div>
    </div>

    <div class="lo-card p-4">
        <p class="text-sm font-semibold text-slate-800 mb-3">Hoy</p>
        <?php if (($pendingResponses ?? []) === [] && ($overdueFollowups ?? []) === []): ?>
            <p class="text-sm text-slate-500">No hay pendientes por hoy.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($pendingResponses ?? [] as $p): ?>
                    <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'])) ?>" class="flex items-center justify-between py-2.5 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate"><?= e((string) $p['name']) ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) ($p['city'] ?? '—')) ?> · <?= e((string) $p['phone']) ?></p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 shrink-0">Respondió — atender</span>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($overdueFollowups ?? [] as $p): ?>
                    <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'])) ?>" class="flex items-center justify-between py-2.5 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate"><?= e((string) $p['name']) ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) ($p['city'] ?? '—')) ?> · <?= e((string) $p['phone']) ?> · contactado el <?= e($fmtFecha($p['last_contacted_at'] ?? null)) ?></p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 shrink-0">Seguimiento vencido</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
