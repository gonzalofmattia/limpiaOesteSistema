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
<div x-data="{ tab: '<?= e($activeTab) ?>' }" x-effect="$nextTick(() => window.lucide && window.lucide.createIcons())">
    <div class="mb-5"><h2 class="text-2xl font-semibold">Productos</h2><p class="text-sm text-slate-500">Catálogo y combos con estado de stock.</p></div>
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
                    <input type="text" name="search" value="<?= e($q) ?>" placeholder="Buscar..."
                           class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-48 focus:ring-2 focus:ring-[#1a6b3c]">
                </div>
                <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 25) ?>">
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
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Cat.</th>
                        <th class="text-left px-3 py-2">Proveedor</th>
                        <th class="text-right px-3 py-2">Stock total</th>
                        <th class="text-right px-3 py-2">Comprometido</th>
                        <th class="text-right px-3 py-2">Disponible</th>
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
                            <?php
                            $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
                            $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
                            $stockAvailable = $stockTotal - $stockCommitted;
                            ?>
                            <td class="px-3 py-2 text-right"><?= $stockTotal ?></td>
                            <td class="px-3 py-2 text-right <?= $stockCommitted > 0 ? 'text-amber-600 font-medium' : 'text-gray-500' ?>"><?= $stockCommitted ?></td>
                            <td class="px-3 py-2 text-right font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></td>
                            <td class="px-3 py-2 text-right"><?= formatPrice($calc['precio_lista_seiq']) ?></td>
                            <td class="px-3 py-2 text-right"><?= formatPrice($calc['costo']) ?></td>
                            <td class="px-3 py-2 text-right font-medium"><?= formatPrice($calc['precio_venta']) ?></td>
                            <td class="px-3 py-2 text-right text-green-700"><?= formatPrice($calc['margen_pesos']) ?></td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass((int) ($p['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>">
                                    <?= e(statusLabel((int) ($p['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-2">
                                <a href="<?= e(url('/productos/' . (int) $p['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                    <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                                </a>
                                <form action="<?= e(url('/productos/' . (int) $p['id'] . '/toggle')) ?>" method="post" class="inline">
                                    <?= csrfField() ?>
                                    <button type="submit" class="<?= $p['is_active'] ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-700' ?> transition hover:scale-105" title="<?= $p['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                        <i data-lucide="<?= $p['is_active'] ? 'toggle-right' : 'toggle-left' ?>" class="w-5 h-5 <?= $p['is_active'] ? 'text-green-500' : 'text-gray-400' ?>"></i>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $per_page = $per_page ?? 25;
        $total_pages = $pages ?? 1;
        require APP_PATH . '/Views/layout/pagination.php';
        ?>
    </div>

    <div x-show="tab === 'combos'" x-cloak>
        <div class="flex justify-end mb-4">
            <a href="<?= e(url('/combos/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Crear combo</a>
        </div>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
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
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass((int) ($c['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>">
                                    <?= e(statusLabel((int) ($c['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-2">
                                <a href="<?= e(url('/combos/' . (int) $c['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                    <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                                </a>
                                <form action="<?= e(url('/combos/' . (int) $c['id'] . '/toggle')) ?>" method="post" class="inline">
                                    <?= csrfField() ?>
                                    <button type="submit" class="<?= (int) ($c['is_active'] ?? 0) === 1 ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-700' ?> transition hover:scale-105" title="<?= (int) ($c['is_active'] ?? 0) === 1 ? 'Desactivar' : 'Activar' ?>">
                                        <i data-lucide="<?= (int) ($c['is_active'] ?? 0) === 1 ? 'toggle-right' : 'toggle-left' ?>" class="w-5 h-5 <?= (int) ($c['is_active'] ?? 0) === 1 ? 'text-green-500' : 'text-gray-400' ?>"></i>
                                    </button>
                                </form>
                                <?php $deleteFormId = 'delete-combo-' . (int) $c['id']; ?>
                                <span class="mx-1 h-4 w-px bg-gray-200 inline-block"></span>
                                <form id="<?= e($deleteFormId) ?>" action="<?= e(url('/combos/' . (int) $c['id'] . '/eliminar')) ?>" method="post" class="inline">
                                    <?= csrfField() ?>
                                    <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el combo <?= e((string) $c['name']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
                                        <i data-lucide="trash-2" class="w-5 h-5 text-red-400 hover:text-red-600"></i>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
