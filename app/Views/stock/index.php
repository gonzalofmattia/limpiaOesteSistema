<?php
$skuCount = (int) ($total ?? count($products));
$stockValue = 0.0;
$sinStock = 0;
$reponer = 0;
$enCaminoTotal = 0;
$bajoMinimo = 0;
foreach (($products ?? []) as $p) {
    $stock = (int) ($p['stock_units'] ?? 0);
    $committed = (int) ($p['stock_committed_units'] ?? 0);
    $disponible = $stock - $committed;
    $inTransit = (int) ($p['in_transit_units'] ?? 0);
    $min = (int) ($p['units_per_box'] ?? 0);
    $stockValue += max(0, $stock) * (float) ($p['cost'] ?? 0);
    $enCaminoTotal += max(0, $inTransit);
    if ($stock <= 0) { $sinStock++; }
    if ($stock > 0 && $min > 0 && $stock < $min) { $reponer++; }
    $stockEfectivo = $disponible + $inTransit;
    if (($p['stock_minimum'] ?? null) !== null && $stockEfectivo < (int) $p['stock_minimum']) { $bajoMinimo++; }
}
$lowStockCountGlobal = (int) ($lowStockCount ?? $bajoMinimo);
$currentFilter = $stockFilter ?? '';
$loStockListPath = parse_url(url('/stock-actual'), PHP_URL_PATH) ?: '/stock-actual';
$loFilterStockActive = trim((string) ($q ?? '')) !== '' || $currentFilter === 'bajo' || (int) ($per_page ?? 50) !== 50;
$reorderSuggestionCfg = [
    'fetchUrl' => url('/stock/reorder-suggestion'),
    'createUrl' => url('/stock/create-reorder'),
    'csrf' => csrfToken(),
];
?>
<script>
window.__reorderSuggestionCfg = <?= json_encode($reorderSuggestionCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener('alpine:init', () => {
    Alpine.data('reorderSuggestionModal', () => ({
        isOpen: false,
        loading: false,
        creating: false,
        error: '',
        suggestions: [],
        selected: {},
        boxes: {},
        fetchUrl: '',
        createUrl: '',
        csrf: '',
        init() {
            const cfg = window.__reorderSuggestionCfg || {};
            this.fetchUrl = cfg.fetchUrl || '';
            this.createUrl = cfg.createUrl || '';
            this.csrf = cfg.csrf || '';
            if (new URLSearchParams(window.location.search).get('sugerencia') === '1') {
                this.showModal();
            }
        },
        get allSelected() {
            if (this.suggestions.length === 0) return false;
            return this.suggestions.every((r) => !!this.selected[r.product_id]);
        },
        get canCreate() {
            return Object.keys(this.selected).some((id) => this.selected[id]);
        },
        async showModal() {
            this.isOpen = true;
            this.error = '';
            this.loading = true;
            this.suggestions = [];
            this.selected = {};
            this.boxes = {};
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            try {
                const res = await fetch(this.fetchUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error al cargar sugerencias');
                this.suggestions = data.suggestions || [];
                this.suggestions.forEach((r) => {
                    this.selected[r.product_id] = true;
                    this.boxes[r.product_id] = r.cajas_sugeridas;
                });
            } catch (e) {
                this.error = e.message || 'No se pudieron cargar las sugerencias.';
            } finally {
                this.loading = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        closeModal() {
            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');
        },
        toggleRow(productId, checked) { this.selected[productId] = checked; },
        toggleSelectAll(checked) {
            this.suggestions.forEach((r) => { this.selected[r.product_id] = checked; });
        },
        selectAll() { this.suggestions.forEach((r) => { this.selected[r.product_id] = true; }); },
        deselectAll() { this.suggestions.forEach((r) => { this.selected[r.product_id] = false; }); },
        setBoxes(productId, value) { this.boxes[productId] = Math.max(1, parseInt(value, 10) || 1); },
        boxesForRow(row) {
            const id = row.product_id;
            return this.boxes[id] != null ? this.boxes[id] : row.cajas_sugeridas;
        },
        async createOrder() {
            if (!this.canCreate || this.creating) return;
            const items = [];
            this.suggestions.forEach((r) => {
                if (!this.selected[r.product_id]) return;
                items.push({
                    product_id: r.product_id,
                    boxes: Math.max(1, parseInt(this.boxesForRow(r), 10) || r.cajas_sugeridas || 1),
                });
            });
            if (items.length === 0) return;
            this.creating = true;
            this.error = '';
            try {
                const res = await fetch(this.createUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': this.csrf },
                    body: JSON.stringify({ items }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo crear el pedido');
                window.location.href = data.redirect;
            } catch (e) {
                this.error = e.message || 'Error al crear el pedido.';
                this.creating = false;
            }
        },
    }));

    Alpine.data('inlineStockRow', (cfg) => ({
        productId: cfg.productId,
        stockUnits: cfg.stockUnits,
        stockCommitted: cfg.stockCommitted,
        inTransit: cfg.inTransit,
        minimo: cfg.minimo,
        canEdit: !!cfg.canEdit,
        inlineUrl: cfg.inlineUrl,
        csrf: cfg.csrf,
        editing: false,
        draft: '',
        saving: false,
        cellFeedback: null,
        feedbackTimer: null,
        bajoMinimo() {
            return this.minimo !== null && this.disponibleMasCamino() < this.minimo;
        },
        disponible() { return this.stockUnits - this.stockCommitted; },
        disponibleMasCamino() { return this.disponible() + this.inTransit; },
        disponibleClass() { return this.disponible() > 0 ? 'text-green-700' : 'text-red-700'; },
        dispCaminoClass() { return this.disponibleMasCamino() > 0 ? 'text-emerald-700' : 'text-red-700'; },
        clearFeedbackTimer() {
            if (this.feedbackTimer) { clearTimeout(this.feedbackTimer); this.feedbackTimer = null; }
        },
        setFeedback(kind) {
            this.clearFeedbackTimer();
            this.cellFeedback = kind;
            if (kind) {
                this.feedbackTimer = setTimeout(() => { this.cellFeedback = null; this.feedbackTimer = null; }, 1600);
            }
        },
        startEdit() {
            if (!this.canEdit || this.saving) return;
            this.clearFeedbackTimer();
            this.editing = true;
            this.draft = String(this.stockUnits);
            this.cellFeedback = null;
            this.$nextTick(() => {
                const el = this.$refs.stockInput;
                if (el) { el.focus(); el.select(); }
                if (window.rebuildLucideIcons) window.rebuildLucideIcons();
            });
        },
        cancelEdit() {
            this.clearFeedbackTimer();
            this.editing = false;
            this.draft = String(this.stockUnits);
            this.cellFeedback = null;
        },
        async commit() {
            if (!this.editing || this.saving) return false;
            const v = parseInt(String(this.draft).trim(), 10);
            if (Number.isNaN(v) || v < 0) { this.draft = String(this.stockUnits); this.setFeedback('err'); return false; }
            if (v === this.stockUnits) { this.editing = false; return true; }
            this.saving = true;
            try {
                const res = await fetch(this.inlineUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': this.csrf },
                    body: JSON.stringify({ product_id: this.productId, new_stock: v, notes: 'Ajuste manual inline' }),
                });
                let data = {};
                try { data = await res.json(); } catch (e) { data = {}; }
                if (!res.ok || !data.success) { this.draft = String(this.stockUnits); this.setFeedback('err'); return false; }
                this.stockUnits = data.stock_units;
                this.stockCommitted = data.stock_committed_units;
                this.editing = false;
                this.setFeedback('ok');
                this.$nextTick(() => { if (window.rebuildLucideIcons) window.rebuildLucideIcons(); });
                return true;
            } catch (e) {
                this.draft = String(this.stockUnits);
                this.setFeedback('err');
                return false;
            } finally {
                this.saving = false;
            }
        },
        async onTabNext(e) {
            e.preventDefault();
            const ok = await this.commit();
            if (!ok && this.editing) return;
            const tr = this.$el.closest('tr');
            const tbody = tr && tr.closest('tbody');
            if (!tbody || !tr) return;
            const rows = [...tbody.querySelectorAll('tr[data-inline-stock-row]')];
            const i = rows.indexOf(tr);
            if (i < 0 || i >= rows.length - 1) return;
            const next = rows[i + 1];
            const d = window.Alpine && Alpine.$data(next);
            if (d && typeof d.startEdit === 'function') d.startEdit();
        },
        onBlurCommit() {
            this.$nextTick(() => { if (this.editing && !this.saving) this.commit(); });
        },
    }));
});
</script>
<div class="space-y-5"
     data-lo-filter-persist
     data-lo-filter-page="stock-actual"
     data-lo-filter-keys="search,stock_filter,per_page"
     data-lo-filter-list-path="<?= e($loStockListPath) ?>"
     data-lo-filter-clear-url="<?= e(url('/stock-actual')) ?>">
<div class="flex items-center justify-end">
    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <button type="button" class="inline-flex w-full sm:w-auto min-h-11 items-center justify-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">Salida</button>
        <button type="button" class="inline-flex w-full sm:w-auto min-h-11 items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Ingreso</button>
    </div>
</div>
<div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
    <div class="lo-card p-4"><p class="text-xs text-slate-500">SKUs</p><p class="text-2xl font-semibold"><?= $skuCount ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Valorizado al costo</p><p class="text-xl font-semibold"><?= formatPrice($stockValue) ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Sin stock</p><p class="text-2xl font-semibold text-red-600"><?= $sinStock ?></p></div>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">Para reponer</p><p class="text-2xl font-semibold text-amber-600"><?= $reponer ?></p></div>
    <a href="<?= e(url('/stock-actual?stock_filter=bajo')) ?>" class="lo-card p-4 <?= $lowStockCountGlobal > 0 ? 'border-red-300 bg-red-50' : '' ?>">
        <p class="text-xs text-slate-500">Bajo mínimo</p>
        <p class="text-2xl font-semibold <?= $lowStockCountGlobal > 0 ? 'text-red-700' : 'text-slate-400' ?>"><?= $lowStockCountGlobal ?></p>
    </a>
    <div class="lo-card p-4"><p class="text-xs text-slate-500">En camino (un.)</p><p class="text-2xl font-semibold text-blue-700"><?= $enCaminoTotal ?></p></div>
</div>
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
<form method="get" class="flex items-center gap-2 flex-1 min-w-0">
        <input type="hidden" name="per_page" value="<?= (int) ($per_page ?? 50) ?>">
        <?php if ($currentFilter === 'bajo'): ?>
            <input type="hidden" name="stock_filter" value="bajo">
        <?php endif; ?>
    <div class="flex-1 min-h-11 rounded-xl border border-lo-border bg-white px-3 flex items-center gap-2"><i data-lucide="search" class="h-4 w-4 text-slate-400 shrink-0"></i><input type="text" name="search" value="<?= e((string) ($q ?? '')) ?>" placeholder="Buscar producto..." class="w-full min-h-11 bg-transparent outline-none text-base md:text-sm"></div>
    <?php require APP_PATH . '/Views/layout/partials/ui-btn-filter.php'; ?>
</form>
<?php if ($loFilterStockActive): ?>
    <button type="button" data-lo-filter-clear class="shrink-0 inline-flex min-h-11 items-center justify-center px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar filtros</button>
<?php endif; ?>
</div>
<div class="flex gap-2 overflow-x-auto pb-1">
    <a href="<?= e(url('/stock-actual' . ($q !== '' ? '?search=' . urlencode($q) : ''))) ?>"
       class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $currentFilter === '' ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Todos <span class="ml-1 text-[10px]"><?= $skuCount ?></span>
    </a>
    <a href="<?= e(url('/stock-actual?stock_filter=bajo' . ($q !== '' ? '&search=' . urlencode($q) : ''))) ?>"
       class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $currentFilter === 'bajo' ? 'bg-red-700 text-white' : 'border border-red-200 text-red-700 hover:bg-red-50' ?>">
        Stock bajo <span class="ml-1 text-[10px]"><?= $lowStockCountGlobal ?></span>
    </a>
    <button type="button"
            @click="$dispatch('open-reorder-suggestion')"
            class="px-3 h-8 rounded-full border border-emerald-200 text-emerald-700 hover:bg-emerald-50 inline-flex items-center text-xs font-semibold">
        📊 Sugerencia de reposición
    </button>
    <a href="<?= e(url('/stock/proyeccion')) ?>"
       class="px-3 h-8 rounded-full border border-sky-200 text-sky-800 hover:bg-sky-50 inline-flex items-center text-xs font-semibold">
        Proyección compra
    </a>
</div>

<div class="lo-card p-6">
    <h3 class="text-sm font-semibold text-gray-800 mb-3">Ajuste manual de stock</h3>
    <?php if (empty($hasAdjustmentsTable)): ?>
        <p class="text-sm text-red-700">
            Falta la tabla de historial de ajustes. Ejecutá la migración <code>database/migrations/2026_04_28_stock_adjustments.sql</code>.
        </p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/stock-actual/ajustar')) ?>" class="grid md:grid-cols-4 gap-3 items-end">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Código de producto</label>
                <input type="text" name="product_code" required placeholder="Ej: ECOLH05"
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nuevo stock (un.)</label>
                <input type="number" min="0" name="new_stock" required
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Motivo (opcional)</label>
                <input type="text" name="notes" placeholder="Conteo físico"
                       class="w-full border border-gray-300 rounded-lg text-base md:text-sm px-3 py-2.5 min-h-11">
            </div>
            <button type="submit" class="min-h-11 px-4 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-base md:text-sm font-medium w-full md:w-auto">Guardar ajuste</button>
        </form>
    <?php endif; ?>
</div>

<p class="text-sm text-gray-500 mb-2"><?= (int) ($total ?? count($products)) ?> productos con stock o en camino</p>

<div
    x-data="reorderSuggestionModal()"
    @open-reorder-suggestion.window="showModal()"
    @keydown.escape.window="isOpen && closeModal()"
>
    <div x-show="isOpen" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div x-show="isOpen" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="closeModal()"></div>
        <div x-show="isOpen" x-transition @click.stop
            class="relative z-10 w-full sm:max-w-6xl max-h-[92vh] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-xl border border-slate-200">
            <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-4 border-b border-slate-200 shrink-0">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Sugerencia de reposición</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Stock efectivo = disponible + en camino.</p>
                </div>
                <button type="button" @click="closeModal()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="px-4 sm:px-6 py-3 border-b border-slate-100 shrink-0 flex flex-wrap items-center gap-3">
                <template x-if="loading"><span class="text-sm text-slate-500">Cargando sugerencias…</span></template>
                <template x-if="!loading && suggestions.length > 0">
                    <span class="text-sm text-slate-600"><span x-text="suggestions.length"></span> producto(s) para reponer</span>
                </template>
                <template x-if="!loading && suggestions.length === 0">
                    <span class="text-sm text-emerald-700 font-medium">No hay productos bajo el mínimo.</span>
                </template>
                <div class="ml-auto flex gap-2" x-show="suggestions.length > 0">
                    <button type="button" @click="selectAll()" class="text-xs px-2 py-1 rounded border border-slate-200">Seleccionar todos</button>
                    <button type="button" @click="deselectAll()" class="text-xs px-2 py-1 rounded border border-slate-200">Deseleccionar</button>
                </div>
            </div>
            <div class="flex-1 overflow-auto px-4 sm:px-6 py-2 min-h-0">
                <p x-show="error" class="text-sm text-red-700 py-4" x-text="error"></p>
                <div class="lo-table-wrap" x-show="suggestions.length > 0">
                    <table class="min-w-full text-sm lo-table">
                        <thead class="bg-gray-50 text-gray-600 border-b sticky top-0">
                            <tr>
                                <th class="px-2 py-2 w-10"><input type="checkbox" class="rounded" :checked="allSelected" @change="toggleSelectAll($event.target.checked)"></th>
                                <th class="text-left px-2 py-2">Código</th>
                                <th class="text-left px-2 py-2">Producto</th>
                                <th class="text-left px-2 py-2 hidden lg:table-cell">Categoría</th>
                                <th class="text-right px-2 py-2">Uds/Caja</th>
                                <th class="text-right px-2 py-2">Efectivo</th>
                                <th class="text-right px-2 py-2">Mínimo</th>
                                <th class="text-right px-2 py-2">Faltante</th>
                                <th class="text-right px-2 py-2">Cajas</th>
                                <th class="text-left px-2 py-2">Proveedor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in suggestions" :key="row.product_id">
                                <tr :class="selected[row.product_id] ? 'bg-emerald-50/50' : ''">
                                    <td class="px-2 py-2"><input type="checkbox" class="rounded" :checked="!!selected[row.product_id]" @change="toggleRow(row.product_id, $event.target.checked)"></td>
                                    <td class="px-2 py-2 font-mono text-xs" x-text="row.code"></td>
                                    <td class="px-2 py-2"><span class="lo-truncate block max-w-xs" x-text="row.name"></span></td>
                                    <td class="px-2 py-2 hidden lg:table-cell text-gray-600" x-text="row.category_name"></td>
                                    <td class="px-2 py-2 text-right" x-text="row.units_per_box"></td>
                                    <td class="px-2 py-2 text-right font-semibold" x-text="row.stock_efectivo"></td>
                                    <td class="px-2 py-2 text-right" x-text="row.minimo"></td>
                                    <td class="px-2 py-2 text-right text-amber-700 font-medium" x-text="row.faltante"></td>
                                    <td class="px-2 py-2 text-right">
                                        <input type="number" min="1" class="w-16 rounded border px-2 py-1 text-right text-sm" :value="boxesForRow(row)" @input="setBoxes(row.product_id, $event.target.value)">
                                    </td>
                                    <td class="px-2 py-2 text-xs" x-text="row.supplier_name"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="shrink-0 px-4 sm:px-6 py-4 border-t flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" @click="closeModal()" class="min-h-11 px-4 py-2.5 rounded-lg border text-sm">Cancelar</button>
                <button type="button" @click="createOrder()" :disabled="!canCreate || creating"
                    class="min-h-11 px-5 py-2.5 rounded-lg bg-emerald-700 text-white text-sm font-medium disabled:opacity-50">
                    <span x-show="!creating">Crear pedido a proveedor</span>
                    <span x-show="creating">Creando…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="lo-table-wrap hidden md:block">
    <table class="min-w-full text-sm lo-table">
        <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
            <tr>
                <th class="text-left px-3 py-2">Código</th>
                <th class="text-left px-3 py-2">Producto</th>
                <th class="text-left px-3 py-2">Categoría</th>
                <th class="text-right px-3 py-2">Uds/Caja</th>
                <th class="text-right px-3 py-2">Stock total</th>
                <th class="text-right px-3 py-2">Mínimo</th>
                <th class="text-right px-3 py-2">Comprometido</th>
                <th class="text-right px-3 py-2">Disponible</th>
                <th class="text-right px-3 py-2">En camino</th>
                <th class="text-right px-3 py-2">Disp. + camino</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($products as $p): ?>
                <?php
                $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
                $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
                $inTransit = max(0, (int) ($p['in_transit_units'] ?? 0));
                $minimo = $p['stock_minimum'] ?? null;
                $minimoJs = $minimo === null ? 'null' : (int) $minimo;
                $canInline = !empty($hasAdjustmentsTable);
                ?>
                <tr
                    data-inline-stock-row
                    class="group"
                    :class="bajoMinimo() ? 'bg-red-50 text-red-700' : 'hover:bg-gray-50'"
                    x-data='inlineStockRow({
                        productId: <?= (int) $p['id'] ?>,
                        stockUnits: <?= $stockTotal ?>,
                        stockCommitted: <?= $stockCommitted ?>,
                        inTransit: <?= $inTransit ?>,
                        minimo: <?= $minimoJs ?>,
                        canEdit: <?= $canInline ? 'true' : 'false' ?>,
                        inlineUrl: <?= json_encode(url('/stock/inline-adjust'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                        csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
                    })'
                >
                    <td class="px-3 py-2 font-mono text-xs"><?= e((string) $p['code']) ?></td>
                    <?php if ((int) ($p['is_active'] ?? 1) !== 1): ?>
                        <td class="px-3 py-2">
                            <span class="lo-truncate" title="<?= e((string) $p['name']) ?>"><?= e((string) $p['name']) ?></span>
                            <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">Inactivo</span>
                        </td>
                    <?php else: ?>
                        <td class="px-3 py-2"><span class="lo-truncate" title="<?= e((string) $p['name']) ?>"><?= e((string) $p['name']) ?></span></td>
                    <?php endif; ?>
                    <td class="px-3 py-2 text-gray-600"><?= e((string) $p['category_name']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= (int) ($p['units_per_box'] ?? 1) ?></td>
                    <td
                        class="px-3 py-2 text-right align-middle transition-shadow duration-300 rounded-md"
                        :class="{
                            'ring-2 ring-green-400/80 ring-offset-1': cellFeedback === 'ok',
                            'ring-2 ring-red-400/80 ring-offset-1': cellFeedback === 'err'
                        }"
                    >
                        <template x-if="!editing">
                            <div class="inline-flex items-center justify-end gap-0.5 w-full min-h-[2rem]">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-end gap-1 rounded px-1 py-0.5 text-right tabular-nums <?= $canInline ? 'cursor-pointer hover:bg-slate-100/80' : 'cursor-default' ?>"
                                    @click="startEdit()"
                                    :disabled="!canEdit"
                                >
                                    <span x-text="stockUnits"></span>
                                    <i
                                        x-show="canEdit"
                                        data-lucide="pencil"
                                        class="h-3.5 w-3.5 shrink-0 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100"
                                        aria-hidden="true"
                                    ></i>
                                    <span x-show="cellFeedback === 'ok'" x-cloak class="text-green-600 font-semibold" aria-hidden="true">✓</span>
                                </button>
                            </div>
                        </template>
                        <template x-if="editing">
                            <div class="inline-flex items-center justify-end w-full">
                                <input
                                    x-ref="stockInput"
                                    type="number"
                                    min="0"
                                    step="1"
                                    x-model="draft"
                                    @keydown.enter.prevent="commit()"
                                    @keydown.escape.prevent="cancelEdit()"
                                    @keydown.tab.prevent="onTabNext($event)"
                                    @blur="onBlurCommit()"
                                    class="w-24 max-w-full rounded-md border border-sky-400/90 bg-white px-2 py-1 text-right text-sm tabular-nums shadow-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-400"
                                >
                            </div>
                        </template>
                    </td>
                    <td class="px-3 py-2 text-right" :class="bajoMinimo() ? 'font-bold text-red-700' : 'text-gray-500'"><?= $minimo !== null ? (int) $minimo : '—' ?></td>
                    <td class="px-3 py-2 text-right font-medium tabular-nums" :class="stockCommitted > 0 ? 'text-amber-600' : 'text-gray-500'" x-text="stockCommitted"></td>
                    <td class="px-3 py-2 text-right font-semibold" :class="disponibleClass()" x-text="disponible()"></td>
                    <td class="px-3 py-2 text-right <?= $inTransit > 0 ? 'text-blue-700 font-semibold' : 'text-gray-500' ?>"><?= $inTransit ?></td>
                    <td class="px-3 py-2 text-right font-semibold" :class="dispCaminoClass()" x-text="disponibleMasCamino()"></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="10" class="px-3 py-6 text-center text-gray-500">No hay productos con stock para mostrar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="md:hidden lo-mobile-card-list">
    <?php if (($products ?? []) === []): ?>
        <p class="text-center text-slate-500 py-10 text-sm">No hay productos con stock para mostrar.</p>
    <?php endif; ?>
    <?php foreach (($products ?? []) as $p): ?>
        <?php
        $stockTotal = max(0, (int) ($p['stock_units'] ?? 0));
        $stockCommitted = max(0, (int) ($p['stock_committed_units'] ?? 0));
        $stockAvailable = $stockTotal - $stockCommitted;
        $inTransit = max(0, (int) ($p['in_transit_units'] ?? 0));
        $availablePlusTransit = $stockAvailable + $inTransit;
        $minimo = $p['stock_minimum'] ?? null;
        $isBajoMinimo = $minimo !== null && $availablePlusTransit < (int) $minimo;
        ?>
        <article class="lo-mobile-card shadow-sm <?= $isBajoMinimo ? 'border-red-200 bg-red-50/40' : '' ?>">
            <div class="flex items-start justify-between gap-2 mb-1">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-slate-500"><?= e((string) $p['code']) ?></p>
                    <p class="text-base font-semibold text-slate-900 leading-snug"><?= e((string) $p['name']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?= e((string) $p['category_name']) ?></p>
                </div>
                <?php if ($isBajoMinimo): ?>
                    <span class="shrink-0 inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-800">Bajo mín.</span>
                <?php elseif ((int) ($p['is_active'] ?? 1) !== 1): ?>
                    <span class="shrink-0 inline-flex px-2 py-1 rounded-full text-[10px] bg-slate-100 text-slate-600">Inactivo</span>
                <?php endif; ?>
            </div>
            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm mt-3 pt-3 border-t border-slate-100">
                <div><dt class="text-xs text-slate-500">Stock total</dt><dd class="font-semibold"><?= $stockTotal ?></dd></div>
                <div><dt class="text-xs text-slate-500">Disponible</dt><dd class="font-semibold <?= $stockAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $stockAvailable ?></dd></div>
                <div><dt class="text-xs text-slate-500">Comprometido</dt><dd class="<?= $stockCommitted > 0 ? 'text-amber-600 font-medium' : 'text-slate-600' ?>"><?= $stockCommitted ?></dd></div>
                <div><dt class="text-xs text-slate-500">En camino</dt><dd class="<?= $inTransit > 0 ? 'text-blue-700 font-semibold' : 'text-slate-600' ?>"><?= $inTransit ?></dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Disp. + en camino</dt><dd class="font-semibold <?= $availablePlusTransit > 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= $availablePlusTransit ?></dd></div>
            </dl>
        </article>
    <?php endforeach; ?>
</div>

<?php require APP_PATH . '/Views/layout/pagination.php'; ?>

<?php if (!empty($hasAdjustmentsTable)): ?>
    <div class="lo-table-wrap mt-6">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Últimos ajustes</h3>
        </div>
        <table class="min-w-full text-sm lo-table">
            <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                <tr>
                    <th class="text-left px-3 py-2">Fecha</th>
                    <th class="text-left px-3 py-2">Código</th>
                    <th class="text-left px-3 py-2">Producto</th>
                    <th class="text-right px-3 py-2">Antes</th>
                    <th class="text-right px-3 py-2">Después</th>
                    <th class="text-right px-3 py-2">Diferencia</th>
                    <th class="text-left px-3 py-2">Usuario</th>
                    <th class="text-left px-3 py-2">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach (($adjustments ?? []) as $a): ?>
                    <tr>
                        <td class="px-3 py-2 text-gray-600"><?= e((string) ($a['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 font-mono text-xs"><?= e((string) ($a['code'] ?? '')) ?></td>
                        <td class="px-3 py-2"><span class="lo-truncate" title="<?= e((string) ($a['name'] ?? '')) ?>"><?= e((string) ($a['name'] ?? '')) ?></span></td>
                        <td class="px-3 py-2 text-right"><?= (int) ($a['previous_stock'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-right"><?= (int) ($a['new_stock'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-right <?= (int) ($a['difference'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                            <?= (int) ($a['difference'] ?? 0) >= 0 ? '+' : '' ?><?= (int) ($a['difference'] ?? 0) ?>
                        </td>
                        <td class="px-3 py-2"><?= e((string) ($a['created_by'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= e((string) ($a['notes'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($adjustments ?? []) === []): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-gray-500">Todavía no hay ajustes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
