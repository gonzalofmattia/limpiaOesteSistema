<?php

$listings = is_array($listings ?? null) ? $listings : [];

$statusFilter = (string) ($status_filter ?? '');

$mlCsrf = csrfToken();

$mlListingsBaseUrl = url('/mercadolibre/listings');

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

<div class="space-y-5" x-data="{ syncConfirm: false, deleteConfirm: false, deleteListingId: null }">

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



    <div class="lo-table-wrap">

        <table class="min-w-full text-sm lo-table">

            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">

                <tr>

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

                        x-data="mlListingRow(<?= e(json_encode($rowConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>)"
                        @click.outside="if (editing) commitEdit()"

                    >

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

                                    <form method="post" action="<?= e(url('/mercadolibre/listings/' . $id . '/pausar')) ?>" class="inline">

                                        <?= csrfField() ?>

                                        <button type="submit" class="text-amber-700 hover:underline text-xs font-semibold">Pausar</button>

                                    </form>

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

                        <td colspan="10" class="px-4 py-10 text-center text-slate-500">

                            No hay listings<?= $statusFilter !== '' ? ' con este estado' : '' ?>.

                            <a href="<?= e(url('/mercadolibre/listings/nueva')) ?>" class="text-lo-blue font-semibold hover:underline">Crear el primero</a>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>



    <?php require APP_PATH . '/Views/layout/pagination.php'; ?>



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



    <div x-show="deleteConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="deleteConfirm = false">

        <div class="w-full max-w-md rounded-2xl bg-white border border-lo-border shadow-xl p-5" @click.outside="deleteConfirm = false">

            <h4 class="text-base font-semibold text-slate-900">Eliminar listing</h4>

            <p class="mt-2 text-sm text-slate-600">¿Eliminar este listing? Si fue publicado en ML, se cerrará y borrará permanentemente.</p>

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


