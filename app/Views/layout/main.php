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
            <a href="<?= e(url('/')) ?>" class="block px-4 py-2 <?= isActive('/') && !isActive('/categorias') && !isActive('/productos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Dashboard</a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Catálogo</p>
            <a href="<?= e(url('/categorias')) ?>" class="block px-4 py-2 <?= isActive('/categorias') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Categorías</a>
            <a href="<?= e(url('/productos')) ?>" class="block px-4 py-2 <?= isActive('/productos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Productos</a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Comercial</p>
            <a href="<?= e(url('/listas')) ?>" class="block px-4 py-2 <?= isActive('/listas') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Listas de precios</a>
            <a href="<?= e(url('/clientes')) ?>" class="block px-4 py-2 <?= isActive('/clientes') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Clientes</a>
            <a href="<?= e(url('/presupuestos')) ?>" class="block px-4 py-2 <?= isActive('/presupuestos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Presupuestos</a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Sistema</p>
            <a href="<?= e(url('/settings')) ?>" class="block px-4 py-2 <?= isActive('/settings') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">Configuración</a>
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
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
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
