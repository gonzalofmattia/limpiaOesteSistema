<?php
$totalClients = count($clients ?? []);
$withDebt = 0;
$vip = 0;
$totalBalance = 0.0;
foreach (($clients ?? []) as $c) {
    $bal = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0);
    if ($bal > 0) { $withDebt++; }
    $totalBalance += $bal;
    if (strtoupper((string) ($c['category'] ?? '')) === 'VIP') { $vip++; }
}
?>
<div class="space-y-5">
    <div class="flex justify-end items-center">
        <?php $uiBtnHref = url('/clientes/crear'); $uiBtnLabel = 'Nuevo cliente'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Total clientes</p><p class="text-2xl font-semibold"><?= $totalClients ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Con saldo</p><p class="text-2xl font-semibold"><?= $withDebt ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Saldo total</p><p class="text-xl font-semibold"><?= formatPrice($totalBalance) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">VIP</p><p class="text-2xl font-semibold"><?= $vip ?></p></div>
    </div>
    <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
        <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nombre, email o teléfono..." class="w-full bg-transparent outline-none text-sm"></div>
        <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
    </form>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <span class="px-3 h-8 rounded-full bg-slate-900 text-white inline-flex items-center text-xs font-semibold">Todos <span class="ml-1 text-[10px] opacity-80"><?= $totalClients ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">VIP <span class="ml-1 text-[10px]"><?= $vip ?></span></span>
        <span class="px-3 h-8 rounded-full border border-slate-200 inline-flex items-center text-xs text-slate-600">Con deuda <span class="ml-1 text-[10px]"><?= $withDebt ?></span></span>
    </div>
    <div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Categoría</th>
                <th class="text-left px-4 py-3">Lista</th>
                <th class="text-right px-4 py-3">Saldo</th>
                <th class="text-right px-4 py-3">Total compras</th>
                <th class="text-left px-4 py-3">Última</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <?php $initials = strtoupper(substr((string) ($c['name'] ?? ''), 0, 1) . substr((string) ($c['business_name'] ?? ''), 0, 1)); ?>
                        <div class="flex items-center gap-3">
                            <span class="h-9 w-9 rounded-full bg-emerald-500 text-white text-xs font-semibold grid place-items-center"><?= e($initials !== '' ? $initials : 'CL') ?></span>
                            <div>
                                <p class="font-medium truncate max-w-[220px]" title="<?= e((string) ($c['name'] ?? '—')) ?>"><?= e((string) ($c['name'] ?? '—')) ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($c['email'] ?? '—')) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="lo-status-pill bg-violet-50 text-violet-700"><span class="lo-dot bg-violet-500"></span><?= e((string) ($c['category'] ?? '—')) ?></span></td>
                    <td class="px-4 py-3"><?= e((string) ($c['assigned_pricelist'] ?? '—')) ?></td>
                    <?php $balance = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0); ?>
                    <td class="px-4 py-3 text-right font-medium <?= $balance > 0 ? 'text-red-600' : '' ?>"><?= formatPrice($balance) ?></td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatPrice((float) ($c['total_purchases'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= e((string) ($c['last_purchase_at'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $c['id'])) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Ver cuenta corriente">
                                <i data-lucide="wallet" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                            </a>
                            <a href="<?= e(url('/clientes/' . (int) $c['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                <i data-lucide="pencil" class="w-5 h-5 text-blue-500 hover:text-blue-700"></i>
                            </a>
                            <?php $deleteFormId = 'delete-client-' . (int) $c['id']; ?>
                            <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/clientes/' . (int) $c['id'] . '/eliminar')) ?>" class="inline">
                                <?= csrfField() ?>
                                <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el cliente <?= e((string) $c['name']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
                                    <i data-lucide="trash-2" class="w-5 h-5 text-red-400 hover:text-red-600"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php require APP_PATH . '/Views/layout/pagination.php'; ?>
