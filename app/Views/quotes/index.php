<?php
$totalQuotes = count($quotes ?? []);
$acceptedQuotes = count(array_filter($quotes ?? [], fn($q) => (string) ($q['status'] ?? '') === 'accepted'));
$pendingQuotes = count(array_filter($quotes ?? [], fn($q) => in_array((string) ($q['status'] ?? ''), ['draft', 'sent'], true)));
$rejectedQuotes = count(array_filter($quotes ?? [], fn($q) => (string) ($q['status'] ?? '') === 'rejected'));
$amountTotal = array_sum(array_map(fn($q) => (float) ($q['total'] ?? 0), $quotes ?? []));
?>
<div class="space-y-5">
<div class="flex justify-end items-center">
    <?php $uiBtnHref = url('/presupuestos/crear'); $uiBtnLabel = 'Nuevo presupuesto'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Este mes</p><p class="text-2xl font-semibold"><?= $totalQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Aceptados</p><p class="text-2xl font-semibold"><?= $acceptedQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Pendientes</p><p class="text-2xl font-semibold"><?= $pendingQuotes ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Monto total</p><p class="text-xl font-semibold"><?= formatPrice($amountTotal) ?></p></div>
</div>
<form method="get" class="flex items-center gap-2">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nº o cliente..." class="w-full bg-transparent outline-none text-sm"></div>
    <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
</form>
<div class="flex gap-2 overflow-x-auto pb-1">
    <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todos <span class="ml-1 text-[10px]"><?= $totalQuotes ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Aceptados <span class="ml-1 text-[10px]"><?= $acceptedQuotes ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Pendientes <span class="ml-1 text-[10px]"><?= $pendingQuotes ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Rechazados <span class="ml-1 text-[10px]"><?= $rejectedQuotes ?></span></span>
</div>
<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Número</th>
                <th class="text-left px-4 py-3">Cliente</th>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-right px-4 py-3">Total</th>
                <th class="text-center px-4 py-3">Estado</th>
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
                        <?php $st = (string) ($q['status'] ?? ''); ?>
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass($st)) ?>"><?= e(statusLabel($st)) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-slate-600 hover:text-blue-600 transition hover:scale-105" title="Ver detalle">
                            <i data-lucide="eye" class="w-5 h-5 text-gray-500 hover:text-blue-600"></i>
                        </a>
                        <?php if (in_array((string) ($q['status'] ?? ''), ['draft', 'sent', 'accepted'], true)): ?>
                            <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-300 cursor-not-allowed" title="Solo se puede editar en draft, sent o accepted">
                                <i data-lucide="pencil" class="w-5 h-5"></i>
                            </span>
                        <?php endif; ?>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/pdf')) ?>" class="text-green-600 hover:text-green-700 transition hover:scale-105" title="Descargar PDF">
                            <i data-lucide="file-text" class="w-5 h-5 text-green-500 hover:text-green-700"></i>
                        </a>
                        <?php $deleteFormId = 'delete-quote-' . (int) $q['id']; ?>
                        <span class="mx-1 h-4 w-px bg-gray-200 inline-block"></span>
                        <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/presupuestos/' . (int) $q['id'] . '/eliminar')) ?>" class="inline">
                            <?= csrfField() ?>
                            <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el presupuesto <?= e((string) $q['quote_number']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
                                <i data-lucide="trash-2" class="w-5 h-5 text-red-400 hover:text-red-600"></i>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
