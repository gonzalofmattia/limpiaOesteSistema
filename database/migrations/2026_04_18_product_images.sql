-- Imágenes de productos + campos para catálogo web
-- Ejecutar sobre la base existente. Si alguna columna ya existe, omitir solo ese ALTER (MySQL no soporta IF NOT EXISTS en ADD COLUMN de forma portable).

CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL COMMENT 'en bytes',
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_cover TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = imagen principal',
    alt_text VARCHAR(255) DEFAULT NULL COMMENT 'texto alternativo para catálogo web',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_cover (product_id, is_cover),
    INDEX idx_product_sort (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos catálogo (verificar con DESCRIBE products antes de aplicar; omitir líneas duplicadas)
ALTER TABLE products
    ADD COLUMN short_description VARCHAR(255) DEFAULT NULL COMMENT 'descripción corta para catálogo' AFTER description;

ALTER TABLE products
    ADD COLUMN full_description TEXT DEFAULT NULL COMMENT 'descripción larga para catálogo' AFTER short_description;

ALTER TABLE products
    ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = visible en catálogo público' AFTER is_featured;

ALTER TABLE products
    ADD COLUMN slug VARCHAR(255) DEFAULT NULL COMMENT 'URL amigable para catálogo' AFTER name;

ALTER TABLE products
    ADD COLUMN content_volume VARCHAR(50) DEFAULT NULL COMMENT 'ej: 5 Lts, 900cc, 360ML' AFTER presentation;

-- Settings opcionales: markup % forzado solo para API catálogo (vacío = usa reglas normales del PricingEngine)
INSERT INTO settings (setting_key, setting_value, description)
VALUES
    ('catalog_markup_mayorista', '', 'Markup % fijo para precio mayorista en API catálogo (vacío = efectivo producto/categoría/global)'),
    ('catalog_markup_minorista', '', 'Markup % fijo para precio minorista en API catálogo (vacío = mismo cálculo que mayorista)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Opcional (mejora consultas catálogo): CREATE INDEX idx_products_slug ON products (slug(191));
