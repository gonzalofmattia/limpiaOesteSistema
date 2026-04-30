<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Panel') ?> — LIMPIA OESTE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
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
    </script>
</head>
<body class="bg-lo-bg text-lo-text antialiased" x-data="{ sidebarOpen: false, deleteModal: { open: false, formId: '', itemName: 'este registro' }, openDeleteModal(formId, itemName){ this.deleteModal.formId=formId; this.deleteModal.itemName=itemName || 'este registro'; this.deleteModal.open=true; }}">
<?php $flash = getFlash(); ?>
<div class="min-h-screen lg:flex">
    <aside class="fixed lg:static inset-y-0 left-0 z-40 w-[220px] bg-white border-r border-lo-border flex flex-col transition-transform duration-200"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="h-20 px-4 border-b border-lo-border flex items-center justify-between">
            <a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/img/logoLimpiaOeste.png')) ?>" alt="Limpia Oeste" class="h-10 w-auto"></a>
            <button class="lg:hidden text-slate-500" @click="sidebarOpen=false"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
            <?php $itemBase = 'group flex items-center gap-3 rounded-lg px-3 py-2.5 border-l-2 transition'; ?>
            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Principal</p>
            <a href="<?= e(url('/')) ?>" class="<?= $itemBase ?> <?= isActive('/') && !isActive('/categorias') && !isActive('/productos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>">
                <i data-lucide="layout-dashboard" class="h-4 w-4"></i><span>Dashboard</span>
            </a>
            <p class="px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Catálogo</p>
            <a href="<?= e(url('/categorias')) ?>" class="<?= $itemBase ?> <?= isActive('/categorias') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="tags" class="h-4 w-4"></i><span>Categorías</span></a>
            <a href="<?= e(url('/productos')) ?>" class="<?= $itemBase ?> <?= isActive('/productos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="package" class="h-4 w-4"></i><span>Productos</span></a>
            <a href="<?= e(url('/stock-actual')) ?>" class="<?= $itemBase ?> <?= isActive('/stock-actual') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="warehouse" class="h-4 w-4"></i><span>Stock actual</span></a>
            <p class="px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Comercial</p>
            <a href="<?= e(url('/listas')) ?>" class="<?= $itemBase ?> <?= isActive('/listas') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="list-ordered" class="h-4 w-4"></i><span>Listas de precios</span></a>
            <a href="<?= e(url('/clientes')) ?>" class="<?= $itemBase ?> <?= isActive('/clientes') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="users" class="h-4 w-4"></i><span>Clientes</span></a>
            <a href="<?= e(url('/presupuestos')) ?>" class="<?= $itemBase ?> <?= isActive('/presupuestos') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="file-text" class="h-4 w-4"></i><span>Presupuestos</span></a>
            <a href="<?= e(url('/ventas')) ?>" class="<?= $itemBase ?> <?= isActive('/ventas') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="shopping-cart" class="h-4 w-4"></i><span>Ventas</span></a>
            <a href="<?= e(url('/ventas-ml')) ?>" class="<?= $itemBase ?> <?= isActive('/ventas-ml') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="store" class="h-4 w-4"></i><span>Ventas ML</span></a>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="<?= $itemBase ?> <?= isActive('/pedido-seiq') || isActive('/pedidos-proveedor') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="truck" class="h-4 w-4"></i><span>Pedidos a Proveedores</span></a>
            <a href="<?= e(url('/cuenta-corriente')) ?>" class="<?= $itemBase ?> <?= isActive('/cuenta-corriente') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="wallet" class="h-4 w-4"></i><span>Cuenta Corriente</span></a>
            <p class="px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sistema</p>
            <a href="<?= e(url('/settings')) ?>" class="<?= $itemBase ?> <?= isActive('/settings') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="settings" class="h-4 w-4"></i><span>Configuración</span></a>
            <a href="<?= e(url('/sincronizacion')) ?>" class="<?= $itemBase ?> <?= isActive('/sincronizacion') ? 'bg-lo-blueSoft text-lo-blue border-lo-blue' : 'text-slate-600 hover:bg-slate-50 border-transparent' ?>"><i data-lucide="refresh-cw" class="h-4 w-4"></i><span>Sincronización</span></a>
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
        <header class="sticky top-0 z-30 bg-white/95 border-b border-lo-border backdrop-blur">
            <div class="mx-auto max-w-[1400px] px-4 lg:px-8 h-20 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button class="lg:hidden p-2 rounded-lg border border-lo-border" @click="sidebarOpen=true"><i data-lucide="menu" class="h-5 w-5"></i></button>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900"><?= e($title ?? 'Panel') ?></h1>
                        <p class="text-xs text-slate-500"><?= e($subtitle ?? 'Resumen rápido de tu negocio') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="hidden md:flex items-center gap-2 h-10 rounded-xl border border-lo-border bg-white px-3 min-w-[320px]">
                        <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
                        <input class="w-full bg-transparent text-sm outline-none placeholder:text-slate-400" placeholder="Buscar productos, clientes, pr...">
                        <span class="text-[10px] text-slate-400 rounded-md border border-slate-200 px-1.5 py-0.5">⌘K</span>
                    </div>
                    <button class="h-10 w-10 rounded-xl border border-lo-border bg-white grid place-items-center"><i data-lucide="bell" class="h-4 w-4 text-slate-500"></i></button>
                    <div class="relative" x-data="{ open:false }">
                        <button @click="open=!open" class="h-10 rounded-xl border border-lo-border bg-white px-2.5 flex items-center gap-2">
                            <span class="h-7 w-7 rounded-full bg-sky-500 text-white text-xs font-semibold grid place-items-center">AD</span>
                            <span class="text-sm text-slate-700">admin</span>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400"></i>
                        </button>
                        <div x-show="open" x-cloak @click.away="open=false" x-transition class="absolute right-0 mt-2 w-36 rounded-xl border border-lo-border bg-white shadow-md py-1">
                            <a href="<?= e(url('/logout')) ?>" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">Salir</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-[1400px] px-4 lg:px-8 py-6">
            <?php if ($flash): ?>
                <?php $flashClass = match ($flash['type'] ?? '') { 'error' => 'bg-red-50 text-red-800 border-red-200', 'info' => 'bg-blue-50 text-blue-800 border-blue-200', default => 'bg-green-50 text-green-800 border-green-200' }; ?>
                <div x-data="{ show:true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 px-4 py-3 rounded-xl border text-sm <?= $flashClass ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<div class="fixed inset-0 bg-black/40 z-30 lg:hidden" x-show="sidebarOpen" x-cloak x-transition @click="sidebarOpen=false"></div>
<?php require APP_PATH . '/Views/layout/delete_modal.php'; ?>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<script>
if (window.lucide) window.lucide.createIcons();
document.addEventListener('alpine:initialized', () => window.lucide && window.lucide.createIcons());
</script>
</body>
</html>
