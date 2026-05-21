<?php
$unlinked = is_array($unlinked ?? null) ? $unlinked : [];
$linked = is_array($linked ?? null) ? $linked : [];
$csrf = csrfToken();
$saveUrl = url('/mercadolibre/vincular-existentes/guardar');
$unlinkedForJs = array_values(array_map(static function ($row) {
    return [
        'ml_item_id' => (string) ($row['ml_item_id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'price' => (float) ($row['price'] ?? 0),
        'thumbnail_url' => (string) ($row['thumbnail_url'] ?? ''),
        'ml_status' => (string) ($row['ml_status'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'available_quantity' => (int) ($row['available_quantity'] ?? 0),
        'ml_permalink' => (string) ($row['ml_permalink'] ?? ''),
    ];
}, $unlinked));

function mlLinkStatusLabel(string $status): string
{
    return match ($status) {
        'active' => 'Activo',
        'paused' => 'Pausado',
        'closed' => 'Cerrado',
        default => ucfirst($status),
    };
}

function mlLinkStatusClass(string $status): string
{
    return match ($status) {
        'active' => 'bg-green-100 text-green-800',
        'paused' => 'bg-amber-100 text-amber-800',
        'closed' => 'bg-slate-100 text-slate-700',
        default => 'bg-slate-100 text-slate-700',
    };
}
?>
<script>
const ML_LINK_CONFIG = {
    csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
    saveUrl: <?= json_encode($saveUrl, JSON_UNESCAPED_UNICODE) ?>,
    unlinked: <?= json_encode($unlinkedForJs, JSON_UNESCAPED_UNICODE) ?>,
    linked: <?= json_encode($linked, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<div class="space-y-6" x-data="mlLinkExisting(ML_LINK_CONFIG)">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-xl font-semibold text-slate-900">Vincular publicaciones existentes</h2>
            <p class="mt-1 text-sm text-slate-600">
                Publicaciones activas y pausadas en ML que aún no están en el sistema. Asocialas a un producto del catálogo.
            </p>
        </div>
        <a href="<?= e(url('/mercadolibre')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver al panel
        </a>
    </div>

    <p class="text-sm text-red-700" x-show="globalError" x-text="globalError"></p>
    <p class="text-sm text-green-700" x-show="globalSuccess" x-text="globalSuccess"></p>

    <section class="lo-card overflow-hidden">
        <div class="px-4 py-3 border-b border-lo-border flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Sin vincular</h3>
                <p class="text-xs text-slate-500 mt-0.5" x-text="rows.length + ' publicación(es) pendiente(s)'"></p>
            </div>
            <button type="button" @click="linkSelected()" :disabled="bulkLoading || selectedCount === 0"
                    class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                <svg x-show="bulkLoading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="bulkLoading ? 'Vinculando…' : 'Vincular seleccionados (' + selectedCount + ')'"></span>
            </button>
        </div>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="px-3 py-3 w-10">
                            <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="allSelected" class="rounded border-gray-300">
                        </th>
                        <th class="text-left px-3 py-3 w-16">Foto</th>
                        <th class="text-left px-4 py-3">Título</th>
                        <th class="text-right px-4 py-3">Precio</th>
                        <th class="text-left px-4 py-3">Estado ML</th>
                        <th class="text-left px-4 py-3 min-w-[220px]">Producto vinculado</th>
                        <th class="text-right px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="row in rows" :key="row.ml_item_id">
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-3 py-3">
                                <input type="checkbox" x-model="row.selected" class="rounded border-gray-300">
                            </td>
                            <td class="px-3 py-3">
                                <template x-if="row.thumbnail_url">
                                    <img :src="row.thumbnail_url" alt="" class="h-12 w-12 rounded-lg border border-gray-200 object-cover bg-white">
                                </template>
                                <template x-if="!row.thumbnail_url">
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-slate-400 text-xs">—</span>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800" x-text="row.title"></p>
                                <p class="text-xs text-slate-500 font-mono mt-0.5" x-text="row.ml_item_id"></p>
                                <a x-show="row.ml_permalink" :href="row.ml_permalink" target="_blank" rel="noopener" class="text-xs text-lo-blue hover:underline">Ver en ML</a>
                            </td>
                            <td class="px-4 py-3 text-right font-mono whitespace-nowrap" x-text="fmtPrice(row.price)"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="statusClass(row.ml_status)" x-text="statusLabel(row.ml_status)"></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="relative">
                                    <input type="text" x-model="row.productQuery" @input.debounce.300ms="searchProduct(row)"
                                           placeholder="Buscar producto…"
                                           class="w-full rounded-lg border border-lo-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-lo-blue/30">
                                    <div x-show="row.productResults.length > 0" x-cloak @click.outside="row.productResults = []"
                                         class="absolute z-20 left-0 right-0 mt-1 rounded-xl border border-lo-border bg-white shadow-lg max-h-48 overflow-y-auto">
                                        <template x-for="item in row.productResults" :key="item.id">
                                            <button type="button" @click="pickProduct(row, item)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 border-b border-slate-50 last:border-0">
                                                <span class="font-medium text-slate-800" x-text="item.code"></span>
                                                <span class="text-slate-500"> — </span>
                                                <span x-text="item.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500" x-show="row.productId > 0">
                                    <span class="font-medium" x-text="row.productLabel"></span>
                                </p>
                                <p class="mt-1 text-xs text-red-600" x-show="row.error" x-text="row.error"></p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="linkOne(row)" :disabled="row.loading || row.productId <= 0"
                                        class="inline-flex items-center gap-1 rounded-lg border border-lo-border bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                    <svg x-show="row.loading" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    Vincular
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                            No hay publicaciones pendientes de vincular. Todas las activas/pausadas en ML ya están en el sistema.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="lo-card overflow-hidden">
        <div class="px-4 py-3 border-b border-lo-border">
            <h3 class="text-sm font-semibold text-slate-800">Ya vinculados</h3>
            <p class="text-xs text-slate-500 mt-0.5"><?= count($linked) ?> publicación(es) en ML ya registradas en ml_listings</p>
        </div>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="text-left px-3 py-3 w-16">Foto</th>
                        <th class="text-left px-4 py-3">Título ML</th>
                        <th class="text-right px-4 py-3">Precio</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Producto en sistema</th>
                        <th class="text-right px-4 py-3">Listing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($linked as $row): ?>
                        <?php
                        $thumb = trim((string) ($row['thumbnail_url'] ?? ''));
                        $mlStatus = (string) ($row['ml_status'] ?? '');
                        $listingId = (int) ($row['listing_id'] ?? 0);
                        $productCode = trim((string) ($row['product_code'] ?? ''));
                        $productName = trim((string) ($row['product_name'] ?? ''));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-3">
                                <?php if ($thumb !== ''): ?>
                                    <img src="<?= e($thumb) ?>" alt="" class="h-12 w-12 rounded-lg border border-gray-200 object-cover bg-white">
                                <?php else: ?>
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-slate-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800"><?= e((string) ($row['title'] ?? '')) ?></p>
                                <p class="text-xs text-slate-500 font-mono"><?= e((string) ($row['ml_item_id'] ?? '')) ?></p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono"><?= formatPrice((float) ($row['price'] ?? 0)) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?= e(mlLinkStatusClass($mlStatus)) ?>">
                                    <?= e(mlLinkStatusLabel($mlStatus)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?php if ($productName !== ''): ?>
                                    <span class="font-medium"><?= e($productCode) ?></span> — <?= e($productName) ?>
                                <?php else: ?>
                                    <span class="text-slate-400">Sin producto</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <?php if ($listingId > 0): ?>
                                    <a href="<?= e(url('/mercadolibre/listings/' . $listingId . '/editar')) ?>" class="text-lo-blue hover:underline text-sm font-medium">Editar</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($linked === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Ninguna publicación de ML coincide con listings ya vinculados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
function mlLinkExisting(cfg) {
    return {
        csrf: cfg.csrf || '',
        saveUrl: cfg.saveUrl || '',
        rows: (cfg.unlinked || []).map((row) => ({
            ...row,
            selected: false,
            productId: 0,
            productLabel: '',
            productQuery: '',
            productResults: [],
            loading: false,
            error: '',
        })),
        bulkLoading: false,
        globalError: '',
        globalSuccess: '',
        get selectedCount() {
            return this.rows.filter((r) => r.selected && r.productId > 0).length;
        },
        get allSelected() {
            return this.rows.length > 0 && this.rows.every((r) => r.selected);
        },
        fmtPrice(n) {
            const v = Number(n) || 0;
            return '$' + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        statusLabel(s) {
            const map = { active: 'Activo', paused: 'Pausado', closed: 'Cerrado' };
            return map[s] || s;
        },
        statusClass(s) {
            const map = {
                active: 'bg-green-100 text-green-800',
                paused: 'bg-amber-100 text-amber-800',
                closed: 'bg-slate-100 text-slate-700',
            };
            return map[s] || 'bg-slate-100 text-slate-700';
        },
        toggleAll(checked) {
            this.rows.forEach((r) => { r.selected = checked; });
        },
        async searchProduct(row) {
            const q = (row.productQuery || '').trim();
            if (row.productId > 0 && q !== row.productLabel) {
                row.productId = 0;
                row.productLabel = '';
            }
            if (q.length < 2) {
                row.productResults = [];
                return;
            }
            try {
                const res = await fetch(window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q)));
                const data = await res.json();
                row.productResults = data.results || [];
            } catch (e) {
                row.productResults = [];
            }
        },
        pickProduct(row, item) {
            row.productId = Number(item.id || 0);
            row.productLabel = (item.code || '') + ' — ' + (item.name || '');
            row.productQuery = row.productLabel;
            row.productResults = [];
            row.error = '';
        },
        async linkOne(row) {
            if (row.productId <= 0) {
                row.error = 'Seleccioná un producto.';
                return;
            }
            row.loading = true;
            row.error = '';
            this.globalError = '';
            try {
                const body = new URLSearchParams({
                    _csrf: this.csrf,
                    ml_item_id: row.ml_item_id,
                    product_id: String(row.productId),
                });
                const res = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: body.toString(),
                });
                const data = await res.json();
                if (!data.success) {
                    row.error = data.error || 'No se pudo vincular.';
                    return;
                }
                this.rows = this.rows.filter((r) => r.ml_item_id !== row.ml_item_id);
                this.globalSuccess = 'Vinculado: ' + row.title;
                setTimeout(() => { this.globalSuccess = ''; }, 4000);
            } catch (e) {
                row.error = 'Error de red.';
            } finally {
                row.loading = false;
            }
        },
        async linkSelected() {
            const selected = this.rows.filter((r) => r.selected && r.productId > 0);
            if (selected.length === 0) {
                this.globalError = 'Marcá filas y seleccioná un producto en cada una.';
                return;
            }
            this.bulkLoading = true;
            this.globalError = '';
            this.globalSuccess = '';
            try {
                const items = selected.map((r) => ({
                    ml_item_id: r.ml_item_id,
                    product_id: r.productId,
                }));
                const body = new URLSearchParams({
                    _csrf: this.csrf,
                    batch: '1',
                    items: JSON.stringify(items),
                });
                const res = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: body.toString(),
                });
                const data = await res.json();
                const linkedIds = new Set((data.linked || []).map((x) => x.ml_item_id));
                if (linkedIds.size > 0) {
                    this.rows = this.rows.filter((r) => !linkedIds.has(r.ml_item_id));
                }
                if (data.errors && data.errors.length) {
                    data.errors.forEach((err) => {
                        const row = this.rows.find((r) => r.ml_item_id === err.ml_item_id);
                        if (row) {
                            row.error = err.error || 'Error';
                        }
                    });
                    this.globalError = 'Algunas filas no se vincularon. Revisá los mensajes en rojo.';
                }
                if (linkedIds.size > 0) {
                    this.globalSuccess = linkedIds.size + ' publicación(es) vinculada(s).';
                }
            } catch (e) {
                this.globalError = 'Error de red al vincular en lote.';
            } finally {
                this.bulkLoading = false;
            }
        },
    };
}
</script>
