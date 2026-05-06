-- Marca origen de línea de pedido proveedor: auto o manual.
ALTER TABLE seiq_order_items
    ADD COLUMN origin ENUM('auto','manual') NOT NULL DEFAULT 'auto' AFTER units_remainder;

