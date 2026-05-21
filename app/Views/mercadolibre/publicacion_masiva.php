<?php
$products = is_array($products ?? null) ? $products : [];
$categories = is_array($categories ?? null) ? $categories : [];
$defaultMarkup = (float) ($default_markup ?? 75);
$defaultQuantity = (int) ($default_quantity ?? 12);
$csrf = csrfToken();
$executeUrl = url('/mercadolibre/publicacion-masiva/ejecutar');
$productsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<div class="space-y-6" x-data="mlBulkPublish(<?= e($productsJson ?: '[]') ?>)">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-xl font-semibold text-slate-900">Publicación masiva</h2>
            <p class="mt-1 text-sm text-slate-600">
                Productos activos sin listing activo en ML. Seleccioná los que querés publicar.
            </p>
        </div>
        <a href="<?= e(url('/mercadolibre')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver al panel
        </a>
    </div>

    <section class="lo-card p-4">
        <div class="flex flex-wrap items-end gap-4">
            <label class="block min-w-[180px]">
                <span class="text-sm text-slate-600">Categoría</span>
                <select x-model="filterCategory"
                        class="mt-1 block w-full rounded-lg border border-lo-border px-3 py-2 text-sm text-slate-800 focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) ($cat['id'] ?? 0) ?>"><?= e((string) ($cat['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-sm text-slate-600">Buscar por nombre</span>
                <input type="search"
                       x-model.debounce.200ms="filterSearch"
                       placeholder="Nombre o código…"
                       class="mt-1 block w-56 rounded-lg border border-lo-border px-3 py-2 text-sm text-slate-800 focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer select-none pb-2">
                <input type="checkbox" x-model="filterWithPhoto" class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">
                <span class="text-sm text-slate-700">Solo con foto</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer select-none pb-2">
                <input type="checkbox" x-model="filterWithDescription" class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">
                <span class="text-sm text-slate-700">Solo con descripción</span>
            </label>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            <span x-text="visibleProducts.length"></span> producto(s) visibles ·
            <span class="font-semibold text-lo-blue" x-text="selectedCount"></span> seleccionado(s)
        </p>
    </section>

    <section class="lo-card overflow-hidden">
        <div class="border-b border-lo-border px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                    <input type="checkbox"
                           :checked="allVisibleSelected"
                           @change="toggleSelectAllVisible()"
                           class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">
                    <span>Seleccionar todos los visibles</span>
                </label>
            </div>
            <span class="text-xs text-slate-500" x-show="selectedCount > 0">
                <span x-text="selectedCount"></span> seleccionado(s)
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3 w-10"></th>
                        <th class="px-3 py-3 w-14">Foto</th>
                        <th class="px-3 py-3">Nombre</th>
                        <th class="px-3 py-3">Categoría</th>
                        <th class="px-3 py-3 text-right">Precio ML</th>
                        <th class="px-3 py-3">Descripción</th>
                        <th class="px-3 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-if="visibleProducts.length === 0">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                No hay productos que coincidan con los filtros (o ya están publicados en ML).
                            </td>
                        </tr>
                    </template>
                    <template x-for="p in visibleProducts" :key="p.id">
                        <tr class="hover:bg-slate-50" :class="selectedIds.includes(p.id) ? 'bg-lo-blueSoft/40' : ''">
                            <td class="px-3 py-2.5">
                                <input type="checkbox"
                                       :value="p.id"
                                       :checked="selectedIds.includes(p.id)"
                                       @change="toggleProduct(p.id)"
                                       class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">
                            </td>
                            <td class="px-3 py-2.5">
                                <template x-if="p.thumb_url">
                                    <img :src="p.thumb_url" alt="" class="h-10 w-10 rounded-lg object-cover border border-slate-200 bg-white">
                                </template>
                                <template x-if="!p.thumb_url">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                        <i data-lucide="image-off" class="h-4 w-4"></i>
                                    </span>
                                </template>
                            </td>
                            <td class="px-3 py-2.5">
                                <p class="font-medium text-slate-800" x-text="p.name"></p>
                                <p class="text-xs text-slate-500 font-mono" x-text="p.code"></p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600" x-text="p.category_name"></td>
                            <td class="px-3 py-2.5 text-right font-medium text-slate-800" x-text="p.price_formatted"></td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      :class="p.has_description ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                                    <span x-text="p.has_description ? 'Tiene' : 'No tiene'"></span>
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <a :href="productEditUrl(p.id)"
                                   class="text-xs font-semibold text-lo-blue hover:underline">Editar producto</a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <section x-show="selectedCount > 0" x-cloak class="lo-card p-5 border-l-4 border-l-lo-blue space-y-4">
        <h3 class="text-sm font-semibold text-slate-800">Configuración de publicación</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="text-sm text-slate-600">Markup ML (%)</span>
                <input type="number" step="0.01" min="0" x-model="configMarkup"
                       class="mt-1 block w-full rounded-lg border border-lo-border px-3 py-2 text-sm focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">
            </label>
            <label class="block">
                <span class="text-sm text-slate-600">Cantidad disponible</span>
                <input type="number" min="1" x-model.number="configQuantity"
                       class="mt-1 block w-full rounded-lg border border-lo-border px-3 py-2 text-sm focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">
            </label>
            <label class="block sm:col-span-2">
                <span class="text-sm text-slate-600">Tipo de publicación</span>
                <select x-model="configListingType"
                        class="mt-1 block w-full rounded-lg border border-lo-border px-3 py-2 text-sm focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">
                    <option value="gold_special">Clásica (gold_special)</option>
                    <option value="gold_pro">Premium (gold_pro)</option>
                    <option value="free">Gratuita (free)</option>
                </select>
            </label>
        </div>
        <label class="inline-flex items-start gap-2 cursor-pointer select-none">
            <input type="checkbox" x-model="configGenerateDescription" class="mt-0.5 rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">
            <span class="text-sm text-slate-700">
                Generar descripción con IA si el producto no tiene una
                <span class="block text-xs text-slate-500">Antes de publicar, genera y guarda la descripción en el catálogo.</span>
            </span>
        </label>
        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-lo-border">
            <button type="button"
                    @click="startPublish()"
                    :disabled="running || selectedCount === 0"
                    class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <i data-lucide="upload-cloud" class="h-4 w-4" :class="running ? 'animate-pulse' : ''"></i>
                <span x-text="running ? 'Publicando…' : 'Publicar seleccionados'"></span>
            </button>
            <span class="text-xs text-slate-500" x-show="!running">Se publicará uno por uno en MercadoLibre.</span>
        </div>

        <div x-show="running || log.length > 0" x-cloak class="pt-4 border-t border-lo-border space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-sm font-semibold text-slate-800">Progreso</h4>
                <span class="text-xs text-slate-500" x-show="running" x-text="progressLabel"></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden" x-show="progressTotal > 0">
                <div class="h-full bg-lo-blue transition-all duration-300" :style="'width:' + progressPct + '%'"></div>
            </div>
            <div class="max-h-56 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs font-mono space-y-1">
                <template x-for="(entry, idx) in log" :key="'pub-' + idx">
                    <div class="flex flex-wrap gap-x-2" :class="entry.status === 'ok' ? 'text-green-700' : 'text-red-700'">
                        <span x-text="'#' + entry.product_id"></span>
                        <span x-text="entry.product_name"></span>
                        <span x-text="'→ ' + entry.message"></span>
                    </div>
                </template>
            </div>
            <div x-show="summary" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <p class="font-semibold">Publicación finalizada</p>
                <p class="mt-1" x-text="summaryText"></p>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mlBulkPublish', (initialProducts) => ({
        products: Array.isArray(initialProducts) ? initialProducts : [],
        selectedIds: [],
        filterCategory: '',
        filterSearch: '',
        filterWithPhoto: false,
        filterWithDescription: false,
        configMarkup: <?= json_encode($defaultMarkup) ?>,
        configQuantity: <?= (int) $defaultQuantity ?>,
        configListingType: 'gold_special',
        configGenerateDescription: true,
        csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
        executeUrl: <?= json_encode($executeUrl, JSON_UNESCAPED_UNICODE) ?>,
        running: false,
        progressCurrent: 0,
        progressTotal: 0,
        log: [],
        summary: null,
        get visibleProducts() {
            const q = (this.filterSearch || '').trim().toLowerCase();
            const cat = String(this.filterCategory || '');
            return this.products.filter((p) => {
                if (cat !== '' && String(p.category_id) !== cat) return false;
                if (this.filterWithPhoto && !p.has_photo) return false;
                if (this.filterWithDescription && !p.has_description) return false;
                if (q !== '') {
                    const hay = (p.name || '').toLowerCase() + ' ' + (p.code || '').toLowerCase();
                    if (!hay.includes(q)) return false;
                }
                return true;
            });
        },
        get selectedCount() {
            return this.selectedIds.length;
        },
        get allVisibleSelected() {
            const vis = this.visibleProducts;
            if (vis.length === 0) return false;
            return vis.every((p) => this.selectedIds.includes(p.id));
        },
        get progressPct() {
            if (this.progressTotal <= 0) return 0;
            return Math.round((this.progressCurrent / this.progressTotal) * 100);
        },
        get progressLabel() {
            return this.progressCurrent + ' / ' + this.progressTotal;
        },
        get summaryText() {
            if (!this.summary) return '';
            return this.summary.ok + ' publicado(s), ' + this.summary.errors + ' error(es)';
        },
        productEditUrl(id) {
            return window.appUrl('/productos/' + id + '/editar');
        },
        toggleProduct(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx >= 0) {
                this.selectedIds.splice(idx, 1);
            } else {
                this.selectedIds.push(id);
            }
        },
        toggleSelectAllVisible() {
            const vis = this.visibleProducts;
            if (this.allVisibleSelected) {
                const visIds = new Set(vis.map((p) => p.id));
                this.selectedIds = this.selectedIds.filter((id) => !visIds.has(id));
            } else {
                vis.forEach((p) => {
                    if (!this.selectedIds.includes(p.id)) {
                        this.selectedIds.push(p.id);
                    }
                });
            }
        },
        async startPublish() {
            if (this.running || this.selectedIds.length === 0) return;
            if (!confirm('¿Publicar ' + this.selectedIds.length + ' producto(s) en MercadoLibre?')) return;

            this.running = true;
            this.log = [];
            this.summary = null;
            this.progressCurrent = 0;
            this.progressTotal = this.selectedIds.length;

            const body = new URLSearchParams();
            body.set('_csrf', this.csrf);
            body.set('product_ids', JSON.stringify(this.selectedIds));
            body.set('ml_markup', String(this.configMarkup));
            body.set('available_quantity', String(this.configQuantity));
            body.set('listing_type_id', this.configListingType);
            if (this.configGenerateDescription) {
                body.set('generate_description', '1');
            }

            try {
                const res = await fetch(this.executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/x-ndjson',
                    },
                    body: body.toString(),
                });

                if (!res.ok || !res.body) {
                    throw new Error('Error HTTP ' + res.status);
                }

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';
                    for (const line of lines) {
                        if (!line.trim()) continue;
                        let data;
                        try {
                            data = JSON.parse(line);
                        } catch (e) {
                            continue;
                        }
                        this.handleStreamEvent(data);
                    }
                }
                if (buffer.trim()) {
                    try {
                        this.handleStreamEvent(JSON.parse(buffer));
                    } catch (e) {}
                }
            } catch (e) {
                this.log.push({
                    product_id: 0,
                    product_name: '',
                    status: 'error',
                    message: e.message || 'Error de red',
                });
            } finally {
                this.running = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        handleStreamEvent(data) {
            if (data.type === 'start') {
                this.progressTotal = Number(data.total || this.selectedIds.length);
                return;
            }
            if (data.type === 'progress') {
                this.progressCurrent = Number(data.index || 0);
                this.log.push({
                    product_id: data.product_id,
                    product_name: data.product_name || '',
                    status: data.status,
                    message: data.message || '',
                });
                if (data.status === 'ok') {
                    const pid = Number(data.product_id);
                    this.products = this.products.filter((p) => p.id !== pid);
                    this.selectedIds = this.selectedIds.filter((id) => id !== pid);
                }
                return;
            }
            if (data.type === 'done') {
                this.summary = { ok: data.ok || 0, errors: data.errors || 0 };
                this.progressCurrent = this.progressTotal;
                return;
            }
            if (data.type === 'error') {
                this.log.push({
                    product_id: 0,
                    product_name: '',
                    status: 'error',
                    message: data.error || 'Error',
                });
            }
        },
        init() {
            this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
        },
    }));
});
</script>
