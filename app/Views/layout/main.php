<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Panel') ?> — LIMPIA OESTE</title>
    <link rel="icon" type="image/png" href="<?= e(url('/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('globalHeaderSearch', () => ({
                q: '',
                open: false,
                mobileSearchOpen: false,
                loading: false,
                t: null,
                results: { products: [], clients: [], quotes: [], categories: [] },
                async runSearch() {
                    if ((this.q || '').trim().length < 2) {
                        this.open = false;
                        this.results = { products: [], clients: [], quotes: [], categories: [] };
                        return;
                    }
                    this.loading = true;
                    this.open = true;
                    try {
                        const res = await fetch(window.appUrl('/api/buscar?q=') + encodeURIComponent(this.q));
                        const data = await res.json();
                        this.results = data.results || { products: [], clients: [], quotes: [], categories: [] };
                    } catch (e) {
                        this.results = { products: [], clients: [], quotes: [], categories: [] };
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons());
                    }
                },
                debounceSearch() {
                    clearTimeout(this.t);
                    this.t = setTimeout(() => this.runSearch(), 300);
                },
                goToResults() {
                    if ((this.q || '').trim() === '') return;
                    window.location.href = window.appUrl('/buscar?q=') + encodeURIComponent(this.q);
                },
                openMobileSearch() {
                    this.mobileSearchOpen = true;
                    this.$nextTick(() => {
                        const el = this.$refs.mobileSearchInput;
                        if (el) el.focus();
                        window.rebuildLucideIcons && window.rebuildLucideIcons();
                    });
                },
                closeMobileSearch() {
                    this.mobileSearchOpen = false;
                    this.open = false;
                },
            }));
        });
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        lo: {
                            bg: '#F8FAFC',
                            text: '#1E293B',
                            muted: '#64748B',
                            border: '#E2E8F0',
                            blue: '#1565C0',
                            blueSoft: '#E0F2FE'
                        }
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode(defined('BASE_URL_PATH') ? BASE_URL_PATH : (defined('BASE_URL') ? BASE_URL : ''), JSON_UNESCAPED_SLASHES) ?>;
        window.PRODUCT_IMAGE_PUBLIC_BASE = <?= json_encode(productImagePublicBaseUrl(), JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body class="bg-lo-bg text-lo-text antialiased" x-data="{ sidebarOpen: false, deleteModal: { open: false, formId: '', itemName: 'este registro' }, openDeleteModal(formId, itemName){ this.deleteModal.formId=formId; this.deleteModal.itemName=itemName || 'este registro'; this.deleteModal.open=true; this.$nextTick(() => window.rebuildLucideIcons && window.rebuildLucideIcons()); } }">
<?php
$flash = getFlash();
$mlActiveListingsCount = 0;
try {
    $mlActiveListingsCount = (int) \App\Models\Database::getInstance()->fetchColumn(
        "SELECT COUNT(*) FROM ml_listings WHERE status = 'active'"
    );
} catch (\Throwable) {
    $mlActiveListingsCount = 0;
}
$outreachInboxCount = \App\Controllers\InboxController::pendingCount();
?>
<div class="min-h-screen lg:flex">
    <aside class="fixed lg:static inset-y-0 left-0 z-40 w-[220px] bg-white border-r border-lo-border flex flex-col transition-transform duration-200"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="h-20 px-4 border-b border-lo-border flex items-center justify-between">
            <a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/img/logoLimpiaOeste.png')) ?>" alt="Limpia Oeste" class="h-10 w-auto"></a>
            <button class="lg:hidden text-slate-500" @click="sidebarOpen=false"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
            <?php
            $itemBase = 'group flex items-center gap-3 rounded-lg px-3 py-2.5 border-l-2 transition';
            $navGroupActive = [
                'principal' => isActive('/') && !isActive('/categorias') && !isActive('/productos'),
                'catalogo' => isActive('/categorias') || isActive('/productos') || isActive('/catalogo-visual')
                    || isActive('/catalogo/generar-descripciones') || isActive('/stock-actual') || isActive('/stock/proyeccion'),
                'comercial' => isActive('/listas') || isActive('/clientes') || isActive('/presupuestos') || isActive('/ventas')
                    || isActive('/ventas-ml') || isActive('/mercadolibre') || isActive('/prospeccion')
                    || isActive('/pedidos-proveedor') || isActive('/cuenta-corriente'),
                'sistema' => isActive('/settings') || isActive('/sincronizacion'),
            ];
            $navGroupOpen = static function (string $key) use ($navGroupActive): string {
                if ($navGroupActive[$key]) {
                    return 'true';
                }
                return "(localStorage.getItem('lo_nav_{$key}') ?? '1') === '1'";
            };
            $navGroupStart = static function (string $key, string $label) use ($navGroupOpen): void {
                echo '<div x-data="{ open: ' . $navGroupOpen($key) . ' }" x-init="$watch(\'open\', v => localStorage.setItem(\'lo_nav_' . $key . '\', v ? \'1\' : \'0\'))">';
                echo '<button type="button" @click="open = !open" class="w-full flex items-center justify-between px-3 pt-4 pb-1 text-left">';
                echo '<span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">' . e($label) . '</span>';
                echo '<i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400 transition-transform" :class="{ \'-rotate-90\': !open }"></i>';
                echo '</button>';
                echo '<div x-show="open" x-cloak>';
            };
            $navGroupEnd = static function (): void {
                echo '</div></div>';
            };
            ?>
            <?php $navGroupStart('principal', 'Principal'); ?>
            <a href="<?= e(url('/')) ?>" class="<?= $itemBase ?> <?= isActive('/') && !isActive('/categorias') && !isActive('/productos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>">
                <i data-lucide="layout-dashboard" class="h-4 w-4"></i><span>Dashboard</span>
            </a>
            <?php $navGroupEnd(); ?>
            <?php $navGroupStart('catalogo', 'Catálogo'); ?>
            <a href="<?= e(url('/categorias')) ?>" class="<?= $itemBase ?> <?= isActive('/categorias') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="tags" class="h-4 w-4"></i><span>Categorías</span></a>
            <a href="<?= e(url('/productos')) ?>" class="<?= $itemBase ?> <?= isActive('/productos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="package" class="h-4 w-4"></i><span>Productos</span></a>
            <a href="<?= e(url('/catalogo-visual')) ?>" class="<?= $itemBase ?> <?= isActive('/catalogo-visual') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="layout-grid" class="h-4 w-4"></i><span>Vista visual</span></a>
            <a href="<?= e(url('/catalogo/generar-descripciones')) ?>" class="<?= $itemBase ?> <?= isActive('/catalogo/generar-descripciones') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="sparkles" class="h-4 w-4"></i><span>Generar descripciones IA</span></a>
            <a href="<?= e(url('/stock-actual')) ?>" class="<?= $itemBase ?> <?= isActive('/stock-actual') && !isActive('/stock/proyeccion') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="warehouse" class="h-4 w-4"></i><span>Stock actual</span></a>
            <a href="<?= e(url('/stock/proyeccion')) ?>" class="<?= $itemBase ?> <?= isActive('/stock/proyeccion') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="trending-up" class="h-4 w-4"></i><span>Proyección compra</span></a>
            <?php $navGroupEnd(); ?>
            <?php $navGroupStart('comercial', 'Comercial'); ?>
            <a href="<?= e(url('/listas')) ?>" class="<?= $itemBase ?> <?= isActive('/listas') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="list-ordered" class="h-4 w-4"></i><span>Listas de precios</span></a>
            <a href="<?= e(url('/clientes')) ?>" class="<?= $itemBase ?> <?= isActive('/clientes') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="users" class="h-4 w-4"></i><span>Clientes</span></a>
            <a href="<?= e(url('/presupuestos')) ?>" class="<?= $itemBase ?> <?= isActive('/presupuestos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="file-text" class="h-4 w-4"></i><span>Presupuestos</span></a>
            <a href="<?= e(url('/ventas')) ?>" class="<?= $itemBase ?> <?= isActive('/ventas') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="shopping-cart" class="h-4 w-4"></i><span>Ventas</span></a>
            <a href="<?= e(url('/ventas-ml')) ?>" class="<?= $itemBase ?> <?= isActive('/ventas-ml') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="store" class="h-4 w-4"></i><span>Ventas ML</span></a>
            <a href="<?= e(url('/mercadolibre')) ?>" class="<?= $itemBase ?> <?= isActive('/mercadolibre') && !isActive('/mercadolibre/publicacion-masiva') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>">
                <i data-lucide="shopping-bag" class="h-4 w-4"></i>
                <span class="flex-1">MercadoLibre</span>
                <span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-bold text-green-800"><?= $mlActiveListingsCount ?></span>
            </a>
            <a href="<?= e(url('/mercadolibre/publicacion-masiva')) ?>" class="<?= $itemBase ?> <?= isActive('/mercadolibre/publicacion-masiva') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6">
                <i data-lucide="upload-cloud" class="h-4 w-4"></i><span>Publicación masiva</span>
            </a>
            <a href="<?= e(url('/prospeccion')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion') && !isActive('/prospeccion/campanas') && !isActive('/prospeccion/recontacto-clientes') && !isActive('/prospeccion/cola') && !isActive('/prospeccion/bandeja') && !isActive('/prospeccion/instrucciones') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="radar" class="h-4 w-4"></i><span>Prospección</span></a>
            <a href="<?= e(url('/prospeccion/bandeja')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion/bandeja') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6">
                <i data-lucide="inbox" class="h-4 w-4"></i><span class="flex-1">Bandeja</span>
                <?php if ($outreachInboxCount > 0): ?>
                    <span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-800"><?= $outreachInboxCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= e(url('/prospeccion/campanas')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion/campanas') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="megaphone" class="h-4 w-4"></i><span>Campañas</span></a>
            <a href="<?= e(url('/prospeccion/recontacto-clientes')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion/recontacto-clientes') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="repeat" class="h-4 w-4"></i><span>Recontacto clientes</span></a>
            <a href="<?= e(url('/prospeccion/cola')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion/cola') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="send" class="h-4 w-4"></i><span>Cola de envíos</span></a>
            <a href="<?= e(url('/prospeccion/instrucciones')) ?>" class="<?= $itemBase ?> <?= isActive('/prospeccion/instrucciones') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?> pl-6"><i data-lucide="book-open" class="h-4 w-4"></i><span>Instrucciones</span></a>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="<?= $itemBase ?> <?= isActive('/pedidos-proveedor') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="truck" class="h-4 w-4"></i><span>Pedidos a Proveedores</span></a>
            <a href="<?= e(url('/cuenta-corriente')) ?>" class="<?= $itemBase ?> <?= isActive('/cuenta-corriente') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="wallet" class="h-4 w-4"></i><span>Cuenta Corriente</span></a>
            <?php $navGroupEnd(); ?>
            <?php $navGroupStart('sistema', 'Sistema'); ?>
            <a href="<?= e(url('/settings')) ?>" class="<?= $itemBase ?> <?= isActive('/settings') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="settings" class="h-4 w-4"></i><span>Configuración</span></a>
            <a href="<?= e(url('/sincronizacion')) ?>" class="<?= $itemBase ?> <?= isActive('/sincronizacion') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="refresh-cw" class="h-4 w-4"></i><span>Sincronización</span></a>
            <?php $navGroupEnd(); ?>
        </nav>
        <div class="p-3 border-t border-lo-border">
            <div class="rounded-xl bg-slate-50 px-3 py-2 mb-2">
                <p class="text-xs font-semibold text-slate-700">Lista Seiq N°<?= e(setting('lista_seiq_numero', '—')) ?></p>
                <p class="text-[11px] text-slate-500">Actualizada <?= e(setting('lista_seiq_fecha', '—')) ?></p>
            </div>
            <a href="<?= e(url('/logout')) ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100"><i data-lucide="log-out" class="h-4 w-4"></i>Cerrar sesión</a>
        </div>
    </aside>
    <div class="flex-1 min-w-0 lg:pl-0">
        <header class="sticky top-0 z-30 bg-white/95 border-b border-lo-border backdrop-blur" x-data="globalHeaderSearch" @keydown.escape.window="closeMobileSearch()">
            <div class="mx-auto max-w-[1400px] px-4 lg:px-8 h-20 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0 flex-1 md:flex-initial md:min-w-0">
                    <button type="button" class="lg:hidden shrink-0 min-h-11 min-w-11 p-2 rounded-lg border border-lo-border flex items-center justify-center" @click="sidebarOpen=true" aria-label="Abrir menú"><i data-lucide="menu" class="h-5 w-5"></i></button>
                    <div class="min-w-0">
                        <h1 class="text-lg font-semibold text-slate-900 truncate"><?= e($title ?? 'Panel') ?></h1>
                        <p class="text-xs text-slate-500 truncate"><?= e($subtitle ?? 'Resumen rápido de tu negocio') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" class="md:hidden min-h-11 min-w-11 rounded-xl border border-lo-border bg-white grid place-items-center" @click="openMobileSearch()" aria-label="Buscar">
                        <i data-lucide="search" class="h-5 w-5 text-slate-600"></i>
                    </button>
                    <div class="hidden md:block relative min-w-[360px]">
                        <div class="flex items-center gap-2 h-11 rounded-xl border border-lo-border bg-white px-3">
                            <i data-lucide="search" class="h-4 w-4 text-slate-400 shrink-0"></i>
                            <input x-model="q" @input="debounceSearch()" @focus="if((q||'').trim().length>=2) open=true" @keydown.enter.prevent="goToResults()" class="w-full min-h-0 bg-transparent text-sm outline-none placeholder:text-slate-400" placeholder="Buscar productos, clientes, pr...">
                            <span class="text-[10px] text-slate-400 rounded-md border border-slate-200 px-1.5 py-0.5 shrink-0">⌘K</span>
                        </div>
                        <div x-show="open" x-cloak @click.away="open=false" x-transition class="absolute left-0 right-0 mt-2 rounded-xl border border-lo-border bg-white shadow-lg p-2 z-50 max-h-[420px] overflow-y-auto">
                            <template x-if="loading"><p class="px-2 py-2 text-xs text-slate-500">Buscando...</p></template>
                            <template x-if="!loading">
                                <div class="space-y-2">
                                    <template x-for="section in [{k:'products',t:'Productos',i:'package',f:'name',d:'code'},{k:'clients',t:'Clientes',i:'users',f:'name',d:'phone'},{k:'quotes',t:'Presupuestos',i:'file-text',f:'quote_number',d:'sale_number'}]" :key="section.k">
                                        <div x-show="(results[section.k]||[]).length > 0" class="border border-slate-100 rounded-lg">
                                            <p class="px-2 py-1.5 text-[11px] uppercase tracking-wide text-slate-500 font-semibold" x-text="section.t"></p>
                                            <template x-for="item in results[section.k].slice(0,5)" :key="item.id">
                                                <a :href="item.url" class="px-2 py-2 flex items-center justify-between hover:bg-slate-50 rounded-md">
                                                    <span class="text-sm text-slate-700 truncate" x-text="item[section.f] || '—'"></span>
                                                    <span class="text-xs text-slate-500 ml-3 shrink-0" x-text="item[section.d] || ''"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                    <p x-show="(results.products||[]).length===0 && (results.clients||[]).length===0 && (results.quotes||[]).length===0" class="px-2 py-2 text-xs text-slate-500">No se encontraron resultados para "<span x-text="q"></span>"</p>
                                </div>
                            </template>
                        </div>
                    </div>
                    <button type="button" class="hidden md:grid min-h-11 min-w-11 rounded-xl border border-lo-border bg-white place-items-center" aria-label="Notificaciones"><i data-lucide="bell" class="h-4 w-4 text-slate-500"></i></button>
                    <div class="relative" x-data="{ open:false }">
                        <button type="button" @click="open=!open" class="min-h-11 h-11 rounded-xl border border-lo-border bg-white px-1.5 md:px-2.5 flex items-center gap-2">
                            <span class="h-7 w-7 rounded-full bg-sky-500 text-white text-xs font-semibold grid place-items-center shrink-0">AD</span>
                            <span class="hidden md:inline text-sm text-slate-700">admin</span>
                            <i data-lucide="chevron-down" class="hidden md:block h-4 w-4 text-slate-400 shrink-0"></i>
                        </button>
                        <div x-show="open" x-cloak @click.away="open=false" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1" class="absolute right-0 mt-2 w-36 rounded-xl border border-lo-border bg-white shadow-md py-1">
                            <a href="<?= e(url('/logout')) ?>" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">Salir</a>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="mobileSearchOpen" x-cloak x-transition.opacity class="md:hidden fixed inset-0 z-[55] bg-black/40" @click="closeMobileSearch()"></div>
            <div x-show="mobileSearchOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="md:hidden fixed left-0 right-0 top-0 z-[60] bg-white border-b border-lo-border shadow-xl pt-[env(safe-area-inset-top)]">
                <div class="px-3 py-3 flex items-center gap-2 border-b border-lo-border">
                    <div class="flex-1 flex items-center gap-2 min-h-[48px] rounded-xl border border-lo-border bg-slate-50 px-3">
                        <i data-lucide="search" class="h-5 w-5 text-slate-400 shrink-0"></i>
                        <input x-ref="mobileSearchInput" x-model="q" @input="debounceSearch()" @keydown.enter.prevent="goToResults(); closeMobileSearch()" class="flex-1 w-full min-h-[44px] text-base bg-transparent outline-none placeholder:text-slate-400" placeholder="Buscar productos, clientes…">
                    </div>
                    <button type="button" class="shrink-0 min-h-11 min-w-[4.5rem] rounded-lg text-sm font-semibold text-lo-blue" @click="closeMobileSearch()">Cerrar</button>
                </div>
                <div class="max-h-[min(65vh,480px)] overflow-y-auto p-3">
                    <template x-if="loading"><p class="px-1 py-3 text-sm text-slate-500">Buscando…</p></template>
                    <template x-if="!loading">
                        <div class="space-y-2">
                            <template x-for="section in [{k:'products',t:'Productos',i:'package',f:'name',d:'code'},{k:'clients',t:'Clientes',i:'users',f:'name',d:'phone'},{k:'quotes',t:'Presupuestos',i:'file-text',f:'quote_number',d:'sale_number'}]" :key="'m-'+section.k">
                                <div x-show="(results[section.k]||[]).length > 0" class="border border-slate-100 rounded-xl">
                                    <p class="px-3 py-2 text-xs uppercase tracking-wide text-slate-500 font-semibold" x-text="section.t"></p>
                                    <template x-for="item in results[section.k].slice(0,8)" :key="'mi-'+section.k+'-'+item.id">
                                        <a :href="item.url" @click="closeMobileSearch()" class="block px-3 py-3 min-h-[48px] border-t border-slate-50 first:border-t-0 flex items-center justify-between gap-2 active:bg-slate-50">
                                            <span class="text-base text-slate-800 font-medium truncate" x-text="item[section.f] || '—'"></span>
                                            <span class="text-sm text-slate-500 shrink-0 max-w-[40%] truncate text-right" x-text="item[section.d] || ''"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                            <p x-show="(results.products||[]).length===0 && (results.clients||[]).length===0 && (results.quotes||[]).length===0 && (q||'').trim().length>=2" class="px-2 py-6 text-center text-sm text-slate-500">No hay resultados para «<span x-text="q"></span>»</p>
                            <p x-show="(q||'').trim().length<2" class="px-2 py-6 text-center text-sm text-slate-500">Escribí al menos 2 caracteres para buscar.</p>
                        </div>
                    </template>
                </div>
                <div class="p-3 border-t border-lo-border pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <button type="button" @click="goToResults(); closeMobileSearch()" class="w-full min-h-11 rounded-xl bg-lo-blue text-white text-base font-semibold py-2.5">Ver página de resultados</button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-[1400px] px-4 lg:px-8 py-6 pb-24 md:pb-6">
            <?php if ($flash): ?>
                <?php $flashClass = match ($flash['type'] ?? '') { 'error' => 'bg-red-50 text-red-800 border-red-300', 'info' => 'bg-blue-50 text-blue-800 border-blue-300', default => 'bg-green-50 text-green-800 border-green-300' }; ?>
                <div x-data="{ show: true, _t: null }" x-show="show" x-init="_t = setTimeout(() => show = false, 10000)" @click="show = false; if (_t) { clearTimeout(_t); _t = null; }" role="status" title="Tocá para cerrar" class="mb-4 w-full md:max-w-none cursor-pointer select-none -mx-4 px-4 py-4 md:mx-0 md:rounded-xl border-y-2 md:border-2 text-base md:text-sm font-medium shadow-sm md:shadow-none <?= $flashClass ?>">
                    <span class="block pr-8"><?= e($flash['message']) ?></span>
                    <span class="mt-1 block text-xs font-normal opacity-80 md:hidden">Tocá para cerrar</span>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-[45] bg-white border-t border-lo-border shadow-lg grid grid-cols-4 items-center" style="min-height:60px;padding-bottom:max(8px, env(safe-area-inset-bottom))" aria-label="Accesos rápidos">
            <?php
            $navItem = static function (string $href, string $icon, string $label, bool $active): void {
                $cls = $active ? 'text-lo-blue bg-lo-blueSoft/60' : 'text-slate-600 active:bg-slate-100';
                echo '<a href="' . e($href) . '" class="flex flex-col items-center justify-center gap-0.5 py-1 min-h-[48px] ' . $cls . '">';
                echo '<i data-lucide="' . e($icon) . '" class="h-5 w-5"></i>';
                echo '<span class="text-[10px] font-semibold leading-tight text-center px-0.5">' . e($label) . '</span>';
                echo '</a>';
            };
            $navItem(url('/'), 'layout-dashboard', 'Dashboard', isActive('/') && !isActive('/categorias') && !isActive('/productos') && !isActive('/stock-actual') && !isActive('/presupuestos') && !isActive('/cuenta-corriente'));
            $navItem(url('/presupuestos'), 'file-text', 'Presupuestos', isActive('/presupuestos'));
            $navItem(url('/stock-actual'), 'warehouse', 'Stock', isActive('/stock-actual'));
            $navItem(url('/cuenta-corriente'), 'wallet', 'Cuenta cte.', isActive('/cuenta-corriente'));
            ?>
        </nav>
    </div>
</div>
<div class="fixed inset-0 bg-black/40 z-30 lg:hidden" x-show="sidebarOpen" x-cloak x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="sidebarOpen=false"></div>
<?php require APP_PATH . '/Views/layout/delete_modal.php'; ?>
<?php include APP_PATH . '/Views/components/modal-pago-rapido.php'; ?>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<script>
if (window.rebuildLucideIcons) window.rebuildLucideIcons();
document.addEventListener('alpine:initialized', () => window.rebuildLucideIcons && window.rebuildLucideIcons());
</script>
</body>
</html>
