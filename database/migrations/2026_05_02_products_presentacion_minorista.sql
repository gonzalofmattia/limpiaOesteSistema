-- Presentación corta para listas de precios minoristas (PDF / catálogo).
ALTER TABLE products
    ADD COLUMN presentacion_minorista VARCHAR(50) NULL DEFAULT NULL
    AFTER presentation;

-- Aerosoles: inferir por código ECO y volumen en el nombre
UPDATE products SET presentacion_minorista =
    CASE
        WHEN code LIKE 'ECO%' AND name LIKE '%500%' THEN 'Aerosol 500ml'
        WHEN code LIKE 'ECO%' AND name LIKE '%360%' THEN 'Aerosol 360ml'
        WHEN code LIKE 'ECO%' AND name LIKE '%300%' THEN 'Aerosol 300ml'
        WHEN code LIKE 'ECO%' AND name LIKE '%295%' THEN 'Aerosol 295ml'
        WHEN code LIKE 'ECO%' AND name LIKE '%260%' THEN 'Aerosol 260ml'
        WHEN code LIKE 'ECO%' AND name LIKE '%230%' THEN 'Aerosol 230ml'
        ELSE 'Aerosol'
    END
WHERE category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'aerosoles' OR pc.slug = 'aerosoles'
       OR c.name LIKE '%Aerosol%' OR pc.name LIKE '%Aerosol%'
);

-- Aerosoles que quedaron en genérico "Aerosol" → asumir 295ml (línea típica ECOMAX)
UPDATE products SET presentacion_minorista = 'Aerosol 295ml'
WHERE presentacion_minorista = 'Aerosol'
  AND category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'aerosoles' OR pc.slug = 'aerosoles'
       OR c.name LIKE '%Aerosol%' OR pc.name LIKE '%Aerosol%'
);

-- Bidones / institucional
UPDATE products SET presentacion_minorista = 'Bidón 5L'
WHERE category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'bidones' OR pc.slug = 'bidones'
       OR c.name LIKE '%Bidon%' OR c.name LIKE '%Bidón%' OR c.name LIKE '%Institucional%'
       OR pc.name LIKE '%Bidon%' OR pc.name LIKE '%Bidón%' OR pc.name LIKE '%Institucional%'
);

-- Masivo / consumo masivo
UPDATE products SET presentacion_minorista =
    CASE
        WHEN name LIKE '%500%' OR name LIKE '%500cc%' THEN 'Crema 500ml'
        WHEN name LIKE '%750%' THEN 'Botella 750ml'
        WHEN name LIKE '%900%' THEN 'Botella 900ml'
        WHEN name LIKE '%1L%' OR name LIKE '%1 L%' THEN 'Botella 1L'
        ELSE 'Botella 1L'
    END
WHERE category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'masivo' OR pc.slug = 'masivo'
       OR c.name LIKE '%Masivo%' OR c.name LIKE '%Consumo%'
       OR pc.name LIKE '%Masivo%' OR pc.name LIKE '%Consumo%'
);

-- Papelería: códigos puntuales
UPDATE products SET presentacion_minorista = 'Bolsón x12 rollos' WHERE code = 'HB100';
UPDATE products SET presentacion_minorista = 'Bolsón x30 rollos' WHERE code = 'H3080P';

-- Sobres
UPDATE products SET presentacion_minorista = 'Sobre concentrado'
WHERE category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'sobres' OR pc.slug = 'sobres'
       OR c.name LIKE '%Sobre%' OR pc.name LIKE '%Sobre%'
);

-- Línea alimenticia
UPDATE products SET presentacion_minorista = 'Bidón 5L'
WHERE category_id IN (
    SELECT c.id FROM categories c
    LEFT JOIN categories pc ON c.parent_id = pc.id
    WHERE c.slug = 'alimenticia' OR pc.slug = 'alimenticia'
       OR c.name LIKE '%Alimentic%' OR pc.name LIKE '%Alimentic%'
);
