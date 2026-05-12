<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require_once BASE_PATH . '/vendor/autoload.php';

require_once APP_PATH . '/Helpers/Env.php';
App\Helpers\Env::load(BASE_PATH . '/.env');

require_once APP_PATH . '/Helpers/functions.php';
