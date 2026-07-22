<?php
$isEdit = $user !== null;
$action = url($isEdit ? '/usuarios/' . (int) $user['id'] : '/usuarios');
$u = $user ?? [];
$role = (string) ($u['role'] ?? 'revendedor');
$costMultiplier = isset($u['cost_multiplier']) ? (string) $u['cost_multiplier'] : '1.0000';
?>
<div class="max-w-lg bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <form method="post" action="<?= e($action) ?>" class="space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
            <input type="text" name="username" required value="<?= e((string) ($u['username'] ?? '')) ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
            <input type="text" name="full_name" value="<?= e((string) ($u['full_name'] ?? '')) ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= $isEdit ? 'Nueva contraseña (vacío = no cambiar)' : 'Contraseña' ?></label>
            <input type="password" name="password" autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
            <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="revendedor" <?= $role === 'revendedor' ? 'selected' : '' ?>>Revendedor</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Multiplicador de costo (1.0000 - 2.0000)</label>
            <input type="text" name="cost_multiplier" value="<?= e($costMultiplier) ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int) ($u['is_active'] ?? 1) === 1 ? 'checked' : '' ?>
                   class="rounded border-gray-300">
            <label for="is_active" class="text-sm text-gray-700">Activo</label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="<?= e(url('/usuarios')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Cancelar</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm">Guardar</button>
        </div>
    </form>
</div>
