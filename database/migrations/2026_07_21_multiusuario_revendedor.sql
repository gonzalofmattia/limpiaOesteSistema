ALTER TABLE admin_users
    ADD COLUMN role ENUM('admin','revendedor') NOT NULL DEFAULT 'admin' AFTER username,
    ADD COLUMN full_name VARCHAR(120) NULL AFTER role,
    ADD COLUMN cost_multiplier DECIMAL(6,4) NOT NULL DEFAULT 1.0000 AFTER full_name,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER cost_multiplier;

UPDATE admin_users SET role = 'admin' WHERE 1;

ALTER TABLE quotes
    ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER client_id,
    ADD INDEX idx_quotes_owner_user_id (owner_user_id);

ALTER TABLE clients
    ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_clients_owner_user_id (owner_user_id);

ALTER TABLE price_lists
    ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_price_lists_owner_user_id (owner_user_id);

UPDATE quotes q, (SELECT id FROM admin_users WHERE role = 'admin' ORDER BY id LIMIT 1) au
    SET q.owner_user_id = au.id WHERE q.owner_user_id IS NULL;

UPDATE clients c, (SELECT id FROM admin_users WHERE role = 'admin' ORDER BY id LIMIT 1) au
    SET c.owner_user_id = au.id WHERE c.owner_user_id IS NULL;

UPDATE price_lists pl, (SELECT id FROM admin_users WHERE role = 'admin' ORDER BY id LIMIT 1) au
    SET pl.owner_user_id = au.id WHERE pl.owner_user_id IS NULL;
