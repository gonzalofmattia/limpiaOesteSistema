SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'discount_percentage'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN discount_percentage DECIMAL(5,2) DEFAULT NULL AFTER subtotal',
  'SELECT "discount_percentage ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'discount_amount'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE quotes ADD COLUMN discount_amount DECIMAL(12,2) DEFAULT NULL AFTER discount_percentage',
  'SELECT "discount_amount ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
