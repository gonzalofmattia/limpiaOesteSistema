-- Migracion: Recalcular stock comprometido basado en presupuestos accepted
-- Fecha: 2026-04-30
-- Contexto: Bug E - la transicion accepted->delivered no liberaba stock comprometido
--
-- Estructura real detectada:
-- - quote_items guarda lineas directas (product_id) y lineas de combo (combo_id).
-- - Los componentes de combo se obtienen via combo_products (combo_id, product_id, quantity).
-- - stock_committed_units se mide en unidades sueltas (misma unidad que stock_units).

START TRANSACTION;

-- PASO 1: Resetear stock_committed_units a 0 para todos los productos
UPDATE products
SET stock_committed_units = 0;

-- PASO 2 + PASO 3: Recalcular comprometido accepted (directos + combos descompuestos)
DROP TEMPORARY TABLE IF EXISTS tmp_accepted_units_by_product;
CREATE TEMPORARY TABLE tmp_accepted_units_by_product (
    product_id INT NOT NULL PRIMARY KEY,
    units INT NOT NULL
);

INSERT INTO tmp_accepted_units_by_product (product_id, units)
SELECT t.product_id, SUM(t.units) AS units
FROM (
    -- Items directos de producto
    SELECT
        qi.product_id AS product_id,
        CASE
            WHEN COALESCE(qi.unit_type, 'caja') = 'unidad'
                THEN qi.quantity
            ELSE qi.quantity * GREATEST(1, COALESCE(p.units_per_box, 1))
        END AS units
    FROM quote_items qi
    JOIN quotes q ON q.id = qi.quote_id
    JOIN products p ON p.id = qi.product_id
    WHERE q.status = 'accepted'
      AND qi.product_id IS NOT NULL

    UNION ALL

    -- Items de combo descompuestos a producto
    SELECT
        cp.product_id AS product_id,
        (qi.quantity * cp.quantity) AS units
    FROM quote_items qi
    JOIN quotes q ON q.id = qi.quote_id
    JOIN combo_products cp ON cp.combo_id = qi.combo_id
    WHERE q.status = 'accepted'
      AND qi.combo_id IS NOT NULL
) t
GROUP BY t.product_id;

UPDATE products p
JOIN tmp_accepted_units_by_product t ON t.product_id = p.id
SET p.stock_committed_units = GREATEST(0, t.units);

-- PASO 4: Verificacion
SELECT
    p.code,
    p.name,
    p.stock_units,
    p.stock_committed_units,
    (p.stock_units - p.stock_committed_units) AS disponible
FROM products p
WHERE p.stock_units > 0 OR p.stock_committed_units > 0
ORDER BY p.name;

COMMIT;
