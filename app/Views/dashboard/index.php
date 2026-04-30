<?php
$labels = $monthlyLabels ?? [];
$values = $monthlySales ?? [];
$currentMonthIndex = count($labels) > 0 ? count($labels) - 1 : 0;
$pendingMore = max(0, (int) ($pendingDeliveryTotalRows ?? 0) - count($pendingDeliveryQuotes ?? []));
?>
<div class="space-y-6">
    <section class="rounded-2xl bg-gradient-to-r from-[#1a6b3c] to-[#1565C0] text-white p-6 lg:p-8">
        <p class="text-xs font-semibold uppercase tracking-widest text-white/70">Panel Comercial</p>
        <h2 class="mt-2 text-2xl font-semibold">¡Buen día, <?= e((string) ($_SESSION['admin_username'] ?? 'admin')) ?>! 👋</h2>
        <p class="mt-2 text-sm text-white/80">Hoy llevás <span class="font-semibold"><?= formatPrice((float) ($salesTodayAmount ?? 0)) ?></span> en ventas. La semana acumula <span class="font-semibold"><?= formatPrice((float) ($salesWeekAmount ?? 0)) ?></span>.</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="<?= e(url('/presupuestos/crear')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900"><i data-lucide="plus" class="h-4 w-4"></i>Nuevo presupuesto</a>
            <a href="<?= e(url('/productos/crear')) ?>" class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white"><i data-lucide="package" class="h-4 w-4"></i>Nuevo producto</a>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="lo-card p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Presupuestos aceptados</p>
            <p class="mt-2 text-3xl font-semibold"><?= formatPrice((float) ($salesMonthAmount ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= (int) ($salesMonthCount ?? 0) ?> presupuestos este mes</p>
        </article>
        <article class="lo-card p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Cobrado</p>
            <p class="mt-2 text-3xl font-semibold"><?= formatPrice((float) ($salesWeekAmount ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500">Cobros registrados</p>
        </article>
        <article class="lo-card p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Ganancia estimada</p>
            <p class="mt-2 text-3xl font-semibold"><?= formatPrice((float) ($profitEstimated ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500">Entregado neto - costo</p>
        </article>
        <article class="lo-card p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">Pendiente de cobro</p>
            <p class="mt-2 text-3xl font-semibold text-red-600"><?= formatPrice((float) ($receivable ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= (int) ($clientsWithDebt ?? 0) ?> clientes con deuda</p>
        </article>
    </section>

    <section class="lo-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div><h3 class="text-sm font-semibold">Ventas</h3><p class="text-xs text-slate-500">Resumen del período</p></div>
            <a href="<?= e(url('/ventas')) ?>" class="text-xs font-semibold text-lo-blue">Ver detalle</a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Hoy</p><p class="mt-1 text-lg font-semibold"><?= formatPrice((float) ($salesTodayAmount ?? 0)) ?></p><p class="text-[11px] text-slate-500"><?= (int) ($salesTodayCount ?? 0) ?> ventas</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Semana</p><p class="mt-1 text-lg font-semibold"><?= formatPrice((float) ($salesWeekAmount ?? 0)) ?></p><p class="text-[11px] text-slate-500"><?= (int) ($salesWeekCount ?? 0) ?> ventas</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Mes</p><p class="mt-1 text-lg font-semibold"><?= formatPrice((float) ($salesMonthAmount ?? 0)) ?></p><p class="text-[11px] text-slate-500"><?= (int) ($salesMonthCount ?? 0) ?> ventas</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Ticket prom.</p><p class="mt-1 text-lg font-semibold"><?= formatPrice((float) ($salesMonthAvgTicket ?? 0)) ?></p><p class="text-[11px] text-slate-500">este mes</p></div>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <article class="lg:col-span-2 lo-card p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Ventas últimos 6 meses</h3>
            <div class="h-[240px]">
                <canvas id="sales6mChart"></canvas>
            </div>
            <div class="mt-3 flex items-center gap-3 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#378ADD]"></span>Meses anteriores</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#1f5d99]"></span>Mes actual</span>
            </div>
        </article>
        <article class="lo-card p-4">
            <h3 class="text-sm font-semibold mb-3">Saldo proveedores</h3>
            <div class="space-y-2 text-sm">
                <?php foreach (($supplierDebts ?? []) as $s): ?>
                    <p class="flex justify-between"><span class="truncate pr-2"><?= e((string) ($s['name'] ?? '')) ?></span><span class="font-medium"><?= formatPrice((float) ($s['debt'] ?? 0)) ?></span></p>
                <?php endforeach; ?>
                <?php if (($supplierDebts ?? []) === []): ?><p class="text-slate-500">Sin saldos pendientes</p><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Productos</p><p class="text-2xl font-semibold"><?= (int) ($productsCount ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Categorías</p><p class="text-2xl font-semibold"><?= (int) ($categoriesCount ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Clientes activos</p><p class="text-2xl font-semibold"><?= (int) ($clientsCount ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Presupuestos</p><p class="text-2xl font-semibold"><?= (int) ($salesMonthCount ?? 0) ?></p></div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <article class="lo-card p-4">
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

        <article class="lo-card p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Top 5 clientes del mes</h3>
            <div class="divide-y divide-gray-100">
                <?php foreach (($topClientsMonth ?? []) as $c): ?>
                    <p class="flex justify-between py-2 text-sm"><span class="truncate pr-2"><?= e((string) ($c['name'] ?? '')) ?></span><span class="font-medium"><?= formatPrice((float) ($c['total_amount'] ?? 0)) ?></span></p>
                <?php endforeach; ?>
                <?php if (($topClientsMonth ?? []) === []): ?><p class="text-sm text-gray-500 py-2">Sin ventas este mes</p><?php endif; ?>
            </div>
        </article>
    </section>
    <section class="lo-card p-4">
        <h3 class="text-sm font-semibold mb-2">Actividad de cobros</h3>
        <p class="text-xs text-slate-500 mb-4">Tendencia de los últimos 6 meses</p>
        <div class="h-28"><canvas id="cashFlowChart"></canvas></div>
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
(() => {
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?> || [];
    const values = <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?> || [];
    const canvas = document.getElementById('cashFlowChart');
    if (!canvas || !window.Chart) return;
    new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor: '#16a34a', borderRadius: 6, borderSkipped: false }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#eef2f7' } } } }
    });
})();
</script>
