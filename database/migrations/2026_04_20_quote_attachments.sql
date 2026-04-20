CREATE TABLE IF NOT EXISTS quote_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    type ENUM('remito', 'factura') NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_type (quote_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
