<div class="flex justify-between items-center mb-6">
    <p class="text-sm text-gray-600">Listas generadas con PDF descargable.</p>
    <a href="<?= e(url('/listas/generar')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Generar nueva</a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-right px-4 py-3">Markup</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($lists as $l): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($l['name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($l['generated_at'] ?? $l['created_at']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $l['custom_markup'] !== null && $l['custom_markup'] !== '' ? formatPercent((float) $l['custom_markup']) : 'Global' ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $st = $l['status'];
                        $cls = match ($st) {
                            'active' => 'bg-green-100 text-green-800',
                            'draft' => 'bg-amber-100 text-amber-800',
                            default => 'bg-gray-100 text-gray-600',
                        };
                        ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $cls ?>"><?= e($st) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="<?= e(url('/listas/' . (int) $l['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                        <?php if (!empty($l['pdf_path'])): ?>
                            <a href="<?= e(url('/listas/' . (int) $l['id'] . '/pdf')) ?>" class="text-[#1a6b3c] hover:underline">PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
