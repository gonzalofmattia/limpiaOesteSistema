<?php
$q = $filters['q'] ?? '';
$cat = $filters['category_id'] ?? '';
$st = $filters['status'] ?? '';
?>
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 mb-6">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Categoría</label>
            <select name="category_id" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-[#1a6b3c]">
                <option value="">Todas</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) $cat === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
                    <td class="px-3 py-2 text-right"><?= formatPrice($calc['precio_lista_seiq']) ?></td>
                    <td class="px-3 py-2 text-right"><?= formatPrice($calc['costo']) ?></td>
                    <td class="px-3 py-2 text-right font-medium"><?= formatPrice($calc['precio_venta']) ?></td>
                    <td class="px-3 py-2 text-right text-green-700"><?= formatPrice($calc['margen_pesos']) ?></td>
                    <td class="px-3 py-2 text-center">
                        <?= $p['is_active'] ? '<span class="text-green-600">●</span>' : '<span class="text-gray-300">●</span>' ?>
                    </td>
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
        <?php
        $qs = $_GET;
        for ($i = 1; $i <= $pages; $i++):
            $qs['page'] = $i;
            $url = rtrim(url('/productos'), '/') . '?' . http_build_query($qs);
            ?>
            <a href="<?= e($url) ?>" class="px-3 py-1 rounded text-sm <?= $i === (int) $page ? 'bg-[#1a6b3c] text-white' : 'bg-white border border-gray-200 hover:bg-gray-50' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
