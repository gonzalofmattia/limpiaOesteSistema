-- Recepción de pedidos con factura consolidada del proveedor
-- Permite recibir varios pedidos juntos cubiertos por una sola factura,
-- registrando un único asiento en account_transactions por el monto real.

SET @has_invoice_number = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seiq_orders'
      AND COLUMN_NAME = 'invoice_number'
);
SET @sql_add_invoice_number = IF(
    @has_invoice_number = 0,
    'ALTER TABLE seiq_orders ADD COLUMN invoice_number VARCHAR(50) NULL AFTER receipt_stock_applied',
    'SELECT 1'
);
PREPARE stmt_inv_num FROM @sql_add_invoice_number;
EXECUTE stmt_inv_num;
DEALLOCATE PREPARE stmt_inv_num;

SET @has_invoice_date = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seiq_orders'
      AND COLUMN_NAME = 'invoice_date'
);
SET @sql_add_invoice_date = IF(
    @has_invoice_date = 0,
    'ALTER TABLE seiq_orders ADD COLUMN invoice_date DATE NULL AFTER invoice_number',
    'SELECT 1'
);
PREPARE stmt_inv_date FROM @sql_add_invoice_date;
EXECUTE stmt_inv_date;
DEALLOCATE PREPARE stmt_inv_date;

SET @has_invoice_amount = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seiq_orders'
      AND COLUMN_NAME = 'invoice_amount'
);
SET @sql_add_invoice_amount = IF(
    @has_invoice_amount = 0,
    'ALTER TABLE seiq_orders ADD COLUMN invoice_amount DECIMAL(12,2) NULL AFTER invoice_date',
    'SELECT 1'
);
PREPARE stmt_inv_amount FROM @sql_add_invoice_amount;
EXECUTE stmt_inv_amount;
DEALLOCATE PREPARE stmt_inv_amount;

SET @has_received_with = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seiq_orders'
      AND COLUMN_NAME = 'received_with_order_id'
);
SET @sql_add_received_with = IF(
    @has_received_with = 0,
    'ALTER TABLE seiq_orders ADD COLUMN received_with_order_id INT NULL AFTER invoice_amount, ADD INDEX idx_received_with (received_with_order_id)',
    'SELECT 1'
);
PREPARE stmt_recv_with FROM @sql_add_received_with;
EXECUTE stmt_recv_with;
DEALLOCATE PREPARE stmt_recv_with;
