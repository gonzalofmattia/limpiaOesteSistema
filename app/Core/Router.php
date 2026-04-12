<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{method:string,pattern:string,handler:array{0:class-string,1:string},public:bool}> */
    private array $routes = [];

    /** @param list<array{0:string,1:string,2:string|array{0:class-string,1:string},3?:array}> $definitions */
    public function load(array $definitions): void
    {
        foreach ($definitions as $def) {
            [$method, $pattern, $handler] = $def;
            $opts = $def[3] ?? [];
            $isPublic = !empty($opts['public']);
            if (is_string($handler)) {
                [$classShort, $action] = explode('@', $handler);
                $class = 'App\\Controllers\\' . $classShort;
                $handler = [$class, $action];
            }
            $this->routes[] = [
                'method' => strtoupper($method),
                'pattern' => $pattern,
                'handler' => $handler,
                'public' => $isPublic,
            ];
        }
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $url = $_GET['url'] ?? '';
        $url = trim((string) $url, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->patternToRegex($route['pattern']);
            if (preg_match($regex, $url, $matches)) {
                $paramValues = $this->orderedParams($route['pattern'], $matches);
                if (!$route['public'] && empty($_SESSION['admin_user_id'])) {
                    \redirect('/login');
                }
                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->{$action}(...$paramValues);
                return;
            }
        }

        http_response_code(404);
        echo '404 — Ruta no encontrada';
    }

    /** @param array<string, string> $matches */
    private function orderedParams(string $pattern, array $matches): array
    {
        $pattern = trim($pattern, '/');
        if (!preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $pattern, $m)) {
            return [];
        }
        $ordered = [];
        foreach ($m[1] as $name) {
            if (isset($matches[$name])) {
                $ordered[] = $matches[$name];
            }
        }
        return $ordered;
    }

    private function patternToRegex(string $pattern): string
    {
        $pattern = trim($pattern, '/');
        $parts = $pattern === '' ? [] : explode('/', $pattern);
        $escaped = [];
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $m)) {
                $name = $m[1];
                $escaped[] = '(?P<' . $name . '>[0-9]+)';
            } else {
                $escaped[] = preg_quote($part, '#');
            }
        }
        $inner = implode('/', $escaped);
        return '#^' . ($inner === '' ? '' : $inner) . '$#';
    }
}
