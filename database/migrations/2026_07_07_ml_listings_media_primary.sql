-- Marca qué listing ML es la fuente de imágenes/descripción cuando un mismo producto
-- tiene más de una publicación activa (product_images y products.full_description son
-- por producto, no por listing — con 2+ listings hace falta elegir uno como "principal").

ALTER TABLE ml_listings
    ADD COLUMN is_media_primary TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = este listing es la fuente de imágenes/descripción cuando el producto tiene más de un listing ML' AFTER use_real_stock;
