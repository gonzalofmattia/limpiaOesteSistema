<?php

declare(strict_types=1);

/**
 * Whitelist de rutas por rol (usado por Auth::canAccess()). Los patrones son los mismos
 * declarados en config/routes.php (sin slash inicial). Un patrón terminado en "*" matchea
 * por prefijo; method '*' matchea cualquier verbo HTTP.
 *
 * Solo aplica al rol 'revendedor' — 'admin' tiene acceso total (ver Auth::canAccess()).
 * Todo lo que no está listado acá queda bloqueado (403) para revendedor.
 */
return [
    'revendedor' => [
        ['method' => 'GET', 'pattern' => ''],
        ['method' => 'GET', 'pattern' => 'logout'],
        ['method' => 'GET', 'pattern' => 'dashboard/detalle/{slug}'],
        ['method' => 'GET', 'pattern' => 'buscar'],
        ['method' => 'GET', 'pattern' => 'productos'],
        ['method' => 'GET', 'pattern' => 'productos/{id}'],
        ['method' => '*', 'pattern' => 'listas*'],
        ['method' => '*', 'pattern' => 'clientes*'],
        ['method' => '*', 'pattern' => 'presupuestos*'],
        ['method' => 'GET', 'pattern' => 'stock-actual'],
        ['method' => 'GET', 'pattern' => 'stock-actual/reposicion'],
        ['method' => 'GET', 'pattern' => 'stock/reorder-suggestion'],
        ['method' => '*', 'pattern' => 'api/*'],
    ],
];
