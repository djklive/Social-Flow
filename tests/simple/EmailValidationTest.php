<?php
/**
 * Test simple de validation des emails
 * SocialFlow - Tests Simples
 */

require_once __DIR__ . '/SimpleTestRunner.php';
require_once __DIR__ . '/../../includes/functions.php';

$runner = new SimpleTestRunner();

// Test 1: Emails valides
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

// Test 2: Emails invalides
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

// Test 3: Cas limites
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

// Exécuter les tests
$runner->runAll();
?>
