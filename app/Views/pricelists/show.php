<?php
$waGroups = [];
foreach ($items as $it) {
    $cn = (string) $it['category_name'];
    if (!isset($waGroups[$cn])) {
        $waGroups[$cn] = [];
    }
    $p = (float) ($list['include_iva'] && $it['precio_venta_iva'] ? $it['precio_venta_iva'] : $it['precio_venta']);
    $waGroups[$cn][] = [
        'name' => (string) $it['name'],
        'precio' => formatPrice($p),
    ];
}
$waMeta = [
    'listName' => (string) $list['name'],
    'ivaLine' => !empty($list['include_iva']) ? 'Precios con IVA incluido' : 'Precios sin IVA',
    'wa' => (string) (setting('empresa_whatsapp', '') ?? ''),
    'ig' => (string) (setting('empresa_instagram', '') ?? ''),
];
?>
<div class="flex flex-wrap justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-gray-500">Generada: <?= e($list['generated_at'] ?? $list['created_at']) ?></p>
        <?php if ($list['description']): ?>
            <p class="text-gray-700 mt-1"><?= e($list['description']) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if (!empty($list['pdf_path'])): ?>
            <a href="<?= e(url('/listas/' . (int) $list['id'] . '/pdf')) ?>" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm">Descargar PDF</a>
        <?php endif; ?>
        <button type="button" onclick="compartirListaWhatsApp()" class="inline-flex items-center gap-2 px-4 py-2 bg-[#25D366] text-white rounded-lg hover:bg-[#1fb855] transition text-sm">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.638-1.464A11.932 11.932 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.115 0-4.142-.588-5.904-1.699l-.424-.252-2.752.869.87-2.693-.276-.44A9.72 9.72 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/></svg>
            Compartir por WhatsApp
        </button>
        <a href="<?= e(url('/listas')) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Volver</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-4 py-2">Categoría</th>
                <th class="text-left px-4 py-2">Código</th>
                <th class="text-left px-4 py-2">Producto</th>
                <th class="text-right px-4 py-2">Lista base</th>
                <th class="text-right px-4 py-2">Costo LO</th>
                <th class="text-right px-4 py-2">Venta</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $it): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-600"><?= e($it['category_name']) ?></td>
                    <td class="px-4 py-2 font-mono text-xs"><?= e($it['code']) ?></td>
                    <td class="px-4 py-2"><?= e($it['name']) ?></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) $it['precio_base_usado']) ?></td>
                    <td class="px-4 py-2 text-right"><?= formatPrice((float) $it['costo_limpia_oeste']) ?></td>
                    <td class="px-4 py-2 text-right font-medium">
                        <?= formatPrice((float) ($list['include_iva'] && $it['precio_venta_iva'] ? $it['precio_venta_iva'] : $it['precio_venta'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-xs text-gray-500 mt-4 px-4 pb-4"><?= e(priceIvaLegendLine(!empty($list['include_iva']))) ?></p>
</div>

<script>
function compartirListaWhatsApp() {
    const groups = <?= json_encode($waGroups, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    const meta = <?= json_encode($waMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    let mensaje = '*Lista de Precios: ' + meta.listName + '*\n';
    mensaje += 'Fecha: <?= e(date('d/m/Y')) ?>\n\n';
    Object.keys(groups).forEach(function (cat) {
        mensaje += '*' + cat + '*\n';
        groups[cat].forEach(function (row) {
            mensaje += '• ' + row.name + ' — ' + row.precio + '\n';
        });
        mensaje += '\n';
    });
    mensaje += '_' + meta.ivaLine + '_';
    mensaje += '\n_Entrega en 24hs — Zona Oeste GBA_';
    mensaje += '\n\nLIMPIA OESTE';
    if (meta.wa) { mensaje += '\nWhatsApp: ' + meta.wa; }
    if (meta.ig) { mensaje += '\nIG: ' + meta.ig; }
    window.open('https://wa.me/?text=' + encodeURIComponent(mensaje), '_blank');
}
</script>
