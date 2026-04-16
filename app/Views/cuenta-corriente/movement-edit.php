<?php
/** @var array<string, mixed> $movement */
$isClient = (string) ($movement['account_type'] ?? '') === 'client';
$backUrl = $isClient
    ? url('/cuenta-corriente/cliente/' . (int) ($movement['account_id'] ?? 0))
    : url('/cuenta-corriente/proveedor/' . (int) ($movement['account_id'] ?? 0));
$type = (string) ($movement['transaction_type'] ?? '');
$amt = (float) ($movement['amount'] ?? 0);
$amtDisplay = $type === 'adjustment'
    ? number_format($amt, 2, ',', '.')
    : number_format(abs($amt), 2, ',', '.');
?>
<div class="max-w-lg mx-auto space-y-4">
    <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-900">Editar movimiento</h2>
        <a href="<?= e($backUrl) ?>" class="text-sm text-[#1565C0] hover:underline">Volver</a>
    </div>
    <p class="text-sm text-gray-600">
        <?= $type === 'payment' ? 'Pago / cobro' : 'Ajuste' ?> ·
        <?= $isClient ? 'Cliente' : 'Proveedor' ?> #<?= (int) ($movement['account_id'] ?? 0) ?>
    </p>

    <form method="post" action="<?= e(url('/cuenta-corriente/movimiento/' . (int) $movement['id'] . '/actualizar')) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        <?= csrfField() ?>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Fecha</label>
            <input type="date" name="transaction_date" value="<?= e(substr((string) ($movement['transaction_date'] ?? date('Y-m-d')), 0, 10)) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>

        <?php if ($type === 'payment'): ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Monto</label>
                <input type="text" name="amount" value="<?= e($amtDisplay) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Medio</label>
                <select name="payment_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php $m = (string) ($movement['payment_method'] ?? 'efectivo'); ?>
                    <option value="efectivo" <?= $m === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    <option value="transferencia" <?= $m === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                    <option value="otro" <?= $m === 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Referencia</label>
                <input type="text" name="payment_reference" value="<?= e((string) ($movement['payment_reference'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        <?php else: ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Monto (+/-)</label>
                <input type="text" name="amount" value="<?= e($amtDisplay) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Descripción</label>
                <input type="text" name="description" value="<?= e((string) ($movement['description'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Notas</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e((string) ($movement['notes'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar cambios</button>
    </form>
</div>
