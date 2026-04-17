-- Stock al recibir pedido a proveedor: flag en pedido + backfill idempotente
-- Ejecutar una vez en producción (re-ejecutar no duplica stock si receipt_stock_applied ya está en 1).

SET @has_receipt_flag = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seiq_orders'
      AND COLUMN_NAME = 'receipt_stock_applied'
);

SET @sql_add_receipt_flag = IF(
    @has_receipt_flag = 0,
    'ALTER TABLE seiq_orders ADD COLUMN receipt_stock_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER received_at',
    'SELECT 1'
);

PREPARE stmt_receipt_flag FROM @sql_add_receipt_flag;
EXECUTE stmt_receipt_flag;
DEALLOCATE PREPARE stmt_receipt_flag;

-- Suma unidades recibidas (cajas pedidas × unidades por caja) solo para pedidos ya «received» sin aplicar aún.
UPDATE products p
INNER JOIN (
    SELECT soi.product_id, SUM(soi.boxes_to_order * soi.units_per_box) AS add_units
    FROM seiq_order_items soi
    INNER JOIN seiq_orders so ON so.id = soi.seiq_order_id
    WHERE so.status = 'received'
      AND IFNULL(so.receipt_stock_applied, 0) = 0
    GROUP BY soi.product_id
) t ON p.id = t.product_id
SET p.stock_units = p.stock_units + t.add_units;

UPDATE seiq_orders
SET receipt_stock_applied = 1
WHERE status = 'received'
  AND IFNULL(receipt_stock_applied, 0) = 0;
