<?php

use App\Helpers\QuoteLinePricing;

$isEdit = $quote !== null;
$action = url($isEdit ? '/presupuestos/' . (int) $quote['id'] : '/presupuestos');
$q = $quote ?? [];
$initialLines = [];
foreach ($items as $it) {
    $isComboLine = (int) ($it['combo_id'] ?? 0) > 0;
    $packLabel = trim((string) ($it['unit_label'] ?? ''));
    if ($packLabel === '') {
        $packLabel = trim((string) ($it['sale_unit_label'] ?? '')) ?: 'Caja';
    }
    $initialLines[] = [
        'combo_id' => (int) ($it['combo_id'] ?? 0),
        'product_id' => (int) $it['product_id'],
        'code' => $it['code'] ?? '',
        'name' => $isComboLine ? ($it['combo_name'] ?? '') : ($it['name'] ?? ''),
        'quantity' => (int) ($it['quantity'] ?? 1),
        'unit_type' => $isComboLine ? 'combo' : QuoteLinePricing::normalizeUnitType((string) ($it['unit_type'] ?? 'caja')),
        'pack_label' => $isComboLine ? 'Combo' : $packLabel,
        'sale_unit_default' => (($it['sale_unit_type'] ?? 'caja') === 'unidad') ? 'unidad' : 'caja',
        'unit_price' => (float) ($it['unit_price'] ?? 0),
    ];
}
$linesJson = json_encode($initialLines, JSON_UNESCAPED_UNICODE);
$clientsJson = json_encode(array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name']], $clients), JSON_UNESCAPED_UNICODE);
?>
<script>
window.__quoteForm = {
    lines: <?= $linesJson ?: '[]' ?>,
    clients: <?= $clientsJson ?>,
    csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
    customMarkup: <?= json_encode(isset($q['custom_markup']) && $q['custom_markup'] !== null && $q['custom_markup'] !== '' ? (string) $q['custom_markup'] : '', JSON_UNESCAPED_UNICODE) ?>,
    includeIva: <?= !empty($q['include_iva']) ? 'true' : 'false' ?>,
    discountPercentage: <?= json_encode(isset($q['discount_percentage']) && $q['discount_percentage'] !== null ? (string) $q['discount_percentage'] : '', JSON_UNESCAPED_UNICODE) ?>,
    discountAmount: <?= json_encode(isset($q['discount_amount']) && $q['discount_amount'] !== null ? (string) $q['discount_amount'] : '', JSON_UNESCAPED_UNICODE) ?>
};
</script>
<div class="max-w-5xl" x-data="quoteForm()" x-init="init(window.__quoteForm)">
    <form method="post" action="<?= e($action) ?>" id="quote-form" class="space-y-6">
        <?= csrfField() ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="client_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($q['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Título (opcional)</label>
                <input type="text" name="title" value="<?= e($q['title'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup presupuesto (%)</label>
                <input type="text" name="custom_markup" placeholder="Vacío = reglas estándar"
                       x-model="customMarkup"
                       @change="refreshAllLinePrices()"
                       value="<?= isset($q['custom_markup']) && $q['custom_markup'] !== null && $q['custom_markup'] !== '' ? e((string) $q['custom_markup']) : '' ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Validez (días)</label>
                <input type="number" name="validity_days" min="1" value="<?= e((string) ($q['validity_days'] ?? setting('quote_validity_days', '7'))) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="inline-flex items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="include_iva" value="1" x-model="includeIva" @change="refreshAllLinePrices()" <?= !empty($q['include_iva']) ? 'checked' : '' ?>> Incluir IVA en precios unitarios
            </label>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas / condiciones</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($q['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-800">Ítems</h2>
                <div class="flex gap-3">
                    <button type="button" @click="addLine()" class="text-sm text-[#1565C0] hover:underline">+ Agregar producto</button>
                    <button type="button" @click="addComboLine()" class="text-sm text-[#1a6b3c] hover:underline">+ Agregar combo</button>
                </div>
            </div>
            <div class="space-y-4 mb-4">
                <template x-for="(line, idx) in lines" :key="idx">
                    <div class="border border-gray-200 rounded-lg p-4 grid md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-5" x-show="line.unit_type !== 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
                            <input type="text" x-model="line.query" @input.debounce.300ms="search(idx)" placeholder="Código o nombre…"
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <div x-show="line.results && line.results.length" class="mt-1 border border-gray-200 rounded bg-white max-h-40 overflow-y-auto text-sm shadow">
                                <template x-for="r in line.results" :key="r.id">
                                    <button type="button" class="block w-full text-left px-2 py-1.5 hover:bg-gray-50 border-b border-gray-50 last:border-0" @click="pick(idx, r)">
                                        <span class="block font-medium text-gray-900" x-text="r.code + ' — ' + r.name"></span>
                                        <span class="block text-xs text-gray-500 mt-0.5" x-show="r.category_context" x-text="r.category_context"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="text-xs text-gray-600 mt-1" x-show="line.product_id">
                                <span class="block font-medium text-gray-800" x-text="line.name"></span>
                                <span class="block text-gray-500 mt-0.5" x-show="line.category_context" x-text="line.category_context"></span>
                            </p>
                        </div>
                        <div class="md:col-span-5" x-show="line.unit_type === 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Combo</label>
                            <select x-model.number="line.combo_id" @change="pickCombo(idx)" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="0">Seleccionar combo...</option>
                                <template x-for="c in combos" :key="c.id">
                                    <option :value="c.id" x-text="c.name + ' — ' + formatCurrency(c.final_price || 0)"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-600 mt-1" x-show="line.name" x-text="line.name"></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <input type="number" min="1" x-model.number="line.quantity" @input="recalculateDiscountIfAuto()" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div class="md:col-span-3" x-show="line.unit_type !== 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Presentación</label>
                            <select x-model="line.unit_type" @change="updateLinePrice(idx)" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="caja" x-text="line.pack_label || 'Presentación'"></option>
                                <option value="unidad">Unidad</option>
                            </select>
                        </div>
                        <div class="md:col-span-3" x-show="line.unit_type === 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                            <p class="h-9 flex items-center text-sm text-gray-700">Combo</p>
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <button type="button" class="text-red-600 text-sm" @click="remove(idx)">Quitar</button>
                        </div>
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="line.product_id">
                        <input type="hidden" :name="'items['+idx+'][combo_id]'" :value="line.combo_id">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'items['+idx+'][unit_type]'" :value="line.unit_type">
                    </div>
                </template>
            </div>
            <p class="text-xs text-gray-500" x-show="lines.length === 0">Agregá al menos un producto.</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Descuento</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Porcentaje de descuento (%)</label>
                    <input type="number" min="0" max="100" step="0.01" x-model="discountPercentage" @input="onDiscountPercentageChange()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ej: 10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Monto de descuento ($)</label>
                    <input type="number" min="0" step="0.01" x-model="discountAmount" @input="onDiscountAmountManualInput()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ej: 15000">
                    <p class="text-xs text-gray-500 mt-1" x-show="discountManuallyEdited">Monto ajustado manualmente.</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-xs text-gray-500">Subtotal</p>
                    <p class="font-medium" x-text="formatCurrency(subtotal())"></p>
                    <p class="text-xs text-gray-500 mt-1">Total final</p>
                    <p class="text-lg font-semibold text-[#1a6b3c]" x-text="formatCurrency(finalTotal())"></p>
                </div>
            </div>
            <input type="hidden" name="discount_percentage" :value="normalizedDiscountPercentage()">
            <input type="hidden" name="discount_amount" :value="normalizedDiscountAmount()">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar presupuesto</button>
            <a href="<?= e(url('/presupuestos')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
<script>
function quoteForm() {
    return {
        lines: [],
        combos: [],
        customMarkup: '',
        includeIva: false,
        discountPercentage: '',
        discountAmount: '',
        discountManuallyEdited: false,
        async init(cfg) {
            this.lines = (cfg.lines || []).map(l => ({
                combo_id: Number(l.combo_id || 0),
                product_id: l.product_id || 0,
                code: l.code || '',
                name: l.name || '',
                category_context: l.category_context || '',
                quantity: l.quantity || 1,
                unit_type: l.unit_type || 'caja',
                pack_label: l.pack_label || 'Caja',
                sale_unit_default: l.sale_unit_default || 'caja',
                unit_price: Number(l.unit_price || 0),
                query: '',
                results: []
            }));
            this.customMarkup = cfg.customMarkup || '';
            this.includeIva = !!cfg.includeIva;
            this.discountPercentage = cfg.discountPercentage || '';
            this.discountAmount = cfg.discountAmount || '';
            this.discountManuallyEdited = this.discountAmount !== '';
            if (this.lines.length === 0) this.addLine();
            await this.loadCombos();
            this.refreshAllLinePrices(false);
            if (this.discountPercentage !== '' && !this.discountManuallyEdited) {
                this.recalculateDiscountAmount();
            }
        },
        addLine() {
            this.lines.push({
                combo_id: 0, product_id: 0, code: '', name: '', category_context: '', quantity: 1, unit_type: 'caja',
                pack_label: 'Caja', sale_unit_default: 'caja', unit_price: 0, query: '', results: []
            });
        },
        addComboLine() {
            this.lines.push({
                combo_id: 0, product_id: 0, code: '', name: '', category_context: '', quantity: 1, unit_type: 'combo',
                pack_label: 'Combo', sale_unit_default: 'caja', unit_price: 0, query: '', results: []
            });
        },
        async loadCombos() {
            try {
                const res = await fetch(window.appUrl('/api/combos'));
                const data = await res.json();
                this.combos = data.results || [];
            } catch (e) {
                this.combos = [];
            }
        },
        pickCombo(i) {
            const line = this.lines[i];
            const combo = this.combos.find(c => Number(c.id) === Number(line.combo_id));
            if (!combo) {
                line.name = '';
                line.unit_price = 0;
                this.recalculateDiscountIfAuto();
                return;
            }
            line.product_id = 0;
            line.name = combo.name || '';
            line.unit_price = Number(combo.final_price || 0);
            line.pack_label = 'Combo';
            this.recalculateDiscountIfAuto();
        },
        remove(i) { this.lines.splice(i, 1); if (this.lines.length === 0) this.addLine(); this.recalculateDiscountIfAuto(); },
        async search(i) {
            const line = this.lines[i];
            if (line.unit_type === 'combo') {
                this.pickCombo(i);
                return;
            }
            const q = (line.query || '').trim();
            if (q.length < 2) { line.results = []; return; }
            const res = await fetch(window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q)));
            const j = await res.json();
            line.results = j.results || [];
        },
        pick(i, r) {
            const line = this.lines[i];
            line.combo_id = 0;
            line.product_id = r.id;
            line.code = r.code;
            line.name = r.name;
            line.category_context = r.category_context || '';
            line.pack_label = (r.sale_unit_label && String(r.sale_unit_label).trim()) ? r.sale_unit_label.trim() : 'Caja';
            line.sale_unit_default = (r.sale_unit_type === 'unidad') ? 'unidad' : 'caja';
            line.unit_type = line.sale_unit_default;
            line.results = [];
            line.query = '';
            this.updateLinePrice(i);
        },
        async updateLinePrice(i) {
            const line = this.lines[i];
            if (line.unit_type === 'combo') {
                this.pickCombo(i);
                return;
            }
            if (!line || !line.product_id) {
                if (line) line.unit_price = 0;
                this.recalculateDiscountIfAuto();
                return;
            }
            try {
                const params = new URLSearchParams({
                    unit_type: line.unit_type || 'caja',
                    include_iva: this.includeIva ? '1' : '0'
                });
                if ((this.customMarkup || '').trim() !== '') {
                    params.set('markup', (this.customMarkup || '').trim());
                }
                const res = await fetch(window.appUrl('/api/productos/' + line.product_id + '/precio?' + params.toString()));
                const j = await res.json();
                if (j && j.calc) {
                    line.unit_price = Number(this.includeIva && j.calc.precio_con_iva !== null ? j.calc.precio_con_iva : j.calc.precio_venta) || 0;
                }
            } catch (e) {
                // Sin precio remoto: mantiene valor actual.
            }
            this.recalculateDiscountIfAuto();
        },
        async refreshAllLinePrices(recalculateDiscount = true) {
            for (let i = 0; i < this.lines.length; i += 1) {
                await this.updateLinePrice(i);
            }
            if (recalculateDiscount) {
                this.recalculateDiscountIfAuto();
            }
        },
        lineSubtotal(line) {
            const qty = Number(line.quantity || 0);
            const unit = Number(line.unit_price || 0);
            return Math.max(0, qty * unit);
        },
        subtotal() {
            return this.lines.reduce((acc, line) => acc + this.lineSubtotal(line), 0);
        },
        finalTotal() {
            const total = this.subtotal() - this.safeDiscountAmount();
            return total > 0 ? total : 0;
        },
        safeDiscountAmount() {
            const raw = Number(this.discountAmount || 0);
            if (!Number.isFinite(raw)) return 0;
            const bounded = raw < 0 ? 0 : raw;
            const max = this.subtotal();
            return bounded > max ? max : bounded;
        },
        onDiscountPercentageChange() {
            this.discountManuallyEdited = false;
            this.recalculateDiscountAmount();
        },
        onDiscountAmountManualInput() {
            this.discountManuallyEdited = true;
        },
        recalculateDiscountAmount() {
            const pct = Number(this.discountPercentage || 0);
            if (!Number.isFinite(pct) || pct <= 0) {
                this.discountAmount = '';
                return;
            }
            const boundedPct = Math.min(100, Math.max(0, pct));
            const amount = this.subtotal() * (boundedPct / 100);
            this.discountAmount = amount.toFixed(2);
        },
        recalculateDiscountIfAuto() {
            if (!this.discountManuallyEdited && (this.discountPercentage || '').trim() !== '') {
                this.recalculateDiscountAmount();
            }
        },
        normalizedDiscountPercentage() {
            const pct = Number(this.discountPercentage || 0);
            if (!Number.isFinite(pct) || pct <= 0) return '';
            return Math.min(100, Math.max(0, pct)).toFixed(2);
        },
        normalizedDiscountAmount() {
            const amount = this.safeDiscountAmount();
            return amount > 0 ? amount.toFixed(2) : '';
        },
        formatCurrency(value) {
            const num = Number(value || 0);
            return '$ ' + num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
