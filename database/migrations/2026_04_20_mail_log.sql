CREATE TABLE IF NOT EXISTS mail_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    attachment_id INT NULL,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
