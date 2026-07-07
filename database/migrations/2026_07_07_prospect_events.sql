CREATE TABLE prospect_events (
    id INT NOT NULL AUTO_INCREMENT,
    prospect_id INT NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    detail TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_prospect_events_prospect_created (prospect_id, created_at),
    CONSTRAINT fk_prospect_events_prospect FOREIGN KEY (prospect_id) REFERENCES prospects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
