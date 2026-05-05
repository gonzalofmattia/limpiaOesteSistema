-- =====================================================
-- Feature 5: Precios por segmento de cliente
-- =====================================================

-- Agregar tipo de cliente (segmento) y markup personalizado
ALTER TABLE clients
  ADD COLUMN client_type ENUM('mayorista','minorista','barrio_cerrado','gastronomico','mercadolibre')
  DEFAULT 'mayorista' AFTER notes;

ALTER TABLE clients
  ADD COLUMN default_markup DECIMAL(5,2) NULL AFTER client_type;

-- Tabla de configuración de markups por segmento
-- Esto permite cambiar el markup de cada segmento sin tocar código
CREATE TABLE IF NOT EXISTS client_segment_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  segment_key VARCHAR(50) NOT NULL UNIQUE,
  segment_label VARCHAR(100) NOT NULL,
  default_markup DECIMAL(5,2) NOT NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar configuración inicial de segmentos
INSERT INTO client_segment_config (segment_key, segment_label, default_markup, sort_order) VALUES
  ('mayorista', 'Mayorista', 60.00, 1),
  ('minorista', 'Minorista', 100.00, 2),
  ('barrio_cerrado', 'Barrio Cerrado', 90.00, 3),
  ('gastronomico', 'Gastronómico', 70.00, 4),
  ('mercadolibre', 'MercadoLibre', 60.00, 5)
ON DUPLICATE KEY UPDATE
  segment_label = VALUES(segment_label),
  default_markup = VALUES(default_markup),
  sort_order = VALUES(sort_order);

-- Clasificar clientes existentes según datos conocidos
-- (el admin puede ajustar después desde el formulario)
UPDATE clients SET client_type = 'barrio_cerrado' WHERE id IN (8, 11, 10, 4, 9);
-- Belen SPL, Eduardo Motoni HSP, Sofia SPL Miss, San Patricio, Jimena Gonzalez
UPDATE clients SET client_type = 'gastronomico' WHERE id IN (15, 14);
-- Cafecito Lujan, La Artesanal Ramos Mejia
UPDATE clients SET client_type = 'mercadolibre' WHERE id = 7;
-- MercadoLibre
UPDATE clients SET client_type = 'minorista' WHERE id IN (2, 12, 5, 13);
-- Ana María, Carla, Romina, Susana
UPDATE clients SET client_type = 'mayorista' WHERE id IN (1, 3);
-- Gonzalo Mattia (test), Maria Gloria
