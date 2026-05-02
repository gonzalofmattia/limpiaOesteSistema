<?php
use App\Helpers\PricingEngine;
$allFields = [
    'precio_lista_unitario', 'precio_lista_caja', 'precio_lista_bidon',
    'precio_lista_litro', 'precio_lista_bulto', 'precio_lista_sobre',
];
$minorista_hogar_products = $minorista_hogar_products ?? [];
$minorista_default_name = (string) ($minorista_default_name ?? 'Lista Minorista Hogar');
$minorista_default_markup = (string) ($minorista_default_markup ?? '90');
$presetJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR;
$minoristaPresetProductsJson = json_encode($minorista_hogar_products, $presetJsonFlags);
$minoristaDefaultNameJson = json_encode($minorista_default_name, $presetJsonFlags);
$minoristaDefaultMarkupJson = json_encode($minorista_default_markup, $presetJsonFlags);
?>
<script>
window.__priceListMinoristaPreset = <?= $minoristaPresetProductsJson ?>;
window.__priceListMinoristaDefaultName = <?= $minoristaDefaultNameJson ?>;
window.__priceListMinoristaDefaultMarkup = <?= $minoristaDefaultMarkupJson ?>;
</script>
<div class="max-w-4xl bg-white rounded-xl border border-gray-200 shadow-sm p-6" x-data="{
    supplier: '',
    picked: [],
    listType: '',
    minoristaPresetProducts: (typeof window.__priceListMinoristaPreset !== 'undefined' && window.__priceListMinoristaPreset ? window.__priceListMinoristaPreset : []),
    minoristaDefaultName: (typeof window.__priceListMinoristaDefaultName !== 'undefined' ? window.__priceListMinoristaDefaultName : ''),
    minoristaDefaultMarkup: (typeof window.__priceListMinoristaDefaultMarkup !== 'undefined' ? window.__priceListMinoristaDefaultMarkup : '90'),
    searchQ: '',
    searchHits: [],
    searchOpen: false,
    searchLoading: false,
    searchTimer: null,
    toggleChildren(rootId, checked) {
        document.querySelectorAll('input[type=checkbox][data-pl-parent=\'' + rootId + '\']').forEach(function (el) { el.checked = checked; });
    },
    isVisible(nodeSupplier) {
        return this.supplier === '' || this.supplier === nodeSupplier;
    },
    clearAllCategories() {
        document.querySelectorAll('input[name=\'category_ids[]\']').forEach(function (el) { el.checked = false; });
    },
    scheduleSearch() {
        if (this.searchTimer) clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(function () { this.runSearch(); }.bind(this), 300);
    },
    async runSearch() {
        var q = (this.searchQ || '').trim();
        if (q.length < 2) { this.searchHits = []; this.searchLoading = false; return; }
        this.searchLoading = true;
        this.searchHits = [];
        try {
            var u = window.appUrl('/api/productos/buscar?q=' + encodeURIComponent(q));
            if (this.supplier) u += '&supplier=' + encodeURIComponent(this.supplier);
            var res = await fetch(u);
            var data = await res.json();
            this.searchHits = data.results || [];
        } catch (e) {
            this.searchHits = [];
        } finally {
            this.searchLoading = false;
        }
    },
    addPick(row) {
        var id = parseInt(row.id, 10);
        if (!id || this.picked.some(function (p) { return p.id === id; })) return;
        this.picked.push({ id: id, code: row.code || '', name: row.name || '' });
        this.searchQ = '';
        this.searchHits = [];
        this.searchOpen = false;
    },
    removePick(id) {
        this.picked = this.picked.filter(function (p) { return p.id !== id; });
    },
    applyMinoristaPreset() {
        this.clearAllCategories();
        this.supplier = '';
        var rows = this.minoristaPresetProducts || [];
        this.picked = rows.map(function (r) {
            return { id: parseInt(r.id, 10), code: r.code || '', name: r.name || '' };
        }).filter(function (p) { return p.id > 0; });
        var root = this.$root;
        var nameInput = root.querySelector('input[name=\'name\']');
        if (nameInput) { nameInput.value = this.minoristaDefaultName; }
        var mkInput = root.querySelector('input[name=\'custom_markup\']');
        if (mkInput) { mkInput.value = this.minoristaDefaultMarkup; }
        var pf = root.querySelector('select[name=\'price_field\']');
        if (pf) { pf.value = 'precio_lista_unitario'; }
        this.listType = 'minorista';
    }
}">
    <form method="post" action="<?= e(url('/listas/preview')) ?>" class="space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="list_type" :value="listType">
        <div class="flex flex-wrap items-center gap-3 pb-2 border-b border-gray-100">
            <button type="button" @click="applyMinoristaPreset()"
                    class="px-4 py-2 rounded-lg bg-[#2E7D32] text-white text-sm font-medium hover:bg-[#1B5E20]">
                Generar Lista Minorista Hogar
            </button>
            <p class="text-xs text-gray-500 max-w-xl">Precarga productos, markup minorista (configuración o 90%), precio unitario y nombre con mes actual. Podés editar todo antes de la vista previa.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la lista</label>
            <input type="text" name="name" required placeholder="Lista Mayorista Abril 2026"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
            <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
            <select name="supplier" x-model="supplier" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos los proveedores</option>
                <?php foreach (($suppliers ?? []) as $s): ?>
                    <option value="<?= e((string) $s['slug']) ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Categorías a incluir</p>
            <div class="space-y-2 text-sm border border-gray-100 rounded-lg p-3 bg-gray-50/50">
                <?php foreach ($categoryTree as $root): ?>
                    <?php $rootSupplier = (string) ($root['supplier_slug'] ?? ''); ?>
                    <div class="pl-0" x-show="isVisible('<?= e($rootSupplier) ?>')">
                        <label class="inline-flex items-center gap-2 font-medium text-gray-800">
                            <input type="checkbox" name="category_ids[]" value="<?= (int) $root['id'] ?>" checked
                                   @change="toggleChildren(<?= (int) $root['id'] ?>, $event.target.checked)">
                            <?= e($root['name']) ?>
                            <?php if (!empty($root['supplier_name'])): ?>
                                <span class="text-xs text-gray-500">(<?= e((string) $root['supplier_name']) ?>)</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($root['children'] as $ch): ?>
                            <label class="flex items-center gap-2 ml-6 text-gray-700" x-show="isVisible('<?= e((string) ($ch['supplier_slug'] ?? $rootSupplier)) ?>')">
                                <input type="checkbox" name="category_ids[]" value="<?= (int) $ch['id'] ?>" checked
                                       data-pl-parent="<?= (int) $root['id'] ?>">
                                <?= e($ch['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-500 mt-1">Al marcar o desmarcar una categoría principal se actualizan sus subcategorías; podés ajustar cada subcategoría por separado.</p>
            <button type="button" @click="clearAllCategories()"
                    class="mt-2 text-xs text-[#1565C0] hover:underline">Desmarcar todas las categorías</button>
        </div>
        <div class="border border-gray-100 rounded-lg p-4 bg-white space-y-3" @click.away="searchOpen = false">
            <p class="text-sm font-medium text-gray-700">Productos puntuales (opcional)</p>
            <p class="text-xs text-gray-500">Buscá por código o nombre y agregá ítems a la lista. Se suman a los productos de las categorías marcadas arriba (sin duplicar).</p>
            <div class="relative">
                <input type="text" x-model="searchQ" @input="scheduleSearch()" @focus="searchOpen = true"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c]"
                       placeholder="Escribí al menos 2 caracteres…" autocomplete="off">
                <div x-show="searchOpen && (searchQ || '').trim().length >= 2"
                     x-cloak
                     class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg text-sm">
                    <template x-if="searchLoading">
                        <div class="px-3 py-2 text-gray-500">Buscando…</div>
                    </template>
                    <template x-if="!searchLoading && searchHits.length === 0">
                        <div class="px-3 py-2 text-gray-500">Sin resultados</div>
                    </template>
                    <template x-for="row in searchHits" :key="row.id">
                        <button type="button" @click="addPick(row)"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-50 last:border-0">
                            <span class="font-mono text-xs text-gray-600" x-text="row.code"></span>
                            <span class="block font-medium text-gray-900" x-text="row.name"></span>
                            <span class="block text-xs text-gray-500 truncate" x-text="row.category_context"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 min-h-[2rem]" x-show="picked.length > 0">
                <template x-for="p in picked" :key="p.id">
                    <span class="inline-flex items-center gap-1 pl-2 pr-1 py-1 rounded-full bg-[#E8F5E9] text-xs text-gray-800 border border-[#C8E6C9]">
                        <span class="font-mono text-[10px] text-gray-600" x-text="p.code"></span>
                        <span x-text="p.name"></span>
                        <button type="button" @click="removePick(p.id)" class="p-0.5 rounded hover:bg-red-100 text-gray-500 hover:text-red-700" aria-label="Quitar">×</button>
                    </span>
                </template>
            </div>
            <template x-for="p in picked" :key="'hid-' + p.id">
                <input type="hidden" name="product_ids[]" :value="p.id">
            </template>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup para esta lista (%)</label>
                <input type="text" name="custom_markup" placeholder="<?= e(setting('default_markup', '60')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">Vacío = usa reglas de producto/categoría/global.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Campo de precio base</label>
                <select name="price_field" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($allFields as $f): ?>
                        <option value="<?= e($f) ?>"><?= e(PricingEngine::priceFieldLabel($f)) ?> (<?= e($f) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Si un producto no tiene ese precio, se usa el principal de su categoría.</p>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_iva" value="1"> Incluir IVA en precio mostrado
        </label>
        <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1565C0] text-white text-sm font-medium">Vista previa</button>
            <a href="<?= e(url('/listas')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
