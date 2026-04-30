<?php
$activeLists = count(array_filter($lists ?? [], fn($l) => (string) ($l['status'] ?? '') === 'active'));
$expiredLists = count(array_filter($lists ?? [], fn($l) => (string) ($l['status'] ?? '') !== 'active'));
?>
<div class="space-y-5">
<div class="flex justify-end items-center">
    <a href="<?= e(url('/listas/generar')) ?>" class="lo-btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Nueva lista</a>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Listas activas</p><p class="text-2xl font-semibold"><?= $activeLists ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Última actualizada</p><p class="text-sm font-semibold"><?= e((string) (($lists[0]['name'] ?? '—'))) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Vencidas</p><p class="text-2xl font-semibold"><?= $expiredLists ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Productos cubiertos</p><p class="text-2xl font-semibold"><?= (int) ($productsCovered ?? 0) ?></p></div>
</div>
<form method="get" class="mb-4 flex gap-2">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar..." class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="Buscar">
        <i data-lucide="search" class="w-5 h-5 text-white"></i>
    </button>
</form>
<div class="flex gap-2 overflow-x-auto pb-1">
    <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todas <span class="ml-1 text-[10px]"><?= count($lists ?? []) ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Activas <span class="ml-1 text-[10px]"><?= $activeLists ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Vencidas <span class="ml-1 text-[10px]"><?= $expiredLists ?></span></span>
</div>
<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-right px-4 py-3">Markup</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($lists as $l): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><span class="lo-truncate" title="<?= e($l['name']) ?>"><?= e($l['name']) ?></span></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($l['generated_at'] ?? $l['created_at']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $l['custom_markup'] !== null && $l['custom_markup'] !== '' ? formatPercent((float) $l['custom_markup']) : 'Global' ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php $st = (string) ($l['status'] ?? ''); ?>
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass($st)) ?>"><?= e(statusLabel($st)) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                        <a href="<?= e(url('/listas/' . (int) $l['id'])) ?>" class="text-slate-600 hover:text-blue-600 transition hover:scale-105" title="Ver detalle">
                            <i data-lucide="eye" class="w-5 h-5 text-gray-500 hover:text-blue-600"></i>
                        </a>
                        <?php if (!empty($l['pdf_path'])): ?>
                            <a href="<?= e(url('/listas/' . (int) $l['id'] . '/pdf')) ?>" class="text-green-600 hover:text-green-700 transition hover:scale-105" title="Descargar PDF">
                                <i data-lucide="file-text" class="w-5 h-5 text-green-500 hover:text-green-700"></i>
                            </a>
                        <?php endif; ?>
                        <?php $deleteFormId = 'delete-list-' . (int) $l['id']; ?>
                        <span class="mx-1 h-4 w-px bg-gray-200 inline-block"></span>
                        <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/listas/' . (int) $l['id'] . '/eliminar')) ?>" class="inline">
                            <?= csrfField() ?>
                            <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'la lista <?= e((string) $l['name']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
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
