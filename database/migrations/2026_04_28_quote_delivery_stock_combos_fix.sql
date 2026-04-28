-- Backfill idempotente para descuentos de stock en presupuestos delivered con combos.
-- Solo afecta quotes con delivery_stock_applied = 0.

UPDATE products p
INNER JOIN (
    SELECT x.product_id AS pid, SUM(x.units_to_discount) AS u
    FROM (
        SELECT qi.product_id,
               CASE
                   WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) = 'unidad' THEN qi.quantity
                   WHEN LOWER(TRIM(COALESCE(qi.unit_type, ''))) IN ('bidon', 'bidón', 'litro', 'sobre', 'bulto') THEN qi.quantity
                   ELSE qi.quantity * GREATEST(1, COALESCE(pr.units_per_box, 1))
               END AS units_to_discount
        FROM quote_items qi
        INNER JOIN quotes q ON q.id = qi.quote_id
        INNER JOIN products pr ON pr.id = qi.product_id
        WHERE q.status = 'delivered'
          AND IFNULL(q.delivery_stock_applied, 0) = 0
          AND qi.product_id IS NOT NULL

        UNION ALL

        SELECT cp.product_id,
               (qi.quantity * cp.quantity) AS units_to_discount
        FROM quote_items qi
        INNER JOIN quotes q ON q.id = qi.quote_id
        INNER JOIN combo_products cp ON cp.combo_id = qi.combo_id
        WHERE q.status = 'delivered'
          AND IFNULL(q.delivery_stock_applied, 0) = 0
          AND qi.combo_id IS NOT NULL
    ) x
    GROUP BY x.product_id
) t ON p.id = t.pid
SET p.stock_units = GREATEST(0, p.stock_units - t.u);

UPDATE quotes
SET delivery_stock_applied = 1
WHERE status = 'delivered'
  AND IFNULL(delivery_stock_applied, 0) = 0;
