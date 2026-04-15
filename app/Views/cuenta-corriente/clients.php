<div class="flex justify-between items-center mb-4">
    <div class="flex gap-2">
        <a href="<?= e(url('/cuenta-corriente/clientes?only_with_debt=1')) ?>" class="px-3 py-1.5 rounded-lg border text-sm <?= $onlyWithDebt ? 'bg-gray-900 text-white border-gray-900' : 'border-gray-300' ?>">Solo con deuda</a>
        <a href="<?= e(url('/cuenta-corriente/clientes')) ?>" class="px-3 py-1.5 rounded-lg border text-sm <?= !$onlyWithDebt ? 'bg-gray-900 text-white border-gray-900' : 'border-gray-300' ?>">Todos</a>
    </div>
    <a href="<?= e(url('/cuenta-corriente')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Registrar cobro</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
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
                        <?= e($r['name']) ?>
                        <?php if ($balance <= 0): ?>
                            <span class="ml-2 text-xs inline-flex px-2 py-0.5 rounded-full bg-green-100 text-green-700">✓ Al día</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) $r['total_invoiced']) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatPrice((float) $r['total_paid']) ?></td>
                    <td class="px-4 py-3 text-right font-semibold <?= $balance > 0 ? 'text-red-700' : 'text-green-700' ?>"><?= formatPrice($balance) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $r['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                        <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $r['id'] . '/pdf')) ?>" class="text-[#1a6b3c] hover:underline">PDF</a>
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
