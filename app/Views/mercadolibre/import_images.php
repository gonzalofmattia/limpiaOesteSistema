<?php
$withoutPhotosCount = (int) ($without_photos_count ?? 0);
$products = is_array($products ?? null) ? $products : [];
$mlCsrf = csrfToken();
$executeUrl = url('/mercadolibre/importar-imagenes/ejecutar');
$seiqExecuteUrl = url('/mercadolibre/importar-imagenes/seiq-ejecutar');
?>
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MercadoLibre</p>
            <h2 class="text-xl font-semibold text-slate-900">Importar imágenes</h2>
            <p class="mt-1 text-sm text-slate-600">
                Importá fotos desde MercadoLibre o desde el sitio oficial de Seiq.
            </p>
        </div>
        <a href="<?= e(url('/mercadolibre')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver al panel
        </a>
    </div>

    <section class="lo-card p-5 border-l-4 border-l-lo-blue" x-data="mlImageImport()">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">MercadoLibre</h3>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-600">Productos activos sin foto</p>
                <p class="text-3xl font-semibold text-slate-900" x-text="withoutPhotos" data-without-photos><?= $withoutPhotosCount ?></p>
            </div>
            <div class="flex flex-wrap items-end gap-4">
                <label class="block">
                    <span class="text-sm text-slate-600">Probar con los primeros</span>
                    <input type="number"
                           min="1"
                           x-model.number="testLimit"
                           class="mt-1 block w-24 rounded-lg border border-lo-border px-3 py-2 text-sm text-slate-800 focus:border-lo-blue focus:outline-none focus:ring-1 focus:ring-lo-blue"
                           placeholder="Todos">
                </label>
                <button type="button"
                        @click="startImport()"
                        :disabled="running || withoutPhotos <= 0"
                        class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="download" class="h-4 w-4" :class="running ? 'animate-pulse' : ''"></i>
                    <span x-text="running ? 'Importando…' : 'Importar fotos desde ML'"></span>
                </button>
            </div>
        </div>

        <div x-show="running || log.length > 0" x-cloak class="mt-5 pt-5 border-t border-lo-border space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-sm font-semibold text-slate-800">Progreso ML</h4>
                <span class="text-xs text-slate-500" x-show="running" x-text="progressLabel"></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden" x-show="total > 0">
                <div class="h-full bg-lo-blue transition-all duration-300" :style="'width:' + progressPct + '%'"></div>
            </div>
            <div class="max-h-48 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs font-mono space-y-1">
                <template x-for="(entry, idx) in log" :key="'ml-' + idx">
                    <div class="flex flex-wrap gap-x-2" :class="entryClass(entry.status)">
                        <span x-text="'#' + entry.product_id"></span>
                        <span x-text="entry.product_name"></span>
                        <span x-text="'→ ' + entry.message"></span>
                    </div>
                </template>
            </div>
            <div x-show="summary" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <p class="font-semibold">Importación ML finalizada</p>
                <p class="mt-1" x-text="summaryText"></p>
            </div>
        </div>
    </section>

    <section class="lo-card p-5 border-l-4 border-l-green-600" x-data="seiqImageImport()">
        <h3 class="text-sm font-semibold text-slate-800 mb-2">Seiq.com.ar</h3>
        <p class="text-sm text-slate-600 mb-4">
            Scrapea los listados oficiales de Seiq y matchea productos por similitud de nombre (≥ 60%).
        </p>
        <button type="button"
                @click="startImport()"
                :disabled="running"
                class="inline-flex items-center gap-2 rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50">
            <i data-lucide="globe" class="h-4 w-4" :class="running ? 'animate-pulse' : ''"></i>
            <span x-text="running ? 'Importando desde Seiq…' : 'Importar desde Seiq.com.ar'"></span>
        </button>

        <div x-show="running || log.length > 0" x-cloak class="mt-5 pt-5 border-t border-lo-border space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-sm font-semibold text-slate-800">Progreso Seiq</h4>
                <span class="text-xs text-slate-500" x-show="running" x-text="progressLabel"></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden" x-show="totalUrls > 0">
                <div class="h-full bg-green-600 transition-all duration-300" :style="'width:' + progressPct + '%'"></div>
            </div>
            <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs font-mono space-y-1">
                <template x-for="(entry, idx) in log" :key="'seiq-' + idx">
                    <div class="flex flex-wrap gap-x-2" :class="entryClass(entry.status)">
                        <span x-text="entry.prefix"></span>
                        <span x-text="entry.message"></span>
                    </div>
                </template>
            </div>
            <div x-show="summary" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <p class="font-semibold">Importación Seiq finalizada</p>
                <p class="mt-1" x-text="summaryText"></p>
            </div>
        </div>
    </section>

    <section class="lo-card overflow-hidden" x-data="{ withoutPhotos: <?= $withoutPhotosCount ?> }">
        <div class="border-b border-lo-border px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">Productos activos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">ID</th>
                        <th class="px-4 py-3 font-semibold">Nombre</th>
                        <th class="px-4 py-3 font-semibold">Estado foto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($products === []): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">No hay productos activos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $row): ?>
                            <?php
                            $pid = (int) ($row['id'] ?? 0);
                            $hasPhoto = (int) ($row['has_photo'] ?? 0) === 1;
                            ?>
                            <tr class="hover:bg-slate-50" data-product-row="<?= $pid ?>">
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600"><?= $pid ?></td>
                                <td class="px-4 py-2.5 text-slate-800"><?= e((string) ($row['name'] ?? '')) ?></td>
                                <td class="px-4 py-2.5">
                                    <?php if ($hasPhoto): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 photo-badge">
                                            <i data-lucide="check" class="h-3 w-3"></i>Tiene foto
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 photo-badge">
                                            <i data-lucide="image-off" class="h-3 w-3"></i>Sin foto
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mlImageImport', () => ({
        csrf: <?= json_encode($mlCsrf, JSON_UNESCAPED_UNICODE) ?>,
        executeUrl: <?= json_encode($executeUrl, JSON_UNESCAPED_UNICODE) ?>,
        withoutPhotos: <?= $withoutPhotosCount ?>,
        testLimit: 10,
        running: false,
        total: 0,
        current: 0,
        log: [],
        summary: null,
        get progressPct() {
            if (this.total <= 0) return 0;
            return Math.round((this.current / this.total) * 100);
        },
        get progressLabel() {
            return this.current + ' / ' + this.total;
        },
        get summaryText() {
            if (!this.summary) return '';
            return this.summary.imported + ' fotos importadas, ' + this.summary.not_found + ' productos sin match en ML'
                + (this.summary.errors > 0 ? ', ' + this.summary.errors + ' errores' : '');
        },
        entryClass(status) {
            if (status === 'ok') return 'text-green-700';
            if (status === 'no_encontrado') return 'text-amber-700';
            return 'text-red-700';
        },
        markRowImported(productId) {
            const row = document.querySelector('[data-product-row="' + productId + '"]');
            if (!row) return;
            const badge = row.querySelector('.photo-badge');
            if (badge) {
                badge.className = 'inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 photo-badge';
                badge.innerHTML = '<i data-lucide="check" class="h-3 w-3"></i>Tiene foto';
                window.rebuildLucideIcons && window.rebuildLucideIcons();
            }
            const counter = document.querySelector('[data-without-photos]');
            if (counter) {
                const n = Math.max(0, parseInt(counter.textContent || '0', 10) - 1);
                counter.textContent = String(n);
            }
        },
        async startImport() {
            if (this.running || this.withoutPhotos <= 0) return;
            this.running = true;
            this.log = [];
            this.summary = null;
            this.total = 0;
            this.current = 0;

            try {
                const params = new URLSearchParams();
                params.set('_csrf', this.csrf);
                if (this.testLimit > 0) {
                    params.set('limit', String(Math.floor(this.testLimit)));
                }
                const res = await fetch(this.executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/x-ndjson',
                    },
                    body: params.toString(),
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
                        this.handleLine(line);
                    }
                }
                if (buffer.trim()) {
                    this.handleLine(buffer);
                }
            } catch (e) {
                this.log.push({
                    product_id: '-',
                    product_name: '',
                    status: 'error',
                    message: e.message || 'Error de red',
                });
            } finally {
                this.running = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        handleLine(line) {
            let data;
            try {
                data = JSON.parse(line);
            } catch (e) {
                return;
            }
            if (data.type === 'start') {
                this.total = data.total || 0;
                return;
            }
            if (data.type === 'progress') {
                this.current = data.index || this.current + 1;
                this.log.push({
                    product_id: data.product_id,
                    product_name: data.product_name,
                    status: data.status,
                    message: data.message || data.status,
                });
                if (data.status === 'ok') {
                    this.withoutPhotos = Math.max(0, this.withoutPhotos - 1);
                    this.markRowImported(data.product_id);
                }
                this.$nextTick(() => {
                    const el = this.$el.querySelector('.max-h-48');
                    if (el) el.scrollTop = el.scrollHeight;
                });
                return;
            }
            if (data.type === 'done') {
                this.summary = data;
            }
        },
    }));

    Alpine.data('seiqImageImport', () => ({
        csrf: <?= json_encode($mlCsrf, JSON_UNESCAPED_UNICODE) ?>,
        executeUrl: <?= json_encode($seiqExecuteUrl, JSON_UNESCAPED_UNICODE) ?>,
        running: false,
        totalUrls: 0,
        currentUrl: 0,
        log: [],
        summary: null,
        get progressPct() {
            if (this.totalUrls <= 0) return 0;
            return Math.round((this.currentUrl / this.totalUrls) * 100);
        },
        get progressLabel() {
            return this.currentUrl + ' / ' + this.totalUrls + ' URLs';
        },
        get summaryText() {
            if (!this.summary) return '';
            return (this.summary.imported || 0) + ' fotos importadas, '
                + (this.summary.matched || 0) + ' matches, '
                + (this.summary.no_match || 0) + ' sin match'
                + ((this.summary.errors || 0) > 0 ? ', ' + this.summary.errors + ' errores' : '');
        },
        entryClass(status) {
            if (status === 'ok' || status === 'preview') return 'text-green-700';
            if (status === 'sin_match' || status === 'skipped') return 'text-amber-700';
            return 'text-red-700';
        },
        markRowImported(productId) {
            const row = document.querySelector('[data-product-row="' + productId + '"]');
            if (!row) return;
            const badge = row.querySelector('.photo-badge');
            if (badge) {
                badge.className = 'inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 photo-badge';
                badge.innerHTML = '<i data-lucide="check" class="h-3 w-3"></i>Tiene foto';
                window.rebuildLucideIcons && window.rebuildLucideIcons();
            }
        },
        async startImport() {
            if (this.running) return;
            this.running = true;
            this.log = [];
            this.summary = null;
            this.totalUrls = 0;
            this.currentUrl = 0;

            try {
                const res = await fetch(this.executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/x-ndjson',
                    },
                    body: '_csrf=' + encodeURIComponent(this.csrf),
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
                        this.handleLine(line);
                    }
                }
                if (buffer.trim()) {
                    this.handleLine(buffer);
                }
            } catch (e) {
                this.log.push({
                    prefix: 'ERROR',
                    status: 'error',
                    message: e.message || 'Error de red',
                });
            } finally {
                this.running = false;
                this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
            }
        },
        handleLine(line) {
            let data;
            try {
                data = JSON.parse(line);
            } catch (e) {
                return;
            }
            if (data.type === 'start') {
                this.totalUrls = data.total || 0;
                return;
            }
            if (data.type === 'url_start') {
                this.currentUrl = data.index || this.currentUrl + 1;
                this.log.push({
                    prefix: 'URL',
                    status: 'info',
                    message: 'Procesando ' + (data.url || ''),
                });
                return;
            }
            if (data.type === 'url_done') {
                this.log.push({
                    prefix: 'URL OK',
                    status: 'info',
                    message: (data.found || 0) + ' productos, ' + (data.matched || 0) + ' matches, ' + (data.imported || 0) + ' importados',
                });
                return;
            }
            if (data.type === 'item') {
                const sim = data.similarity ? ' (' + data.similarity + '%' + (data.strategy ? ', ' + data.strategy : '') + ')' : '';
                const prod = data.product_name ? ' → #' + data.product_id + ' ' + data.product_name + sim : '';
                this.log.push({
                    prefix: data.seiq_name || '',
                    status: data.status || '',
                    message: (data.message || '') + prod,
                });
                if (data.status === 'ok' && data.product_id) {
                    this.markRowImported(data.product_id);
                }
                return;
            }
            if (data.type === 'url_error') {
                this.log.push({
                    prefix: 'URL ERROR',
                    status: 'error',
                    message: (data.url || '') + ': ' + (data.message || ''),
                });
                return;
            }
            if (data.type === 'done') {
                this.summary = data;
            }
        },
    }));
});
</script>
