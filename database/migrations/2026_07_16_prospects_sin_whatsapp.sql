ALTER TABLE prospects
    MODIFY COLUMN status ENUM('nuevo','contactado','respondio','interesado','visita_agendada','muestra_entregada','cotizado','cliente','no_interesado','sin_respuesta','sin_whatsapp') NOT NULL DEFAULT 'nuevo';
