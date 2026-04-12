<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function run(): void
    {
        $config = require APP_PATH . '/config/app.php';
        date_default_timezone_set($config['timezone'] ?? 'America/Argentina/Buenos_Aires');
        session_name($config['session_name'] ?? 'limpia_oeste_session');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        require_once APP_PATH . '/Helpers/functions.php';

        $router = new Router();
        /** @var list<array> $routeList */
        $routeList = require APP_PATH . '/config/routes.php';
        $router->load($routeList);
        $router->dispatch();
    }
}
