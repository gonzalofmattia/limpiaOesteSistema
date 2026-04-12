<?php
use App\Helpers\PricingEngine;

$isEdit = $product !== null;
$action = url($isEdit ? '/productos/' . (int) $product['id'] : '/productos');
$p = $product ?? [];

$fieldsBySlug = [];
foreach ($categories as $c) {
    $fieldsBySlug[$c['slug']] = PricingEngine::getAvailablePriceFields($c['slug']);
}
$fieldsJson = json_encode($fieldsBySlug, JSON_UNESCAPED_UNICODE);
$catsJson = json_encode(array_map(function ($c) {
    return [
        'id' => (int) $c['id'],
        'name' => $c['name'],
        'slug' => $c['slug'],
        'default_discount' => (float) $c['default_discount'],
        'default_markup' => $c['default_markup'],
    ];
}, $categories), JSON_UNESCAPED_UNICODE);

$selectedCatId = $p['category_id'] ?? ($categories[0]['id'] ?? '');
?>
<script>
window.__productFormCfg = {
    fieldsBySlug: <?= $fieldsJson ?>,
    categories: <?= $catsJson ?>,
    csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
    initialCategoryId: <?= json_encode((string) $selectedCatId, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<div class="max-w-4xl" x-data="productForm()" x-init="init(window.__productFormCfg)">
    <form method="post" action="<?= e($action) ?>" id="product-form" class="space-y-8"
          @input.debounce.400ms="preview()" @change="preview()">
        <?= csrfField() ?>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Identificación</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select name="category_id" x-model="categoryId" @change="syncSlug(); preview()"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) ($p['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" name="code" required value="<?= e($p['code'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" required value="<?= e($p['name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre corto</label>
                    <input type="text" name="short_name" value="<?= e($p['short_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($p['description'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Presentación</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contenido</label>
                    <input type="text" name="content" value="<?= e($p['content'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Presentación</label>
                    <input type="text" name="presentation" value="<?= e($p['presentation'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unidades por caja</label>
                    <input type="number" name="units_per_box" min="1" value="<?= e((string) ($p['units_per_box'] ?? 1)) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Volumen unitario</label>
                    <input type="text" name="unit_volume" value="<?= e($p['unit_volume'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Equivalencia</label>
                    <input type="text" name="equivalence" value="<?= e($p['equivalence'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">EAN13</label>
                    <input type="text" name="ean13" maxlength="13" value="<?= e($p['ean13'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Venta</h2>
            <p class="text-xs text-gray-500 mb-4">Define cómo se cotiza por defecto la presentación principal en presupuestos (siempre podés elegir también venta por unidad suelta).</p>
            <div class="space-y-4">
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-2">Tipo de venta por defecto</span>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sale_unit_type" value="caja" <?= (($p['sale_unit_type'] ?? 'caja') !== 'unidad') ? 'checked' : '' ?>>
                            Por caja / pack / bulto
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sale_unit_type" value="unidad" <?= (($p['sale_unit_type'] ?? 'caja') === 'unidad') ? 'checked' : '' ?>>
                            Por unidad
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Etiqueta de venta</label>
                    <input type="text" name="sale_unit_label" value="<?= e($p['sale_unit_label'] ?? 'Caja') ?>"
                           x-bind:placeholder="placeholderPackLabel()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción para presupuesto (ve el cliente)</label>
                    <input type="text" name="sale_unit_description" value="<?= e($p['sale_unit_description'] ?? '') ?>"
                           x-bind:placeholder="placeholderSaleDesc()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-gray-500 mt-1">Ej.: pack completo, caja de bidones, etc. Se muestra en la columna «Detalle» del presupuesto y PDF.</p>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Precios lista Seiq (sin IVA)</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <?php
                $priceInputs = [
                    'precio_lista_unitario' => 'Unitario',
                    'precio_lista_caja' => 'Caja',
                    'precio_lista_bidon' => 'Bidón 5L',
                    'precio_lista_litro' => 'Litro',
                    'precio_lista_bulto' => 'Bulto',
                    'precio_lista_sobre' => 'Sobre',
                ];
                foreach ($priceInputs as $field => $label):
                ?>
                    <div x-show="showField('<?= $field ?>')" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?></label>
                        <input type="text" name="<?= $field ?>" value="<?= e(isset($p[$field]) && $p[$field] !== null ? (string) $p[$field] : '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm price-input">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Pricing LIMPIA OESTE</h2>
            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Override descuento (%)</label>
                    <input type="text" name="discount_override" placeholder="Vacío = categoría"
                           value="<?= $p['discount_override'] !== null && $p['discount_override'] !== '' ? e((string) $p['discount_override']) : '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Override markup (%)</label>
                    <input type="text" name="markup_override" placeholder="Vacío = global / categoría"
                           value="<?= $p['markup_override'] !== null && $p['markup_override'] !== '' ? e((string) $p['markup_override']) : '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm space-y-1">
                <p class="font-medium text-gray-800 mb-2">Vista previa (campo principal categoría)</p>
                <p>Precio lista: <span class="font-mono" x-text="fmt.lista">—</span></p>
                <p>Descuento: <span x-text="fmt.discount">—</span> (<span x-text="src.discount">—</span>)</p>
                <p>Costo LO: <span class="font-mono" x-text="fmt.costo">—</span></p>
                <p>Markup: <span x-text="fmt.markup">—</span> (<span x-text="src.markup">—</span>)</p>
                <p>Precio venta: <span class="font-mono font-semibold text-[#1a6b3c]" x-text="fmt.venta">—</span></p>
                <p>Margen: <span class="font-mono" x-text="fmt.margen">—</span></p>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-4">Información adicional</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dilución</label>
                    <input type="text" name="dilution" value="<?= e($p['dilution'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Costo de uso</label>
                    <input type="text" name="usage_cost" value="<?= isset($p['usage_cost']) && $p['usage_cost'] !== null ? e((string) $p['usage_cost']) : '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pallet / logística</label>
                    <input type="text" name="pallet_info" value="<?= e($p['pallet_info'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                    <input type="number" name="sort_order" value="<?= e((string) ($p['sort_order'] ?? 0)) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex items-center gap-4 sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" <?= !isset($p['is_active']) || $p['is_active'] ? 'checked' : '' ?>> Activo
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?>> Destacado
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas internas</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($p['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Guardar</button>
            <a href="<?= e(url('/productos')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>

<script>
function productForm() {
    return {
        categoryId: '',
        fieldsBySlug: {},
        categories: [],
        csrf: '',
        slug: '',
        fmt: { lista: '—', discount: '—', costo: '—', markup: '—', venta: '—', margen: '—' },
        src: { discount: '—', markup: '—' },
        init(cfg) {
            this.fieldsBySlug = cfg.fieldsBySlug || {};
            this.categories = cfg.categories || [];
            this.csrf = cfg.csrf || '';
            this.categoryId = cfg.initialCategoryId || '';
            this.syncSlug();
            this.preview();
        },
        syncSlug() {
            const sel = document.querySelector('select[name=category_id]');
            if (sel) this.categoryId = sel.value;
            const cat = this.categories.find(c => String(c.id) === String(this.categoryId));
            this.slug = cat ? cat.slug : '';
        },
        showField(field) {
            if (!this.slug || !this.fieldsBySlug[this.slug]) return true;
            return this.fieldsBySlug[this.slug].includes(field);
        },
        placeholderPackLabel() {
            const m = { aerosoles: 'Pack x12', bidones: 'Caja 4x5L', masivo: 'Caja x12', sobres: 'Caja', alimenticia: 'Caja 4x5L' };
            return m[this.slug] || 'Caja / pack';
        },
        placeholderSaleDesc() {
            const m = {
                aerosoles: 'Pack 12 aerosoles 295ML',
                bidones: 'Caja 4 bidones x 5 Litros',
                masivo: 'Caja 12 unidades 1L',
                sobres: 'Caja completa — presentación',
                alimenticia: 'Caja 4 bidones x 5 Litros'
            };
            return m[this.slug] || 'Descripción para el cliente';
        },
        async preview() {
            this.syncSlug();
            const form = document.getElementById('product-form');
            const fd = new FormData(form);
            const body = {
                _csrf: this.csrf,
                category_slug: this.slug,
                category_id: parseInt(this.categoryId, 10) || 0,
                discount_override: fd.get('discount_override') || '',
                markup_override: fd.get('markup_override') || '',
                price_field: '',
                include_iva: false,
                precio_lista_unitario: fd.get('precio_lista_unitario') || '',
                precio_lista_caja: fd.get('precio_lista_caja') || '',
                precio_lista_bidon: fd.get('precio_lista_bidon') || '',
                precio_lista_litro: fd.get('precio_lista_litro') || '',
                precio_lista_bulto: fd.get('precio_lista_bulto') || '',
                precio_lista_sobre: fd.get('precio_lista_sobre') || '',
            };
            const cat = this.categories.find(c => String(c.id) === String(this.categoryId));
            if (cat) {
                body.default_discount = cat.default_discount;
                body.category_default_markup = cat.default_markup;
            }
            try {
                const res = await fetch(window.appUrl('/api/pricing/preview'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body)
                });
                const j = await res.json();
                if (!j.formatted) return;
                this.fmt.lista = j.formatted.lista;
                this.fmt.costo = j.formatted.costo;
                this.fmt.venta = j.formatted.venta;
                this.fmt.margen = j.formatted.margen;
                this.fmt.discount = (j.calc && j.calc.discount_percent != null) ? j.calc.discount_percent.toFixed(1).replace('.', ',') + '%' : '—';
                this.fmt.markup = (j.calc && j.calc.markup_percent != null) ? j.calc.markup_percent.toFixed(1).replace('.', ',') + '%' : '—';
                this.src.discount = j.discount_source || '—';
                this.src.markup = j.markup_source || '—';
            } catch (e) { /* silencioso */ }
        }
    };
}
</script>
