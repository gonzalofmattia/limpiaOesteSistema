<?php
$statusBadge = static function (string $status): string {
    return match ($status) {
        'queued' => 'bg-slate-100 text-slate-700',
        'claimed' => 'bg-blue-100 text-blue-800',
        'sent' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
        default => 'bg-slate-100 text-slate-700',
    };
};
$fmtHora = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);
    return $t === false ? '—' : date('H:i', $t);
};
?>
<div class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Tope diario</p><p class="text-2xl font-semibold"><?= (int) ($dailyCap ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Mensajes hoy</p><p class="text-2xl font-semibold"><?= count($rows ?? []) ?></p></div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Última señal del worker</p>
            <p class="text-lg font-semibold"><?= !empty($worker['last_heartbeat']) ? e(date('d/m H:i', strtotime((string) $worker['last_heartbeat']))) : '—' ?></p>
        </div>
    </div>

    <div class="lo-table-wrap hidden md:block">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Hora</th>
                    <th class="text-left px-4 py-3">Prospecto</th>
                    <th class="text-left px-4 py-3">Campaña</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Todavía no hay mensajes programados para hoy.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows ?? [] as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-slate-600"><?= e($fmtHora($r['sent_at'] ?? $r['claimed_at'] ?? $r['created_at'])) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($r['client_id'] !== null): ?>
                                <a href="<?= e(url('/clientes/' . (int) $r['client_id'])) ?>" class="text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $r['prospect_name']) ?></a>
                            <?php else: ?>
                                <a href="<?= e(url('/prospeccion/prospectos/' . (int) $r['prospect_id'])) ?>" class="text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $r['prospect_name']) ?></a>
                            <?php endif; ?>
                            <span class="text-slate-400"> · <?= e((string) $r['phone']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e((string) ($r['campaign_name'] ?? '—')) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $r['status'])) ?>"><?= e(ucfirst((string) $r['status'])) ?></span></td>
                        <td class="px-4 py-3 text-red-600 text-xs"><?= e((string) ($r['error'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="md:hidden lo-mobile-card-list">
        <?php foreach ($rows ?? [] as $r): ?>
            <article class="lo-mobile-card shadow-sm">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-base font-semibold text-slate-900"><?= e((string) $r['prospect_name']) ?></p>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $r['status'])) ?>"><?= e(ucfirst((string) $r['status'])) ?></span>
                </div>
                <p class="text-sm text-slate-500"><?= e((string) $r['phone']) ?> · <?= e((string) ($r['campaign_name'] ?? '—')) ?></p>
                <?php if (!empty($r['error'])): ?><p class="text-xs text-red-600 mt-1"><?= e((string) $r['error']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
