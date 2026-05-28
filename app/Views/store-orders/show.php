<?php
$order = is_array($order ?? null) ? $order : [];
$items = is_array($order['items'] ?? null) ? $order['items'] : [];
$orderId = (int) ($order['id'] ?? 0);
$currentStatus = (string) ($order['status'] ?? 'pending');

$shippingLabels = [
    'retiro' => 'Retiro en Luján',
    'zona_propia' => 'Envío en zona',
    'consultar' => 'Consultar envío',
];

$paymentLabels = [
    'transferencia' => 'Transferencia bancaria',
    'mercadopago' => 'Mercado Pago',
    'efectivo' => 'Efectivo al recibir',
];

$statusOptions = [
    'pending' => 'Pendiente',
    'confirmed' => 'Confirmado',
    'preparing' => 'Preparando',
    'shipped' => 'Enviado',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado',
];

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
<div class="max-w-5xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="<?= e(url('/pedidos-web')) ?>" class="text-sm text-lo-blue hover:underline">← Volver al listado</a>
        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= e($storeOrderStatusBadge($currentStatus)) ?>">
            <?= e($statusOptions[$currentStatus] ?? $currentStatus) ?>
        </span>
    </div>

    <div class="bg-white border border-lo-border rounded-xl p-5">
        <p class="text-sm text-slate-500">Pedido web</p>
        <h2 class="text-2xl font-semibold text-slate-900"><?= e((string) ($order['order_number'] ?? '')) ?></h2>
        <p class="text-sm text-slate-500 mt-1"><?= e((string) ($order['created_at'] ?? '')) ?></p>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white border border-lo-border rounded-xl p-4">
            <p class="text-sm font-semibold text-slate-800 mb-2">Cliente</p>
            <p class="font-medium"><?= e((string) ($order['customer_name'] ?? '—')) ?></p>
            <p class="text-sm text-slate-600 mt-1"><?= e((string) ($order['customer_phone'] ?? '')) ?></p>
            <?php if (!empty($order['customer_email'])): ?>
                <p class="text-sm text-slate-600"><?= e((string) $order['customer_email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($order['customer_notes'])): ?>
                <p class="text-sm text-slate-500 mt-3"><span class="font-medium text-slate-700">Notas del cliente:</span> <?= e((string) $order['customer_notes']) ?></p>
            <?php endif; ?>
        </div>
        <div class="bg-white border border-lo-border rounded-xl p-4">
            <p class="text-sm font-semibold text-slate-800 mb-2">Entrega y pago</p>
            <p class="text-sm"><span class="text-slate-500">Envío:</span> <?= e($shippingLabels[(string) ($order['shipping_method'] ?? '')] ?? '—') ?></p>
            <?php if (($order['shipping_method'] ?? '') === 'zona_propia'): ?>
                <p class="text-sm text-slate-600 mt-1"><?= e((string) ($order['shipping_address'] ?? '')) ?>, <?= e((string) ($order['shipping_locality'] ?? '')) ?></p>
            <?php endif; ?>
            <p class="text-sm mt-2"><span class="text-slate-500">Pago:</span> <?= e($paymentLabels[(string) ($order['payment_method'] ?? '')] ?? '—') ?></p>
        </div>
    </div>

    <div class="bg-white border border-lo-border rounded-xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Producto</th>
                    <th class="text-right px-4 py-3">Cant.</th>
                    <th class="text-right px-4 py-3">P. unit.</th>
                    <th class="text-right px-4 py-3">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $item): ?>
                    <?php
                    $qty = (int) ($item['quantity'] ?? 1);
                    $price = (float) ($item['price'] ?? 0);
                    ?>
                    <tr>
                        <td class="px-4 py-3"><?= e((string) ($item['name'] ?? '—')) ?></td>
                        <td class="px-4 py-3 text-right"><?= $qty ?></td>
                        <td class="px-4 py-3 text-right"><?= formatPrice($price) ?></td>
                        <td class="px-4 py-3 text-right font-medium"><?= formatPrice($price * $qty) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-lo-border rounded-xl p-4 max-w-md ml-auto">
        <div class="flex justify-between text-sm py-1">
            <span class="text-slate-500">Subtotal</span>
            <span><?= formatPrice((float) ($order['subtotal'] ?? 0)) ?></span>
        </div>
        <div class="flex justify-between text-sm py-1">
            <span class="text-slate-500">Envío</span>
            <span><?= (float) ($order['shipping_cost'] ?? 0) === 0.0 ? 'Gratis' : formatPrice((float) $order['shipping_cost']) ?></span>
        </div>
        <div class="flex justify-between text-base font-bold py-2 border-t border-lo-border mt-2">
            <span>Total</span>
            <span class="text-lo-blue"><?= formatPrice((float) ($order['total'] ?? 0)) ?></span>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white border border-lo-border rounded-xl p-4">
            <p class="text-sm font-semibold text-slate-800 mb-3">Estado del pedido</p>
            <form method="post" action="<?= e(url('/pedidos-web/' . $orderId . '/status')) ?>" class="flex flex-wrap items-end gap-3">
                <?= csrfField() ?>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs text-slate-500 mb-1">Estado</label>
                    <select name="status" class="w-full rounded-lg border border-lo-border px-3 py-2 text-sm">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-lo-blue text-white text-sm font-semibold hover:opacity-90">Actualizar</button>
            </form>
        </div>

        <div class="bg-white border border-lo-border rounded-xl p-4">
            <p class="text-sm font-semibold text-slate-800 mb-3">Notas internas</p>
            <form method="post" action="<?= e(url('/pedidos-web/' . $orderId . '/notas')) ?>">
                <?= csrfField() ?>
                <textarea
                    name="admin_notes"
                    rows="3"
                    class="w-full rounded-lg border border-lo-border px-3 py-2 text-sm mb-3"
                    placeholder="Notas para el equipo..."
                ><?= e((string) ($order['admin_notes'] ?? '')) ?></textarea>
                <button type="submit" class="px-4 py-2 rounded-lg border border-lo-border bg-white text-sm font-semibold hover:bg-slate-50">Guardar notas</button>
            </form>
        </div>
    </div>

    <div>
        <button type="button" disabled class="px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm font-semibold cursor-not-allowed" title="Próximamente">
            Crear presupuesto en el sistema
        </button>
    </div>
</div>
