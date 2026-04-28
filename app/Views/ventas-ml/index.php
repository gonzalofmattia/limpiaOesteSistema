<div class="flex justify-between mb-6">
    <p class="text-sm text-gray-600">Ventas manuales de MercadoLibre para consolidar en pedidos.</p>
    <a href="<?= e(url('/ventas-ml/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Nueva venta ML</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-left px-4 py-3">Productos</th>
                <th class="text-right px-4 py-3">Total ML</th>
                <th class="text-right px-4 py-3">Neto MP</th>
                <th class="text-right px-4 py-3">Costos ML</th>
                <th class="text-right px-4 py-3">Ganancia</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($sales as $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-600"><?= e((string) ($s['created_at'] ?? '')) ?></td>
                    <td class="px-4 py-3"><?= (int) ($s['products_count'] ?? 0) ?> productos</td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) ($s['ml_sale_total'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) ($s['ml_net_amount'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) ($s['ml_costs'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right <?= (float) ($s['gain'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                        <?= formatPrice((float) ($s['gain'] ?? 0)) ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= e(url('/ventas-ml/' . (int) $s['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($sales === []): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">Todavía no hay ventas ML cargadas.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
