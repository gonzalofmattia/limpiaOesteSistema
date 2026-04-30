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
            <i data-lucide="message-circle" class="w-4 h-4 text-white"></i>
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
                <th class="text-right px-4 py-2">Precio caja/bulto</th>
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
                        <?= formatPrice((float) (((int) $list['include_iva'] === 1) && $it['precio_venta_iva'] ? $it['precio_venta_iva'] : $it['precio_venta'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-xs text-gray-500 mt-4 px-4 pb-4"><?= e(priceIvaLegendLine((int) $list['include_iva'] === 1)) ?></p>
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
    mensaje += '\n_Entrega prioritaria — <?= e(setting('empresa_zona', 'Zona Oeste GBA')) ?>_';
    mensaje += '\n\nLIMPIA OESTE';
    if (meta.wa) { mensaje += '\nWhatsApp: ' + meta.wa; }
    if (meta.ig) { mensaje += '\nIG: ' + meta.ig; }
    window.open('https://wa.me/?text=' + encodeURIComponent(mensaje), '_blank');
}
</script>
