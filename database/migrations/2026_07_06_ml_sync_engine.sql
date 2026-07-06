-- Motor de sincronización bidireccional ML <-> sistema (título, precio, stock, descripción, categoría, imágenes)
-- Ejecutar sobre la base existente. Si alguna columna ya existe, omitir solo ese ALTER (MySQL no soporta IF NOT EXISTS en ADD COLUMN de forma portable).

ALTER TABLE product_images
    ADD COLUMN source_hash VARCHAR(32) DEFAULT NULL COMMENT 'MD5 del contenido binario, para no re-descargar imágenes ya importadas' AFTER file_size;

ALTER TABLE product_images
    ADD COLUMN ml_picture_id VARCHAR(60) DEFAULT NULL COMMENT 'pictures[].id de MercadoLibre, para detectar drift sin descargar' AFTER source_hash;

CREATE TABLE IF NOT EXISTS ml_sync_snapshots (
    ml_listing_id       INT NOT NULL PRIMARY KEY,
    title                VARCHAR(60) NULL,
    price                DECIMAL(12,2) NULL,
    available_quantity   INT NULL,
    description_hash     CHAR(32) NULL,
    category_id           VARCHAR(20) NULL,
    images_id_list        TEXT NULL COMMENT 'JSON con la lista ordenada de ml_picture_id de la última sync aplicada',
    last_synced_at         TIMESTAMP NULL,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ml_listing_id) REFERENCES ml_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ml_sync_conflicts (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ml_listing_id    INT NOT NULL,
    field             VARCHAR(30) NOT NULL COMMENT 'title|price|available_quantity|description|category_id',
    ml_value          TEXT NULL,
    system_value      TEXT NULL,
    last_sync_value   TEXT NULL,
    detected_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at       TIMESTAMP NULL,
    resolution        ENUM('ml','sistema') NULL,
    resolved_by       VARCHAR(100) NULL,
    FOREIGN KEY (ml_listing_id) REFERENCES ml_listings(id) ON DELETE CASCADE,
    INDEX idx_listing_field (ml_listing_id, field),
    INDEX idx_pending (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
