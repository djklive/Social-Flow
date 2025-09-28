<?php
/**
 * Test simple de base de données (version simplifiée)
 * SocialFlow - Tests Simples
 */

require_once __DIR__ . '/SimpleTestRunner.php';

// Configuration directe de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialflow_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getTestDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

$runner = new SimpleTestRunner();

// Test 1: Connexion à la base de données
$runner->addTest('Base de Données - Connexion', function() {
    try {
        $db = getTestDB();
        SimpleTestRunner::assertNotNull($db, "La connexion à la base de données doit être établie");
        
        // Tester une requête simple
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        SimpleTestRunner::assertEquals(1, $result['test'], "La requête de test doit retourner 1");
        
        return true;
    } catch (Exception $e) {
        throw new Exception("Erreur de connexion: " . $e->getMessage());
    }
});

// Test 2: Vérification des tables principales
$runner->addTest('Base de Données - Tables Principales', function() {
    $db = getTestDB();
    
    $requiredTables = ['users', 'subscriptions', 'posts'];
    
    foreach ($requiredTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        
        SimpleTestRunner::assertNotNull($result, "La table '$table' doit exister");
    }
    
    return true;
});

// Test 3: Structure de la table users
$runner->addTest('Base de Données - Structure Table Users', function() {
    $db = getTestDB();
    
    $requiredColumns = ['id', 'email', 'password_hash', 'first_name', 'last_name', 'role', 'status'];
    
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $column) {
        SimpleTestRunner::assertTrue(
            in_array($column, $columnNames), 
            "La colonne '$column' doit exister dans la table users"
        );
    }
    
    return true;
});

// Test 4: Données de test
$runner->addTest('Base de Données - Données de Test', function() {
    $db = getTestDB();
    
    // Vérifier qu'il y a des utilisateurs de test
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    SimpleTestRunner::assertTrue(
        $result['count'] > 0, 
        "Il doit y avoir au moins un utilisateur dans la base de données"
    );
    
    // Vérifier qu'il y a un admin
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $result = $stmt->fetch();
    
    SimpleTestRunner::assertTrue(
        $result['count'] > 0, 
        "Il doit y avoir au moins un administrateur"
    );
    
    return true;
});

// Test 5: Test d'insertion simple
$runner->addTest('Base de Données - Test Insertion', function() {
    $db = getTestDB();
    
    // Créer un utilisateur de test temporaire
    $testEmail = 'test_' . time() . '@example.com';
    $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, status, created_at) 
        VALUES (?, ?, 'Test', 'User', 'client', 'active', NOW())
    ");
    
    $result = $stmt->execute([$testEmail, $hashedPassword]);
    SimpleTestRunner::assertTrue($result, "L'insertion doit réussir");
    
    $userId = $db->lastInsertId();
    SimpleTestRunner::assertTrue($userId > 0, "L'ID utilisateur doit être positif");
    
    // Vérifier que l'utilisateur existe
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    SimpleTestRunner::assertNotNull($user, "L'utilisateur doit être trouvé");
    SimpleTestRunner::assertEquals($testEmail, $user['email'], "L'email doit correspondre");
    
    // Nettoyer
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    return true;
});

// Exécuter les tests
$runner->runAll();
?>
