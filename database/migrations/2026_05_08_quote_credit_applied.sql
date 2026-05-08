ALTER TABLE quotes
  ADD COLUMN credit_applied DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
  ADD COLUMN credit_transaction_id INT NULL DEFAULT NULL AFTER credit_applied;
