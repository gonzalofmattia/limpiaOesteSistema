<?php
$users = $users ?? [];
?>
<div class="space-y-5">
    <div class="flex justify-end items-center">
        <?php $uiBtnHref = url('/usuarios/crear'); $uiBtnLabel = 'Nuevo usuario'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <div class="lo-table-wrap">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Usuario</th>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Rol</th>
                    <th class="text-right px-4 py-3">Multiplicador costo</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Último acceso</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= e((string) $u['username']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= e((string) ($u['full_name'] ?? '—')) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= (string) $u['role'] === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' ?>">
                                <?= (string) $u['role'] === 'admin' ? 'Admin' : 'Revendedor' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right"><?= e(number_format((float) $u['cost_multiplier'], 4, ',', '.')) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= e(statusBadgeClass((int) ($u['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>">
                                <?= e(statusLabel((int) ($u['is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= e((string) ($u['last_login'] ?? '—')) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e(url('/usuarios/' . (int) $u['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                    <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
