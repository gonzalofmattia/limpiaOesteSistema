<?php
$orders = is_array($orders ?? null) ? $orders : [];
$importedSalesMap = is_array($imported_sales_map ?? null) ? $imported_sales_map : [];
$orderNetDisplay = is_array($order_net_display ?? null) ? $order_net_display : [];
$orderImportErrors = is_array($order_import_errors ?? null) ? $order_import_errors : [];
$connected = !empty($connected);
$ordersSuccess = !empty($orders_success);
$ordersError = trim((string) ($orders_error ?? ''));
$offset = max(0, (int) ($offset ?? 0));
$orderCount = count($orders);

$orderDate = static function (array $order): string {
    $raw = (string) ($order['date_created'] ?? $order['date_closed'] ?? '');
    if ($raw === '') {
        return '—';
    }
    try {
        return (new DateTime($raw))->format('d/m/Y H:i');
    } catch (\Throwable) {
        return $raw;
    }
};

$orderBuyer = static function (array $order): string {
    if (!isset($order['buyer']) || !is_array($order['buyer'])) {
        return '—';
    }
    $nick = trim((string) ($order['buyer']['nickname'] ?? ''));
    if ($nick !== '') {
        return $nick;
    }
    $id = $order['buyer']['id'] ?? null;

    return $id !== null ? 'ID ' . (string) $id : '—';
};

$mlPaymentStatus = static function (array $order): string {
    $status = strtolower(trim((string) ($order['status'] ?? '')));
    if (in_array($status, ['paid', 'partially_paid'], true)) {
        return 'paid';
    }
    if ($status === 'cancelled') {
        return 'cancelled';
    }
    $payments = $order['payments'] ?? [];
    if (is_array($payments)) {
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $ps = strtolower(trim((string) ($payment['status'] ?? '')));
            if ($ps === 'approved') {
                return 'paid';
            }
            if (in_array($ps, ['cancelled', 'refunded', 'charged_back'], true)) {
                return 'cancelled';
            }
        }
    }

    return 'pending';
};

$mlShippingStatus = static function (array $order): string {
    $shipping = $order['shipping'] ?? null;
    if (is_array($shipping)) {
        $status = strtolower(trim((string) ($shipping['status'] ?? '')));
        if ($status !== '') {
            return $status;
        }
    }

    return 'pending';
};

$paymentBadgeClass = static function (string $status): string {
    return match ($status) {
        'paid' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
};

$shippingBadgeClass = static function (string $status): string {
    return match ($status) {
        'ready_to_ship' => 'bg-blue-100 text-blue-800',
        'shipped' => 'bg-sky-100 text-sky-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-slate-100 text-slate-700',
    };
};

$paymentLabel = static function (string $status): string {
    return match ($status) {
        'paid' => 'Pagado',
        'cancelled' => 'Cancelado',
        default => 'Pendiente',
    };
};

$shippingLabel = static function (string $status): string {
    return match ($status) {
        'ready_to_ship' => 'Listo para enviar',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$orderLineItems = static function (array $order): array {
    $raw = $order['items'] ?? $order['order_items'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $items = [];
    foreach ($raw as $item) {
        if (is_array($item)) {
            $items[] = $item;
        }
    }

    return $items;
};

$itemTitle = static function (array $item): string {
    $title = trim((string) ($item['item']['title'] ?? $item['title'] ?? ''));

    return $title !== '' ? $title : '—';
};

$itemQuantity = static function (array $item): int {
    return max(1, (int) ($item['quantity'] ?? 1));
};

$itemUnitPrice = static function (array $item): float {
    $price = (float) ($item['unit_price'] ?? $item['full_unit_price'] ?? 0);

    return $price > 0 ? $price : 0.0;
};

$orderTotal = static function (array $order) use ($orderLineItems, $itemQuantity, $itemUnitPrice): float {
    $total = (float) ($order['total_amount'] ?? $order['paid_amount'] ?? 0);
    if ($total > 0) {
        return round($total, 2);
    }
    $sum = 0.0;
    foreach ($orderLineItems($order) as $item) {
        $qty = $itemQuantity($item);
        $unit = $itemUnitPrice($item);
        if ($unit > 0) {
            $sum += round($unit * $qty, 2);
        }
    }

    return round($sum, 2);
};
?>
<div class="space-y-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-lg font-semibold text-slate-900">Órdenes (últimos 30 días)</h2>
            <p class="mt-0.5 text-sm text-slate-500">Importá órdenes de ML como ventas en el sistema.</p>
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
                        <th class="text-left px-4 py-3">Productos</th>
                        <th class="text-right px-4 py-3">Total</th>
                        <th class="text-right px-4 py-3">Neto ML</th>
                        <th class="text-left px-4 py-3">Pago</th>
                        <th class="text-left px-4 py-3">Envío</th>
                        <th class="text-left px-4 py-3">En sistema</th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        if (!is_array($order)) {
                            continue;
                        }
                        $orderId = trim((string) ($order['id'] ?? ''));
                        $lineItems = $orderLineItems($order);
                        $importedSale = $orderId !== '' ? ($importedSalesMap[$orderId] ?? null) : null;
                        $importedQuoteId = is_array($importedSale) ? (int) ($importedSale['id'] ?? 0) : 0;
                        $isImported = $importedQuoteId > 0;
                        $importError = $orderId !== '' ? trim((string) ($orderImportErrors[$orderId] ?? '')) : '';
                        $netInfo = $orderId !== '' ? ($orderNetDisplay[$orderId] ?? null) : null;
                        $payStatus = $mlPaymentStatus($order);
                        $shipStatus = $mlShippingStatus($order);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= e($orderDate($order)) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($orderBuyer($order)) ?></td>
                            <td class="px-4 py-3 text-slate-700">
                                <?php if ($lineItems === []): ?>
                                    <span class="text-slate-400">—</span>
                                <?php else: ?>
                                    <ul class="space-y-1.5">
                                        <?php foreach ($lineItems as $item): ?>
                                            <li class="leading-snug">
                                                <span class="font-medium"><?= e($itemTitle($item)) ?></span>
                                                <span class="text-slate-500">
                                                    · <?= (int) $itemQuantity($item) ?> u.
                                                    · <?= formatPrice($itemUnitPrice($item)) ?> c/u
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-medium"><?= formatPrice($orderTotal($order)) ?></td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?php if (is_array($netInfo)): ?>
                                    <span class="font-medium"><?= formatPrice((float) ($netInfo['amount'] ?? 0)) ?></span>
                                    <?php if (!empty($netInfo['from_system'])): ?>
                                        <span class="block text-[10px] text-green-700 font-semibold uppercase tracking-wide">En sistema</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= e($paymentBadgeClass($payStatus)) ?>">
                                    <?= e($paymentLabel($payStatus)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= e($shippingBadgeClass($shipStatus)) ?>">
                                    <?= e($shippingLabel($shipStatus)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($isImported): ?>
                                    <a href="<?= e(url('/ventas-ml/' . $importedQuoteId)) ?>" class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 hover:bg-green-200">
                                        Importado
                                    </a>
                                <?php elseif ($importError !== ''): ?>
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Sin vincular</span>
                                    <p class="mt-1 max-w-xs text-xs text-red-700"><?= e($importError) ?></p>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <?php if ($orderId !== ''): ?>
                                    <?php if ($isImported): ?>
                                        <a href="<?= e(url('/ventas-ml/' . $importedQuoteId)) ?>" class="text-xs font-semibold text-lo-blue hover:underline">Ver venta ML</a>
                                    <?php elseif ($importError !== ''): ?>
                                        <span class="text-xs text-red-700">No importable</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/mercadolibre/ordenes/' . rawurlencode($orderId) . '/importar')) ?>" class="inline">
                                            <?= csrfField() ?>
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-lo-blue/30 bg-lo-blueSoft px-3 py-1.5 text-xs font-semibold text-lo-blue hover:bg-blue-100">
                                                <i data-lucide="download" class="h-3.5 w-3.5"></i>
                                                Importar como venta ML
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($orders === [] && $ordersSuccess): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                                No hay órdenes en los últimos 30 días.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($orders === [] && !$ordersSuccess && $ordersError === ''): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                                Sin datos de órdenes por el momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center text-sm text-slate-500">
            <span><?= $orderCount ?> orden(es) en esta página</span>
            <div class="flex gap-4">
                <?php if ($offset > 0): ?>
                    <a href="<?= e(url('/mercadolibre/ordenes?offset=' . max(0, $offset - 50))) ?>" class="font-semibold text-lo-blue hover:underline">← Anteriores</a>
                <?php endif; ?>
                <?php if ($orderCount >= 50): ?>
                    <a href="<?= e(url('/mercadolibre/ordenes?offset=' . ($offset + 50))) ?>" class="font-semibold text-lo-blue hover:underline">Siguientes →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
