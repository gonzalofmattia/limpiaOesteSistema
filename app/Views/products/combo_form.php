<?php
$isEdit = $combo !== null;
$action = $isEdit ? url('/combos/' . (int) $combo['id'] . '/actualizar') : url('/combos/guardar');
$initial = [];
foreach (($comboProducts ?? []) as $it) {
    $initial[] = [
        'product_id' => (int) $it['product_id'],
        'code' => (string) ($it['code'] ?? ''),
        'name' => (string) ($it['name'] ?? ''),
        'presentation' => trim((string) ($it['presentation'] ?? '')),
        'quantity' => (int) ($it['quantity'] ?? 1),
    ];
}
?>
<script>
window.__comboForm = {
    combo: <?= json_encode($combo, JSON_UNESCAPED_UNICODE) ?: 'null' ?>,
    products: <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?: '[]' ?>,
    csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<div class="max-w-5xl" x-data="comboForm()" x-init="init(window.__comboForm)">
    <form method="post" action="<?= e($action) ?>" class="space-y-6">
        <?= csrfField() ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del combo</label>
                <input type="text" name="name" required value="<?= e((string) ($combo['name'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e((string) ($combo['description'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup %</label>
                <input type="number" step="0.01" min="0" name="markup_percentage" x-model="markup" @input="refreshPrices()"
                       value="<?= e((string) ($combo['markup_percentage'] ?? '90')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descuento %</label>
                <input type="number" step="0.01" min="0" max="100" name="discount_percentage" x-model="discount"
                       value="<?= e((string) ($combo['discount_percentage'] ?? '0')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal calculado</label>
                <p class="h-10 flex items-center text-sm font-medium text-gray-800" x-text="formatCurrency(calculatedSubtotal())"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal ajustado</label>
                <input type="number" step="0.01" min="0" name="subtotal_override" x-model="subtotalOverride"
                       value="<?= e((string) ($combo['subtotal_override'] ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="inline-flex items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" name="is_active" value="1" <?= isset($combo['is_active']) ? ((int) $combo['is_active'] === 1 ? 'checked' : '') : 'checked' ?>> Activo
            </label>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-semibold text-gray-800">Productos del combo</h2>
                <button type="button" @click="addEmptyRow()" class="text-sm text-[#1565C0] hover:underline">+ Agregar fila</button>
            </div>
            <template x-for="(line, idx) in lines" :key="idx">
                <div class="border border-gray-200 rounded-lg p-4 grid md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-6">
                        <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
                        <input type="text" x-model="line.query" @input.debounce.250ms="search(idx)" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="Código o nombre...">
                        <div x-show="line.results.length" class="mt-1 border border-gray-200 rounded bg-white max-h-40 overflow-y-auto text-sm shadow">
                            <template x-for="r in line.results" :key="r.id">
                                <button type="button" class="block w-full text-left px-2 py-1.5 hover:bg-gray-50" @click="pick(idx, r)">
                                    <span class="font-medium" x-text="r.code + ' — ' + r.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="r.category_context || ''"></span>
                                </button>
                            </template>
                        </div>
                        <p class="text-xs text-gray-600 mt-1" x-show="line.product_id" x-text="line.name + (line.presentation ? ' · ' + line.presentation : '')"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                        <input type="number" min="1" x-model.number="line.quantity" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-500 mb-1">Precio unitario</label>
                        <p class="h-9 flex items-center text-sm" x-text="formatCurrency(line.unit_price || 0)"></p>
                    </div>
                    <div class="md:col-span-1 text-right">
                        <button type="button" class="text-red-600 text-sm" @click="remove(idx)">Quitar</button>
                    </div>
                    <input type="hidden" :name="'products[' + idx + '][product_id]'" :value="line.product_id">
                    <input type="hidden" :name="'products[' + idx + '][quantity]'" :value="line.quantity">
                </div>
            </template>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm text-gray-700">Precio final: <strong x-text="formatCurrency(finalPrice())"></strong></p>
            <p class="text-sm text-gray-700 mt-1">Ahorro cliente: <strong x-text="formatCurrency(savings())"></strong></p>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar</button>
            <a href="<?= e(url('/productos?tab=combos')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
<script>
function comboForm() {
    return {
        lines: [],
        markup: '90',
        discount: '0',
        subtotalOverride: '',
        async init(cfg) {
            this.lines = (cfg.products || []).map((p) => ({
                product_id: Number(p.product_id || 0),
                code: p.code || '',
                name: p.name || '',
                presentation: p.presentation || '',
                quantity: Number(p.quantity || 1),
                query: '',
                results: [],
                unit_price: 0
            }));
            if (this.lines.length === 0) {
                this.addEmptyRow();
            }
            if (cfg.combo) {
                this.markup = String(cfg.combo.markup_percentage ?? '90');
                this.discount = String(cfg.combo.discount_percentage ?? '0');
                this.subtotalOverride = cfg.combo.subtotal_override !== null ? String(cfg.combo.subtotal_override) : '';
            }
            await this.refreshPrices();
        },
        addEmptyRow() {
            this.lines.push({ product_id: 0, code: '', name: '', presentation: '', quantity: 1, query: '', results: [], unit_price: 0 });
        },
        remove(i) {
            this.lines.splice(i, 1);
            if (this.lines.length === 0) this.addEmptyRow();
        },
        async search(i) {
            const q = (this.lines[i].query || '').trim();
            if (q.length < 2) {
                this.lines[i].results = [];
                return;
            }
            const res = await fetch(window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q)));
            const data = await res.json();
            this.lines[i].results = data.results || [];
        },
        async pick(i, r) {
            const line = this.lines[i];
            line.product_id = Number(r.id || 0);
            line.code = r.code || '';
            line.name = r.name || '';
            line.query = '';
            line.results = [];
            await this.updateLinePrice(i);
        },
        async updateLinePrice(i) {
            const line = this.lines[i];
            if (!line.product_id) {
                line.unit_price = 0;
                return;
            }
            const markup = String(this.markup || '').trim();
            const res = await fetch(window.appUrl('/api/productos/' + line.product_id + '/precio?unit_type=unidad&markup=' + encodeURIComponent(markup)));
            const data = await res.json();
            line.unit_price = Number(data?.calc?.precio_venta || 0);
        },
        async refreshPrices() {
            for (let i = 0; i < this.lines.length; i += 1) {
                await this.updateLinePrice(i);
            }
        },
        calculatedSubtotal() {
            return this.lines.reduce((acc, line) => acc + (Number(line.quantity || 0) * Number(line.unit_price || 0)), 0);
        },
        effectiveSubtotal() {
            const manual = Number(this.subtotalOverride || 0);
            if (String(this.subtotalOverride || '').trim() !== '' && Number.isFinite(manual) && manual >= 0) {
                return manual;
            }
            return this.calculatedSubtotal();
        },
        savings() {
            const pct = Math.max(0, Math.min(100, Number(this.discount || 0)));
            return this.effectiveSubtotal() * (pct / 100);
        },
        finalPrice() {
            return Math.max(0, this.effectiveSubtotal() - this.savings());
        },
        formatCurrency(v) {
            return '$ ' + Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
