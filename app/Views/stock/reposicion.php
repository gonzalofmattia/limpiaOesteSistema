<?php
$rows = $rows ?? [];
$totalRows = count($rows);
$bajoMinimo = 0;
$alertaMenos15 = 0;
foreach ($rows as $r) {
    $stock = (int) ($r['stock_units'] ?? 0);
    $min = $r['stock_minimum'] !== null ? (int) $r['stock_minimum'] : null;
    $promDiario = (float) ($r['promedio_diario'] ?? 0);
    if ($min !== null && $stock < $min) { $bajoMinimo++; }
    elseif ($promDiario > 0 && ($stock / $promDiario) < 15) { $alertaMenos15++; }
}
?>
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= e(url('/stock-actual')) ?>" class="text-xs text-lo-blue hover:underline">← Volver a stock</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Productos analizados</p><p class="text-2xl font-semibold"><?= $totalRows ?></p></div>
        <div class="lo-card p-4 <?= $bajoMinimo > 0 ? 'border-red-300 bg-red-50' : '' ?>"><p class="text-xs text-slate-500">🔴 Bajo mínimo</p><p class="text-2xl font-semibold <?= $bajoMinimo > 0 ? 'text-red-700' : '' ?>"><?= $bajoMinimo ?></p></div>
        <div class="lo-card p-4 <?= $alertaMenos15 > 0 ? 'border-amber-300 bg-amber-50' : '' ?>"><p class="text-xs text-slate-500">🟡 Menos de 15 días</p><p class="text-2xl font-semibold <?= $alertaMenos15 > 0 ? 'text-amber-700' : '' ?>"><?= $alertaMenos15 ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">🟢 OK</p><p class="text-2xl font-semibold text-emerald-700"><?= max(0, $totalRows - $bajoMinimo - $alertaMenos15) ?></p></div>
    </div>

    <p class="text-xs text-slate-500">Basado en ventas de los últimos 90 días. Solo productos con stock mínimo configurado.</p>

    <div class="lo-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-right px-3 py-2">Stock actual</th>
                        <th class="text-right px-3 py-2">Mínimo</th>
                        <th class="text-right px-3 py-2">Disponible</th>
                        <th class="text-right px-3 py-2">Vendido 90d</th>
                        <th class="text-right px-3 py-2">Prom/día</th>
                        <th class="text-center px-3 py-2">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r):
                        $stock = (int) ($r['stock_units'] ?? 0);
                        $min = $r['stock_minimum'] !== null ? (int) $r['stock_minimum'] : null;
                        $committed = (int) ($r['stock_committed_units'] ?? 0);
                        $disponible = $stock - $committed;
                        $vendido = (int) ($r['vendido_90d'] ?? 0);
                        $promDiario = (float) ($r['promedio_diario'] ?? 0);
                        $diasStock = $promDiario > 0 ? round($stock / $promDiario, 0) : null;

                        if ($min !== null && $stock < $min) {
                            $estado = '🔴 Bajo mínimo';
                            $estadoClass = 'text-red-700 font-semibold';
                            $rowClass = 'bg-red-50';
                        } elseif ($promDiario > 0 && $diasStock !== null && $diasStock < 15) {
                            $estado = '🟡 &lt;15 días';
                            $estadoClass = 'text-amber-700 font-semibold';
                            $rowClass = 'bg-amber-50';
                        } else {
                            $estado = '🟢 OK';
                            $estadoClass = 'text-emerald-700';
                            $rowClass = 'hover:bg-gray-50';
                        }
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="px-3 py-2">
                                <span class="font-mono text-xs text-slate-500"><?= e((string) $r['code']) ?></span>
                                <span class="ml-2 lo-truncate" title="<?= e((string) $r['name']) ?>"><?= e((string) $r['name']) ?></span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold"><?= $stock ?></td>
                            <td class="px-3 py-2 text-right"><?= $min !== null ? $min : '—' ?></td>
                            <td class="px-3 py-2 text-right <?= $disponible < 0 ? 'text-red-700 font-bold' : '' ?>"><?= $disponible ?></td>
                            <td class="px-3 py-2 text-right"><?= number_format($vendido) ?></td>
                            <td class="px-3 py-2 text-right"><?= $promDiario > 0 ? number_format($promDiario, 1, ',', '.') : '—' ?></td>
                            <td class="px-3 py-2 text-center whitespace-nowrap <?= $estadoClass ?>">
                                <?= $estado ?>
                                <?php if ($diasStock !== null && $promDiario > 0): ?>
                                    <span class="text-[10px] text-slate-500 ml-1">(<?= (int) $diasStock ?>d)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-gray-500">
                                No hay productos con stock mínimo configurado. Configurá el mínimo desde el formulario de producto.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
