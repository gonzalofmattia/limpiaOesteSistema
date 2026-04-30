ALTER TABLE clients
    ADD COLUMN category VARCHAR(50) NULL AFTER city,
    ADD COLUMN assigned_pricelist VARCHAR(120) NULL AFTER category,
    ADD COLUMN total_purchases DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER balance,
    ADD COLUMN last_purchase_at DATETIME NULL AFTER total_purchases;
