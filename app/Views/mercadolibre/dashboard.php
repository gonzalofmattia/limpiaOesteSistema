<?php
$statusCounts = is_array($status_counts ?? null) ? $status_counts : [];
$activeListings = is_array($active_listings ?? null) ? $active_listings : [];
$activeSyncErrors = is_array($active_sync_errors ?? null) ? $active_sync_errors : [];
$connected = !empty($connected);
$mlUserId = trim((string) ($ml_user_id ?? ''));
?>
<div class="space-y-6" x-data="{
    syncConfirm: false,
    syncRunning: false,
    syncIndex: 0,
    syncTotal: 0,
    syncLog: [],
    syncSummary: null,
    syncError: null,
    async runSyncAll() {
        this.syncRunning = true;
        this.syncIndex = 0;
        this.syncTotal = 0;
        this.syncLog = [];
        this.syncSummary = null;
        this.syncError = null;
        const csrfInput = document.querySelector(&quot;#ml-sync-all-form input[name='_csrf']&quot;);
        const body = new URLSearchParams();
        body.set('_csrf', csrfInput ? csrfInput.value : '');
        try {
            const res = await fetch('<?= e(url('/mercadolibre/listings/sync-all')) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/x-ndjson' },
                body: body.toString(),
            });
            if (!res.ok || !res.body) { throw new Error('Error HTTP ' + res.status); }
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            const handle = (data) => {
                if (data.type === 'start') { this.syncTotal = Number(data.total || 0); return; }
                if (data.type === 'progress') {
                    this.syncIndex = Number(data.index || this.syncIndex);
                    if (data.blocked) {
                        this.syncLog.push('#' + data.listing_id + ' bloqueado: ' + (data.block_reason || ''));
                    } else if (data.fields) {
                        const changed = Object.entries(data.fields).filter(([, v]) => v !== 'no_change');
                        if (changed.length > 0) {
                            this.syncLog.push('#' + data.listing_id + ': ' + changed.map(([k, v]) => k + '=' + v).join(', '));
                        }
                    }
                    return;
                }
                if (data.type === 'done') { this.syncSummary = data; return; }
                if (data.type === 'error') { this.syncError = data.error || 'Error desconocido'; }
            };
            while (true) {
                const chunk = await reader.read();
                if (chunk.done) break;
                buffer += decoder.decode(chunk.value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                lines.forEach((line) => { if (!line.trim()) return; try { handle(JSON.parse(line)); } catch (e) {} });
            }
            if (buffer.trim()) { try { handle(JSON.parse(buffer)); } catch (e) {} }
        } catch (e) {
            this.syncError = e.message || 'Error de red';
        } finally {
            this.syncRunning = false;
        }
    },
}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Integración API</p>
            <h2 class="text-xl font-semibold text-slate-900">Panel MercadoLibre</h2>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(url('/mercadolibre/listings')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i data-lucide="list" class="h-4 w-4"></i>Listings
            </a>
            <?php if ($connected): ?>
                <a href="<?= e(url('/mercadolibre/vincular-existentes')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-800 hover:bg-violet-100">
                    <i data-lucide="link-2" class="h-4 w-4"></i>Vincular publicaciones existentes
                </a>
            <?php endif; ?>
            <a href="<?= e(url('/mercadolibre/ordenes')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i data-lucide="shopping-bag" class="h-4 w-4"></i>Órdenes
            </a>
            <?php if ($connected): ?>
                <a href="<?= e(url('/mercadolibre/publicacion-masiva')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-blue/30 bg-lo-blueSoft px-4 py-2 text-sm font-medium text-lo-blue hover:bg-blue-100">
                    <i data-lucide="upload-cloud" class="h-4 w-4"></i>Publicación masiva
                </a>
            <?php endif; ?>
            <a href="<?= e(url('/mercadolibre/importar-imagenes')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i data-lucide="image-down" class="h-4 w-4"></i>Importar imágenes
            </a>
            <?php if ($connected): ?>
                <a href="<?= e(url('/mercadolibre/precios-competencia')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                    <i data-lucide="trending-up" class="h-4 w-4"></i>Análisis de precios
                </a>
            <?php endif; ?>
            <?php if ($connected): ?>
                <form method="post" action="<?= e(url('/mercadolibre/desconectar')) ?>" class="inline">
                    <?= csrfField() ?>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                        <i data-lucide="unlink" class="h-4 w-4"></i>Desconectar
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/mercadolibre/conectar')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="link" class="h-4 w-4"></i>Conectar cuenta
                </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="lo-card p-5 border-l-4 <?= $connected ? 'border-l-green-500 bg-green-50/40' : 'border-l-red-500 bg-red-50/40' ?>">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="h-11 w-11 rounded-xl grid place-items-center <?= $connected ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <i data-lucide="<?= $connected ? 'check-circle' : 'alert-circle' ?>" class="h-5 w-5"></i>
                </span>
                <div>
                    <h3 class="text-sm font-semibold <?= $connected ? 'text-green-800' : 'text-red-800' ?>">
                        <?= $connected ? 'Conectado a MercadoLibre' : 'Sin conectar' ?>
                    </h3>
                    <?php if ($connected && $mlUserId !== ''): ?>
                        <p class="mt-1 text-sm text-slate-600">Usuario ML: <span class="font-medium"><?= e($mlUserId) ?></span></p>
                    <?php elseif ($connected): ?>
                        <p class="mt-1 text-sm text-slate-600">Sesión OAuth activa.</p>
                    <?php else: ?>
                        <p class="mt-1 text-sm text-slate-600">Conectá la cuenta vendedora para publicar y sincronizar listings.</p>
                    <?php endif; ?>
                    <?php if (!empty($last_synced_at)): ?>
                        <p class="mt-1 text-xs text-slate-500">Última sincronización global: <?= e((string) $last_synced_at) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($connected): ?>
                <form id="ml-sync-all-form" method="post" action="<?= e(url('/mercadolibre/listings/sync-all')) ?>">
                    <?= csrfField() ?>
                    <button type="button" @click="syncConfirm = true" class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>Sincronizar todos
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($activeSyncErrors !== []): ?>
        <section class="rounded-2xl border border-red-200 border-l-4 border-l-red-500 bg-red-50 p-4">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="h-5 w-5 text-red-600"></i>
                <h3 class="text-sm font-semibold text-red-800">
                    <?= count($activeSyncErrors) ?> listing(s) activo(s) con error de sincronización
                </h3>
            </div>
            <ul class="mt-3 space-y-2">
                <?php foreach ($activeSyncErrors as $err): ?>
                    <li class="rounded-lg bg-white/80 border border-red-100 px-3 py-2 text-sm">
                        <span class="font-medium text-slate-800"><?= e((string) ($err['title'] ?? 'Listing')) ?></span>
                        <span class="text-red-700 block mt-0.5"><?= e((string) ($err['last_sync_error'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <?php
        $kpiCards = [
            ['key' => 'draft', 'label' => 'Borradores', 'icon' => 'file-edit', 'tone' => 'text-slate-700 bg-slate-100'],
            ['key' => 'active', 'label' => 'Activos', 'icon' => 'check-circle', 'tone' => 'text-green-700 bg-green-100'],
            ['key' => 'paused', 'label' => 'Pausados', 'icon' => 'pause-circle', 'tone' => 'text-amber-700 bg-amber-100'],
            ['key' => 'closed', 'label' => 'Cerrados', 'icon' => 'x-circle', 'tone' => 'text-red-700 bg-red-100'],
        ];
        foreach ($kpiCards as $card):
            $count = (int) ($statusCounts[$card['key']] ?? 0);
        ?>
            <article class="lo-card p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500"><?= e($card['label']) ?></p>
                    <span class="h-8 w-8 rounded-lg grid place-items-center <?= e($card['tone']) ?>">
                        <i data-lucide="<?= e($card['icon']) ?>" class="h-4 w-4"></i>
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-slate-900"><?= $count ?></p>
                <a href="<?= e(url('/mercadolibre/listings?status=' . $card['key'])) ?>" class="mt-2 inline-flex text-xs font-semibold text-lo-blue hover:underline">Ver listings</a>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="lo-card overflow-hidden">
        <div class="px-4 py-3 border-b border-lo-border flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-800">Listings activos</h3>
            <a href="<?= e(url('/mercadolibre/listings/nueva')) ?>" class="text-xs font-semibold text-lo-blue hover:underline">+ Nuevo listing</a>
        </div>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3">Producto</th>
                        <th class="text-left px-4 py-3">Título ML</th>
                        <th class="text-right px-4 py-3">Precio</th>
                        <th class="text-left px-4 py-3">Última sync</th>
                        <th class="text-left px-4 py-3">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($activeListings as $row): ?>
                        <?php $syncError = trim((string) ($row['last_sync_error'] ?? '')); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-slate-700"><?= e((string) ($row['product_name'] ?? '—')) ?></td>
                            <td class="px-4 py-3">
                                <a href="<?= e(url('/mercadolibre/listings/' . (int) ($row['id'] ?? 0) . '/editar')) ?>" class="font-medium text-lo-blue hover:underline">
                                    <?= e((string) ($row['title'] ?? '')) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right"><?= formatPrice((float) ($row['price'] ?? 0)) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e((string) ($row['last_synced_at'] ?? '—')) ?></td>
                            <td class="px-4 py-3 <?= $syncError !== '' ? 'text-red-700 font-medium' : 'text-slate-400' ?>">
                                <?= $syncError !== '' ? e($syncError) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($activeListings === []): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay listings activos todavía.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="syncConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="if (!syncRunning) syncConfirm = false">
        <div class="w-full max-w-md rounded-2xl bg-white border border-lo-border shadow-xl p-5" @click.outside="if (!syncRunning) syncConfirm = false">

            <template x-if="!syncRunning && !syncSummary && !syncError">
                <div>
                    <h4 class="text-base font-semibold text-slate-900">Sincronizar todos los listings activos</h4>
                    <p class="mt-2 text-sm text-slate-600">Se corre el motor de sync bidireccional (título, precio, stock, descripción, categoría e imágenes) sobre los listings activos y pausados. Los campos que cambiaron a la vez en ML y en el sistema quedan como conflicto pendiente, no se pisan solos.</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="syncConfirm = false" class="px-4 py-2 rounded-lg border border-lo-border text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="button" @click="runSyncAll()" class="px-4 py-2 rounded-lg bg-lo-blue text-sm font-semibold text-white hover:bg-blue-700">Sincronizar</button>
                    </div>
                </div>
            </template>

            <template x-if="syncRunning">
                <div>
                    <h4 class="text-base font-semibold text-slate-900">Sincronizando…</h4>
                    <p class="mt-2 text-sm text-slate-600" x-text="(syncTotal > 0 ? syncIndex + ' / ' + syncTotal : 'Iniciando…')"></p>
                    <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full bg-lo-blue transition-all" :style="'width: ' + (syncTotal > 0 ? Math.round((syncIndex / syncTotal) * 100) : 0) + '%'"></div>
                    </div>
                    <div class="mt-3 max-h-40 overflow-y-auto text-xs text-slate-600 space-y-1">
                        <template x-for="(line, i) in syncLog.slice(-30)" :key="i">
                            <div x-text="line"></div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="!syncRunning && (syncSummary || syncError)">
                <div>
                    <h4 class="text-base font-semibold text-slate-900">Sincronización terminada</h4>
                    <template x-if="syncError">
                        <p class="mt-2 text-sm text-red-700" x-text="syncError"></p>
                    </template>
                    <template x-if="syncSummary">
                        <div class="mt-2 text-sm text-slate-700 space-y-1">
                            <p x-text="'Traídos desde ML: ' + syncSummary.pulled + ' · Enviados a ML: ' + syncSummary.pushed + ' · Sin cambios: ' + syncSummary.no_change"></p>
                            <p x-show="syncSummary.conflicts > 0" class="text-amber-700" x-text="syncSummary.conflicts + ' conflicto(s) pendiente(s) de resolución manual.'"></p>
                            <p x-show="syncSummary.blocked > 0" class="text-slate-500" x-text="syncSummary.blocked + ' listing(s) bloqueado(s) (revisar log).'"></p>
                            <p x-show="syncSummary.skipped > 0" class="text-slate-500" x-text="syncSummary.skipped + ' campo(s) de imagen/descripción sin tocar (producto con más de un listing ML).'"></p>
                            <p x-show="syncSummary.errors > 0" class="text-red-700" x-text="syncSummary.errors + ' error(es) (revisar log).'"></p>
                        </div>
                    </template>
                    <div class="mt-5 flex justify-end gap-2">
                        <a x-show="syncSummary && syncSummary.conflicts > 0" href="<?= e(url('/mercadolibre/sync/conflictos')) ?>" class="px-4 py-2 rounded-lg border border-lo-border text-sm font-medium text-slate-700 hover:bg-slate-50">Ver conflictos</a>
                        <button type="button" @click="syncConfirm = false; syncSummary = null; syncError = null;" class="px-4 py-2 rounded-lg bg-lo-blue text-sm font-semibold text-white hover:bg-blue-700">Cerrar</button>
                    </div>
                </div>
            </template>

        </div>
    </div>
</div>
