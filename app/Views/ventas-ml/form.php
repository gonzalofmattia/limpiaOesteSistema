<?php
$isEdit = isset($sale) && is_array($sale) && !empty($sale['id']);
$formAction = $isEdit ? url('/ventas-ml/' . (int) $sale['id']) : url('/ventas-ml/guardar');
$initialLines = [];
foreach (($items ?? []) as $it) {
    $initialLines[] = [
        'product_id' => (int) ($it['product_id'] ?? 0),
        'code' => (string) ($it['code'] ?? ''),
        'name' => (string) ($it['name'] ?? ''),
        'quantity' => (int) ($it['quantity'] ?? 1),
        'unit_type' => (string) ($it['unit_type'] ?? 'caja'),
        'pack_label' => (string) ($it['sale_unit_label'] ?? 'Caja'),
        'unit_price' => (float) ($it['unit_price'] ?? 0),
    ];
}
?>
<script>
window.__mlSaleForm = {
    lines: <?= json_encode($initialLines, JSON_UNESCAPED_UNICODE) ?: '[]' ?>,
    saleDate: <?= json_encode($isEdit ? substr((string) ($sale['created_at'] ?? ''), 0, 10) : date('Y-m-d'), JSON_UNESCAPED_UNICODE) ?>,
    mlSaleTotal: <?= json_encode((string) ($sale['ml_sale_total'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    mlNetAmount: <?= json_encode((string) ($sale['ml_net_amount'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
};
</script>
<div class="max-w-5xl" x-data="mlSaleForm()" x-init="init(window.__mlSaleForm)" x-effect="if (!manualTotal) { mlSaleTotal = productsTotal(); }">
    <form method="post" action="<?= e($formAction) ?>" class="space-y-6">
        <?= csrfField() ?>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de venta</label>
                <input type="date" name="sale_date" x-model="saleDate"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-800">Productos</h2>
                <button type="button" @click="addLine()" class="text-sm text-[#1565C0] hover:underline">+ Agregar producto</button>
            </div>
            <div class="space-y-4">
                <template x-for="(line, idx) in lines" :key="idx">
                    <div class="border border-gray-200 rounded-lg p-4 grid md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-4">
                            <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
                            <input type="text" x-model="line.query" @input.debounce.300ms="search(idx)" placeholder="Código o nombre…"
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <div x-show="line.results.length" class="mt-1 border border-gray-200 rounded bg-white max-h-40 overflow-y-auto text-sm shadow">
                                <template x-for="r in line.results" :key="r.id">
                                    <button type="button" class="block w-full text-left px-2 py-1.5 hover:bg-gray-50 border-b border-gray-50 last:border-0" @click="pick(idx, r)">
                                        <span class="block font-medium text-gray-900" x-text="r.code + ' — ' + r.name"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="text-xs text-green-700 mt-1" x-show="line.product_id">
                                Seleccionado: <span x-text="line.code + ' — ' + line.name"></span>
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <input type="number" min="1" x-model.number="line.quantity" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Presentación</label>
                            <select x-model="line.unit_type" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="caja" x-text="line.pack_label || 'Caja'"></option>
                                <option value="unidad">Unidad</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Precio unitario ML</label>
                            <input type="number" min="0" step="0.01" x-model.number="line.unit_price" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div class="md:col-span-2 flex justify-between items-center">
                            <p class="text-xs text-gray-500" x-text="formatCurrency(lineSubtotal(line))"></p>
                            <button type="button" class="text-red-600 text-sm" @click="remove(idx)">Quitar</button>
                        </div>

                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="line.product_id">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'items['+idx+'][unit_type]'" :value="line.unit_type">
                        <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="line.unit_price">
                    </div>
                </template>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Total venta ML</label>
                    <div class="flex items-center gap-2 mb-2">
                        <input id="ml-total-manual" type="checkbox" x-model="manualTotal">
                        <label for="ml-total-manual" class="text-xs text-gray-600">Editar manualmente</label>
                    </div>
                    <input type="number" min="0" step="0.01" x-model.number="mlSaleTotal"
                           :readonly="!manualTotal"
                           :class="manualTotal ? 'bg-white' : 'bg-gray-100'"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Neto recibido MP</label>
                    <input type="number" min="0" step="0.01" name="ml_net_amount" x-model.number="mlNetAmount" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-xs text-gray-500">Costos ML (Total - Neto)</p>
                    <p class="font-medium" x-text="formatCurrency(mlCosts())"></p>
                </div>
            </div>
            <input type="hidden" name="ml_sale_total" :value="safeMoney(mlSaleTotal)">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium"><?= $isEdit ? 'Guardar cambios' : 'Guardar venta ML' ?></button>
            <a href="<?= e(url('/ventas-ml')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>

<script>
function mlSaleForm() {
    return {
        lines: [],
        manualTotal: false,
        saleDate: '',
        mlSaleTotal: 0,
        mlNetAmount: 0,
        init(cfg) {
            this.saleDate = cfg && cfg.saleDate ? cfg.saleDate : '';
            this.mlSaleTotal = Number(cfg && cfg.mlSaleTotal ? cfg.mlSaleTotal : 0);
            this.mlNetAmount = Number(cfg && cfg.mlNetAmount ? cfg.mlNetAmount : 0);
            this.lines = Array.isArray(cfg && cfg.lines) ? cfg.lines.map(l => ({
                product_id: Number(l.product_id || 0),
                code: l.code || '',
                name: l.name || '',
                quantity: Number(l.quantity || 1),
                unit_type: l.unit_type || 'caja',
                pack_label: l.pack_label || 'Caja',
                query: l.product_id ? ((l.code || '') + ' — ' + (l.name || '')) : '',
                results: [],
                unit_price: Number(l.unit_price || 0)
            })) : [];
            if (this.lines.length === 0) this.addLine();
            this.manualTotal = !!(cfg && cfg.mlSaleTotal && Number(cfg.mlSaleTotal) > 0);
        },
        addLine() {
            this.lines.push({
                product_id: 0, code: '', name: '', quantity: 1, unit_type: 'caja', pack_label: 'Caja',
                query: '', results: [], unit_price: 0
            });
        },
        remove(i) {
            this.lines.splice(i, 1);
            if (this.lines.length === 0) this.addLine();
        },
        async search(i) {
            const line = this.lines[i];
            const q = (line.query || '').trim();
            if (line.product_id && q !== (line.code + ' — ' + line.name)) {
                line.product_id = 0;
                line.code = '';
                line.name = '';
            }
            if (q.length < 2) {
                line.results = [];
                return;
            }
            const res = await fetch(window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q)));
            const j = await res.json();
            line.results = j.results || [];
        },
        pick(i, r) {
            const line = this.lines[i];
            line.product_id = r.id;
            line.code = r.code;
            line.name = r.name;
            line.pack_label = (r.sale_unit_label && String(r.sale_unit_label).trim()) ? r.sale_unit_label.trim() : 'Caja';
            line.unit_type = (r.sale_unit_type === 'unidad') ? 'unidad' : 'caja';
            line.results = [];
            line.query = r.code + ' — ' + r.name;
        },
        lineSubtotal(line) {
            return Math.max(0, Number(line.quantity || 0) * Number(line.unit_price || 0));
        },
        productsTotal() {
            return this.lines.reduce((acc, line) => acc + this.lineSubtotal(line), 0);
        },
        mlCosts() {
            return Number(this.mlSaleTotal || 0) - Number(this.mlNetAmount || 0);
        },
        safeMoney(value) {
            const n = Number(value || 0);
            return Number.isFinite(n) ? n.toFixed(2) : '0.00';
        },
        formatCurrency(value) {
            const num = Number(value || 0);
            return '$ ' + num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
