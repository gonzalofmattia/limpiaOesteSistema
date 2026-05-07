-- Tolerancia visual de balance para clientes
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('balance_tolerance', '800', 'Tolerancia en pesos para considerar cliente al día (solo visual, el saldo real se mantiene)')
ON DUPLICATE KEY UPDATE setting_value = '800';
