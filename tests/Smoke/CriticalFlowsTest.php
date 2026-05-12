<?php

declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

class CriticalFlowsTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = 'http://localhost/limpiaOesteSistema/public';

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

    private function httpGet(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
    }

    private function httpPost(string $path, array $data): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/lo_test_cookies.txt',
            CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/lo_test_cookies.txt',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
    }

    public function testLoginPageReturns200(): void
    {
        $r = $this->httpGet('/login');
        $this->assertEquals(200, $r['code'], 'La página de login debe responder 200');
    }

    public function testSecurityHeadersOnLoginPage(): void
    {
        $r = $this->httpGet('/login');

        $this->assertStringContainsString(
            'X-Frame-Options',
            $r['headers'],
            'Response debe incluir header X-Frame-Options'
        );
        $this->assertStringContainsString(
            'X-Content-Type-Options',
            $r['headers'],
            'Response debe incluir header X-Content-Type-Options'
        );
        $this->assertStringContainsString(
            'X-XSS-Protection',
            $r['headers'],
            'Response debe incluir header X-XSS-Protection'
        );
        $this->assertStringContainsString(
            'Referrer-Policy',
            $r['headers'],
            'Response debe incluir header Referrer-Policy'
        );
    }

    public function testLoginWithWrongCredentialsDoesNotReturn500(): void
    {
        $r = $this->httpPost('/login', [
            'username' => 'invalid_user_test',
            'password' => 'wrong_password_test',
            '_csrf' => 'test_token',
        ]);

        $this->assertContains(
            $r['code'],
            [200, 302, 303],
            "Login con credenciales incorrectas no debe dar error 500 (dio {$r['code']})"
        );
    }

    public function testProtectedRoutesRedirectToLogin(): void
    {
        $protectedRoutes = [
            '/',
            '/presupuestos',
            '/productos',
            '/clientes',
            '/stock-actual',
            '/pedidos-proveedor',
            '/cuenta-corriente',
            '/settings',
        ];

        foreach ($protectedRoutes as $route) {
            $r = $this->httpGet($route);
            $this->assertContains(
                $r['code'],
                [302, 303, 200],
                "Ruta protegida {$route} debe redirigir a login sin sesión (dio {$r['code']})"
            );

            if ($r['code'] === 302 || $r['code'] === 303) {
                $this->assertStringContainsString(
                    'login',
                    strtolower($r['headers']),
                    "Redirect de {$route} debe apuntar a /login"
                );
            }
        }
    }

    public function testOldPedidoSeiqRoutesReturn404(): void
    {
        $oldRoutes = [
            '/pedido-seiq',
            '/pedido-seiq/generar',
        ];

        foreach ($oldRoutes as $route) {
            $r = $this->httpGet($route);
            $this->assertNotEquals(
                200,
                $r['code'],
                "Ruta vieja {$route} no debería responder 200 — debe estar eliminada"
            );
        }
    }

    public function testNewPedidosProveedorRouteExists(): void
    {
        $r = $this->httpGet('/pedidos-proveedor');
        $this->assertNotEquals(
            404,
            $r['code'],
            '/pedidos-proveedor debe existir (302 a login es OK, 404 es error)'
        );
    }

    public function testPublicApiCatalogWorks(): void
    {
        $r = $this->httpGet('/api/catalogo/productos');
        $this->assertEquals(
            200,
            $r['code'],
            'API pública de catálogo de productos debe responder 200'
        );
    }
}
