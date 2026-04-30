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
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
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
<body
    class="bg-[#F3F4F6] text-[#111827] font-sans antialiased"
    x-data="{
        sidebarOpen: false,
        deleteModal: { open: false, formId: '', itemName: 'este registro' },
        openDeleteModal(formId, itemName) {
            this.deleteModal.formId = formId;
            this.deleteModal.itemName = itemName || 'este registro';
            this.deleteModal.open = true;
        }
    }"
>
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
                <i data-lucide="house" class="w-5 h-5 shrink-0"></i>
                Dashboard
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Catálogo</p>
            <a href="<?= e(url('/categorias')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/categorias') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="tags" class="w-5 h-5 shrink-0"></i>
                Categorías
            </a>
            <a href="<?= e(url('/productos')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/productos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="package" class="w-5 h-5 shrink-0"></i>
                Productos
            </a>
            <a href="<?= e(url('/stock-actual')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/stock-actual') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="boxes" class="w-5 h-5 shrink-0"></i>
                Stock actual
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Comercial</p>
            <a href="<?= e(url('/listas')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/listas') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="clipboard-list" class="w-5 h-5 shrink-0"></i>
                Listas de precios
            </a>
            <a href="<?= e(url('/clientes')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/clientes') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="users" class="w-5 h-5 shrink-0"></i>
                Clientes
            </a>
            <a href="<?= e(url('/presupuestos')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/presupuestos') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="file-text" class="w-5 h-5 shrink-0"></i>
                Presupuestos
            </a>
            <a href="<?= e(url('/ventas')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/ventas') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="receipt" class="w-5 h-5 shrink-0"></i>
                Ventas
            </a>
            <a href="<?= e(url('/ventas-ml')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/ventas-ml') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="store" class="w-5 h-5 shrink-0"></i>
                Ventas ML
            </a>
            <a href="<?= e(url('/pedidos-proveedor')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/pedido-seiq') || isActive('/pedidos-proveedor') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="clipboard-check" class="w-5 h-5 shrink-0"></i>
                Pedidos a Proveedores
            </a>
            <a href="<?= e(url('/cuenta-corriente')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/cuenta-corriente') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="wallet" class="w-5 h-5 shrink-0"></i>
                Cuenta Corriente
            </a>
            <p class="px-4 pt-4 pb-1 text-xs text-gray-500 uppercase">Sistema</p>
            <a href="<?= e(url('/settings')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/settings') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="settings" class="w-5 h-5 shrink-0"></i>
                Configuración
            </a>
            <a href="<?= e(url('/sincronizacion')) ?>" class="flex items-center gap-2 px-4 py-2 <?= isActive('/sincronizacion') ? 'bg-primary/20 border-l-4 border-primary-light text-white' : 'hover:bg-gray-800 border-l-4 border-transparent' ?>">
                <i data-lucide="refresh-cw" class="w-5 h-5 shrink-0"></i>
                Sincronización
            </a>
        </nav>
        <div class="px-4 pb-3 border-t border-gray-800">
            <a href="<?= e(url('/logout')) ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm text-gray-400 hover:text-red-400 hover:bg-gray-800 rounded-lg transition">
                <i data-lucide="log-out" class="w-5 h-5 shrink-0"></i>
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
<?php require APP_PATH . '/Views/layout/delete_modal.php'; ?>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<script>
    if (window.lucide) {
        window.lucide.createIcons();
    }
    document.addEventListener('alpine:initialized', function () {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
</script>
</body>
</html>
