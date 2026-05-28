-- is_featured ya existe en products (install.php). Solo agregar is_new:
ALTER TABLE products
    ADD COLUMN is_new TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured;
