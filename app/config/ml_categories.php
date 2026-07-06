<?php

declare(strict_types=1);

/**
 * Mapeo de categorías internas → categorías hoja de MercadoLibre.
 * MLA127680 (Productos de Limpieza) es padre y no admite publicaciones.
 */
return [
    /** Slug de categoría del catálogo → category_id ML (hoja) */
    'category_slug_map' => [
        'bidones-automotor' => 'MLA392349',
    ],

    /** Desengrasantes vehiculares — Accesorios para Vehículos > Limpieza de Vehículos */
    'vehicle_degreasers_category' => 'MLA392349',

    /** Fallback general de limpieza hogar (hoja, listing_allowed=true) */
    'general_cleaning_fallback' => 'MLA127688',
];
