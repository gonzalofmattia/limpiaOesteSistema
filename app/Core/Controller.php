<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected bool $authRequired = true;

    public function requiresAuth(): bool
    {
        return $this->authRequired;
    }

    /** @param array<string, mixed> $data */
    protected function view(string $path, array $data = [], ?string $layout = 'layout/main'): void
    {
        $viewFile = APP_PATH . '/Views/' . $path . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . e($path);
            return;
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        if ($layout !== null) {
            $layoutFile = APP_PATH . '/Views/' . $layout . '.php';
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /** @param array<string, mixed> $data */
    protected function viewRaw(string $path, array $data = []): void
    {
        $this->view($path, $data, null);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
