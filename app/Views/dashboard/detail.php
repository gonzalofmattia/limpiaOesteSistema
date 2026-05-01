<?php
$metric = (string) ($metric ?? '');
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="space-y-4">
    <section class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">Desglose</p>
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mt-1"><?= e($title ?? 'Detalle') ?></h2>
                <p class="text-sm text-gray-600 mt-1"><?= e((string) ($explain ?? '')) ?></p>
            </div>
            <a href="<?= e(url('/')) ?>" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">
                Volver
            </a>
        </div>
        <p class="text-3xl font-bold text-gray-900 mt-4"><?= formatPrice((float) ($value ?? 0)) ?></p>
    </section>

    <section class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Movimientos considerados</h3>

        <?php if ($rows === []): ?>
            <p class="text-sm text-gray-500">No hay datos para mostrar en este indicador.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($rows as $row): ?>
                    <?php if ($metric === 'aceptados'): ?>
                        <a href="<?= e(url('/presupuestos/' . (int) $row['id'])) ?>" class="block rounded-xl border border-gray-100 p-3 hover:border-gray-300">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900"><?= e((string) ($row['quote_number'] ?? '')) ?></p>
                                    <p class="text-xs text-gray-500"><?= e((string) ($row['client_name'] ?? 'Sin cliente')) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-800"><?= formatPrice((float) ($row['total'] ?? 0)) ?></p>
                                    <p class="text-xs text-gray-500"><?= e((string) ($row['status'] ?? '')) ?></p>
                                </div>
                            </div>
                        </a>
                    <?php elseif ($metric === 'cobrado'): ?>
                        <div class="rounded-xl border border-gray-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900"><?= e((string) ($row['client_name'] ?? 'Cliente')) ?></p>
                                    <p class="text-xs text-gray-500"><?= e((string) ($row['description'] ?? '')) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-primary"><?= formatPrice((float) ($row['amount'] ?? 0)) ?></p>
                                    <p class="text-xs text-gray-500"><?= e((string) ($row['transaction_date'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($metric === 'ganancia'): ?>
                        <a href="<?= e(url('/presupuestos/' . (int) $row['id'])) ?>" class="block rounded-xl border border-gray-100 p-3 hover:border-gray-300">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900"><?= e((string) ($row['quote_number'] ?? '')) ?></p>
                                    <p class="text-xs text-gray-500">Entregado neto / costo estimado</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">
                                        <?= formatPrice((float) ($row['delivered_net'] ?? 0)) ?> / <?= formatPrice((float) ($row['estimated_cost'] ?? 0)) ?>
                                    </p>
                                    <p class="text-sm font-semibold <?= ((float) ($row['delivered_net'] ?? 0) - (float) ($row['estimated_cost'] ?? 0)) >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                                        <?= formatPrice((float) ($row['delivered_net'] ?? 0) - (float) ($row['estimated_cost'] ?? 0)) ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php elseif ($metric === 'pendiente'): ?>
                        <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $row['id'])) ?>" class="block rounded-xl border border-gray-100 p-3 hover:border-gray-300">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-gray-900 truncate"><?= e((string) ($row['name'] ?? 'Cliente')) ?></p>
                                <p class="text-sm font-semibold text-red-600"><?= formatPrice((float) ($row['balance'] ?? 0)) ?></p>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
