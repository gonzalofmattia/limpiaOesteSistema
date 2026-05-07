<?php
use App\Helpers\ClientReceivableSummary;

$stats = $stats ?? ['total' => 0, 'with_debt' => 0, 'sum_balance' => 0.0];
$withDebt = (bool) ($with_debt ?? false);
$withFavor = (bool) ($with_favor ?? false);
$tolerance = (float) ($balance_tolerance ?? 800.0);
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
$clientsTabUrl = static function (string $tab): string {
    $q = $_GET;
    unset($q['page']);
    unset($q['with_debt'], $q['with_favor']);
    if ($tab === 'debt') {
        $q['with_debt'] = '1';
    } elseif ($tab === 'favor') {
        $q['with_favor'] = '1';
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
        <?php elseif ($withFavor): ?>
            <input type="hidden" name="with_favor" value="1">
        <?php endif; ?>
        <div class="flex-1 h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i><input type="text" name="search" value="<?= e((string) ($search ?? '')) ?>" placeholder="Buscar por nombre, email o teléfono..." class="w-full bg-transparent outline-none text-sm"></div>
        <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
    </form>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <a href="<?= e($clientsTabUrl('all')) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= (!$withDebt && !$withFavor) ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Todos <span class="ml-1 text-[10px] <?= (!$withDebt && !$withFavor) ? 'opacity-80' : '' ?>"><?= (int) ($stats['total'] ?? 0) ?></span></a>
        <a href="<?= e($clientsTabUrl('debt')) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $withDebt ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">Con deuda <span class="ml-1 text-[10px] <?= $withDebt ? 'opacity-80' : '' ?>"><?= (int) ($stats['with_debt'] ?? 0) ?></span></a>
        <a href="<?= e($clientsTabUrl('favor')) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $withFavor ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">A favor <span class="ml-1 text-[10px] <?= $withFavor ? 'opacity-80' : '' ?>"><?= (int) ($stats['with_favor'] ?? 0) ?></span></a>
    </div>
    <div class="lo-table-wrap">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Segmento</th>
                <th class="text-right px-4 py-3">Saldo</th>
                <th class="text-left px-4 py-3">Última</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($clients as $c): ?>
                <?php
                $balance = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0);
                $display = ClientReceivableSummary::getClientDisplayStatus($balance, $tolerance);
                $ultimaRaw = $c['last_quote_at'] ?? null;
                $ultimaTxt = $fmtUltimaCompra($ultimaRaw);
                $segmentKey = (string) ($c['client_type'] ?? 'mayorista');
                $segmentLabel = (string) ($c['segment_label'] ?? ucfirst(str_replace('_', ' ', $segmentKey)));
                $segmentMarkup = ($c['default_markup'] ?? null) !== null && $c['default_markup'] !== ''
                    ? (float) $c['default_markup']
                    : (float) ($c['segment_default_markup'] ?? 60);
                $segmentBadge = match ($segmentKey) {
                    'mayorista' => 'bg-blue-100 text-blue-800',
                    'minorista' => 'bg-gray-100 text-gray-800',
                    'barrio_cerrado' => 'bg-green-100 text-green-800',
                    'gastronomico' => 'bg-orange-100 text-orange-800',
                    'mercadolibre' => 'bg-yellow-100 text-yellow-800',
                    default => 'bg-slate-100 text-slate-700',
                };
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
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= e($segmentBadge) ?>">
                            <?= e($segmentLabel) ?> (<?= e(number_format($segmentMarkup, 2, ',', '.')) ?>%)
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if (($display['status'] ?? '') === 'al_dia'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700" title="<?= e((string) ($display['detail'] ?? '')) ?>">Al día</span>
                        <?php elseif (($display['status'] ?? '') === 'con_deuda'): ?>
                            <span class="text-red-600 font-semibold"><?= formatPrice($balance) ?></span>
                        <?php else: ?>
                            <span class="text-blue-600 font-semibold"><?= formatPrice($balance) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-left text-sm <?= $ultimaTxt !== '' ? 'text-slate-700' : 'text-slate-400' ?>"><?= $ultimaTxt !== '' ? e($ultimaTxt) : '—' ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="<?= e(url('/cuenta-corriente/cliente/' . (int) $c['id'])) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Ver cuenta corriente">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h3m0 0l-2-2m2 2l-2 2" />
                                </svg>
                            </a>
                            <button type="button"
                                    @click="window.dispatchEvent(new CustomEvent('abrir-pago', { detail: { clientId: <?= (int) $c['id'] ?>, clientName: <?= e((string) json_encode((string) ($c['name'] ?? 'Cliente'), JSON_UNESCAPED_UNICODE)) ?>, clientBalance: <?= e((string) json_encode((float) $balance)) ?> } }))"
                                    class="text-green-600 hover:text-green-700 transition hover:scale-105"
                                    title="Registrar pago">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                            <a href="<?= e(url('/clientes/' . (int) $c['id'] . '/editar')) ?>" class="text-blue-600 hover:text-blue-700 transition hover:scale-105" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <?php $deleteFormId = 'delete-client-' . (int) $c['id']; ?>
                            <form id="<?= e($deleteFormId) ?>" method="post" action="<?= e(url('/clientes/' . (int) $c['id'] . '/eliminar')) ?>" class="inline">
                                <?= csrfField() ?>
                                <button type="button" @click="openDeleteModal('<?= e($deleteFormId) ?>', 'el cliente <?= e((string) $c['name']) ?>')" class="text-red-600 hover:text-red-700 transition hover:scale-105" title="Eliminar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
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
