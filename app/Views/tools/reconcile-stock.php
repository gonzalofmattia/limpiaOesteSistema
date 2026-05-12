<?php
/** @var list<array{product_id:int,code:string,name:string,committed_actual:int,committed_calculado:int,diferencia:int}> $discrepancies */
$discrepancies = $discrepancies ?? [];
$count = (int) ($count ?? 0);
?>
<div class="space-y-6 max-w-5xl">
    <div>
        <p class="text-xs text-slate-500 mb-1">
            <a href="<?= e(url('/settings')) ?>" class="text-lo-blue hover:underline">← Configuración</a>
        </p>
        <h1 class="text-xl font-semibold text-slate-900">Reconciliar stock comprometido</h1>
        <p class="text-sm text-slate-600 mt-1"><?= e((string) ($subtitle ?? '')) ?></p>
    </div>

    <div class="lo-card p-4 border border-slate-200">
        <p class="text-sm font-medium text-slate-800">
            <?php if ($count === 0): ?>
                <span class="text-emerald-700">✅ Todo sincronizado</span>
                <span class="block text-xs font-normal text-slate-500 mt-1">No hay diferencias entre <code class="text-xs bg-slate-100 px-1 rounded">stock_committed_units</code> y el cálculo desde presupuestos aceptados / parciales.</span>
            <?php else: ?>
                <span class="text-amber-800"><?= $count ?> producto<?= $count === 1 ? '' : 's' ?> con discrepancia</span>
                <span class="block text-xs font-normal text-slate-500 mt-1">Solo productos activos. El POST recalcula y corrige <strong>todos</strong> los productos con diferencia (incl. inactivos si aplica).</span>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($count > 0): ?>
        <form method="post" action="<?= e(url('/tools/reconciliar-stock/aplicar')) ?>" class="inline">
            <?= csrfField() ?>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#14542f]">
                Aplicar corrección
            </button>
        </form>

        <div class="lo-table-wrap overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-right px-3 py-2 whitespace-nowrap">Committed actual</th>
                        <th class="text-right px-3 py-2 whitespace-nowrap">Committed calculado</th>
                        <th class="text-right px-3 py-2 whitespace-nowrap">Diferencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($discrepancies as $row): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-3 py-2">
                                <span class="font-mono text-xs text-slate-500"><?= e($row['code']) ?></span>
                                <span class="block text-slate-900"><?= e($row['name']) ?></span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) $row['committed_actual'] ?></td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium"><?= (int) $row['committed_calculado'] ?></td>
                            <td class="px-3 py-2 text-right tabular-nums <?= (int) $row['diferencia'] > 0 ? 'text-amber-700' : 'text-red-700' ?>">
                                <?= (int) $row['diferencia'] >= 0 ? '+' : '' ?><?= (int) $row['diferencia'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="text-xs text-slate-500">
        No se modifica <code class="bg-slate-100 px-1 rounded">stock_units</code>. La corrección solo ajusta <code class="bg-slate-100 px-1 rounded">stock_committed_units</code> al total pendiente (accepted / partially_delivered, restando entregas parciales y explotando combos).
    </p>
</div>
