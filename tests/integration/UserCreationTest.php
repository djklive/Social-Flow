<?php
/**
 * Tests d'intégration pour la création d'utilisateurs
 * SocialFlow - Tests d'Intégration
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

class UserCreationTest extends PHPUnit\Framework\TestCase {
    private $db;
    private $testEmails = [];
    
    protected function setUp(): void {
        $this->db = getDB();
        
        // Vérifier la connexion à la base de données
        $this->assertNotNull($this->db, "La connexion à la base de données doit être établie");
    }
    
    protected function tearDown(): void {
        // Nettoyer tous les utilisateurs de test créés
        foreach ($this->testEmails as $email) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email]);
        }
        $this->testEmails = [];
    }
    
    /**
     * Test de création d'utilisateur complet
     */
    public function testCreateUserIntegration() {
        // Données de test
        $userData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@integration.com',
            'password' => 'password123',
            'role' => 'client',
            'phone' => '+237123456789'
        ];
        
        $this->testEmails[] = $userData['email'];
        
        // Créer l'utilisateur
        $hashedPassword = hash_password($userData['password']);
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $result = $stmt->execute([
            $userData['first_name'],
            $userData['last_name'],
            $userData['email'],
            $userData['phone'],
            $hashedPassword,
            $userData['role']
        ]);
        
        // Vérifier que l'insertion a réussi
        $this->assertTrue($result, "L'insertion de l'utilisateur doit réussir");
        
        $userId = $this->db->lastInsertId();
        $this->assertGreaterThan(0, $userId, "L'ID de l'utilisateur doit être positif");
        
        // Vérifier que l'utilisateur existe en base
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $this->assertNotFalse($user, "L'utilisateur doit être trouvé en base");
        $this->assertEquals($userData['first_name'], $user['first_name']);
        $this->assertEquals($userData['last_name'], $user['last_name']);
        $this->assertEquals($userData['email'], $user['email']);
        $this->assertEquals($userData['phone'], $user['phone']);
        $this->assertEquals($userData['role'], $user['role']);
        $this->assertEquals('active', $user['status']);
        $this->assertNotNull($user['created_at']);
        
        // Vérifier que le mot de passe est correctement haché
        $this->assertTrue(password_verify($userData['password'], $user['password_hash']));
    }
    
    /**
     * Test de connexion utilisateur
     */
    public function testUserLoginIntegration() {
        // Créer un utilisateur de test
        $email = 'login@integration.com';
        $password = 'loginpass123';
        $hashedPassword = hash_password($password);
        
        $this->testEmails[] = $email;
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Login', 'Test', ?, ?, 'client', 'active', NOW())
        ");
        $stmt->execute([$email, $hashedPassword]);
        $userId = $this->db->lastInsertId();
        
        // Tester la connexion
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        $this->assertNotFalse($user, "L'utilisateur doit être trouvé");
        $this->assertEquals($userId, $user['id']);
        $this->assertTrue(password_verify($password, $user['password_hash']), "Le mot de passe doit être vérifié");
        
        // Mettre à jour la dernière connexion
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        $this->assertTrue($result, "La mise à jour de last_login doit réussir");
        
        // Vérifier que last_login a été mis à jour
        $stmt = $this->db->prepare("SELECT last_login FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $lastLogin = $stmt->fetchColumn();
        
        $this->assertNotNull($lastLogin, "last_login ne doit pas être null");
    }
    
    /**
     * Test de création d'utilisateur avec email dupliqué
     */
    public function testDuplicateEmailPrevention() {
        $email = 'duplicate@test.com';
        $this->testEmails[] = $email;
        
        // Créer le premier utilisateur
        $hashedPassword = hash_password('password123');
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('First', 'User', ?, ?, 'client', 'active', NOW())
        ");
        $result1 = $stmt->execute([$email, $hashedPassword]);
        $this->assertTrue($result1, "Le premier utilisateur doit être créé");
        
        // Essayer de créer un deuxième utilisateur avec le même email
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Second', 'User', ?, ?, 'client', 'active', NOW())
        ");
        
        // Cette requête doit échouer à cause de la contrainte UNIQUE sur email
        $this->expectException(PDOException::class);
        $stmt->execute([$email, $hashedPassword]);
    }
    
    /**
     * Test de validation des rôles
     */
    public function testRoleValidation() {
        $validRoles = ['client', 'community_manager', 'admin'];
        $email = 'role@test.com';
        $this->testEmails[] = $email;
        
        foreach ($validRoles as $role) {
            $hashedPassword = hash_password('password123');
            $stmt = $this->db->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
                VALUES ('Role', 'Test', ?, ?, ?, 'active', NOW())
            ");
            
            $result = $stmt->execute([$email . $role, $hashedPassword, $role]);
            $this->assertTrue($result, "Le rôle '$role' doit être accepté");
            
            // Vérifier que le rôle est correctement stocké
            $userId = $this->db->lastInsertId();
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $storedRole = $stmt->fetchColumn();
            
            $this->assertEquals($role, $storedRole, "Le rôle '$role' doit être correctement stocké");
        }
    }
}
?>
