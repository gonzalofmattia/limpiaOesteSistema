<?php
$statusBadge = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-800',
    'received' => 'bg-green-100 text-green-800',
];
?>
<div class="flex flex-wrap justify-between gap-4 mb-6">
    <p class="text-sm text-gray-600">Pedidos consolidados por proveedor desde presupuestos aceptados.</p>
    <a href="<?= e(url('/pedidos-proveedor/generar')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Generar pedidos</a>
</div>
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
                            <?php $st = $o['status']; ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $statusBadge[$st] ?? 'bg-gray-100' ?>"><?= e($st) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                            <a href="<?= e(url('/pedidos-proveedor/' . (int) $o['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                            <a href="<?= e(url('/pedidos-proveedor/' . (int) $o['id'] . '/pdf')) ?>" class="text-[#1a6b3c] hover:underline">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
