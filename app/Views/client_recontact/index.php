<?php
$enabled = $enabled ?? false;
$days = $days ?? 45;
$cooldownDays = $cooldownDays ?? 60;
$dailyLimit = $dailyLimit ?? 5;
$count = $count ?? 0;
$preview = $preview ?? [];
$history = $history ?? [];
$starProducts = $starProducts ?? [];
$statusBadge = static function (string $status): string {
    return match ($status) {
        'sent' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'claimed' => 'bg-blue-100 text-blue-800',
        'queued' => 'bg-slate-100 text-slate-700',
        default => 'bg-gray-200 text-gray-600',
    };
};
$statusLabel = static function (string $status): string {
    return match ($status) {
        'sent' => 'Enviado',
        'failed' => 'Fallido',
        'claimed' => 'Enviando...',
        'queued' => 'Pendiente',
        'cancelled' => 'Cancelado',
        default => ucfirst($status),
    };
};
$search = $search ?? '';
$searchResults = $searchResults ?? [];
?>
<div class="space-y-5">
    <div class="lo-card p-5">
        <p class="text-sm text-slate-600 mb-4">
            Le manda un mensaje de reposición a clientes activos que no compran hace un tiempo,
            mencionando lo que llevaron la última vez. Usa la misma cola y worker de WhatsApp
            que las campañas de prospección — mismo pacing (delays entre envíos, horario, tope diario)
            para no arriesgar el número.
        </p>
        <form method="post" action="<?= e(url('/prospeccion/recontacto-clientes/settings')) ?>" class="space-y-4">
            <?= csrfField() ?>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> class="h-4 w-4 rounded border-lo-border">
                Recontacto de clientes activo
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Días sin comprar</label>
                    <input type="number" name="days" value="<?= (int) $days ?>" min="7" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cooldown entre recontactos (días)</label>
                    <input type="number" name="cooldown_days" value="<?= (int) $cooldownDays ?>" min="7" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tope diario propio</label>
                    <input type="number" name="daily_limit" value="<?= (int) $dailyLimit ?>" min="1" max="25" class="w-full min-h-11 rounded-xl border border-lo-border px-3 text-sm">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>

    <div class="lo-card p-5">
        <p class="text-sm text-slate-600 mb-1">
            Matchean <strong><?= (int) $count ?></strong> clientes ahora mismo.
        </p>
        <?php if (!$enabled): ?>
            <p class="text-sm text-amber-600 mb-4">Activalo arriba para que empiece a encolar solos (respetando el tope diario).</p>
        <?php endif; ?>
        <?php if ($preview !== []): ?>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2 mt-3">Primeros <?= count($preview) ?> mensajes</p>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php foreach ($preview as $item): ?>
                    <div class="rounded-xl border border-slate-100 p-3">
                        <p class="text-sm font-medium text-slate-800"><?= e((string) $item['client']['name']) ?> <span class="text-slate-400 font-normal">· último pedido <?= e(date('d/m/Y', strtotime((string) $item['client']['last_order_at']))) ?></span></p>
                        <p class="text-sm text-slate-600 whitespace-pre-line mt-1"><?= e($item['rendered_body']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-500 mt-3">No hay clientes que matcheen el filtro ahora mismo.</p>
        <?php endif; ?>
    </div>

    <div class="lo-card p-5">
        <p class="text-sm font-semibold text-slate-800 mb-1">Historial de recontactos</p>
        <p class="text-xs text-slate-500 mb-4">Últimos 50, más reciente primero. "Enviado" confirma que el worker hizo click y el cuadro de WhatsApp quedó vacío.</p>
        <?php if ($history === []): ?>
            <p class="text-sm text-slate-500">Todavía no se mandó ningún recontacto.</p>
        <?php else: ?>
            <div class="lo-table-wrap hidden md:block">
                <table class="min-w-full text-sm lo-table">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3">Fecha</th>
                            <th class="text-left px-4 py-3">Cliente</th>
                            <th class="text-left px-4 py-3">Teléfono</th>
                            <th class="text-left px-4 py-3">Estado</th>
                            <th class="text-left px-4 py-3">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-slate-600"><?= e(date('d/m H:i', strtotime((string) ($h['sent_at'] ?? $h['created_at'])))) ?></td>
                                <td class="px-4 py-3"><a href="<?= e(url('/clientes/' . (int) $h['client_id'])) ?>" class="text-slate-900 hover:text-lo-blue hover:underline"><?= e((string) $h['client_name']) ?></a></td>
                                <td class="px-4 py-3 text-slate-600"><?= e((string) $h['phone']) ?></td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $h['status'])) ?>"><?= e($statusLabel((string) $h['status'])) ?></span></td>
                                <td class="px-4 py-3 text-red-600 text-xs"><?= e((string) ($h['error'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="md:hidden lo-mobile-card-list">
                <?php foreach ($history as $h): ?>
                    <article class="lo-mobile-card shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <p class="text-base font-semibold text-slate-900"><?= e((string) $h['client_name']) ?></p>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= e($statusBadge((string) $h['status'])) ?>"><?= e($statusLabel((string) $h['status'])) ?></span>
                        </div>
                        <p class="text-sm text-slate-500"><?= e((string) $h['phone']) ?> · <?= e(date('d/m H:i', strtotime((string) ($h['sent_at'] ?? $h['created_at'])))) ?></p>
                        <?php if (!empty($h['error'])): ?><p class="text-xs text-red-600 mt-1"><?= e((string) $h['error']) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="lo-card p-5">
        <p class="text-sm font-semibold text-slate-800 mb-1">Productos estrella para recomendar</p>
        <p class="text-xs text-slate-500 mb-4">
            No son "otro uso del mismo producto que ya compró" — son productos tuyos que querés ofrecerle
            aunque no los haya llevado nunca (ej. CP130, Strong, Quitasarro). El mensaje elige uno al azar
            entre estos (que el cliente no haya comprado ya en su último pedido) y lo suma como recomendación.
            Si no cargás ninguno, el mensaje usa un cierre genérico.
        </p>

        <?php if ($starProducts !== []): ?>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Estrella actuales</p>
            <div class="space-y-2 mb-5">
                <?php foreach ($starProducts as $p): ?>
                    <form method="post" action="<?= e(url('/prospeccion/recontacto-clientes/productos/' . (int) $p['id'])) ?>" class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <?= csrfField() ?>
                        <span class="text-sm text-slate-700 sm:w-56 shrink-0"><?= e((string) $p['name']) ?></span>
                        <input type="text" name="cross_sell_tip" value="<?= e((string) $p['cross_sell_tip']) ?>" class="flex-1 min-h-10 rounded-lg border border-lo-border px-3 text-sm">
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center justify-center px-3 py-2 bg-slate-600 text-white text-xs font-medium rounded-lg hover:bg-slate-700">Guardar</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Agregar un producto estrella</p>
        <form method="get" action="<?= e(url('/prospeccion/recontacto-clientes')) ?>" class="flex gap-2 mb-3">
            <input type="text" name="buscar" value="<?= e($search) ?>" placeholder="Buscar por nombre o código..." class="flex-1 min-h-10 rounded-lg border border-lo-border px-3 text-sm">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700">Buscar</button>
        </form>
        <?php if ($search !== ''): ?>
            <?php if ($searchResults !== []): ?>
                <div class="space-y-2">
                    <?php foreach ($searchResults as $p): ?>
                        <form method="post" action="<?= e(url('/prospeccion/recontacto-clientes/productos/' . (int) $p['id'])) ?>" class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <?= csrfField() ?>
                            <span class="text-sm text-slate-700 sm:w-56 shrink-0"><?= e((string) $p['name']) ?></span>
                            <input type="text" name="cross_sell_tip" value="<?= e((string) ($p['cross_sell_tip'] ?? '')) ?>" placeholder="Tip para recomendarlo (ej: nuestro Strong limpia el vidrio del horno como ningún otro)" class="flex-1 min-h-10 rounded-lg border border-lo-border px-3 text-sm">
                            <button type="submit" class="inline-flex items-center justify-center px-3 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700">Marcar estrella</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">No encontré productos con "<?= e($search) ?>".</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
