-- Settings del motor de envios (worker Python externo). outreach_api_token se genera random
-- una sola vez aca; si se necesita rotar, actualizar tambien worker/config.json del worker.
INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('outreach_api_token', '1da23a661c4ee6cb0271fe06a30bd617019abf2b9fbb65db340e6268265110a0', 'Token que usa el worker de WhatsApp para autenticarse contra la API de prospeccion (header X-Outreach-Token)'),
    ('outreach_daily_cap', '15', 'Tope global de mensajes de WhatsApp por dia (maximo absoluto 25)'),
    ('outreach_window_start', '09:30', 'Hora de inicio de la ventana de envio (HH:MM)'),
    ('outreach_window_end', '18:30', 'Hora de fin de la ventana de envio (HH:MM)'),
    ('outreach_weekends_enabled', '0', 'Si esta en 1, tambien se envia sabados y domingos'),
    ('outreach_global_pause', '0', 'Si esta en 1, el worker no recibe mensajes nuevos hasta reanudar'),
    ('outreach_min_delay_seconds', '60', 'Delay minimo (segundos) entre mensajes del worker'),
    ('outreach_max_delay_seconds', '180', 'Delay maximo (segundos) entre mensajes del worker'),
    ('outreach_prospect_cooldown_days', '7', 'Dias minimos entre dos contactos al mismo prospecto')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
