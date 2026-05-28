<?php
$orders = is_array($orders ?? null) ? $orders : [];
$statusFilter = (string) ($status_filter ?? '');

$statusFilters = [
    '' => 'Todos',
    'pending' => 'Pendiente',
    'confirmed' => 'Confirmado',
    'preparing' => 'Preparando',
    'shipped' => 'Enviado',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado',
];

$shippingLabels = [
    'retiro' => 'Retiro Luján',
    'zona_propia' => 'Envío zona',
    'consultar' => 'Consultar',
];

$paymentLabels = [
    'transferencia' => 'Transferencia',
    'mercadopago' => 'Mercado Pago',
    'efectivo' => 'Efectivo',
];

$storeOrderStatusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'Preparando',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
        default => ucfirst($status),
    };
};

$storeOrderStatusBadge = static function (string $status): string {
    return match ($status) {
        'pending' => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'preparing' => 'bg-orange-100 text-orange-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
};
?>
<div class="space-y-5">
    <div class="flex gap-2 overflow-x-auto pb-1">
        <?php foreach ($statusFilters as $value => $label): ?>
            <?php $isActive = $statusFilter === $value; ?>
            <a
                href="<?= e(url('/pedidos-web' . ($value !== '' ? '?status=' . urlencode($value) : ''))) ?>"
                class="px-3 min-h-9 h-9 md:h-8 rounded-full inline-flex items-center text-xs font-semibold whitespace-nowrap <?= $isActive ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>"
            >
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="lo-table-wrap">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">N° Pedido</th>
                    <th class="text-left px-4 py-3">Cliente</th>
                    <th class="text-left px-4 py-3">Teléfono</th>
                    <th class="text-left px-4 py-3">Envío</th>
                    <th class="text-left px-4 py-3">Pago</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Fecha</th>
                    <th class="text-right px-4 py-3">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($orders === []): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No hay pedidos web todavía.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php $st = (string) ($o['status'] ?? 'pending'); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold"><?= e((string) $o['order_number']) ?></td>
                            <td class="px-4 py-3"><?= e((string) $o['customer_name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= e((string) $o['customer_phone']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= e($shippingLabels[(string) ($o['shipping_method'] ?? '')] ?? '—') ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= e($paymentLabels[(string) ($o['payment_method'] ?? '')] ?? '—') ?></td>
                            <td class="px-4 py-3 text-right font-semibold"><?= formatPrice((float) ($o['total'] ?? 0)) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e($storeOrderStatusBadge($st)) ?>">
                                    <?= e($storeOrderStatusLabel($st)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e((string) ($o['created_at'] ?? '')) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= e(url('/pedidos-web/' . (int) $o['id'])) ?>" class="text-lo-blue hover:underline text-sm font-medium">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
