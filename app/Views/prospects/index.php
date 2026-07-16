<?php
$statusLabels = $statusLabels ?? [];
$businessTypeLabels = $businessTypeLabels ?? [];
$statusBadge = static function (string $status): string {
    return match ($status) {
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
        'sin_whatsapp' => 'bg-gray-200 text-gray-600',
        default => 'bg-slate-100 text-slate-700',
    };
};
?>
<div class="space-y-5">
    <div class="flex justify-end">
        <?php $uiBtnHref = url('/prospeccion/prospectos/crear'); $uiBtnLabel = 'Nuevo prospecto'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>

    <form method="get" class="lo-card p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2 min-h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2">
            <i data-lucide="search" class="h-4 w-4 text-slate-400 shrink-0"></i>
            <input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nombre o teléfono..." class="w-full min-h-11 bg-transparent outline-none text-sm">
        </div>
        <select name="status" class="min-h-11 rounded-xl border border-lo-border bg-white px-3 text-sm">
            <option value="">Todos los estados</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($status ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="business_type" class="min-h-11 rounded-xl border border-lo-border bg-white px-3 text-sm">
            <option value="">Todos los rubros</option>
            <?php foreach ($businessTypeLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($business_type ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="city" class="min-h-11 rounded-xl border border-lo-border bg-white px-3 text-sm">
            <option value="">Todas las ciudades</option>
            <?php foreach ($cities ?? [] as $c): ?>
                <option value="<?= e((string) $c['city']) ?>" <?= ($city ?? '') === $c['city'] ? 'selected' : '' ?>><?= e((string) $c['city']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="sm:col-span-4 flex justify-end">
            <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
        </div>
    </form>

    <div class="lo-table-wrap hidden md:block">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Rubro</th>
                    <th class="text-left px-4 py-3">Teléfono</th>
                    <th class="text-left px-4 py-3">Ciudad</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (($prospects ?? []) === []): ?>
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No hay prospectos en este listado.</td></tr>
                <?php endif; ?>
                <?php foreach ($prospects ?? [] as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'])) ?>" class="font-medium text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $p['name']) ?></a>
                            <?php if (!empty($p['blacklisted'])): ?>
                                <span class="ml-1 text-xs text-red-600">(no contactar)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($businessTypeLabels[$p['business_type']] ?? $p['business_type']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e((string) $p['phone']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e((string) ($p['city'] ?? '—')) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $p['status'])) ?>"><?= e($statusLabels[$p['status']] ?? $p['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700" title="Editar">
                                <i data-lucide="pencil" class="h-4 w-4 inline"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="md:hidden lo-mobile-card-list">
        <?php if (($prospects ?? []) === []): ?>
            <p class="text-center text-slate-500 py-10 text-sm">No hay prospectos en este listado.</p>
        <?php endif; ?>
        <?php foreach ($prospects ?? [] as $p): ?>
            <article class="lo-mobile-card shadow-sm">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'])) ?>" class="text-base font-semibold text-slate-900"><?= e((string) $p['name']) ?></a>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $p['status'])) ?>"><?= e($statusLabels[$p['status']] ?? $p['status']) ?></span>
                </div>
                <p class="text-sm text-slate-500"><?= e($businessTypeLabels[$p['business_type']] ?? $p['business_type']) ?> · <?= e((string) ($p['city'] ?? '—')) ?></p>
                <p class="text-sm text-slate-500 mb-2"><?= e((string) $p['phone']) ?></p>
                <a href="<?= e(url('/prospeccion/prospectos/' . (int) $p['id'] . '/editar')) ?>" class="text-sm font-medium text-lo-blue">Editar</a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php require APP_PATH . '/Views/layout/pagination.php'; ?>
</div>
