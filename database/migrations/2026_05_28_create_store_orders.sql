CREATE TABLE store_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(20) NOT NULL,
    
    -- Datos del cliente
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NULL,
    customer_phone VARCHAR(30) NOT NULL,
    
    -- Método de envío
    shipping_method ENUM('retiro', 'zona_propia', 'consultar') NOT NULL DEFAULT 'retiro',
    shipping_address TEXT NULL COMMENT 'Dirección completa si es zona_propia',
    shipping_locality VARCHAR(100) NULL,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- Método de pago
    payment_method ENUM('transferencia', 'mercadopago', 'efectivo') NOT NULL,
    
    -- Totales
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- Items (JSON snapshot)
    items_json TEXT NOT NULL COMMENT 'Snapshot de los items del carrito',
    
    -- Estado
    status ENUM('pending', 'confirmed', 'preparing', 'shipped', 'delivered', 'cancelled') 
        NOT NULL DEFAULT 'pending',
    
    -- Vinculación con el sistema
    quote_id INT NULL,
    client_id INT NULL,
    
    -- Notas
    customer_notes TEXT NULL,
    admin_notes TEXT NULL,
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uq_store_orders_number (order_number),
    KEY idx_store_orders_status (status),
    KEY idx_store_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
