<?php
$labels = $monthlyLabels ?? [];
$values = $monthlySales ?? [];
$currentMonthIndex = count($labels) > 0 ? count($labels) - 1 : 0;
$pendingMore = max(0, (int) ($pendingDeliveryTotalRows ?? 0) - count($pendingDeliveryQuotes ?? []));
?>
<div class="space-y-5">
    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-lg bg-gray-100 p-4">
            <p class="text-[13px] text-gray-600">Ventas hoy</p>
            <p class="text-2xl font-medium text-gray-900 mt-1"><?= formatPrice((float) ($salesTodayAmount ?? 0)) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= (int) ($salesTodayCount ?? 0) ?> ventas</p>
        </div>
        <div class="rounded-lg bg-gray-100 p-4">
            <p class="text-[13px] text-gray-600">Ventas semana</p>
            <p class="text-2xl font-medium text-gray-900 mt-1"><?= formatPrice((float) ($salesWeekAmount ?? 0)) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= (int) ($salesWeekCount ?? 0) ?> ventas</p>
        </div>
        <div class="rounded-lg bg-gray-100 p-4">
            <p class="text-[13px] text-gray-600">Ventas mes</p>
            <p class="text-2xl font-medium text-gray-900 mt-1"><?= formatPrice((float) ($salesMonthAmount ?? 0)) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= (int) ($salesMonthCount ?? 0) ?> ventas</p>
        </div>
        <div class="rounded-lg bg-gray-100 p-4">
            <p class="text-[13px] text-gray-600">Ticket promedio</p>
            <p class="text-2xl font-medium text-gray-900 mt-1"><?= formatPrice((float) ($salesMonthAvgTicket ?? 0)) ?></p>
            <p class="text-xs text-gray-500 mt-1">este mes</p>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <article class="rounded-xl border border-gray-200 bg-white px-5 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Cobros pendientes</h3>
                <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-700"><?= (int) ($clientsWithDebt ?? 0) ?> clientes</span>
            </div>
            <p class="text-2xl font-medium text-red-600 mt-3"><?= formatPrice((float) ($receivable ?? 0)) ?></p>
            <div class="mt-3 space-y-1 text-sm">
                <?php foreach (($topDebtors ?? []) as $d): ?>
                    <p class="flex justify-between"><span class="text-gray-700 truncate pr-2"><?= e((string) ($d['name'] ?? '')) ?></span><span class="text-red-600 font-medium"><?= formatPrice((float) ($d['balance'] ?? 0)) ?></span></p>
                <?php endforeach; ?>
                <?php if (($topDebtors ?? []) === []): ?><p class="text-gray-500">Sin deuda pendiente</p><?php endif; ?>
            </div>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white px-5 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Pendientes de entrega</h3>
                <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-700"><?= (int) ($pendingDeliveryCount ?? 0) ?> presupuestos</span>
            </div>
            <p class="text-2xl font-medium text-amber-600 mt-3"><?= formatPrice((float) ($pendingDeliveryAmount ?? 0)) ?></p>
            <div class="mt-3 space-y-1 text-sm">
                <?php foreach (($pendingDeliveryQuotes ?? []) as $q): ?>
                    <p class="flex justify-between"><span class="text-gray-700 truncate pr-2"><?= e((string) ($q['client_name'] ?? '')) ?></span><span class="text-amber-700 font-medium"><?= formatPrice((float) ($q['total'] ?? 0)) ?></span></p>
                <?php endforeach; ?>
                <?php if ($pendingMore > 0): ?><p class="text-gray-500">y <?= $pendingMore ?> más...</p><?php endif; ?>
                <?php if (($pendingDeliveryQuotes ?? []) === []): ?><p class="text-gray-500">Sin pendientes de entrega</p><?php endif; ?>
            </div>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white px-5 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Ganancia estimada</h3>
                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">este mes</span>
            </div>
            <p class="text-2xl font-medium text-green-600 mt-3"><?= formatPrice((float) ($profitEstimated ?? 0)) ?></p>
            <div class="mt-3 space-y-1 text-sm">
                <p class="flex justify-between"><span class="text-gray-600">Facturado neto:</span><span class="font-medium"><?= formatPrice((float) ($deliveredMonthNet ?? 0)) ?></span></p>
                <p class="flex justify-between"><span class="text-gray-600">Costo estimado:</span><span class="font-medium"><?= formatPrice((float) ($deliveredMonthCost ?? 0)) ?></span></p>
                <p class="flex justify-between"><span class="text-gray-600">Margen:</span><span class="font-medium text-green-600"><?= number_format((float) ($profitMarginPercent ?? 0), 1, ',', '.') ?>%</span></p>
                <?php if ((int) ($deliveredMonthCostNullCount ?? 0) > 0): ?><p class="text-gray-500">Sin datos de costo en algunos ítems.</p><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <article class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Ventas últimos 6 meses</h3>
            <div class="h-[240px]">
                <canvas id="sales6mChart"></canvas>
            </div>
            <div class="mt-3 flex items-center gap-3 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#378ADD]"></span>Meses anteriores</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#1f5d99]"></span>Mes actual</span>
            </div>
        </article>
        <article class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Top 5 productos</h3>
            <div class="space-y-2 text-sm">
                <?php foreach (($topProductsMonth ?? []) as $p): ?>
                    <p class="flex justify-between"><span class="truncate pr-2"><?= e((string) ($p['name'] ?? '')) ?></span><span class="font-medium"><?= (int) ($p['units'] ?? 0) ?> u</span></p>
                <?php endforeach; ?>
                <?php if (($topProductsMonth ?? []) === []): ?><p class="text-gray-500">Sin ventas este mes</p><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <a href="<?= e(url('/presupuestos/crear')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#14542f]">Nuevo presupuesto</a>
        <a href="<?= e(url('/productos/crear')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-[#1565C0] text-white text-sm font-medium hover:bg-[#0f4e98]">Nuevo producto</a>
        <a href="<?= e(url('/cuenta-corriente')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-white border border-gray-300 text-gray-800 text-sm font-medium hover:bg-gray-50">Ver cuenta corriente</a>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <article class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Últimos presupuestos</h3>
                <a href="<?= e(url('/presupuestos')) ?>" class="text-sm text-[#1565C0] hover:underline">Ver todos</a>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach (($recentQuotes ?? []) as $q): ?>
                    <a href="<?= e(url('/presupuestos/' . (int) ($q['id'] ?? 0))) ?>" class="flex items-start justify-between py-2">
                        <span class="min-w-0 pr-3">
                            <span class="block text-sm font-medium text-gray-900"><?= e((string) ($q['quote_number'] ?? '')) ?></span>
                            <span class="block text-xs text-gray-500 truncate"><?= e((string) ($q['client_name'] ?? '—')) ?></span>
                        </span>
                        <span class="text-sm font-medium text-gray-900 whitespace-nowrap"><?= formatPrice((float) ($q['total'] ?? 0)) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (($recentQuotes ?? []) === []): ?><p class="text-sm text-gray-500 py-2">Sin presupuestos aún</p><?php endif; ?>
            </div>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Top 5 clientes del mes</h3>
            <div class="divide-y divide-gray-100">
                <?php foreach (($topClientsMonth ?? []) as $c): ?>
                    <p class="flex justify-between py-2 text-sm"><span class="truncate pr-2"><?= e((string) ($c['name'] ?? '')) ?></span><span class="font-medium"><?= formatPrice((float) ($c['total_amount'] ?? 0)) ?></span></p>
                <?php endforeach; ?>
                <?php if (($topClientsMonth ?? []) === []): ?><p class="text-sm text-gray-500 py-2">Sin ventas este mes</p><?php endif; ?>
            </div>
        </article>
    </section>
</div>

<script>
(() => {
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?> || [];
    const values = <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?> || [];
    const currentMonthIndex = <?= (int) $currentMonthIndex ?>;
    const canvas = document.getElementById('sales6mChart');
    if (!canvas || !window.Chart) return;
    const colors = values.map((_, i) => i === currentMonthIndex ? '#1f5d99' : '#378ADD');
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { ticks: { callback: (v) => '$ ' + Number(v).toLocaleString('es-AR') } }
            }
        }
    });
})();
</script>
