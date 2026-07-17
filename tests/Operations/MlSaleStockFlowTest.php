<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;

/**
 * Cubre el fix del flujo de stock en Ventas ML:
 * - importar/crear una venta ML debe comprometer stock (como un presupuesto aceptado).
 * - editar una venta ML aceptada debe liberar y re-comprometer (no duplicar).
 * - una venta ML delivered no debe poder editarse.
 * - debe existir una acción explícita para marcar una venta ML como entregada,
 *   que sea la que descuenta el stock físico real.
 * - borrar un presupuesto/venta delivered debe reponer el stock físico (bug previo:
 *   delete() solo cubría accepted/partially_delivered).
 */
final class MlSaleStockFlowTest extends TestCase
{
    public function testPersistSaleCommitsStockOnCreate(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/MercadoLibreController.php');

        $this->assertStringContainsString(
            'QuoteDeliveryStock::commitStock($db, $quoteId)',
            $controller,
            'persistSale() debe comprometer stock (commitStock) al crear/editar una venta ML'
        );
    }

    public function testPersistSaleReleasesCommittedBeforeReEditingAcceptedSale(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/MercadoLibreController.php');

        $this->assertStringContainsString(
            'QuoteDeliveryStock::releaseCommittedStock($db, $quoteId)',
            $controller,
            'persistSale() debe liberar el comprometido previo antes de re-comprometer al editar una venta ML aceptada'
        );
    }

    public function testPersistSaleBlocksEditingDeliveredSale(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/MercadoLibreController.php');

        $this->assertMatchesRegularExpression(
            '/existingStatus === \'delivered\'/',
            $controller,
            'persistSale() debe bloquear la edición de una venta ML ya entregada'
        );
    }

    public function testMarkDeliveredActionExists(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/MercadoLibreController.php');

        $this->assertMatchesRegularExpression(
            '/function\s+markDelivered\s*\(/',
            $controller,
            'MercadoLibreController debe tener una acción explícita para marcar una venta ML como entregada'
        );
        $this->assertStringContainsString('verifyCsrf', $controller);
    }

    public function testMarkDeliveredRouteExists(): void
    {
        $routes = file_get_contents(APP_PATH . '/config/routes.php');

        $this->assertMatchesRegularExpression(
            '#ventas-ml/\{id\}/entregado#',
            $routes,
            'Debe existir la ruta POST ventas-ml/{id}/entregado'
        );
    }

    public function testSharedDeliverHelperExists(): void
    {
        $this->assertFileExists(
            APP_PATH . '/Helpers/QuoteStatusTransitions.php',
            'Debe existir el helper compartido QuoteStatusTransitions'
        );
        $helper = file_get_contents(APP_PATH . '/Helpers/QuoteStatusTransitions.php');
        $this->assertMatchesRegularExpression('/function\s+deliver\s*\(/', $helper);
    }

    public function testMercadoLibreAndSeiqOrderControllersReuseSharedDeliverHelper(): void
    {
        $ml = file_get_contents(APP_PATH . '/Controllers/MercadoLibreController.php');
        $seiq = file_get_contents(APP_PATH . '/Controllers/SeiqOrderController.php');

        $this->assertStringContainsString(
            'QuoteStatusTransitions::deliver(',
            $ml,
            'MercadoLibreController::markDelivered() debe reusar QuoteStatusTransitions::deliver() en vez de reimplementar la guarda'
        );
        $this->assertStringContainsString(
            'QuoteStatusTransitions::deliver(',
            $seiq,
            'SeiqOrderController::markQuotesDelivered() debe reusar QuoteStatusTransitions::deliver() en vez de reimplementar la guarda'
        );
    }

    public function testVentasReportShowsMlBadge(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/SaleController.php');
        $this->assertStringContainsString(
            'is_mercadolibre',
            $controller,
            'SaleController debe traer is_mercadolibre para poder distinguir el origen ML en /ventas'
        );

        $view = file_get_contents(APP_PATH . '/Views/sales/index.php');
        $this->assertStringContainsString(
            'is_mercadolibre',
            $view,
            'La vista de /ventas debe mostrar un indicador de origen ML'
        );
    }

    public function testDeleteRevertsStockForDeliveredQuotes(): void
    {
        $controller = file_get_contents(APP_PATH . '/Controllers/QuoteController.php');

        $start = strpos($controller, 'public function delete(string $id): void');
        $this->assertIsInt($start, 'No se encontró el método delete() en QuoteController');
        $nextPublic = strpos($controller, "\n    public function ", $start + 1);
        $nextPrivate = strpos($controller, "\n    private function ", $start + 1);
        $candidates = array_filter([$nextPublic, $nextPrivate], static fn ($v): bool => $v !== false);
        $this->assertNotEmpty($candidates, 'No se pudo delimitar el final del método delete()');
        $nextMethod = min($candidates);

        $deleteBody = substr($controller, $start, $nextMethod - $start);
        $this->assertStringContainsString(
            "qst === 'delivered'",
            $deleteBody,
            'delete() debe manejar explícitamente el caso status=delivered'
        );
        $this->assertStringContainsString(
            'revertDeliveredStock',
            $deleteBody,
            'delete() debe reponer el stock físico (revertDeliveredStock) al borrar un presupuesto/venta delivered'
        );
    }
}
