<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
$pdo = new PDO(
    'mysql:host=' . \App\Helpers\Env::get('DB_HOST') . ';dbname=' . \App\Helpers\Env::get('DB_NAME'),
    \App\Helpers\Env::get('DB_USER'),
    \App\Helpers\Env::get('DB_PASS')
);
$pdo->exec("UPDATE settings SET setting_value = '75' WHERE setting_key = 'ml_default_markup'");
echo "ml_default_markup actualizado a 75\n";
