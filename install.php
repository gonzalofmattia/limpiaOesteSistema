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
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    default_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    default_markup DECIMAL(5,2) DEFAULT NULL,
    presentation_info VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    status ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
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
