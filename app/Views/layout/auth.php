<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar — LIMPIA OESTE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script>window.APP_BASE_URL = <?= json_encode(defined('BASE_URL') ? BASE_URL : '', JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body class="min-h-screen bg-[#F3F4F6] font-[Poppins,sans-serif] flex items-center justify-center p-4">
<?php $flash = getFlash(); ?>
<div class="w-full max-w-md">
    <?php if ($flash): ?>
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</div>
</body>
</html>
