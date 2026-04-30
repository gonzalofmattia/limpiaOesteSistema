<?php
$filters = $filters ?? [];
$productRows = $productRows ?? [];
$clientRows = $clientRows ?? [];
$comboRows = $comboRows ?? [];
$hasCostData = !empty($hasCostData);
$profitRows = $profitRows ?? [];
?>
<div class="space-y-5">
    <form method="get" action="<?= e(url('/ventas/reportes')) ?>" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Desde</label>
            <input type="date" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
            <input type="date" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm">Filtrar</button>
    </form>

    <section class="bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-800">Ventas por producto</h3>
            <a href="<?= e(url('/ventas/reportes/exportar?' . http_build_query(array_merge($filters, ['type' => 'productos'])))) ?>" class="text-sm text-[#1565C0] hover:underline">Exportar Excel</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="text-left text-gray-600"><th class="py-2">Codigo</th><th>Producto</th><th class="text-right">Unidades</th><th class="text-right">Monto</th></tr></thead><tbody>
            <?php foreach ($productRows as $r): ?>
                <tr class="border-t border-gray-100"><td class="py-2"><?= e((string) ($r['code'] ?? '')) ?></td><td><?= e((string) ($r['name'] ?? '')) ?></td><td class="text-right"><?= (int) ($r['units_sold'] ?? 0) ?></td><td class="text-right"><?= formatPrice((float) ($r['amount_sold'] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-800">Ventas por cliente</h3>
            <a href="<?= e(url('/ventas/reportes/exportar?' . http_build_query(array_merge($filters, ['type' => 'clientes'])))) ?>" class="text-sm text-[#1565C0] hover:underline">Exportar Excel</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="text-left text-gray-600"><th class="py-2">Cliente</th><th class="text-right">Ventas</th><th class="text-right">Monto</th><th class="text-right">Ticket prom.</th><th class="text-right">Ultima compra</th></tr></thead><tbody>
            <?php foreach ($clientRows as $r): ?>
                <tr class="border-t border-gray-100"><td class="py-2"><?= e((string) ($r['name'] ?? '')) ?></td><td class="text-right"><?= (int) ($r['sales_count'] ?? 0) ?></td><td class="text-right"><?= formatPrice((float) ($r['total_amount'] ?? 0)) ?></td><td class="text-right"><?= formatPrice((float) ($r['avg_ticket'] ?? 0)) ?></td><td class="text-right"><?= e((string) ($r['last_sale'] ?? '')) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-800">Ventas por combo</h3>
            <a href="<?= e(url('/ventas/reportes/exportar?' . http_build_query(array_merge($filters, ['type' => 'combos'])))) ?>" class="text-sm text-[#1565C0] hover:underline">Exportar Excel</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="text-left text-gray-600"><th class="py-2">Combo</th><th class="text-right">Cantidad</th><th class="text-right">Monto</th></tr></thead><tbody>
            <?php foreach ($comboRows as $r): ?>
                <tr class="border-t border-gray-100"><td class="py-2"><?= e((string) ($r['name'] ?? '')) ?></td><td class="text-right"><?= (int) ($r['combo_qty'] ?? 0) ?></td><td class="text-right"><?= formatPrice((float) ($r['combo_amount'] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-800">Rentabilidad (snapshot histórico)</h3>
            <a href="<?= e(url('/ventas/reportes/exportar?' . http_build_query(array_merge($filters, ['type' => 'rentabilidad'])))) ?>" class="text-sm text-[#1565C0] hover:underline">Exportar Excel</a>
        </div>
        <?php if (!$hasCostData): ?>
            <p class="text-xs text-amber-700 mb-2">Nota: no hay costo base persistido en productos, pero este reporte usa snapshot por línea en `quote_items`.</p>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-gray-600"><th class="py-2">Nro Venta</th><th>Nro Presupuesto</th><th>Fecha</th><th>Cliente</th><th class="text-right">Venta</th><th class="text-right">Costo</th><th class="text-right">Margen $</th><th class="text-right">Margen %</th></tr></thead>
                <tbody>
                <?php foreach ($profitRows as $r): ?>
                    <tr class="border-t border-gray-100">
                        <td class="py-2 font-mono"><?= e((string) ($r['sale_number'] ?: '—')) ?></td>
                        <td><?= e((string) $r['quote_number']) ?></td>
                        <td><?= e((string) $r['sale_date']) ?></td>
                        <td><?= e((string) $r['client_name']) ?></td>
                        <td class="text-right"><?= formatPrice((float) $r['sale_total']) ?></td>
                        <td class="text-right"><?= formatPrice((float) $r['cost_total']) ?></td>
                        <td class="text-right <?= (float) $r['margin_amount'] >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= formatPrice((float) $r['margin_amount']) ?></td>
                        <td class="text-right"><?= number_format((float) $r['margin_percent'], 2, ',', '.') ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
