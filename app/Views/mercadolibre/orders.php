<?php
$orders = is_array($orders ?? null) ? $orders : [];
$connected = !empty($connected);
$ordersSuccess = !empty($orders_success);
$ordersError = trim((string) ($orders_error ?? ''));
$offset = max(0, (int) ($offset ?? 0));

$orderDate = static function (array $order): string {
    $raw = (string) ($order['date_created'] ?? $order['date_closed'] ?? '');
    if ($raw === '') {
        return '—';
    }
    try {
        $dt = new DateTime($raw);
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable) {
        return $raw;
    }
};
$orderBuyer = static function (array $order): string {
    if (isset($order['buyer']) && is_array($order['buyer'])) {
        $nick = trim((string) ($order['buyer']['nickname'] ?? ''));
        if ($nick !== '') {
            return $nick;
        }
        $id = $order['buyer']['id'] ?? null;
        return $id !== null ? 'ID ' . (string) $id : '—';
    }
    return '—';
};
$orderProduct = static function (array $order): string {
    $items = $order['order_items'] ?? [];
    if (!is_array($items) || $items === []) {
        return '—';
    }
    $titles = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string) ($item['item']['title'] ?? $item['title'] ?? ''));
        if ($title !== '') {
            $qty = (int) ($item['quantity'] ?? 1);
            $titles[] = $qty > 1 ? ($title . ' ×' . $qty) : $title;
        }
    }
    return $titles !== [] ? implode(' · ', array_slice($titles, 0, 2)) : '—';
};
$orderAmount = static function (array $order): float {
    if (isset($order['total_amount'])) {
        return (float) $order['total_amount'];
    }
    if (isset($order['paid_amount'])) {
        return (float) $order['paid_amount'];
    }
    return 0.0;
};
$orderStatus = static function (array $order): string {
    $status = trim((string) ($order['status'] ?? ''));
    return $status !== '' ? $status : '—';
};
?>
<div class="space-y-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-lg font-semibold text-slate-900">Órdenes recientes</h2>
        </div>
        <a href="<?= e(url('/mercadolibre')) ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-lo-blue hover:underline">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver al panel
        </a>
    </div>

    <?php if (!$connected): ?>
        <section class="lo-card p-6 text-center">
            <span class="mx-auto h-12 w-12 rounded-full bg-red-100 text-red-600 grid place-items-center">
                <i data-lucide="link-2-off" class="h-6 w-6"></i>
            </span>
            <h3 class="mt-3 text-base font-semibold text-slate-900">Cuenta ML no conectada</h3>
            <p class="mt-1 text-sm text-slate-600">Conectá MercadoLibre desde el panel para ver las órdenes de venta.</p>
            <a href="<?= e(url('/mercadolibre/conectar')) ?>" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-lo-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Conectar ahora
            </a>
        </section>
    <?php elseif (!$ordersSuccess && $ordersError !== ''): ?>
        <section class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            No se pudieron cargar las órdenes: <?= e($ordersError) ?>
        </section>
    <?php endif; ?>

    <?php if ($connected): ?>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3">Fecha</th>
                        <th class="text-left px-4 py-3">Comprador</th>
                        <th class="text-left px-4 py-3">Producto</th>
                        <th class="text-right px-4 py-3">Monto</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-right px-4 py-3">ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($orders as $order): ?>
                        <?php if (!is_array($order)) { continue; } ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-slate-600"><?= e($orderDate($order)) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($orderBuyer($order)) ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= e($orderProduct($order)) ?></td>
                            <td class="px-4 py-3 text-right font-medium"><?= formatPrice($orderAmount($order)) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    <?= e($orderStatus($order)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500"><?= e((string) ($order['id'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($orders === [] && $ordersSuccess): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                No hay órdenes recientes para mostrar.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($orders === [] && !$ordersSuccess && $ordersError === ''): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                Sin datos de órdenes por el momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center">
            <?php if ($offset > 0): ?>
                <a href="<?= e(url('/mercadolibre/ordenes?offset=' . max(0, $offset - 50))) ?>" class="text-sm font-semibold text-lo-blue hover:underline">← Anteriores</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if (count($orders) >= 50): ?>
                <a href="<?= e(url('/mercadolibre/ordenes?offset=' . ($offset + 50))) ?>" class="text-sm font-semibold text-lo-blue hover:underline">Siguientes →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
