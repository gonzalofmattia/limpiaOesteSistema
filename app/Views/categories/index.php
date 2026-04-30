<?php /** @var list<array<string,mixed>> $categoryTree */ ?>
<?php
$totalCategories = 0;
$totalProducts = 0;
$emptyCategories = 0;
foreach ($categoryTree as $root) {
    $totalCategories++;
    $rootProducts = (int) ($root['product_count'] ?? 0);
    $totalProducts += $rootProducts;
    if ($rootProducts <= 0) { $emptyCategories++; }
    foreach (($root['children'] ?? []) as $c) {
        $totalCategories++;
        $childProducts = (int) ($c['product_count'] ?? 0);
        $totalProducts += $childProducts;
        if ($childProducts <= 0) { $emptyCategories++; }
    }
}
?>
<div class="space-y-5">
    <div class="flex justify-end items-center">
        <a href="<?= e(url('/categorias/crear')) ?>" class="lo-btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Nueva categoría</a>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Total categorías</p><p class="text-2xl font-semibold"><?= $totalCategories ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Productos vinculados</p><p class="text-2xl font-semibold"><?= $totalProducts ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Más popular</p><p class="text-xl font-semibold"><?= e((string) (($categoryTree[0]['name'] ?? '—'))) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Vacías</p><p class="text-2xl font-semibold"><?= $emptyCategories ?></p></div>
    </div>
    <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
        <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar categoría..." class="w-full bg-transparent outline-none text-sm"></div>
        <button class="h-11 w-11 rounded-xl border border-lo-border bg-white grid place-items-center"><i data-lucide="sliders-horizontal" class="h-4 w-4"></i></button>
    </form>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todas <span class="ml-1 text-[10px]"><?= $totalCategories ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Con productos <span class="ml-1 text-[10px]"><?= $totalCategories - $emptyCategories ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Vacías <span class="ml-1 text-[10px]"><?= $emptyCategories ?></span></span>
    </div>
    <div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-right px-4 py-3">Desc. Seiq</th>
                <th class="text-right px-4 py-3">Markup</th>
                <th class="text-left px-4 py-3">Presentación</th>
                <th class="text-right px-4 py-3">Productos</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php
            $globalMarkup = (float) (setting('default_markup', '60') ?? 60);
            foreach ($categoryTree as $root):
                $rootMarkupLabel = $root['default_markup'] !== null && $root['default_markup'] !== ''
                    ? formatPercent((float) $root['default_markup'])
                    : 'Global ' . formatPercent($globalMarkup);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><span class="lo-truncate" title="<?= e($root['name']) ?>"><?= e($root['name']) ?></span></td>
                    <td class="px-4 py-3 text-right"><?= formatPercent((float) $root['default_discount']) ?></td>
                    <td class="px-4 py-3 text-right text-gray-600"><?= e($rootMarkupLabel) ?></td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate"><?= e($root['presentation_info'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= (int) $root['product_count'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($root['is_active']): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Activa</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                        <a href="<?= e(url('/categorias/' . (int) $root['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                            <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                        </a>
                        <form action="<?= e(url('/categorias/' . (int) $root['id'] . '/toggle')) ?>" method="post" class="inline">
                            <?= csrfField() ?>
                            <button type="submit" class="<?= $root['is_active'] ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-700' ?> transition hover:scale-105" title="<?= $root['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                    <i data-lucide="<?= $root['is_active'] ? 'toggle-right' : 'toggle-left' ?>" class="w-5 h-5 <?= $root['is_active'] ? 'text-green-500' : 'text-gray-400' ?>"></i>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
                <?php
                $children = $root['children'] ?? [];
                $n = count($children);
                foreach ($children as $i => $c):
                    $isLast = $i === $n - 1;
                    $branch = $isLast ? '└─' : '├─';
                    $pd = (float) $c['default_discount'];
                    $parentD = isset($root['default_discount']) ? (float) $root['default_discount'] : 0.0;
                    $showDisc = abs($pd - $parentD) > 0.0001;
                    $markupLabel = $c['default_markup'] !== null && $c['default_markup'] !== ''
                        ? formatPercent((float) $c['default_markup'])
                        : $rootMarkupLabel;
                    $parentM = $root['default_markup'];
                    $childM = $c['default_markup'];
                    $showMarkup = ($childM !== null && $childM !== '')
                        && ($parentM === null || $parentM === '' || (float) $childM !== (float) $parentM);
                    ?>
                    <tr class="hover:bg-gray-50 bg-gray-50/50">
                        <td class="px-4 py-3 pl-8 text-gray-800">
                            <span class="text-gray-400 font-mono text-xs mr-1"><?= e($branch) ?></span><span class="inline-block align-middle truncate max-w-[220px]" title="<?= e($c['name']) ?>"><?= e($c['name']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right"><?= $showDisc ? formatPercent($pd) : '—' ?></td>
                        <td class="px-4 py-3 text-right text-gray-600"><?= $showMarkup ? e($markupLabel) : '—' ?></td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate"><?= e($c['presentation_info'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $c['product_count'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($c['is_active']): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Activa</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                            <a href="<?= e(url('/categorias/' . (int) $c['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                            </a>
                            <form action="<?= e(url('/categorias/' . (int) $c['id'] . '/toggle')) ?>" method="post" class="inline">
                                <?= csrfField() ?>
                                <button type="submit" class="<?= $c['is_active'] ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-700' ?> transition hover:scale-105" title="<?= $c['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                    <i data-lucide="<?= $c['is_active'] ? 'toggle-right' : 'toggle-left' ?>" class="w-5 h-5 <?= $c['is_active'] ? 'text-green-500' : 'text-gray-400' ?>"></i>
                                </button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
</div>
