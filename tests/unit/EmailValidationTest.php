<?php
/**
 * Tests unitaires pour la validation des emails
 * SocialFlow - Tests Unitaires
 */

require_once __DIR__ . '/../../includes/functions.php';

class EmailValidationTest extends PHPUnit\Framework\TestCase {
    
    /**
     * Test des emails valides
     */
    public function testValidEmail() {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'admin@socialflow.com',
            'user+tag@example.org',
            'test123@domain-name.com',
            'a@b.co',
            'user@subdomain.example.com'
        ];
        
        foreach ($validEmails as $email) {
            $this->assertTrue(
                validate_email($email), 
                "Email valide rejeté: $email"
            );
        }
    }
    
    /**
     * Test des emails invalides
     */
    public function testInvalidEmail() {
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com',
            'user@domain..com',
            '',
            'user@.domain.com',
            'user@domain.com.',
            'user@@domain.com'
        ];
        
        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                validate_email($email), 
                "Email invalide accepté: $email"
            );
        }
    }
    
    /**
     * Test des cas limites
     */
    public function testEdgeCases() {
        // Email très long mais valide
        $longEmail = str_repeat('a', 50) . '@' . str_repeat('b', 50) . '.com';
        $this->assertTrue(validate_email($longEmail), "Email long valide rejeté");
        
        // Email avec caractères spéciaux
        $specialEmail = 'user+test@example-domain.com';
        $this->assertTrue(validate_email($specialEmail), "Email avec caractères spéciaux rejeté");
        
        // Email avec chiffres
        $numericEmail = 'user123@domain456.com';
        $this->assertTrue(validate_email($numericEmail), "Email avec chiffres rejeté");
    }
}
?>
