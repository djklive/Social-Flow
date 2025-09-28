<?php
/**
 * Tests unitaires pour la gestion des mots de passe
 * SocialFlow - Tests Unitaires
 */

require_once __DIR__ . '/../../includes/functions.php';

class PasswordTest extends PHPUnit\Framework\TestCase {
    
    /**
     * Test du hachage des mots de passe
     */
    public function testPasswordHashing() {
        $password = 'motdepasse123';
        $hashed = hash_password($password);
        
        // Vérifier que le hachage n'est pas vide
        $this->assertNotEmpty($hashed, "Le hachage ne doit pas être vide");
        
        // Vérifier que le hachage est différent du mot de passe original
        $this->assertNotEquals($password, $hashed, "Le hachage doit être différent du mot de passe original");
        
        // Vérifier que le hachage a la bonne longueur (bcrypt = 60 caractères)
        $this->assertEquals(60, strlen($hashed), "Le hachage doit faire 60 caractères");
        
        // Vérifier que le hachage commence par $2y$ (bcrypt)
        $this->assertStringStartsWith('$2y$', $hashed, "Le hachage doit utiliser bcrypt");
    }
    
    /**
     * Test de la vérification des mots de passe
     */
    public function testPasswordVerification() {
        $password = 'testpassword456';
        $hashed = hash_password($password);
        
        // Vérifier que la vérification fonctionne avec le bon mot de passe
        $this->assertTrue(
            password_verify($password, $hashed), 
            "La vérification doit réussir avec le bon mot de passe"
        );
        
        // Vérifier qu'un mauvais mot de passe est rejeté
        $this->assertFalse(
            password_verify('wrongpassword', $hashed), 
            "La vérification doit échouer avec un mauvais mot de passe"
        );
        
        // Vérifier qu'un mot de passe vide est rejeté
        $this->assertFalse(
            password_verify('', $hashed), 
            "La vérification doit échouer avec un mot de passe vide"
        );
    }
    
    /**
     * Test de l'unicité des hachages
     */
    public function testPasswordHashUniqueness() {
        $password = 'samepassword';
        
        // Générer plusieurs hachages du même mot de passe
        $hash1 = hash_password($password);
        $hash2 = hash_password($password);
        $hash3 = hash_password($password);
        
        // Les hachages doivent être différents (salt aléatoire)
        $this->assertNotEquals($hash1, $hash2, "Les hachages doivent être différents");
        $this->assertNotEquals($hash2, $hash3, "Les hachages doivent être différents");
        $this->assertNotEquals($hash1, $hash3, "Les hachages doivent être différents");
        
        // Mais tous doivent vérifier le même mot de passe
        $this->assertTrue(password_verify($password, $hash1));
        $this->assertTrue(password_verify($password, $hash2));
        $this->assertTrue(password_verify($password, $hash3));
    }
    
    /**
     * Test avec différents types de mots de passe
     */
    public function testDifferentPasswordTypes() {
        $passwords = [
            'simple123',
            'Complex@Pass123!',
            '123456789',
            'abcdefgh',
            'Mot de Passe avec Espaces',
            'émojis🔐test',
            'verylongpasswordwithlotsofcharacters123456789'
        ];
        
        foreach ($passwords as $password) {
            $hashed = hash_password($password);
            
            // Vérifier que le hachage est généré
            $this->assertNotEmpty($hashed, "Le hachage ne doit pas être vide pour: $password");
            
            // Vérifier que la vérification fonctionne
            $this->assertTrue(
                password_verify($password, $hashed), 
                "La vérification doit réussir pour: $password"
            );
        }
    }
}
?>
