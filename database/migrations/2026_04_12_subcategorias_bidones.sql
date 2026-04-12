-- Subcategorías Bidones (ejecutar después de categories.parent_id)
-- MySQL 8+

ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL AFTER id;
ALTER TABLE categories ADD INDEX idx_parent (parent_id);
ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL;

SET @bidones_id = (SELECT id FROM categories WHERE slug = 'bidones' LIMIT 1);

INSERT INTO categories (parent_id, name, slug, default_discount, presentation_info, sort_order, is_active) VALUES
(@bidones_id, 'Limpiadores y Aromatizantes', 'bidones-limpiadores-aromatizantes', 35.00, 'Caja 4x5 Litros', 1, 1),
(@bidones_id, 'Cuidado de Manos', 'bidones-cuidado-manos', 35.00, 'Caja 4x5 Litros', 2, 1),
(@bidones_id, 'Limpiadores Desengrasantes', 'bidones-desengrasantes', 35.00, 'Caja 4x5 Litros', 3, 1),
(@bidones_id, 'Cuidado de la Cocina', 'bidones-cocina', 35.00, 'Caja 4x5 Litros', 4, 1),
(@bidones_id, 'Lavandería', 'bidones-lavanderia', 35.00, 'Caja 4x5 Litros', 5, 1),
(@bidones_id, 'Limpieza y Tratamiento de Pisos', 'bidones-pisos', 35.00, 'Caja 4x5 Litros', 6, 1),
(@bidones_id, 'Ceras', 'bidones-ceras', 35.00, 'Caja 4x5 Litros', 7, 1),
(@bidones_id, 'Curadores Hidrofugos', 'bidones-curadores', 35.00, 'Caja 4x5 Litros', 8, 1),
(@bidones_id, 'Limpieza de Alfombras', 'bidones-alfombras', 35.00, 'Caja 4x5 Litros', 9, 1),
(@bidones_id, 'Limpiadores Desinfectantes', 'bidones-desinfectantes', 35.00, 'Caja 4x5 Litros', 10, 1),
(@bidones_id, 'Cosmética del Automotor', 'bidones-automotor', 35.00, 'Caja 4x5 Litros', 11, 1),
(@bidones_id, 'Productos para Piletas', 'bidones-piletas', 35.00, 'Caja 4x5 Litros', 12, 1),
(@bidones_id, 'Insecticidas', 'bidones-insecticidas', 35.00, 'Caja 4x5 Litros', 13, 1);

SET @sub_limp_arom = (SELECT id FROM categories WHERE slug = 'bidones-limpiadores-aromatizantes' LIMIT 1);
SET @sub_manos = (SELECT id FROM categories WHERE slug = 'bidones-cuidado-manos' LIMIT 1);
SET @sub_deseng = (SELECT id FROM categories WHERE slug = 'bidones-desengrasantes' LIMIT 1);
SET @sub_cocina = (SELECT id FROM categories WHERE slug = 'bidones-cocina' LIMIT 1);
SET @sub_lavand = (SELECT id FROM categories WHERE slug = 'bidones-lavanderia' LIMIT 1);
SET @sub_pisos = (SELECT id FROM categories WHERE slug = 'bidones-pisos' LIMIT 1);
SET @sub_ceras = (SELECT id FROM categories WHERE slug = 'bidones-ceras' LIMIT 1);
SET @sub_curad = (SELECT id FROM categories WHERE slug = 'bidones-curadores' LIMIT 1);
SET @sub_alfomb = (SELECT id FROM categories WHERE slug = 'bidones-alfombras' LIMIT 1);
SET @sub_desinf = (SELECT id FROM categories WHERE slug = 'bidones-desinfectantes' LIMIT 1);
SET @sub_auto = (SELECT id FROM categories WHERE slug = 'bidones-automotor' LIMIT 1);
SET @sub_piletas = (SELECT id FROM categories WHERE slug = 'bidones-piletas' LIMIT 1);
SET @sub_insect = (SELECT id FROM categories WHERE slug = 'bidones-insecticidas' LIMIT 1);

UPDATE products SET category_id = @sub_limp_arom WHERE code IN (
    '861002', '861003', '861004', '861005', '861006', '861007', '861008',
    '329201', '334345', '399329', '861010', '861600'
);

UPDATE products SET category_id = @sub_manos WHERE code IN (
    '861008 A', '861022', '861023', '861023 A', '291920',
    '260001', '100000', '260000'
);

UPDATE products SET category_id = @sub_deseng WHERE code IN (
    '861013', '382018', '861401', '2045', '2046', '8256',
    '2048', '250070', '861015', '250066', '250067'
);

UPDATE products SET category_id = @sub_cocina WHERE code IN (
    '861017', '2026F', '861008 B', '861009', '861007 A', '220060',
    '398120', '861018', '861020', '861024', '261011', '28014',
    '262200', '260065', '262205', '262210', '250060', '250065'
);

UPDATE products SET category_id = @sub_lavand WHERE code IN (
    '861080', '18187', '28186A', '28186Z',
    '8116A', '8116D', '8116E', '8116B',
    '261030', '261021', '1001', '260046',
    '250040', '250020', '250010', '250015', '250050'
);

UPDATE products SET category_id = @sub_pisos WHERE code IN (
    '861100', '2060', '861101', '2061', '260032', '260033',
    '26034', '26035', '26035 A', '861102', '861103', '861104',
    '861105', '861106', '861145', '262190', '861014', '861017 A',
    '861107'
);

UPDATE products SET category_id = @sub_ceras WHERE code IN (
    '861200', '861204', '861205', '861206',
    '14001B', '14003', '14002A',
    '240011', '240031', '240021',
    '861250', '262215'
);

UPDATE products SET category_id = @sub_curad WHERE code IN (
    '861900', '861901', '861902'
);

UPDATE products SET category_id = @sub_alfomb WHERE code IN (
    '861009 A'
);

UPDATE products SET category_id = @sub_desinf WHERE code IN (
    '260089', '861000', '861016', '861019', '861621', '861122',
    '861012', '2062', '261000', '464656', '260071', '260072',
    '260073', '260070', 'ECHL1'
);

UPDATE products SET category_id = @sub_auto WHERE code IN (
    '861620', '1609.40', '262100', '262120', '262140',
    '26159', '452710', '162910', '262175'
);

UPDATE products SET category_id = @sub_piletas WHERE code IN (
    '200055', '260050'
);

UPDATE products SET category_id = @sub_insect WHERE code IN (
    '861650', '861652'
);
