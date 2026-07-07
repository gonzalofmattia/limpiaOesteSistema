<?php

declare(strict_types=1);

/**
 * Mapeo de categorías internas → categorías hoja de MercadoLibre.
 * MLA127680 (Productos de Limpieza) es padre y no admite publicaciones.
 */
return [
    /**
     * Slug de categoría del catálogo → category_id ML (hoja).
     * 'bidones-automotor' apuntaba a MLA392349 ("Accesorios para Vehículos > Limpieza de
     * Vehículos"), pero ML ya no acepta publicaciones ahí vía API: exige el atributo
     * PART_NUMBER (repuestos) que no podemos mapear, y no admite envío me2 (este negocio
     * publica siempre con me2, nunca me1 — ver buildPublishShipping()). Apunta al fallback
     * general hasta encontrar una categoría hoja real para limpieza vehicular vía
     * GET /sites/MLA/domain_discovery/search?q=... (nunca asumir por nombre de categoría padre).
     */
    'category_slug_map' => [
        'bidones-automotor' => 'MLA127688',
    ],

    /**
     * Desengrasantes vehiculares — mismo motivo que arriba: MLA392349 quedó inutilizable
     * (PART_NUMBER obligatorio + solo me1), se redirige al fallback general hasta reemplazarla.
     */
    'vehicle_degreasers_category' => 'MLA127688',

    /** Fallback general de limpieza hogar (hoja, listing_allowed=true) */
    'general_cleaning_fallback' => 'MLA127688',
];
