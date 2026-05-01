<?php
$quote = $quote ?? [];
$items = $items ?? [];
$accountTx = $accountTx ?? [];
$payment = $payment ?? ['label' => 'Pendiente (saldo cliente)', 'badge' => 'bg-rose-100 text-rose-800'];
$deliveryDelivered = ((string) ($quote['status'] ?? '') === 'delivered');
?>
<div class="max-w-4xl space-y-5">
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <p class="text-sm text-gray-500">Venta</p>
        <h2 class="text-2xl font-semibold text-gray-900"><?= e((string) (($quote['sale_number'] ?? '') !== '' ? $quote['sale_number'] : $quote['quote_number'])) ?></h2>
        <p class="text-sm text-gray-600 mt-1">Presupuesto origen: <?= e((string) ($quote['quote_number'] ?? '')) ?></p>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-gray-800 mb-1">Cliente</p>
            <p><?= e((string) ($quote['client_name'] ?? '—')) ?></p>
            <p class="text-sm text-gray-600"><?= e((string) ($quote['phone'] ?? '')) ?></p>
            <p class="text-sm text-gray-600"><?= e((string) ($quote['email'] ?? '')) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-gray-800 mb-1">Estado</p>
            <p><span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass($deliveryDelivered ? 'delivered' : 'pending')) ?>"><?= e(statusLabel($deliveryDelivered ? 'delivered' : 'pending')) ?></span></p>
            <p class="mt-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= e((string) $payment['badge']) ?>"><?= e((string) $payment['label']) ?></span></p>
            <p class="text-lg font-semibold mt-2"><?= formatPrice((float) ($quote['total'] ?? 0)) ?></p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-2">Item</th>
                <th class="text-right px-4 py-2">Cant.</th>
                <th class="text-right px-4 py-2">P. unit.</th>
                <th class="text-right px-4 py-2">Subtotal</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $it): ?>
                <tr>
                    <td class="px-4 py-2"><?= (int) ($it['combo_id'] ?? 0) > 0 ? e((string) ($it['combo_name'] ?? 'Combo')) : e((string) (($it['code'] ?? '') . ' — ' . ($it['name'] ?? ''))) ?></td>
                    <td class="px-4 py-2 text-right"><?= (int) ($it['quantity'] ?? 0) ?></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) ($it['unit_price'] ?? 0)) ?></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) ($it['subtotal'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-gray-800 mb-3">Movimientos de cuenta corriente</p>
        <div class="space-y-2">
            <?php foreach ($accountTx as $tx): ?>
                <div class="flex justify-between text-sm border-b border-gray-100 pb-2">
                    <span><?= e((string) ($tx['transaction_date'] ?? '')) ?> · <?= e((string) ($tx['description'] ?? '')) ?></span>
                    <span><?= formatPrice((float) ($tx['amount'] ?? 0)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
