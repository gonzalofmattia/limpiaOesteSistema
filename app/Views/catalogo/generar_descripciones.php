<?php
$stats = is_array($stats ?? null) ? $stats : ['total' => 0, 'with_description' => 0, 'without_description' => 0];
$products = is_array($products ?? null) ? $products : [];
$csrf = csrfToken();
$executeUrl = url('/catalogo/generar-descripciones/ejecutar');
?>
<div class="space-y-6" x-data="catalogDescriptionGenerator()">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catálogo</p>
            <h2 class="text-xl font-semibold text-slate-900">Generar descripciones IA</h2>
            <p class="mt-1 text-sm text-slate-600">
                Generá descripciones largas y cortas para el catálogo con Claude.
            </p>
        </div>
        <a href="<?= e(url('/productos')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-lo-border bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Volver a productos
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="lo-card p-5 border-l-4 border-l-lo-blue">
            <p class="text-sm text-slate-600">Productos activos</p>
            <p class="text-3xl font-semibold text-slate-900" x-text="totalActive" data-kpi-total><?= (int) $stats['total'] ?></p>
        </div>
        <div class="lo-card p-5 border-l-4 border-l-green-600">
            <p class="text-sm text-slate-600">Con descripción</p>
            <p class="text-3xl font-semibold text-green-700" x-text="withDescription" data-kpi-with><?= (int) $stats['with_description'] ?></p>
        </div>
        <div class="lo-card p-5 border-l-4 border-l-red-500">
            <p class="text-sm text-slate-600">Sin descripción</p>
            <p class="text-3xl font-semibold text-red-600" x-text="withoutDescription" data-kpi-without><?= (int) $stats['without_description'] ?></p>
        </div>
    </div>

    <section class="lo-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
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
                        @click="startGenerate('missing')"
                        :disabled="running || withoutDescription <= 0"
                        class="inline-flex items-center gap-2 rounded-lg bg-lo-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="sparkles" class="h-4 w-4" :class="running && mode === 'missing' ? 'animate-pulse' : ''"></i>
                    <span x-text="running && mode === 'missing' ? 'Generando…' : 'Generar descripciones faltantes'"></span>
                </button>
                <button type="button"
                        @click="confirmRegenerateAll()"
                        :disabled="running || totalActive <= 0"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="refresh-cw" class="h-4 w-4" :class="running && mode === 'all' ? 'animate-spin' : ''"></i>
                    <span x-text="running && mode === 'all' ? 'Regenerando…' : 'Regenerar todas'"></span>
                </button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        @click="filter = 'all'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="filter === 'all' ? 'bg-lo-blue text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    Todos
                </button>
                <button type="button"
                        @click="filter = 'missing'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="filter === 'missing' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    Sin descripción
                </button>
                <button type="button"
                        @click="filter = 'with'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="filter === 'with' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    Con descripción
                </button>
            </div>
        </div>

        <div x-show="running || log.length > 0" x-cloak class="mt-5 pt-5 border-t border-lo-border space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h4 class="text-sm font-semibold text-slate-800">Progreso</h4>
                <span class="font-mono text-xs text-slate-600" x-show="total > 0" x-text="progressBarText"></span>
            </div>
            <div class="space-y-1" x-show="total > 0">
                <div class="h-3 rounded-full bg-slate-100 overflow-hidden border border-slate-200">
                    <div class="h-full bg-lo-blue transition-all duration-200 ease-out" :style="'width:' + progressPct + '%'"></div>
                </div>
                <p class="text-xs text-slate-500 text-right" x-text="progressLabel + ' (' + progressPct + '%)'"></p>
            </div>
            <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs font-mono space-y-1.5" x-ref="logPanel">
                <template x-for="(entry, idx) in log" :key="'desc-' + idx">
                    <div class="flex flex-wrap gap-x-2 leading-relaxed" :class="entryClass(entry.status)">
                        <span class="text-slate-500 shrink-0" x-text="entry.label"></span>
                        <span class="font-medium shrink-0" x-text="entry.name"></span>
                        <span x-text="entry.message"></span>
                    </div>
                </template>
            </div>
            <div x-show="summary" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <p class="font-semibold">Generación finalizada</p>
                <p class="mt-1" x-text="summaryText"></p>
            </div>
        </div>
    </section>

    <section class="lo-card overflow-hidden">
        <div class="border-b border-lo-border px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">Productos activos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">ID</th>
                        <th class="px-4 py-3 font-semibold">Nombre</th>
                        <th class="px-4 py-3 font-semibold">Categoría</th>
                        <th class="px-4 py-3 font-semibold">Estado</th>
                        <th class="px-4 py-3 font-semibold">Preview</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($products === []): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No hay productos activos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $row): ?>
                            <?php
                            $pid = (int) ($row['id'] ?? 0);
                            $hasDesc = (int) ($row['has_description'] ?? 0) === 1;
                            $short = trim((string) ($row['short_description'] ?? ''));
                            $preview = $short !== '' ? mb_substr($short, 0, 80) : '—';
                            if ($short !== '' && mb_strlen($short) > 80) {
                                $preview .= '…';
                            }
                            ?>
                            <tr class="hover:bg-slate-50 product-row"
                                data-product-row="<?= $pid ?>"
                                data-has-description="<?= $hasDesc ? '1' : '0' ?>"
                                x-show="rowVisible(<?= $hasDesc ? 'true' : 'false' ?>)">
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600"><?= $pid ?></td>
                                <td class="px-4 py-2.5 text-slate-800"><?= e((string) ($row['name'] ?? '')) ?></td>
                                <td class="px-4 py-2.5 text-slate-600"><?= e((string) ($row['category_name'] ?? '')) ?></td>
                                <td class="px-4 py-2.5">
                                    <?php if ($hasDesc): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 desc-badge">
                                            <i data-lucide="check" class="h-3 w-3"></i>Completa
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 desc-badge">
                                            <i data-lucide="x" class="h-3 w-3"></i>Sin descripción
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 text-xs max-w-xs truncate desc-preview" title="<?= e($short) ?>">
                                    <?= e($preview) ?>
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
    Alpine.data('catalogDescriptionGenerator', () => ({
        csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
        executeUrl: <?= json_encode($executeUrl, JSON_UNESCAPED_UNICODE) ?>,
        totalActive: <?= (int) $stats['total'] ?>,
        withDescription: <?= (int) $stats['with_description'] ?>,
        withoutDescription: <?= (int) $stats['without_description'] ?>,
        testLimit: '',
        running: false,
        mode: '',
        filter: 'all',
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
        get progressBarText() {
            if (this.total <= 0) return '';
            const width = 10;
            const filled = Math.min(width, Math.max(0, Math.round((this.progressPct / 100) * width)));
            let bar = '[';
            for (let i = 0; i < width; i++) {
                if (i < filled - 1) bar += '=';
                else if (i === filled - 1 && filled > 0) bar += '>';
                else bar += ' ';
            }
            bar += ']';
            return bar + ' ' + this.current + '/' + this.total + ' (' + this.progressPct + '%)';
        },
        get summaryText() {
            if (!this.summary) return '';
            return this.summary.generated + ' generadas, ' + this.summary.errors + ' errores';
        },
        rowVisible(hasDescription) {
            if (this.filter === 'all') return true;
            if (this.filter === 'missing') return !hasDescription;
            return hasDescription;
        },
        entryClass(status) {
            if (status === 'ok') return 'text-green-700';
            return 'text-red-700';
        },
        confirmRegenerateAll() {
            if (this.running) return;
            if (!confirm('¿Regenerar TODAS las descripciones? Se sobreescribirán las existentes.')) return;
            this.startGenerate('all');
        },
        markRowGenerated(productId, shortPreview) {
            const row = document.querySelector('[data-product-row="' + productId + '"]');
            if (!row) return;
            const hadDesc = row.getAttribute('data-has-description') === '1';
            row.setAttribute('data-has-description', '1');
            const badge = row.querySelector('.desc-badge');
            if (badge) {
                badge.className = 'inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 desc-badge';
                badge.innerHTML = '<i data-lucide="check" class="h-3 w-3"></i>Completa';
            }
            const preview = row.querySelector('.desc-preview');
            if (preview && shortPreview) {
                preview.textContent = shortPreview;
                preview.title = shortPreview;
            }
            if (!hadDesc) {
                this.withoutDescription = Math.max(0, this.withoutDescription - 1);
                this.withDescription += 1;
            }
            window.rebuildLucideIcons && window.rebuildLucideIcons();
        },
        async startGenerate(mode) {
            if (this.running) return;
            if (mode === 'missing' && this.withoutDescription <= 0) return;
            if (mode === 'all' && this.totalActive <= 0) return;

            this.running = true;
            this.mode = mode;
            this.log = [];
            this.summary = null;
            this.total = 0;
            this.current = 0;

            try {
                const params = new URLSearchParams();
                params.set('_csrf', this.csrf);
                params.set('mode', mode);
                if (this.testLimit > 0) {
                    params.set('limit', String(Math.floor(this.testLimit)));
                }
                const res = await fetch(this.executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'text/event-stream',
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
                        try {
                            this.handleLine(line);
                        } catch (e) {}
                    }
                }
                if (buffer.trim()) {
                    try {
                        this.handleLine(buffer);
                    } catch (e) {}
                }
            } catch (e) {
                this.log.push({
                    label: 'ERROR',
                    name: '',
                    status: 'error',
                    message: '❌ ' + (e.message || 'Error de red'),
                });
            } finally {
                this.running = false;
                this.mode = '';
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
                const label = (data.index || this.current) + '/' + (data.total || this.total);
                const msg = data.status === 'ok'
                    ? ('✅ ok — ' + (data.short || ''))
                    : ('❌ error — ' + (data.error || 'error'));
                this.log.push({
                    label: label,
                    name: data.name || '',
                    status: data.status,
                    message: msg,
                });
                if (data.status === 'ok' && data.product_id) {
                    this.markRowGenerated(data.product_id, data.short || '');
                }
                this.$nextTick(() => {
                    const el = this.$refs.logPanel;
                    if (el) el.scrollTop = el.scrollHeight;
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
