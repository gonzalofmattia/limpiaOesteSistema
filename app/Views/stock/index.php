<div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 mb-6">
    <form method="get" class="flex gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
            <input type="text" name="q" value="<?= e((string) ($q ?? '')) ?>" placeholder="Código o nombre"
                   class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-64 focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm">Filtrar</button>
    </form>
    <a href="<?= e(url('/productos')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Ir a Productos</a>
</div>

<p class="text-sm text-gray-500 mb-2"><?= count($products) ?> productos con stock mayor a 0</p>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
            <tr>
                <th class="text-left px-3 py-2">Código</th>
                <th class="text-left px-3 py-2">Producto</th>
                <th class="text-left px-3 py-2">Categoría</th>
                <th class="text-right px-3 py-2">Unidades por caja</th>
                <th class="text-right px-3 py-2">Stock (un.)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($products as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs"><?= e((string) $p['code']) ?></td>
                    <td class="px-3 py-2"><?= e((string) $p['name']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= e((string) $p['category_name']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= (int) ($p['units_per_box'] ?? 1) ?></td>
                    <td class="px-3 py-2 text-right font-semibold text-[#1a6b3c]"><?= (int) ($p['stock_units'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">No hay productos con stock para mostrar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
