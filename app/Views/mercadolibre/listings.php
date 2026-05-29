<?php

$listings = is_array($listings ?? null) ? $listings : [];

$statusFilter = (string) ($status_filter ?? '');

$mlCsrf = csrfToken();

$mlListingsBaseUrl = url('/mercadolibre/listings');

$defaultQuantity = (int) (setting('ml_default_quantity', '12') ?? '12');

$bulkExecuteUrl = url('/mercadolibre/listings/actualizar-cantidad');

$visibleListingIds = [];

foreach ($listings as $listingRow) {

    $lid = (int) ($listingRow['id'] ?? 0);

    if ($lid > 0) {

        $visibleListingIds[] = $lid;

    }

}

$visibleListingIdsJson = json_encode($visibleListingIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$mlStatusBadge = static function (string $status): string {

    return match ($status) {

        'active' => 'bg-green-100 text-green-800',

        'paused' => 'bg-amber-100 text-amber-800',

        'closed' => 'bg-red-100 text-red-800',

        default => 'bg-slate-100 text-slate-700',

    };

};

$mlStatusLabel = static function (string $status): string {

    return match ($status) {

        'active' => 'Activo',

        'paused' => 'Pausado',

        'closed' => 'Cerrado',

        default => 'Borrador',

    };

};

?>

<div class="space-y-5" x-data="mlListingsPage(<?= e($visibleListingIdsJson ?: '[]') ?>)">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex flex-wrap gap-2">

            <a href="<?= e(url('/mercadolibre/listings')) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $statusFilter === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Todos</a>

            <?php foreach (['draft' => 'Borrador', 'active' => 'Activo', 'paused' => 'Pausado', 'closed' => 'Cerrado'] as $st => $label): ?>

                <a href="<?= e(url('/mercadolibre/listings?status=' . $st)) ?>" class="px-3 h-8 rounded-full inline-flex items-center text-xs font-semibold <?= $statusFilter === $st ? 'bg-lo-blue text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>"><?= e($label) ?></a>

            <?php endforeach; ?>

        </div>

        <div class="flex flex-wrap gap-2">

            <?php $uiBtnHref = url('/mercadolibre/listings/nueva'); $uiBtnLabel = 'Nuevo listing'; require APP_PATH . '/Views/layout/partials/ui-btn-primary.php'; ?>

            <form id="ml-listings-sync-all" method="post" action="<?= e(url('/mercadolibre/listings/sync-all')) ?>">

                <?= csrfField() ?>

                <button type="button" @click="syncConfirm = true" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">

                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>Sincronizar todos

                </button>

            </form>

        </div>

    </div>



    <section x-show="selectedCount > 0" x-cloak class="lo-card p-5 border-l-4 border-l-lo-blue space-y-4">

        <h3 class="text-sm font-semibold text-slate-800">Actualización masiva de cantidad</h3>

        <div class="flex flex-wrap items-end gap-4">

            <label class="block">

                <span class="text-sm text-slate-600">Nueva cantidad</span>

                <input type="number" min="1" x-model.number="bulkQuantity"

                       class="mt-1 block w-32 rounded-lg border border-lo-border px-3 py-2 text-sm focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue">

            </label>

            <label class="inline-flex items-center gap-2 cursor-pointer select-none pb-2">

                <input type="checkbox"

                       :checked="allVisibleSelected"

                       @change="toggleSelectAllVisible()"

                       class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">

                <span class="text-sm text-slate-700">Seleccionar todos los visibles</span>

            </label>

            <button type="button"

                    @click="startBulkUpdate()"

                    :disabled="bulkRunning || selectedCount === 0"

                    class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">

                <i data-lucide="package" class="h-4 w-4" :class="bulkRunning ? 'animate-pulse' : ''"></i>

                <span x-text="bulkRunning ? 'Actualizando…' : 'Actualizar cantidad en seleccionados'"></span>

            </button>

            <span class="text-xs text-slate-500 pb-2">

                <span class="font-semibold text-lo-blue" x-text="selectedCount"></span> seleccionado(s)

            </span>

        </div>



        <div x-show="bulkRunning || bulkLog.length > 0" x-cloak class="pt-4 border-t border-lo-border space-y-3">

            <div class="flex items-center justify-between gap-3">

                <h4 class="text-sm font-semibold text-slate-800">Progreso</h4>

                <span class="text-xs text-slate-500" x-show="bulkRunning" x-text="bulkProgressLabel"></span>

            </div>

            <div class="h-2 rounded-full bg-slate-100 overflow-hidden" x-show="bulkProgressTotal > 0">

                <div class="h-full bg-lo-blue transition-all duration-300" :style="'width:' + bulkProgressPct + '%'"></div>

            </div>

            <div class="max-h-56 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs font-mono space-y-1">

                <template x-for="(entry, idx) in bulkLog" :key="'bulk-qty-' + idx">

                    <div class="flex flex-wrap gap-x-2" :class="entry.status === 'ok' ? 'text-green-700' : 'text-red-700'">

                        <span x-text="'#' + entry.listing_id"></span>

                        <span x-text="entry.product_name || entry.listing_title"></span>

                        <span x-text="'→ ' + entry.message"></span>

                    </div>

                </template>

            </div>

            <div x-show="bulkSummary" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">

                <p class="font-semibold">Actualización finalizada</p>

                <p class="mt-1" x-text="bulkSummaryText"></p>

            </div>

        </div>

    </section>



    <div class="lo-table-wrap">

        <table class="min-w-full text-sm lo-table">

            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">

                <tr>

                    <th class="px-3 py-3 w-10"></th>

                    <th class="w-14 px-3 py-3"></th>

                    <th class="text-left px-4 py-3">Producto</th>

                    <th class="text-left px-4 py-3 min-w-[10rem]">Título ML</th>

                    <th class="text-right px-3 py-3 w-20">Markup %</th>

                    <th class="text-right px-4 py-3">Precio</th>

                    <th class="text-center px-3 py-3 w-16">Qty</th>

                    <th class="text-center px-4 py-3">Fotos en ML</th>

                    <th class="text-left px-4 py-3">Estado</th>

                    <th class="text-left px-4 py-3">Última sync</th>

                    <th class="text-right px-4 py-3">Acciones</th>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-100">

                <?php foreach ($listings as $l): ?>

                    <?php

                    $id = (int) ($l['id'] ?? 0);

                    $status = (string) ($l['status'] ?? 'draft');

                    $permalink = trim((string) ($l['ml_permalink'] ?? ''));

                    $syncError = trim((string) ($l['last_sync_error'] ?? ''));

                    $mlItemId = trim((string) ($l['ml_item_id'] ?? ''));

                    $mlPictures = $l['ml_pictures_count'] ?? null;

                    $mlPicturesInt = $mlPictures !== null && $mlPictures !== '' ? (int) $mlPictures : null;

                    $canDelete = $status === 'draft' || $status === 'closed' || $mlItemId === '';

                    $productId = (int) ($l['product_id'] ?? 0);

                    $coverFilename = trim((string) ($l['cover_filename'] ?? ''));
                    $mlThumbnail = trim((string) ($l['ml_thumbnail'] ?? ''));
                    $localCoverUrl = ($productId > 0 && $coverFilename !== '')
                        ? productImageUrl($productId, $coverFilename)
                        : '';
                    if ($mlThumbnail !== '') {
                        $thumbUrl = $mlThumbnail;
                        $thumbTitle = 'Foto en MercadoLibre';
                    } elseif ($localCoverUrl !== '') {
                        $thumbUrl = $localCoverUrl;
                        $thumbTitle = 'Foto local del producto';
                    } else {
                        $thumbUrl = '';
                        $thumbTitle = 'Sin foto';
                    }

                    $markupVal = $l['ml_markup'] ?? null;

                    $markupDisplay = $markupVal !== null && $markupVal !== ''

                        ? rtrim(rtrim(number_format((float) $markupVal, 2, ',', ''), '0'), ',')

                        : '';

                    $rowConfig = [

                        'id' => $id,

                        'csrf' => $mlCsrf,

                        'baseUrl' => $mlListingsBaseUrl,

                        'title' => (string) ($l['title'] ?? ''),

                        'markup' => $markupDisplay,

                        'qty' => (int) ($l['available_quantity_override'] ?? 12),

                        'priceFormatted' => formatPrice((float) ($l['price'] ?? 0)),

                        'mlItemId' => $mlItemId,

                        'mlPictures' => $mlPicturesInt,

                        'status' => $status,

                        'statusLabel' => $mlStatusLabel($status),

                        'statusBadge' => $mlStatusBadge($status),

                    ];

                    ?>

                    <tr

                        class="hover:bg-gray-50"

                        :class="$parent.selectedIds.includes(<?= $id ?>) ? 'bg-lo-blueSoft/40' : ''"

                        x-data="mlListingRow(<?= e(json_encode($rowConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>)"
                        @click.outside="if (editing) commitEdit()"

                    >

                        <td class="px-3 py-3 align-middle">

                            <input type="checkbox"

                                   :checked="$parent.selectedIds.includes(<?= $id ?>)"

                                   @change="$parent.toggleListing(<?= $id ?>)"

                                   class="rounded border-lo-border text-lo-blue focus:ring-lo-blue/30">

                        </td>

                        <td class="px-3 py-3 align-middle">

                            <div class="relative w-12 h-12 shrink-0">

                                <?php if ($thumbUrl !== ''): ?>
                                    <img
                                        src="<?= e($thumbUrl) ?>"
                                        alt=""
                                        title="<?= e($thumbTitle) ?>"
                                        class="w-12 h-12 rounded-lg object-cover border border-slate-200 bg-white"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-slate-200 border border-slate-300" title="<?= e($thumbTitle) ?>"></div>
                                <?php endif; ?>

                                <span

                                    x-show="syncing || saving"

                                    x-cloak

                                    class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80"

                                >

                                    <svg class="animate-spin h-5 w-5 text-lo-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">

                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>

                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>

                                    </svg>

                                </span>

                            </div>

                        </td>

                        <td class="px-4 py-3">

                            <p class="font-medium text-slate-800"><?= e((string) ($l['product_name'] ?? '—')) ?></p>

                            <?php if (!empty($l['product_code'])): ?>

                                <p class="text-xs text-slate-500"><?= e((string) $l['product_code']) ?></p>

                            <?php endif; ?>

                        </td>

                        <td class="px-4 py-3 min-w-[10rem]">

                            <template x-if="editing !== 'title'">

                                <p

                                    class="text-slate-800 cursor-pointer hover:bg-slate-100 rounded px-1 -mx-1"

                                    @click="startEdit('title')"

                                    x-text="title"

                                    title="Clic para editar"

                                ></p>

                            </template>

                            <input

                                x-show="editing === 'title'"

                                x-cloak

                                type="text"

                                maxlength="60"

                                class="w-full rounded border border-lo-border px-2 py-1 text-sm"

                                x-model="title"

                                x-ref="titleInput"

                                @keydown.enter.prevent="commitEdit()"

                                @keydown.escape.prevent="cancelEdit()"

                            >

                            <p x-show="inlineError" x-cloak class="text-xs text-red-600 mt-1" x-text="inlineError"></p>

                            <?php if ($syncError !== ''): ?>

                                <p class="text-xs text-red-600 mt-1 sync-static-error"><?= e($syncError) ?></p>

                            <?php endif; ?>

                        </td>

                        <td class="px-3 py-3 text-right">

                            <template x-if="editing !== 'markup'">

                                <span

                                    class="cursor-pointer hover:bg-slate-100 rounded px-1 font-medium text-slate-700"

                                    @click="startEdit('markup')"

                                    x-text="markup !== '' ? markup + '%' : '—'"

                                    title="Clic para editar markup"

                                ></span>

                            </template>

                            <input

                                x-show="editing === 'markup'"

                                x-cloak

                                type="text"

                                inputmode="decimal"

                                class="w-16 rounded border border-lo-border px-2 py-1 text-sm text-right"

                                x-model="markup"

                                x-ref="markupInput"

                                placeholder="75"

                                @keydown.enter.prevent="commitEdit()"

                                @keydown.escape.prevent="cancelEdit()"

                            >

                        </td>

                        <td class="px-4 py-3 text-right font-medium whitespace-nowrap" x-text="priceFormatted"></td>

                        <td class="px-3 py-3 text-center">

                            <template x-if="editing !== 'qty'">

                                <span

                                    class="cursor-pointer hover:bg-slate-100 rounded px-1 font-medium"

                                    @click="startEdit('qty')"

                                    x-text="qty"

                                    title="Clic para editar cantidad"

                                ></span>

                            </template>

                            <input

                                x-show="editing === 'qty'"

                                x-cloak

                                type="number"

                                min="1"

                                class="w-14 rounded border border-lo-border px-2 py-1 text-sm text-center"

                                x-model.number="qty"

                                x-ref="qtyInput"

                                @keydown.enter.prevent="commitEdit()"

                                @keydown.escape.prevent="cancelEdit()"

                            >

                        </td>

                        <td class="px-4 py-3 text-center">

                            <template x-if="!mlItemId">

                                <span class="text-slate-400">—</span>

                            </template>

                            <template x-if="mlItemId && mlPictures === null">

                                <span class="text-slate-500 text-xs">?</span>

                            </template>

                            <span x-show="mlItemId && mlPictures !== null && mlPictures >= 2" x-cloak class="inline-flex items-center justify-center gap-1" title="Al menos 2 fotos en ML">
                                <i data-lucide="check-circle" class="h-4 w-4 text-green-600"></i>
                                <span class="text-xs font-semibold text-green-700" x-text="mlPictures"></span>
                            </span>
                            <span x-show="mlItemId && mlPictures !== null && mlPictures < 2" x-cloak class="inline-flex items-center justify-center gap-1" title="Menos de 2 fotos en ML">
                                <i data-lucide="alert-circle" class="h-4 w-4 text-red-600"></i>
                                <span class="text-xs font-semibold text-red-700" x-text="mlPictures"></span>
                            </span>

                        </td>

                        <td class="px-4 py-3">

                            <span

                                class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold"

                                :class="statusBadge"

                                x-text="statusLabel"

                            ></span>

                        </td>

                        <td class="px-4 py-3 text-slate-600"><?= e((string) ($l['last_synced_at'] ?? '—')) ?></td>

                        <td class="px-4 py-3 text-right whitespace-nowrap">

                            <div class="inline-flex flex-wrap justify-end gap-2">

                                <a href="<?= e(url('/mercadolibre/listings/' . $id . '/editar')) ?>" class="text-lo-blue hover:underline text-xs font-semibold">Editar</a>

                                <?php if ($status === 'draft'): ?>

                                    <form method="post" action="<?= e(url('/mercadolibre/listings/' . $id . '/publicar')) ?>" class="inline">

                                        <?= csrfField() ?>

                                        <button type="submit" class="text-green-700 hover:underline text-xs font-semibold">Publicar</button>

                                    </form>

                                <?php elseif ($status === 'active'): ?>

                                    <form method="post" action="<?= e(url('/mercadolibre/listings/' . $id . '/sync')) ?>" class="inline">

                                        <?= csrfField() ?>

                                        <button type="submit" class="text-lo-blue hover:underline text-xs font-semibold">Sync</button>

                                    </form>

                                    <button
                                        type="button"
                                        class="text-amber-700 hover:underline text-xs font-semibold"
                                        @click="pauseListingId = <?= $id ?>; pauseConfirm = true"
                                    >Pausar</button>

                                    <?php if ($permalink !== ''): ?>

                                        <a href="<?= e($permalink) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 text-slate-600 hover:underline text-xs font-semibold">

                                            Ver en ML <i data-lucide="external-link" class="h-3 w-3"></i>

                                        </a>

                                    <?php endif; ?>

                                <?php elseif ($status === 'paused'): ?>

                                    <form method="post" action="<?= e(url('/mercadolibre/listings/' . $id . '/activar')) ?>" class="inline">

                                        <?= csrfField() ?>

                                        <button type="submit" class="text-green-700 hover:underline text-xs font-semibold">Reactivar</button>

                                    </form>

                                    <?php if ($permalink !== ''): ?>

                                        <a href="<?= e($permalink) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 text-slate-600 hover:underline text-xs font-semibold">

                                            Ver en ML <i data-lucide="external-link" class="h-3 w-3"></i>

                                        </a>

                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php if ($canDelete): ?>

                                    <button

                                        type="button"

                                        class="text-red-700 hover:underline text-xs font-semibold"

                                        @click="deleteListingId = <?= $id ?>; deleteConfirm = true"

                                    >Eliminar</button>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if ($listings === []): ?>

                    <tr>

                        <td colspan="11" class="px-4 py-10 text-center text-slate-500">

                            No hay listings<?= $statusFilter !== '' ? ' con este estado' : '' ?>.

                            <a href="<?= e(url('/mercadolibre/listings/nueva')) ?>" class="text-lo-blue font-semibold hover:underline">Crear el primero</a>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>



    <?php require APP_PATH . '/Views/layout/pagination.php'; ?>



    <form id="ml-pause-listing-form" method="post" action="#" class="hidden">
        <?= csrfField() ?>
    </form>

    <form id="ml-delete-listing-form" method="post" action="#" class="hidden">

        <?= csrfField() ?>

    </form>



    <div x-show="syncConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="syncConfirm = false">

        <div class="w-full max-w-md rounded-2xl bg-white border border-lo-border shadow-xl p-5" @click.outside="syncConfirm = false">

            <h4 class="text-base font-semibold text-slate-900">Sincronizar todos</h4>

            <p class="mt-2 text-sm text-slate-600">Se sincronizarán todos los listings activos y pausados con MercadoLibre.</p>

            <div class="mt-5 flex justify-end gap-2">

                <button type="button" @click="syncConfirm = false" class="px-4 py-2 rounded-lg border border-lo-border text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>

                <button type="button" @click="document.getElementById('ml-listings-sync-all').submit()" class="px-4 py-2 rounded-lg bg-lo-blue text-sm font-semibold text-white hover:bg-blue-700">Confirmar</button>

            </div>

        </div>

    </div>



    <div x-show="pauseConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="pauseConfirm = false">
        <div class="w-full max-w-md rounded-2xl bg-white border border-lo-border shadow-xl p-5" @click.outside="pauseConfirm = false">
            <h4 class="text-base font-semibold text-slate-900">Pausar publicación</h4>
            <p class="mt-2 text-sm text-slate-600">¿Pausar esta publicación? La publicación quedará invisible temporalmente pero mantendrá su historial.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="pauseConfirm = false" class="px-4 py-2 rounded-lg border border-lo-border text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg bg-amber-600 text-sm font-semibold text-white hover:bg-amber-700"
                    @click="
                        if (pauseListingId) {
                            const f = document.getElementById('ml-pause-listing-form');
                            f.action = '<?= e(url('/mercadolibre/listings/')) ?>/' + pauseListingId + '/pausar';
                            f.submit();
                        }
                        pauseConfirm = false;
                    "
                >Pausar</button>
            </div>
        </div>
    </div>

    <div x-show="deleteConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="deleteConfirm = false">
        <div class="w-full max-w-md rounded-2xl bg-white border border-lo-border shadow-xl p-5" @click.outside="deleteConfirm = false">
            <h4 class="text-base font-semibold text-slate-900">Eliminar listing</h4>
            <p class="mt-2 text-sm text-slate-600">¿Eliminar este listing de forma permanente? Esta acción no se puede deshacer. Si la publicación existe en MercadoLibre, se cerrará (<strong>closed</strong>) y se borrará definitivamente — a diferencia de pausar, no podrás reactivarla.</p>

            <div class="mt-5 flex justify-end gap-2">

                <button type="button" @click="deleteConfirm = false" class="px-4 py-2 rounded-lg border border-lo-border text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>

                <button

                    type="button"

                    class="px-4 py-2 rounded-lg bg-red-600 text-sm font-semibold text-white hover:bg-red-700"

                    @click="

                        if (deleteListingId) {

                            const f = document.getElementById('ml-delete-listing-form');

                            f.action = '<?= e(url('/mercadolibre/listings/')) ?>/' + deleteListingId + '/eliminar';

                            f.submit();

                        }

                        deleteConfirm = false;

                    "

                >Eliminar</button>

            </div>

        </div>

    </div>

</div>



<script>

document.addEventListener('alpine:init', () => {

    Alpine.data('mlListingsPage', (visibleIds) => ({

        syncConfirm: false,

        deleteConfirm: false,

        deleteListingId: null,

        pauseConfirm: false,

        pauseListingId: null,

        visibleIds: Array.isArray(visibleIds) ? visibleIds : [],

        selectedIds: [],

        bulkQuantity: <?= (int) $defaultQuantity ?>,

        bulkRunning: false,

        bulkProgressCurrent: 0,

        bulkProgressTotal: 0,

        bulkLog: [],

        bulkSummary: null,

        csrf: <?= json_encode($mlCsrf, JSON_UNESCAPED_UNICODE) ?>,

        bulkExecuteUrl: <?= json_encode($bulkExecuteUrl, JSON_UNESCAPED_UNICODE) ?>,

        get selectedCount() {

            return this.selectedIds.length;

        },

        get allVisibleSelected() {

            if (this.visibleIds.length === 0) return false;

            return this.visibleIds.every((id) => this.selectedIds.includes(id));

        },

        get bulkProgressPct() {

            if (this.bulkProgressTotal <= 0) return 0;

            return Math.round((this.bulkProgressCurrent / this.bulkProgressTotal) * 100);

        },

        get bulkProgressLabel() {

            return this.bulkProgressCurrent + ' / ' + this.bulkProgressTotal;

        },

        get bulkSummaryText() {

            if (!this.bulkSummary) return '';

            return this.bulkSummary.ok + ' actualizado(s), ' + this.bulkSummary.errors + ' error(es)';

        },

        toggleListing(id) {

            const idx = this.selectedIds.indexOf(id);

            if (idx >= 0) {

                this.selectedIds.splice(idx, 1);

            } else {

                this.selectedIds.push(id);

            }

        },

        toggleSelectAllVisible() {

            if (this.allVisibleSelected) {

                const visSet = new Set(this.visibleIds);

                this.selectedIds = this.selectedIds.filter((id) => !visSet.has(id));

            } else {

                this.visibleIds.forEach((id) => {

                    if (!this.selectedIds.includes(id)) {

                        this.selectedIds.push(id);

                    }

                });

            }

        },

        async startBulkUpdate() {

            if (this.bulkRunning || this.selectedIds.length === 0) return;

            const qty = Math.max(1, parseInt(String(this.bulkQuantity), 10) || 1);

            if (!confirm('¿Actualizar cantidad a ' + qty + ' en ' + this.selectedIds.length + ' listing(s)?')) return;

            this.bulkRunning = true;

            this.bulkLog = [];

            this.bulkSummary = null;

            this.bulkProgressCurrent = 0;

            this.bulkProgressTotal = this.selectedIds.length;

            const body = new URLSearchParams();

            body.set('_csrf', this.csrf);

            body.set('listing_ids', JSON.stringify(this.selectedIds));

            body.set('available_quantity', String(qty));

            try {

                const res = await fetch(this.bulkExecuteUrl, {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/x-www-form-urlencoded',

                        'Accept': 'application/x-ndjson',

                    },

                    body: body.toString(),

                });

                if (!res.ok || !res.body) {

                    throw new Error('Error HTTP ' + res.status);

                }

                const reader = res.body.getReader();

                const decoder = new TextDecoder();

                let buffer = '';

                while (true) {

                    const { done, value } = await reader.read();

                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    const lines = buffer.split('\n');

                    buffer = lines.pop() || '';

                    for (const line of lines) {

                        if (!line.trim()) continue;

                        let data;

                        try {

                            data = JSON.parse(line);

                        } catch (e) {

                            continue;

                        }

                        this.handleBulkStreamEvent(data);

                    }

                }

                if (buffer.trim()) {

                    try {

                        this.handleBulkStreamEvent(JSON.parse(buffer));

                    } catch (e) {}

                }

            } catch (e) {

                this.bulkLog.push({

                    listing_id: 0,

                    listing_title: '',

                    product_name: '',

                    status: 'error',

                    message: e.message || 'Error de red',

                });

            } finally {

                this.bulkRunning = false;

                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());

            }

        },

        handleBulkStreamEvent(data) {

            if (data.type === 'start') {

                this.bulkProgressTotal = Number(data.total || this.selectedIds.length);

                return;

            }

            if (data.type === 'progress') {

                this.bulkProgressCurrent = Number(data.index || 0);

                this.bulkLog.push({

                    listing_id: data.listing_id,

                    listing_title: data.listing_title || '',

                    product_name: data.product_name || '',

                    status: data.status,

                    message: data.message || '',

                });

                if (data.status === 'ok' && data.listing_id) {

                    window.dispatchEvent(new CustomEvent('ml-bulk-qty-updated', {

                        detail: { id: data.listing_id, qty: data.quantity },

                    }));

                }

                return;

            }

            if (data.type === 'done') {

                this.bulkSummary = { ok: data.ok || 0, errors: data.errors || 0 };

                this.bulkProgressCurrent = this.bulkProgressTotal;

                return;

            }

            if (data.type === 'error') {

                this.bulkLog.push({

                    listing_id: 0,

                    listing_title: '',

                    product_name: '',

                    status: 'error',

                    message: data.error || 'Error',

                });

            }

        },

    }));

});



function mlListingRow(config) {

    const statusMap = {

        active: { label: 'Activo', badge: 'bg-green-100 text-green-800' },

        paused: { label: 'Pausado', badge: 'bg-amber-100 text-amber-800' },

        closed: { label: 'Cerrado', badge: 'bg-red-100 text-red-800' },

        draft: { label: 'Borrador', badge: 'bg-slate-100 text-slate-700' },

    };



    return {

        id: config.id,

        csrf: config.csrf,

        baseUrl: config.baseUrl,

        title: config.title,

        markup: config.markup,

        qty: config.qty,

        priceFormatted: config.priceFormatted,

        mlItemId: config.mlItemId || '',

        mlPictures: config.mlPictures,

        status: config.status,

        statusLabel: config.statusLabel,

        statusBadge: config.statusBadge,

        editing: null,

        draft: {},

        saving: false,

        syncing: false,

        inlineError: '',



        init() {

            this._bulkQtyHandler = (e) => {

                if (e.detail && e.detail.id === this.id && e.detail.qty != null) {

                    this.qty = e.detail.qty;

                }

            };

            window.addEventListener('ml-bulk-qty-updated', this._bulkQtyHandler);

        },

        destroy() {

            if (this._bulkQtyHandler) {

                window.removeEventListener('ml-bulk-qty-updated', this._bulkQtyHandler);

            }

        },



        startEdit(field) {

            if (this.saving || this.syncing) return;

            this.draft = { title: this.title, markup: this.markup, qty: this.qty };

            this.editing = field;

            this.inlineError = '';

            this.$nextTick(() => {

                const ref = this.$refs[field + 'Input'];

                if (ref) {

                    ref.focus();

                    if (typeof ref.select === 'function') ref.select();

                }

            });

        },



        cancelEdit() {
            if (this.draft.title !== undefined) this.title = this.draft.title;
            if (this.draft.markup !== undefined) this.markup = this.draft.markup;
            if (this.draft.qty !== undefined) this.qty = this.draft.qty;
            this.editing = null;
        },



        isDirty() {

            return this.title !== this.draft.title

                || String(this.markup) !== String(this.draft.markup)

                || Number(this.qty) !== Number(this.draft.qty);

        },



        async commitEdit() {

            if (!this.editing) return;

            if (!this.isDirty()) {

                this.editing = null;

                return;

            }

            this.editing = null;

            this.inlineError = '';

            const staticErr = this.$el.querySelector('.sync-static-error');

            if (staticErr) staticErr.remove();



            this.saving = true;

            try {

                const save = await this.postForm(this.baseUrl + '/' + this.id, {

                    title: this.title,

                    ml_markup: this.markup,

                    available_quantity_override: String(this.qty),

                });

                if (!save.success) {

                    this.inlineError = save.error || 'No se pudo guardar.';

                    return;

                }

                this.title = save.title ?? this.title;

                this.markup = save.ml_markup_display ?? (save.ml_markup != null ? String(save.ml_markup) : '');

                this.qty = save.available_quantity_override ?? this.qty;

                this.priceFormatted = save.price_formatted ?? this.priceFormatted;



                if (this.mlItemId) {

                    this.syncing = true;

                    const sync = await this.postForm(this.baseUrl + '/' + this.id + '/sync', {});

                    this.syncing = false;

                    if (!sync.success) {

                        this.inlineError = sync.error || 'Error al sincronizar con ML.';

                        return;

                    }

                    this.applySync(sync);

                }

            } catch (e) {

                this.inlineError = 'Error de red al guardar.';

            } finally {

                this.saving = false;

                this.syncing = false;

                if (typeof lucide !== 'undefined') lucide.createIcons();

            }

        },



        applySync(sync) {

            if (sync.price_formatted) this.priceFormatted = sync.price_formatted;

            if (sync.ml_pictures_count !== undefined && sync.ml_pictures_count !== null) {

                this.mlPictures = sync.ml_pictures_count;

            }

            if (sync.status) {

                this.status = sync.status;

                const m = statusMap[sync.status] || statusMap.draft;

                this.statusLabel = m.label;

                this.statusBadge = m.badge;

            }

            if (sync.ml_not_found) {

                this.inlineError = 'El ítem ya no existe en ML; listing marcado como cerrado.';

            } else if (sync.last_sync_error) {

                this.inlineError = sync.last_sync_error;

            }

        },



        async postForm(url, fields) {

            const body = new URLSearchParams();

            body.set('_csrf', this.csrf);

            body.set('inline', '1');

            Object.entries(fields).forEach(([k, v]) => body.set(k, v));

            const res = await fetch(url, {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/x-www-form-urlencoded',

                    'X-Requested-With': 'XMLHttpRequest',

                    'Accept': 'application/json',

                },

                body: body.toString(),

                credentials: 'same-origin',

            });

            return res.json();

        },

    };

}

</script>


