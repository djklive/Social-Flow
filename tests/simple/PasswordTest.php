<?php
/**
 * Test simple de gestion des mots de passe
 * SocialFlow - Tests Simples
 */

require_once __DIR__ . '/SimpleTestRunner.php';
require_once __DIR__ . '/../../includes/functions.php';

$runner = new SimpleTestRunner();

// Test 1: Hachage des mots de passe
$runner->addTest('Hachage Mot de Passe - Génération', function() {
    $password = 'motdepasse123';
    $hashed = hash_password($password);
    
    // Vérifier que le hachage n'est pas vide
    SimpleTestRunner::assertNotEmpty($hashed, "Le hachage ne doit pas être vide");
    
    // Vérifier que le hachage est différent du mot de passe original
    SimpleTestRunner::assertNotEquals($password, $hashed, "Le hachage doit être différent du mot de passe original");
    
    // Vérifier que le hachage a la bonne longueur (bcrypt = 60 caractères)
    SimpleTestRunner::assertEquals(60, strlen($hashed), "Le hachage doit faire 60 caractères");
    
    // Vérifier que le hachage commence par $2y$ (bcrypt)
    SimpleTestRunner::assertTrue(
        strpos($hashed, '$2y$') === 0, 
        "Le hachage doit utiliser bcrypt"
    );
    
    return true;
});

// Test 2: Vérification des mots de passe
$runner->addTest('Hachage Mot de Passe - Vérification', function() {
    $password = 'testpassword456';
    $hashed = hash_password($password);
    
    // Vérifier que la vérification fonctionne avec le bon mot de passe
    SimpleTestRunner::assertTrue(
        password_verify($password, $hashed), 
        "La vérification doit réussir avec le bon mot de passe"
    );
    
    // Vérifier qu'un mauvais mot de passe est rejeté
    SimpleTestRunner::assertFalse(
        password_verify('wrongpassword', $hashed), 
        "La vérification doit échouer avec un mauvais mot de passe"
    );
    
    // Vérifier qu'un mot de passe vide est rejeté
    SimpleTestRunner::assertFalse(
        password_verify('', $hashed), 
        "La vérification doit échouer avec un mot de passe vide"
    );
    
    return true;
});

// Test 3: Unicité des hachages
$runner->addTest('Hachage Mot de Passe - Unicité', function() {
    $password = 'samepassword';
    
    // Générer plusieurs hachages du même mot de passe
    $hash1 = hash_password($password);
    $hash2 = hash_password($password);
    $hash3 = hash_password($password);
    
    // Les hachages doivent être différents (salt aléatoire)
    SimpleTestRunner::assertNotEquals($hash1, $hash2, "Les hachages doivent être différents");
    SimpleTestRunner::assertNotEquals($hash2, $hash3, "Les hachages doivent être différents");
    SimpleTestRunner::assertNotEquals($hash1, $hash3, "Les hachages doivent être différents");
    
    // Mais tous doivent vérifier le même mot de passe
    SimpleTestRunner::assertTrue(password_verify($password, $hash1));
    SimpleTestRunner::assertTrue(password_verify($password, $hash2));
    SimpleTestRunner::assertTrue(password_verify($password, $hash3));
    
    return true;
});

// Test 4: Différents types de mots de passe
$runner->addTest('Hachage Mot de Passe - Types Variés', function() {
    $passwords = [
        'simple123',
        'Complex@Pass123!',
        '123456789',
        'abcdefgh',
        'Mot de Passe avec Espaces',
        'verylongpasswordwithlotsofcharacters123456789'
    ];
    
    foreach ($passwords as $password) {
        $hashed = hash_password($password);
        
        // Vérifier que le hachage est généré
        SimpleTestRunner::assertNotEmpty($hashed, "Le hachage ne doit pas être vide pour: $password");
        
        // Vérifier que la vérification fonctionne
        SimpleTestRunner::assertTrue(
            password_verify($password, $hashed), 
            "La vérification doit réussir pour: $password"
        );
    }
    
    return true;
});

// Exécuter les tests
$runner->runAll();
?>
