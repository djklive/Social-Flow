@echo off
echo ========================================
echo    SocialFlow - Tests Simples
echo ========================================
echo.

echo [INFO] Exécution des tests unitaires...
echo.
php tests/simple/EmailValidationTest.php

echo.
echo [INFO] Exécution des tests de mots de passe...
echo.
php tests/simple/PasswordTest.php

echo.
echo [INFO] Exécution des tests de base de données...
echo.
php tests/simple/SimpleDatabaseTest.php

echo.
echo ========================================
echo    Tests terminés
echo ========================================
echo.
pause