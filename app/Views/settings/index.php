<?php
/** @var array<string, array{setting_key:string,setting_value:string,description:?string}> $settings */
$labels = [
    'empresa_nombre' => ['Empresa', 'Nombre comercial'],
    'empresa_tagline' => ['Empresa', 'Leyenda'],
    'empresa_instagram' => ['Empresa', 'Instagram'],
    'empresa_whatsapp' => ['Empresa', 'WhatsApp'],
    'empresa_zona' => ['Empresa', 'Zona de cobertura'],
    'default_markup' => ['Pricing', 'Markup global default (%)'],
    'iva_rate' => ['Pricing', 'Tasa IVA (%)'],
    'lista_seiq_numero' => ['Pricing', 'Lista Seiq N°'],
    'lista_seiq_fecha' => ['Pricing', 'Fecha lista Seiq'],
    'moneda' => ['Sistema', 'Moneda'],
    'mostrar_iva' => ['Sistema', 'Mostrar IVA en listados (0/1)'],
    'quote_prefix' => ['Sistema', 'Prefijo presupuestos'],
    'quote_validity_days' => ['Sistema', 'Validez presupuesto default (días)'],
    'seiq_cliente_id' => ['Seiq', 'ID Cliente Seiq'],
    'seiq_cliente_nombre' => ['Seiq', 'Nombre en Seiq'],
    'seiq_condicion_pago' => ['Seiq', 'Condición de pago'],
    'seiq_observaciones' => ['Seiq', 'Observaciones (pedido)'],
];
$groups = ['Empresa' => [], 'Pricing' => [], 'Sistema' => [], 'Seiq' => []];
foreach ($labels as $key => $meta) {
    $groups[$meta[0]][$key] = $meta[1];
}
?>
<div class="max-w-3xl space-y-8">
    <form method="post" action="<?= e(url('/settings')) ?>" class="space-y-8">
        <?= csrfField() ?>
        <?php foreach ($groups as $gname => $keys): ?>
            <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4"><?= e($gname) ?></h2>
                <div class="space-y-4">
                    <?php foreach ($keys as $key => $label): ?>
                        <?php $row = $settings[$key] ?? ['setting_value' => '']; ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?></label>
                            <input type="text" name="s_<?= e($key) ?>" value="<?= e($row['setting_value'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
                            <?php if (!empty($row['description'])): ?>
                                <p class="text-xs text-gray-500 mt-1"><?= e($row['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar configuración</button>
    </form>
</div>
