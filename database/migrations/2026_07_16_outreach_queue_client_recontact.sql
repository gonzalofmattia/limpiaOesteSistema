-- Permite que outreach_queue tenga filas de recontacto a CLIENTES (no
-- prospectos): prospect_id pasa a ser opcional, se agrega client_id
-- (mutuamente excluyente con prospect_id, a nivel aplicacion) y
-- phone_override para guardar el telefono ya normalizado al encolar
-- (clients.phone no viene normalizado como prospects.phone).
ALTER TABLE outreach_queue
    MODIFY COLUMN prospect_id INT NULL,
    ADD COLUMN client_id INT NULL AFTER prospect_id,
    ADD COLUMN phone_override VARCHAR(20) NULL AFTER client_id,
    ADD CONSTRAINT fk_outreach_queue_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE;

ALTER TABLE outreach_queue ADD INDEX idx_outreach_queue_client (client_id);
