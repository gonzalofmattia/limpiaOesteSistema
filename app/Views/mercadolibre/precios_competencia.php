<?php
$rows = is_array($rows ?? null) ? $rows : [];
$csrf = csrfToken();
$analyzeUrl = url('/mercadolibre/precios-competencia/analizar-todos');
$applyAllUrl = url('/mercadolibre/precios-competencia/aplicar-todos');
$applyBaseUrl = url('/mercadolibre/precios-competencia');
$mlUserId = trim((string) ($ml_user_id ?? ''));
$rowsJson = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<div class="space-y-6" x-data="mlPriceIntel(<?= e($rowsJson ?: '[]') ?>, '<?= e($csrf) ?>', '<?= e($analyzeUrl) ?>', '<?= e($applyAllUrl) ?>', '<?= e($applyBaseUrl) ?>', '<?= e($mlUserId) ?>')">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-xl font-semibold text-slate-900">Análisis de precios competitivos</h2>
            <p class="mt-1 text-sm text-slate-600">
                La búsqueda en ML se ejecuta desde tu navegador (el servidor de Ferozo no puede consultar la API de búsqueda).
                Los resultados se guardan en caché 24 horas.
            </p>
        </div>
        <a href="<?= e(url('/mercadolibre')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver al panel
        </a>
    </div>

    <section class="lo-card p-4">
        <div class="flex flex-wrap items-center gap-3">
            <button type="button"
                    @click="analyzeAll()"
                    :disabled="analyzing"
                    class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                <i data-lucide="search" class="h-4 w-4" :class="analyzing ? 'animate-spin' : ''"></i>
                <span x-text="analyzing ? 'Analizando…' : 'Analizar todos'"></span>
            </button>
            <button type="button"
                    @click="applyAll()"
                    :disabled="applyingAll || analyzing"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-60">
                <i data-lucide="check-circle" class="h-4 w-4"></i>Aplicar todos los sugeridos
            </button>
            <template x-if="analyzing">
                <span class="text-sm text-slate-600">
                    Progreso: <span class="font-semibold" x-text="progressDone"></span> / <span x-text="progressTotal"></span>
                </span>
            </template>
        </div>
        <p x-show="message" x-text="message" class="mt-3 text-sm" :class="messageError ? 'text-red-600' : 'text-green-700'"></p>
    </section>

    <div class="lo-table-wrap">
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                <tr>
                    <th class="w-14 px-3 py-3"></th>
                    <th class="text-left px-4 py-3">Producto</th>
                    <th class="text-right px-4 py-3">Mi precio</th>
                    <th class="text-right px-4 py-3">Precio mínimo</th>
                    <th class="text-right px-4 py-3">Promedio competencia</th>
                    <th class="text-right px-4 py-3">Precio sugerido</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-center px-4 py-3">Competidores</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="items.length === 0">
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                            No hay listings activos para analizar.
                        </td>
                    </tr>
                </template>
                <template x-for="item in items" :key="item.listing.id">
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2.5">
                            <img :src="thumbUrl(item)" :alt="item.listing.product_name" class="h-10 w-10 rounded object-cover bg-slate-100" loading="lazy">
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-slate-900" x-text="item.listing.product_name || '—'"></p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1" x-text="item.listing.title || ''"></p>
                            <p x-show="item.search_query" class="text-xs text-slate-400 mt-0.5">
                                Búsqueda: <span x-text="item.search_query"></span>
                            </p>
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium" x-text="fmt(item.analysis?.current_price ?? item.listing.price)"></td>
                        <td class="px-4 py-2.5 text-right text-slate-600" x-text="fmt(item.analysis?.min_acceptable_price)"></td>
                        <td class="px-4 py-2.5 text-right text-slate-600" x-text="fmt(item.analysis?.avg_competitor_price)"></td>
                        <td class="px-4 py-2.5 text-right font-semibold text-lo-blue" x-text="fmt(item.analysis?.suggested_price)"></td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                  :class="statusBadge(item.analysis?.status)"
                                  x-text="item.analysis?.status || 'Sin analizar'"></span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-slate-600" x-text="item.analysis ? (item.analysis.competitors_count ?? 0) : '—'"></td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <button type="button"
                                        @click="searchCompetitors(item)"
                                        class="inline-flex items-center gap-1 rounded border border-lo-blue bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-lo-blue hover:bg-blue-100 disabled:opacity-50"
                                        :disabled="searchingId === item.listing.id || analyzing">
                                    <i data-lucide="search" class="h-3.5 w-3.5" :class="searchingId === item.listing.id ? 'animate-spin' : ''"></i>
                                    <span x-text="searchingId === item.listing.id ? 'Buscando…' : 'Buscar competidores'"></span>
                                </button>
                                <button type="button"
                                        @click="toggleExpand(item.listing.id)"
                                        class="inline-flex items-center gap-1 rounded border border-lo-border bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        :disabled="!item.analysis || !(item.analysis.competitors || []).length">
                                    <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>Competidores
                                </button>
                                <button type="button"
                                        @click="applyOne(item.listing.id)"
                                        class="inline-flex items-center gap-1 rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                        :disabled="!item.analysis?.suggested_price || applyingId === item.listing.id">
                                    Aplicar sugerido
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="expanded[item.listing.id]" x-cloak class="bg-slate-50/80">
                        <td colspan="9" class="px-4 py-3">
                            <div class="rounded-lg border border-lo-border bg-white overflow-hidden">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-50 text-slate-500 uppercase tracking-wide">
                                        <tr>
                                            <th class="text-left px-3 py-2">Título</th>
                                            <th class="text-right px-3 py-2 w-24">Precio</th>
                                            <th class="text-right px-3 py-2 w-20">Ventas</th>
                                            <th class="text-left px-3 py-2 w-40">Reputación</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(c, ci) in (item.analysis?.competitors || [])" :key="ci">
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <a :href="c.permalink || '#'" target="_blank" rel="noopener" class="text-lo-blue hover:underline line-clamp-2" x-text="c.title"></a>
                                                </td>
                                                <td class="px-3 py-2 text-right font-medium" x-text="fmt(c.price)"></td>
                                                <td class="px-3 py-2 text-right" x-text="c.sold_quantity ?? 0"></td>
                                                <td class="px-3 py-2 text-slate-600" x-text="c.seller_reputation || '—'"></td>
                                            </tr>
                                        </template>
                                        <tr x-show="!(item.analysis?.competitors || []).length">
                                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">Sin competidores encontrados.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p x-show="item.analysis?.analyzed_at" class="mt-2 text-xs text-slate-400">
                                Analizado: <span x-text="item.analysis?.analyzed_at || item.analysis?.cached_at || ''"></span>
                            </p>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
function mlPriceIntel(initialRows, csrf, analyzeUrl, applyAllUrl, applyBaseUrl, mlUserId) {
    const ML_SEARCH_BASE = 'https://api.mercadolibre.com/sites/MLA/search';

    return {
        items: initialRows.map(r => ({
            listing: r.listing,
            analysis: r.analysis || null,
            search_query: r.search_query || '',
        })),
        mlUserId: mlUserId || '',
        expanded: {},
        analyzing: false,
        searchingId: null,
        applyingAll: false,
        applyingId: null,
        progressDone: 0,
        progressTotal: 0,
        message: '',
        messageError: false,

        fmt(val) {
            const n = parseFloat(val);
            if (!n || isNaN(n) || n <= 0) return '—';
            return '$' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        statusBadge(status) {
            if (status === 'Podés subir') return 'bg-emerald-100 text-emerald-800';
            if (status === 'Estás caro') return 'bg-red-100 text-red-800';
            if (status === 'Precio OK') return 'bg-blue-100 text-blue-800';
            return 'bg-slate-100 text-slate-600';
        },

        thumbUrl(item) {
            const ml = (item.listing.ml_thumbnail || '').trim();
            if (ml) return ml;
            const pid = item.listing.product_id;
            const fn = (item.listing.cover_filename || '').trim();
            if (pid && fn) return '<?= e(url('/producto-imagen')) ?>/' + pid + '/' + encodeURIComponent(fn);
            return 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect fill="#e2e8f0" width="40" height="40"/></svg>');
        },

        toggleExpand(id) {
            this.expanded[id] = !this.expanded[id];
        },

        findItem(listingId) {
            return this.items.find(i => i.listing.id === listingId);
        },

        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        filterCompetitors(results, mlItemId) {
            const ownId = String(this.mlUserId || '');
            const excludeItem = String(mlItemId || '');

            return (results || [])
                .filter(item => {
                    const sellerId = String(item.seller?.id ?? '');
                    if (ownId !== '' && sellerId === ownId) return false;
                    if (excludeItem !== '' && String(item.id ?? '') === excludeItem) return false;
                    return item.buying_mode === 'buy_it_now';
                })
                .slice(0, 5);
        },

        async fetchMlSearch(query) {
            const url = ML_SEARCH_BASE + '?q=' + encodeURIComponent(query) + '&limit=10';
            const res = await fetch(url);
            if (!res.ok) {
                throw new Error('MercadoLibre respondió HTTP ' + res.status);
            }
            return res.json();
        },

        async saveCompetitors(listingId, competidores, searchQuery) {
            const res = await fetch(applyBaseUrl + '/' + listingId + '/guardar-competidores', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf,
                },
                body: JSON.stringify({
                    listing_id: listingId,
                    competidores,
                    search_query: searchQuery,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudieron guardar los competidores.');
            }
            return data;
        },

        async runSearchForItem(item) {
            const listingId = item.listing.id;
            const query = (item.search_query || '').trim();
            if (!query) {
                throw new Error('Sin término de búsqueda para ' + (item.listing.product_name || listingId));
            }

            const data = await this.fetchMlSearch(query);
            const competidores = this.filterCompetitors(data.results, item.listing.ml_item_id);
            const saved = await this.saveCompetitors(listingId, competidores, query);
            if (saved.analysis) {
                item.analysis = saved.analysis;
            }

            return { competidores, analysis: saved.analysis };
        },

        async searchCompetitors(item) {
            if (this.searchingId || this.analyzing) return;

            const listingId = item.listing.id;
            this.searchingId = listingId;
            this.message = '';
            this.messageError = false;

            try {
                const result = await this.runSearchForItem(item);
                this.message = 'Competidores encontrados: ' + result.competidores.length + '.';
            } catch (e) {
                this.message = e.message || 'Error al buscar competidores.';
                this.messageError = true;
            } finally {
                this.searchingId = null;
                if (window.lucide) lucide.createIcons();
            }
        },

        async analyzeAll() {
            if (this.analyzing) return;
            this.analyzing = true;
            this.message = '';
            this.messageError = false;
            this.progressDone = 0;
            this.progressTotal = this.items.length;

            let errors = 0;

            try {
                const clearRes = await fetch(analyzeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_csrf=' + encodeURIComponent(csrf),
                });
                const clearData = await clearRes.json();
                if (!clearRes.ok || !clearData.success) {
                    throw new Error(clearData.error || 'No se pudo limpiar la caché.');
                }

                for (let i = 0; i < this.items.length; i++) {
                    const item = this.items[i];
                    this.searchingId = item.listing.id;
                    try {
                        await this.runSearchForItem(item);
                    } catch (e) {
                        errors++;
                        console.warn('Listing ' + item.listing.id + ':', e);
                    }
                    this.searchingId = null;
                    this.progressDone = i + 1;
                    if (i < this.items.length - 1) {
                        await this.sleep(1000);
                    }
                }

                this.message = 'Análisis completado para ' + this.items.length + ' listings.'
                    + (errors > 0 ? ' (' + errors + ' con error).' : '');
                this.messageError = errors > 0;
            } catch (e) {
                this.message = e.message || 'Error al analizar.';
                this.messageError = true;
            } finally {
                this.analyzing = false;
                this.searchingId = null;
                if (window.lucide) lucide.createIcons();
            }
        },

        async applyOne(listingId) {
            if (this.applyingId) return;
            this.applyingId = listingId;
            this.message = '';
            this.messageError = false;

            try {
                const res = await fetch(applyBaseUrl + '/' + listingId + '/aplicar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_csrf=' + encodeURIComponent(csrf),
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.error || 'No se pudo aplicar el precio.');
                }
                const row = this.findItem(listingId);
                if (row) {
                    if (!row.analysis) row.analysis = {};
                    row.analysis.current_price = data.new_price;
                    row.listing.price = data.new_price;
                    row.analysis.status = 'Precio OK';
                }
                this.message = 'Precio actualizado correctamente.';
            } catch (e) {
                this.message = e.message || 'Error al aplicar precio.';
                this.messageError = true;
            } finally {
                this.applyingId = null;
            }
        },

        async applyAll() {
            if (this.applyingAll) return;
            if (!confirm('¿Aplicar precio sugerido a todos los listings con estado "Podés subir"?')) return;

            this.applyingAll = true;
            this.message = '';
            this.messageError = false;

            try {
                const res = await fetch(applyAllUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_csrf=' + encodeURIComponent(csrf),
                });
                const data = await res.json();
                if (!data.success && (data.failed || 0) > 0) {
                    throw new Error((data.errors || []).join('; ') || data.error || 'Algunos precios fallaron.');
                }
                this.items.forEach(item => {
                    if (item.analysis?.status === 'Podés subir' && item.analysis?.suggested_price) {
                        item.analysis.current_price = item.analysis.suggested_price;
                        item.listing.price = item.analysis.suggested_price;
                        item.analysis.status = 'Precio OK';
                    }
                });
                this.message = 'Aplicados: ' + (data.applied || 0) + ', omitidos: ' + (data.skipped || 0) + '.';
                if ((data.failed || 0) > 0) {
                    this.messageError = true;
                    this.message += ' Fallidos: ' + data.failed + '.';
                }
            } catch (e) {
                this.message = e.message || 'Error en aplicación masiva.';
                this.messageError = true;
            } finally {
                this.applyingAll = false;
            }
        },
    };
}
</script>
