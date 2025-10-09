@echo off
echo ========================================
echo    SocialFlow - Execution des Tests
echo ========================================
echo.

REM Vérifier si PHPUnit est installé
php vendor/bin/phpunit --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERREUR] PHPUnit n'est pas installé ou non trouvé
    echo.
    echo Pour installer PHPUnit, exécutez:
    echo composer require --dev phpunit/phpunit
    echo.
    pause
    exit /b 1
)

echo [INFO] PHPUnit trouvé, démarrage des tests...
echo.

REM Créer les dossiers de résultats si nécessaire
if not exist "tests\results" mkdir tests\results
if not exist "tests\coverage" mkdir tests\coverage

echo [INFO] Exécution de tous les tests...
echo.

REM Exécuter tous les tests
php vendor/bin/phpunit --configuration phpunit.xml

echo.
echo ========================================
echo    Tests terminés
echo ========================================
echo.
echo Résultats disponibles dans:
echo - tests\results\ (rapports JUnit et TestDox)
echo - tests\coverage\ (rapport de couverture HTML)
echo.
pause
