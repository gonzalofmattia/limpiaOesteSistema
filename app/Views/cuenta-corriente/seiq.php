<div class="space-y-4">
<div class="flex flex-wrap justify-between items-center gap-3">
    <div>
        <p class="text-sm text-gray-600 font-medium"><?= e($supplier['name']) ?> · Saldo actual (deuda): <span class="font-semibold"><?= formatPrice((float) $debt) ?></span></p>
    </div>
    <a href="<?= e(url('/cuenta-corriente/proveedor/' . (int) $supplier['id'] . '/pdf')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Descargar PDF</a>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="lo-card p-4">
        <h3 class="font-semibold mb-2">Registrar pago</h3>
        <form method="post" action="<?= e(url('/cuenta-corriente/pago-proveedor')) ?>" class="space-y-2">
            <?= csrfField() ?>
            <input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>">
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="amount" placeholder="Monto" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input type="date" name="transaction_date" value="<?= e(date('Y-m-d')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <select name="payment_method" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="mercadopago">Mercado Pago</option>
                    <option value="otro">Otro</option>
                </select>
                <input type="text" name="payment_reference" placeholder="Referencia" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <textarea name="notes" rows="2" placeholder="Notas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#1565C0] text-white text-sm">Registrar pago</button>
        </form>
    </div>
    <div class="lo-card p-4">
        <h3 class="font-semibold mb-2">Registrar ajuste</h3>
        <form method="post" action="<?= e(url('/cuenta-corriente/ajuste')) ?>" class="space-y-2">
            <?= csrfField() ?>
            <input type="hidden" name="account_type" value="supplier">
            <input type="hidden" name="account_id" value="<?= (int) $supplier['id'] ?>">
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="amount" placeholder="Monto (+/-)" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input type="date" name="transaction_date" value="<?= e(date('Y-m-d')) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <input type="text" name="description" placeholder="Descripción (obligatoria)" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <textarea name="notes" rows="2" placeholder="Notas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm">Registrar ajuste</button>
        </form>
    </div>
</div>
<form method="get" class="mb-4 flex gap-2">
    <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
    <input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar..." class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="Buscar"><i data-lucide="search" class="w-5 h-5 text-white"></i></button>
</form>

<div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-left px-4 py-3">Concepto</th>
                <th class="text-right px-4 py-3">Cargo</th>
                <th class="text-right px-4 py-3">Pago</th>
                <th class="text-right px-4 py-3">Deuda</th>
                <th class="text-right px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($transactions as $tx): ?>
                <?php $canEdit = accountMovementIsEditable($tx); ?>
                <tr>
                    <td class="px-4 py-2"><?= e(date('d/m/Y', strtotime((string) $tx['transaction_date']))) ?></td>
                    <td class="px-4 py-2"><span class="lo-truncate" title="<?= e((string) $tx['description']) ?>"><?= e((string) $tx['description']) ?></span></td>
                    <td class="px-4 py-2 text-right"><?= (float) $tx['cargo'] > 0 ? formatPrice((float) $tx['cargo']) : '' ?></td>
                    <td class="px-4 py-2 text-right"><?= (float) $tx['pago'] > 0 ? formatPrice((float) $tx['pago']) : '' ?></td>
                    <td class="px-4 py-2 text-right font-medium"><?= formatPrice((float) $tx['running_debt']) ?></td>
                    <td class="px-4 py-2">
                        <?php if ($canEdit): ?>
                            <div class="flex items-center justify-end gap-3">
                            <a href="<?= e(url('/cuenta-corriente/movimiento/' . (int) $tx['id'] . '/editar')) ?>" class="text-sm" title="Editar"><i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i></a>
                            <?php $deleteFormId = 'delete-movement-supplier-' . (int) $tx['id']; ?>
                            <span class="border-l border-gray-200 h-5 mx-1"></span>
                            <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/cuenta-corriente/movimiento/' . (int) $tx['id'] . '/eliminar')) ?>" class="inline">
                                <?= csrfField() ?>
                                <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el movimiento seleccionado')" class="text-sm" title="Eliminar"><i data-lucide="trash-2" class="w-5 h-5 text-red-400 hover:text-red-600"></i></button>
                            </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
</div>
