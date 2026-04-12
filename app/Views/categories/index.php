<div class="flex justify-between items-center mb-6">
    <p class="text-sm text-gray-600">Administrá descuentos por defecto Seiq y presentación.</p>
    <a href="<?= e(url('/categorias/crear')) ?>" class="inline-flex px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Nueva categoría</a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="min-w-full text-sm">
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
            <?php foreach ($categories as $c): ?>
                <?php
                $globalMarkup = (float) (setting('default_markup', '60') ?? 60);
                $markupLabel = $c['default_markup'] !== null && $c['default_markup'] !== ''
                    ? formatPercent((float) $c['default_markup'])
                    : 'Global ' . formatPercent($globalMarkup);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= e($c['name']) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPercent((float) $c['default_discount']) ?></td>
                    <td class="px-4 py-3 text-right text-gray-600"><?= e($markupLabel) ?></td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate"><?= e($c['presentation_info'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= (int) $c['product_count'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($c['is_active']): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Activa</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="<?= e(url('/categorias/' . (int) $c['id'] . '/editar')) ?>" class="text-[#1565C0] hover:underline">Editar</a>
                        <form action="<?= e(url('/categorias/' . (int) $c['id'] . '/toggle')) ?>" method="post" class="inline">
                            <?= csrfField() ?>
                            <button type="submit" class="text-gray-500 hover:text-gray-800 text-xs"><?= $c['is_active'] ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
