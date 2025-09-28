<?php
/**
 * Bootstrap pour les tests PHPUnit
 * SocialFlow - Configuration des Tests
 */

// Définir l'environnement de test
define('APP_ENV', 'testing');
define('TESTING', true);

// Inclure l'autoloader de Composer si disponible
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Inclure les fichiers de configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Configuration de la base de données de test
function getTestDB() {
    try {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'socialflow_test';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erreur de connexion à la base de données de test: " . $e->getMessage());
    }
}

// Fonction pour nettoyer la base de données de test
function cleanTestDatabase() {
    $db = getTestDB();
    
    // Désactiver les contraintes de clés étrangères
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Tables à nettoyer (dans l'ordre inverse des dépendances)
    $tables = [
        'activity_logs',
        'notifications',
        'statistics',
        'posts',
        'drafts',
        'social_accounts',
        'user_settings',
        'collaboration_invites',
        'trash',
        'payments',
        'subscriptions',
        'client_assignments',
        'users'
    ];
    
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE $table");
    }
    
    // Réactiver les contraintes de clés étrangères
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// Fonction pour créer des données de test
function createTestData() {
    $db = getTestDB();
    
    // Créer un utilisateur admin de test
    $adminPassword = hash_password('admin123');
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified, created_at) 
        VALUES ('admin@test.com', ?, 'Admin', 'Test', 'admin', 'active', TRUE, NOW())
    ");
    $stmt->execute([$adminPassword]);
    $adminId = $db->lastInsertId();
    
    // Créer un community manager de test
    $cmPassword = hash_password('cm123');
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified, created_at) 
        VALUES ('cm@test.com', ?, 'CM', 'Test', 'community_manager', 'active', TRUE, NOW())
    ");
    $stmt->execute([$cmPassword]);
    $cmId = $db->lastInsertId();
    
    // Créer un client de test
    $clientPassword = hash_password('client123');
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified, created_at) 
        VALUES ('client@test.com', ?, 'Client', 'Test', 'client', 'active', TRUE, NOW())
    ");
    $stmt->execute([$clientPassword]);
    $clientId = $db->lastInsertId();
    
    // Créer une assignation
    $stmt = $db->prepare("
        INSERT INTO client_assignments (client_id, community_manager_id, assigned_at, status) 
        VALUES (?, ?, NOW(), 'active')
    ");
    $stmt->execute([$clientId, $cmId]);
    
    // Créer un abonnement
    $stmt = $db->prepare("
        INSERT INTO subscriptions (client_id, plan_type, price, status, start_date, end_date, created_at) 
        VALUES (?, 'monthly', 29.99, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), NOW())
    ");
    $stmt->execute([$clientId]);
    
    return [
        'admin_id' => $adminId,
        'cm_id' => $cmId,
        'client_id' => $clientId
    ];
}

// Gestion des erreurs pour les tests
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Configuration du timezone
date_default_timezone_set('Europe/Paris');

// Messages de démarrage
if (php_sapi_name() === 'cli') {
    echo "=== SocialFlow Test Environment ===\n";
    echo "Environment: " . APP_ENV . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "=====================================\n\n";
}
?>
