<?php
$stats = $stats ?? ['total' => 0, 'with_photo' => 0, 'without_photo' => 0];
$categories = $categories ?? [];
$categoryFilterOptions = $categoryFilterOptions ?? [];
$categoryFilterMapJson = json_encode($categoryFilterMap ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$csrf = csrfToken();
?>
<div id="vcat-root">
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Productos activos</p>
            <p class="text-2xl font-semibold"><?= (int) $stats['total'] ?></p>
        </div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Con foto</p>
            <p class="text-2xl font-semibold text-green-700"><?= (int) $stats['with_photo'] ?></p>
        </div>
        <div class="lo-card p-4">
            <p class="text-xs text-slate-500">Sin foto</p>
            <p class="text-2xl font-semibold text-red-600"><?= (int) $stats['without_photo'] ?></p>
        </div>
    </div>

    <div class="lo-card p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1" for="vcat-category-filter">Categoría</label>
            <select id="vcat-category-filter" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-[#1a6b3c]">
                <option value="">Todas las categorías</option>
                <?php foreach ($categoryFilterOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>"><?= e($opt['label']) ?><?= !empty($opt['is_parent']) ? ' (todos)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1" for="vcat-search">Buscar</label>
            <input type="text" id="vcat-search" placeholder="Nombre o código..."
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-[#1a6b3c]">
        </div>
        <label class="flex items-center gap-2 h-10 px-3 rounded-lg border border-gray-300 bg-white cursor-pointer select-none">
            <input type="checkbox" id="vcat-only-no-photo" class="rounded border-gray-300 text-[#1a6b3c] focus:ring-[#1a6b3c]">
            <span class="text-sm text-gray-700">Solo sin foto</span>
        </label>
        <p class="text-sm text-gray-500 sm:ml-auto"><span id="vcat-visible-count">0</span> productos visibles</p>
    </div>

    <?php foreach ($categories as $cat): ?>
        <section class="mb-10 vcat-section" data-vcat-section="<?= (int) $cat['id'] ?>">
            <div class="flex items-center gap-3 mb-4">
                <h2 class="text-lg font-semibold text-slate-800"><?= e($cat['name']) ?></h2>
                <span class="text-xs text-slate-400 vcat-section-count"><?= count($cat['products']) ?> productos</span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($cat['products'] as $product):
                    $pid = (int) $product['id'];
                    $hasPhoto = !empty($product['has_photo']);
                    ?>
                    <article class="lo-card overflow-hidden flex flex-col vcat-card"
                             data-product-id="<?= $pid ?>"
                             data-category-id="<?= (int) $product['category_id'] ?>"
                             data-has-photo="<?= $hasPhoto ? '1' : '0' ?>"
                             data-name="<?= e($product['name']) ?>"
                             data-code="<?= e($product['code']) ?>">
                        <div class="relative aspect-square bg-slate-100 vcat-photo-wrap">
                            <?php if ($hasPhoto && !empty($product['image_url'])): ?>
                                <img src="<?= e($product['image_url']) ?>"
                                     alt="<?= e($product['name']) ?>"
                                     class="w-full h-full object-cover vcat-photo">
                                <button type="button"
                                        class="absolute top-2 right-2 px-2 py-1 rounded-md bg-white/90 border border-slate-200 text-[11px] font-medium text-slate-600 hover:bg-white shadow-sm"
                                        onclick="vcatUpload(<?= $pid ?>)">Cambiar foto</button>
                            <?php else: ?>
                                <div class="photo-placeholder w-full h-full flex flex-col items-center justify-center gap-2 text-slate-400 hover:bg-slate-200/60 transition cursor-pointer"
                                     onclick="vcatUpload(<?= $pid ?>)">
                                    <i data-lucide="camera" class="h-10 w-10"></i>
                                    <span class="text-xs font-medium">Click para subir foto</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 flex-1 flex flex-col gap-1">
                            <h3 class="text-sm font-medium text-slate-900 leading-snug break-words"><?= e($product['name']) ?></h3>
                            <p class="text-xs text-slate-400 font-mono"><?= e($product['code']) ?></p>
                            <?php if (!empty($product['volume'])): ?>
                                <p class="text-xs text-slate-500"><?= e($product['volume']) ?></p>
                            <?php endif; ?>
                            <div class="mt-auto pt-2">
                                <span class="badge-foto inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $hasPhoto ? 'badge-green bg-green-100 text-green-800' : 'badge-red bg-red-100 text-red-800' ?>">
                                    <?= $hasPhoto ? 'Con foto' : 'Sin foto' ?>
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div id="vcat-empty" class="lo-card p-12 text-center text-slate-500 hidden">
        <i data-lucide="image-off" class="h-10 w-10 mx-auto mb-3 text-slate-300"></i>
        <p class="text-sm">No hay productos que coincidan con los filtros.</p>
    </div>
</div>

<input type="file" id="vcat-file-input" accept="image/jpeg,image/png,image/webp,image/*" style="display:none">

<script>
(function () {
    const categoryFilterMap = <?= $categoryFilterMapJson ?>;
    const categoryFilter = document.getElementById('vcat-category-filter');
    const searchInput = document.getElementById('vcat-search');
    const onlyNoPhoto = document.getElementById('vcat-only-no-photo');
    const visibleCountEl = document.getElementById('vcat-visible-count');
    const emptyEl = document.getElementById('vcat-empty');

    function applyFilters() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const catVal = categoryFilter.value;
        const catIds = catVal ? (categoryFilterMap[catVal] || categoryFilterMap[parseInt(catVal, 10)] || [parseInt(catVal, 10)]) : null;
        const onlyNo = onlyNoPhoto.checked;
        let visible = 0;

        document.querySelectorAll('.vcat-card').forEach(function (card) {
            const cid = parseInt(card.dataset.categoryId, 10);
            const hasPhoto = card.dataset.hasPhoto === '1';
            const name = (card.dataset.name || '').toLowerCase();
            const code = (card.dataset.code || '').toLowerCase();
            let show = true;
            if (catIds && catIds.indexOf(cid) === -1) show = false;
            if (onlyNo && hasPhoto) show = false;
            if (q !== '' && name.indexOf(q) === -1 && code.indexOf(q) === -1) show = false;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.querySelectorAll('.vcat-section').forEach(function (section) {
            const cards = section.querySelectorAll('.vcat-card');
            let sectionVisible = 0;
            cards.forEach(function (c) {
                if (c.style.display !== 'none') sectionVisible++;
            });
            section.style.display = sectionVisible > 0 ? '' : 'none';
        });

        if (visibleCountEl) visibleCountEl.textContent = String(visible);
        if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
        if (window.rebuildLucideIcons) window.rebuildLucideIcons();
    }

    categoryFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    onlyNoPhoto.addEventListener('change', applyFilters);
    window.vcatApplyFilters = applyFilters;
    applyFilters();
})();

let vcatCurrentId = null;
const vcatInput = document.getElementById('vcat-file-input');
const vcatCsrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function vcatUpload(productId) {
    vcatCurrentId = productId;
    vcatInput.click();
}

vcatInput.addEventListener('change', function () {
    if (!this.files[0] || !vcatCurrentId) return;
    const productId = vcatCurrentId;
    const formData = new FormData();
    formData.append('image', this.files[0]);
    formData.append('_csrf', vcatCsrf);

    const card = document.querySelector('[data-product-id="' + productId + '"]');
    const wrap = card ? card.querySelector('.vcat-photo-wrap') : null;

    fetch(window.appUrl('/api/productos/' + productId + '/subir-imagen'), {
        method: 'POST',
        body: formData,
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                alert(data.error || 'Error al subir la imagen');
                return;
            }
            if (!card || !wrap) return;

            wrap.innerHTML =
                '<img src="' + data.url + '?t=' + Date.now() + '" alt="" class="w-full h-full object-cover vcat-photo">' +
                '<button type="button" class="absolute top-2 right-2 px-2 py-1 rounded-md bg-white/90 border border-slate-200 text-[11px] font-medium text-slate-600 hover:bg-white shadow-sm" onclick="vcatUpload(' + productId + ')">Cambiar foto</button>';

            card.dataset.hasPhoto = '1';
            const badge = card.querySelector('.badge-foto');
            if (badge) {
                badge.classList.remove('badge-red', 'bg-red-100', 'text-red-800');
                badge.classList.add('badge-green', 'bg-green-100', 'text-green-800');
                badge.textContent = 'Con foto';
            }
            if (window.vcatApplyFilters) window.vcatApplyFilters();
        })
        .catch(function () {
            alert('Error de red al subir la imagen');
        })
        .finally(function () {
            vcatInput.value = '';
            vcatCurrentId = null;
        });
});
</script>
