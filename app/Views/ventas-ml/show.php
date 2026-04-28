<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">Venta ML <?= e((string) ($sale['quote_number'] ?? '')) ?></p>
            <h2 class="text-xl font-semibold text-gray-900">Detalle de venta MercadoLibre</h2>
            <p class="text-sm text-gray-600 mt-1"><?= e((string) ($sale['created_at'] ?? '')) ?></p>
        </div>
        <div class="flex gap-2 items-start">
            <a href="<?= e(url('/ventas-ml/' . (int) ($sale['id'] ?? 0) . '/editar')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Editar</a>
            <a href="<?= e(url('/ventas-ml')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Volver</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-2">Producto</th>
                    <th class="text-right px-4 py-2">Cant.</th>
                    <th class="text-right px-4 py-2">Precio unitario</th>
                    <th class="text-right px-4 py-2">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="px-4 py-2"><?= e((string) $it['code']) ?> — <?= e((string) $it['name']) ?></td>
                        <td class="px-4 py-2 text-right"><?= (int) $it['quantity'] ?></td>
                        <td class="px-4 py-2 text-right"><?= formatPrice((float) $it['unit_price']) ?></td>
                        <td class="px-4 py-2 text-right font-medium"><?= formatPrice((float) $it['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200 text-right space-y-1 text-sm">
            <p>Total productos (líneas): <span class="font-medium"><?= formatPrice((float) ($stats['items_total'] ?? 0)) ?></span></p>
            <p>Total ML: <span class="font-medium"><?= formatPrice((float) ($sale['ml_sale_total'] ?? 0)) ?></span></p>
            <p>Neto MP: <span class="font-medium"><?= formatPrice((float) ($sale['ml_net_amount'] ?? 0)) ?></span></p>
            <p>Costos ML: <span class="font-medium"><?= formatPrice((float) ($stats['ml_costs'] ?? 0)) ?></span></p>
            <p class="text-lg font-semibold <?= (float) ($stats['gain'] ?? 0) >= 0 ? 'text-[#1a6b3c]' : 'text-red-700' ?>">
                Ganancia: <?= formatPrice((float) ($stats['gain'] ?? 0)) ?>
            </p>
        </div>
    </div>
</div>
