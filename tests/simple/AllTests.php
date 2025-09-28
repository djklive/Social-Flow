<?php
/**
 * Suite complète de tests simples pour SocialFlow
 * Version sans dépendance base de données
 */

require_once __DIR__ . '/SimpleTestRunner.php';
require_once __DIR__ . '/../../includes/functions.php';

$runner = new SimpleTestRunner();

echo "=== SocialFlow - Suite Complète de Tests ===\n";
echo "Tests sans dépendance base de données\n\n";

// === TESTS UNITAIRES ===

// Test 1: Validation des emails
$runner->addTest('Validation Email - Emails Valides', function() {
    $validEmails = [
        'test@example.com',
        'user.name@domain.co.uk',
        'admin@socialflow.com',
        'user+tag@example.org'
    ];
    
    foreach ($validEmails as $email) {
        SimpleTestRunner::assertTrue(
            validate_email($email), 
            "Email valide rejeté: $email"
        );
    }
    return true;
});

$runner->addTest('Validation Email - Emails Invalides', function() {
    $invalidEmails = [
        'invalid-email',
        '@domain.com',
        'user@',
        'user..name@domain.com',
        'user@domain',
        'user name@domain.com'
    ];
    
    foreach ($invalidEmails as $email) {
        SimpleTestRunner::assertFalse(
            validate_email($email), 
            "Email invalide accepté: $email"
        );
    }
    return true;
});

$runner->addTest('Validation Email - Cas Limites', function() {
    // Email avec caractères spéciaux
    SimpleTestRunner::assertTrue(
        validate_email('user+test@example-domain.com'),
        "Email avec caractères spéciaux rejeté"
    );
    
    // Email avec chiffres
    SimpleTestRunner::assertTrue(
        validate_email('user123@domain456.com'),
        "Email avec chiffres rejeté"
    );
    
    return true;
});

// Test 2: Gestion des mots de passe
$runner->addTest('Hachage Mot de Passe - Génération', function() {
    $password = 'motdepasse123';
    $hashed = hash_password($password);
    
    SimpleTestRunner::assertNotEmpty($hashed, "Le hachage ne doit pas être vide");
    SimpleTestRunner::assertNotEquals($password, $hashed, "Le hachage doit être différent du mot de passe original");
    SimpleTestRunner::assertEquals(60, strlen($hashed), "Le hachage doit faire 60 caractères");
    SimpleTestRunner::assertTrue(
        strpos($hashed, '$2y$') === 0, 
        "Le hachage doit utiliser bcrypt"
    );
    
    return true;
});

$runner->addTest('Hachage Mot de Passe - Vérification', function() {
    $password = 'testpassword456';
    $hashed = hash_password($password);
    
    SimpleTestRunner::assertTrue(
        password_verify($password, $hashed), 
        "La vérification doit réussir avec le bon mot de passe"
    );
    
    SimpleTestRunner::assertFalse(
        password_verify('wrongpassword', $hashed), 
        "La vérification doit échouer avec un mauvais mot de passe"
    );
    
    return true;
});

$runner->addTest('Hachage Mot de Passe - Unicité', function() {
    $password = 'samepassword';
    
    $hash1 = hash_password($password);
    $hash2 = hash_password($password);
    $hash3 = hash_password($password);
    
    SimpleTestRunner::assertNotEquals($hash1, $hash2, "Les hachages doivent être différents");
    SimpleTestRunner::assertNotEquals($hash2, $hash3, "Les hachages doivent être différents");
    SimpleTestRunner::assertNotEquals($hash1, $hash3, "Les hachages doivent être différents");
    
    SimpleTestRunner::assertTrue(password_verify($password, $hash1));
    SimpleTestRunner::assertTrue(password_verify($password, $hash2));
    SimpleTestRunner::assertTrue(password_verify($password, $hash3));
    
    return true;
});

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
        SimpleTestRunner::assertNotEmpty($hashed, "Le hachage ne doit pas être vide pour: $password");
        SimpleTestRunner::assertTrue(
            password_verify($password, $hashed), 
            "La vérification doit réussir pour: $password"
        );
    }
    
    return true;
});

// === TESTS DE FONCTIONS UTILITAIRES ===

$runner->addTest('Fonctions Utilitaires - Sanitize Input', function() {
    $input = "  <script>alert('test')</script>  ";
    $sanitized = sanitize_input($input);
    
    SimpleTestRunner::assertNotEquals($input, $sanitized, "L'entrée doit être nettoyée");
    SimpleTestRunner::assertFalse(strpos($sanitized, '<script>') !== false, "Les balises script doivent être supprimées");
    
    return true;
});

$runner->addTest('Fonctions Utilitaires - Validation Téléphone', function() {
    $validPhones = [
        '+237123456789',
        '0123456789',
        '+33123456789'
    ];
    
    $invalidPhones = [
        '123',
        'abc',
        '+',
        ''
    ];
    
    foreach ($validPhones as $phone) {
        SimpleTestRunner::assertTrue(
            validate_phone($phone), 
            "Téléphone valide rejeté: $phone"
        );
    }
    
    foreach ($invalidPhones as $phone) {
        SimpleTestRunner::assertFalse(
            validate_phone($phone), 
            "Téléphone invalide accepté: $phone"
        );
    }
    
    return true;
});

$runner->addTest('Fonctions Utilitaires - Génération Mot de Passe', function() {
    $password = generate_password(12);
    
    SimpleTestRunner::assertEquals(12, strlen($password), "Le mot de passe doit faire 12 caractères");
    SimpleTestRunner::assertTrue(
        preg_match('/[a-zA-Z0-9!@#$%^&*]/', $password), 
        "Le mot de passe doit contenir des caractères valides"
    );
    
    return true;
});

$runner->addTest('Fonctions Utilitaires - Format Date', function() {
    $date = '2024-01-15 14:30:00';
    $formatted = format_date_fr($date, 'd/m/Y H:i');
    
    SimpleTestRunner::assertEquals('15/01/2024 14:30', $formatted, "La date doit être formatée correctement");
    
    return true;
});

$runner->addTest('Fonctions Utilitaires - Time Ago', function() {
    $recent = date('Y-m-d H:i:s', time() - 30);
    $timeAgo = time_ago($recent);
    
    SimpleTestRunner::assertNotEmpty($timeAgo, "Le temps écoulé ne doit pas être vide");
    SimpleTestRunner::assertTrue(
        strpos($timeAgo, 'min') !== false || strpos($timeAgo, 'instant') !== false, 
        "Le temps écoulé doit être formaté correctement"
    );
    
    return true;
});

// === TESTS DE SÉCURITÉ ===

$runner->addTest('Sécurité - Génération Token CSRF', function() {
    session_start();
    $token1 = generate_csrf_token();
    $token2 = generate_csrf_token();
    
    SimpleTestRunner::assertEquals($token1, $token2, "Le token CSRF doit être persistant");
    SimpleTestRunner::assertEquals(64, strlen($token1), "Le token CSRF doit faire 64 caractères");
    
    return true;
});

$runner->addTest('Sécurité - Vérification Token CSRF', function() {
    session_start();
    $token = generate_csrf_token();
    
    SimpleTestRunner::assertTrue(
        verify_csrf_token($token), 
        "La vérification du token CSRF doit réussir"
    );
    
    SimpleTestRunner::assertFalse(
        verify_csrf_token('invalid_token'), 
        "La vérification d'un token invalide doit échouer"
    );
    
    return true;
});

// Exécuter tous les tests
$runner->runAll();

echo "\n=== RÉSUMÉ FINAL ===\n";
echo "✅ Tests unitaires: Fonctions de base testées\n";
echo "✅ Tests de sécurité: Validation et hachage testés\n";
echo "✅ Tests utilitaires: Fonctions helper testées\n";
echo "⚠️ Tests base de données: Non exécutés (MySQL non accessible)\n";
echo "\nPour exécuter les tests de base de données:\n";
echo "1. Démarrer MySQL/XAMPP\n";
echo "2. Exécuter: php tests/simple/SimpleDatabaseTest.php\n";
?>
