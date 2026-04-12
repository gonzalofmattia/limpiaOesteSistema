<?php

declare(strict_types=1);

return [
    'host' => \App\Helpers\Env::get('DB_HOST', 'localhost'),
    'database' => \App\Helpers\Env::get('DB_NAME', 'limpia_oeste_abm'),
    'username' => \App\Helpers\Env::get('DB_USER', 'root'),
    'password' => \App\Helpers\Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
