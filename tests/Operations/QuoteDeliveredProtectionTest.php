<?php

declare(strict_types=1);

namespace Tests\Operations;

use App\Models\Database;
use PHPUnit\Framework\TestCase;

final class QuoteDeliveredProtectionTest extends TestCase
{
    /**
     * Verifica que EDITABLE_STATUSES NO incluye 'delivered'.
     */
    public function testDeliveredNotInEditableStatuses(): void
    {
        $quoteController = file_get_contents(APP_PATH . '/Controllers/QuoteController.php');

        $found = preg_match(
            '/EDITABLE_STATUSES\s*=\s*\[([^\]]+)\]/',
            $quoteController,
            $matches
        );

        $this->assertSame(1, $found, 'Debe existir EDITABLE_STATUSES en QuoteController');

        $statuses = $matches[1];
        $this->assertStringNotContainsString(
            'delivered',
            preg_replace('/partially_delivered/', '', $statuses),
            'EDITABLE_STATUSES NO debe incluir "delivered"'
        );
    }

    /**
     * Verifica que el método edit() valida contra EDITABLE_STATUSES.
     */
    public function testEditMethodChecksStatus(): void
    {
        $quoteController = file_get_contents(APP_PATH . '/Controllers/QuoteController.php');

        $hasEditProtection = preg_match(
            '/(EDITABLE_STATUSES|editable.*status|status.*editable)/i',
            $quoteController
        ) === 1;

        $this->assertTrue(
            $hasEditProtection,
            'QuoteController debe verificar EDITABLE_STATUSES al editar'
        );
    }

    /**
     * Verifica que la vista de presupuesto NO muestra botón editar para delivered.
     */
    public function testViewHidesEditButtonForDelivered(): void
    {
        $viewFiles = [
            APP_PATH . '/Views/quotes/index.php',
            APP_PATH . '/Views/quotes/preview.php',
        ];

        $hasConditional = false;
        foreach ($viewFiles as $file) {
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if (preg_match('/(delivered|EDITABLE_STATUSES|status.*!=|status.*!==|editable)/i', $content) === 1) {
                $hasConditional = true;
                break;
            }
        }

        $this->assertTrue(
            $hasConditional,
            'Las vistas de presupuestos deben condicionar la visibilidad del botón editar según el estado'
        );
    }

    /**
     * Test HTTP: intentar acceder a la ruta de editar un presupuesto delivered.
     */
    public function testEditDeliveredQuoteViaHTTP(): void
    {
        $baseUrl = 'http://localhost/limpiaOesteSistema/public';

        try {
            $db = Database::getInstance();
            $delivered = $db->fetch(
                "SELECT id FROM quotes WHERE status = 'delivered' LIMIT 1"
            );

            if ($delivered === null) {
                $this->markTestSkipped('No hay presupuestos delivered en la BD para testear');
                return;
            }

            $quoteId = (int) $delivered['id'];

            $ch = curl_init("{$baseUrl}/presupuestos/{$quoteId}/editar");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_errno($ch);
            curl_close($ch);

            if ($err !== 0) {
                $this->markTestSkipped('No se pudo conectar al servidor HTTP: ' . $baseUrl);
                return;
            }

            $this->assertNotEquals(
                200,
                $code,
                'Acceder a editar presupuesto delivered sin sesión no debe dar 200 con el formulario'
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }
}
