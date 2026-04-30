<?php
$statusStyle = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-800',
    'accepted' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'expired' => 'bg-amber-100 text-amber-800',
    'delivered' => 'bg-teal-100 text-teal-900',
];
?>
<div class="flex justify-between mb-6">
    <p class="text-sm text-gray-600">Presupuestos con numeración <?= e(setting('quote_prefix', 'LO')) ?>-AÑO-NNNN.</p>
    <a href="<?= e(url('/presupuestos/crear')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Nuevo presupuesto</a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Número</th>
                <th class="text-left px-4 py-3">Cliente</th>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-right px-4 py-3">Total</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($quotes as $q): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs">
                        <?= e($q['quote_number']) ?>
                        <?php if ((int) ($q['is_mercadolibre'] ?? 0) === 1): ?>
                            <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 font-sans">ML</span>
                        <?php endif; ?>
                        <?php if ((int) ($q['attachments_count'] ?? 0) > 0): ?>
                            <span class="ml-1.5 text-gray-500 font-sans normal-case" title="Documentos adjuntos">📎 <?= (int) $q['attachments_count'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?= e($q['client_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($q['created_at']) ?></td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) $q['total']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php $st = $q['status']; ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $statusStyle[$st] ?? 'bg-gray-100' ?>"><?= e($st) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                        <?php if (in_array((string) ($q['status'] ?? ''), ['draft', 'sent', 'accepted'], true)): ?>
                            <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/editar')) ?>" class="text-gray-600 hover:underline">Editar</a>
                        <?php else: ?>
                            <span class="text-gray-300 cursor-not-allowed" title="Solo se puede editar en draft, sent o accepted">Editar</span>
                        <?php endif; ?>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/pdf')) ?>" class="text-[#1a6b3c] hover:underline">PDF</a>
                        <form method="post" action="<?= e(url('/presupuestos/' . (int) $q['id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Seguro que querés eliminar este presupuesto?');">
                            <?= csrfField() ?>
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
