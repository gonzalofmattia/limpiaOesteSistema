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
    'sale_prefix' => ['Sistema', 'Prefijo ventas'],
    'quote_validity_days' => ['Sistema', 'Validez presupuesto default (días)'],
    'catalog_markup_mayorista' => ['Catálogo API', 'Markup % mayorista (vacío = reglas normales)'],
    'catalog_markup_minorista' => ['Catálogo API', 'Markup % minorista (vacío = igual que mayorista)'],
];
$groups = ['Empresa' => [], 'Pricing' => [], 'Catálogo API' => [], 'Sistema' => []];
foreach ($labels as $key => $meta) {
    $groups[$meta[0]][$key] = $meta[1];
}
?>
<div class="space-y-5">
    <form method="post" action="<?= e(url('/settings')) ?>" class="grid lg:grid-cols-[280px_1fr] gap-6">
        <?= csrfField() ?>
        <nav class="space-y-2">
            <?php
            $menu = [
                ['label' => 'Empresa', 'icon' => 'building-2'],
                ['label' => 'Usuarios y permisos', 'icon' => 'users'],
                ['label' => 'Notificaciones', 'icon' => 'bell'],
                ['label' => 'Seguridad', 'icon' => 'shield'],
                ['label' => 'Marca y apariencia', 'icon' => 'palette'],
                ['label' => 'Facturación', 'icon' => 'credit-card'],
            ];
            foreach ($menu as $m):
            ?>
                <div class="rounded-xl border border-lo-border bg-white px-3 py-2.5 text-sm <?= $m['label'] === 'Empresa' ? 'bg-sky-50 border-sky-200' : '' ?>">
                    <div class="flex items-center gap-2">
                        <span class="h-8 w-8 rounded-lg bg-slate-100 grid place-items-center"><i data-lucide="<?= e($m['icon']) ?>" class="h-4 w-4 text-slate-600"></i></span>
                        <span><?= e($m['label']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="space-y-6">
        <?php foreach ($groups as $gname => $keys): ?>
            <section class="lo-card p-6">
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
        <section class="lo-card p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Proveedores</h2>
            <div class="space-y-6">
                <?php foreach (($suppliers ?? []) as $supplier): ?>
                    <div class="border border-gray-100 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3"><?= e((string) $supplier['name']) ?></h3>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">ID Cliente</label>
                                <input type="text" name="supplier_<?= (int) $supplier['id'] ?>_cliente_id" value="<?= e((string) ($supplier['cliente_id'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Nombre Cliente</label>
                                <input type="text" name="supplier_<?= (int) $supplier['id'] ?>_cliente_nombre" value="<?= e((string) ($supplier['cliente_nombre'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Condición de pago</label>
                                <input type="text" name="supplier_<?= (int) $supplier['id'] ?>_condicion_pago" value="<?= e((string) ($supplier['condicion_pago'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Observaciones</label>
                                <input type="text" name="supplier_<?= (int) $supplier['id'] ?>_observaciones" value="<?= e((string) ($supplier['observaciones'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <div class="flex justify-end"><button type="submit" class="lo-btn-primary">Guardar cambios</button></div>
        </div>
    </form>
</div>
