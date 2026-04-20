<?php
$quoteAttachments = $quoteAttachments ?? [];
$invoiceAttachmentCount = (int) ($invoiceAttachmentCount ?? 0);
$remitos = array_values(array_filter($quoteAttachments, static fn ($a) => ($a['type'] ?? '') === 'remito'));
$facturas = array_values(array_filter($quoteAttachments, static fn ($a) => ($a['type'] ?? '') === 'factura'));
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
    'delivered' => 'bg-teal-100 text-teal-900',
];
?>
<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">Presupuesto <?= e($quote['quote_number']) ?></p>
            <h2 class="text-xl font-semibold text-gray-900"><?= e($quote['title'] ?: 'Sin título') ?></h2>
            <p class="text-sm text-gray-600 mt-1">Validez: <?= (int) $quote['validity_days'] ?> días · <?= e($quote['created_at']) ?></p>
            <?php if (empty($readonly)): ?>
                <p class="text-sm text-gray-600 mt-2">
                    <a href="#documentos-adjuntos" class="text-[#1565C0] font-medium hover:underline">Adjuntos (remito / factura)</a>
                    <span class="text-gray-500"> — sección para subir PDF o imagen.</span>
                    <?php if ($invoiceAttachmentCount === 0): ?>
                        <span class="text-gray-500"> El botón <strong class="text-gray-700">Enviar factura por mail</strong> aparece cuando hay al menos una factura cargada.</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2 items-start">
            <span class="inline-flex px-2 py-1 rounded-full text-xs <?= $badges[$st] ?? 'bg-gray-100' ?>"><?= e($st) ?></span>
            <a href="<?= e(url('/presupuestos/' . (int) $quote['id'] . '/pdf')) ?>" class="px-3 py-1.5 rounded-lg bg-[#1a6b3c] text-white text-sm">PDF</a>
            <?php if (empty($readonly)): ?>
                <a href="#documentos-adjuntos" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50" title="Subir remito o factura">📎 Adjuntos</a>
            <?php endif; ?>
            <?php if (empty($readonly) && $invoiceAttachmentCount > 0): ?>
                <a href="<?= e(url('/presupuestos/' . (int) $quote['id'] . '/enviar-mail')) ?>" class="px-3 py-1.5 rounded-lg bg-[#1565C0] text-white text-sm">📧 Enviar factura por mail</a>
            <?php endif; ?>
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
        <?php foreach (['draft', 'sent', 'accepted', 'rejected', 'expired', 'delivered'] as $s): ?>
            <button type="submit" name="status" value="<?= e($s) ?>" class="px-3 py-1 rounded-lg text-xs border border-gray-200 hover:bg-gray-50"><?= e($s) ?></button>
        <?php endforeach; ?>
    </form>
    <p class="text-xs text-gray-600 mt-2 mb-0">El estado <strong>delivered</strong> descuenta del <strong>stock</strong> de cada producto las unidades del presupuesto (según caja vs unidad). Si volvés a otro estado, se revierte el descuento.</p>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Cliente</h3>
        <p class="font-medium"><?= e($quote['client_name'] ?? '—') ?></p>
        <?php if (!empty($quote['business_name'])): ?><p class="text-sm text-gray-600"><?= e($quote['business_name']) ?></p><?php endif; ?>
        <?php if (!empty($quote['phone'])): ?><p class="text-sm"><?= e($quote['phone']) ?></p><?php endif; ?>
        <?php if (!empty($quote['city'])): ?><p class="text-sm text-gray-600"><?= e($quote['city']) ?></p><?php endif; ?>
    </div>

    <?php if (empty($readonly)): ?>
    <div id="documentos-adjuntos" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6 scroll-mt-6">
        <h3 class="text-sm font-semibold text-gray-800">Documentos adjuntos</h3>

        <?php if ($quoteAttachments !== []): ?>
            <div class="space-y-6">
                <?php if ($remitos !== []): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Remitos</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">Tipo</th>
                                        <th class="text-left px-3 py-2">Archivo</th>
                                        <th class="text-left px-3 py-2">Notas</th>
                                        <th class="text-left px-3 py-2">Fecha</th>
                                        <th class="text-right px-3 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($remitos as $a): ?>
                                        <?php
                                        $aid = (int) $a['id'];
                                        $bytes = (int) ($a['file_size'] ?? 0);
                                        $sizeKb = $bytes > 0 ? number_format($bytes / 1024, 1, ',', '.') . ' KB' : '—';
                                        ?>
                                        <tr>
                                            <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800">remito</span></td>
                                            <td class="px-3 py-2"><?= e((string) ($a['original_filename'] ?? '')) ?><span class="text-gray-400 text-xs ml-1">(<?= e($sizeKb) ?>)</span></td>
                                            <td class="px-3 py-2 text-gray-600 max-w-[200px] truncate" title="<?= e((string) ($a['notes'] ?? '')) ?>"><?= e((string) ($a['notes'] ?? '—')) ?></td>
                                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= e((string) ($a['created_at'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                <a href="<?= e(url('/adjuntos/' . $aid . '/ver')) ?>" class="inline-flex p-1.5 text-gray-600 hover:text-[#1a6b3c]" title="Ver"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                                <a href="<?= e(url('/adjuntos/' . $aid . '/descargar')) ?>" class="inline-flex p-1.5 text-gray-600 hover:text-[#1565C0]" title="Descargar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                                <form method="post" action="<?= e(url('/adjuntos/' . $aid . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar este adjunto?');">
                                                    <?= csrfField() ?>
                                                    <button type="submit" class="inline-flex p-1.5 text-gray-600 hover:text-red-600" title="Eliminar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($facturas !== []): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Facturas</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">Tipo</th>
                                        <th class="text-left px-3 py-2">Archivo</th>
                                        <th class="text-left px-3 py-2">Notas</th>
                                        <th class="text-left px-3 py-2">Fecha</th>
                                        <th class="text-right px-3 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($facturas as $a): ?>
                                        <?php
                                        $aid = (int) $a['id'];
                                        $bytes = (int) ($a['file_size'] ?? 0);
                                        $sizeKb = $bytes > 0 ? number_format($bytes / 1024, 1, ',', '.') . ' KB' : '—';
                                        ?>
                                        <tr>
                                            <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-800">factura</span></td>
                                            <td class="px-3 py-2"><?= e((string) ($a['original_filename'] ?? '')) ?><span class="text-gray-400 text-xs ml-1">(<?= e($sizeKb) ?>)</span></td>
                                            <td class="px-3 py-2 text-gray-600 max-w-[200px] truncate" title="<?= e((string) ($a['notes'] ?? '')) ?>"><?= e((string) ($a['notes'] ?? '—')) ?></td>
                                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= e((string) ($a['created_at'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                <a href="<?= e(url('/adjuntos/' . $aid . '/ver')) ?>" class="inline-flex p-1.5 text-gray-600 hover:text-[#1a6b3c]" title="Ver"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                                <a href="<?= e(url('/adjuntos/' . $aid . '/descargar')) ?>" class="inline-flex p-1.5 text-gray-600 hover:text-[#1565C0]" title="Descargar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                                <form method="post" action="<?= e(url('/adjuntos/' . $aid . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar este adjunto?');">
                                                    <?= csrfField() ?>
                                                    <button type="submit" class="inline-flex p-1.5 text-gray-600 hover:text-red-600" title="Eliminar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500">Todavía no hay archivos adjuntos.</p>
        <?php endif; ?>

        <div class="border-t border-gray-200 pt-6" x-data="{ fileName: '' }">
            <p class="text-sm font-medium text-gray-700 mb-3">Subir archivo</p>
            <form method="post" action="<?= e(url('/adjuntos/subir')) ?>" enctype="multipart/form-data" class="space-y-3 max-w-lg">
                <?= csrfField() ?>
                <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="remito">Remito</option>
                        <option value="factura">Factura</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Archivo (PDF, JPG, PNG · máx. 10 MB)</label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                           @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1a6b3c] file:text-white hover:file:bg-[#145a33]">
                    <p class="text-xs text-gray-500 mt-1" x-show="fileName" x-text="'Seleccionado: ' + fileName"></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notas (opcional)</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Subir archivo</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
