-- Ejecutar en producción (phpMyAdmin) si pedidos-proveedor falla por columnas faltantes.
-- Idempotente: omitir cada ALTER si la columna ya existe.
-- Alternativa: ejecutar install.php o database/migrations/2026_05_13_seiq_orders_invoice_consolidation.sql

ALTER TABLE seiq_orders ADD COLUMN receipt_stock_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER received_at;
ALTER TABLE seiq_orders ADD COLUMN invoice_number VARCHAR(50) NULL AFTER receipt_stock_applied;
ALTER TABLE seiq_orders ADD COLUMN invoice_date DATE NULL AFTER invoice_number;
ALTER TABLE seiq_orders ADD COLUMN invoice_amount DECIMAL(12,2) NULL AFTER invoice_date;
ALTER TABLE seiq_orders ADD COLUMN received_with_order_id INT NULL AFTER invoice_amount;
ALTER TABLE seiq_orders ADD INDEX idx_received_with (received_with_order_id);
