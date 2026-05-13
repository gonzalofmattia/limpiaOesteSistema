<?php
use App\Helpers\ClientReceivableSummary;

$c = is_array($client ?? null) ? $client : [];
$cid = (int) ($c['id'] ?? 0);
$recent = is_array($recent_quotes ?? null) ? $recent_quotes : [];
$top = is_array($top_products ?? null) ? $top_products : [];
$summary = is_array($purchase_summary ?? null) ? $purchase_summary : ['total_count' => 0, 'avg_ticket' => 0.0, 'last_purchase_at' => null];
$balance = (float) ($balance ?? 0);
$tolerance = (float) ($balance_tolerance ?? 800.0);
$display = ClientReceivableSummary::getClientDisplayStatus($balance, $tolerance);
$totalBilled = (float) ($total_billed ?? 0);
$lastPay = is_array($last_payment ?? null) ? $last_payment : null;
$lastQuoteId = isset($last_quote_id) && $last_quote_id !== null ? (int) $last_quote_id : null;

$fmtDate = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);

    return $t !== false ? date('d/m/Y', $t) : '—';
};

$quoteNro = static function (array $q): string {
    $sale = trim((string) ($q['sale_number'] ?? ''));
    if ($sale !== '') {
        return $sale;
    }
    $qn = trim((string) ($q['quote_number'] ?? ''));
    if ($qn !== '') {
        return $qn;
    }

    return '#' . (int) ($q['id'] ?? 0);
};

$quoteLineTotal = static function (array $q): float {
    if (!empty($q['is_mercadolibre'])) {
        return round((float) ($q['ml_net_amount'] ?? 0), 2);
    }

    return round((float) ($q['total'] ?? 0), 2);
};
?>
<div class="space-y-5 max-w-5xl">
    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:justify-between sm:items-start gap-3">
        <div>
            <p class="text-xs text-slate-500 mb-1"><a href="<?= e(url('/clientes')) ?>" class="text-lo-blue hover:underline">Clientes</a></p>
            <h1 class="text-xl font-semibold text-slate-900"><?= e((string) ($c['name'] ?? 'Cliente')) ?></h1>
            <?php if (!empty($c['business_name'])): ?>
                <p class="text-sm text-slate-600"><?= e((string) $c['business_name']) ?></p>
            <?php endif; ?>
            <p class="text-sm text-slate-500 mt-1">
                <?php if (!empty($c['phone'])): ?><span><?= e((string) $c['phone']) ?></span><?php endif; ?>
                <?php if (!empty($c['email'])): ?>
                    <?php if (!empty($c['phone'])): ?> · <?php endif; ?>
                    <span><?= e((string) $c['email']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <?php if ($lastQuoteId !== null): ?>
                <a href="<?= e(url('/presupuestos/crear?duplicar=' . $lastQuoteId)) ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-lo-blue text-white text-sm font-medium hover:opacity-95">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                    Repetir último pedido
                </a>
            <?php endif; ?>
            <a href="<?= e(url('/clientes/' . $cid . '/editar')) ?>" class="px-3 py-2 rounded-lg border border-lo-border text-sm text-slate-700 hover:bg-slate-50">Editar datos</a>
            <a href="<?= e(url('/cuenta-corriente/cliente/' . $cid)) ?>" class="px-3 py-2 rounded-lg border border-lo-border text-sm text-lo-blue hover:bg-lo-blueSoft">Cuenta corriente</a>
        </div>
    </div>

    <section class="space-y-3" aria-labelledby="hist-compras">
        <h2 id="hist-compras" class="text-sm font-semibold text-slate-800 border-b border-lo-border pb-2">Historial de compras</h2>
        <div class="grid grid-cols-2 gap-2 sm:gap-3">
            <div class="lo-card p-3 sm:p-4">
                <p class="text-[11px] sm:text-xs text-slate-500">Total compras</p>
                <p class="text-lg sm:text-2xl font-semibold tabular-nums"><?= (int) ($summary['total_count'] ?? 0) ?></p>
            </div>
            <div class="lo-card p-3 sm:p-4">
                <p class="text-[11px] sm:text-xs text-slate-500">Ticket promedio</p>
                <p class="text-lg sm:text-2xl font-semibold tabular-nums"><?= formatPrice((float) ($summary['avg_ticket'] ?? 0)) ?></p>
            </div>
            <div class="lo-card p-3 sm:p-4">
                <p class="text-[11px] sm:text-xs text-slate-500">Última compra</p>
                <p class="text-sm sm:text-lg font-semibold"><?= e($fmtDate($summary['last_purchase_at'] ?? null)) ?></p>
            </div>
            <div class="lo-card p-3 sm:p-4">
                <p class="text-[11px] sm:text-xs text-slate-500">Balance</p>
                <p class="text-lg sm:text-2xl font-semibold tabular-nums <?= ($display['color'] ?? '') === 'red' ? 'text-red-600' : (($display['color'] ?? '') === 'blue' ? 'text-blue-600' : 'text-emerald-700') ?>">
                    <?= formatPrice($balance) ?>
                </p>
                <p class="text-[10px] text-slate-400 mt-0.5"><?= e((string) ($display['label'] ?? '')) ?></p>
            </div>
        </div>
        <div class="lo-card p-3 sm:p-4 grid sm:grid-cols-3 gap-3 text-sm border-t border-lo-border">
            <div>
                <p class="text-xs text-slate-500">Total facturado (hist.)</p>
                <p class="font-semibold tabular-nums"><?= formatPrice($totalBilled) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Último pago</p>
                <?php if ($lastPay !== null && ($lastPay['amount'] ?? 0) > 0): ?>
                    <p class="font-semibold tabular-nums"><?= formatPrice((float) $lastPay['amount']) ?></p>
                    <p class="text-xs text-slate-500"><?= e($fmtDate($lastPay['transaction_date'] ?? null)) ?></p>
                <?php else: ?>
                    <p class="text-slate-400">—</p>
                <?php endif; ?>
            </div>
            <div class="sm:text-right sm:self-center">
                <a href="<?= e(url('/cuenta-corriente/cliente/' . $cid)) ?>" class="text-sm text-lo-blue hover:underline">Ver movimientos en CC</a>
            </div>
        </div>

        <div class="lo-table-wrap -mx-1 sm:mx-0">
            <table class="min-w-[520px] w-full text-sm lo-table">
                <thead class="bg-slate-50 border-b border-lo-border text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2">Nro</th>
                        <th class="text-left px-3 py-2">Fecha</th>
                        <th class="text-right px-3 py-2">Total</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-right px-3 py-2">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($recent === []): ?>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">Sin presupuestos aceptados o entregados aún.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent as $q): ?>
                            <?php
                            $st = (string) ($q['status'] ?? '');
                            $notes = trim((string) ($q['notes'] ?? ''));
                            ?>
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-3 py-2 font-medium whitespace-nowrap"><?= e($quoteNro($q)) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap"><?= e($fmtDate($q['created_at'] ?? null)) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= formatPrice($quoteLineTotal($q)) ?></td>
                                <td class="px-3 py-2">
                                    <?php $status = $st; ?>
                                    <?php include APP_PATH . '/Views/components/status_badge.php'; ?>
                                    <?php if ($notes !== ''): ?>
                                        <details class="mt-1 max-w-[200px]">
                                            <summary class="text-[11px] text-lo-blue cursor-pointer select-none">Notas</summary>
                                            <p class="text-[11px] text-slate-600 mt-1 whitespace-pre-wrap break-words"><?= e($notes) ?></p>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="<?= e(url('/presupuestos/' . (int) ($q['id'] ?? 0))) ?>" class="text-lo-blue text-xs font-medium hover:underline whitespace-nowrap">Ver presupuesto</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3" aria-labelledby="top-prod">
        <h2 id="top-prod" class="text-sm font-semibold text-slate-800 border-b border-lo-border pb-2">Productos más comprados</h2>
        <p class="text-xs text-slate-500">Unidades estimadas (líneas directas + componentes de combos).</p>
        <div class="lo-table-wrap -mx-1 sm:mx-0">
            <table class="min-w-[480px] w-full text-sm lo-table">
                <thead class="bg-slate-50 border-b border-lo-border text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 w-10">#</th>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-right px-3 py-2">Unidades</th>
                        <th class="text-left px-3 py-2">Última compra</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($top === []): ?>
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">Sin datos de productos todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top as $i => $row): ?>
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2 text-slate-500"><?= (int) $i + 1 ?></td>
                                <td class="px-3 py-2"><?= e((string) ($row['name'] ?? '')) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium"><?= e(number_format((float) ($row['total_units'] ?? 0), 0, ',', '.')) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap"><?= e($fmtDate($row['last_purchase'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
