<?php
$filters = $filters ?? [];
$summary = $summary ?? [];
$sales = $sales ?? [];
?>
<div class="space-y-5">
    <div class="flex items-center justify-end"></div>
    <form method="get" action="<?= e(url('/ventas')) ?>" class="lo-card p-4 grid md:grid-cols-6 gap-3">
        <input type="date" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <input type="date" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <input type="text" name="client" value="<?= e((string) ($filters['client'] ?? '')) ?>" placeholder="Cliente" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <select name="delivery" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Entrega (todos)</option>
            <option value="accepted" <?= (($filters['delivery'] ?? '') === 'accepted') ? 'selected' : '' ?>>Pendiente</option>
            <option value="delivered" <?= (($filters['delivery'] ?? '') === 'delivered') ? 'selected' : '' ?>>Entregado</option>
        </select>
        <select name="payment" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Cobro (todos)</option>
            <option value="pending" <?= (($filters['payment'] ?? '') === 'pending') ? 'selected' : '' ?>>Pendiente</option>
            <option value="partial" <?= (($filters['payment'] ?? '') === 'partial') ? 'selected' : '' ?>>Parcial</option>
            <option value="paid" <?= (($filters['payment'] ?? '') === 'paid') ? 'selected' : '' ?>>Cobrado</option>
        </select>
        <input type="text" name="search" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Buscar..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
        <div class="md:col-span-6 flex gap-2">
            <button class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm">Filtrar</button>
            <a href="<?= e(url('/ventas/exportar?' . http_build_query($filters))) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Exportar Excel</a>
            <a href="<?= e(url('/ventas/reportes')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Reportes</a>
        </div>
    </form>

    <div class="flex gap-2 overflow-x-auto pb-1">
        <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todos <span class="ml-1 text-[10px]"><?= (int) ($summary['count'] ?? 0) ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Pendientes entrega <span class="ml-1 text-[10px]"><?= (int) ($summary['pending_delivery_count'] ?? 0) ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Pendientes cobro <span class="ml-1 text-[10px]"><?= (int) ($summary['pending_collection_count'] ?? 0) ?></span></span>
    </div>

    <div class="grid md:grid-cols-5 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-gray-500">Total ventas</p><p class="text-lg font-semibold"><?= formatPrice((float) ($summary['total_amount'] ?? 0)) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-gray-500">Cantidad</p><p class="text-lg font-semibold"><?= (int) ($summary['count'] ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-gray-500">Ticket promedio</p><p class="text-lg font-semibold"><?= formatPrice((float) ($summary['avg_ticket'] ?? 0)) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-gray-500">Pend. entrega</p><p class="text-lg font-semibold"><?= (int) ($summary['pending_delivery_count'] ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-gray-500">Pend. cobro</p><p class="text-sm font-semibold"><?= (int) ($summary['pending_collection_count'] ?? 0) ?> · <?= formatPrice((float) ($summary['pending_collection_amount'] ?? 0)) ?></p></div>
    </div>

    <div class="lo-table-wrap">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-2">Nro Venta</th>
                <th class="text-left px-4 py-2">Nro Presupuesto</th>
                <th class="text-left px-4 py-2">Fecha</th>
                <th class="text-left px-4 py-2">Cliente</th>
                <th class="text-right px-4 py-2">Items</th>
                <th class="text-right px-4 py-2">Total</th>
                <th class="text-center px-4 py-2">Entrega</th>
                <th class="text-center px-4 py-2">Cobro (según saldo cliente)</th>
                <th class="text-right px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <span class="h-8 w-8 rounded-lg bg-sky-50 text-sky-700 grid place-items-center"><i data-lucide="shopping-cart" class="h-4 w-4"></i></span>
                            <span class="font-mono"><?= e((string) ($sale['sale_number'] ?: '—')) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-2"><?= e((string) $sale['quote_number']) ?></td>
                    <td class="px-4 py-2"><?= e((string) $sale['created_date']) ?></td>
                    <td class="px-4 py-2"><span class="lo-truncate" title="<?= e((string) $sale['client_name']) ?>"><?= e((string) $sale['client_name']) ?></span></td>
                    <td class="px-4 py-2 text-right"><?= (int) $sale['items_count'] ?></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) $sale['total']) ?></td>
                    <td class="px-4 py-2 text-center"><span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e((string) $sale['delivery_badge']) ?>"><?= e((string) $sale['delivery_label']) ?></span></td>
                    <td class="px-4 py-2 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= e((string) $sale['payment_badge']) ?>"><?= e((string) $sale['payment_label']) ?></span></td>
                    <td class="px-4 py-2">
                        <div class="flex justify-end">
                            <a href="<?= e(url('/ventas/' . (int) $sale['id'])) ?>" class="text-slate-600 hover:text-blue-600 transition hover:scale-105" title="Ver detalle">
                                <i data-lucide="eye" class="w-5 h-5 text-gray-500 hover:text-blue-600"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require APP_PATH . '/Views/layout/pagination.php'; ?>
</div>
