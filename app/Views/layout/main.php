<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Panel') ?> — LIMPIA OESTE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1a6b3c', light: '#2db368' },
                        accent: '#1565C0',
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode(defined('BASE_URL_PATH') ? BASE_URL_PATH : (defined('BASE_URL') ? BASE_URL : ''), JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body class="bg-[#F3F4F6] text-[#111827] font-sans antialiased" x-data="{ sidebarOpen: false }">
<?php $flash = getFlash(); ?>
<div class="min-h-screen flex flex-col md:flex-row">
    <aside class="fixed md:static inset-y-0 left-0 z-40 w-64 bg-gray-900 text-gray-100 transform transition-transform md:translate-x-0 flex flex-col"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
        <div class="p-4 border-b border-gray-800 flex items-center justify-between">
            <a href="<?= e(url('/')) ?>" class="block">
                <img src="<?= e(url('/assets/img/logoLimpiaOeste.png')) ?>" alt="LIMPIA OESTE" class="h-8 w-auto">
            </a>
            <button type="button" class="md:hidden text-gray-400" @click="sidebarOpen = false">✕</button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 text-sm">
            <a href="<?= e(url('/')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/') && !isActive('/categorias') && !isActive('/productos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75V16.5c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v4.125H19.5c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5"/>
                </svg>
                Dashboard
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Catálogo</p>
            <a href="<?= e(url('/categorias')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/categorias') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z"/>
                </svg>
                Categorías
            </a>
            <a href="<?= e(url('/productos')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/productos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5v9l9 5.25m0-9v9m0-9L21 7.5"/>
                </svg>
                Productos
            </a>
            <a href="<?= e(url('/stock-actual')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/stock-actual') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                </svg>
                Stock actual
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Comercial</p>
            <a href="<?= e(url('/listas')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/listas') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                </svg>
                Listas de precios
            </a>
            <a href="<?= e(url('/clientes')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/clientes') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
                Clientes
            </a>
            <a href="<?= e(url('/presupuestos')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/presupuestos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                Presupuestos
            </a>
            <a href="<?= e(url('/ventas')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/ventas') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h7.5"/>
                </svg>
                Ventas
            </a>
            <a href="<?= e(url('/ventas-ml')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/ventas-ml') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5m-16.5 4.5h16.5m-16.5 4.5h10.5m-7.5 3.75h10.5"/>
                </svg>
                Ventas ML
            </a>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/pedido-seiq') || isActive('/pedidos-proveedor') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                Pedidos a Proveedores
            </a>
            <a href="<?= e(url('/cuenta-corriente')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/cuenta-corriente') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Cuenta Corriente
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Sistema</p>
            <a href="<?= e(url('/settings')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/settings') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Configuración
            </a>
            <a href="<?= e(url('/sincronizacion')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/sincronizacion') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.023 9.348h4.992v-.001M2.985 19.644h4.992m0 0v-4.992m0 4.992l3.181-3.183a8.25 8.25 0 0113.803 3.7M4.031 4.355a8.25 8.25 0 0113.803-3.7l3.181 3.182m0 0V.845m0 2.992h-4.992"/>
                </svg>
                Sincronización
            </a>
        </nav>
        <div class="px-4 pb-3 border-t border-gray-800">
            <a href="<?= e(url('/logout')) ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm text-gray-400 hover:text-red-400 hover:bg-gray-800 rounded-lg transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Cerrar sesión
            </a>
        </div>
        <div class="p-4 border-t border-gray-800 text-xs text-gray-400">
            <p class="font-medium text-gray-300">Lista Seiq N°<?= e(setting('lista_seiq_numero', '—')) ?></p>
            <p><?= e(setting('lista_seiq_fecha', '')) ?></p>
        </div>
    </aside>
    <div class="flex-1 flex flex-col min-w-0 md:ml-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <button type="button" class="md:hidden p-2 rounded-lg border border-gray-200" @click="sidebarOpen = true">☰</button>
                    <h1 class="text-lg font-semibold text-gray-900"><?= e($title ?? '') ?></h1>
                </div>
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm text-gray-700 hover:text-primary">
                        <span><?= e($_SESSION['admin_username'] ?? 'admin') ?></span>
                        <span>▼</span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">
                        <a href="<?= e(url('/logout')) ?>" class="block px-3 py-2 hover:bg-gray-50 text-red-600">Salir</a>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 p-4 md:p-6">
            <?php if ($flash): ?>
                <?php
                $flashClass = match ($flash['type'] ?? '') {
                    'error' => 'bg-red-50 text-red-800 border border-red-200',
                    'info' => 'bg-blue-50 text-blue-800 border border-blue-200',
                    default => 'bg-green-50 text-green-800 border border-green-200',
                };
                ?>
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flashClass ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<div class="fixed inset-0 bg-black/40 z-30 md:hidden" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
</body>
</html>
