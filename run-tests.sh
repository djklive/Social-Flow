#!/bin/bash

echo "========================================"
echo "    SocialFlow - Execution des Tests"
echo "========================================"
echo

# Vérifier si PHPUnit est installé
if ! command -v php &> /dev/null; then
    echo "[ERREUR] PHP n'est pas installé ou non trouvé dans le PATH"
    exit 1
fi

if [ ! -f "vendor/bin/phpunit" ]; then
    echo "[ERREUR] PHPUnit n'est pas installé"
    echo
    echo "Pour installer PHPUnit, exécutez:"
    echo "composer require --dev phpunit/phpunit"
    echo
    exit 1
fi

echo "[INFO] PHPUnit trouvé, démarrage des tests..."
echo

# Créer les dossiers de résultats si nécessaire
mkdir -p tests/results
mkdir -p tests/coverage

echo "[INFO] Exécution de tous les tests..."
echo

# Exécuter tous les tests
php vendor/bin/phpunit --configuration phpunit.xml

echo
echo "========================================"
echo "    Tests terminés"
echo "========================================"
echo
echo "Résultats disponibles dans:"
echo "- tests/results/ (rapports JUnit et TestDox)"
echo "- tests/coverage/ (rapport de couverture HTML)"
echo
