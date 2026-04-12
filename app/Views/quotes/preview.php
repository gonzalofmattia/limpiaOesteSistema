<?php
$includeIvaQuote = !empty($quote['include_iva']);
$leyendaIva = priceIvaLegendLine($includeIvaQuote);
$waItems = [];
foreach ($items as $it) {
    $waItems[] = [
        'qty' => (int) $it['quantity'],
        'label' => (string) ($it['unit_label'] ?? ''),
        'product' => trim(($it['code'] ?? '') . ' — ' . ($it['name'] ?? '')),
        'sub' => formatPrice((float) $it['subtotal']),
    ];
}
$waPhone = preg_replace('/\D/', '', (string) ($quote['phone'] ?? ''));
$st = $quote['status'];
$badges = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-800',
    'accepted' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'expired' => 'bg-amber-100 text-amber-800',
];
?>
<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">Presupuesto <?= e($quote['quote_number']) ?></p>
            <h2 class="text-xl font-semibold text-gray-900"><?= e($quote['title'] ?: 'Sin título') ?></h2>
            <p class="text-sm text-gray-600 mt-1">Validez: <?= (int) $quote['validity_days'] ?> días · <?= e($quote['created_at']) ?></p>
        </div>
        <div class="flex flex-wrap gap-2 items-start">
            <span class="inline-flex px-2 py-1 rounded-full text-xs <?= $badges[$st] ?? 'bg-gray-100' ?>"><?= e($st) ?></span>
            <a href="<?= e(url('/presupuestos/' . (int) $quote['id'] . '/pdf')) ?>" class="px-3 py-1.5 rounded-lg bg-[#1a6b3c] text-white text-sm">PDF</a>
            <button type="button" onclick="compartirWhatsAppPresupuesto()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#25D366] text-white rounded-lg hover:bg-[#1fb855] transition text-sm">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.638-1.464A11.932 11.932 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.115 0-4.142-.588-5.904-1.699l-.424-.252-2.752.869.87-2.693-.276-.44A9.72 9.72 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/></svg>
                Enviar por WhatsApp
            </button>
            <a href="<?= e(url('/presupuestos/' . (int) $quote['id'] . '/editar')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Editar</a>
            <a href="<?= e(url('/presupuestos')) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Volver</a>
        </div>
    </div>

    <?php if (empty($readonly)): ?>
    <form method="post" action="<?= e(url('/presupuestos/' . (int) $quote['id'] . '/status')) ?>" class="flex flex-wrap gap-2 items-center bg-white p-4 rounded-xl border border-gray-200">
        <?= csrfField() ?>
        <span class="text-sm text-gray-600">Cambiar estado:</span>
        <?php foreach (['draft', 'sent', 'accepted', 'rejected', 'expired'] as $s): ?>
            <button type="submit" name="status" value="<?= e($s) ?>" class="px-3 py-1 rounded-lg text-xs border border-gray-200 hover:bg-gray-50"><?= e($s) ?></button>
        <?php endforeach; ?>
    </form>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Cliente</h3>
        <p class="font-medium"><?= e($quote['client_name'] ?? '—') ?></p>
        <?php if (!empty($quote['business_name'])): ?><p class="text-sm text-gray-600"><?= e($quote['business_name']) ?></p><?php endif; ?>
        <?php if (!empty($quote['phone'])): ?><p class="text-sm"><?= e($quote['phone']) ?></p><?php endif; ?>
        <?php if (!empty($quote['city'])): ?><p class="text-sm text-gray-600"><?= e($quote['city']) ?></p><?php endif; ?>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-2">Producto</th>
                    <th class="text-left px-4 py-2">Detalle</th>
                    <th class="text-right px-4 py-2">Cant.</th>
                    <th class="text-right px-4 py-2">P. unit.</th>
                    <th class="text-right px-4 py-2">Precio</th>
                    <th class="text-right px-4 py-2">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="px-4 py-2"><?= e($it['code']) ?> — <?= e($it['name']) ?></td>
                        <td class="px-4 py-2 text-gray-700 text-sm"><?= e(quoteItemDetalleDisplay($it)) ?></td>
                        <td class="px-4 py-2 text-right"><?= (int) $it['quantity'] ?></td>
                        <td class="px-4 py-2 text-right text-gray-700"><?= formatPrice(quoteItemIndividualUnitPrice($it, $quote)) ?></td>
                        <td class="px-4 py-2 text-right"><?= formatPrice((float) $it['unit_price']) ?></td>
                        <td class="px-4 py-2 text-right font-medium"><?= formatPrice((float) $it['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-500 mt-2 px-4"><?= e($leyendaIva) ?></p>
        <div class="px-4 py-3 border-t border-gray-200 text-right space-y-1 text-sm">
            <p>Subtotal (neto): <span class="font-medium"><?= formatPrice((float) $quote['subtotal']) ?></span></p>
            <?php if ((float) $quote['iva_amount'] > 0): ?>
                <p>IVA: <span class="font-medium"><?= formatPrice((float) $quote['iva_amount']) ?></span></p>
            <?php endif; ?>
            <p class="text-lg font-semibold text-[#1a6b3c]">Total: <?= formatPrice((float) $quote['total']) ?></p>
        </div>
    </div>

    <?php if (!empty($quote['notes'])): ?>
        <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm text-amber-900">
            <strong>Condiciones:</strong> <?= nl2br(e($quote['notes'])) ?>
        </div>
    <?php endif; ?>
</div>

<script>
function compartirWhatsAppPresupuesto() {
    const cfg = <?= json_encode([
        'numero' => (string) ($quote['quote_number'] ?? ''),
        'cliente' => (string) ($quote['client_name'] ?? 'Cliente'),
        'total' => formatPrice((float) ($quote['total'] ?? 0)),
        'fecha' => date('d/m/Y', strtotime((string) ($quote['created_at'] ?? 'now'))),
        'validez' => (int) ($quote['validity_days'] ?? 7),
        'iva' => $includeIvaQuote ? 'Precios con IVA incluido' : 'Precios sin IVA',
        'items' => $waItems,
        'tel' => $waPhone,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    let mensaje = '*Presupuesto ' + cfg.numero + '*\n';
    mensaje += 'Fecha: ' + cfg.fecha + '\n';
    mensaje += 'Cliente: ' + cfg.cliente + '\n\n';
    cfg.items.forEach(function (it) {
        mensaje += '• ' + it.qty + 'x ' + (it.label || '') + ' - ' + it.product + ' — ' + it.sub + '\n';
    });
    mensaje += '\n*Total: ' + cfg.total + '*\n';
    mensaje += '\n_' + cfg.iva + '_';
    mensaje += '\n_Validez: ' + cfg.validez + ' días_';
    mensaje += '\n\nLIMPIA OESTE — Zona Oeste GBA';
    let url;
    if (cfg.tel && cfg.tel.length >= 10) {
        const tel = cfg.tel.startsWith('54') ? cfg.tel : '54' + cfg.tel;
        url = 'https://wa.me/' + tel + '?text=' + encodeURIComponent(mensaje);
    } else {
        url = 'https://wa.me/?text=' + encodeURIComponent(mensaje);
    }
    window.open(url, '_blank');
}
</script>
