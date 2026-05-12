@echo off
echo ============================================
echo  TESTS QA - FASE 1 SEGURIDAD
echo  Sistema Limpia Oeste
echo ============================================
echo.

echo [1/2] Tests de seguridad (codigo)...
php vendor/bin/phpunit --testsuite Security --colors=always
echo.

echo [2/2] Tests de smoke (requiere Laragon corriendo)...
php vendor/bin/phpunit --testsuite Smoke --colors=always
echo.

echo ============================================
echo  TESTS COMPLETADOS
echo ============================================
pause
