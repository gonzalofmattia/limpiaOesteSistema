<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <p class="text-sm text-gray-500">Productos activos</p>
        <p class="text-2xl font-bold text-[#1a6b3c]"><?= (int) $productsActive ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <p class="text-sm text-gray-500">Categorías</p>
        <p class="text-2xl font-bold text-[#1565C0]"><?= (int) $categoriesCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <p class="text-sm text-gray-500">Clientes activos</p>
        <p class="text-2xl font-bold text-gray-800"><?= (int) $clientsCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <p class="text-sm text-gray-500">Presupuestos</p>
        <p class="text-2xl font-bold text-gray-800"><?= (int) $quotesCount ?></p>
    </div>
</div>

<?php if (!empty($accountsEnabled)): ?>
    <div class="grid sm:grid-cols-2 gap-4 mb-8">
        <a href="<?= e(url('/cuenta-corriente/clientes')) ?>" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm block hover:border-[#1a6b3c]">
            <p class="text-sm text-gray-500">A cobrar</p>
            <p class="text-2xl font-bold text-[#1a6b3c]"><?= formatPrice((float) $receivable) ?></p>
            <p class="text-sm text-gray-600"><?= (int) $clientsWithDebt ?> clientes</p>
        </a>
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500 mb-2">Deuda proveedores</p>
            <?php foreach (($supplierDebts ?? []) as $supplier): ?>
                <a href="<?= e(url('/cuenta-corriente/proveedor/' . (int) $supplier['id'])) ?>" class="flex justify-between text-sm py-1 hover:text-[#1565C0]">
                    <span><?= e($supplier['name']) ?></span>
                    <span class="font-medium"><?= formatPrice((float) $supplier['debt']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="flex flex-wrap gap-3 mb-8">
    <a href="<?= e(url('/productos/crear')) ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium hover:bg-[#2db368]">Nuevo producto</a>
    <a href="<?= e(url('/listas/generar')) ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 text-sm font-medium hover:bg-gray-50">Generar lista</a>
    <a href="<?= e(url('/presupuestos/crear')) ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-[#1565C0] text-white text-sm font-medium hover:bg-blue-700">Nuevo presupuesto</a>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-800">Categorías y descuentos Seiq</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-2">Nombre</th>
                        <th class="text-right px-4 py-2">Desc.</th>
                        <th class="text-right px-4 py-2">Productos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($catStats as $c): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><?= e($c['name']) ?></td>
                            <td class="px-4 py-2 text-right"><?= formatPercent((float) $c['default_discount']) ?></td>
                            <td class="px-4 py-2 text-right"><?= (int) $c['product_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h2 class="font-semibold text-gray-800">Últimos presupuestos</h2>
            <a href="<?= e(url('/presupuestos')) ?>" class="text-xs text-[#1565C0] hover:underline">Ver todos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-2">Número</th>
                        <th class="text-left px-4 py-2">Cliente</th>
                        <th class="text-right px-4 py-2">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$recentQuotes): ?>
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin presupuestos aún</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentQuotes as $q): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><a class="text-[#1565C0] hover:underline" href="<?= e(url('/presupuestos/' . (int) $q['id'])) ?>"><?= e($q['quote_number']) ?></a></td>
                            <td class="px-4 py-2"><?= e($q['client_name'] ?? '—') ?></td>
                            <td class="px-4 py-2 text-right"><?= formatPrice((float) $q['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
