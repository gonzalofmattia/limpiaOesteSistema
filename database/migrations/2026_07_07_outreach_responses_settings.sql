INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('outreach_followup_days', '7', 'Dias sin respuesta antes de mandar el seguimiento automatico'),
    ('outreach_recontact_days', '45', 'Dias sin novedades antes de un recontacto automatico'),
    ('outreach_max_recontacts', '2', 'Maximo de recontactos automaticos antes de marcar sin_respuesta'),
    ('outreach_optout_keywords', 'no me interesa,no molestar,baja,dejen de escribir,no gracias', 'Palabras clave (separadas por coma) que disparan opt-out automatico')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
