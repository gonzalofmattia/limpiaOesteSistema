SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'is_mercadolibre'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN is_mercadolibre TINYINT(1) NOT NULL DEFAULT 0 AFTER include_iva',
  'SELECT "is_mercadolibre ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'ml_net_amount'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN ml_net_amount DECIMAL(12,2) DEFAULT NULL AFTER is_mercadolibre',
  'SELECT "ml_net_amount ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'ml_sale_total'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN ml_sale_total DECIMAL(12,2) DEFAULT NULL AFTER ml_net_amount',
  'SELECT "ml_sale_total ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO clients (name, business_name, notes, is_active)
SELECT 'MercadoLibre', 'MercadoLibre', 'Cliente automático para ventas de MercadoLibre', 1
WHERE NOT EXISTS (
    SELECT 1 FROM clients WHERE LOWER(name) = 'mercadolibre'
);
