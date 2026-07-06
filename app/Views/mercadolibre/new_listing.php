<?php
$listing = is_array($listing ?? null) ? $listing : null;
$isEdit = $listing !== null;
$listingId = $isEdit ? (int) ($listing['id'] ?? 0) : 0;
$formAction = $isEdit
    ? url('/mercadolibre/listings/' . $listingId)
    : url('/mercadolibre/listings');
$initialProductId = (int) ($listing['product_id'] ?? 0);
$initialProductLabel = '';
if ($initialProductId > 0 && is_array($products ?? null)) {
    foreach ($products as $p) {
        if ((int) ($p['id'] ?? 0) === $initialProductId) {
            $initialProductLabel = trim((string) (($p['code'] ?? '') . ' — ' . ($p['name'] ?? '')));
            break;
        }
    }
}
$cfg = [
    'productId' => $initialProductId,
    'productLabel' => $initialProductLabel,
    'title' => (string) ($listing['title'] ?? ''),
    'mlCategoryId' => (string) ($listing['ml_category_id'] ?? ''),
    'mlMarkup' => $listing['ml_markup'] ?? ($default_markup ?? 75),
    'price' => $listing['price'] ?? '',
    'quantity' => (int) ($listing['available_quantity_override'] ?? ($default_quantity ?? 12)),
    'notes' => (string) ($listing['notes'] ?? ''),
    'listingType' => (string) ($listing['listing_type_id'] ?? 'gold_special'),
    'defaultMarkup' => (float) ($default_markup ?? 75),
    'initialImageCount' => (int) ($initial_image_count ?? 0),
    'mlDescription' => (string) ($initial_description ?? ''),
];
?>
<div class="max-w-3xl space-y-5" x-data="mlListingForm(<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= e(url('/mercadolibre/listings')) ?>" class="text-lo-blue hover:underline">Listings</a>
        <span>/</span>
        <span><?= $isEdit ? 'Editar' : 'Nuevo' ?></span>
    </div>

    <form method="post" action="<?= e($formAction) ?>" class="lo-card p-5 space-y-5">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Producto</label>
            <div class="relative">
                <input type="text" x-model="productQuery" @input.debounce.300ms="searchProduct()"
                       placeholder="Buscar por código o nombre (mín. 2 caracteres)"
                       class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
                <input type="hidden" name="product_id" :value="productId">
                <div x-show="productResults.length > 0" x-cloak @click.outside="productResults = []"
                     class="absolute z-20 left-0 right-0 mt-1 rounded-xl border border-lo-border bg-white shadow-lg max-h-56 overflow-y-auto">
                    <template x-for="item in productResults" :key="item.id">
                        <button type="button" @click="pickProduct(item)"
                                class="w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 border-b border-slate-50 last:border-0">
                            <span class="font-medium text-slate-800" x-text="item.code"></span>
                            <span class="text-slate-500"> — </span>
                            <span x-text="item.name"></span>
                        </button>
                    </template>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500" x-show="productId > 0">
                Seleccionado: <span class="font-medium" x-text="productLabel"></span>
            </p>
            <div x-show="productId > 0 && !productHasImages" x-cloak
                 class="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="flex items-start gap-2">
                    <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-amber-600"></i>
                    <div>
                        <p class="font-semibold">Este producto no tiene fotos</p>
                        <p class="mt-1 text-amber-800">MercadoLibre exige al menos una imagen para publicar. Subí fotos en la ficha del producto antes de continuar.</p>
                        <a :href="productEditUrl" class="mt-2 inline-flex text-sm font-semibold text-lo-blue hover:underline">Ir a editar producto →</a>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-2 mb-1">
                <label for="ml-title" class="text-sm font-medium text-slate-700">Título MercadoLibre</label>
                <span class="text-xs font-medium" :class="title.length > 60 ? 'text-red-600' : 'text-slate-500'" x-text="title.length + '/60'"></span>
            </div>
            <input id="ml-title" type="text" name="title" x-model="title" maxlength="80"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30"
                   :class="title.length > 60 ? 'border-red-400 bg-red-50' : 'border-lo-border'">
            <p class="mt-1 text-xs text-red-600" x-show="title.length > 60">El título en ML admite máximo 60 caracteres.</p>
        </div>

        <div class="space-y-4">
            <div>
                <label for="ml-markup" class="block text-sm font-medium text-slate-700 mb-1">Markup ML (%)</label>
                <input id="ml-markup" type="number" step="0.01" min="0" name="ml_markup" x-model="mlMarkup"
                       @input="onMarkupInput()" @change="onMarkupInput()"
                       :readonly="priceMode === 'target'"
                       class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30"
                       :class="priceMode === 'target' ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : ''">
                <p class="mt-1 text-xs text-slate-500" x-show="priceMode === 'target'" x-cloak>Calculado automáticamente desde el precio objetivo.</p>
            </div>
            <div>
                <label for="ml-target-price" class="block text-sm font-medium text-slate-700 mb-1">Precio objetivo (opcional)</label>
                <input id="ml-target-price" type="text" x-model="targetPrice" placeholder="Ej: 16722,39"
                       @input.debounce.400ms="onTargetPriceInput()"
                       class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
                <p class="mt-1 text-xs text-slate-500">Si lo completás, el markup se calcula al revés y el precio publicado toma este valor.</p>
            </div>
            <div>
                <label for="ml-price" class="block text-sm font-medium text-slate-700 mb-1">Precio publicado</label>
                <input id="ml-price" type="text" name="price" x-model="price"
                       :readonly="priceMode === 'target'"
                       :class="priceMode === 'target' ? 'bg-slate-100 text-slate-800' : ''"
                       placeholder="Se completa desde el preview"
                       class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
            </div>
        </div>

        <div class="rounded-xl border border-lo-border bg-slate-50 p-4 text-sm" x-show="productId > 0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Preview de precio ML</p>
            <template x-if="priceLoading">
                <p class="text-slate-500">Calculando…</p>
            </template>
            <template x-if="!priceLoading && pricePreview">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-slate-700">
                    <div class="flex justify-between sm:block"><dt class="text-slate-500">Costo base</dt><dd class="font-semibold" x-text="formatMoney(pricePreview.costo_base)"></dd></div>
                    <div class="flex justify-between sm:block">
                        <dt class="text-slate-500">Markup aplicado</dt>
                        <dd class="font-semibold" :class="pricePreview.markup_calculado_automaticamente ? 'text-slate-500' : ''">
                            <span x-text="pricePreview.markup_aplicado + '%'"></span>
                            <span x-show="pricePreview.markup_calculado_automaticamente" class="text-xs font-normal text-slate-500"> (calculado automáticamente)</span>
                        </dd>
                    </div>
                    <div class="flex justify-between sm:block"><dt class="text-slate-500">Cargo fijo envío</dt><dd class="font-semibold" x-text="formatMoney(pricePreview.cargo_fijo_envio)"></dd></div>
                    <div class="flex justify-between sm:block"><dt class="text-slate-500">Comisión ML</dt><dd class="font-semibold" x-text="pricePreview.comision_ml_pct + '% (' + formatMoney(pricePreview.comision_ml_monto) + ')'"></dd></div>
                    <div class="flex justify-between sm:block sm:col-span-2 border-t border-slate-200 pt-2 mt-1">
                        <dt class="text-slate-800 font-medium">Precio publicado</dt>
                        <dd class="font-bold text-lg text-lo-blue" x-text="formatMoney(pricePreview.precio_publicado)"></dd>
                    </div>
                    <div class="flex justify-between sm:block"><dt class="text-slate-500">Neto recibido</dt><dd class="font-semibold" x-text="formatMoney(pricePreview.neto_recibido)"></dd></div>
                    <div class="flex justify-between sm:block"><dt class="text-slate-500">Ganancia</dt>
                        <dd class="font-semibold" :class="pricePreview.ganancia_pesos >= 0 ? 'text-green-700' : 'text-red-700'"
                            x-text="formatMoney(pricePreview.ganancia_pesos) + ' (' + pricePreview.ganancia_pct + '%)'"></dd>
                    </div>
                </dl>
            </template>
            <p class="text-xs text-red-600 mt-2" x-show="priceError" x-text="priceError"></p>
            <p class="text-xs text-slate-600 mt-3 leading-relaxed" x-show="!priceLoading && pricePreview">
                El precio por defecto está calculado para que tu ganancia neta en ML sea equivalente a una venta mayorista al 60% de markup, descontando comisión ML y cargo fijo de envío.
            </p>
        </div>

        <div x-show="productId > 0" x-cloak class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label for="ml-description" class="text-sm font-medium text-slate-700">Descripción MercadoLibre</label>
                <button type="button" @click="generateDescription()" :disabled="descLoading || productId <= 0"
                        class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-800 hover:bg-violet-100 disabled:opacity-50">
                    <span x-show="!descLoading">✨ Generar descripción con IA</span>
                    <span x-show="descLoading" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Generando…
                    </span>
                </button>
            </div>
            <textarea id="ml-description" name="ml_description" rows="10" maxlength="50000" x-model="mlDescription"
                      placeholder="Generá la descripción con IA o escribila manualmente. Se guardará en el producto al guardar o publicar."
                      class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30"></textarea>
            <p class="text-xs text-slate-500 flex justify-between">
                <span x-show="descError" class="text-red-600" x-text="descError"></span>
                <span class="ml-auto" :class="mlDescription.length > 50000 ? 'text-red-600 font-semibold' : 'text-slate-500'"
                      x-text="mlDescription.length.toLocaleString('es-AR') + ' / 50.000'"></span>
            </p>
        </div>

        <div>
            <label for="ml-category" class="block text-sm font-medium text-slate-700 mb-1">Categoría ML</label>
            <div class="flex gap-2">
                <input id="ml-category" type="text" name="ml_category_id" x-model="mlCategoryId" placeholder="Ej: MLA1234"
                       class="flex-1 rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
                <button type="button" @click="suggestCategory()" :disabled="categoryLoading || title.trim() === ''"
                        class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                    <span x-text="categoryLoading ? 'Buscando…' : 'Sugerir categoría'"></span>
                </button>
            </div>
            <p class="mt-1 text-xs text-red-600" x-show="categoryError" x-text="categoryError"></p>
            <p class="mt-1 text-xs text-green-700" x-show="categorySuccess" x-text="categorySuccess"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="ml-qty" class="block text-sm font-medium text-slate-700 mb-1">Cantidad disponible</label>
                <input id="ml-qty" type="number" min="1" name="available_quantity_override" x-model.number="quantity"
                       class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
            </div>
            <div>
                <label for="ml-type" class="block text-sm font-medium text-slate-700 mb-1">Tipo de publicación</label>
                <select id="ml-type" name="listing_type_id" x-model="listingType"
                        class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
                    <option value="gold_special">Clásica (gold_special)</option>
                </select>
            </div>
        </div>

        <div>
            <label for="ml-notes" class="block text-sm font-medium text-slate-700 mb-1">Notas internas</label>
            <textarea id="ml-notes" name="notes" rows="3" x-model="notes"
                      class="w-full rounded-lg border border-lo-border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30"></textarea>
        </div>

        <?php if ($isEdit): ?>
            <div class="rounded-lg bg-slate-50 border border-lo-border px-3 py-2 text-xs text-slate-600">
                Estado actual:
                <span class="font-semibold"><?= e((string) ($listing['status'] ?? 'draft')) ?></span>
                <?php if (!empty($listing['ml_item_id'])): ?>
                    · ID ML: <?= e((string) $listing['ml_item_id']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <input type="hidden" name="product_full_description" :value="catalogFullDescription">
        <input type="hidden" name="product_short_description" :value="catalogShortDescription">

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Guardar cambios' : 'Guardar borrador' ?>
            </button>
            <a href="<?= e(url('/mercadolibre/listings')) ?>" class="inline-flex items-center rounded-lg border border-lo-border px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
        </div>
    </form>

    <?php if ($isEdit && ($listing['status'] ?? '') === 'draft'): ?>
        <form method="post" action="<?= e(url('/mercadolibre/listings/' . $listingId . '/publicar')) ?>" class="mt-3" @submit="syncPublishDescription">
            <?= csrfField() ?>
            <input type="hidden" name="ml_description" :value="mlDescription">
            <input type="hidden" name="product_full_description" :value="catalogFullDescription">
            <input type="hidden" name="product_short_description" :value="catalogShortDescription">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-5 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-100">
                <i data-lucide="upload" class="h-4 w-4"></i>Publicar en ML
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
function mlListingForm(cfg) {
    return {
        productId: Number(cfg.productId || 0),
        productLabel: cfg.productLabel || '',
        productQuery: cfg.productLabel || '',
        productResults: [],
        title: cfg.title || '',
        mlCategoryId: cfg.mlCategoryId || '',
        mlMarkup: cfg.mlMarkup ?? cfg.defaultMarkup ?? 75,
        mlDescription: cfg.mlDescription || '',
        catalogFullDescription: '',
        catalogShortDescription: '',
        descLoading: false,
        descError: '',
        targetPrice: '',
        priceMode: 'markup',
        price: cfg.price !== null && cfg.price !== '' ? String(cfg.price) : '',
        quantity: Number(cfg.quantity || 12),
        notes: cfg.notes || '',
        listingType: cfg.listingType || 'gold_special',
        pricePreview: null,
        priceLoading: false,
        priceError: '',
        categoryLoading: false,
        categoryError: '',
        categorySuccess: '',
        productImageCount: Number(cfg.initialImageCount || 0),
        get productHasImages() {
            return this.productImageCount > 0;
        },
        get productEditUrl() {
            return this.productId > 0
                ? window.appUrl('/productos/' + this.productId + '/editar')
                : '#';
        },
        init() {
            if (this.productId > 0) {
                this.refreshPricePreview();
                this.checkProductImages();
            }
            this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
        },
        async searchProduct() {
            const q = (this.productQuery || '').trim();
            if (this.productId > 0 && q !== this.productLabel) {
                this.productId = 0;
                this.productLabel = '';
                this.pricePreview = null;
                this.productImageCount = 0;
            }
            if (q.length < 2) {
                this.productResults = [];
                return;
            }
            try {
                const res = await fetch(window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q)));
                const data = await res.json();
                this.productResults = data.results || [];
            } catch (e) {
                this.productResults = [];
            }
        },
        pickProduct(item) {
            this.productId = Number(item.id || 0);
            this.productLabel = (item.code || '') + ' — ' + (item.name || '');
            this.productQuery = this.productLabel;
            this.productResults = [];
            if (!this.title.trim()) {
                this.title = this.buildSuggestedMlTitle(item);
            }
            this.refreshPricePreview();
            this.checkProductImages();
        },
        buildSuggestedMlTitle(item) {
            const name = this.normalizeTitleCase(String(item.name || '').trim());
            const volume = this.extractMlTitleVolume(item);
            const maxLen = 60;

            let title = volume !== '' ? name + ' ' + volume + ' SEIQ' : name + ' SEIQ';
            if (title.length <= maxLen) {
                return title;
            }

            title = volume !== '' ? name + ' ' + volume : name;
            if (title.length <= maxLen) {
                return title;
            }

            title = name;
            if (title.length <= maxLen) {
                return title;
            }

            return name.slice(0, maxLen).trim();
        },
        extractMlTitleVolume(item) {
            const content = String(item.content || '').trim();
            const unitVolume = String(item.unit_volume || '').trim();
            const minorista = String(item.presentacion_minorista || '').trim();

            if (content === '') {
                const fallback = unitVolume || minorista;
                return fallback ? this.normalizeTitleCase(fallback) : '';
            }

            const contentLower = content.toLowerCase();

            const multiPack = content.match(/(\d+)\s*[x×]\s*([\d.,]+\s*(?:litros?|lts?|lt|ml|cc|gr|g|kg)[\w./]*)/i)
                || content.match(/^(\d+)\s*[x×]\s*(.+)$/i);
            if (multiPack) {
                const unitPart = String(multiPack[2] || '').trim();
                if (unitPart !== '' && !/^x\s*\d/i.test(unitPart)) {
                    return this.normalizeTitleCase(unitPart);
                }
            }

            const isBoxPresentation = /\bx\s*\d+\s*u?\b/i.test(content)
                || /\bpack\b/i.test(contentLower)
                || /\bcaja\b/i.test(contentLower)
                || /\bx\d{1,3}\b/i.test(contentLower);
            if (isBoxPresentation) {
                const fallback = unitVolume || minorista;
                return fallback ? this.normalizeTitleCase(fallback) : '';
            }

            if (!/\d+\s*[x×]\s*\d/i.test(content)) {
                return this.normalizeTitleCase(content);
            }

            return '';
        },
        normalizeTitleCase(text) {
            if (!text) {
                return '';
            }
            return text.split(/\s+/).map((word) => {
                if (word === '') {
                    return word;
                }
                if (word !== word.toUpperCase() || word.length <= 1) {
                    return word;
                }
                return word.charAt(0) + word.slice(1).toLowerCase();
            }).join(' ');
        },
        async checkProductImages() {
            if (this.productId <= 0) {
                this.productImageCount = 0;
                return;
            }
            try {
                const res = await fetch(window.appUrl('/api/ml/producto-imagenes?product_id=' + this.productId));
                const data = await res.json();
                this.productImageCount = data.success ? Number(data.count || 0) : 0;
            } catch (e) {
                this.productImageCount = 0;
            }
            this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
        },
        onMarkupInput() {
            this.priceMode = 'markup';
            this.targetPrice = '';
            this.refreshPricePreview();
        },
        onTargetPriceInput() {
            const t = (this.targetPrice || '').trim();
            if (t === '') {
                this.priceMode = 'markup';
                this.refreshPricePreview();
                return;
            }
            this.priceMode = 'target';
            this.refreshPricePreview();
        },
        async refreshPricePreview() {
            if (this.productId <= 0) {
                this.pricePreview = null;
                return;
            }
            this.priceLoading = true;
            this.priceError = '';
            try {
                const params = new URLSearchParams({ product_id: String(this.productId) });
                if (this.priceMode === 'target' && (this.targetPrice || '').trim() !== '') {
                    params.set('precio_objetivo', (this.targetPrice || '').trim().replace(',', '.'));
                } else {
                    params.set('markup', String(this.mlMarkup ?? ''));
                }
                const res = await fetch(window.appUrl('/api/ml/precio-preview?' + params.toString()));
                const data = await res.json();
                if (!data.success) {
                    this.pricePreview = null;
                    this.priceError = data.error || 'No se pudo calcular el precio.';
                    return;
                }
                this.pricePreview = data;
                if (data.markup_calculado_automaticamente) {
                    this.mlMarkup = data.markup_aplicado;
                }
                if (this.priceMode === 'target') {
                    this.price = (this.targetPrice || '').trim().replace(',', '.');
                } else if (!this.price.trim()) {
                    this.price = String(data.precio_publicado ?? '');
                }
            } catch (e) {
                this.pricePreview = null;
                this.priceError = 'Error de red al calcular precio.';
            } finally {
                this.priceLoading = false;
            }
        },
        async suggestCategory() {
            const t = (this.title || '').trim();
            if (t === '') {
                this.categoryError = 'Ingresá un título antes de sugerir categoría.';
                this.categorySuccess = '';
                return;
            }
            this.categoryLoading = true;
            this.categoryError = '';
            this.categorySuccess = '';
            try {
                const params = new URLSearchParams({ title: t });
                if (this.productId > 0) {
                    params.set('product_id', String(this.productId));
                }
                const res = await fetch(window.appUrl('/api/ml/sugerir-categoria?' + params.toString()));
                const data = await res.json();
                if (!data.success || !data.category_id) {
                    this.categoryError = data.error || 'No se encontró categoría.';
                    return;
                }
                this.mlCategoryId = data.category_id;
                this.categorySuccess = 'Categoría sugerida: ' + data.category_id;
            } catch (e) {
                this.categoryError = 'Error al consultar categoría ML.';
            } finally {
                this.categoryLoading = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        async generateDescription() {
            if (this.productId <= 0) {
                this.descError = 'Seleccioná un producto primero.';
                return;
            }
            this.descLoading = true;
            this.descError = '';
            try {
                const body = new URLSearchParams({ product_id: String(this.productId) });
                const res = await fetch(window.appUrl('/api/ml/generar-descripcion'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                });
                const data = await res.json();
                if (!data.success) {
                    this.descError = data.error || 'No se pudo generar la descripción.';
                    if (Array.isArray(data.banned_terms) && data.banned_terms.length > 0) {
                        this.descError += ' Términos detectados: ' + data.banned_terms.join(', ');
                        if (data.descripcion) {
                            this.mlDescription = data.descripcion;
                            this.catalogFullDescription = data.full_description || '';
                            this.catalogShortDescription = data.short_description || '';
                        }
                    }
                    return;
                }
                this.mlDescription = data.descripcion || '';
                this.catalogFullDescription = data.full_description || '';
                this.catalogShortDescription = data.short_description || '';
            } catch (e) {
                this.descError = 'Error de red al generar la descripción.';
            } finally {
                this.descLoading = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        syncPublishDescription() {},
        formatMoney(value) {
            const n = Number(value || 0);
            return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
