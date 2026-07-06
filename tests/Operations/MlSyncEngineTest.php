<?php

declare(strict_types=1);

namespace Tests\Operations;

use App\Helpers\MlSyncEngine;
use App\Helpers\TextSafetyChecker;
use PHPUnit\Framework\TestCase;

final class MlSyncEngineTest extends TestCase
{
    public function testNoChangeWhenNothingDrifted(): void
    {
        $this->assertSame(
            MlSyncEngine::NO_CHANGE,
            MlSyncEngine::resolveField('Igual', 'Igual', 'Igual')
        );
    }

    public function testPullFromMlWhenOnlyMlChanged(): void
    {
        $this->assertSame(
            MlSyncEngine::PULL_FROM_ML,
            MlSyncEngine::resolveField('Nuevo en ML', 'Viejo', 'Viejo')
        );
    }

    public function testPushToMlWhenOnlySystemChanged(): void
    {
        $this->assertSame(
            MlSyncEngine::PUSH_TO_ML,
            MlSyncEngine::resolveField('Viejo', 'Nuevo en sistema', 'Viejo')
        );
    }

    public function testConflictWhenBothChangedToDifferentValues(): void
    {
        $this->assertSame(
            MlSyncEngine::CONFLICT,
            MlSyncEngine::resolveField('Editado en ML', 'Recalculado por el sistema', 'Original')
        );
    }

    public function testNoChangeWhenBothConvergedToSameValue(): void
    {
        $this->assertSame(
            MlSyncEngine::NO_CHANGE,
            MlSyncEngine::resolveField('Mismo valor nuevo', 'Mismo valor nuevo', 'Original')
        );
    }

    public function testConflictOnFirstRunWithNoSnapshotAndValuesDiffer(): void
    {
        $this->assertSame(
            MlSyncEngine::CONFLICT,
            MlSyncEngine::resolveField('Valor en ML', 'Valor en sistema', null)
        );
    }

    public function testNoChangeOnFirstRunWithNoSnapshotAndValuesMatch(): void
    {
        $this->assertSame(
            MlSyncEngine::NO_CHANGE,
            MlSyncEngine::resolveField('Mismo valor', 'Mismo valor', null)
        );
    }

    public function testResolveFieldWorksWithNumericValues(): void
    {
        $this->assertSame(MlSyncEngine::PULL_FROM_ML, MlSyncEngine::resolveField(1500.0, 1200.0, 1200.0));
        $this->assertSame(MlSyncEngine::PUSH_TO_ML, MlSyncEngine::resolveField(12, 20, 12));
    }

    /**
     * TextSafetyChecker es el guardrail que MlSyncEngine debe consultar antes de resolver
     * PUSH_TO_ML para descripción (ver CLAUDE.md: lenguaje prohibido dispara pérdida de
     * elegibilidad Mercado Envíos, ya pasó con el producto Strong). Confirma que el checker
     * detecta al menos uno de los términos configurados.
     */
    public function testTextSafetyCheckerDetectsBannedTerm(): void
    {
        $found = TextSafetyChecker::containsBannedMercadoEnviosTerms('Producto inflamable, no usar cerca de una llama.');
        $this->assertNotEmpty($found, 'TextSafetyChecker debería detectar "inflamable" como término bloqueado.');
    }

    public function testTextSafetyCheckerAllowsCleanText(): void
    {
        $found = TextSafetyChecker::containsBannedMercadoEnviosTerms('Rendimiento certificado SENASA, uso profesional y gastronómico.');
        $this->assertSame([], $found);
    }

    /**
     * Guardrail de regresión: si alguien saca la llamada a TextSafetyChecker o a
     * isCategoryLeaf() del motor, este test debería fallar. No reemplaza un test de
     * integración con DB (no hay fixture de DB de test en este repo), pero evita que el
     * guardrail se pierda silenciosamente en un refactor futuro.
     */
    public function testEngineSourceWiresSafetyGuardrails(): void
    {
        $source = (string) file_get_contents(APP_PATH . '/Helpers/MlSyncEngine.php');

        $this->assertMatchesRegularExpression(
            '/TextSafetyChecker::containsBannedMercadoEnviosTerms\s*\(/',
            $source,
            'MlSyncEngine debe consultar TextSafetyChecker antes de empujar una descripción a ML.'
        );
        $this->assertMatchesRegularExpression(
            '/MercadoLibreService::isCategoryLeaf\s*\(/',
            $source,
            'MlSyncEngine debe validar isCategoryLeaf() antes de aplicar un pull de categoría.'
        );
        $this->assertMatchesRegularExpression(
            '/self::CONFLICT/',
            $source,
            'MlSyncEngine debe poder resolver CONFLICT (no solo pull/push automáticos).'
        );
    }
}
