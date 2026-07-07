<?php $report = $report ?? null; ?>
<div class="space-y-5 max-w-3xl">
    <div class="lo-card p-5">
        <p class="text-sm font-semibold text-slate-800 mb-1">Importar prospectos desde Excel</p>
        <p class="text-sm text-slate-500 mb-4">Columnas esperadas (no distingue mayúsculas/acentos): <code>nombre</code>, <code>rubro</code>, <code>telefono</code>, <code>ciudad</code>, <code>fuente</code>. Solo <code>nombre</code> y <code>telefono</code> son obligatorias.</p>
        <form method="post" action="<?= e(url('/prospeccion/importar')) ?>" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3">
            <?= csrfField() ?>
            <input type="file" name="xlsx" accept=".xlsx" required class="flex-1 text-sm">
            <button type="submit" class="shrink-0 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Importar</button>
        </form>
    </div>

    <?php if ($report !== null): ?>
        <div class="lo-card p-5">
            <p class="text-sm font-semibold text-slate-800 mb-3">Resumen de la importación</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="lo-card-soft p-3"><p class="text-xs text-slate-500">Importados</p><p class="text-xl font-semibold text-green-700"><?= (int) $report['imported'] ?></p></div>
                <div class="lo-card-soft p-3"><p class="text-xs text-slate-500">Duplicados</p><p class="text-xl font-semibold text-amber-700"><?= (int) $report['duplicated'] ?></p></div>
                <div class="lo-card-soft p-3"><p class="text-xs text-slate-500">Ya son clientes</p><p class="text-xl font-semibold text-blue-700"><?= (int) $report['existing_clients'] ?></p></div>
                <div class="lo-card-soft p-3"><p class="text-xs text-slate-500">Inválidos</p><p class="text-xl font-semibold text-red-700"><?= count($report['invalid']) ?></p></div>
            </div>
            <?php if (($report['invalid'] ?? []) !== []): ?>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Filas inválidas</p>
                <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-100">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600"><tr><th class="text-left px-3 py-2">Fila</th><th class="text-left px-3 py-2">Motivo</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($report['invalid'] as $inv): ?>
                                <tr><td class="px-3 py-2"><?= (int) $inv['line'] ?></td><td class="px-3 py-2"><?= e((string) $inv['reason']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
