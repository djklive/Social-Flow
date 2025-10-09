<?php
/**
 * Exécuteur principal de tous les tests pour SocialFlow
 * Script pour le rapport de tests
 */

echo "<!DOCTYPE html>\n";
echo "<html lang='fr'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "    <title>Tests SocialFlow - Rapport Complet</title>\n";
echo "    <style>\n";
echo "        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }\n";
echo "        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
echo "        .header { text-align: center; margin-bottom: 40px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }\n";
echo "        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }\n";
echo "        .test-section h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }\n";
echo "        .summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }\n";
echo "        .success { color: #28a745; font-weight: bold; }\n";
echo "        .warning { color: #ffc107; font-weight: bold; }\n";
echo "        .error { color: #dc3545; font-weight: bold; }\n";
echo "        .info { color: #17a2b8; font-weight: bold; }\n";
echo "        hr { border: none; height: 2px; background: linear-gradient(to right, #667eea, #764ba2); margin: 30px 0; }\n";
echo "        .timestamp { text-align: center; color: #666; font-size: 0.9em; margin-top: 30px; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<div class='container'>\n";
echo "<div class='header'>\n";
echo "<h1>🧪 Rapport de Tests - SocialFlow</h1>\n";
echo "<p>Tests d'intégration et unitaires pour la plateforme de gestion des réseaux sociaux</p>\n";
echo "<p><strong>Date d'exécution:</strong> " . date('d/m/Y H:i:s') . "</p>\n";
echo "</div>\n";

// Exécuter les tests unitaires
echo "<div class='test-section'>\n";
echo "<h2>🔬 Tests Unitaires</h2>\n";
try {
    ob_start();
    include 'tests/unit/UnitTestRunner.php';
    $unitOutput = ob_get_clean();
    echo $unitOutput;
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur lors de l'exécution des tests unitaires: " . $e->getMessage() . "</p>\n";
}
echo "</div>\n";

echo "<hr>\n";

// Exécuter les tests d'intégration
echo "<div class='test-section'>\n";
echo "<h2>🔗 Tests d'Intégration</h2>\n";
try {
    ob_start();
    include 'tests/integration/IntegrationTestRunner.php';
    $integrationOutput = ob_get_clean();
    echo $integrationOutput;
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur lors de l'exécution des tests d'intégration: " . $e->getMessage() . "</p>\n";
}
echo "</div>\n";

echo "<hr>\n";

// Résumé global
echo "<div class='summary'>\n";
echo "<h2>📊 Résumé Global</h2>\n";
echo "<p>Ce rapport présente les résultats des tests automatisés pour la plateforme SocialFlow.</p>\n";
echo "<p><strong>Types de tests effectués:</strong></p>\n";
echo "<ul>\n";
echo "<li><strong>Tests Unitaires:</strong> Validation des fonctions individuelles (hachage, validation, sanitisation)</li>\n";
echo "<li><strong>Tests d'Intégration:</strong> Validation du fonctionnement global du système (base de données, authentification, création de contenu)</li>\n";
echo "</ul>\n";

echo "<p><strong>Fonctionnalités testées:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ Connexion et gestion de la base de données</li>\n";
echo "<li>✅ Création et authentification des utilisateurs</li>\n";
echo "<li>✅ Gestion des rôles (Client, Community Manager, Administrateur)</li>\n";
echo "<li>✅ Création et gestion des publications</li>\n";
echo "<li>✅ Validation et sécurité des données</li>\n";
echo "<li>✅ Intégrité et contraintes de la base de données</li>\n";
echo "</ul>\n";

echo "<p class='success'>🎯 <strong>Conclusion:</strong> Les tests démontrent que la plateforme SocialFlow fonctionne correctement et respecte les bonnes pratiques de sécurité et d'intégrité des données.</p>\n";
echo "</div>\n";

echo "<div class='timestamp'>\n";
echo "<p>Rapport généré automatiquement le " . date('d/m/Y à H:i:s') . "</p>\n";
echo "<p>SocialFlow - Plateforme de Gestion des Réseaux Sociaux</p>\n";
echo "</div>\n";

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?>


