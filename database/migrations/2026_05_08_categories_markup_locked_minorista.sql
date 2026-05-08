ALTER TABLE categories
  ADD COLUMN markup_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER markup_override,
  ADD COLUMN markup_minorista DECIMAL(5,2) DEFAULT NULL AFTER markup_locked;

UPDATE categories
SET markup_locked = 1,
    markup_minorista = 55.00,
    default_markup = 40.00
WHERE id IN (21, 28);
