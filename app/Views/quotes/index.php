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
                    <td class="px-4 py-3 font-mono text-xs"><?= e($q['quote_number']) ?></td>
                    <td class="px-4 py-3"><?= e($q['client_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($q['created_at']) ?></td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) $q['total']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php $st = $q['status']; ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $statusStyle[$st] ?? 'bg-gray-100' ?>"><?= e($st) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>" class="text-[#1565C0] hover:underline">Ver</a>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/editar')) ?>" class="text-gray-600 hover:underline">Editar</a>
                        <a href="<?= e(url('/presupuestos/' . (int) $q['id'] . '/pdf')) ?>" class="text-[#1a6b3c] hover:underline">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
