<?php
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($per_page ?? 20));
$total = max(0, (int) ($total ?? 0));
$totalPages = max(1, (int) ($total_pages ?? 1));
$start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$end = $total > 0 ? min($total, $page * $perPage) : 0;
$query = $_GET;
unset($query['page']);
$basePath = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/';
$buildUrl = static function (int $targetPage) use ($query, $basePath): string {
    $params = $query;
    $params['page'] = max(1, $targetPage);
    return $basePath . '?' . http_build_query($params);
};
$window = 2;
$from = max(1, $page - $window);
$to = min($totalPages, $page + $window);
?>
<div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm text-gray-600">Mostrando <?= $start ?>-<?= $end ?> de <?= $total ?> resultados</p>
    <?php if ($totalPages > 1): ?>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="<?= e($buildUrl(1)) ?>" class="px-3 py-1.5 rounded border border-gray-300 <?= $page === 1 ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>">« Primera</a>
            <a href="<?= e($buildUrl($page - 1)) ?>" class="px-3 py-1.5 rounded border border-gray-300 <?= $page === 1 ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>">‹ Anterior</a>
            <?php for ($i = $from; $i <= $to; $i++): ?>
                <a href="<?= e($buildUrl($i)) ?>" class="px-3 py-1.5 rounded border <?= $i === $page ? 'bg-[#1a6b3c] text-white border-[#1a6b3c]' : 'border-gray-300 hover:bg-gray-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= e($buildUrl($page + 1)) ?>" class="px-3 py-1.5 rounded border border-gray-300 <?= $page === $totalPages ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>">Siguiente ›</a>
            <a href="<?= e($buildUrl($totalPages)) ?>" class="px-3 py-1.5 rounded border border-gray-300 <?= $page === $totalPages ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>">Última »</a>
        </div>
    <?php endif; ?>
</div>
