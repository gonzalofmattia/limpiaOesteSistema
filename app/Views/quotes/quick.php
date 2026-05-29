<?php
declare(strict_types=1);
$validityDays = max(1, (int) ($quote_validity_days ?? 7));
$cfg = [
    'csrf' => csrfToken(),
    'storeUrl' => url('/presupuestos/rapido'),
    'validityDays' => $validityDays,
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1565C0">
    <title><?= e($title ?? 'Presupuesto rápido') ?> — LIMPIA OESTE</title>
    <link rel="icon" type="image/png" href="<?= e(url('/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        lo: { blue: '#1565C0', border: '#E2E8F0', bg: '#F1F5F9' }
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] }
                }
            }
        };
    </script>
    <script>
        window.APP_BASE_URL = <?= json_encode(defined('BASE_URL_PATH') ? BASE_URL_PATH : (defined('BASE_URL') ? BASE_URL : ''), JSON_UNESCAPED_SLASHES) ?>;
        window.appUrl = function (path) {
            var b = (typeof window.APP_BASE_URL !== 'undefined' && window.APP_BASE_URL)
                ? String(window.APP_BASE_URL).replace(/\/$/, '')
                : '';
            path = String(path || '/').replace(/^\//, '');
            if (!b) return '/' + path;
            return b + '/' + path;
        };
        window.__QUICK_QUOTE__ = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    </script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-100 font-sans text-slate-900 antialiased" x-data="quickQuoteApp()" x-init="init()">
    <div class="flex min-h-[100dvh] flex-col" :class="step === 1 ? 'pb-[calc(14rem+env(safe-area-inset-bottom,0px))] sm:pb-[calc(12rem+env(safe-area-inset-bottom,0px))]' : 'pb-6'">
        <header class="sticky top-0 z-20 flex items-center gap-2 border-b border-slate-200 bg-white/95 px-3 py-3 backdrop-blur supports-[backdrop-filter]:bg-white/80">
            <a href="<?= e(url('/')) ?>" class="inline-flex h-11 min-w-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 active:bg-slate-50">Inicio</a>
            <h1 class="min-w-0 flex-1 text-center text-base font-bold leading-tight text-slate-800" x-text="step === 1 ? 'Pedido rápido' : 'Cliente'"></h1>
            <a href="<?= e(url('/presupuestos')) ?>" class="inline-flex h-11 min-w-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 active:bg-slate-50">Lista</a>
        </header>

        <template x-if="step === 1">
            <div class="flex flex-1 flex-col min-h-0">
                <div class="shrink-0 p-3 pt-2">
                    <label class="sr-only" for="qq-search">Buscar producto o combo</label>
                    <input
                        id="qq-search"
                        type="search"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        x-model="searchQ"
                        @input="scheduleProductSearch()"
                        x-ref="searchInput"
                        placeholder="Buscar producto o combo…"
                        class="w-full rounded-2xl border-2 border-slate-200 bg-white px-4 shadow-sm outline-none ring-lo-blue focus:border-lo-blue"
                        style="height:48px;font-size:18px"
                    >
                    <p class="mt-2 text-center text-xs text-slate-500" x-show="searchLoading" x-cloak>Buscando…</p>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-2 space-y-2" x-show="searchError">
                    <p class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800" x-text="searchError"></p>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-2 space-y-2">
                    <template x-for="row in searchResults" :key="(row.kind || 'product') + '-' + row.id">
                        <div class="flex items-stretch gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-semibold leading-snug text-slate-900" x-text="row.name"></p>
                                <p class="mt-0.5 text-xs text-slate-500 line-clamp-2" x-text="subtitle(row)"></p>
                                <p class="mt-2 text-xl font-bold text-lo-blue" x-text="row._priceLabel || '…'"></p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center self-center rounded-xl bg-lo-blue text-2xl font-bold text-white shadow active:scale-95 min-h-[44px] min-w-[44px]"
                                @click="addProduct(row)"
                                :aria-label="row.kind === 'combo' ? 'Agregar combo' : 'Agregar producto'"
                            >+</button>
                        </div>
                    </template>
                    <p class="py-8 text-center text-sm text-slate-500" x-show="searchQ.trim().length >= 2 && !searchLoading && searchResults.length === 0 && !searchError">Sin resultados</p>
                    <p class="py-6 text-center text-sm text-slate-400" x-show="searchQ.trim().length < 2">Escribí al menos 2 letras para buscar</p>
                </div>
            </div>
        </template>

        <template x-if="step === 2">
            <div class="flex flex-1 flex-col gap-3 overflow-y-auto p-3">
                <button type="button" class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-base font-semibold text-slate-800 active:bg-slate-50 min-h-[48px]" @click="backToProducts()">← Volver a productos</button>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="qq-client-q">Buscar cliente</label>
                    <input
                        id="qq-client-q"
                        type="search"
                        autocomplete="off"
                        class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-base outline-none focus:border-lo-blue min-h-[48px]"
                        style="font-size:16px"
                        placeholder="Nombre, razón social o teléfono…"
                        x-model="clientSearchQ"
                        @input="scheduleClientSearch()"
                    >
                    <div class="mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow" x-show="clientHits.length > 0" x-cloak>
                        <template x-for="c in clientHits" :key="c.id">
                            <button type="button" class="flex w-full flex-col items-start gap-0.5 border-b border-slate-100 px-4 py-3 text-left last:border-0 active:bg-slate-50" @click="selectClient(c)">
                                <span class="text-base font-semibold text-slate-900" x-text="c.name"></span>
                                <span class="text-xs text-slate-500" x-text="(c.phone || '') + (c.business_name ? ' · ' + c.business_name : '')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="selectedClient" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3" x-cloak>
                    <p class="text-xs font-semibold uppercase text-emerald-800">Cliente</p>
                    <p class="text-base font-bold text-emerald-950" x-text="selectedClient ? selectedClient.name : ''"></p>
                    <button type="button" class="mt-2 text-sm font-semibold text-emerald-800 underline" @click="clearClient()">Cambiar</button>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-bold text-slate-800">Crear cliente rápido</p>
                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600" for="qc-name">Nombre</label>
                            <input id="qc-name" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-base min-h-[48px]" style="font-size:16px" x-model="quickClient.name" placeholder="Nombre o fantasía">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600" for="qc-phone">Teléfono</label>
                            <input id="qc-phone" type="tel" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-base min-h-[48px]" style="font-size:16px" x-model="quickClient.phone" placeholder="Celular">
                        </div>
                        <p class="text-sm text-red-700" x-show="quickClientError" x-text="quickClientError"></p>
                        <button type="button" class="inline-flex h-12 items-center justify-center rounded-xl bg-slate-800 text-base font-semibold text-white active:bg-slate-900 min-h-[48px]" :disabled="quickClientSaving" @click="saveQuickClient()" x-text="quickClientSaving ? 'Guardando…' : 'Guardar cliente'"></button>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-show="selectedClient" x-cloak>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="qq-markup">Markup aplicado (%)</label>
                    <p class="mt-1 text-xs text-slate-500" x-text="markupHint"></p>
                    <input id="qq-markup" type="text" inputmode="decimal" class="mt-2 w-full rounded-xl border-2 border-slate-200 px-3 py-3 text-base font-semibold outline-none focus:border-lo-blue min-h-[48px]" style="font-size:16px" x-model="customMarkupStr" @input="scheduleMarkupRefresh()">
                </div>

                <p class="text-sm text-red-700" x-show="saveError" x-text="saveError"></p>
                <button
                    type="button"
                    class="inline-flex h-14 w-full items-center justify-center rounded-2xl bg-lo-blue text-lg font-bold text-white shadow-lg active:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 min-h-[56px]"
                    :disabled="!selectedClient || lines.length === 0 || saving"
                    @click="submitQuote()"
                    x-text="saving ? 'Creando…' : 'Crear presupuesto'"
                ></button>
            </div>
        </template>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white shadow-[0_-4px_20px_rgba(0,0,0,0.08)] pb-[env(safe-area-inset-bottom,0px)]" x-show="step === 1" x-cloak>
        <div class="max-h-[40vh] overflow-y-auto border-b border-slate-100 px-3 py-2">
            <template x-for="(line, idx) in lines" :key="line.key">
                <div class="flex items-center gap-2 border-b border-slate-50 py-2 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-tight text-slate-900" x-text="line.name"></p>
                        <p class="text-xs text-slate-500" x-text="line.unitLabel"></p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-xl font-bold text-slate-800 active:bg-slate-100 min-h-[44px] min-w-[44px]" @click="decQty(idx)">−</button>
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="h-11 w-12 rounded-xl border border-slate-200 text-center text-base font-bold min-h-[44px]"
                            style="font-size:16px"
                            :value="String(line.quantity)"
                            @input="setQtyFromInput(idx, $event.target.value)"
                        >
                        <button type="button" class="grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-xl font-bold text-slate-800 active:bg-slate-100 min-h-[44px] min-w-[44px]" @click="incQty(idx)">+</button>
                    </div>
                    <div class="w-20 text-right text-sm font-bold text-slate-800" x-text="fmtMoney(lineLineTotal(line))"></div>
                    <button type="button" class="grid h-11 w-11 place-items-center rounded-xl border border-red-100 bg-red-50 text-lg text-red-700 min-h-[44px] min-w-[44px]" @click="removeLine(idx)" aria-label="Quitar">×</button>
                </div>
            </template>
            <p class="py-4 text-center text-sm text-slate-500" x-show="lines.length === 0">Tocá + en un producto o combo para agregarlo</p>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">Total</p>
                <p class="text-xl font-bold text-slate-900" x-text="fmtMoney(cartTotal)"></p>
            </div>
            <button
                type="button"
                class="inline-flex h-14 min-w-[160px] flex-1 items-center justify-center rounded-2xl bg-lo-blue px-4 text-base font-bold text-white shadow active:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 max-w-[220px] min-h-[56px]"
                :disabled="lines.length === 0"
                @click="goStep2()"
            >Continuar</button>
        </div>
    </div>

    <script>
        function quickQuoteApp() {
            const cfg = window.__QUICK_QUOTE__ || {};
            return {
                step: 1,
                csrf: cfg.csrf || '',
                storeUrl: cfg.storeUrl || '/presupuestos/rapido',
                validityDays: cfg.validityDays || 7,
                searchQ: '',
                searchResults: [],
                searchLoading: false,
                searchError: '',
                searchTimer: null,
                clientSearchTimer: null,
                markupRefreshTimer: null,
                lines: [],
                cartTotal: 0,
                clientSearchQ: '',
                clientHits: [],
                selectedClient: null,
                customMarkupStr: '',
                markupHint: '',
                quickClient: { name: '', phone: '' },
                quickClientError: '',
                quickClientSaving: false,
                saveError: '',
                saving: false,
                init() {
                    this.$nextTick(() => {
                        if (this.$refs.searchInput) this.$refs.searchInput.focus();
                    });
                },
                scheduleProductSearch() {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.runProductSearch(), 300);
                },
                scheduleClientSearch() {
                    clearTimeout(this.clientSearchTimer);
                    this.clientSearchTimer = setTimeout(() => this.runClientSearch(), 300);
                },
                scheduleMarkupRefresh() {
                    clearTimeout(this.markupRefreshTimer);
                    this.markupRefreshTimer = setTimeout(() => this.refreshCartPrices(), 400);
                },
                subtitle(row) {
                    if (row.kind === 'combo') {
                        const n = Number(row.products_count) || 0;
                        return 'Combo' + (n > 0 ? ' · ' + n + ' producto' + (n === 1 ? '' : 's') : '');
                    }
                    const a = (row.sale_unit_description || '').trim();
                    const b = (row.content || '').trim();
                    const bits = [a, b].filter(Boolean);
                    if (bits.length) return bits.join(' · ');
                    return (row.category_context || '').trim() || '—';
                },
                fmtMoney(n) {
                    const x = Number(n) || 0;
                    return '$' + Math.round(x).toLocaleString('es-AR');
                },
                async runProductSearch() {
                    const q = (this.searchQ || '').trim();
                    this.searchError = '';
                    if (q.length < 2) {
                        this.searchResults = [];
                        return;
                    }
                    this.searchLoading = true;
                    try {
                        const enc = encodeURIComponent(q);
                        const [resP, resC] = await Promise.all([
                            fetch(window.appUrl('/api/productos/buscar?q=' + enc)),
                            fetch(window.appUrl('/api/combos/buscar?q=' + enc)),
                        ]);
                        const dataP = await resP.json().catch(() => ({}));
                        const dataC = await resC.json().catch(() => ({}));
                        const prods = ((dataP && dataP.results) ? dataP.results : []).map((r) => Object.assign({ kind: 'product', _priceLabel: '…' }, r));
                        const combos = ((dataC && dataC.results) ? dataC.results : []).map((r) => Object.assign({ _priceLabel: '…' }, r));
                        this.searchResults = combos.concat(prods);
                        this.hydrateSearchPrices();
                    } catch (e) {
                        this.searchResults = [];
                        this.searchError = 'No se pudo buscar. Reintentá.';
                    } finally {
                        this.searchLoading = false;
                    }
                },
                comboMarkupParams() {
                    const p = new URLSearchParams();
                    const m = String(this.customMarkupStr || '').trim().replace(',', '.');
                    if (m !== '' && !isNaN(Number(m))) p.set('markup', m);
                    return p.toString();
                },
                priceParams() {
                    const p = new URLSearchParams();
                    p.set('unit_type', 'unidad');
                    const m = String(this.customMarkupStr || '').trim().replace(',', '.');
                    if (m !== '' && !isNaN(Number(m))) p.set('markup', m);
                    return p.toString();
                },
                async hydrateSearchPrices() {
                    const rows = this.searchResults;
                    for (let i = 0; i < rows.length; i++) {
                        const r = rows[i];
                        try {
                            if (r.kind === 'combo') {
                                const res = await fetch(window.appUrl('/api/combos/' + r.id + '/precio?' + this.comboMarkupParams()));
                                const j = await res.json();
                                if (!res.ok || typeof j.final_price === 'undefined') continue;
                                const unit = Number(j.final_price) || 0;
                                r._unitNet = unit;
                                r._priceLabel = this.fmtMoney(unit);
                                continue;
                            }
                            const res = await fetch(window.appUrl('/api/productos/' + r.id + '/precio?' + this.priceParams()));
                            const j = await res.json();
                            if (!res.ok || !j.calc) continue;
                            const unit = Number(j.calc.precio_venta) || 0;
                            r._unitNet = unit;
                            r._priceLabel = this.fmtMoney(unit);
                        } catch (e) { /* ignore */ }
                    }
                },
                async fetchUnitPrice(productId) {
                    const res = await fetch(window.appUrl('/api/productos/' + productId + '/precio?' + this.priceParams()));
                    const j = await res.json();
                    if (!res.ok || !j.calc) return 0;
                    return Number(j.calc.precio_venta) || 0;
                },
                async fetchComboPrice(comboId) {
                    const res = await fetch(window.appUrl('/api/combos/' + comboId + '/precio?' + this.comboMarkupParams()));
                    const j = await res.json();
                    if (!res.ok || typeof j.final_price === 'undefined') return 0;
                    return Number(j.final_price) || 0;
                },
                addProduct(row) {
                    if (row.kind === 'combo') {
                        const cid = Number(row.id);
                        const existingC = this.lines.find((l) => Number(l.combo_id || 0) === cid);
                        if (existingC) {
                            existingC.quantity = Math.min(99999, existingC.quantity + 1);
                            this.recalcTotal();
                            return;
                        }
                        const hint = Number(row._unitNet) || Number(row.final_price) || 0;
                        this.lines.push({
                            key: 'c' + cid + '-' + Date.now(),
                            combo_id: cid,
                            product_id: 0,
                            name: row.name,
                            unitLabel: 'Combo',
                            quantity: 1,
                            unitPrice: hint,
                        });
                        if (!this.lines[this.lines.length - 1].unitPrice) {
                            this.fetchComboPrice(cid).then((u) => {
                                const ln = this.lines.find((l) => Number(l.combo_id || 0) === cid);
                                if (ln) { ln.unitPrice = u; this.recalcTotal(); }
                            });
                        } else {
                            this.recalcTotal();
                        }
                        return;
                    }
                    const id = Number(row.id);
                    const existing = this.lines.find((l) => Number(l.product_id || 0) === id && !Number(l.combo_id || 0));
                    if (existing) {
                        existing.quantity = Math.min(99999, existing.quantity + 1);
                        this.refreshCartPrices();
                        return;
                    }
                    const unitLabel = (row.sale_unit_label && String(row.sale_unit_type) === 'unidad') ? row.sale_unit_label : 'Unidad';
                    this.lines.push({
                        key: 'p' + id + '-' + Date.now(),
                        product_id: id,
                        combo_id: 0,
                        name: row.name,
                        unitLabel,
                        quantity: 1,
                        unitPrice: Number(row._unitNet) || 0,
                    });
                    if (!this.lines[this.lines.length - 1].unitPrice) {
                        this.fetchUnitPrice(id).then((u) => {
                            const ln = this.lines.find((l) => Number(l.product_id || 0) === id && !Number(l.combo_id || 0));
                            if (ln) { ln.unitPrice = u; this.recalcTotal(); }
                        });
                    } else {
                        this.recalcTotal();
                    }
                },
                lineLineTotal(line) {
                    return (Number(line.unitPrice) || 0) * (Number(line.quantity) || 0);
                },
                recalcTotal() {
                    let t = 0;
                    this.lines.forEach((l) => { t += this.lineLineTotal(l); });
                    this.cartTotal = t;
                },
                async refreshCartPrices() {
                    for (const line of this.lines) {
                        const cid = Number(line.combo_id || 0);
                        if (cid > 0) {
                            line.unitPrice = await this.fetchComboPrice(cid);
                        } else {
                            line.unitPrice = await this.fetchUnitPrice(line.product_id);
                        }
                    }
                    this.recalcTotal();
                    this.hydrateSearchPrices();
                },
                decQty(i) {
                    const l = this.lines[i];
                    if (!l) return;
                    l.quantity = Math.max(1, (Number(l.quantity) || 1) - 1);
                    this.recalcTotal();
                },
                incQty(i) {
                    const l = this.lines[i];
                    if (!l) return;
                    l.quantity = Math.min(99999, (Number(l.quantity) || 1) + 1);
                    this.recalcTotal();
                },
                setQtyFromInput(i, raw) {
                    const l = this.lines[i];
                    if (!l) return;
                    let n = parseInt(String(raw).replace(/\D/g, ''), 10);
                    if (!isFinite(n) || n < 1) n = 1;
                    if (n > 99999) n = 99999;
                    l.quantity = n;
                    this.recalcTotal();
                },
                removeLine(i) {
                    this.lines.splice(i, 1);
                    this.recalcTotal();
                },
                goStep2() {
                    if (this.lines.length === 0) return;
                    this.step = 2;
                    this.saveError = '';
                    if (this.selectedClient) this.loadMarkupForClient(this.selectedClient.id);
                },
                backToProducts() {
                    this.step = 1;
                    this.$nextTick(() => { if (this.$refs.searchInput) this.$refs.searchInput.focus(); });
                },
                async runClientSearch() {
                    const q = (this.clientSearchQ || '').trim();
                    if (q.length < 2) {
                        this.clientHits = [];
                        return;
                    }
                    try {
                        const res = await fetch(window.appUrl('/api/buscar?q=' + encodeURIComponent(q)));
                        const data = await res.json();
                        this.clientHits = (data.results && data.results.clients) ? data.results.clients.slice(0, 12) : [];
                    } catch (e) {
                        this.clientHits = [];
                    }
                },
                async selectClient(c) {
                    this.selectedClient = { id: Number(c.id), name: c.name || ('#' + c.id) };
                    this.clientHits = [];
                    this.clientSearchQ = '';
                    await this.loadMarkupForClient(this.selectedClient.id);
                },
                clearClient() {
                    this.selectedClient = null;
                    this.customMarkupStr = '';
                    this.markupHint = '';
                    this.refreshCartPrices();
                },
                async loadMarkupForClient(clientId) {
                    try {
                        const res = await fetch(window.appUrl('/api/clientes/' + clientId + '/markup'));
                        const data = await res.json();
                        if (!res.ok) return;
                        const mk = Number(data.markup);
                        this.customMarkupStr = isFinite(mk) ? String(mk) : '';
                        if (data.is_override) {
                            this.markupHint = 'Markup del cliente (override). Podés ajustarlo.';
                        } else {
                            this.markupHint = (data.segment_label ? ('Segmento: ' + data.segment_label + '. ') : '') + 'Valor sugerido; podés cambiarlo.';
                        }
                        await this.refreshCartPrices();
                    } catch (e) { /* */ }
                },
                async saveQuickClient() {
                    this.quickClientError = '';
                    const name = (this.quickClient.name || '').trim();
                    const phone = (this.quickClient.phone || '').trim();
                    if (!name) { this.quickClientError = 'El nombre es obligatorio.'; return; }
                    if (!phone) { this.quickClientError = 'El teléfono es obligatorio.'; return; }
                    this.quickClientSaving = true;
                    try {
                        const res = await fetch(window.appUrl('/api/clientes/crear'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ name, phone }),
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success || !data.client) {
                            this.quickClientError = (data && data.error) ? data.error : 'No se pudo crear.';
                            return;
                        }
                        this.quickClient = { name: '', phone: '' };
                        await this.selectClient(data.client);
                    } catch (e) {
                        this.quickClientError = 'Error de red.';
                    } finally {
                        this.quickClientSaving = false;
                    }
                },
                async submitQuote() {
                    if (!this.selectedClient || this.lines.length === 0) return;
                    this.saveError = '';
                    this.saving = true;
                    const items = this.lines.map((l) => {
                        const qty = Math.max(1, parseInt(String(l.quantity), 10) || 1);
                        const cid = Number(l.combo_id || 0);
                        if (cid > 0) {
                            return { combo_id: cid, product_id: 0, quantity: qty, unit_type: 'combo' };
                        }
                        return { product_id: l.product_id, quantity: qty, unit_type: 'unidad' };
                    });
                    const body = {
                        _csrf: this.csrf,
                        client_id: this.selectedClient.id,
                        items,
                        custom_markup: String(this.customMarkupStr || '').trim(),
                        validity_days: this.validityDays,
                        include_iva: false,
                    };
                    try {
                        const res = await fetch(this.storeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(body),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            this.saveError = (data && data.error) ? data.error : 'No se pudo crear el presupuesto.';
                            return;
                        }
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.href = window.appUrl('/presupuestos/' + data.id);
                        }
                    } catch (e) {
                        this.saveError = 'Error de red al guardar.';
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
