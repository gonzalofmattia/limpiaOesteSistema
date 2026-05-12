<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;

final class QuoteFormValidationTest extends TestCase
{
    private string $formContent;

    protected function setUp(): void
    {
        $formFile = APP_PATH . '/Views/quotes/form.php';
        $this->assertFileExists($formFile, 'El formulario de presupuestos debe existir');
        $this->formContent = file_get_contents($formFile);
    }

    public function testFormValidatesClientRequired(): void
    {
        $hasClientValidation = preg_match(
            '/(client.*required|client.*valid|seleccion.*cliente|clientFieldInvalid|formValid|selectedClientId|Seleccion)/i',
            $this->formContent
        ) === 1;

        $this->assertTrue(
            $hasClientValidation,
            'El formulario debe validar que se seleccione un cliente (Alpine / mensajes / estado)'
        );
    }

    public function testFormHasVisualValidation(): void
    {
        $this->assertMatchesRegularExpression(
            '/border-red|text-red|error|invalid/i',
            $this->formContent,
            'El formulario debe tener indicadores visuales de error (border-red, text-red, etc.)'
        );
    }

    public function testQuantityInputsHaveMinValue(): void
    {
        $hasMinRestriction = preg_match(
            '/(min\s*=\s*["\']1["\']|min\s*=\s*1|quantity.*>=\s*1|cantidad.*>=\s*1|inputmode.*numeric)/i',
            $this->formContent
        ) === 1;

        $this->assertTrue(
            $hasMinRestriction,
            'Los inputs de cantidad deben tener min="1" o validación equivalente'
        );
    }

    public function testSaveButtonCanBeDisabled(): void
    {
        $hasDisableLogic = preg_match(
            '/(disabled|:disabled|x-bind:disabled|opacity-50|cursor-not-allowed|canSave|canSubmit|isValid|formValid)/i',
            $this->formContent
        ) === 1;

        $this->assertTrue(
            $hasDisableLogic,
            'El botón guardar debe poder deshabilitarse cuando la validación falla'
        );
    }
}
