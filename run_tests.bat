@echo off
echo ============================================
echo  TESTS QA - SISTEMA LIMPIA OESTE
echo ============================================
echo.

echo [1/4] Tests de seguridad (Fase 1)...
php vendor/bin/phpunit --testsuite Security --colors=always
echo.

echo [2/4] Tests de operaciones (Fase 2)...
php vendor/bin/phpunit --testsuite Operations --colors=always
echo.

echo [3/4] Tests de smoke - Fase 1 (requiere Laragon)...
php vendor/bin/phpunit --testsuite Smoke --testdox --colors=always
echo.

echo ============================================
echo  TESTS COMPLETADOS
echo ============================================
pause
