-- Soporte multi-proveedor: Seiq + Higienik

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    contact_name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    notes TEXT,
    cliente_id VARCHAR(50),
    cliente_nombre VARCHAR(255),
    condicion_pago VARCHAR(100),
    observaciones VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO suppliers (name, slug, contact_name, phone, cliente_id, cliente_nombre, condicion_pago, observaciones)
VALUES
('Seiq', 'seiq', 'Rodrigo', NULL, '15487', 'MATTIA GONZALO FRANCISCO', 'CONTADO CTA CTE', 'HAEDO - 29'),
('Higienik', 'higienik', 'Rodrigo', NULL, '15487', 'MATTIA GONZALO FRANCISCO', 'CONTADO CTA CTE', 'HAEDO - 29')
ON DUPLICATE KEY UPDATE
    contact_name = VALUES(contact_name),
    cliente_id = VALUES(cliente_id),
    cliente_nombre = VALUES(cliente_nombre),
    condicion_pago = VALUES(condicion_pago),
    observaciones = VALUES(observaciones);

ALTER TABLE categories ADD COLUMN supplier_id INT DEFAULT NULL AFTER parent_id;
ALTER TABLE categories ADD CONSTRAINT fk_supplier_category FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

SET @seiq_id = (SELECT id FROM suppliers WHERE slug = 'seiq');
UPDATE categories SET supplier_id = @seiq_id WHERE slug IN (
    'aerosoles', 'bidones', 'masivo', 'sobres', 'alimenticia',
    'insecticidas-concentrados', 'pouches-y-dispenser'
);
UPDATE categories c
JOIN categories parent ON c.parent_id = parent.id
SET c.supplier_id = @seiq_id
WHERE parent.slug = 'bidones';

SET @hig_id = (SELECT id FROM suppliers WHERE slug = 'higienik');
UPDATE categories
SET supplier_id = @hig_id
WHERE slug LIKE 'hig-%' OR slug LIKE 'fac-%'
   OR slug IN ('papelera-higienik', 'papelera-factory');
UPDATE categories c
JOIN categories parent ON c.parent_id = parent.id
SET c.supplier_id = @hig_id
WHERE parent.slug IN ('papelera-higienik', 'papelera-factory');

ALTER TABLE seiq_orders ADD COLUMN supplier_id INT DEFAULT NULL AFTER id;
ALTER TABLE seiq_orders ADD CONSTRAINT fk_order_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id);
UPDATE seiq_orders SET supplier_id = @seiq_id WHERE supplier_id IS NULL;

-- Si existe cuenta corriente de proveedor, reasigna el account_id.
SET @has_account_tx = (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'account_transactions'
);
SET @sql_account = IF(
  @has_account_tx > 0,
  CONCAT('UPDATE account_transactions SET account_id = ', @seiq_id, ' WHERE account_type = ''supplier'' AND account_id = 0'),
  'SELECT 1'
);
PREPARE stmt_account FROM @sql_account;
EXECUTE stmt_account;
DEALLOCATE PREPARE stmt_account;

DELETE FROM settings
WHERE setting_key IN (
    'seiq_cliente_id', 'seiq_cliente_nombre',
    'seiq_condicion_pago', 'seiq_observaciones'
);
