<div class="flex justify-between mb-6">
    <p class="text-sm text-gray-600">Clientes para presupuestos.</p>
    <a href="<?= e(url('/clientes/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Nuevo cliente</a>
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
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Razón social</th>
                <th class="text-left px-4 py-3">Teléfono</th>
                <th class="text-left px-4 py-3">Ciudad</th>
                <th class="text-left px-4 py-3">Estado cuenta</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($c['business_name'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= e($c['phone'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= e($c['city'] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <?php $balance = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0); ?>
                        <?php if ($balance > 0): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">Debe <?= formatPrice($balance) ?></span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Al día</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                        <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $c['id'])) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Ver cuenta corriente">
                            <i data-lucide="wallet" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                        </a>
                        <a href="<?= e(url('/clientes/' . (int) $c['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                            <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                        </a>
                        <?php $deleteFormId = 'delete-client-' . (int) $c['id']; ?>
                        <span class="mx-1 h-4 w-px bg-gray-200 inline-block"></span>
                        <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/clientes/' . (int) $c['id'] . '/eliminar')) ?>" class="inline">
                            <?= csrfField() ?>
                            <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el cliente <?= e((string) $c['name']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
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
