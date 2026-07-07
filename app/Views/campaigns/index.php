<?php
$statusBadge = static function (string $status): string {
    return match ($status) {
        'borrador' => 'bg-slate-100 text-slate-700',
        'activa' => 'bg-green-100 text-green-800',
        'pausada' => 'bg-amber-100 text-amber-800',
        'finalizada' => 'bg-gray-200 text-gray-600',
        default => 'bg-slate-100 text-slate-700',
    };
};
?>
<div class="space-y-5">
    <div class="flex justify-end">
        <?php $uiBtnHref = url('/prospeccion/campanas/crear'); $uiBtnLabel = 'Nueva campaña'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <div class="lo-table-wrap hidden md:block">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Plantilla</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Enviados</th>
                    <th class="text-right px-4 py-3">Pendientes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (($campaigns ?? []) === []): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Todavía no hay campañas.</td></tr>
                <?php endif; ?>
                <?php foreach ($campaigns ?? [] as $c): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="<?= e(url('/prospeccion/campanas/' . (int) $c['id'])) ?>" class="font-medium text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $c['name']) ?></a>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e((string) $c['template_name']) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $c['status'])) ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
                        <td class="px-4 py-3 text-right"><?= (int) $c['total_sent'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $c['total_pending'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="md:hidden lo-mobile-card-list">
        <?php foreach ($campaigns ?? [] as $c): ?>
            <article class="lo-mobile-card shadow-sm">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <a href="<?= e(url('/prospeccion/campanas/' . (int) $c['id'])) ?>" class="text-base font-semibold text-slate-900"><?= e((string) $c['name']) ?></a>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $c['status'])) ?>"><?= e(ucfirst((string) $c['status'])) ?></span>
                </div>
                <p class="text-sm text-slate-500"><?= e((string) $c['template_name']) ?></p>
                <p class="text-sm text-slate-500">Enviados: <?= (int) $c['total_sent'] ?> · Pendientes: <?= (int) $c['total_pending'] ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</div>
