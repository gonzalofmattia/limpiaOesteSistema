<?php
$query = (string) ($query ?? '');
$results = $results ?? ['products' => [], 'clients' => [], 'quotes' => [], 'categories' => []];
?>
<div class="space-y-4">
    <div class="lo-card p-4">
        <p class="text-sm text-slate-500">Resultados para</p>
        <p class="text-lg font-semibold"><?= e($query !== '' ? $query : '—') ?></p>
    </div>

    <?php
    $sections = [
        'products' => ['label' => 'Productos', 'icon' => 'package'],
        'clients' => ['label' => 'Clientes', 'icon' => 'users'],
        'quotes' => ['label' => 'Presupuestos', 'icon' => 'file-text'],
        'categories' => ['label' => 'Categorías', 'icon' => 'tags'],
    ];
    ?>
    <?php foreach ($sections as $key => $meta): ?>
        <?php $rows = $results[$key] ?? []; ?>
        <div class="lo-table-wrap">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
                <i data-lucide="<?= e($meta['icon']) ?>" class="h-4 w-4 text-slate-500"></i>
                <h3 class="text-sm font-semibold text-slate-800"><?= e($meta['label']) ?> (<?= count($rows) ?>)</h3>
            </div>
            <div class="divide-y divide-slate-100">
                <?php if ($rows === []): ?>
                    <p class="px-4 py-3 text-sm text-slate-500">Sin resultados.</p>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <a href="<?= e((string) ($r['url'] ?? '#')) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
                            <span class="text-sm text-slate-800">
                                <?= e((string) ($r['name'] ?? $r['quote_number'] ?? $r['code'] ?? '—')) ?>
                            </span>
                            <span class="text-xs text-slate-500">
                                <?= e((string) ($r['code'] ?? $r['phone'] ?? $r['quote_number'] ?? '')) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
