SET @has_sale_number := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND COLUMN_NAME = 'sale_number'
);

SET @sql_sale_col := IF(
  @has_sale_number = 0,
  'ALTER TABLE quotes ADD COLUMN sale_number VARCHAR(20) NULL AFTER quote_number',
  'SELECT "sale_number ya existe"'
);
PREPARE stmt_sale_col FROM @sql_sale_col;
EXECUTE stmt_sale_col;
DEALLOCATE PREPARE stmt_sale_col;

SET @has_sale_number_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quotes'
    AND INDEX_NAME = 'idx_sale_number'
);

SET @sql_sale_idx := IF(
  @has_sale_number_idx = 0,
  'ALTER TABLE quotes ADD UNIQUE INDEX idx_sale_number (sale_number)',
  'SELECT "idx_sale_number ya existe"'
);
PREPARE stmt_sale_idx FROM @sql_sale_idx;
EXECUTE stmt_sale_idx;
DEALLOCATE PREPARE stmt_sale_idx;

INSERT INTO settings (setting_key, setting_value, description)
SELECT 'sale_prefix', 'V-', 'Prefijo para numeracion de ventas'
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'sale_prefix'
);

SET @sale_prefix_value := (
  SELECT setting_value
  FROM settings
  WHERE setting_key = 'sale_prefix'
  LIMIT 1
);
SET @sale_prefix_value := IFNULL(NULLIF(TRIM(@sale_prefix_value), ''), 'V-');

SET @rownum := 0;
UPDATE quotes q
JOIN (
  SELECT id, (@rownum := @rownum + 1) AS seq
  FROM quotes
  WHERE status IN ('accepted', 'delivered')
    AND (sale_number IS NULL OR sale_number = '')
  ORDER BY id ASC
) t ON t.id = q.id
SET q.sale_number = CONCAT(@sale_prefix_value, LPAD(t.seq, 4, '0'));

SET @has_cost_unit_snapshot := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND COLUMN_NAME = 'cost_unit_snapshot'
);
SET @sql_cost_unit_snapshot := IF(
  @has_cost_unit_snapshot = 0,
  'ALTER TABLE quote_items ADD COLUMN cost_unit_snapshot DECIMAL(12,2) NULL AFTER markup_applied',
  'SELECT "cost_unit_snapshot ya existe"'
);
PREPARE stmt_cost_unit_snapshot FROM @sql_cost_unit_snapshot;
EXECUTE stmt_cost_unit_snapshot;
DEALLOCATE PREPARE stmt_cost_unit_snapshot;

SET @has_cost_subtotal_snapshot := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote_items'
    AND COLUMN_NAME = 'cost_subtotal_snapshot'
);
SET @sql_cost_subtotal_snapshot := IF(
  @has_cost_subtotal_snapshot = 0,
  'ALTER TABLE quote_items ADD COLUMN cost_subtotal_snapshot DECIMAL(12,2) NULL AFTER cost_unit_snapshot',
  'SELECT "cost_subtotal_snapshot ya existe"'
);
PREPARE stmt_cost_subtotal_snapshot FROM @sql_cost_subtotal_snapshot;
EXECUTE stmt_cost_subtotal_snapshot;
DEALLOCATE PREPARE stmt_cost_subtotal_snapshot;

SET @iva_rate_setting := (
  SELECT setting_value
  FROM settings
  WHERE setting_key = 'iva_rate'
  LIMIT 1
);
SET @iva_rate := IFNULL(NULLIF(@iva_rate_setting, ''), '21');
SET @iva_divisor := 1 + (CAST(@iva_rate AS DECIMAL(10,4)) / 100);
SET @iva_divisor := IF(@iva_divisor <= 0, 1.21, @iva_divisor);

UPDATE quote_items qi
JOIN quotes q ON q.id = qi.quote_id
SET
  qi.cost_unit_snapshot = ROUND(
    (
      CASE
        WHEN q.include_iva = 1 THEN (qi.unit_price / @iva_divisor)
        ELSE qi.unit_price
      END
    ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
  , 2),
  qi.cost_subtotal_snapshot = ROUND(
    (
      CASE
        WHEN q.include_iva = 1 THEN (qi.subtotal / @iva_divisor)
        ELSE qi.subtotal
      END
    ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
  , 2)
WHERE qi.cost_unit_snapshot IS NULL
   OR qi.cost_subtotal_snapshot IS NULL;
