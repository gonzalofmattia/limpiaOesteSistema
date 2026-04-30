<?php
$skuCount = (int) ($total ?? count($products));
$stockValue = 0.0;
$sinStock = 0;
$reponer = 0;
foreach (($products ?? []) as $p) {
    $stock = (int) ($p['stock_units'] ?? 0);
    $min = (int) ($p['units_per_box'] ?? 0);
    $stockValue += max(0, $stock) * (float) ($p['cost'] ?? 0);
    if ($stock <= 0) { $sinStock++; }
    if ($stock > 0 && $min > 0 && $stock < $min) { $reponer++; }
}
?>
<div class="space-y-5">
<div class="flex items-center justify-end">
    <div class="flex gap-2">
        <button class="h-10 px-4 rounded-xl border border-lo-border bg-white text-sm font-medium">Salida</button>
        <button class="h-10 px-4 rounded-xl bg-lo-blue text-white text-sm font-medium">Ingreso</button>
    </div>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">SKUs</p><p class="text-2xl font-semibold"><?= $skuCount ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Valorizado al costo</p><p class="text-xl font-semibold"><?= formatPrice($stockValue) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Sin stock</p><p class="text-2xl font-semibold text-red-600"><?= $sinStock ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Para reponer</p><p class="text-2xl font-semibold text-amber-600"><?= $reponer ?></p></div>
</div>
<form method="get" class="flex items-center gap-2">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($q ?? '')) ?>" placeholder="Buscar producto..." class="w-full bg-transparent outline-none text-sm"></div>
    <button class="h-11 w-11 rounded-xl border border-lo-border bg-white grid place-items-center"><i data-lucide="sliders-horizontal" class="h-4 w-4"></i></button>
</form>
<div class="flex gap-2 overflow-x-auto pb-1">
    <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todos <span class="ml-1 text-[10px]"><?= $skuCount ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Sin stock <span class="ml-1 text-[10px]"><?= $sinStock ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Reponer <span class="ml-1 text-[10px]"><?= $reponer ?></span></span>
    <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">OK <span class="ml-1 text-[10px]"><?= max(0, $skuCount - $sinStock - $reponer) ?></span></span>
</div>

<div class="lo-card p-6">
    <h3 class="text-sm font-semibold text-gray-800 mb-3">Ajuste manual de stock</h3>
    <?php if (empty($hasAdjustmentsTable)): ?>
        <p class="text-sm text-red-700">
            Falta la tabla de historial de ajustes. Ejecutá la migración <code>database/migrations/2026_04_28_stock_adjustments.sql</code>.
        </p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/stock-actual/ajustar')) ?>" class="grid md:grid-cols-4 gap-3 items-end">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Código de producto</label>
                <input type="text" name="product_code" required placeholder="Ej: ECOLH05"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nuevo stock (un.)</label>
                <input type="number" min="0" name="new_stock" required
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Motivo (opcional)</label>
                <input type="text" name="notes" placeholder="Conteo físico"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar ajuste</button>
        </form>
    <?php endif; ?>
</div>

<p class="text-sm text-gray-500 mb-2"><?= (int) ($total ?? count($products)) ?> productos con stock mayor a 0</p>

<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
            <tr>
                <th class="text-left px-3 py-2">Código</th>
                <th class="text-left px-3 py-2">Producto</th>
                <th class="text-left px-3 py-2">Categoría</th>
                <th class="text-right px-3 py-2">Unidades por caja</th>
                <th class="text-right px-3 py-2">Stock total</th>
                <th class="text-right px-3 py-2">Comprometido</th>
                <th class="text-right px-3 py-2">Disponible</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($products as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs"><?= e((string) $p['code']) ?></td>
                    <td class="px-3 py-2"><span class="lo-truncate" title="<?= e((string) $p['name']) ?>"><?= e((string) $p['name']) ?></span></td>
                    <td class="px-3 py-2 text-gray-600"><?= e((string) $p['category_name']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= (int) ($p['units_per_box'] ?? 1) ?></td>
                    <?php
                    $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
                    $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
                    $stockAvailable = $stockTotal - $stockCommitted;
                    ?>
                    <td class="px-3 py-2 text-right"><?= $stockTotal ?></td>
                    <td class="px-3 py-2 text-right <?= $stockCommitted > 0 ? 'text-amber-600 font-medium' : 'text-gray-500' ?>"><?= $stockCommitted ?></td>
                    <td class="px-3 py-2 text-right font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay productos con stock para mostrar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>

<?php if (!empty($hasAdjustmentsTable)): ?>
    <div class="lo-table-wrap mt-6">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Últimos ajustes</h3>
        </div>
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                <tr>
                    <th class="text-left px-3 py-2">Fecha</th>
                    <th class="text-left px-3 py-2">Código</th>
                    <th class="text-left px-3 py-2">Producto</th>
                    <th class="text-right px-3 py-2">Antes</th>
                    <th class="text-right px-3 py-2">Después</th>
                    <th class="text-right px-3 py-2">Diferencia</th>
                    <th class="text-left px-3 py-2">Usuario</th>
                    <th class="text-left px-3 py-2">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach (($adjustments ?? []) as $a): ?>
                    <tr>
                        <td class="px-3 py-2 text-gray-600"><?= e((string) ($a['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 font-mono text-xs"><?= e((string) ($a['code'] ?? '')) ?></td>
                        <td class="px-3 py-2"><span class="lo-truncate" title="<?= e((string) ($a['name'] ?? '')) ?>"><?= e((string) ($a['name'] ?? '')) ?></span></td>
                        <td class="px-3 py-2 text-right"><?= (int) ($a['previous_stock'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-right"><?= (int) ($a['new_stock'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-right <?= (int) ($a['difference'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                            <?= (int) ($a['difference'] ?? 0) >= 0 ? '+' : '' ?><?= (int) ($a['difference'] ?? 0) ?>
                        </td>
                        <td class="px-3 py-2"><?= e((string) ($a['created_by'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= e((string) ($a['notes'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($adjustments ?? []) === []): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-gray-500">Todavía no hay ajustes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
