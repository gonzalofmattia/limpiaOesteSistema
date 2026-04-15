-- Cuenta Corriente: movimientos unificados clientes / proveedor Seiq

CREATE TABLE IF NOT EXISTS account_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('client', 'supplier') NOT NULL,
    account_id INT NOT NULL,
    transaction_type ENUM('invoice', 'payment', 'adjustment') NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('efectivo', 'transferencia', 'otro') DEFAULT NULL,
    payment_reference VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    notes TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_type, account_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (transaction_date),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_client_balance = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clients'
    AND COLUMN_NAME = 'balance'
);
SET @sql_add_balance = IF(
  @has_client_balance = 0,
  'ALTER TABLE clients ADD COLUMN balance DECIMAL(12,2) DEFAULT 0 AFTER notes',
  'SELECT 1'
);
PREPARE stmt_add_balance FROM @sql_add_balance;
EXECUTE stmt_add_balance;
DEALLOCATE PREPARE stmt_add_balance;

-- Recalcula balance desde transacciones existentes si ya hay datos.
UPDATE clients c
LEFT JOIN (
    SELECT account_id,
           SUM(CASE WHEN transaction_type = 'invoice' THEN amount ELSE 0 END) AS invoices,
           SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS payments,
           SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) AS adjustments
    FROM account_transactions
    WHERE account_type = 'client'
    GROUP BY account_id
) t ON t.account_id = c.id
SET c.balance = COALESCE(t.invoices, 0) - COALESCE(t.payments, 0) + COALESCE(t.adjustments, 0);
