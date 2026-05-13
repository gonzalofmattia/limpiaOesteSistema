<?php
$skuCount = (int) ($total ?? count($products));
$stockValue = 0.0;
$sinStock = 0;
$reponer = 0;
$enCaminoTotal = 0;
$bajoMinimo = 0;
foreach (($products ?? []) as $p) {
    $stock = (int) ($p['stock_units'] ?? 0);
    $inTransit = (int) ($p['in_transit_units'] ?? 0);
    $min = (int) ($p['units_per_box'] ?? 0);
    $stockValue += max(0, $stock) * (float) ($p['cost'] ?? 0);
    $enCaminoTotal += max(0, $inTransit);
    if ($stock <= 0) { $sinStock++; }
    if ($stock > 0 && $min > 0 && $stock < $min) { $reponer++; }
    if (($p['stock_minimum'] ?? null) !== null && $stock < (int) $p['stock_minimum']) { $bajoMinimo++; }
}
$lowStockCountGlobal = (int) ($lowStockCount ?? $bajoMinimo);
$currentFilter = $stockFilter ?? '';
$loStockListPath = parse_url(url('/stock-actual'), PHP_URL_PATH) ?: '/stock-actual';
$loFilterStockActive = trim((string) ($q ?? '')) !== '' || $currentFilter === 'bajo' || (int) ($per_page ?? 50) !== 50;
?>
<div class="space-y-5"
     data-lo-filter-persist
     data-lo-filter-page="stock-actual"
     data-lo-filter-keys="search,stock_filter,per_page"
     data-lo-filter-list-path="<?= e($loStockListPath) ?>"
     data-lo-filter-clear-url="<?= e(url('/stock-actual')) ?>">
<div class="flex items-center justify-end">
    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <button type="button" class="inline-flex w-full sm:w-auto min-h-11 items-center justify-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">Salida</button>
        <button type="button" class="inline-flex w-full sm:w-auto min-h-11 items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Ingreso</button>
    </div>
</div>
<div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">SKUs</p><p class="text-2xl font-semibold"><?= $skuCount ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Valorizado al costo</p><p class="text-xl font-semibold"><?= formatPrice($stockValue) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Sin stock</p><p class="text-2xl font-semibold text-red-600"><?= $sinStock ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Para reponer</p><p class="text-2xl font-semibold text-amber-600"><?= $reponer ?></p></div>
    <a href="<?= e(url('/stock-actual?stock_filter=bajo')) ?>" class="lo-card p-4 <?= $lowStockCountGlobal > 0 ? 'border-red-300 bg-red-50' : '' ?>">
        <p class="text-xs text-slate-500">Bajo mínimo</p>
        <p class="text-2xl font-semibold <?= $lowStockCountGlobal > 0 ? 'text-red-700' : 'text-slate-400' ?>"><?= $lowStockCountGlobal ?></p>
    </a>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">En camino (un.)</p><p class="text-2xl font-semibold text-blue-700"><?= $enCaminoTotal ?></p></div>
</div>
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
<form method="get" class="flex items-center gap-2 flex-1 min-w-0">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 50) ?>">
        <?php if ($currentFilter === 'bajo'): ?>
            <input type="hidden" name="stock_filter" value="bajo">
        <?php endif; ?>
    <div class="flex-1 min-h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400 shrink-0"></i><input type="text" name="search" value="<?= e((string) ($q ?? '')) ?>" placeholder="Buscar producto..." class="w-full min-h-11 bg-transparent outline-none text-base md:text-sm"></div>
    <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
</form>
<?php if ($loFilterStockActive): ?>
    <button type="button" data-lo-filter-clear class="shrink-0 inline-flex min-h-11 items-center justify-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar filtros</button>
<?php endif; ?>
</div>
<div class="flex gap-2 overflow-x-auto pb-1">
    <a href="<?= e(url('/stock-actual' . ($q !== '' ? '?search=' . urlencode($q) : ''))) ?>"
       class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $currentFilter === '' ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Todos <span class="ml-1 text-[10px]"><?= $skuCount ?></span>
    </a>
    <a href="<?= e(url('/stock-actual?stock_filter=bajo' . ($q !== '' ? '&search=' . urlencode($q) : ''))) ?>"
       class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $currentFilter === 'bajo' ? 'bg-red-700 text-white' : 'border border-red-200 text-red-700 hover:bg-red-50' ?>">
        Stock bajo <span class="ml-1 text-[10px]"><?= $lowStockCountGlobal ?></span>
    </a>
    <a href="<?= e(url('/stock-actual/reposicion')) ?>"
       class="px-3 h-8 rounded-full border border-emerald-200 text-emerald-700 hover:bg-emerald-50 inline-flex items-center text-xs font-semibold">
        📊 Sugerencia de reposición
    </a>
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
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nuevo stock (un.)</label>
                <input type="number" min="0" name="new_stock" required
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Motivo (opcional)</label>
                <input type="text" name="notes" placeholder="Conteo físico"
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <button type="submit" class="min-h-11 px-4 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-base md:text-sm font-medium w-full md:w-auto">Guardar ajuste</button>
        </form>
    <?php endif; ?>
</div>

<p class="text-sm text-gray-500 mb-2"><?= (int) ($total ?? count($products)) ?> productos con stock o en camino</p>

<div class="lo-table-wrap hidden md:block">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
            <tr>
                <th class="text-left px-3 py-2">Código</th>
                <th class="text-left px-3 py-2">Producto</th>
                <th class="text-left px-3 py-2">Categoría</th>
                <th class="text-right px-3 py-2">Uds/Caja</th>
                <th class="text-right px-3 py-2">Stock total</th>
                <th class="text-right px-3 py-2">Mínimo</th>
                <th class="text-right px-3 py-2">Comprometido</th>
                <th class="text-right px-3 py-2">Disponible</th>
                <th class="text-right px-3 py-2">En camino</th>
                <th class="text-right px-3 py-2">Disp. + camino</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($products as $p): ?>
                <?php
                $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
                $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
                $stockAvailable = $stockTotal - $stockCommitted;
                $inTransit = max(0, (int) ($p['in_transit_units'] ?? 0));
                $availablePlusTransit = $stockAvailable + $inTransit;
                $minimo = $p['stock_minimum'] ?? null;
                $isBajoMinimo = $minimo !== null && $stockTotal < (int) $minimo;
                ?>
                <tr class="<?= $isBajoMinimo ? 'bg-red-50 text-red-700' : 'hover:bg-gray-50' ?>">
                    <td class="px-3 py-2 font-mono text-xs"><?= e((string) $p['code']) ?></td>
                    <?php if ((int) ($p['is_active'] ?? 1) !== 1): ?>
                        <td class="px-3 py-2">
                            <span class="lo-truncate" title="<?= e((string) $p['name']) ?>"><?= e((string) $p['name']) ?></span>
                            <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">Inactivo</span>
                        </td>
                    <?php else: ?>
                        <td class="px-3 py-2"><span class="lo-truncate" title="<?= e((string) $p['name']) ?>"><?= e((string) $p['name']) ?></span></td>
                    <?php endif; ?>
                    <td class="px-3 py-2 text-gray-600"><?= e((string) $p['category_name']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= (int) ($p['units_per_box'] ?? 1) ?></td>
                    <td class="px-3 py-2 text-right"><?= $stockTotal ?></td>
                    <td class="px-3 py-2 text-right <?= $isBajoMinimo ? 'font-bold text-red-700' : 'text-gray-500' ?>"><?= $minimo !== null ? (int) $minimo : '—' ?></td>
                    <td class="px-3 py-2 text-right <?= $stockCommitted > 0 ? 'text-amber-600 font-medium' : 'text-gray-500' ?>"><?= $stockCommitted ?></td>
                    <td class="px-3 py-2 text-right font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></td>
                    <td class="px-3 py-2 text-right <?= $inTransit > 0 ? 'text-blue-700 font-semibold' : 'text-gray-500' ?>"><?= $inTransit ?></td>
                    <td class="px-3 py-2 text-right font-semibold <?= $availablePlusTransit > 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= $availablePlusTransit ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="10" class="px-3 py-6 text-center text-gray-500">No hay productos con stock para mostrar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="md:hidden lo-mobile-card-list">
    <?php if (($products ?? []) === []): ?>
        <p class="text-center text-slate-500 py-10 text-sm">No hay productos con stock para mostrar.</p>
    <?php endif; ?>
    <?php foreach (($products ?? []) as $p): ?>
        <?php
        $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
        $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
        $stockAvailable = $stockTotal - $stockCommitted;
        $inTransit = max(0, (int) ($p['in_transit_units'] ?? 0));
        $availablePlusTransit = $stockAvailable + $inTransit;
        $minimo = $p['stock_minimum'] ?? null;
        $isBajoMinimo = $minimo !== null && $stockTotal < (int) $minimo;
        ?>
        <article class="lo-mobile-card shadow-sm <?= $isBajoMinimo ? 'border-red-200 bg-red-50/40' : '' ?>">
            <div class="flex items-start justify-between gap-2 mb-1">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-slate-500"><?= e((string) $p['code']) ?></p>
                    <p class="text-base font-semibold text-slate-900 leading-snug"><?= e((string) $p['name']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?= e((string) $p['category_name']) ?></p>
                </div>
                <?php if ($isBajoMinimo): ?>
                    <span class="shrink-0 inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-800">Bajo mín.</span>
                <?php elseif ((int) ($p['is_active'] ?? 1) !== 1): ?>
                    <span class="shrink-0 inline-flex px-2 py-1 rounded-full text-[10px] bg-slate-100 text-slate-600">Inactivo</span>
                <?php endif; ?>
            </div>
            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm mt-3 pt-3 border-t border-slate-100">
                <div><dt class="text-xs text-slate-500">Stock total</dt><dd class="font-semibold"><?= $stockTotal ?></dd></div>
                <div><dt class="text-xs text-slate-500">Disponible</dt><dd class="font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></dd></div>
                <div><dt class="text-xs text-slate-500">Comprometido</dt><dd class="<?= $stockCommitted > 0 ? 'text-amber-600 font-medium' : 'text-slate-600' ?>"><?= $stockCommitted ?></dd></div>
                <div><dt class="text-xs text-slate-500">En camino</dt><dd class="<?= $inTransit > 0 ? 'text-blue-700 font-semibold' : 'text-slate-600' ?>"><?= $inTransit ?></dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Disp. + en camino</dt><dd class="font-semibold <?= $availablePlusTransit > 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= $availablePlusTransit ?></dd></div>
            </dl>
        </article>
    <?php endforeach; ?>
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
