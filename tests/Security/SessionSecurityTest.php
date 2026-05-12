<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class SessionSecurityTest extends TestCase
{
    public function testAuthControllerHasSessionRegenerate(): void
    {
        $authFile = APP_PATH . '/Controllers/AuthController.php';
        $this->assertFileExists($authFile);

        $content = file_get_contents($authFile);
        $this->assertStringContainsString(
            'session_regenerate_id',
            $content,
            'AuthController debe llamar session_regenerate_id(true) después del login'
        );
    }

    public function testSessionRegenerateUsesDeleteOldSession(): void
    {
        $authFile = APP_PATH . '/Controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContainsString(
            'session_regenerate_id(true)',
            $content,
            'session_regenerate_id debe usarse con true para eliminar la sesión vieja'
        );
    }

    public function testAppHasSecureCookieParams(): void
    {
        $appFile = APP_PATH . '/Core/App.php';
        $indexFile = BASE_PATH . '/public/index.php';

        $appContent = file_get_contents($appFile);
        $indexContent = file_get_contents($indexFile);
        $combined = $appContent . $indexContent;

        $this->assertStringContainsString(
            'httponly',
            strtolower($combined),
            'La cookie de sesión debe tener httponly configurado (en App.php o index.php)'
        );

        $this->assertStringContainsString(
            'samesite',
            strtolower($combined),
            'La cookie de sesión debe tener samesite configurado'
        );
    }

    public function testCookieHasSecureFlag(): void
    {
        $appFile = APP_PATH . '/Core/App.php';
        $content = file_get_contents($appFile);

        $this->assertStringContainsString(
            "'secure'",
            $content,
            'session_set_cookie_params debe incluir el flag secure'
        );
    }

    public function testSecurityHeadersPresent(): void
    {
        $indexFile = BASE_PATH . '/public/index.php';
        $htaccess = BASE_PATH . '/public/.htaccess';

        $indexContent = file_get_contents($indexFile);
        $htaccessContent = file_exists($htaccess) ? file_get_contents($htaccess) : '';
        $combined = $indexContent . $htaccessContent;

        $this->assertStringContainsString(
            'X-Frame-Options',
            $combined,
            'Debe existir header X-Frame-Options en index.php o .htaccess'
        );

        $this->assertStringContainsString(
            'X-Content-Type-Options',
            $combined,
            'Debe existir header X-Content-Type-Options'
        );

        $this->assertStringContainsString(
            'X-XSS-Protection',
            $combined,
            'Debe existir header X-XSS-Protection'
        );

        $this->assertStringContainsString(
            'Referrer-Policy',
            $combined,
            'Debe existir header Referrer-Policy'
        );
    }
}
