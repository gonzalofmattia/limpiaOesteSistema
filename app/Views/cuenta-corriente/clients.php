<div class="space-y-4">
<div class="flex justify-between items-center">
    <div class="flex gap-2">
        <a href="<?= e(url('/cuenta-corriente/clientes?only_with_debt=1')) ?>" class="px-3 py-1.5 rounded-lg border text-sm <?= $onlyWithDebt ? 'bg-gray-900 text-white border-gray-900' : 'border-gray-300' ?>">Solo con deuda</a>
        <a href="<?= e(url('/cuenta-corriente/clientes')) ?>" class="px-3 py-1.5 rounded-lg border text-sm <?= !$onlyWithDebt ? 'bg-gray-900 text-white border-gray-900' : 'border-gray-300' ?>">Todos</a>
    </div>
    <a href="<?= e(url('/cuenta-corriente')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Registrar cobro</a>
</div>
<form method="get" class="mb-4 flex gap-2">
    <input type="hidden" name="only_with_debt" value="<?= $onlyWithDebt ? '1' : '0' ?>">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar..." class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="Buscar"><i data-lucide="search" class="w-5 h-5 text-white"></i></button>
</form>

<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Cliente</th>
                <th class="text-right px-4 py-3">Facturado</th>
                <th class="text-right px-4 py-3">Cobrado</th>
                <th class="text-right px-4 py-3">Saldo</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
                <?php $balance = (float) $r['balance']; ?>
                <tr>
                    <td class="px-4 py-3 font-medium">
                        <span class="lo-truncate" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></span>
                        <?php if ($balance <= 0): ?>
                            <span class="ml-2 text-xs inline-flex px-2 py-0.5 rounded-full bg-green-100 text-green-700">✓ Al día</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) $r['total_invoiced']) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) $r['total_paid']) ?></td>
                    <td class="px-4 py-3 text-right font-semibold <?= $balance > 0 ? 'text-red-700' : 'text-green-700' ?>"><?= formatPrice($balance) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3">
                            <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $r['id'])) ?>" title="Ver detalle"><i data-lucide="eye" class="w-5 h-5 text-gray-500 hover:text-blue-600"></i></a>
                            <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $r['id'] . '/pdf')) ?>" title="Descargar PDF"><i data-lucide="file-text" class="w-5 h-5 text-green-500 hover:text-green-700"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 border-t border-gray-200">
            <tr>
                <td colspan="3" class="px-4 py-3 text-right font-semibold">Total a cobrar</td>
                <td class="px-4 py-3 text-right font-bold"><?= formatPrice((float) $totalReceivable) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
</div>
