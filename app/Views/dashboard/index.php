<?php
$labels = is_array($monthlyLabels ?? null) ? $monthlyLabels : [];
$acceptedSeries = is_array($monthlyAccepted ?? null) ? $monthlyAccepted : [];
$collectedSeries = is_array($monthlyCollected ?? null) ? $monthlyCollected : [];
$supplierSeries = is_array($monthlySupplierPayments ?? null) ? $monthlySupplierPayments : [];

$sparkPoints = static function (array $values): string {
    if ($values === []) {
        return '';
    }
    $count = count($values);
    $max = max($values);
    $min = min($values);
    $range = $max - $min;
    $width = 100.0;
    $height = 36.0;
    $points = [];
    foreach ($values as $i => $value) {
        $x = $count <= 1 ? 0 : ($i * ($width / ($count - 1)));
        $ratio = $range <= 0.00001 ? 0.5 : (($value - $min) / $range);
        $y = $height - ($ratio * $height);
        $points[] = round($x, 2) . ',' . round($y, 2);
    }
    return implode(' ', $points);
};

$barMax = max(array_merge([1.0], $acceptedSeries, $collectedSeries, $supplierSeries));
?>

<div class="space-y-5">
    <section class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-500">Panel comercial</p>
        <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mt-1">Resumen rápido de tu negocio</h2>
        <p class="text-sm text-gray-600 mt-1">Vista simple para tomar decisiones desde el celular.</p>
    </section>

    <?php if (!empty($accountsEnabled)): ?>
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <a href="<?= e(url('/dashboard/detalle/aceptados')) ?>" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:border-primary transition">
                <p class="text-xs text-gray-500">Presupuestos aceptados</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= formatPrice((float) $acceptedQuotesTotal) ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= (int) $acceptedQuotesCount ?> presupuestos (tap para desglose)</p>
                <p class="text-[11px] text-gray-400 mt-1">Formula: sum(total) donde estado = accepted/delivered.</p>
            </a>
            <a href="<?= e(url('/dashboard/detalle/cobrado')) ?>" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:border-primary transition">
                <p class="text-xs text-gray-500">Cobrado</p>
                <p class="text-2xl font-bold text-primary mt-1"><?= formatPrice((float) $collectedTotal) ?></p>
                <p class="text-xs text-gray-500 mt-1">Cobros registrados (tap para desglose)</p>
                <p class="text-[11px] text-gray-400 mt-1">Formula: sum(cobros clientes) en cuenta corriente.</p>
            </a>
            <a href="<?= e(url('/dashboard/detalle/ganancia')) ?>" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:border-primary transition">
                <p class="text-xs text-gray-500">Ganancia estimada</p>
                <p class="text-2xl font-bold mt-1 <?= (float) $deliveredProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                    <?= formatPrice((float) $deliveredProfit) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">Entregado neto - costo estimado (tap para desglose)</p>
                <p class="text-[11px] text-gray-400 mt-1">Formula: sum(entregado neto) - sum(costo estimado).</p>
            </a>
            <a href="<?= e(url('/dashboard/detalle/pendiente')) ?>" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:border-primary transition">
                <p class="text-xs text-gray-500">Pendiente de cobro</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?= formatPrice((float) $receivable) ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= (int) $clientsWithDebt ?> clientes con deuda (tap para desglose)</p>
                <p class="text-[11px] text-gray-400 mt-1">Formula: suma de saldos positivos por cliente.</p>
            </a>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Evolución mensual (6 meses)</h3>
                    <span class="text-xs text-gray-500">Aceptados / Cobrado</span>
                </div>
                <div class="h-40 bg-gray-50 rounded-xl border border-gray-100 p-3 flex items-end gap-2">
                    <?php foreach ($labels as $index => $label): ?>
                        <?php
                        $accepted = (float) ($acceptedSeries[$index] ?? 0.0);
                        $col = (float) ($collectedSeries[$index] ?? 0.0);
                        $sup = (float) ($supplierSeries[$index] ?? 0.0);
                        $hAccepted = max(6, (int) round(($accepted / $barMax) * 100));
                        $hCol = max(6, (int) round(($col / $barMax) * 100));
                        $hSup = max(6, (int) round(($sup / $barMax) * 100));
                        ?>
                        <div class="flex-1 min-w-0">
                            <div class="h-24 flex items-end justify-center gap-1">
                                <div class="w-2 rounded bg-blue-300" style="height: <?= $hAccepted ?>%"></div>
                                <div class="w-2 rounded bg-primary/80" style="height: <?= $hCol ?>%"></div>
                                <div class="w-2 rounded bg-rose-300" style="height: <?= $hSup ?>%"></div>
                            </div>
                            <p class="text-[10px] text-center text-gray-500 mt-2 truncate"><?= e($label) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-blue-300"></span>Aceptados</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-primary/80"></span>Cobrado</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded bg-rose-300"></span>Pago proveedor</span>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-800 mb-2">Saldo proveedores</h3>
                <?php if (($supplierDebts ?? []) === []): ?>
                    <p class="text-sm text-gray-500">Sin movimientos aún.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($supplierDebts, 0, 5) as $supplier): ?>
                            <a href="<?= e(url('/cuenta-corriente/proveedor/' . (int) $supplier['id'])) ?>" class="flex items-center justify-between text-sm hover:text-accent">
                                <span class="truncate pr-2"><?= e($supplier['name']) ?></span>
                                <span class="font-medium"><?= formatPrice((float) $supplier['debt']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Entregado neto / costo</p>
                    <p class="text-sm font-semibold text-gray-800">
                        <?= formatPrice((float) $deliveredNetTotal) ?> / <?= formatPrice((float) $deliveredCostTotal) ?>
                    </p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Productos</p>
            <p class="text-xl font-semibold text-gray-900"><?= (int) $productsActive ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Categorias</p>
            <p class="text-xl font-semibold text-gray-900"><?= (int) $categoriesCount ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Clientes activos</p>
            <p class="text-xl font-semibold text-gray-900"><?= (int) $clientsCount ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Presupuestos</p>
            <p class="text-xl font-semibold text-gray-900"><?= (int) $quotesCount ?></p>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="<?= e(url('/presupuestos/crear')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-accent text-white text-sm font-medium hover:bg-blue-700">
            Nuevo presupuesto
        </a>
        <a href="<?= e(url('/productos/crear')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-light">
            Nuevo producto
        </a>
        <a href="<?= e(url('/cuenta-corriente')) ?>" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-white border border-gray-300 text-sm font-medium hover:bg-gray-50">
            Ver cuenta corriente
        </a>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Ultimos presupuestos</h3>
                <a href="<?= e(url('/presupuestos')) ?>" class="text-xs text-accent hover:underline">Ver todos</a>
            </div>
            <div class="mt-3 space-y-2">
                <?php if (!$recentQuotes): ?>
                    <p class="text-sm text-gray-500">Sin presupuestos aun.</p>
                <?php endif; ?>
                <?php foreach ($recentQuotes as $q): ?>
                    <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="block rounded-xl border border-gray-100 p-3 hover:border-gray-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate"><?= e($q['quote_number']) ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= e($q['client_name'] ?? 'Sin cliente') ?></p>
                            </div>
                            <p class="text-sm font-semibold text-gray-800 whitespace-nowrap"><?= formatPrice((float) $q['total']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800">Actividad de cobros</h3>
            <?php if ($collectedSeries === []): ?>
                <p class="text-sm text-gray-500 mt-3">Sin datos para graficar.</p>
            <?php else: ?>
                <div class="mt-3 h-20 bg-gray-50 rounded-xl border border-gray-100 p-3">
                    <svg viewBox="0 0 100 36" preserveAspectRatio="none" class="w-full h-full">
                        <polyline fill="none" stroke="#1a6b3c" stroke-width="2.5" points="<?= e($sparkPoints($collectedSeries)) ?>"></polyline>
                    </svg>
                </div>
                <p class="text-xs text-gray-500 mt-2">Tendencia de cobros de los ultimos 6 meses.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
