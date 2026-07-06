<?php

declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class OperationsFlowsTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = 'http://sistema.limpiaOeste.test';

        $ch = curl_init($this->baseUrl . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true,
        ]);
        $result = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($result === false || $err !== 0) {
            $this->markTestSkipped('Laragon no está corriendo o el proyecto no es accesible en ' . $this->baseUrl);
        }
    }

    /**
     * @return array{code:int, headers:string, body:string}
     */
    private function httpGet(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = $response !== false ? substr($response, 0, $headerSize) : '';
        $body = $response !== false ? substr($response, $headerSize) : '';
        curl_close($ch);

        return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
    }

    public function testStockPageLoads(): void
    {
        $r = $this->httpGet('/stock-actual');
        $this->assertContains($r['code'], [200, 302], 'La página de stock debe responder 200 o 302');
    }

    public function testStockFilterBajoWorks(): void
    {
        $r = $this->httpGet('/stock-actual?stock_filter=bajo');
        $this->assertContains($r['code'], [200, 302], 'El filtro de stock bajo debe responder correctamente');
    }

    public function testDashboardLoads(): void
    {
        $r = $this->httpGet('/');
        $this->assertContains($r['code'], [200, 302], 'El dashboard debe responder 200 o 302');
    }

    public function testQuotesListLoads(): void
    {
        $r = $this->httpGet('/presupuestos');
        $this->assertContains($r['code'], [200, 302], 'La lista de presupuestos debe responder');
    }

    public function testQuoteCreateFormLoads(): void
    {
        $r = $this->httpGet('/presupuestos/crear');
        $this->assertContains($r['code'], [200, 302], 'El formulario de crear presupuesto debe responder');
    }

    public function testProductsListLoads(): void
    {
        $r = $this->httpGet('/productos');
        $this->assertContains($r['code'], [200, 302], 'La lista de productos debe responder');
    }

    public function testClientsListLoads(): void
    {
        $r = $this->httpGet('/clientes');
        $this->assertContains($r['code'], [200, 302], 'La lista de clientes debe responder');
    }

    public function testCuentaCorrienteLoads(): void
    {
        $r = $this->httpGet('/cuenta-corriente');
        $this->assertContains($r['code'], [200, 302], 'Cuenta corriente debe responder');
    }

    public function testPedidosProveedorLoads(): void
    {
        $r = $this->httpGet('/pedidos-proveedor');
        $this->assertContains($r['code'], [200, 302], 'Pedidos a proveedor debe responder');
    }

    public function testApiProductSearchStillWorks(): void
    {
        $r = $this->httpGet('/api/productos/buscar?q=duft');
        $this->assertContains(
            $r['code'],
            [200, 302],
            'API búsqueda de productos debe responder 200 (autenticado) o 302 (login)'
        );
        if ($r['code'] === 200) {
            $json = json_decode($r['body'], true);
            $this->assertIsArray($json, 'La respuesta debe ser JSON válido');
        }
    }

    public function testApiProductSearchEmptyQuery(): void
    {
        $r = $this->httpGet('/api/productos/buscar?q=');
        $this->assertContains($r['code'], [200, 302, 400], 'API búsqueda con query vacío no debe dar error 500');
        if ($r['code'] === 200) {
            $json = json_decode($r['body'], true);
            $this->assertIsArray($json);
        }
    }

    public function testApiComboSearchStillWorks(): void
    {
        $r = $this->httpGet('/api/combos/buscar?q=xx');
        $this->assertContains(
            $r['code'],
            [200, 302],
            'API búsqueda de combos debe responder 200 (autenticado) o 302 (login)'
        );
        if ($r['code'] === 200) {
            $json = json_decode($r['body'], true);
            $this->assertIsArray($json, 'La respuesta debe ser JSON válido');
        }
    }

    public function testSettingsLoads(): void
    {
        $r = $this->httpGet('/settings');
        $this->assertContains($r['code'], [200, 302], 'Settings debe responder');
    }

    public function testNoInternalServerErrors(): void
    {
        $routes = [
            '/', '/login', '/productos', '/clientes',
            '/presupuestos', '/stock-actual', '/pedidos-proveedor',
            '/cuenta-corriente', '/settings', '/listas',
        ];

        $errors = [];
        foreach ($routes as $route) {
            $r = $this->httpGet($route);
            if ($r['code'] === 500) {
                $errors[] = $route;
            }
        }

        $this->assertEmpty(
            $errors,
            'Las siguientes rutas devuelven error 500: ' . implode(', ', $errors)
        );
    }
}
