CREATE TABLE store_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL COMMENT 'Nombre de icono Lucide',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_store_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO store_categories (name, slug, description, icon, sort_order) VALUES
('Cocina y gastronomía', 'cocina', 'Desengrasantes, lavavajillas, abrillantadores y productos para cocinas profesionales.', 'UtensilsCrossed', 1),
('Pisos y superficies', 'pisos', 'Ceras, lustras pisos, limpia vidrios y productos multiuso para todo tipo de superficies.', 'Layers', 2),
('Desinfección', 'desinfeccion', 'Desinfectantes, bactericidas y productos de amonio cuaternario para higiene total.', 'ShieldCheck', 3),
('Lavandería', 'lavanderia', 'Jabón para ropa, suavizantes, lavandinas y productos de lavado profesional.', 'WashingMachine', 4),
('Aerosoles', 'aerosoles', 'Ambientadores, insecticidas en aerosol, lustra muebles y acero inoxidable.', 'Wind', 5),
('Higiene y papel', 'higiene', 'Papel higiénico, toallas de papel, jabón de manos y dispensers.', 'HandHeart', 6),
('Insecticidas', 'insecticidas', 'Insecticidas concentrados, cebos y productos de control de plagas.', 'Bug', 7),
('Combos', 'combos', 'Packs y combos armados para mayor ahorro y conveniencia.', 'Package', 8);
