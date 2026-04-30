<div class="flex flex-wrap justify-between gap-4 mb-6">
    <p class="text-sm text-gray-600">Pedidos consolidados por proveedor desde presupuestos aceptados.</p>
    <a href="<?= e(url('/pedidos-proveedor/generar')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar pedidos</a>
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
                <th class="text-left px-4 py-3">Proveedor</th>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-right px-4 py-3">Productos</th>
                <th class="text-right px-4 py-3">Cajas/bultos</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">Todavía no generaste ningún pedido.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs"><?= e($o['order_number']) ?></td>
                        <td class="px-4 py-3"><?= e((string) ($o['supplier_name'] ?? '—')) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= e($o['created_at']) ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $o['total_products'] ?></td>
                        <td class="px-4 py-3 text-right font-medium"><?= (int) $o['total_boxes'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php $st = (string) ($o['status'] ?? ''); ?>
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass($st)) ?>"><?= e(statusLabel($st)) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                            <a href="<?= e(url('/pedidos-proveedor/' . (int) $o['id'])) ?>" class="text-slate-600 hover:text-blue-600 transition hover:scale-105" title="Ver detalle">
                                <i data-lucide="eye" class="w-5 h-5 text-gray-500 hover:text-blue-600"></i>
                            </a>
                            <a href="<?= e(url('/pedidos-proveedor/' . (int) $o['id'] . '/pdf')) ?>" class="text-green-600 hover:text-green-700 transition hover:scale-105" title="Descargar PDF">
                                <i data-lucide="file-text" class="w-5 h-5 text-green-500 hover:text-green-700"></i>
                            </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
