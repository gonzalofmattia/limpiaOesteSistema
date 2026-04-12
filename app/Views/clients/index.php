<div class="flex justify-between mb-6">
    <p class="text-sm text-gray-600">Clientes para presupuestos.</p>
    <a href="<?= e(url('/clientes/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Nuevo cliente</a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Razón social</th>
                <th class="text-left px-4 py-3">Teléfono</th>
                <th class="text-left px-4 py-3">Ciudad</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($c['business_name'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= e($c['phone'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= e($c['city'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= e(url('/clientes/' . (int) $c['id'] . '/editar')) ?>" class="text-[#1565C0] hover:underline">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
