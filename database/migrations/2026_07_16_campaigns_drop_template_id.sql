-- Las campanias dejan de tener una plantilla fija: el primer contacto ahora
-- se resuelve automaticamente por rubro de cada prospecto (mismo mecanismo
-- que ya usaban seguimientos/recontactos), igual que en OutreachScheduler.
ALTER TABLE outreach_campaigns DROP FOREIGN KEY fk_outreach_campaigns_template;
ALTER TABLE outreach_campaigns DROP COLUMN template_id;
