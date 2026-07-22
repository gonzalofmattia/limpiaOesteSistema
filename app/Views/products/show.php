<?php
$p = $product;
$isAdmin = \App\Helpers\Auth::isAdmin();
$costoMostrado = \App\Helpers\Auth::effectiveCost((float) $calc['costo']);
$margenMostrado = round((float) $calc['precio_venta'] - $costoMostrado, 2);
$stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
$stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
$stockAvailable = $stockTotal - $stockCommitted;
?>
<div class="max-w-3xl space-y-5">
    <div class="flex justify-between items-start gap-4">
        <div>
            <p class="text-xs text-slate-500 font-mono"><?= e((string) $p['code']) ?></p>
            <h2 class="text-xl font-semibold text-slate-900"><?= e((string) $p['name']) ?></h2>
            <p class="text-sm text-slate-500"><?= e((string) ($p['category_name'] ?? '—')) ?><?php if ($p['supplier_name'] ?? null): ?> · <?= e((string) $p['supplier_name']) ?><?php endif; ?></p>
        </div>
        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass((int) ($p['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>">
            <?= e(statusLabel((int) ($p['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>
        </span>
    </div>

    <?php if (!empty($p['short_description']) || !empty($p['description'])): ?>
        <div class="lo-card p-4">
            <p class="text-sm text-slate-700"><?= nl2br(e((string) ($p['short_description'] ?: $p['description']))) ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Stock total</p><p class="text-xl font-semibold"><?= $stockTotal ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Comprometido</p><p class="text-xl font-semibold <?= $stockCommitted > 0 ? 'text-amber-600' : '' ?>"><?= $stockCommitted ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Disponible</p><p class="text-xl font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Presentación</p><p class="text-sm font-medium mt-1"><?= e(productListPresentation($p)) ?></p></div>
    </div>

    <div class="lo-card p-4">
        <p class="text-xs text-slate-500 uppercase font-semibold mb-3">Precios</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <?php if ($isAdmin): ?>
                <div><p class="text-xs text-slate-500">Lista proveedor</p><p class="text-lg font-semibold"><?= formatPrice((float) $calc['precio_lista_seiq']) ?></p></div>
            <?php endif; ?>
            <div><p class="text-xs text-slate-500">Costo</p><p class="text-lg font-semibold"><?= formatPrice($costoMostrado) ?></p></div>
            <div><p class="text-xs text-slate-500">Venta</p><p class="text-lg font-semibold text-lo-blue"><?= formatPrice((float) $calc['precio_venta']) ?></p></div>
            <div><p class="text-xs text-slate-500">Margen</p><p class="text-lg font-semibold text-green-700"><?= formatPrice($margenMostrado) ?></p></div>
        </div>
    </div>

    <?php if (!empty($p['dilution']) || !empty($p['equivalence']) || !empty($p['cross_sell_tip'])): ?>
        <div class="lo-card p-4 space-y-2">
            <?php if (!empty($p['dilution'])): ?>
                <p class="text-sm"><span class="text-slate-500">Dilución:</span> <?= e((string) $p['dilution']) ?></p>
            <?php endif; ?>
            <?php if (!empty($p['equivalence'])): ?>
                <p class="text-sm"><span class="text-slate-500">Equivalencia:</span> <?= e((string) $p['equivalence']) ?></p>
            <?php endif; ?>
            <?php if (!empty($p['cross_sell_tip'])): ?>
                <p class="text-sm"><span class="text-slate-500">Tip de venta:</span> <?= e((string) $p['cross_sell_tip']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <a href="<?= e(url('/productos')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Volver</a>
        <?php if ($isAdmin): ?>
            <a href="<?= e(url('/productos/' . (int) $p['id'] . '/editar')) ?>" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm">Editar</a>
        <?php endif; ?>
    </div>
</div>
