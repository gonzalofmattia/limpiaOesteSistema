<?php
/** @var array<int, array{listing_title:string, ml_item_id:string, ml_permalink:string, conflicts:list<array<string,mixed>>}> $byListing */
$byListing = $byListing ?? [];
$count = (int) ($count ?? 0);

$fieldLabels = [
    'title' => 'Título',
    'price' => 'Precio',
    'available_quantity' => 'Stock disponible',
    'description' => 'Descripción',
    'category_id' => 'Categoría ML',
];

$formatValue = static function (string $field, ?string $value): string {
    if ($value === null || trim($value) === '') {
        return '(vacío)';
    }
    if ($field === 'description' && mb_strlen($value) > 160) {
        return mb_substr($value, 0, 160) . '…';
    }

    return $value;
};
?>
<div class="space-y-6 max-w-6xl">
    <div>
        <p class="text-xs text-slate-500 mb-1">
            <a href="<?= e(url('/mercadolibre')) ?>" class="text-lo-blue hover:underline">← MercadoLibre</a>
        </p>
        <h1 class="text-xl font-semibold text-slate-900">Conflictos de sincronización ML</h1>
        <p class="text-sm text-slate-600 mt-1">
            Campos que cambiaron a la vez en ML y en el sistema desde el último sync: no se
            aplican solos, hay que elegir manualmente qué versión vale.
        </p>
    </div>

    <div class="lo-card p-4 border border-slate-200">
        <p class="text-sm font-medium text-slate-800">
            <?php if ($count === 0): ?>
                <span class="text-emerald-700">✅ No hay conflictos pendientes</span>
            <?php else: ?>
                <span class="text-amber-800"><?= $count ?> campo<?= $count === 1 ? '' : 's' ?> en conflicto</span>
                <span class="block text-xs font-normal text-slate-500 mt-1">Agrupados por publicación. Elegí "Usar ML" o "Usar sistema" por fila — se aplica al instante.</span>
            <?php endif; ?>
        </p>
    </div>

    <?php foreach ($byListing as $listingId => $group): ?>
        <div class="lo-card border border-slate-200 overflow-hidden rounded-xl">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <span class="font-medium text-slate-900"><?= e($group['listing_title']) ?></span>
                    <span class="block text-xs text-slate-500">
                        listing #<?= (int) $listingId ?> · ml_item_id=<?= e($group['ml_item_id']) ?>
                        <?php if ($group['ml_permalink'] !== ''): ?>
                            · <a href="<?= e($group['ml_permalink']) ?>" target="_blank" rel="noopener" class="text-lo-blue hover:underline">ver en ML</a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="lo-table-wrap overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-3 py-2">Campo</th>
                            <th class="text-left px-3 py-2">Valor en ML</th>
                            <th class="text-left px-3 py-2">Valor en sistema</th>
                            <th class="text-left px-3 py-2">Último sync</th>
                            <th class="text-left px-3 py-2">Detectado</th>
                            <th class="text-right px-3 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($group['conflicts'] as $row): ?>
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-3 py-2 font-medium text-slate-800">
                                    <?= e($fieldLabels[$row['field']] ?? $row['field']) ?>
                                </td>
                                <td class="px-3 py-2 max-w-xs">
                                    <?php if ($row['field'] === 'description' && mb_strlen((string) $row['ml_value']) > 160): ?>
                                        <details>
                                            <summary class="cursor-pointer text-slate-700"><?= e($formatValue($row['field'], $row['ml_value'])) ?></summary>
                                            <p class="mt-1 text-xs text-slate-500 whitespace-pre-line"><?= e((string) $row['ml_value']) ?></p>
                                        </details>
                                    <?php else: ?>
                                        <?= e($formatValue($row['field'], $row['ml_value'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 max-w-xs">
                                    <?php if ($row['field'] === 'description' && mb_strlen((string) $row['system_value']) > 160): ?>
                                        <details>
                                            <summary class="cursor-pointer text-slate-700"><?= e($formatValue($row['field'], $row['system_value'])) ?></summary>
                                            <p class="mt-1 text-xs text-slate-500 whitespace-pre-line"><?= e((string) $row['system_value']) ?></p>
                                        </details>
                                    <?php else: ?>
                                        <?= e($formatValue($row['field'], $row['system_value'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500"><?= e($formatValue($row['field'], $row['last_sync_value'])) ?></td>
                                <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap"><?= e((string) $row['detected_at']) ?></td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <form method="post" action="<?= e(url('/mercadolibre/sync/conflictos/' . $row['id'] . '/resolver')) ?>" class="inline-block">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="resolution" value="ml">
                                        <button type="submit" class="px-2.5 py-1 rounded-md bg-slate-800 text-white text-xs font-medium hover:bg-slate-700">Usar ML</button>
                                    </form>
                                    <form method="post" action="<?= e(url('/mercadolibre/sync/conflictos/' . $row['id'] . '/resolver')) ?>" class="inline-block ml-1">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="resolution" value="sistema">
                                        <button type="submit" class="px-2.5 py-1 rounded-md bg-[#1a6b3c] text-white text-xs font-medium hover:bg-[#14542f]">Usar sistema</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
