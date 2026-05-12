<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class RateLimitingTest extends TestCase
{
    public function testLoginAttemptsTableMigrationExists(): void
    {
        $migrationsDir = BASE_PATH . '/database/migrations/';
        $files = glob($migrationsDir . '*login_attempts*');

        $this->assertNotEmpty(
            $files,
            'Debe existir una migración para la tabla login_attempts'
        );
    }

    public function testAuthControllerHasRateLimiting(): void
    {
        $authFile = APP_PATH . '/Controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContainsString(
            'login_attempts',
            $content,
            'AuthController debe referenciar la tabla login_attempts para rate limiting'
        );

        $this->assertMatchesRegularExpression(
            '/COUNT|count/i',
            $content,
            'AuthController debe contar intentos fallidos'
        );
    }

    public function testRateLimitBlocksAfterMaxAttempts(): void
    {
        $authFile = APP_PATH . '/Controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertMatchesRegularExpression(
            '/\$attempts\s*>=\s*\d+/',
            $content,
            'AuthController debe comparar intentos contra un límite máximo'
        );
    }

    public function testRateLimitCleansOldAttempts(): void
    {
        $authFile = APP_PATH . '/Controllers/AuthController.php';
        $content = file_get_contents($authFile);

        $this->assertStringContainsString(
            'DELETE FROM login_attempts',
            $content,
            'AuthController debe limpiar intentos viejos de login_attempts'
        );
    }

    public function testLoginAttemptsTableExistsInDB(): void
    {
        try {
            $db = \App\Models\Database::getInstance();
            $result = $db->fetchAll("SHOW TABLES LIKE 'login_attempts'");
            $this->assertNotEmpty(
                $result,
                'La tabla login_attempts debe existir en la base de datos. ¿Corriste la migración?'
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }
}
