-- Agrega stock persistido por producto (en unidades sueltas), de forma idempotente.
SET @has_stock_units = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'stock_units'
);

SET @sql_add_stock_units = IF(
    @has_stock_units = 0,
    'ALTER TABLE products ADD COLUMN stock_units INT NOT NULL DEFAULT 0 AFTER units_per_box',
    'SELECT 1'
);

PREPARE stmt_add_stock_units FROM @sql_add_stock_units;
EXECUTE stmt_add_stock_units;
DEALLOCATE PREPARE stmt_add_stock_units;
