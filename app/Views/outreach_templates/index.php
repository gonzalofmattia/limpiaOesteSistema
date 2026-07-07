<?php
$stageLabels = $stageLabels ?? [];
$businessTypeLabels = $businessTypeLabels ?? [];
?>
<div class="space-y-5">
    <div class="flex justify-end">
        <?php $uiBtnHref = url('/prospeccion/plantillas/crear'); $uiBtnLabel = 'Nueva plantilla'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <div class="lo-table-wrap hidden md:block">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Rubro</th>
                    <th class="text-left px-4 py-3">Etapa</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (($templates ?? []) === []): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Todavía no hay plantillas cargadas.</td></tr>
                <?php endif; ?>
                <?php foreach ($templates ?? [] as $t): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-slate-900"><?= e((string) $t['name']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e($businessTypeLabels[$t['business_type']] ?? $t['business_type']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e($stageLabels[$t['stage']] ?? $t['stage']) ?></td>
                        <td class="px-4 py-3">
                            <?php if ((int) $t['active'] === 1): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activa</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e(url('/prospeccion/plantillas/' . (int) $t['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700" title="Editar"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                                <form method="post" action="<?= e(url('/prospeccion/plantillas/' . (int) $t['id'] . '/toggle')) ?>" class="inline">
                                    <?= csrfField() ?>
                                    <button type="submit" class="text-slate-500 hover:text-slate-700" title="<?= (int) $t['active'] === 1 ? 'Desactivar' : 'Activar' ?>">
                                        <i data-lucide="<?= (int) $t['active'] === 1 ? 'toggle-right' : 'toggle-left' ?>" class="h-5 w-5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="md:hidden lo-mobile-card-list">
        <?php foreach ($templates ?? [] as $t): ?>
            <article class="lo-mobile-card shadow-sm">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-base font-semibold text-slate-900"><?= e((string) $t['name']) ?></p>
                    <?php if ((int) $t['active'] === 1): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activa</span>
                    <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactiva</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-500 mb-2"><?= e($businessTypeLabels[$t['business_type']] ?? $t['business_type']) ?> · <?= e($stageLabels[$t['stage']] ?? $t['stage']) ?></p>
                <a href="<?= e(url('/prospeccion/plantillas/' . (int) $t['id'] . '/editar')) ?>" class="text-sm font-medium text-lo-blue">Editar</a>
            </article>
        <?php endforeach; ?>
    </div>
</div>
