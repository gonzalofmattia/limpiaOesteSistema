<?php
$labels = $monthlyLabels ?? [];
$values = $monthlySales ?? [];
$currentMonthIndex = count($labels) > 0 ? count($labels) - 1 : 0;
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

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-700">Resumen comercial</h3>
            <form method="get" action="<?= e(url('/')) ?>" class="flex items-center gap-2">
                <label for="periodo" class="text-xs text-slate-500">Período</label>
                <select id="periodo" name="periodo" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="7" <?= (($periodKey ?? '30') === '7') ? 'selected' : '' ?>>Últimos 7 días</option>
                    <option value="30" <?= (($periodKey ?? '30') === '30') ? 'selected' : '' ?>>Últimos 30 días</option>
                    <option value="90" <?= (($periodKey ?? '30') === '90') ? 'selected' : '' ?>>Últimos 90 días</option>
                    <option value="month" <?= (($periodKey ?? '30') === 'month') ? 'selected' : '' ?>>Este mes</option>
                    <option value="lastmonth" <?= (($periodKey ?? '30') === 'lastmonth') ? 'selected' : '' ?>>Mes anterior</option>
                    <option value="all" <?= (($periodKey ?? '30') === 'all') ? 'selected' : '' ?>>Todo (histórico)</option>
                </select>
            </form>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="lo-card p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Ventas</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900"><?= formatPrice((float) ($mainSalesAmount ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= (int) ($mainSalesCount ?? 0) ?> ventas · <?= e((string) ($periodLabel ?? 'últ. 30 días')) ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Presupuestos aceptados y entregados</p>
        </article>
        <article class="lo-card p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Ganancia estimada</p>
            <p class="mt-2 text-3xl font-semibold <?= e((string) ($profitToneClass ?? 'text-slate-900')) ?>"><?= formatPrice((float) ($profitEstimated ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500">Margen: <?= number_format((float) ($profitMarginPercent ?? 0), 1, ',', '.') ?>%</p>
            <p class="mt-1 text-[11px] text-slate-500">Ventas − costo estimado (Margen: <?= number_format((float) ($profitMarginPercent ?? 0), 1, ',', '.') ?>%)</p>
        </article>
        <article class="lo-card p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Por entregar</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600"><?= formatPrice((float) ($pendingDeliveryAmount ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= (int) ($pendingDeliveryCount ?? 0) ?> presupuestos pendientes</p>
            <p class="mt-1 text-[11px] text-slate-500">Aceptados sin entregar</p>
        </article>
        <article class="lo-card p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Por cobrar</p>
            <p class="mt-2 text-3xl font-semibold text-red-600"><?= formatPrice((float) ($receivable ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500"><?= (int) ($clientsWithDebt ?? 0) ?> clientes con saldo</p>
            <p class="mt-1 text-[11px] text-slate-500">Saldo pendiente de clientes</p>
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

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <article class="lo-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Evolución mensual</h3>
            <p class="text-xs text-slate-500 mb-3">Últimos 6 meses</p>
            <div class="h-[240px]">
                <canvas id="sales6mChart"></canvas>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#3B82F6]"></span>Aceptados</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#10B981]"></span>Cobros clientes</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-[#F97316]"></span>Pago proveedor</span>
            </div>
        </article>
        <article class="lo-card p-4 shadow-sm flex flex-col min-h-[280px]">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Saldo proveedores</h3>
                <a href="<?= e(url('/cuenta-corriente')) ?>" class="text-xs text-[#1565C0] hover:underline">Cuenta corriente</a>
            </div>
            <div class="space-y-2 text-sm flex-1 overflow-y-auto max-h-[260px] pr-1">
                <?php foreach (($supplierDebts ?? []) as $s): ?>
                    <?php
                    $name = (string) ($s['name'] ?? '');
                    $compact = preg_replace('/\s+/', '', $name) ?: 'PR';
                    $initials = function_exists('mb_substr')
                        ? strtoupper(mb_substr($compact, 0, 2))
                        : strtoupper(substr($compact, 0, 2));
                    ?>
                    <a href="<?= e(url('/cuenta-corriente/proveedor/' . (int) ($s['id'] ?? 0))) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 p-2.5 hover:bg-slate-100 transition">
                        <span class="h-9 w-9 shrink-0 rounded-full bg-slate-200 text-slate-700 text-xs font-semibold grid place-items-center"><?= e($initials) ?></span>
                        <span class="min-w-0 flex-1 truncate font-medium text-slate-800"><?= e($name) ?></span>
                        <span class="shrink-0 font-semibold text-slate-900 whitespace-nowrap"><?= formatPrice((float) ($s['debt'] ?? 0)) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (($supplierDebts ?? []) === []): ?>
                    <p class="text-sm text-slate-500 py-2"><?= !empty($accountsEnabled) ? 'Sin saldos pendientes' : 'Sin datos de cuenta corriente (tabla no disponible).' ?></p>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <article class="lo-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Top 5 productos</h3>
            <div class="space-y-2">
                <?php foreach (($topProductsAll ?? []) as $index => $p): ?>
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-slate-50">
                        <span class="h-7 w-7 rounded-lg bg-sky-50 text-sky-700 text-xs font-semibold grid place-items-center"><?= (int) $index + 1 ?></span>
                        <span class="flex-1 truncate text-sm"><?= e((string) ($p['name'] ?? '')) ?></span>
                        <span class="text-sm font-semibold text-slate-600"><?= (int) ($p['units'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (($topProductsAll ?? []) === []): ?><p class="text-sm text-gray-500 py-2">Sin ventas registradas</p><?php endif; ?>
            </div>
        </article>
        <article class="lo-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Top 5 clientes</h3>
            <div class="space-y-2">
                <?php foreach (($topClientsAll ?? []) as $index => $c): ?>
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-slate-50">
                        <span class="h-8 w-8 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold grid place-items-center"><?= e(strtoupper(substr((string) ($c['name'] ?? ''), 0, 2))) ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm"><?= e((string) ($c['name'] ?? '')) ?></p>
                            <p class="text-[11px] text-slate-500">#<?= (int) $index + 1 ?> histórico</p>
                        </div>
                        <span class="text-sm font-semibold"><?= formatPrice((float) ($c['total_amount'] ?? 0)) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (($topClientsAll ?? []) === []): ?><p class="text-sm text-gray-500 py-2">Sin ventas registradas</p><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <article class="lo-card p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Últimos presupuestos</h3>
                <a href="<?= e(url('/presupuestos')) ?>" class="text-xs text-[#1565C0] hover:underline">Ver todos</a>
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
        <article class="lo-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold mb-2">Actividad de cobros</h3>
            <p class="text-xs text-slate-500 mb-4">Tendencia de los últimos 6 meses</p>
            <div class="h-28"><canvas id="cashFlowChart"></canvas></div>
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
    const configMain = {
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
    };
    if (window.renderLoChart) {
        window.renderLoChart('dashboardSales6m', canvas, configMain);
    } else {
        new Chart(canvas, configMain);
    }
})();
(() => {
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?> || [];
    const values = <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?> || [];
    const canvas = document.getElementById('cashFlowChart');
    if (!canvas || !window.Chart) return;
    const configCash = {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor: '#16a34a', borderRadius: 6, borderSkipped: false }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#eef2f7' } } } }
    };
    if (window.renderLoChart) {
        window.renderLoChart('dashboardCashFlow', canvas, configCash);
    } else {
        new Chart(canvas, configCash);
    }
})();
</script>
