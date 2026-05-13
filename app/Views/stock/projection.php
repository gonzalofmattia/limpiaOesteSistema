<?php
/** @var list<array<string,mixed>> $rows */
$rows = $rows ?? [];
$totalCompra30d = (float) ($totalCompra30d ?? 0);
$bySupplier = $bySupplier ?? ['seiq' => 0.0, 'higienik' => 0.0, 'otros' => 0.0];
$insufficientHistory = (bool) ($insufficientHistory ?? false);
$daysHistory = $daysHistory ?? null;
?>
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="<?= e(url('/stock-actual')) ?>" class="text-xs text-lo-blue hover:underline">← Volver a stock</a>
        <p class="text-[11px] text-slate-500">Solo proyección informativa · no genera pedidos</p>
    </div>

    <?php if ($insufficientHistory): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong>Advertencia:</strong> el historial de presupuestos aceptados / entregados es corto
            <?php if ($daysHistory !== null): ?>
                (aprox. <?= (int) $daysHistory ?> día<?= (int) $daysHistory !== 1 ? 's' : '' ?> desde el registro más antiguo).
            <?php else: ?>
                (no hay datos suficientes).
            <?php endif; ?>
            La proyección gana confiabilidad con al menos 30 días de ventas registradas.
        </div>
    <?php endif; ?>

    <div class="lo-card p-6 border-l-4 border-l-lo-blue">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Estimado de compra próximos 30 días</p>
        <p class="text-3xl font-semibold text-slate-900 mt-1"><?= formatPrice($totalCompra30d) ?></p>
        <p class="text-xs text-slate-500 mt-2">Suma de productos con ventas en los últimos 90 días (sin líneas “Sin actividad”).</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Seiq (estimado)</p>
            <p class="text-xl font-semibold text-slate-900"><?= formatPrice((float) ($bySupplier['seiq'] ?? 0)) ?></p>
        </div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Higienik (estimado)</p>
            <p class="text-xl font-semibold text-slate-900"><?= formatPrice((float) ($bySupplier['higienik'] ?? 0)) ?></p>
        </div>
        <?php if ((float) ($bySupplier['otros'] ?? 0) > 0.001): ?>
            <div class="lo-card p-4">
                <p class="text-xs text-slate-500">Otros proveedores</p>
                <p class="text-xl font-semibold text-slate-900"><?= formatPrice((float) $bySupplier['otros']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="lo-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-right px-3 py-2">Stock</th>
                        <th class="text-right px-3 py-2">Promedio/día</th>
                        <th class="text-right px-3 py-2">Días rest.</th>
                        <th class="text-right px-3 py-2">Neces. 30d (u.)</th>
                        <th class="text-right px-3 py-2">Cajas</th>
                        <th class="text-right px-3 py-2">Costo est.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r):
                        $sinActividad = !empty($r['sin_actividad']);
                        $dias = $r['dias_restantes'];
                        $diasNum = is_float($dias) || is_int($dias) ? (float) $dias : null;
                        $rowClass = 'hover:bg-gray-50';
                        if ($sinActividad) {
                            $rowClass = 'bg-slate-50/80 text-slate-600';
                        } elseif ($diasNum !== null) {
                            if ($diasNum < 7) {
                                $rowClass = 'bg-red-50';
                            } elseif ($diasNum <= 15) {
                                $rowClass = 'bg-amber-50';
                            } else {
                                $rowClass = 'bg-emerald-50/60';
                            }
                        }
                        $prom = (float) ($r['promedio_diario'] ?? 0);
                        $nec = (float) ($r['necesidad_30d_unidades'] ?? 0);
                        $cajas = (int) ($r['cajas_a_pedir'] ?? 0);
                        $costo = (float) ($r['costo_estimado'] ?? 0);
                        $tienePrecio = !empty($r['tiene_precio_caja']);
                    ?>
                        <tr class="<?= e($rowClass) ?>">
                            <td class="px-3 py-2 align-top">
                                <div>
                                    <span class="font-mono text-xs text-slate-500"><?= e((string) ($r['code'] ?? '')) ?></span>
                                    <span class="ml-2 lo-truncate font-medium" title="<?= e((string) ($r['name'] ?? '')) ?>"><?= e((string) ($r['name'] ?? '')) ?></span>
                                </div>
                                <p class="text-[10px] text-slate-500 mt-0.5">
                                    30d: <?= number_format((float) ($r['vendido_30d'] ?? 0), 0, ',', '.') ?>
                                    · 60d: <?= number_format((float) ($r['vendido_60d'] ?? 0), 0, ',', '.') ?>
                                    · 90d: <?= number_format((float) ($r['vendido_90d'] ?? 0), 0, ',', '.') ?>
                                    <?php if (($r['supplier_name'] ?? '') !== ''): ?>
                                        <span class="text-slate-400"> · <?= e((string) $r['supplier_name']) ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($sinActividad): ?>
                                    <p class="text-[11px] font-medium text-slate-600 mt-1">Sin actividad</p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold whitespace-nowrap"><?= (int) ($r['stock_units'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <?= $prom > 0 ? number_format($prom, 2, ',', '.') : '—' ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <?php if ($sinActividad || $diasNum === null): ?>
                                    —
                                <?php else: ?>
                                    <?= number_format($diasNum, 1, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <?= $sinActividad ? '—' : number_format($nec, 1, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <?php if ($sinActividad): ?>
                                    —
                                <?php elseif ($cajas > 0): ?>
                                    <?= $cajas ?>
                                <?php else: ?>
                                    <span class="text-slate-400" title="Sin unidades por caja">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap font-medium">
                                <?php if ($sinActividad): ?>
                                    —
                                <?php elseif (!$tienePrecio): ?>
                                    <span class="text-amber-700 text-xs">Sin precio caja</span>
                                <?php elseif ($costo > 0): ?>
                                    <?= formatPrice($costo) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-gray-500">No hay productos activos.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-500">
        Costo por caja: precio lista caja × (1 − descuento %), con el mismo descuento efectivo que en el catálogo (override o categoría).
        Cajas a pedir = techo de (promedio diario × 30) ÷ unidades por caja.
    </p>
</div>
