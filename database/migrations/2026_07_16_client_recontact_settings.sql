INSERT INTO settings (setting_key, setting_value, description) VALUES
('client_recontact_enabled', '0', 'Recontacto automatico a clientes activo (1/0)'),
('client_recontact_days', '45', 'Dias sin comprar para que un cliente entre en el recontacto automatico'),
('client_recontact_cooldown_days', '60', 'Dias minimos entre dos recontactos automaticos al mismo cliente'),
('client_recontact_daily_limit', '5', 'Tope diario propio de mensajes de recontacto a clientes (compite con el tope global)');
