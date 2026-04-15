<?php

declare(strict_types=1);

return [
    ['GET', '', 'DashboardController@index', ['public' => false]],
    ['GET', 'login', 'AuthController@showLogin', ['public' => true]],
    ['POST', 'login', 'AuthController@login', ['public' => true]],
    ['GET', 'logout', 'AuthController@logout', ['public' => false]],

    ['GET', 'categorias', 'CategoryController@index', []],
    ['GET', 'categorias/crear', 'CategoryController@create', []],
    ['POST', 'categorias', 'CategoryController@store', []],
    ['GET', 'categorias/{id}/editar', 'CategoryController@edit', []],
    ['POST', 'categorias/{id}', 'CategoryController@update', []],
    ['POST', 'categorias/{id}/toggle', 'CategoryController@toggle', []],

    ['GET', 'productos', 'ProductController@index', []],
    ['GET', 'productos/crear', 'ProductController@create', []],
    ['POST', 'productos', 'ProductController@store', []],
    ['GET', 'productos/{id}/editar', 'ProductController@edit', []],
    ['POST', 'productos/{id}', 'ProductController@update', []],
    ['POST', 'productos/{id}/toggle', 'ProductController@toggle', []],
    ['GET', 'productos/importar/ejemplo', 'ProductController@downloadImportTemplate', []],
    ['GET', 'productos/importar', 'ProductController@importForm', []],
    ['POST', 'productos/importar', 'ProductController@import', []],
    ['POST', 'productos/importar-masivo', 'ProductController@importMultiSheet', []],

    ['GET', 'listas', 'PriceListController@index', []],
    ['GET', 'listas/generar', 'PriceListController@generateForm', []],
    ['POST', 'listas/preview', 'PriceListController@preview', []],
    ['POST', 'listas', 'PriceListController@store', []],
    ['GET', 'listas/{id}', 'PriceListController@show', []],
    ['GET', 'listas/{id}/pdf', 'PriceListController@downloadPdf', []],

    ['GET', 'clientes', 'ClientController@index', []],
    ['GET', 'clientes/crear', 'ClientController@create', []],
    ['POST', 'clientes', 'ClientController@store', []],
    ['GET', 'clientes/{id}/editar', 'ClientController@edit', []],
    ['POST', 'clientes/{id}', 'ClientController@update', []],

    ['GET', 'presupuestos', 'QuoteController@index', []],
    ['GET', 'presupuestos/crear', 'QuoteController@create', []],
    ['POST', 'presupuestos', 'QuoteController@store', []],
    ['GET', 'presupuestos/{id}', 'QuoteController@show', []],
    ['GET', 'presupuestos/{id}/editar', 'QuoteController@edit', []],
    ['POST', 'presupuestos/{id}', 'QuoteController@update', []],
    ['GET', 'presupuestos/{id}/pdf', 'QuoteController@downloadPdf', []],
    ['POST', 'presupuestos/{id}/status', 'QuoteController@changeStatus', []],

    ['GET', 'pedido-seiq', 'SeiqOrderController@index', []],
    ['GET', 'pedido-seiq/generar', 'SeiqOrderController@generate', []],
    ['POST', 'pedido-seiq', 'SeiqOrderController@store', []],
    ['GET', 'pedido-seiq/{id}', 'SeiqOrderController@show', []],
    ['GET', 'pedido-seiq/{id}/pdf', 'SeiqOrderController@downloadPdf', []],
    ['POST', 'pedido-seiq/{id}/status', 'SeiqOrderController@changeStatus', []],
    ['POST', 'pedido-seiq/{id}/quotes-delivered', 'SeiqOrderController@markQuotesDelivered', []],
    ['GET', 'pedidos-proveedor', 'SeiqOrderController@index', []],
    ['GET', 'pedidos-proveedor/generar', 'SeiqOrderController@generate', []],
    ['POST', 'pedidos-proveedor', 'SeiqOrderController@store', []],
    ['GET', 'pedidos-proveedor/{id}', 'SeiqOrderController@show', []],
    ['GET', 'pedidos-proveedor/{id}/pdf', 'SeiqOrderController@downloadPdf', []],
    ['POST', 'pedidos-proveedor/{id}/status', 'SeiqOrderController@changeStatus', []],
    ['POST', 'pedidos-proveedor/{id}/quotes-delivered', 'SeiqOrderController@markQuotesDelivered', []],

    ['GET', 'settings', 'SettingsController@index', []],
    ['POST', 'settings', 'SettingsController@update', []],

    ['GET', 'api/productos/buscar', 'ApiController@searchProducts', []],
    ['GET', 'api/productos/{id}/precio', 'ApiController@getProductPrice', []],
    ['GET', 'api/categorias/{id}/productos', 'ApiController@getCategoryProducts', []],
    ['POST', 'api/pricing/preview', 'ApiController@previewPricing', []],
];
