-- Vincula presupuestos (quotes) con órdenes de MercadoLibre
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'ml_order_id'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN ml_order_id VARCHAR(20) NULL AFTER ml_sale_total',
  'SELECT "ml_order_id ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND INDEX_NAME = 'idx_quotes_ml_order_id'
);

SET @sql := IF(
  @idx_exists = 0,
  'CREATE INDEX idx_quotes_ml_order_id ON quotes (ml_order_id)',
  'SELECT "idx_quotes_ml_order_id ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
