<?php

declare(strict_types=1);

namespace Tests\Operations;

use App\Models\Database;
use PHPUnit\Framework\TestCase;

final class StockMinimumTest extends TestCase
{
    public function testStockMinimumColumnExists(): void
    {
        try {
            $db = Database::getInstance();
            $columns = $db->fetchAll("SHOW COLUMNS FROM products LIKE 'stock_minimum'");
            $this->assertNotEmpty(
                $columns,
                'La columna stock_minimum debe existir en la tabla products. ¿Corriste la migración?'
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }

    public function testStockMinimumIsNullable(): void
    {
        try {
            $db = Database::getInstance();
            $column = $db->fetch("SHOW COLUMNS FROM products LIKE 'stock_minimum'");
            if ($column === null) {
                $this->markTestSkipped('Columna stock_minimum no existe');
                return;
            }
            $this->assertSame(
                'YES',
                (string) ($column['Null'] ?? ''),
                'stock_minimum debe ser nullable (NULL = sin alerta)'
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }

    public function testProductFormHasStockMinimumField(): void
    {
        $formFile = APP_PATH . '/Views/products/form.php';
        $this->assertFileExists($formFile);

        $content = file_get_contents($formFile);
        $this->assertStringContainsString(
            'stock_minimum',
            $content,
            'El formulario de producto debe incluir el campo stock_minimum'
        );
    }

    public function testProductControllerHandlesStockMinimum(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/ProductController.php');
        $this->assertStringContainsString(
            'stock_minimum',
            $controller,
            'ProductController debe manejar el campo stock_minimum'
        );
    }

    public function testStockViewShowsMinimumColumn(): void
    {
        $stockView = APP_PATH . '/Views/stock/index.php';
        $this->assertFileExists($stockView);

        $content = file_get_contents($stockView);
        $this->assertStringContainsString(
            'stock_minimum',
            $content,
            'La vista de stock debe referenciar stock_minimum'
        );

        $this->assertMatchesRegularExpression(
            '/bg-red|red-50|text-red|danger/i',
            $content,
            'La vista de stock debe tener indicador visual rojo para stock bajo'
        );
    }

    public function testReposicionMethodExists(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/StockController.php');

        $this->assertMatchesRegularExpression(
            '/function\s+(reposicion|replenishment|suggestion)/i',
            $controller,
            'StockController debe tener un método de reposición/sugerencia'
        );
    }

    public function testReposicionViewExists(): void
    {
        $possiblePaths = [
            APP_PATH . '/Views/stock/reposicion.php',
            APP_PATH . '/Views/stock/replenishment.php',
            APP_PATH . '/Views/stock/suggestion.php',
        ];

        $exists = false;
        foreach ($possiblePaths as $path) {
            if (is_file($path)) {
                $exists = true;
                break;
            }
        }

        $this->assertTrue($exists, 'Debe existir una vista de reposición en Views/stock/');
    }

    public function testReposicionRouteExists(): void
    {
        $routes = file_get_contents(APP_PATH . '/config/routes.php');
        $this->assertMatchesRegularExpression(
            '/reposicion|replenishment|suggestion/i',
            $routes,
            'Debe existir una ruta para la vista de reposición en routes.php'
        );
    }

    public function testDashboardHasLowStockCard(): void
    {
        $dashboard = file_get_contents(APP_PATH . '/Views/dashboard/index.php');
        $this->assertMatchesRegularExpression(
            '/stock.*(bajo|low|minimum|mínimo)/i',
            $dashboard,
            'El Dashboard debe tener una tarjeta/indicador de stock bajo'
        );
    }

    public function testDashboardControllerCalculatesLowStock(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/DashboardController.php');
        $this->assertMatchesRegularExpression(
            '/lowStock|low_stock|stock_minimum/i',
            $controller,
            'DashboardController debe calcular la cantidad de productos con stock bajo'
        );
    }

    public function testReposicionRouteResponds(): void
    {
        $baseUrl = 'http://sistema.limpiaOeste.test';

        $possibleRoutes = [
            '/stock-actual/reposicion',
            '/stock/reposicion',
        ];

        $anyResponds = false;
        foreach ($possibleRoutes as $route) {
            $ch = curl_init($baseUrl . $route);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 302 || $code === 200) {
                $anyResponds = true;
                break;
            }
        }

        $this->assertTrue(
            $anyResponds,
            'La ruta de reposición debe existir y responder (302 a login o 200)'
        );
    }
}
