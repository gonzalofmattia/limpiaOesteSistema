<?php
$stats = $stats ?? ['total' => 0, 'with_debt' => 0, 'sum_balance' => 0.0];
$withDebt = (bool) ($with_debt ?? false);
$fmtUltimaCompra = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $t = strtotime((string) $raw);
    if ($t === false) {
        return '';
    }
    $meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
    $d = (int) date('j', $t);
    $m = (int) date('n', $t);
    $y = date('Y', $t);

    return $d . ' ' . ($meses[$m] ?? '') . ' ' . $y;
};
$clientsTabUrl = static function (bool $debtTab): string {
    $q = $_GET;
    unset($q['page']);
    if ($debtTab) {
        $q['with_debt'] = '1';
    } else {
        unset($q['with_debt']);
    }
    $base = url('/clientes');
    $s = http_build_query($q);

    return $s !== '' ? $base . '?' . $s : $base;
};
?>
<div class="space-y-5">
    <div class="flex justify-end items-center">
        <?php $uiBtnHref = url('/clientes/crear'); $uiBtnLabel = 'Nuevo cliente'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Total clientes</p><p class="text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Con saldo</p><p class="text-2xl font-semibold"><?= (int) ($stats['with_debt'] ?? 0) ?></p></div>
        <div class="lo-card p-4"><p class="text-xs text-slate-500">Saldo total</p><p class="text-xl font-semibold"><?= formatPrice((float) ($stats['sum_balance'] ?? 0)) ?></p></div>
    </div>
    <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 20) ?>">
        <?php if ($withDebt): ?>
            <input type="hidden" name="with_debt" value="1">
        <?php endif; ?>
        <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nombre, email o teléfono..." class="w-full bg-transparent outline-none text-sm"></div>
        <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
    </form>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <a href="<?= e($clientsTabUrl(false)) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= !$withDebt ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Todos <span class="ml-1 text-[10px] <?= !$withDebt ? 'opacity-80' : '' ?>"><?= (int) ($stats['total'] ?? 0) ?></span></a>
        <a href="<?= e($clientsTabUrl(true)) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $withDebt ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Con deuda <span class="ml-1 text-[10px] <?= $withDebt ? 'opacity-80' : '' ?>"><?= (int) ($stats['with_debt'] ?? 0) ?></span></a>
    </div>
    <div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-right px-4 py-3">Saldo</th>
                <th class="text-left px-4 py-3">Última</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
                <?php
                $balance = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0);
                $ultimaRaw = $c['last_quote_at'] ?? null;
                $ultimaTxt = $fmtUltimaCompra($ultimaRaw);
                ?>
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
                    <td class="px-4 py-3 text-right">
                        <?php if ($balance > 0): ?>
                            <span class="text-red-600 font-semibold"><?= formatPrice($balance) ?></span>
                        <?php elseif ($balance < 0): ?>
                            <span class="text-blue-600"><?= formatPrice($balance) ?></span>
                        <?php else: ?>
                            <span class="text-green-600"><?= formatPrice($balance) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-left text-sm <?= $ultimaTxt !== '' ? 'text-slate-700' : 'text-slate-400' ?>"><?= $ultimaTxt !== '' ? e($ultimaTxt) : '—' ?></td>
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
