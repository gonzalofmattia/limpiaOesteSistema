<?php
declare(strict_types=1);
/** @var string $status */
$statusKey = strtolower(trim((string) ($status ?? '')));
[$badgeClasses, $badgeLabel] = match ($statusKey) {
    'draft' => ['bg-gray-100 text-gray-700', 'Borrador'],
    'sent' => ['bg-blue-100 text-blue-700', 'Enviado'],
    'accepted' => ['bg-green-100 text-green-700', 'Aceptado'],
    'partially_delivered' => ['bg-yellow-100 text-yellow-700', 'Entrega parcial'],
    'delivered' => ['bg-emerald-100 text-emerald-700', 'Entregado'],
    'rejected' => ['bg-red-100 text-red-700', 'Rechazado'],
    'expired' => ['bg-gray-200 text-gray-500', 'Vencido'],
    default => ['bg-gray-100 text-gray-700', $statusKey !== '' ? statusLabel($statusKey) : '—'],
};
?>
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= e($badgeClasses) ?>"><?= e($badgeLabel) ?></span>
