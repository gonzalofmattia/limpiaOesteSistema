-- Pedido a Seiq: tablas, settings y estado delivered en presupuestos
-- Ejecutar sobre la base existente o usar install.php (incluye lo mismo).

INSERT INTO settings (setting_key, setting_value, description) VALUES
('seiq_cliente_id', '15487', 'ID de cliente en Seiq'),
('seiq_cliente_nombre', 'MATTIA GONZALO FRANCISCO', 'Nombre registrado en Seiq'),
('seiq_condicion_pago', 'CONTADO CTA CTE', 'Condición de pago con Seiq'),
('seiq_observaciones', 'HAEDO - 29', 'Observaciones para el pedido')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description);

ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','expired','delivered') DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS seiq_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    notes TEXT,
    included_quotes TEXT,
    total_products INT DEFAULT 0,
    total_boxes INT DEFAULT 0,
    status ENUM('draft','sent','received') DEFAULT 'draft',
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seiq_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seiq_order_id INT NOT NULL,
    product_id INT NOT NULL,
    qty_units_sold INT NOT NULL DEFAULT 0,
    qty_boxes_sold INT NOT NULL DEFAULT 0,
    total_units_needed INT NOT NULL DEFAULT 0,
    units_per_box INT NOT NULL DEFAULT 1,
    boxes_to_order INT NOT NULL DEFAULT 0,
    units_remainder INT NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (seiq_order_id) REFERENCES seiq_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (seiq_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
