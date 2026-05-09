-- Papelería: units_per_box = 1 (unidad de venta = bolsón/pack completo)
-- Corrección de datos HB100 (product_id 237) y auditoría de stock.
-- Ejecutar con backup previo. Orden: TAREA 1 → TAREA 2 (2a→2d).

-- =============================================================================
-- TAREA 1: units_per_box y presentación de venta (excluye toallas intercaladas)
-- =============================================================================

UPDATE products
SET units_per_box = 1
WHERE category_id IN (24, 25, 23, 26, 27, 31, 32, 30, 33, 34)
  AND units_per_box > 1;

-- Etiqueta de venta: Bolsón / Pack según nombre o presentación
UPDATE products
SET sale_unit_label = CASE
        WHEN LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(presentation, ''))) LIKE '%bolsón%'
             OR LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(presentation, ''))) LIKE '%bolson%'
          THEN 'Bolsón'
        WHEN LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(presentation, ''))) LIKE '%pack%'
          THEN 'Pack'
        ELSE COALESCE(NULLIF(TRIM(sale_unit_label), ''), 'Pack')
    END
WHERE category_id IN (24, 25, 23, 26, 27, 31, 32, 30, 33, 34);

-- Descripción de venta vacía o genérica: derivar de presentación + contenido
UPDATE products
SET sale_unit_description = LEFT(
        TRIM(CONCAT_WS(' — ',
            NULLIF(TRIM(COALESCE(presentation, '')), ''),
            NULLIF(TRIM(COALESCE(content, '')), '')
        )),
        150
    )
WHERE category_id IN (24, 25, 23, 26, 27, 31, 32, 30, 33, 34)
  AND (
      sale_unit_description IS NULL
      OR TRIM(sale_unit_description) = ''
  );

-- sale_unit_type: no se fuerza aquí (se asume "caja" para packs; casos excepcionales revisar en ABM).

-- =============================================================================
-- TAREA 2a: Presupuestos LO-2026-0020 / LO-2026-0024 — ítem HB100 (×12 → ×1)
-- =============================================================================

UPDATE quote_items qi
JOIN quotes q ON qi.quote_id = q.id
SET qi.quantity = CEIL(qi.quantity / 12),
    qi.qty_delivered = qi.qty_delivered / 12,
    qi.subtotal = ROUND(qi.subtotal / 12, 2),
    qi.cost_subtotal_snapshot = CASE
        WHEN qi.cost_subtotal_snapshot IS NOT NULL THEN ROUND(qi.cost_subtotal_snapshot / 12, 2)
        ELSE qi.cost_subtotal_snapshot
    END
WHERE qi.product_id = 237
  AND q.quote_number IN ('LO-2026-0020', 'LO-2026-0024');

-- Recalcular cabecera: subtotal = suma de líneas; total = subtotal - descuento - crédito (iva_amount sin tocar: revisar en UI si aplica)
UPDATE quotes q
JOIN (
    SELECT quote_id, SUM(subtotal) AS sum_lines
    FROM quote_items
    GROUP BY quote_id
) s ON s.quote_id = q.id
SET q.subtotal = ROUND(s.sum_lines, 2),
    q.total = GREATEST(
        0,
        ROUND(
            s.sum_lines
            - COALESCE(q.discount_amount, 0)
            - COALESCE(q.credit_applied, 0),
            2
        )
    )
WHERE q.quote_number IN ('LO-2026-0020', 'LO-2026-0024');

-- =============================================================================
-- TAREA 2b: Pedido proveedor PH-2026-0002 — línea HB100
-- =============================================================================

UPDATE seiq_order_items soi
JOIN seiq_orders so ON soi.seiq_order_id = so.id
SET soi.units_per_box = 1,
    soi.total_units_needed = CEIL(soi.total_units_needed / 12),
    soi.qty_units_sold = CEIL(soi.qty_units_sold / 12)
WHERE soi.product_id = 237
  AND so.order_number = 'PH-2026-0002';

-- =============================================================================
-- TAREA 2c + 2d: Stock HB100 y registro auditable
-- =============================================================================

UPDATE products
SET stock_units = 0,
    stock_committed_units = 1,
    units_per_box = 1
WHERE id = 237;

INSERT INTO stock_adjustments (product_id, previous_stock, new_stock, difference, notes, created_by)
VALUES (
    237,
    12,
    0,
    -12,
    'Corrección: units_per_box 12→1; stock físico real 0 bolsones. Ref. migración papelería mayo 2026.',
    'migration 2026_05_09_papeleria_units_per_box_hb100.sql'
);
