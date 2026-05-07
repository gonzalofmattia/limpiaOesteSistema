<div class="space-y-5">
<div class="flex gap-2 overflow-x-auto pb-1">
    <a href="<?= e(url('/cuenta-corriente')) ?>" class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Resumen</a>
    <a href="<?= e(url('/cuenta-corriente/clientes')) ?>" class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Clientes</a>
    <a href="<?= e(url('/cuenta-corriente')) ?>" class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Proveedores</a>
</div>
<div class="grid lg:grid-cols-2 gap-6">
    <div class="lo-card p-5">
        <p class="text-sm text-gray-500">A COBRAR (clientes)</p>
        <p class="text-3xl font-bold text-[#1a6b3c] mt-2"><?= formatPrice((float) $totalReceivable) ?></p>
        <p class="text-sm text-gray-600 mt-1">Clientes con deuda: <?= (int) $clientsWithDebt ?></p>
        <div class="mt-4">
            <a href="<?= e(url('/cuenta-corriente/clientes')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Ver detalle clientes</a>
        </div>
    </div>
    <div class="lo-card p-5">
        <p class="text-sm text-gray-500">A PAGAR (proveedores)</p>
        <div class="space-y-1 mt-2">
            <?php foreach ($supplierDebts as $supplier): ?>
                <div class="flex justify-between text-sm">
                    <span><?= e($supplier['name']) ?></span>
                    <span class="font-medium"><?= formatPrice((float) $supplier['debt']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-gray-200 mt-3 pt-2 flex justify-between">
            <span class="text-sm text-gray-600">Total</span>
            <span class="font-semibold"><?= formatPrice((float) $totalPayable) ?></span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($supplierDebts as $supplier): ?>
                <a href="<?= e(url('/cuenta-corriente/proveedor/' . (int) $supplier['id'])) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm"><?= e($supplier['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="lo-card p-5">
        <h2 class="font-semibold mb-3">Registrar cobro</h2>
        <form method="post" action="<?= e(url('/cuenta-corriente/cobro')) ?>" class="space-y-3">
            <?= csrfField() ?>
            <select name="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <option value="">Seleccionar cliente...</option>
                <?php foreach ($clientsForForm as $client): ?>
                    <option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?> (<?= formatPrice((float) $client['balance']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="amount" placeholder="Monto" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
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
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Registrar cobro</button>
        </form>
    </div>
    <div class="lo-card p-5">
        <h2 class="font-semibold mb-3">Registrar pago a proveedor</h2>
        <form method="post" action="<?= e(url('/cuenta-corriente/pago-proveedor')) ?>" class="space-y-3">
            <?= csrfField() ?>
            <select name="supplier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <option value="">Seleccionar proveedor...</option>
                <?php foreach ($suppliersForForm as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>"><?= e($supplier['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="amount" placeholder="Monto" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
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
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#1565C0] text-white text-sm font-medium">Registrar pago</button>
        </form>
    </div>
</div>

<div class="lo-table-wrap">
    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="font-semibold text-gray-800">Últimos movimientos</h2>
    </div>
    <form method="get" class="p-4 border-b border-gray-100 flex gap-2">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
        <div class="flex-1 h-10 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar..." class="w-full bg-transparent outline-none text-sm"></div>
        <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
    </form>
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 text-gray-600">
            <tr>
                <th class="text-left px-4 py-2">Fecha</th>
                <th class="text-left px-4 py-2">Tipo</th>
                <th class="text-left px-4 py-2">Cuenta</th>
                <th class="text-left px-4 py-2">Concepto</th>
                <th class="text-right px-4 py-2">Monto</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($recentTransactions as $tx): ?>
                <?php
                $type = (string) $tx['transaction_type'];
                $badgeStatus = $type === 'payment' ? 'received' : ($type === 'invoice' ? 'pending' : 'draft');
                $badge = $type === 'payment' ? 'Cobro/Pago' : ($type === 'invoice' ? 'Cargo' : 'Ajuste');
                $accountName = $tx['account_type'] === 'client' ? ($tx['client_name'] ?? 'Cliente') : ($tx['supplier_name'] ?? 'Proveedor');
                ?>
                <tr>
                    <td class="px-4 py-2"><?= e(date('d/m/Y', strtotime((string) $tx['transaction_date']))) ?></td>
                    <td class="px-4 py-2"><span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass($badgeStatus)) ?>"><?= e($badge) ?></span></td>
                    <td class="px-4 py-2"><span class="lo-truncate" title="<?= e((string) $accountName) ?>"><?= e((string) $accountName) ?></span></td>
                    <td class="px-4 py-2"><span class="lo-truncate" title="<?= e((string) $tx['description']) ?>"><?= e((string) $tx['description']) ?></span></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) $tx['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
