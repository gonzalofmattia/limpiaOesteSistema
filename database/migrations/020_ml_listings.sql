-- Tabla principal de listings ML
CREATE TABLE IF NOT EXISTS ml_listings (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    product_id                  INT NULL,
    combo_id                    INT NULL,
    ml_item_id                  VARCHAR(20) NULL,           -- MLA12345678 (null hasta publicar)
    ml_category_id              VARCHAR(20) NULL,           -- MLA1234 (categoría ML)
    title                       VARCHAR(60) NOT NULL,       -- Título optimizado ML (máx 60 chars)
    status                      ENUM('draft','active','paused','closed') DEFAULT 'draft',
    listing_type_id             VARCHAR(20) DEFAULT 'gold_special', -- Clásica
    price                       DECIMAL(12,2) NULL,         -- Precio publicado en ML
    ml_markup                   DECIMAL(5,2) NULL,          -- Markup aplicado (nullable = usa el global)
    available_quantity_override INT DEFAULT 12,             -- Cantidad fija hoy; futuro: NULL = usar stock real
    use_real_stock              TINYINT(1) DEFAULT 0,       -- Flag para activar stock real en el futuro
    ml_permalink                VARCHAR(255) NULL,          -- URL pública del listing
    ml_thumbnail                VARCHAR(255) NULL,          -- URL foto principal en ML
    last_synced_at              TIMESTAMP NULL,
    last_sync_error             TEXT NULL,                  -- Mensaje de error de última sync fallida
    notes                       TEXT NULL,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (combo_id)   REFERENCES combos(id)   ON DELETE SET NULL,
    UNIQUE KEY unique_ml_item   (ml_item_id),
    INDEX idx_status            (status),
    INDEX idx_product           (product_id),
    INDEX idx_combo             (combo_id)
);

-- Settings nuevas para ML (insertar en tabla settings existente)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('ml_app_id',            ''),
    ('ml_client_secret',     ''),
    ('ml_access_token',      ''),
    ('ml_refresh_token',     ''),
    ('ml_user_id',           ''),
    ('ml_token_expires_at',  ''),
    ('ml_default_markup',    '75'),
    ('ml_absorb_commission', '0'),   -- 0 = el 12% lo paga Limpia Oeste; 1 = se suma al precio
    ('ml_site_id',           'MLA'),
    ('ml_default_quantity',  '12');
