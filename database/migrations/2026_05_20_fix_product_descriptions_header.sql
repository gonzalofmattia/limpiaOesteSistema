-- Limpia descripciones de catálogo que guardaron el encabezado ML por error.
UPDATE products SET short_description = NULL WHERE short_description LIKE 'LIMPIA OESTE%';
UPDATE products SET full_description = NULL WHERE full_description LIKE '%LIMPIA OESTE%Distribuidora%';
