<?php
$q = $filters['q'] ?? '';
$cat = $filters['category_id'] ?? '';
$supplier = $filters['supplier'] ?? '';
$st = $filters['status'] ?? '';
$activeTab = (string) ($_GET['tab'] ?? 'productos');
if (!in_array($activeTab, ['productos', 'combos'], true)) {
    $activeTab = 'productos';
}
?>
<div x-data="{ tab: '<?= e($activeTab) ?>' }">
    <div class="flex items-center gap-2 mb-6">
        <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium"
                :class="tab === 'productos' ? 'bg-[#1a6b3c] text-white' : 'bg-white border border-gray-300 text-gray-700'"
                @click="tab='productos'; history.replaceState(null, '', window.appUrl('/productos?tab=productos'))">
            Productos
        </button>
        <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium"
                :class="tab === 'combos' ? 'bg-[#1a6b3c] text-white' : 'bg-white border border-gray-300 text-gray-700'"
                @click="tab='combos'; history.replaceState(null, '', window.appUrl('/productos?tab=combos'))">
            Combos
        </button>
    </div>

    <div x-show="tab === 'productos'">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 mb-6">
            <form method="get" class="flex flex-wrap gap-3 items-end">
                <input type="hidden" name="tab" value="productos">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Categoría</label>
                    <select name="category_id" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-[#1a6b3c]">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categoryFilterOptions as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>" <?= (string) $cat === (string) $opt['id'] ? 'selected' : '' ?>><?= e($opt['label']) ?><?= !empty($opt['is_parent']) ? ' (todos)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Proveedor</label>
                    <select name="supplier" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-[#1a6b3c]">
                        <option value="">Todos los proveedores</option>
                        <?php foreach (($suppliers ?? []) as $s): ?>
                            <option value="<?= e((string) $s['slug']) ?>" <?= (string) $supplier === (string) $s['slug'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Código o nombre"
                           class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-48 focus:ring-2 focus:ring-[#1a6b3c]">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="status" class="border border-gray-300 rounded-lg text-sm px-3 py-2">
                        <option value="">Todos</option>
                        <option value="1" <?= $st === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $st === '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm">Filtrar</button>
            </form>
            <div class="flex gap-2">
                <a href="<?= e(url('/productos/importar')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Importar CSV</a>
                <a href="<?= e(url('/productos/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Nuevo producto</a>
            </div>
        </div>

        <p class="text-sm text-gray-500 mb-2"><?= (int) $total ?> productos · página <?= (int) $page ?> de <?= (int) $pages ?></p>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Cat.</th>
                        <th class="text-left px-3 py-2">Proveedor</th>
                        <th class="text-right px-3 py-2">Stock (un.)</th>
                        <th class="text-right px-3 py-2">Lista</th>
                        <th class="text-right px-3 py-2">Costo LO</th>
                        <th class="text-right px-3 py-2">Venta</th>
                        <th class="text-right px-3 py-2">Margen</th>
                        <th class="text-center px-3 py-2">Estado</th>
                        <th class="text-right px-3 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($products as $p): ?>
                        <?php $calc = $p['_pricing']; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs"><?= e($p['code']) ?></td>
                            <td class="px-3 py-2 max-w-[200px] truncate" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></td>
                            <td class="px-3 py-2 text-gray-600"><?= e($p['category_name']) ?></td>
                            <td class="px-3 py-2 text-gray-600"><?= e((string) ($p['supplier_name'] ?? '—')) ?></td>
                            <td class="px-3 py-2 text-right"><?= (int) ($p['stock_units'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-right"><?= formatPrice($calc['precio_lista_seiq']) ?></td>
                            <td class="px-3 py-2 text-right"><?= formatPrice($calc['costo']) ?></td>
                            <td class="px-3 py-2 text-right font-medium"><?= formatPrice($calc['precio_venta']) ?></td>
                            <td class="px-3 py-2 text-right text-green-700"><?= formatPrice($calc['margen_pesos']) ?></td>
                            <td class="px-3 py-2 text-center"><?= $p['is_active'] ? '<span class="text-green-600">●</span>' : '<span class="text-gray-300">●</span>' ?></td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="<?= e(url('/productos/' . (int) $p['id'] . '/editar')) ?>" class="text-[#1565C0] hover:underline">Editar</a>
                                <form action="<?= e(url('/productos/' . (int) $p['id'] . '/toggle')) ?>" method="post" class="inline ml-2">
                                    <?= csrfField() ?>
                                    <button type="submit" class="text-xs text-gray-500 hover:text-gray-800"><?= $p['is_active'] ? 'Off' : 'On' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="flex justify-center gap-2 mt-6">
                <?php $qs = $_GET; for ($i = 1; $i <= $pages; $i++): $qs['page'] = $i; $url = rtrim(url('/productos'), '/') . '?' . http_build_query($qs); ?>
                    <a href="<?= e($url) ?>" class="px-3 py-1 rounded text-sm <?= $i === (int) $page ? 'bg-[#1a6b3c] text-white' : 'bg-white border border-gray-200 hover:bg-gray-50' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <div x-show="tab === 'combos'" x-cloak>
        <div class="flex justify-end mb-4">
            <a href="<?= e(url('/combos/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Crear combo</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-right px-3 py-2">Productos</th>
                        <th class="text-right px-3 py-2">Subtotal</th>
                        <th class="text-right px-3 py-2">Descuento %</th>
                        <th class="text-right px-3 py-2">Precio final</th>
                        <th class="text-center px-3 py-2">Estado</th>
                        <th class="text-right px-3 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach (($combos ?? []) as $c): ?>
                        <?php
                        $subtotal = (float) ($c['_subtotal'] ?? 0);
                        $discount = (float) ($c['discount_percentage'] ?? 0);
                        $final = (float) ($c['_final_price'] ?? round($subtotal * (1 - ($discount / 100)), 2));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2"><?= e((string) $c['name']) ?></td>
                            <td class="px-3 py-2 text-right"><?= (int) ($c['products_count'] ?? 0) ?> productos</td>
                            <td class="px-3 py-2 text-right"><?= formatPrice($subtotal) ?></td>
                            <td class="px-3 py-2 text-right"><?= number_format($discount, 2, ',', '.') ?>%</td>
                            <td class="px-3 py-2 text-right font-medium"><?= formatPrice($final) ?></td>
                            <td class="px-3 py-2 text-center">
                                <?php if ((int) ($c['is_active'] ?? 0) === 1): ?>
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Activo</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-600">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="<?= e(url('/combos/' . (int) $c['id'] . '/editar')) ?>" class="text-[#1565C0] hover:underline">Editar</a>
                                <form action="<?= e(url('/combos/' . (int) $c['id'] . '/toggle')) ?>" method="post" class="inline ml-2">
                                    <?= csrfField() ?>
                                    <button type="submit" class="text-xs text-gray-500 hover:text-gray-800"><?= (int) ($c['is_active'] ?? 0) === 1 ? 'Off' : 'On' ?></button>
                                </form>
                                <form action="<?= e(url('/combos/' . (int) $c['id'] . '/eliminar')) ?>" method="post" class="inline ml-2" onsubmit="return confirm('¿Eliminar combo?');">
                                    <?= csrfField() ?>
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
