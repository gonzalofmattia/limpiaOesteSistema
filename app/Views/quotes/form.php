<?php

use App\Helpers\QuoteLinePricing;

$isEdit = $quote !== null;
$quoteStatus = (string) ($quote['status'] ?? 'draft');
$quoteEditable = !$isEdit || in_array($quoteStatus, ['draft', 'sent', 'accepted', 'partially_delivered'], true);
$isPartiallyDelivered = $isEdit && $quoteStatus === 'partially_delivered';
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
        'units_per_box' => (int) ($it['units_per_box'] ?? 1),
        'stock_units' => (int) ($it['stock_units'] ?? 0),
        'stock_committed_units' => (int) ($it['stock_committed_units'] ?? 0),
        'stock_available_units' => (int) ($it['stock_units'] ?? 0) - (int) ($it['stock_committed_units'] ?? 0),
        'unit_price' => (float) ($it['unit_price'] ?? 0),
        'markup_percent' => isset($it['markup_applied']) && $it['markup_applied'] !== null ? (float) $it['markup_applied'] : null,
        'markup_locked' => 0,
    ];
}
$linesJson = json_encode($initialLines, JSON_UNESCAPED_UNICODE);
$clientsJson = json_encode(array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name']], $clients), JSON_UNESCAPED_UNICODE);
?>
<script>
window.__quoteForm = {
    lines: <?= $linesJson ?: '[]' ?>,
    clients: <?= $clientsJson ?>,
    selectedClientId: <?= (int) ($q['client_id'] ?? 0) ?>,
    isReseller: <?= \App\Helpers\Auth::isReseller() ? 'true' : 'false' ?>,
    csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
    customMarkup: <?= json_encode(isset($q['custom_markup']) && $q['custom_markup'] !== null && $q['custom_markup'] !== '' ? (string) $q['custom_markup'] : '', JSON_UNESCAPED_UNICODE) ?>,
    includeIva: <?= !empty($q['include_iva']) ? 'true' : 'false' ?>,
    discountPercentage: <?= json_encode(isset($q['discount_percentage']) && $q['discount_percentage'] !== null ? (string) $q['discount_percentage'] : '', JSON_UNESCAPED_UNICODE) ?>,
    discountAmount: <?= json_encode(isset($q['discount_amount']) && $q['discount_amount'] !== null ? (string) $q['discount_amount'] : '', JSON_UNESCAPED_UNICODE) ?>,
    isEdit: <?= $isEdit ? 'true' : 'false' ?>
};
</script>
<div class="max-w-5xl" x-data="quoteForm()" x-init="init(window.__quoteForm)">
    <?php if (!$quoteEditable): ?>
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Este presupuesto no se puede editar en estado <strong><?= e($quoteStatus) ?></strong>. Te redirigimos al detalle.
        </div>
        <script>window.location.href = <?= json_encode(url('/presupuestos/' . (int) ($quote['id'] ?? 0)), JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>" id="quote-form" class="space-y-6"
          @submit="if (!formValid()) { $event.preventDefault(); }">
        <?= csrfField() ?>
        <?php if ($isPartiallyDelivered): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm">
            <p class="font-semibold">Este presupuesto tiene entregas parciales registradas.</p>
            <p>No se pueden eliminar ni reducir cantidades por debajo de lo ya entregado en productos con entregas.</p>
        </div>
        <?php endif; ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 md:p-6 grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <div class="w-full flex-1 min-w-0">
                        <select name="client_id" required x-model.number="selectedClientId" @change="onClientChanged()"
                                class="w-full rounded-lg px-3 py-2.5 text-base md:text-sm border transition-colors min-h-11"
                                :class="clientFieldInvalid() ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'">
                            <option value="">Seleccionar…</option>
                            <template x-for="client in clients" :key="client.id">
                                <option :value="client.id" x-text="client.name"></option>
                            </template>
                        </select>
                        <div x-show="clientFieldInvalid()" x-transition.opacity.duration.200ms class="mt-1.5">
                            <p class="text-sm text-red-600">Seleccioná un cliente</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="openQuickClientModal()"
                            class="w-full sm:w-auto shrink-0 min-h-11 px-4 py-2 rounded-lg border border-gray-300 text-base md:text-sm text-gray-700 hover:bg-gray-50 self-start">
                        + Nuevo
                    </button>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Título (opcional)</label>
                <input type="text" name="title" value="<?= e($q['title'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup presupuesto (%)</label>
                <input type="text" name="custom_markup" placeholder="Vacío = reglas estándar"
                       x-model="customMarkup"
                       @change="refreshAllLinePrices()"
                       value="<?= isset($q['custom_markup']) && $q['custom_markup'] !== null && $q['custom_markup'] !== '' ? e((string) $q['custom_markup']) : '' ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
                <p class="text-xs text-gray-500 mt-1" x-show="clientMarkupInfo" x-text="clientMarkupInfo"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Validez (días)</label>
                <input type="number" name="validity_days" min="1" value="<?= e((string) ($q['validity_days'] ?? setting('quote_validity_days', '7'))) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
            </div>
            <label class="inline-flex items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="include_iva" value="1" x-model="includeIva" @change="refreshAllLinePrices()" <?= !empty($q['include_iva']) ? 'checked' : '' ?>> Incluir IVA en precios unitarios
            </label>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas / condiciones</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-[5rem]"><?= e($q['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-800">Ítems</h2>
                <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 w-full sm:w-auto">
                    <button type="button" @click="addLine()" class="min-h-11 px-3 rounded-lg border border-blue-200 bg-blue-50/50 text-sm font-medium text-[#1565C0] text-center md:border-0 md:bg-transparent md:min-h-0 md:px-0 md:text-left md:hover:underline">+ Agregar producto</button>
                    <button type="button" @click="addComboLine()" class="min-h-11 px-3 rounded-lg border border-emerald-200 bg-emerald-50/50 text-sm font-medium text-[#1a6b3c] text-center md:border-0 md:bg-transparent md:min-h-0 md:px-0 md:text-left md:hover:underline">+ Agregar combo</button>
                </div>
            </div>
            <div class="space-y-4 mb-4">
                <template x-for="(line, idx) in lines" :key="idx">
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3 md:space-y-0 md:grid md:grid-cols-12 md:gap-3 md:items-end">
                        <div class="w-full md:col-span-5" x-show="line.unit_type !== 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
                            <input type="text" x-model="line.query" @input.debounce.300ms="search(idx)" placeholder="Código o nombre…"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
                            <div x-show="line.results && line.results.length" class="mt-1 border border-gray-200 rounded-lg bg-white max-h-48 overflow-y-auto text-base md:text-sm shadow">
                                <template x-for="r in line.results" :key="r.id">
                                    <button type="button" class="block w-full text-left px-3 py-3 min-h-12 md:min-h-0 md:px-2 md:py-1.5 hover:bg-gray-50 border-b border-gray-50 last:border-0 active:bg-gray-100" @click="pick(idx, r)">
                                        <span class="block font-medium text-gray-900" x-text="r.code + ' — ' + r.name"></span>
                                        <span class="block text-xs text-gray-500 mt-0.5" x-show="r.category_context" x-text="r.category_context"></span>
                                    </button>
                                </template>
                            </div>
                            <div class="mt-2 space-y-1" x-show="line.product_id">
                                <p class="text-base md:text-sm font-semibold text-gray-900 leading-snug" x-text="line.name"></p>
                                <p class="text-xs text-gray-500" x-show="line.category_context" x-text="line.category_context"></p>
                                <p class="text-xs text-gray-500" x-show="line.markup_percent !== null">
                                    Markup: <span x-text="Number(line.markup_percent).toFixed(2) + '%'"></span>
                                    <span x-show="line.markup_locked" title="Markup protegido por categoría">🔒</span>
                                </p>
                            </div>
                        </div>
                        <div class="w-full md:col-span-5" x-show="line.unit_type === 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Combo</label>
                            <select x-model.number="line.combo_id" @change="pickCombo(idx)" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
                                <option value="0">Seleccionar combo...</option>
                                <template x-for="c in combos" :key="c.id">
                                    <option :value="c.id" x-text="c.name + ' — ' + formatCurrency(c.final_price || 0)"></option>
                                </template>
                            </select>
                            <p class="text-base md:text-sm font-semibold text-gray-900 mt-2" x-show="line.name" x-text="line.name"></p>
                            <div class="mt-2 flex items-center justify-between gap-2 md:hidden border-t border-gray-100 pt-3" x-show="line.combo_id">
                                <span class="text-xs text-gray-500">Precio combo</span>
                                <span class="text-base font-semibold text-gray-900" x-text="formatCurrency(line.unit_price)"></span>
                            </div>
                        </div>
                        <div class="w-full md:col-span-3" x-show="line.unit_type !== 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Presentación</label>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center md:block md:space-y-0">
                                <select x-model="line.unit_type" @change="updateLinePrice(idx)" class="w-full flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
                                    <option value="caja" x-text="line.pack_label || 'Presentación'"></option>
                                    <option value="unidad">Unidad</option>
                                </select>
                                <p class="text-base font-semibold text-gray-900 shrink-0 md:hidden" x-show="!isReseller" x-text="formatCurrency(line.unit_price)"></p>
                            </div>
                            <p class="hidden md:block text-xs text-gray-500 mt-1" x-show="!isReseller && line.product_id">Precio unit.: <span class="font-medium text-gray-800" x-text="formatCurrency(line.unit_price)"></span></p>
                        </div>
                        <div class="hidden md:block w-full md:col-span-3" x-show="line.unit_type === 'combo'">
                            <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                            <p class="min-h-11 flex items-center text-base md:text-sm text-gray-700">Combo</p>
                        </div>
                        <div class="w-full md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <div class="flex items-stretch gap-2">
                                <button type="button" class="md:hidden shrink-0 w-11 h-11 rounded-lg border-2 border-gray-300 text-xl font-semibold leading-none text-gray-800 bg-gray-50 active:bg-gray-100 flex items-center justify-center" @click="adjustQty(idx, -1)" aria-label="Menos">−</button>
                                <input type="number" min="1" step="1" x-model.number="line.quantity" @input="recalculateDiscountIfAuto()"
                                       class="flex-1 min-w-0 text-center md:text-left rounded-lg px-3 py-2.5 text-base md:text-sm border transition-colors min-h-11"
                                       :class="lineQuantityInvalid(line) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'">
                                <button type="button" class="md:hidden shrink-0 w-11 h-11 rounded-lg border-2 border-gray-300 text-xl font-semibold leading-none text-gray-800 bg-gray-50 active:bg-gray-100 flex items-center justify-center" @click="adjustQty(idx, 1)" aria-label="Más">+</button>
                            </div>
                            <div x-show="lineQuantityInvalid(line)" x-transition.opacity.duration.200ms class="mt-1">
                                <p class="text-xs text-red-600">La cantidad debe ser al menos 1</p>
                            </div>
                        </div>
                        <div class="w-full md:col-span-2 flex md:justify-end pt-1 md:pt-0">
                            <button type="button" class="min-h-11 w-full md:w-auto px-4 rounded-lg border border-red-100 md:border-0 text-red-600 text-sm font-semibold hover:bg-red-50" @click="remove(idx)">Quitar ítem</button>
                        </div>
                        <template x-if="isReseller">
                            <div class="w-full md:col-span-12 border-t border-gray-100 pt-3" x-show="line.product_id || line.combo_id">
                                <label class="block text-xs text-gray-500 mb-1">Precio de venta (por <span x-text="line.unit_type === 'unidad' ? 'unidad' : (line.unit_type === 'combo' ? 'combo' : 'caja')"></span>)</label>
                                <div class="flex items-center gap-2 max-w-xs">
                                    <span class="text-gray-500">$</span>
                                    <input type="number" min="0" step="0.01" x-model.number="line.unit_price" @input="recalculateDiscountIfAuto()"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11">
                                </div>
                                <p class="text-xs text-gray-500 mt-1" x-show="line.suggested_price">
                                    Sugerido: <span x-text="formatCurrency(line.suggested_price)"></span>
                                    <button type="button" class="text-[#1565C0] hover:underline ml-1" @click="line.unit_price = line.suggested_price; recalculateDiscountIfAuto()">usar sugerido</button>
                                </p>
                            </div>
                        </template>
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="line.product_id">
                        <input type="hidden" :name="'items['+idx+'][combo_id]'" :value="line.combo_id">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'items['+idx+'][unit_type]'" :value="line.unit_type">
                        <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="line.unit_price">
                        <div class="w-full md:col-span-12" x-show="line.stock_warnings && line.stock_warnings.length">
                            <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                <template x-for="(warn, widx) in line.stock_warnings" :key="widx">
                                    <p x-text="'⚠️ ' + warn"></p>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="itemsFieldInvalid()" x-transition.opacity.duration.200ms class="rounded-lg border border-red-200 bg-red-50 px-3 py-2">
                <p class="text-sm text-red-600 font-medium">Agregá al menos un producto</p>
                <p class="text-xs text-red-600/90 mt-0.5">Elegí un producto o un combo en al menos una línea.</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Descuento</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Porcentaje de descuento (%)</label>
                    <input type="number" min="0" max="100" step="0.01" x-model="discountPercentage" @change="onDiscountPercentageChange()" @blur="onDiscountPercentageChange()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11" placeholder="Ej: 10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Monto de descuento ($)</label>
                    <input type="number" min="0" step="0.01" x-model="discountAmount" @change="onDiscountAmountManualInput()" @blur="onDiscountAmountManualInput()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-base md:text-sm min-h-11" placeholder="Ej: 15000">
                    <p class="text-xs text-gray-500 mt-1" x-show="discountManuallyEdited">Monto ajustado manualmente.</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-xs text-gray-500">Subtotal</p>
                    <p class="font-medium" x-text="formatCurrency(subtotal())"></p>
                    <p class="text-xs text-gray-500 mt-1" x-show="safeDiscountAmount() > 0">Descuento</p>
                    <p class="font-medium text-red-700" x-show="safeDiscountAmount() > 0" x-text="'- ' + formatCurrency(safeDiscountAmount())"></p>
                    <p class="text-xs text-gray-500 mt-1">Total final</p>
                    <p class="text-lg font-semibold text-[#1a6b3c]" x-text="formatCurrency(finalTotal())"></p>
                </div>
            </div>
            <input type="hidden" name="discount_percentage" :value="normalizedDiscountPercentage()">
            <input type="hidden" name="discount_amount" :value="normalizedDiscountAmount()">
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <button type="submit"
                    :disabled="!formValid()"
                    :class="formValid() ? '' : 'opacity-50 cursor-not-allowed pointer-events-none'"
                    class="min-h-11 px-5 py-3 rounded-lg bg-[#1a6b3c] text-white text-base md:text-sm font-medium">
                Guardar presupuesto
            </button>
            <a href="<?= e(url('/presupuestos')) ?>" class="min-h-11 px-5 py-3 rounded-lg border border-gray-300 text-base md:text-sm text-center sm:text-left flex items-center justify-center">Cancelar</a>
        </div>
    </form>

    <div x-show="quickClientModalOpen"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
         @click.self="closeQuickClientModal()"
         @keydown.escape.window="closeQuickClientModal()">
        <div x-show="quickClientModalOpen"
             x-transition
             class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Nuevo cliente</h3>
            <p class="mt-1 text-sm text-gray-500">Alta rápida para seleccionar el cliente y continuar el presupuesto.</p>

            <div x-show="quickClientError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="quickClientError"></div>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" x-model.trim="quickClientForm.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Nombre del cliente">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono *</label>
                    <input type="text" x-model.trim="quickClientForm.phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ej: 11 5555 5555">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                    <input type="text" x-model.trim="quickClientForm.city" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Opcional">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button"
                        @click="closeQuickClientModal()"
                        :disabled="quickClientSaving"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-60">
                    Cancelar
                </button>
                <button type="button"
                        @click="saveQuickClient()"
                        :disabled="quickClientSaving"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#14542f] disabled:opacity-60">
                    <i x-show="quickClientSaving" data-lucide="loader-circle" class="h-4 w-4 animate-spin"></i>
                    <span x-text="quickClientSaving ? 'Guardando...' : 'Guardar'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function quoteForm() {
    return {
        lines: [],
        combos: [],
        clients: [],
        selectedClientId: '',
        isEdit: false,
        isReseller: false,
        clientMarkupInfo: '',
        customMarkup: '',
        includeIva: false,
        discountPercentage: '',
        discountAmount: '',
        discountManuallyEdited: false,
        quickClientModalOpen: false,
        quickClientSaving: false,
        quickClientError: '',
        quickClientForm: { name: '', phone: '', city: '' },
        async init(cfg) {
            cfg = cfg || window.__quoteForm || {};
            this.clients = Array.isArray(cfg.clients) ? cfg.clients : [];
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
                suggested_price: Number(l.unit_price || 0),
                markup_percent: l.markup_percent === null || l.markup_percent === undefined ? null : Number(l.markup_percent),
                markup_locked: Number(l.markup_locked || 0),
                units_per_box: Number(l.units_per_box || 1),
                stock_units: Number(l.stock_units || 0),
                stock_committed_units: Number(l.stock_committed_units || 0),
                stock_available_units: Number(l.stock_available_units || 0),
                combo_products: [],
                stock_warnings: [],
                query: '',
                results: []
            }));
            this.customMarkup = cfg.customMarkup || '';
            this.isEdit = !!cfg.isEdit;
            this.isReseller = !!cfg.isReseller;
            this.includeIva = !!cfg.includeIva;
            this.discountPercentage = cfg.discountPercentage || '';
            this.discountAmount = cfg.discountAmount || '';
            this.discountManuallyEdited = this.discountAmount !== '';
            if (this.lines.length === 0) this.addLine();
            await this.loadCombos();
            this.selectedClientId = Number(cfg.selectedClientId || 0) || '';
            if (!this.isEdit && this.selectedClientId) {
                await this.applyClientMarkup(this.selectedClientId);
            }
            this.refreshAllLinePrices(false);
            if (this.discountPercentage !== '' && !this.discountManuallyEdited) {
                this.recalculateDiscountAmount();
            }
        },
        async onClientChanged() {
            const clientId = Number(this.selectedClientId || 0);
            if (!clientId) {
                this.clientMarkupInfo = '';
                return;
            }
            await this.applyClientMarkup(clientId);
        },
        hasSelectedClient() {
            const id = Number(this.selectedClientId);
            return Number.isFinite(id) && id > 0;
        },
        clientFieldInvalid() {
            return !this.hasSelectedClient();
        },
        lineHasProductOrCombo(line) {
            if (String(line.unit_type || '') === 'combo') {
                return Number(line.combo_id || 0) > 0;
            }
            return Number(line.product_id || 0) > 0;
        },
        hasAtLeastOneItem() {
            return this.lines.some((line) => this.lineHasProductOrCombo(line));
        },
        itemsFieldInvalid() {
            return !this.hasAtLeastOneItem();
        },
        lineQuantityInvalid(line) {
            const q = Number(line.quantity);
            return !Number.isFinite(q) || q < 1;
        },
        allLinesHaveValidQuantity() {
            return this.lines.every((line) => !this.lineQuantityInvalid(line));
        },
        formValid() {
            return this.hasSelectedClient() && this.hasAtLeastOneItem() && this.allLinesHaveValidQuantity();
        },
        async applyClientMarkup(clientId) {
            try {
                const res = await fetch(window.appUrl('/api/clientes/' + clientId + '/markup'));
                const data = await res.json();
                if (!res.ok) {
                    return;
                }
                const markup = Number(data.markup || 0);
                this.customMarkup = Number.isFinite(markup) ? markup.toFixed(2) : '';
                const label = String(data.segment_label || '');
                if (data.is_override) {
                    this.clientMarkupInfo = 'Markup cliente: ' + this.customMarkup + '% (override individual)';
                } else {
                    this.clientMarkupInfo = label !== ''
                        ? 'Segmento ' + label + ': ' + this.customMarkup + '%'
                        : 'Markup segmento: ' + this.customMarkup + '%';
                }
                await this.refreshAllLinePrices();
            } catch (e) {
                // Silencioso: si falla API no bloquea el formulario.
            }
        },
        openQuickClientModal() {
            this.quickClientError = '';
            this.quickClientSaving = false;
            this.quickClientForm = { name: '', phone: '', city: '' };
            this.quickClientModalOpen = true;
        },
        closeQuickClientModal() {
            if (this.quickClientSaving) return;
            this.quickClientModalOpen = false;
        },
        async saveQuickClient() {
            const name = (this.quickClientForm.name || '').trim();
            const phone = (this.quickClientForm.phone || '').trim();
            const city = (this.quickClientForm.city || '').trim();
            if (name === '') {
                this.quickClientError = 'El nombre es obligatorio.';
                return;
            }
            if (phone === '') {
                this.quickClientError = 'El teléfono es obligatorio.';
                return;
            }
            this.quickClientSaving = true;
            this.quickClientError = '';
            try {
                const res = await fetch(window.appUrl('/api/clientes/crear'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ name, phone, city })
                });
                const data = await res.json();
                if (!res.ok || !data.success || !data.client) {
                    this.quickClientError = data && data.error ? data.error : 'No se pudo crear el cliente.';
                    this.quickClientSaving = false;
                    return;
                }
                const created = {
                    id: Number(data.client.id),
                    name: String(data.client.name || name),
                    phone: data.client.phone || phone,
                    city: data.client.city || city || null
                };
                const existingIdx = this.clients.findIndex(c => Number(c.id) === created.id);
                if (existingIdx >= 0) {
                    this.clients.splice(existingIdx, 1, created);
                } else {
                    this.clients.push(created);
                    this.clients.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'es', { sensitivity: 'base' }));
                }
                this.selectedClientId = created.id;
                await this.applyClientMarkup(created.id);
                this.quickClientModalOpen = false;
            } catch (e) {
                this.quickClientError = 'Error de conexión al crear cliente.';
            } finally {
                this.quickClientSaving = false;
            }
        },
        addLine() {
            this.lines.push({
                combo_id: 0, product_id: 0, code: '', name: '', category_context: '', quantity: 1, unit_type: 'caja',
                pack_label: 'Caja', sale_unit_default: 'caja', unit_price: 0, suggested_price: 0, markup_percent: null, markup_locked: 0, units_per_box: 1, stock_units: 0, stock_committed_units: 0,
                stock_available_units: 0, combo_products: [], stock_warnings: [], query: '', results: []
            });
        },
        addComboLine() {
            this.lines.push({
                combo_id: 0, product_id: 0, code: '', name: '', category_context: '', quantity: 1, unit_type: 'combo',
                pack_label: 'Combo', sale_unit_default: 'caja', unit_price: 0, suggested_price: 0, markup_percent: null, markup_locked: 0, units_per_box: 1, stock_units: 0, stock_committed_units: 0,
                stock_available_units: 0, combo_products: [], stock_warnings: [], query: '', results: []
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
        async pickCombo(i) {
            const line = this.lines[i];
            const combo = this.combos.find(c => Number(c.id) === Number(line.combo_id));
            if (!combo) {
                line.name = '';
                line.unit_price = 0;
                line.combo_products = [];
                line.stock_warnings = [];
                this.recalculateDiscountIfAuto();
                return;
            }
            line.product_id = 0;
            line.name = combo.name || '';
            line.unit_price = Number(combo.final_price || 0);
            line.suggested_price = line.unit_price;
                line.markup_percent = null;
                line.markup_locked = 0;
            line.pack_label = 'Combo';
            try {
                const res = await fetch(window.appUrl('/api/combos/' + line.combo_id));
                const data = await res.json();
                line.combo_products = Array.isArray(data.products) ? data.products : [];
            } catch (e) {
                line.combo_products = [];
            }
            this.computeLineStockWarnings(line);
            this.recalculateDiscountIfAuto();
        },
        remove(i) { this.lines.splice(i, 1); if (this.lines.length === 0) this.addLine(); this.recalculateDiscountIfAuto(); },
        adjustQty(i, delta) {
            const line = this.lines[i];
            let q = parseInt(line.quantity, 10) || 1;
            q += delta;
            if (q < 1) q = 1;
            line.quantity = q;
            this.recalculateDiscountIfAuto();
            this.computeLineStockWarnings(line);
        },
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
            line.markup_percent = null;
            line.markup_locked = 0;
            line.units_per_box = Number(r.units_per_box || 1);
            line.stock_units = Number(r.stock_units || 0);
            line.stock_committed_units = Number(r.stock_committed_units || 0);
            line.stock_available_units = Number(r.stock_available_units || 0);
            line.combo_products = [];
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
                if (line) {
                    line.unit_price = 0;
                    line.markup_percent = null;
                    line.markup_locked = 0;
                }
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
                    line.suggested_price = line.unit_price;
                    line.markup_percent = Number.isFinite(Number(j.calc.markup_percent)) ? Number(j.calc.markup_percent) : null;
                    line.markup_locked = j.calc.markup_locked ? 1 : 0;
                }
            } catch (e) {
                // Sin precio remoto: mantiene valor actual.
            }
            this.computeLineStockWarnings(line);
            this.recalculateDiscountIfAuto();
        },
        async refreshAllLinePrices(recalculateDiscount = true) {
            for (let i = 0; i < this.lines.length; i += 1) {
                await this.updateLinePrice(i);
            }
            if (recalculateDiscount) {
                this.recalculateDiscountIfAuto();
            }
            this.refreshStockWarnings();
        },
        lineRequestedUnits(line) {
            const qty = Number(line.quantity || 0);
            if (qty <= 0) return 0;
            if (line.unit_type === 'combo') return qty;
            const upb = Number(line.units_per_box || 1);
            return line.unit_type === 'unidad' ? qty : qty * (upb > 0 ? upb : 1);
        },
        computeLineStockWarnings(line) {
            const warnings = [];
            const qty = Math.max(1, Number(line.quantity || 1));
            if (line.unit_type === 'combo') {
                const comboProducts = Array.isArray(line.combo_products) ? line.combo_products : [];
                comboProducts.forEach(cp => {
                    const required = qty * Math.max(1, Number(cp.quantity || 1));
                    const available = Number(cp.stock_available_units || 0);
                    if (required > available) {
                        warnings.push(`Stock insuficiente para ${cp.name || cp.code || 'producto'} (combo). Disponible: ${available} un., solicitado: ${required} un.`);
                    }
                });
                line.stock_warnings = warnings;
                return;
            }
            if (!line.product_id) {
                line.stock_warnings = [];
                return;
            }
            const requested = this.lineRequestedUnits(line);
            const available = Number(line.stock_available_units || 0);
            if (requested > available) {
                warnings.push(`Stock insuficiente para ${line.name || 'producto'}. Disponible: ${available} un., solicitado: ${requested} un.`);
            }
            line.stock_warnings = warnings;
        },
        refreshStockWarnings() {
            this.lines.forEach(line => this.computeLineStockWarnings(line));
        },
        lineSubtotal(line) {
            const qty = Number(line.quantity || 0);
            const unit = Number(line.unit_price || 0);
            this.computeLineStockWarnings(line);
            return Math.max(0, qty * unit);
        },
        isComboLine(line) {
            return String(line.unit_type || '') === 'combo';
        },
        subtotalDiscountable() {
            return this.lines.reduce((acc, line) => acc + (this.isComboLine(line) ? 0 : this.lineSubtotal(line)), 0);
        },
        subtotalNonDiscountable() {
            return this.lines.reduce((acc, line) => acc + (this.isComboLine(line) ? this.lineSubtotal(line) : 0), 0);
        },
        subtotal() {
            return this.lines.reduce((acc, line) => acc + this.lineSubtotal(line), 0);
        },
        finalTotal() {
            const total = (this.subtotalDiscountable() - this.safeDiscountAmount()) + this.subtotalNonDiscountable();
            return total > 0 ? total : 0;
        },
        safeDiscountAmount() {
            const raw = Number(this.discountAmount || 0);
            if (!Number.isFinite(raw)) return 0;
            const bounded = raw < 0 ? 0 : raw;
            const max = this.subtotalDiscountable();
            return bounded > max ? max : bounded;
        },
        onDiscountPercentageChange() {
            this.discountManuallyEdited = false;
            this.recalculateDiscountAmount();
        },
        onDiscountAmountManualInput() {
            this.discountManuallyEdited = true;
            const base = this.subtotalDiscountable();
            const raw = Number(this.discountAmount || 0);
            if (!Number.isFinite(raw) || raw <= 0 || base <= 0) {
                this.discountAmount = '';
                this.discountPercentage = '';
                return;
            }
            const bounded = Math.min(base, Math.max(0, raw));
            this.discountAmount = bounded.toFixed(2);
            this.discountPercentage = ((bounded / base) * 100).toFixed(2);
        },
        recalculateDiscountAmount() {
            const pct = Number(this.discountPercentage || 0);
            if (!Number.isFinite(pct) || pct <= 0) {
                this.discountAmount = '';
                return;
            }
            const boundedPct = Math.min(100, Math.max(0, pct));
            this.discountPercentage = boundedPct.toFixed(2);
            const amount = this.subtotalDiscountable() * (boundedPct / 100);
            this.discountAmount = amount.toFixed(2);
        },
        recalculateDiscountIfAuto() {
            if (!this.discountManuallyEdited && (this.discountPercentage || '').trim() !== '') {
                this.recalculateDiscountAmount();
            }
        },
        normalizedDiscountPercentage() {
            const base = this.subtotalDiscountable();
            if (base <= 0) return '';
            const amount = this.safeDiscountAmount();
            if (amount <= 0) return '';
            return Math.min(100, Math.max(0, (amount / base) * 100)).toFixed(2);
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
