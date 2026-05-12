<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class RoutesTest extends TestCase
{
    private string $routesContent;

    protected function setUp(): void
    {
        $routesFile = APP_PATH . '/config/routes.php';
        $this->assertFileExists($routesFile);
        $this->routesContent = file_get_contents($routesFile);
    }

    public function testNoPedidoSeiqRoutes(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            "/['\"]pedido-seiq/",
            $this->routesContent,
            'No deben existir rutas con prefijo "pedido-seiq" — deben usar "pedidos-proveedor"'
        );
    }

    public function testPedidosProveedorRoutesExist(): void
    {
        $this->assertStringContainsString(
            'pedidos-proveedor',
            $this->routesContent,
            'Deben existir rutas con prefijo "pedidos-proveedor"'
        );
    }

    public function testNoLinksToOldRoutesInViews(): void
    {
        $viewsDir = APP_PATH . '/Views/';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir)
        );

        $violations = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'pedido-seiq')) {
                    $violations[] = str_replace($viewsDir, '', $file->getPathname());
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Archivos de vistas que todavía referencian "pedido-seiq": ' . implode(', ', $violations)
        );
    }

    public function testNoLinksToOldRoutesInJS(): void
    {
        $jsDir = BASE_PATH . '/public/assets/js/';
        if (!is_dir($jsDir)) {
            $this->markTestSkipped('No existe carpeta public/assets/js/');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($jsDir)
        );

        $violations = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'pedido-seiq')) {
                    $violations[] = str_replace($jsDir, '', $file->getPathname());
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Archivos JS que todavía referencian "pedido-seiq": ' . implode(', ', $violations)
        );
    }
}
