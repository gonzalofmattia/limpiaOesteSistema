CREATE TABLE IF NOT EXISTS combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    markup_percentage DECIMAL(5,2) NOT NULL DEFAULT 90.00,
    subtotal_override DECIMAL(12,2) NULL,
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combo_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_combo_product (combo_id, product_id),
    INDEX idx_combo_products_combo (combo_id),
    INDEX idx_combo_products_product (product_id),
    CONSTRAINT fk_combo_products_combo FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    CONSTRAINT fk_combo_products_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @combo_id_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND COLUMN_NAME = 'combo_id'
);

SET @sql := IF(
  @combo_id_exists = 0,
  'ALTER TABLE quote_items ADD COLUMN combo_id INT NULL AFTER product_id',
  'SELECT "quote_items.combo_id ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @product_nullable := (
  SELECT IS_NULLABLE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND COLUMN_NAME = 'product_id'
  LIMIT 1
);

SET @sql := IF(
  @product_nullable = 'NO',
  'ALTER TABLE quote_items MODIFY COLUMN product_id INT NULL',
  'SELECT "quote_items.product_id ya permite NULL"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_combo_exists := (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND CONSTRAINT_NAME = 'fk_quote_items_combo'
);

SET @sql := IF(
  @fk_combo_exists = 0,
  'ALTER TABLE quote_items ADD CONSTRAINT fk_quote_items_combo FOREIGN KEY (combo_id) REFERENCES combos(id)',
  'SELECT "FK fk_quote_items_combo ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_combo_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND INDEX_NAME = 'idx_quote_items_combo'
);

SET @sql := IF(
  @idx_combo_exists = 0,
  'ALTER TABLE quote_items ADD INDEX idx_quote_items_combo (combo_id)',
  'SELECT "INDEX idx_quote_items_combo ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
