-- Stock al entregar presupuesto (cliente): flag en quotes + backfill idempotente
-- Re-ejecutar: solo afecta presupuestos delivered con delivery_stock_applied = 0.

SET @has_delivery_flag = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quotes'
      AND COLUMN_NAME = 'delivery_stock_applied'
);

SET @sql_add_delivery_flag = IF(
    @has_delivery_flag = 0,
    'ALTER TABLE quotes ADD COLUMN delivery_stock_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER pdf_path',
    'SELECT 1'
);

PREPARE stmt_delivery_flag FROM @sql_add_delivery_flag;
EXECUTE stmt_delivery_flag;
DEALLOCATE PREPARE stmt_delivery_flag;

-- Misma regla que QuoteLinePricing::normalizeUnitType (caja vs unidad / bidón / litro / sobre / bulto).
UPDATE products p
INNER JOIN (
    SELECT qi.product_id AS pid, SUM(
        CASE
            WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) = 'unidad' THEN qi.quantity
            WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) IN ('bidon', 'bidón', 'litro', 'sobre', 'bulto') THEN qi.quantity
            ELSE qi.quantity * GREATEST(1, COALESCE(pr.units_per_box, 1))
        END
    ) AS u
    FROM quote_items qi
    INNER JOIN quotes q ON q.id = qi.quote_id
    INNER JOIN products pr ON pr.id = qi.product_id
    WHERE q.status = 'delivered'
      AND IFNULL(q.delivery_stock_applied, 0) = 0
    GROUP BY qi.product_id
) t ON p.id = t.pid
SET p.stock_units = GREATEST(0, p.stock_units - t.u);

UPDATE quotes
SET delivery_stock_applied = 1
WHERE status = 'delivered'
  AND IFNULL(delivery_stock_applied, 0) = 0;
