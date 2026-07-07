CREATE TABLE prospect_responses (
    id INT NOT NULL AUTO_INCREMENT,
    prospect_id INT NOT NULL,
    body TEXT NOT NULL,
    received_at DATETIME NOT NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_prospect_responses_prospect_received (prospect_id, received_at),
    CONSTRAINT fk_prospect_responses_prospect FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
