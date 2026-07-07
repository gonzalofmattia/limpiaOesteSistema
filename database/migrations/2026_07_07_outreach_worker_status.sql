CREATE TABLE outreach_worker_status (
    id INT NOT NULL,
    last_heartbeat DATETIME DEFAULT NULL,
    worker_version VARCHAR(20) DEFAULT NULL,
    messages_sent_today INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO outreach_worker_status (id, messages_sent_today) VALUES (1, 0);
