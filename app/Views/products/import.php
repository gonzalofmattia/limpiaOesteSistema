<?php
/** @var array<string, mixed>|null $multiReport */
$multiReport = $multiReport ?? null;
?>
<div class="max-w-5xl space-y-6">
    <?php if ($multiReport && is_array($multiReport)): ?>
        <?php
        $sheets = $multiReport['sheets'] ?? [];
        $totals = $multiReport['totals'] ?? ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $newCats = $multiReport['new_categories'] ?? [];
        ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Resumen importación masiva</h2>

            <?php if ($newCats !== []): ?>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-medium mb-2">
                        Se crearon <?= count($newCats) ?> categoría<?= count($newCats) === 1 ? '' : 's' ?> nueva<?= count($newCats) === 1 ? '' : 's' ?>. Revisá su configuración:
                    </p>
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($newCats as $nc): ?>
                            <li>
                                <?= e((string) ($nc['name'] ?? '')) ?>
                                →
                                <a href="<?= e(url('/categorias/' . (int) ($nc['id'] ?? 0) . '/editar')) ?>" class="text-accent underline font-medium">Editar</a>
                                <span class="text-amber-800">(descuento default: <?= e((string) ($nc['default_discount'] ?? 35)) ?>%)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-700">
                        <tr>
                            <th class="px-3 py-2 font-medium">Hoja / Categoría</th>
                            <th class="px-3 py-2 font-medium">Estado</th>
                            <th class="px-3 py-2 font-medium text-right">Creados</th>
                            <th class="px-3 py-2 font-medium text-right">Actualizados</th>
                            <th class="px-3 py-2 font-medium text-right">Saltados</th>
                            <th class="px-3 py-2 font-medium text-right">Errores</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($sheets as $s): ?>
                            <?php
                            $state = (string) ($s['category_state'] ?? '');
                            $badgeClass = match ($state) {
                                'CREADA' => 'bg-emerald-100 text-emerald-800',
                                'error' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            ?>
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900"><?= e((string) ($s['sheet'] ?? '')) ?></div>
                                    <?php if (($s['category'] ?? '') !== ($s['sheet'] ?? '')): ?>
                                        <div class="text-xs text-gray-500"><?= e((string) ($s['category'] ?? '')) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['error_message'])): ?>
                                        <div class="text-xs text-red-600 mt-1"><?= e((string) $s['error_message']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium <?= $badgeClass ?>">
                                        <?= e($state === 'existía' ? 'existía' : ($state === 'CREADA' ? 'CREADA' : ($state === 'error' ? 'error' : $state))) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($s['created'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($s['updated'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($s['skipped'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($s['errors'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50 font-semibold text-gray-900">
                            <td class="px-3 py-2">TOTAL</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($totals['created'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($totals['updated'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($totals['skipped'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) ($totals['errors'] ?? 0) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Importación masiva (Excel multi-hoja)</h2>
        <p class="text-sm text-gray-600">
            Subí un Excel donde <strong>cada hoja es una categoría</strong>. El sistema detecta las categorías automáticamente
            y puede crear las que no existen (si lo habilitás abajo).
        </p>
        <form method="post" action="<?= e(url('/productos/importar-masivo')) ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo .xlsx</label>
                <input type="file" name="import_xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="text-sm">
            </div>
            <div class="space-y-2 text-sm text-gray-700">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="multi_create_categories" value="1" checked class="rounded border-gray-300">
                    Crear categorías nuevas automáticamente
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="multi_update_existing" value="1" checked class="rounded border-gray-300">
                    Actualizar productos existentes (coincide por código dentro de la categoría)
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="multi_delete_before" value="1" class="rounded border-gray-300">
                    Borrar productos de la categoría antes de importar (recarga completa por hoja)
                </label>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Importar todas las hojas</button>
            </div>
        </form>
    </div>

    <div class="max-w-xl space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm text-gray-600 mb-3">Descargá el Excel de ejemplo con las columnas correctas. Completalo con los datos de la lista Seiq y volvé a subirlo acá.</p>
            <a href="<?= e(url('/productos/importar/ejemplo')) ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-accent text-white rounded-lg hover:bg-blue-700 transition text-sm">
                <i data-lucide="download" class="w-4 h-4 text-white"></i>
                Descargar Excel de ejemplo
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm text-gray-600 mb-4">Archivo <strong>CSV</strong> con separador <code class="bg-gray-100 px-1 rounded">;</code> o <strong>Excel .xlsx</strong> (plantilla “Productos”). Primera fila: encabezados. Se detectan columnas por nombre.</p>
            <form method="post" action="<?= e(url('/productos/importar')) ?>" enctype="multipart/form-data" class="space-y-4">
                <?= csrfField() ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría destino</label>
                    <select name="category_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Archivo</label>
                    <input type="file" name="csv" accept=".csv,.txt,.xlsx,.xls" required class="text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modo</label>
                    <select name="mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="both">Crear nuevos y actualizar existentes (por código)</option>
                        <option value="create">Solo crear nuevos</option>
                        <option value="update">Solo actualizar existentes</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Importar</button>
                    <a href="<?= e(url('/productos')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Volver</a>
                </div>
            </form>
        </div>
    </div>
</div>
