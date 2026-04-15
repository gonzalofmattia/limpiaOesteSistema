#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Instalación idempotente — LIMPIA OESTE ABM
 * Uso: php install.php
 */

$basePath = __DIR__;
$ds = DIRECTORY_SEPARATOR;

function out(string $msg, string $color = ''): void
{
    $codes = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'reset' => "\033[0m",
    ];
    $prefix = $color && isset($codes[$color]) ? $codes[$color] : '';
    $suffix = $color ? $codes['reset'] : '';
    echo $prefix . $msg . $suffix . PHP_EOL;
}

function ok(string $msg): void
{
    out('[OK] ' . $msg, 'green');
}

function err(string $msg): void
{
    out('[ERROR] ' . $msg, 'red');
}

function warn(string $msg): void
{
    out('[WARN] ' . $msg, 'yellow');
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'limpia_oeste_abm';

try {
    $pdo = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ok("Conexión a MySQL ({$host})");
} catch (PDOException $e) {
    err('No se pudo conectar a MySQL: ' . $e->getMessage());
    exit(1);
}

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ok("Base de datos `{$dbName}`");
} catch (PDOException $e) {
    err($e->getMessage());
    exit(1);
}

$pdo->exec("USE `{$dbName}`");

$schema = <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    default_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    default_markup DECIMAL(5,2) DEFAULT NULL,
    presentation_info VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(100),
    description TEXT,
    content VARCHAR(100),
    presentation VARCHAR(100),
    units_per_box INT DEFAULT 1,
    unit_volume VARCHAR(50),
    equivalence VARCHAR(100),
    ean13 VARCHAR(13),
    sale_unit_type ENUM('caja','unidad') NOT NULL DEFAULT 'caja',
    sale_unit_label VARCHAR(50) NOT NULL DEFAULT 'Caja',
    sale_unit_description VARCHAR(150) DEFAULT NULL,
    precio_lista_unitario DECIMAL(12,2) DEFAULT NULL,
    precio_lista_caja DECIMAL(12,2) DEFAULT NULL,
    precio_lista_bidon DECIMAL(12,2) DEFAULT NULL,
    precio_lista_litro DECIMAL(12,2) DEFAULT NULL,
    precio_lista_bulto DECIMAL(12,2) DEFAULT NULL,
    precio_lista_sobre DECIMAL(12,2) DEFAULT NULL,
    discount_override DECIMAL(5,2) DEFAULT NULL,
    markup_override DECIMAL(5,2) DEFAULT NULL,
    dilution VARCHAR(100),
    usage_cost DECIMAL(12,2),
    pallet_info VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_category (category_id),
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    business_name VARCHAR(255),
    contact_person VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    notes TEXT,
    balance DECIMAL(12,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS price_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    custom_markup DECIMAL(5,2) DEFAULT NULL,
    include_iva TINYINT(1) DEFAULT 0,
    category_filter TEXT,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    generated_at TIMESTAMP NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS price_list_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price_list_id INT NOT NULL,
    product_id INT NOT NULL,
    precio_base_usado DECIMAL(12,2) NOT NULL,
    costo_limpia_oeste DECIMAL(12,2) NOT NULL,
    precio_venta DECIMAL(12,2) NOT NULL,
    precio_venta_iva DECIMAL(12,2),
    markup_applied DECIMAL(5,2) NOT NULL,
    discount_applied DECIMAL(5,2) NOT NULL,
    price_field_used VARCHAR(50),
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_pricelist (price_list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(20) UNIQUE NOT NULL,
    client_id INT,
    title VARCHAR(255),
    notes TEXT,
    validity_days INT DEFAULT 7,
    custom_markup DECIMAL(5,2) DEFAULT NULL,
    include_iva TINYINT(1) DEFAULT 0,
    subtotal DECIMAL(12,2) DEFAULT 0,
    iva_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft','sent','accepted','rejected','expired','delivered') DEFAULT 'draft',
    sent_at TIMESTAMP NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_number (quote_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_type VARCHAR(50),
    unit_label VARCHAR(50) DEFAULT NULL,
    unit_description VARCHAR(150) DEFAULT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    individual_unit_price DECIMAL(12,2) DEFAULT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    price_field_used VARCHAR(50) DEFAULT NULL,
    discount_applied DECIMAL(5,2),
    markup_applied DECIMAL(5,2),
    notes VARCHAR(255),
    sort_order INT DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seiq_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    notes TEXT,
    included_quotes TEXT,
    total_products INT DEFAULT 0,
    total_boxes INT DEFAULT 0,
    status ENUM('draft','sent','received') DEFAULT 'draft',
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seiq_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seiq_order_id INT NOT NULL,
    product_id INT NOT NULL,
    qty_units_sold INT NOT NULL DEFAULT 0,
    qty_boxes_sold INT NOT NULL DEFAULT 0,
    total_units_needed INT NOT NULL DEFAULT 0,
    units_per_box INT NOT NULL DEFAULT 1,
    boxes_to_order INT NOT NULL DEFAULT 0,
    units_remainder INT NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (seiq_order_id) REFERENCES seiq_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (seiq_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('client', 'supplier') NOT NULL,
    account_id INT NOT NULL,
    transaction_type ENUM('invoice', 'payment', 'adjustment') NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('efectivo', 'transferencia', 'otro') DEFAULT NULL,
    payment_reference VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    notes TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_type, account_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (transaction_date),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        err('SQL: ' . $e->getMessage());
        exit(1);
    }
}
ok('Tablas verificadas/creadas');

$installColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);

    return (int) $st->fetchColumn() > 0;
};

try {
    if (!$installColumnExists($pdo, 'clients', 'balance')) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN balance DECIMAL(12,2) DEFAULT 0 AFTER notes');
    }
    ok('Migración clients.balance (si aplica)');
} catch (PDOException $e) {
    warn('Migración clients.balance: ' . $e->getMessage());
}

try {
    if (!$installColumnExists($pdo, 'categories', 'parent_id')) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL AFTER id');
        $pdo->exec('ALTER TABLE categories ADD INDEX idx_parent (parent_id)');
        try {
            $pdo->exec('ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL');
        } catch (PDOException $e) {
            warn('FK fk_categories_parent: ' . $e->getMessage());
        }
        ok('Columna categories.parent_id');
    }
} catch (PDOException $e) {
    warn('Migración parent_id categorías: ' . $e->getMessage());
}

try {
    if (!$installColumnExists($pdo, 'products', 'sale_unit_type')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sale_unit_type ENUM('caja','unidad') NOT NULL DEFAULT 'caja' AFTER ean13");
    }
    if (!$installColumnExists($pdo, 'products', 'sale_unit_label')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sale_unit_label VARCHAR(50) NOT NULL DEFAULT 'Caja' AFTER sale_unit_type");
    }
    if (!$installColumnExists($pdo, 'products', 'sale_unit_description')) {
        $pdo->exec('ALTER TABLE products ADD COLUMN sale_unit_description VARCHAR(150) DEFAULT NULL AFTER sale_unit_label');
    }
    if (!$installColumnExists($pdo, 'quote_items', 'unit_label')) {
        $pdo->exec('ALTER TABLE quote_items ADD COLUMN unit_label VARCHAR(50) DEFAULT NULL AFTER unit_type');
    }
    if (!$installColumnExists($pdo, 'quote_items', 'unit_description')) {
        $pdo->exec('ALTER TABLE quote_items ADD COLUMN unit_description VARCHAR(150) DEFAULT NULL AFTER unit_label');
    }
    if (!$installColumnExists($pdo, 'quote_items', 'price_field_used')) {
        $pdo->exec('ALTER TABLE quote_items ADD COLUMN price_field_used VARCHAR(50) DEFAULT NULL AFTER subtotal');
    }
    if (!$installColumnExists($pdo, 'quote_items', 'individual_unit_price')) {
        $pdo->exec('ALTER TABLE quote_items ADD COLUMN individual_unit_price DECIMAL(12,2) DEFAULT NULL AFTER unit_price');
    }
    ok('Migración columnas venta / presupuestos (si aplica)');
} catch (PDOException $e) {
    warn('Migración columnas: ' . $e->getMessage());
}

try {
    $pdo->exec(
        "ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','expired','delivered') DEFAULT 'draft'"
    );
    ok('ENUM quotes.status incluye delivered (si aplica)');
} catch (PDOException $e) {
    warn('quotes.status delivered: ' . $e->getMessage());
}

try {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    contact_name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    notes TEXT,
    cliente_id VARCHAR(50),
    cliente_nombre VARCHAR(255),
    condicion_pago VARCHAR(100),
    observaciones VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    ok('Tabla suppliers');
} catch (PDOException $e) {
    warn('Tabla suppliers: ' . $e->getMessage());
}

try {
    if (!$installColumnExists($pdo, 'categories', 'supplier_id')) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN supplier_id INT DEFAULT NULL AFTER parent_id');
        try {
            $pdo->exec('ALTER TABLE categories ADD CONSTRAINT fk_supplier_category FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
        } catch (PDOException $e) {
            warn('FK categories.supplier_id: ' . $e->getMessage());
        }
    }
    if (!$installColumnExists($pdo, 'seiq_orders', 'supplier_id')) {
        $pdo->exec('ALTER TABLE seiq_orders ADD COLUMN supplier_id INT DEFAULT NULL AFTER id');
        try {
            $pdo->exec('ALTER TABLE seiq_orders ADD CONSTRAINT fk_order_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)');
        } catch (PDOException $e) {
            warn('FK seiq_orders.supplier_id: ' . $e->getMessage());
        }
    }
    ok('Migración columnas supplier_id');
} catch (PDOException $e) {
    warn('Migración supplier_id: ' . $e->getMessage());
}

try {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS seiq_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    notes TEXT,
    included_quotes TEXT,
    total_products INT DEFAULT 0,
    total_boxes INT DEFAULT 0,
    status ENUM('draft','sent','received') DEFAULT 'draft',
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS seiq_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seiq_order_id INT NOT NULL,
    product_id INT NOT NULL,
    qty_units_sold INT NOT NULL DEFAULT 0,
    qty_boxes_sold INT NOT NULL DEFAULT 0,
    total_units_needed INT NOT NULL DEFAULT 0,
    units_per_box INT NOT NULL DEFAULT 1,
    boxes_to_order INT NOT NULL DEFAULT 0,
    units_remainder INT NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (seiq_order_id) REFERENCES seiq_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (seiq_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    ok('Tablas seiq_orders / seiq_order_items');
} catch (PDOException $e) {
    warn('Tablas Seiq: ' . $e->getMessage());
}

$settingsSeed = [
    ['empresa_nombre', 'LIMPIA OESTE', 'Nombre comercial'],
    ['empresa_tagline', 'Distribuidora Seiq - Zona Oeste GBA', null],
    ['empresa_instagram', '@limpiaOeste', null],
    ['empresa_whatsapp', '2323535220', null],
    ['empresa_zona', 'Zona Oeste GBA', null],
    ['default_markup', '60', null],
    ['iva_rate', '21', null],
    ['lista_seiq_numero', '23', null],
    ['lista_seiq_fecha', '2026-03-27', null],
    ['moneda', 'ARS', null],
    ['mostrar_iva', '0', null],
    ['quote_prefix', 'LO', null],
    ['quote_validity_days', '7', null],
];

$insSetting = $pdo->prepare(
    'INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)'
);
foreach ($settingsSeed as $row) {
    $insSetting->execute([$row[0], $row[1], $row[2]]);
}
ok('Settings seed');

try {
    $pdo->exec("DELETE FROM settings WHERE setting_key IN ('seiq_cliente_id','seiq_cliente_nombre','seiq_condicion_pago','seiq_observaciones')");
} catch (PDOException $e) {
    warn('Limpieza settings Seiq legacy: ' . $e->getMessage());
}

$suppliersSeed = [
    ['Seiq', 'seiq', 'Rodrigo', null, '15487', 'MATTIA GONZALO FRANCISCO', 'CONTADO CTA CTE', 'HAEDO - 29'],
    ['Higienik', 'higienik', 'Rodrigo', null, '15487', 'MATTIA GONZALO FRANCISCO', 'CONTADO CTA CTE', 'HAEDO - 29'],
];
$insSupplier = $pdo->prepare(
    'INSERT INTO suppliers (name, slug, contact_name, phone, cliente_id, cliente_nombre, condicion_pago, observaciones)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       contact_name = VALUES(contact_name),
       phone = VALUES(phone),
       cliente_id = VALUES(cliente_id),
       cliente_nombre = VALUES(cliente_nombre),
       condicion_pago = VALUES(condicion_pago),
       observaciones = VALUES(observaciones)'
);
foreach ($suppliersSeed as $srow) {
    $insSupplier->execute($srow);
}
ok('Suppliers seed');

$st = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ?');
$st->execute(['admin']);
if ((int) $st->fetchColumn() === 0) {
    $hash = password_hash('limpiaOeste2026', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute(['admin', $hash]);
    ok('Usuario admin creado');
} else {
    ok('Usuario admin ya existe (no duplicado)');
}

$categoriesSeed = [
    ['Aerosoles ECOMAX', 'aerosoles', 0.00, 'Pack x12 unidades', 1],
    ['Bidones - Línea Institucional', 'bidones', 35.00, 'Caja 4x5 Litros', 2],
    ['Masivo - Consumo Masivo', 'masivo', 35.00, 'Caja x12 unidades (1L/900cc/750ml)', 3],
    ['Sobres Concentrados', 'sobres', 20.00, 'Caja 10 x 4 sobres', 4],
    ['Línea Alimenticia', 'alimenticia', 35.00, 'Caja 4x5 Litros / x20 Litros', 5],
];

$insCat = $pdo->prepare(
    'INSERT INTO categories (name, slug, default_discount, presentation_info, sort_order) VALUES (?, ?, ?, ?, ?)'
);
foreach ($categoriesSeed as $c) {
    $chk = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $chk->execute([$c[1]]);
    if (!$chk->fetch()) {
        $insCat->execute([$c[0], $c[1], $c[2], $c[3], $c[4]]);
    }
}
ok('Categorías seed');

$bidonesSubsSeed = [
    ['Limpiadores y Aromatizantes', 'bidones-limpiadores-aromatizantes', 35.0, 'Caja 4x5 Litros', 1],
    ['Cuidado de Manos', 'bidones-cuidado-manos', 35.0, 'Caja 4x5 Litros', 2],
    ['Limpiadores Desengrasantes', 'bidones-desengrasantes', 35.0, 'Caja 4x5 Litros', 3],
    ['Cuidado de la Cocina', 'bidones-cocina', 35.0, 'Caja 4x5 Litros', 4],
    ['Lavandería', 'bidones-lavanderia', 35.0, 'Caja 4x5 Litros', 5],
    ['Limpieza y Tratamiento de Pisos', 'bidones-pisos', 35.0, 'Caja 4x5 Litros', 6],
    ['Ceras', 'bidones-ceras', 35.0, 'Caja 4x5 Litros', 7],
    ['Curadores Hidrofugos', 'bidones-curadores', 35.0, 'Caja 4x5 Litros', 8],
    ['Limpieza de Alfombras', 'bidones-alfombras', 35.0, 'Caja 4x5 Litros', 9],
    ['Limpiadores Desinfectantes', 'bidones-desinfectantes', 35.0, 'Caja 4x5 Litros', 10],
    ['Cosmética del Automotor', 'bidones-automotor', 35.0, 'Caja 4x5 Litros', 11],
    ['Productos para Piletas', 'bidones-piletas', 35.0, 'Caja 4x5 Litros', 12],
    ['Insecticidas', 'bidones-insecticidas', 35.0, 'Caja 4x5 Litros', 13],
];
$bidonesProductMoves = [
    'bidones-limpiadores-aromatizantes' => [
        '861002', '861003', '861004', '861005', '861006', '861007', '861008',
        '329201', '334345', '399329', '861010', '861600',
    ],
    'bidones-cuidado-manos' => [
        '861008 A', '861022', '861023', '861023 A', '291920',
        '260001', '100000', '260000',
    ],
    'bidones-desengrasantes' => [
        '861013', '382018', '861401', '2045', '2046', '8256',
        '2048', '250070', '861015', '250066', '250067',
    ],
    'bidones-cocina' => [
        '861017', '2026F', '861008 B', '861009', '861007 A', '220060',
        '398120', '861018', '861020', '861024', '261011', '28014',
        '262200', '260065', '262205', '262210', '250060', '250065',
    ],
    'bidones-lavanderia' => [
        '861080', '18187', '28186A', '28186Z',
        '8116A', '8116D', '8116E', '8116B',
        '261030', '261021', '1001', '260046',
        '250040', '250020', '250010', '250015', '250050',
    ],
    'bidones-pisos' => [
        '861100', '2060', '861101', '2061', '260032', '260033',
        '26034', '26035', '26035 A', '861102', '861103', '861104',
        '861105', '861106', '861145', '262190', '861014', '861017 A',
        '861107',
    ],
    'bidones-ceras' => [
        '861200', '861204', '861205', '861206',
        '14001B', '14003', '14002A',
        '240011', '240031', '240021',
        '861250', '262215',
    ],
    'bidones-curadores' => ['861900', '861901', '861902'],
    'bidones-alfombras' => ['861009 A'],
    'bidones-desinfectantes' => [
        '260089', '861000', '861016', '861019', '861621', '861122',
        '861012', '2062', '261000', '464656', '260071', '260072',
        '260073', '260070', 'ECHL1',
    ],
    'bidones-automotor' => [
        '861620', '1609.40', '262100', '262120', '262140',
        '26159', '452710', '162910', '262175',
    ],
    'bidones-piletas' => ['200055', '260050'],
    'bidones-insecticidas' => ['861650', '861652'],
];

try {
    $chkSub = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $chkSub->execute(['bidones-limpiadores-aromatizantes']);
    if (!$chkSub->fetch()) {
        $stB = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
        $stB->execute(['bidones']);
        $bidonesId = (int) $stB->fetchColumn();
        if ($bidonesId <= 0) {
            throw new RuntimeException('Categoría bidones no encontrada');
        }
        $insSub = $pdo->prepare(
            'INSERT INTO categories (parent_id, name, slug, default_discount, presentation_info, sort_order, is_active) VALUES (?,?,?,?,?,?,1)'
        );
        foreach ($bidonesSubsSeed as $s) {
            $insSub->execute([$bidonesId, $s[0], $s[1], $s[2], $s[3], $s[4]]);
        }
        ok('Subcategorías Bidones creadas');
        foreach ($bidonesProductMoves as $slug => $codes) {
            $stId = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
            $stId->execute([$slug]);
            $sid = $stId->fetchColumn();
            if (!$sid) {
                continue;
            }
            $sid = (int) $sid;
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql = "UPDATE products SET category_id = ? WHERE code IN ({$placeholders})";
            $pdo->prepare($sql)->execute(array_merge([$sid], $codes));
        }
        ok('Productos reasignados a subcategorías Bidones');
    }
} catch (Throwable $e) {
    warn('Seed subcategorías Bidones: ' . $e->getMessage());
}

try {
    $seiqId = (int) $pdo->query("SELECT id FROM suppliers WHERE slug = 'seiq'")->fetchColumn();
    $higId = (int) $pdo->query("SELECT id FROM suppliers WHERE slug = 'higienik'")->fetchColumn();
    if ($seiqId > 0) {
        $pdo->exec("UPDATE categories SET supplier_id = {$seiqId} WHERE slug IN ('aerosoles','bidones','masivo','sobres','alimenticia','insecticidas-concentrados','pouches-y-dispenser')");
        $pdo->exec("UPDATE categories c JOIN categories parent ON c.parent_id = parent.id SET c.supplier_id = {$seiqId} WHERE parent.slug = 'bidones'");
        $pdo->exec("UPDATE seiq_orders SET supplier_id = {$seiqId} WHERE supplier_id IS NULL");
    }
    if ($higId > 0) {
        $pdo->exec("UPDATE categories SET supplier_id = {$higId} WHERE slug LIKE 'hig-%' OR slug LIKE 'fac-%' OR slug IN ('papelera-higienik','papelera-factory')");
        $pdo->exec("UPDATE categories c JOIN categories parent ON c.parent_id = parent.id SET c.supplier_id = {$higId} WHERE parent.slug IN ('papelera-higienik','papelera-factory')");
    }
    ok('Asignación proveedores por categoría');
} catch (PDOException $e) {
    warn('Asignación proveedores: ' . $e->getMessage());
}

function catId(PDO $pdo, string $slug): int
{
    $s = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $s->execute([$slug]);
    $id = $s->fetchColumn();
    if (!$id) {
        throw new RuntimeException("Categoría no encontrada: {$slug}");
    }
    return (int) $id;
}

// [slug, code..ean(1-10), precios(11-16), disc(17), mark(18), dil(19), uso(20), pallet(21), active(22), feat(23), sort(24), notes(25)]
$productsSeed = [
    ['aerosoles', 'ECOAAI01', 'ECOMAX ABRILLANTADOR DE ACERO INOXIDABLE', null, null, 'Aerosol 260ML/360GR', 'PACK X 12u', 12, null, null, '7798270221470', 2974.07, null, null, null, null, null, null, null, null, null, '80PACKS', 1, 0, 0, null],
    ['aerosoles', 'ECOELM03', 'ECOMAX ESPUMA LIMPIADORA MULTIUSO', null, null, 'Aerosol 295ML/360GR', 'PACK X 12u', 12, null, null, '7798270221463', 2595.02, null, null, null, null, null, null, null, null, null, '80PACKS', 1, 0, 0, null],
    ['aerosoles', 'ECOLH05', 'ECOMAX LIMPIA HORNOS', null, null, 'Aerosol 360ML/300GR', 'PACK X 12u', 12, null, null, '7798270221494', 2536.70, null, null, null, null, null, null, null, null, null, '80PACKS', 1, 0, 0, null],
    ['bidones', '861002', 'DUFT SWEET (colonia Limpiador Desodorante)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, 'Flash', null, null, 23398.55, 5849.64, 1169.93, null, null, null, null, '1 en 20', 58.50, null, 1, 0, 0, null],
    ['bidones', '861010', 'WIPER (limpiavidrios)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, 'View', null, null, 26070.68, 6517.67, 1303.53, null, null, null, null, 'Puro', null, null, 1, 0, 0, null],
    ['bidones', '861012', 'SX 185 (limpiador desincrustante y bactericida)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, 'Drastik WC Rein', null, null, 37951.01, 9487.75, 1897.55, null, null, null, null, '1 en 10', 189.76, null, 1, 0, 0, null],
    ['masivo', 'CMAWNG1', 'ALL WAX, CERA LÍQUIDA (NEGRO)', null, null, 'Envase x 1L', 'CAJA X 12 UNIDADES', 12, null, null, '7798270222125', 3576.88, 42922.58, null, null, null, null, null, null, null, null, '50 cajas', 1, 0, 0, null],
    ['masivo', 'ECODPLI14', 'DESODORANTE DE PISOS (LIMÓN)', null, null, 'Envase x 900 cc', 'CAJA X 12 UNIDADES', 12, null, null, '7798270222033', 990.52, 11886.24, null, null, null, null, null, null, null, null, '50 cajas', 1, 0, 0, null],
    ['masivo', 'ECOLL18', 'LAVAVAJILLAS (LIMÓN)', null, null, 'Envase x 750 ml', 'CAJA X 12 UNIDADES', 12, null, null, '7798270222002', 1357.83, 16293.96, null, null, null, null, null, null, null, null, '50 cajas', 1, 0, 0, null],
    ['sobres', '391792', 'LAVANDINA ECOMAX', null, null, null, '10 cajas x 8 Sobres', 1, null, null, null, null, 80261.47, null, null, null, 1003.27, null, null, '1 en 10', 4.01, null, 1, 0, 0, null],
    ['sobres', '391739', 'DESENGRASANTE ECOMAX', null, null, null, '10 cajas x 4 Sobres', 1, null, null, null, null, 83495.52, null, null, null, 2087.39, null, null, '1 en 10', 8.35, null, 1, 0, 0, null],
    ['sobres', '391738', 'LIMPIA VIDRIOS ECOMAX', null, null, null, '10 cajas x 4 Sobres', 1, null, null, null, null, 73770.98, null, null, null, 1844.27, null, null, 'Listo para Usar', 73.77, null, 1, 0, 0, null],
    ['alimenticia', 'ALALC1', 'ECOMAX ALCAL (espuma clorada alcalina)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, null, null, null, 97036.16, 22672.00, null, null, null, null, null, null, null, null, null, 1, 0, 0, null],
    ['alimenticia', 'ALFRC2', 'ECOMAX FORCE (detergente 15% de materia activa)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, null, null, null, null, 55982.40, 13080.00, null, null, null, null, null, null, null, null, null, 1, 0, 0, null],
    ['alimenticia', 'ALACD6', 'ECOMAX ACID (espumígeno desincrustante ácido)', null, null, '4 x 5 Lts', '4 x 5 Lts', 1, null, null, null, null, null, 130625.60, 30520.00, null, null, null, null, null, null, null, null, null, 1, 0, 0, null],
];

$insProd = $pdo->prepare(
    'INSERT INTO products (
        category_id, code, name, short_name, description, content, presentation, units_per_box, unit_volume, equivalence, ean13,
        precio_lista_unitario, precio_lista_caja, precio_lista_bidon, precio_lista_litro, precio_lista_bulto, precio_lista_sobre,
        discount_override, markup_override,
        dilution, usage_cost, pallet_info, is_active, is_featured, sort_order, notes
    ) VALUES (' . implode(',', array_fill(0, 26, '?')) . ')'
);

foreach ($productsSeed as $p) {
    $slug = $p[0];
    $code = $p[1];
    $chk = $pdo->prepare('SELECT id FROM products WHERE code = ?');
    $chk->execute([$code]);
    if ($chk->fetch()) {
        continue;
    }
    $cid = catId($pdo, $slug);
    $insProd->execute([
        $cid,
        $p[1],
        $p[2],
        $p[3],
        $p[4],
        $p[5],
        $p[6],
        $p[7],
        $p[8],
        $p[9],
        $p[10],
        $p[11],
        $p[12],
        $p[13],
        $p[14],
        $p[15],
        $p[16],
        $p[17],
        $p[18],
        $p[19],
        $p[20],
        $p[21],
        $p[22],
        $p[23],
        $p[24],
        $p[25],
    ]);
}
ok('Productos seed');

try {
    $pdo->exec(<<<'SQL'
UPDATE products p
JOIN categories c ON p.category_id = c.id
SET p.sale_unit_label = 'Pack x12',
    p.sale_unit_description = CONCAT('Pack 12 aerosoles ', COALESCE(p.content, '')),
    p.units_per_box = 12
WHERE c.slug = 'aerosoles'
  AND (p.sale_unit_description IS NULL OR TRIM(p.sale_unit_description) = '')
SQL);
    $pdo->exec(<<<'SQL'
UPDATE products p
JOIN categories c ON p.category_id = c.id
SET p.sale_unit_label = 'Caja 4x5L',
    p.sale_unit_description = 'Caja 4 bidones x 5 Litros',
    p.units_per_box = 4
WHERE c.slug = 'bidones'
  AND (p.sale_unit_description IS NULL OR TRIM(p.sale_unit_description) = '')
SQL);
    $pdo->exec(<<<'SQL'
UPDATE products p
JOIN categories c ON p.category_id = c.id
SET p.sale_unit_label = 'Caja x12',
    p.sale_unit_description = CONCAT('Caja 12 unidades ', COALESCE(p.content, '')),
    p.units_per_box = 12
WHERE c.slug = 'masivo'
  AND (p.sale_unit_description IS NULL OR TRIM(p.sale_unit_description) = '')
SQL);
    $pdo->exec(<<<'SQL'
UPDATE products p
JOIN categories c ON p.category_id = c.id
SET p.sale_unit_label = 'Caja',
    p.sale_unit_description = CONCAT('Caja completa - ', COALESCE(p.presentation, '')),
    p.units_per_box = 40
WHERE c.slug = 'sobres'
  AND (p.sale_unit_description IS NULL OR TRIM(p.sale_unit_description) = '')
SQL);
    $pdo->exec(<<<'SQL'
UPDATE products p
JOIN categories c ON p.category_id = c.id
SET p.sale_unit_label = 'Caja 4x5L',
    p.sale_unit_description = 'Caja 4 bidones x 5 Litros',
    p.units_per_box = 4
WHERE c.slug = 'alimenticia'
  AND (p.sale_unit_description IS NULL OR TRIM(p.sale_unit_description) = '')
SQL);
    ok('Valores por categoría en productos (descripciones vacías)');
} catch (PDOException $e) {
    warn('UPDATE categorías venta: ' . $e->getMessage());
}

$configDir = $basePath . $ds . 'app' . $ds . 'config';
if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
    err('No se pudo crear app/config');
    exit(1);
}

$envPath = $basePath . $ds . '.env';
$envExample = $basePath . $ds . '.env.example';
$setEnvVar = static function (string $content, string $key, string $value): string {
    $escaped = preg_quote($key, '/');
    $line = $key . '=' . $value;
    if (preg_match('/^' . $escaped . '=.*$/m', $content)) {
        return (string) preg_replace('/^' . $escaped . '=.*$/m', $line, $content);
    }
    return rtrim($content) . "\n" . $line . "\n";
};

if (!is_file($envPath)) {
    if (is_file($envExample)) {
        copy($envExample, $envPath);
        ok('Archivo .env creado desde .env.example');
    } else {
        file_put_contents($envPath, '');
        ok('Archivo .env creado');
    }
}

$envContent = (string) file_get_contents($envPath);
$envContent = $setEnvVar($envContent, 'APP_ENV', 'local');
$envContent = $setEnvVar($envContent, 'APP_DEBUG', 'true');
$envContent = $setEnvVar($envContent, 'APP_URL', 'http://localhost/limpiaOesteSistema/public');
$envContent = $setEnvVar($envContent, 'DB_HOST', $host);
$envContent = $setEnvVar($envContent, 'DB_NAME', $dbName);
$envContent = $setEnvVar($envContent, 'DB_USER', $user);
$envContent = $setEnvVar($envContent, 'DB_PASS', $pass);
if (!preg_match('/^FTP_HOST=/m', $envContent)) {
    $envContent = $setEnvVar($envContent, 'FTP_HOST', '');
}
if (!preg_match('/^FTP_USER=/m', $envContent)) {
    $envContent = $setEnvVar($envContent, 'FTP_USER', '');
}
if (!preg_match('/^FTP_PASS=/m', $envContent)) {
    $envContent = $setEnvVar($envContent, 'FTP_PASS', '');
}
if (!preg_match('/^FTP_PATH=/m', $envContent)) {
    $envContent = $setEnvVar($envContent, 'FTP_PATH', '/public_html');
}
file_put_contents($envPath, $envContent);
ok('Variables DB_* y APP_* actualizadas en .env (app/config/database.php usa .env)');

$dirs = [
    $basePath . $ds . 'storage' . $ds . 'pdfs',
    $basePath . $ds . 'storage' . $ds . 'logs',
    $basePath . $ds . 'database',
    $basePath . $ds . 'public' . $ds . 'assets' . $ds . 'css',
    $basePath . $ds . 'public' . $ds . 'assets' . $ds . 'js',
    $basePath . $ds . 'public' . $ds . 'assets' . $ds . 'img',
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
    }
}
ok('Carpetas de almacenamiento verificadas');

$htaccessProtect = <<<'HTA'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
HTA;

foreach (['app', 'database', 'storage'] as $folder) {
    $path = $basePath . $ds . $folder . $ds . '.htaccess';
    if (!is_dir($basePath . $ds . $folder)) {
        mkdir($basePath . $ds . $folder, 0755, true);
    }
    file_put_contents($path, $htaccessProtect);
}
ok('.htaccess de protección en app/, database/, storage/');

out('', '');
out('========================================', 'green');
out('  INSTALACIÓN COMPLETADA — LIMPIA OESTE', 'green');
out('========================================', 'green');
out('Credenciales administrador:', 'yellow');
out('  Usuario: admin', '');
out('  Contraseña: limpiaOeste2026', '');
out('', '');
out('Siguiente paso: composer install (si aún no lo ejecutaste) y configurar el virtual host a public/', '');
