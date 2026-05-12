<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;

final class StockReconciliationTest extends TestCase
{
    public function testReconcileMethodExists(): void
    {
        $possibleFiles = [
            APP_PATH . '/Controllers/ToolsController.php',
            APP_PATH . '/Controllers/StockController.php',
        ];

        $found = false;
        foreach ($possibleFiles as $file) {
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if (preg_match('/function\s+(reconcile|reconciliar|reconciliation)/i', $content) === 1) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Debe existir un método de reconciliación de stock en ToolsController o StockController'
        );
    }

    public function testReconcileViewExists(): void
    {
        $possiblePaths = [
            APP_PATH . '/Views/tools/reconcile-stock.php',
            APP_PATH . '/Views/tools/reconcile_stock.php',
            APP_PATH . '/Views/tools/reconciliar-stock.php',
            APP_PATH . '/Views/stock/reconcile.php',
            APP_PATH . '/Views/stock/reconciliar.php',
        ];

        $exists = false;
        foreach ($possiblePaths as $path) {
            if (is_file($path)) {
                $exists = true;
                break;
            }
        }

        $this->assertTrue($exists, 'Debe existir una vista de reconciliación de stock');
    }

    public function testReconcileRouteExists(): void
    {
        $routes = file_get_contents(APP_PATH . '/config/routes.php');
        $this->assertMatchesRegularExpression(
            '/reconcil/i',
            $routes,
            'Debe existir una ruta de reconciliación en routes.php'
        );
    }

    public function testReconcilePostHasCsrf(): void
    {
        $possibleFiles = [
            APP_PATH . '/Controllers/ToolsController.php',
            APP_PATH . '/Controllers/StockController.php',
        ];

        $found = false;
        foreach ($possibleFiles as $file) {
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if (stripos($content, 'reconcil') !== false && stripos($content, 'verifyCsrf') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'El POST de reconciliación debe verificar CSRF'
        );
    }

    public function testReconcileUsesTransaction(): void
    {
        $possibleFiles = [
            APP_PATH . '/Controllers/ToolsController.php',
            APP_PATH . '/Controllers/StockController.php',
        ];

        $found = false;
        foreach ($possibleFiles as $file) {
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if (stripos($content, 'reconcil') !== false
                && (str_contains($content, 'beginTransaction') || str_contains($content, 'BEGIN'))) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'La reconciliación debe usar transacción SQL para aplicar correcciones'
        );
    }

    public function testReconcileRouteResponds(): void
    {
        $baseUrl = 'http://localhost/limpiaOesteSistema/public';

        $possibleRoutes = [
            '/tools/reconciliar-stock',
            '/tools/reconcile-stock',
            '/stock-actual/reconciliar',
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
            'La ruta de reconciliación debe existir y responder (302 a login o 200)'
        );
    }
}
