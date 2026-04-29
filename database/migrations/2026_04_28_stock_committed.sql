-- Agrega stock comprometido persistido por producto y realiza backfill desde presupuestos accepted.
-- El comprometido se mide en unidades sueltas (igual que stock_units).

SET @has_committed_units = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'stock_committed_units'
);

SET @sql_add_committed = IF(
    @has_committed_units = 0,
    'ALTER TABLE products ADD COLUMN stock_committed_units INT NOT NULL DEFAULT 0 AFTER stock_units',
    'SELECT 1'
);

PREPARE stmt_add_committed FROM @sql_add_committed;
EXECUTE stmt_add_committed;
DEALLOCATE PREPARE stmt_add_committed;

UPDATE products
SET stock_committed_units = 0;

UPDATE products p
INNER JOIN (
    SELECT x.product_id AS pid, SUM(x.units_to_commit) AS u
    FROM (
        SELECT qi.product_id,
               CASE
                   WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) = 'unidad' THEN qi.quantity
                   WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) IN ('bidon', 'bidón', 'litro', 'sobre', 'bulto') THEN qi.quantity
                   ELSE qi.quantity * GREATEST(1, COALESCE(pr.units_per_box, 1))
               END AS units_to_commit
        FROM quote_items qi
        INNER JOIN quotes q ON q.id = qi.quote_id
        INNER JOIN products pr ON pr.id = qi.product_id
        WHERE q.status = 'accepted'
          AND IFNULL(q.delivery_stock_applied, 0) = 0
          AND qi.product_id IS NOT NULL

        UNION ALL

        SELECT cp.product_id,
               (qi.quantity * cp.quantity) AS units_to_commit
        FROM quote_items qi
        INNER JOIN quotes q ON q.id = qi.quote_id
        INNER JOIN combo_products cp ON cp.combo_id = qi.combo_id
        WHERE q.status = 'accepted'
          AND IFNULL(q.delivery_stock_applied, 0) = 0
          AND qi.combo_id IS NOT NULL
    ) x
    GROUP BY x.product_id
) t ON p.id = t.pid
SET p.stock_committed_units = GREATEST(0, t.u);
