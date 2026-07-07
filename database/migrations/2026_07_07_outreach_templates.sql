CREATE TABLE outreach_templates (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    business_type ENUM('parrilla','panaderia','restaurante','bar','hotel','clinica','escuela','revendedor','otro','todos') NOT NULL DEFAULT 'todos',
    stage ENUM('primer_contacto','seguimiento_7d','recontacto') NOT NULL,
    body TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_outreach_templates_stage (stage),
    KEY idx_outreach_templates_business_type (business_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
