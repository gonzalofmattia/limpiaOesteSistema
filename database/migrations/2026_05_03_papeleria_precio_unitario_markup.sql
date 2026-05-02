-- Papelería: precio lista unitario = precio caja cuando el unitario está vacío.
-- Solo productos en categorías (o subcategorías) cuyo nombre coincide con patrones de papelería.
UPDATE products p
INNER JOIN categories c ON c.id = p.category_id
LEFT JOIN categories pc ON c.parent_id = pc.id
SET p.precio_lista_unitario = p.precio_lista_caja
WHERE p.precio_lista_unitario IS NULL
  AND p.precio_lista_caja IS NOT NULL
  AND (
    c.name LIKE '%Papel%' OR c.name LIKE '%Toalla%' OR c.name LIKE '%Bobina%'
    OR c.name LIKE '%Higiénico%' OR c.name LIKE '%Higienico%' OR c.name LIKE '%Papelera%'
    OR c.name LIKE '%Factory%' OR c.name LIKE '%Higienik%'
    OR pc.name LIKE '%Papel%' OR pc.name LIKE '%Toalla%' OR pc.name LIKE '%Bobina%'
    OR pc.name LIKE '%Higiénico%' OR pc.name LIKE '%Higienico%' OR pc.name LIKE '%Papelera%'
    OR pc.name LIKE '%Factory%' OR pc.name LIKE '%Higienik%'
  );

-- Override de markup a nivel categoría (lo lee PricingEngine vía category_markup_override).
-- Si la columna ya existe, omitir el ALTER o ignorar el error "Duplicate column".
ALTER TABLE categories
    ADD COLUMN markup_override DECIMAL(5,2) NULL DEFAULT NULL AFTER default_markup;

UPDATE categories
SET markup_override = 65
WHERE name LIKE '%Papel%'
   OR name LIKE '%Toalla%'
   OR name LIKE '%Bobina%'
   OR name LIKE '%Higiénico%'
   OR name LIKE '%Higienico%'
   OR name LIKE '%Papelera%'
   OR name LIKE '%Factory%'
   OR name LIKE '%Higienik%';
