CREATE TABLE outreach_campaigns (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    template_id INT NOT NULL,
    filter_business_type VARCHAR(30) DEFAULT NULL,
    filter_city VARCHAR(100) DEFAULT NULL,
    filter_status VARCHAR(30) NOT NULL DEFAULT 'nuevo',
    daily_limit INT NOT NULL DEFAULT 15,
    status ENUM('borrador', 'activa', 'pausada', 'finalizada') NOT NULL DEFAULT 'borrador',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_outreach_campaigns_status (status),
    CONSTRAINT fk_outreach_campaigns_template FOREIGN KEY (template_id) REFERENCES outreach_templates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
