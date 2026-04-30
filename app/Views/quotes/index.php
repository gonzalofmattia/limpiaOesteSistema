<div class="flex justify-between mb-6">
    <p class="text-sm text-gray-600">Presupuestos con numeración <?= e(setting('quote_prefix', 'LO')) ?>-AÑO-NNNN.</p>
    <a href="<?= e(url('/presupuestos/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Nuevo presupuesto</a>
</div>
<form method="get" class="mb-4 flex gap-2">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar..." class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="Buscar">
        <i data-lucide="search" class="w-5 h-5 text-white"></i>
    </button>
</form>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
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
                    <td class="px-4 py-3 font-mono text-xs">
                        <?= e($q['quote_number']) ?>
                        <?php if ((int) ($q['is_mercadolibre'] ?? 0) === 1): ?>
                            <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 font-sans">ML</span>
                        <?php endif; ?>
                        <?php if ((int) ($q['attachments_count'] ?? 0) > 0): ?>
                            <span class="ml-1.5 text-gray-500 font-sans normal-case" title="Documentos adjuntos">📎 <?= (int) $q['attachments_count'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?= e($q['client_name'] ?? '—') ?></td>
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
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
