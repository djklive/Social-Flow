<?php
/**
 * Test simple de connexion à la base de données
 * SocialFlow - Tests Simples
 */

require_once __DIR__ . '/SimpleTestRunner.php';
require_once __DIR__ . '/../../config/database.php';

$runner = new SimpleTestRunner();

// Test 1: Connexion à la base de données
$runner->addTest('Base de Données - Connexion', function() {
    try {
        $db = getDB();
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

// Test 2: Vérification des tables
$runner->addTest('Base de Données - Tables Existent', function() {
    $db = getDB();
    
    $requiredTables = [
        'users',
        'subscriptions', 
        'payments',
        'client_assignments',
        'posts',
        'notifications'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        
        SimpleTestRunner::assertNotNull($result, "La table '$table' doit exister");
    }
    
    return true;
});

// Test 3: Structure de la table users
$runner->addTest('Base de Données - Structure Table Users', function() {
    $db = getDB();
    
    $requiredColumns = [
        'id',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'role',
        'status',
        'created_at'
    ];
    
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
    $db = getDB();
    
    // Vérifier qu'il y a des utilisateurs de test
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    SimpleTestRunner::assertTrue(
        $result['count'] > 0, 
        "Il doit y avoir au moins un utilisateur dans la base de données"
    );
    
    // Vérifier qu'il y a un admin
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    SimpleTestRunner::assertTrue(
        $result['count'] > 0, 
        "Il doit y avoir au moins un administrateur"
    );
    
    return true;
});

// Test 5: Contraintes de clés étrangères
$runner->addTest('Base de Données - Contraintes FK', function() {
    $db = getDB();
    
    // Vérifier les contraintes de clés étrangères
    $stmt = $db->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreignKeys = $stmt->fetchAll();
    
    SimpleTestRunner::assertTrue(
        count($foreignKeys) > 0, 
        "Il doit y avoir des contraintes de clés étrangères"
    );
    
    // Vérifier des contraintes spécifiques
    $fkTables = array_column($foreignKeys, 'TABLE_NAME');
    $expectedFKTables = ['subscriptions', 'payments', 'client_assignments', 'posts'];
    
    foreach ($expectedFKTables as $table) {
        SimpleTestRunner::assertTrue(
            in_array($table, $fkTables), 
            "La table '$table' doit avoir des contraintes de clés étrangères"
        );
    }
    
    return true;
});

// Exécuter les tests
$runner->runAll();
?>
