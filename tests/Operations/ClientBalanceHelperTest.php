<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;

final class ClientBalanceHelperTest extends TestCase
{
    /**
     * Verifica que recalculateClientBalance NO está duplicado en los controladores.
     * Debe estar en UN SOLO lugar (helper compartido).
     */
    public function testRecalculateNotDuplicatedInControllers(): void
    {
        $quoteController = file_get_contents(APP_PATH . '/Controllers/QuoteController.php');
        $accountController = file_get_contents(APP_PATH . '/Controllers/AccountController.php');

        $quoteHasMethod = preg_match(
            '/function\s+recalculateClientBalance\s*\(/i',
            $quoteController
        ) === 1;
        $accountHasMethod = preg_match(
            '/function\s+recalculateClientBalance\s*\(/i',
            $accountController
        ) === 1;

        $this->assertFalse(
            $quoteHasMethod && $accountHasMethod,
            'recalculateClientBalance() está definido en AMBOS controladores. Debe estar en un solo helper compartido.'
        );
    }

    /**
     * Verifica que el helper compartido existe y tiene el método (recalculateBalance en ClientReceivableSummary).
     */
    public function testRecalculateExistsInHelper(): void
    {
        $helpersDir = APP_PATH . '/Helpers/';
        $found = false;
        $foundIn = '';

        $files = glob($helpersDir . '*.php') ?: [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/function\s+recalculate(Client)?Balance\s*\(/i', $content) === 1) {
                $found = true;
                $foundIn = basename($file);
                break;
            }
        }

        if (!$found) {
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/static\s+function\s+recalculate/i', $content) === 1) {
                    $found = true;
                    $foundIn = basename($file);
                    break;
                }
            }
        }

        $this->assertTrue(
            $found,
            'recalculateClientBalance (o recalculateBalance) debe existir como método en algún Helper. No se encontró en: '
            . implode(', ', array_map('basename', $files))
        );
        $this->assertSame('ClientReceivableSummary.php', $foundIn, 'Se esperaba ClientReceivableSummary::recalculateBalance');
    }

    /**
     * Verifica que ambos controladores USAN el helper (no su propia implementación).
     */
    public function testControllersCallHelper(): void
    {
        $quoteController = file_get_contents(APP_PATH . '/Controllers/QuoteController.php');
        $accountController = file_get_contents(APP_PATH . '/Controllers/AccountController.php');

        $helperPattern = '/(ClientReceivableSummary|ClientBalanceHelper|BalanceHelper)::/i';

        $quoteUsesHelper = preg_match($helperPattern, $quoteController) === 1;
        $accountUsesHelper = preg_match($helperPattern, $accountController) === 1;

        if (!$quoteUsesHelper && !$accountUsesHelper) {
            $functionsFile = file_get_contents(APP_PATH . '/Helpers/functions.php');
            $inGlobalHelpers = preg_match('/function\s+recalculateClientBalance/i', $functionsFile) === 1;

            $this->assertTrue(
                $inGlobalHelpers,
                'Ni QuoteController ni AccountController llaman a un helper externo para recalculateClientBalance. ¿Se extrajo correctamente?'
            );
        } else {
            $this->assertTrue($quoteUsesHelper, 'QuoteController debe usar ClientReceivableSummary (u otro helper) para recalcular saldo.');
            $this->assertTrue($accountUsesHelper, 'AccountController debe usar ClientReceivableSummary (u otro helper) para recalcular saldo.');
        }
    }
}
