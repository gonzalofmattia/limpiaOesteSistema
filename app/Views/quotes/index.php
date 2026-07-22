<?php
$pipeline = is_array($pipeline ?? null) ? $pipeline : [];
$statusCounts = is_array($status_counts ?? null) ? $status_counts : [];
$totalQuotes = array_sum(array_map(static fn($v) => (int) $v, $statusCounts));
$acceptedQuotes = (int) ($statusCounts['accepted'] ?? 0);
$pendingQuotes = (int) (($statusCounts['draft'] ?? 0) + ($statusCounts['sent'] ?? 0));
$rejectedQuotes = (int) ($statusCounts['rejected'] ?? 0);
$amountTotal = array_sum(array_map(fn($q) => (float) ($q['total'] ?? 0), $quotes ?? []));
$currentStatus = (string) ($status ?? '');
$currentSort = (string) ($sort ?? 'created_at');
$currentDir = strtolower((string) ($dir ?? 'desc'));
$filterButtons = [
    ['status' => '', 'label' => 'Todos', 'count' => $totalQuotes],
    ['status' => 'draft', 'label' => 'Borradores', 'count' => (int) ($statusCounts['draft'] ?? 0)],
    ['status' => 'sent', 'label' => 'Enviados', 'count' => (int) ($statusCounts['sent'] ?? 0)],
    ['status' => 'accepted', 'label' => 'Aceptados', 'count' => (int) ($statusCounts['accepted'] ?? 0)],
    ['status' => 'delivered', 'label' => 'Entregados', 'count' => (int) ($statusCounts['delivered'] ?? 0)],
    ['status' => 'partially_delivered', 'label' => 'Entrega parcial', 'count' => (int) ($statusCounts['partially_delivered'] ?? 0)],
    ['status' => 'rejected', 'label' => 'Rechazados', 'count' => (int) ($statusCounts['rejected'] ?? 0)],
    ['status' => 'expired', 'label' => 'Vencidos', 'count' => (int) ($statusCounts['expired'] ?? 0)],
];
$nextDirFor = static function (string $col, string $curSort, string $curDir): string {
    if ($curSort !== $col) {
        return 'asc';
    }
    return $curDir === 'asc' ? 'desc' : 'asc';
};
$sortArrow = static function (string $col, string $curSort, string $curDir): string {
    if ($curSort !== $col) {
        return '';
    }
    return $curDir === 'asc' ? '↑' : '↓';
};
$sortUrl = static function (string $col, string $curSort, string $curDir, string $search, string $status, int $perPage) use ($nextDirFor): string {
    $params = [
        'search' => $search,
        'per_page' => $perPage,
        'status' => $status,
        'sort' => $col,
        'dir' => $nextDirFor($col, $curSort, $curDir),
    ];
    if ($params['status'] === '') {
        unset($params['status']);
    }
    if ($params['search'] === '') {
        unset($params['search']);
    }
    return url('/presupuestos?' . http_build_query($params));
};
$formatPipelineMoney = static function (float $amount): string {
    return '$' . number_format($amount, 0, ',', '.');
};
$pipelineStatusHref = static function (string $stageStatus) use ($currentSort, $currentDir, $search, $per_page): string {
    $filterParams = [
        'per_page' => (int) ($per_page ?? 20),
        'search' => (string) ($search ?? ''),
        'sort' => $currentSort,
        'dir' => $currentDir,
        'status' => $stageStatus,
    ];
    if ($filterParams['search'] === '') {
        unset($filterParams['search']);
    }
    return url('/presupuestos?' . http_build_query($filterParams));
};
$loQuotesListPath = parse_url(url('/presupuestos'), PHP_URL_PATH) ?: '/presupuestos';
$loFilterQuotesActive = $currentStatus !== '' || trim((string) ($search ?? '')) !== ''
    || $currentSort !== 'created_at' || $currentDir !== 'desc'
    || (int) ($per_page ?? 20) !== 20;
?>
<div class="space-y-5 relative pb-20 md:pb-0"
     data-lo-filter-persist
     data-lo-filter-page="presupuestos"
     data-lo-filter-keys="status,search,sort,dir,per_page"
     data-lo-filter-list-path="<?= e($loQuotesListPath) ?>"
     data-lo-filter-clear-url="<?= e(url('/presupuestos')) ?>">
<div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end sm:items-center">
    <div class="hidden md:block">
        <?php $uiBtnHref = url('/presupuestos/crear'); $uiBtnLabel = 'Nuevo presupuesto'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <a href="<?= e(url('/presupuestos/rapido')) ?>" class="inline-flex w-full sm:w-auto min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50">⚡ Presupuesto rápido</a>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Este mes</p><p class="text-2xl font-semibold"><?= $totalQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Aceptados</p><p class="text-2xl font-semibold"><?= $acceptedQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Pendientes</p><p class="text-2xl font-semibold"><?= $pendingQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Monto total</p><p class="text-xl font-semibold"><?= formatPrice($amountTotal) ?></p></div>
</div>
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
<form method="get" class="flex items-center gap-2 flex-1 min-w-0">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
    <input type="hidden" name="sort" value="<?= e($currentSort) ?>">
    <input type="hidden" name="dir" value="<?= e($currentDir) ?>">
    <div class="flex-1 min-h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400 shrink-0"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nº o cliente..." class="w-full min-h-11 bg-transparent outline-none text-base md:text-sm"></div>
    <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
</form>
<?php if ($loFilterQuotesActive): ?>
    <button type="button" data-lo-filter-clear class="shrink-0 inline-flex min-h-11 items-center justify-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar filtros</button>
<?php endif; ?>
</div>
<div class="flex gap-2 overflow-x-auto pb-1">
    <?php foreach ($filterButtons as $btn): ?>
        <?php
        $isActive = $btn['status'] === $currentStatus;
        $filterParams = [
            'per_page' => (int) ($per_page ?? 20),
            'search' => (string) ($search ?? ''),
            'sort' => $currentSort,
            'dir' => $currentDir,
        ];
        if ($btn['status'] !== '') {
            $filterParams['status'] = $btn['status'];
        }
        if ($filterParams['search'] === '') {
            unset($filterParams['search']);
        }
        $filterHref = url('/presupuestos?' . http_build_query($filterParams));
        ?>
        <a href="<?= e($filterHref) ?>" class="px-3 min-h-9 h-9 md:h-8 rounded-full inline-flex items-center text-xs font-semibold whitespace-nowrap <?= $isActive ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>">
            <?= e($btn['label']) ?> <span class="ml-1 text-[10px]">(<?= (int) $btn['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>
<div class="flex flex-nowrap gap-2 overflow-x-auto pb-2 mb-1">
    <?php
    $pipelineStages = [
        ['status' => 'draft', 'label' => 'Borrador', 'wrap' => 'bg-gray-100 border-gray-300'],
        ['status' => 'sent', 'label' => 'Enviados', 'wrap' => 'bg-blue-50 border-blue-300'],
        ['status' => 'accepted', 'label' => 'Aceptados', 'wrap' => 'bg-green-50 border-green-300'],
        ['status' => 'partially_delivered', 'label' => 'Entrega parcial', 'wrap' => 'bg-yellow-50 border-yellow-300'],
        ['status' => 'delivered', 'label' => 'Entregados', 'wrap' => 'bg-emerald-50 border-emerald-400'],
    ];
    foreach ($pipelineStages as $stage):
        $stKey = $stage['status'];
        $row = $pipeline[$stKey] ?? ['cantidad' => 0, 'monto_total' => 0.0];
        $qty = (int) ($row['cantidad'] ?? 0);
        $monto = (float) ($row['monto_total'] ?? 0);
        $isPipelineActive = $currentStatus === $stKey;
        $href = $pipelineStatusHref($stKey);
        $baseCard = 'block min-w-[140px] shrink-0 rounded-xl border p-3 transition hover:brightness-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 focus-visible:ring-slate-700 ' . $stage['wrap'];
        $activeRing = $isPipelineActive ? ' ring-2 ring-slate-800 ring-offset-2' : '';
        ?>
        <a href="<?= e($href) ?>" class="<?= e($baseCard . $activeRing) ?>" title="Filtrar por <?= e($stage['label']) ?>">
            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-tight"><?= e($stage['label']) ?></p>
            <p class="mt-1 text-xs text-slate-600 sm:text-sm"><?= $qty ?> presup.</p>
            <p class="mt-1 text-sm sm:text-base font-semibold text-slate-900 tabular-nums"><?= e($formatPipelineMoney($monto)) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<div class="lo-table-wrap hidden md:block">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">
                    <a href="<?= e($sortUrl('quote_number', $currentSort, $currentDir, (string) ($search ?? ''), $currentStatus, (int) ($per_page ?? 20))) ?>" class="inline-flex items-center gap-1 hover:text-slate-900">
                        Número <span class="text-xs"><?= e($sortArrow('quote_number', $currentSort, $currentDir) !== '' ? $sortArrow('quote_number', $currentSort, $currentDir) : '↕') ?></span>
                    </a>
                </th>
                <th class="text-left px-4 py-3">
                    <a href="<?= e($sortUrl('client_name', $currentSort, $currentDir, (string) ($search ?? ''), $currentStatus, (int) ($per_page ?? 20))) ?>" class="inline-flex items-center gap-1 hover:text-slate-900">
                        Cliente <span class="text-xs"><?= e($sortArrow('client_name', $currentSort, $currentDir) !== '' ? $sortArrow('client_name', $currentSort, $currentDir) : '↕') ?></span>
                    </a>
                </th>
                <th class="text-left px-4 py-3">
                    <a href="<?= e($sortUrl('created_at', $currentSort, $currentDir, (string) ($search ?? ''), $currentStatus, (int) ($per_page ?? 20))) ?>" class="inline-flex items-center gap-1 hover:text-slate-900">
                        Fecha <span class="text-xs"><?= e($sortArrow('created_at', $currentSort, $currentDir) !== '' ? $sortArrow('created_at', $currentSort, $currentDir) : '↕') ?></span>
                    </a>
                </th>
                <th class="text-right px-4 py-3">
                    <a href="<?= e($sortUrl('total', $currentSort, $currentDir, (string) ($search ?? ''), $currentStatus, (int) ($per_page ?? 20))) ?>" class="inline-flex items-center gap-1 hover:text-slate-900">
                        Total <span class="text-xs"><?= e($sortArrow('total', $currentSort, $currentDir) !== '' ? $sortArrow('total', $currentSort, $currentDir) : '↕') ?></span>
                    </a>
                </th>
                <th class="text-center px-4 py-3">
                    <a href="<?= e($sortUrl('status', $currentSort, $currentDir, (string) ($search ?? ''), $currentStatus, (int) ($per_page ?? 20))) ?>" class="inline-flex items-center gap-1 hover:text-slate-900">
                        Estado <span class="text-xs"><?= e($sortArrow('status', $currentSort, $currentDir) !== '' ? $sortArrow('status', $currentSort, $currentDir) : '↕') ?></span>
                    </a>
                </th>
                <?php if (\App\Helpers\Auth::isAdmin()): ?>
                    <th class="text-left px-4 py-3">Vendedor</th>
                <?php endif; ?>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($quotes as $q): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="h-9 w-9 rounded-lg bg-sky-50 grid place-items-center"><i data-lucide="file-text" class="h-4 w-4 text-lo-blue"></i></span>
                            <div class="font-mono text-xs">
                        <?= e($q['quote_number']) ?>
                        <?php if ((int) ($q['is_mercadolibre'] ?? 0) === 1): ?>
                            <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 font-sans">ML</span>
                        <?php endif; ?>
                        <?php if ((int) ($q['attachments_count'] ?? 0) > 0): ?>
                            <span class="ml-1.5 text-gray-500 font-sans normal-case" title="Documentos adjuntos">📎 <?= (int) $q['attachments_count'] ?></span>
                        <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="lo-truncate" title="<?= e($q['client_name'] ?? '—') ?>"><?= e($q['client_name'] ?? '—') ?></span></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($q['created_at']) ?></td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) $q['total']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php $status = (string) ($q['status'] ?? ''); ?>
                        <?php include APP_PATH . '/Views/components/status_badge.php'; ?>
                    </td>
                    <?php if (\App\Helpers\Auth::isAdmin()): ?>
                        <td class="px-4 py-3 text-slate-600"><?= e((string) ($q['owner_full_name'] ?? $q['owner_username'] ?? '—')) ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-slate-600 hover:text-blue-600 transition hover:scale-105" title="Ver detalle">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        <?php if (in_array((string) ($q['status'] ?? ''), ['draft', 'sent', 'accepted', 'partially_delivered'], true)): ?>
                            <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-300 cursor-not-allowed" title="No editable en este estado">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </span>
                        <?php endif; ?>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/pdf')) ?>" class="text-green-600 hover:text-green-700 transition hover:scale-105" title="Descargar PDF">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586A1 1 0 0113.293 3.293l4.414 4.414A1 1 0 0118 8.414V19a2 2 0 01-2 2z" />
                            </svg>
                        </a>
                        <?php $deleteFormId = 'delete-quote-' . (int) $q['id']; ?>
                        <span class="mx-1 h-4 w-px bg-gray-200 inline-block"></span>
                        <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/presupuestos/' . (int) $q['id'] . '/eliminar')) ?>" class="inline">
                            <?= csrfField() ?>
                            <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el presupuesto <?= e((string) $q['quote_number']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="md:hidden lo-mobile-card-list">
    <?php if (($quotes ?? []) === []): ?>
        <p class="text-center text-slate-500 py-10 text-sm">No hay presupuestos en este listado.</p>
    <?php endif; ?>
    <?php foreach ($quotes as $q): ?>
        <article class="lo-mobile-card shadow-sm">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="min-w-0">
                    <p class="font-mono text-sm font-semibold text-slate-900"><?= e($q['quote_number']) ?>
                        <?php if ((int) ($q['is_mercadolibre'] ?? 0) === 1): ?>
                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 font-sans text-xs">ML</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-sm text-slate-600 truncate mt-0.5" title="<?= e($q['client_name'] ?? '—') ?>"><?= e($q['client_name'] ?? '—') ?></p>
                </div>
                <span class="shrink-0 self-start">
                    <?php $status = (string) ($q['status'] ?? ''); ?>
                    <?php include APP_PATH . '/Views/components/status_badge.php'; ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 mb-1"><?= e($q['created_at']) ?></p>
            <p class="text-lg font-semibold text-slate-900 mb-3"><?= formatPrice((float) $q['total']) ?></p>
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg border border-slate-200 text-slate-700" title="Ver detalle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </a>
                <?php if (in_array((string) ($q['status'] ?? ''), ['draft', 'sent', 'accepted', 'partially_delivered'], true)): ?>
                    <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/editar')) ?>" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg border border-slate-200 text-blue-600" title="Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </a>
                <?php else: ?>
                    <span class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg border border-slate-100 text-slate-300" title="No editable en este estado">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </span>
                <?php endif; ?>
                <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/pdf')) ?>" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg border border-slate-200 text-green-600" title="PDF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586A1 1 0 0113.293 3.293l4.414 4.414A1 1 0 0118 8.414V19a2 2 0 01-2 2z" /></svg>
                </a>
                <?php $deleteFormId = 'delete-quote-m-' . (int) $q['id']; ?>
                <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/presupuestos/' . (int) $q['id'] . '/eliminar')) ?>" class="inline ml-auto">
                    <?= csrfField() ?>
                    <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el presupuesto <?= e((string) $q['quote_number']) ?>')" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg border border-red-100 text-red-600" title="Eliminar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<a href="<?= e(url('/presupuestos/crear')) ?>" class="md:hidden fixed right-4 z-[48] inline-flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 active:scale-95 transition" style="bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));" title="Nuevo presupuesto" aria-label="Nuevo presupuesto">
    <i data-lucide="plus" class="h-7 w-7"></i>
</a>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
