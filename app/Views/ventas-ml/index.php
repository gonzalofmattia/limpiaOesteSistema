<div class="space-y-5">
<div class="flex justify-end">
    <a href="<?= e(url('/ventas-ml/crear')) ?>" class="lo-btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Nueva venta ML</a>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Operaciones</p><p class="text-2xl font-semibold"><?= count($sales ?? []) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Total ML</p><p class="text-lg font-semibold"><?= formatPrice(array_sum(array_map(fn($s)=>(float)($s['ml_sale_total']??0), $sales ?? []))) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Neto MP</p><p class="text-lg font-semibold"><?= formatPrice(array_sum(array_map(fn($s)=>(float)($s['ml_net_amount']??0), $sales ?? []))) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Ganancia</p><p class="text-lg font-semibold"><?= formatPrice(array_sum(array_map(fn($s)=>(float)($s['gain']??0), $sales ?? []))) ?></p></div>
</div>
<div class="flex gap-2 overflow-x-auto pb-1">
    <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todas <span class="ml-1 text-[10px]"><?= count($sales ?? []) ?></span></span>
</div>
<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
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
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 text-gray-600">
                            <span class="h-8 w-8 rounded-lg bg-amber-50 text-amber-700 grid place-items-center"><i data-lucide="store" class="h-4 w-4"></i></span>
                            <span><?= e((string) ($s['created_at'] ?? '')) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><?= (int) ($s['products_count'] ?? 0) ?> productos</td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) ($s['ml_sale_total'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) ($s['ml_net_amount'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) ($s['ml_costs'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right <?= (float) ($s['gain'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                        <?= formatPrice((float) ($s['gain'] ?? 0)) ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <a href="<?= e(url('/ventas-ml/' . (int) $s['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                        <a href="<?= e(url('/ventas-ml/' . (int) $s['id'] . '/editar')) ?>" class="text-gray-600 hover:underline">Editar</a>
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
</div>
