<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class EnvExampleTest extends TestCase
{
    private string $envExamplePath;

    protected function setUp(): void
    {
        $this->envExamplePath = BASE_PATH . '/.env.example';
        $this->assertFileExists($this->envExamplePath, '.env.example debe existir');
    }

    public function testNoRealPasswordsInEnvExample(): void
    {
        $content = file_get_contents($this->envExamplePath);
        $lines = explode("\n", $content);
        $sensitiveKeys = ['FTP_PASS', 'DB_PASS', 'MAIL_PASSWORD'];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            foreach ($sensitiveKeys as $key) {
                if (str_starts_with($line, $key . '=')) {
                    $value = substr($line, strlen($key) + 1);
                    $this->assertMatchesRegularExpression(
                        '/^(|tu_.*|your_.*|changeme|xxx|placeholder|CHANGE_ME|.*_aqui.*)$/i',
                        $value,
                        "ALERTA: {$key} en .env.example parece contener una credencial real: '{$value}'"
                    );
                }
            }
        }
    }

    public function testEnvExampleHasAllRequiredKeys(): void
    {
        $content = file_get_contents($this->envExamplePath);
        $requiredKeys = [
            'APP_DEBUG',
            'APP_URL',
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'FTP_HOST',
            'FTP_USER',
            'FTP_PASS',
            'FTP_PATH',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertStringContainsString(
                $key,
                $content,
                "Falta la key {$key} en .env.example"
            );
        }
    }

    public function testEnvNotInRepository(): void
    {
        $gitignore = file_get_contents(BASE_PATH . '/.gitignore');
        $this->assertStringContainsString('.env', $gitignore, '.env debe estar en .gitignore');
    }
}
