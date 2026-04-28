CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    difference INT NOT NULL,
    notes VARCHAR(255) NULL,
    created_by VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_stock_adjustments_product (product_id),
    INDEX idx_stock_adjustments_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
