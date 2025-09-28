<?php
/**
 * Tests fonctionnels pour le processus de connexion
 * SocialFlow - Tests Fonctionnels
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

class LoginProcessTest extends PHPUnit\Framework\TestCase {
    private $db;
    private $testUser;
    private $testEmail = 'functional@logintest.com';
    
    protected function setUp(): void {
        $this->db = getDB();
        $this->assertNotNull($this->db, "La connexion à la base de données doit être établie");
        
        // Créer un utilisateur de test
        $this->createTestUser();
    }
    
    protected function tearDown(): void {
        // Nettoyer l'utilisateur de test
        if ($this->testUser) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$this->testUser['id']]);
        }
    }
    
    private function createTestUser() {
        $password = 'testpassword123';
        $hashedPassword = hash_password($password);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified, created_at) 
            VALUES ('Functional', 'Test', ?, ?, 'client', 'active', TRUE, NOW())
        ");
        $stmt->execute([$this->testEmail, $hashedPassword]);
        
        $userId = $this->db->lastInsertId();
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $this->testUser = $stmt->fetch();
        $this->testUser['plain_password'] = $password;
    }
    
    /**
     * Test du processus complet de connexion
     */
    public function testCompleteLoginProcess() {
        // Étape 1: Vérifier que l'utilisateur existe
        $this->assertNotNull($this->testUser, "L'utilisateur de test doit exister");
        $this->assertEquals($this->testEmail, $this->testUser['email']);
        $this->assertEquals('active', $this->testUser['status']);
        
        // Étape 2: Simuler la validation des données de connexion
        $loginData = [
            'email' => $this->testEmail,
            'password' => $this->testUser['plain_password']
        ];
        
        // Validation de l'email
        $this->assertTrue(validate_email($loginData['email']), "L'email doit être valide");
        
        // Étape 3: Vérifier les identifiants
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$loginData['email']]);
        $user = $stmt->fetch();
        
        $this->assertNotFalse($user, "L'utilisateur doit être trouvé");
        $this->assertTrue(password_verify($loginData['password'], $user['password_hash']), "Le mot de passe doit être correct");
        
        // Étape 4: Simuler la création de session
        $sessionData = [
            'user_id' => $user['id'],
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'user_role' => $user['role'],
            'login_time' => time()
        ];
        
        $this->assertIsInt($sessionData['user_id'], "L'ID utilisateur doit être un entier");
        $this->assertNotEmpty($sessionData['user_name'], "Le nom utilisateur ne doit pas être vide");
        $this->assertContains($sessionData['user_role'], ['client', 'community_manager', 'admin'], "Le rôle doit être valide");
        
        // Étape 5: Mettre à jour last_login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $result = $stmt->execute([$user['id']]);
        
        $this->assertTrue($result, "La mise à jour de last_login doit réussir");
        
        // Étape 6: Vérifier la mise à jour
        $stmt = $this->db->prepare("SELECT last_login FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $lastLogin = $stmt->fetchColumn();
        
        $this->assertNotNull($lastLogin, "last_login doit être mis à jour");
        
        // Étape 7: Logger l'activité de connexion
        $logResult = log_activity($user['id'], 'user_login', 'Connexion utilisateur');
        $this->assertTrue($logResult, "Le log d'activité doit être créé");
        
        // Vérifier que le log existe
        $stmt = $this->db->prepare("SELECT * FROM activity_logs WHERE user_id = ? AND action = 'user_login' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $log = $stmt->fetch();
        
        $this->assertNotFalse($log, "Le log de connexion doit exister");
        $this->assertEquals('user_login', $log['action']);
        $this->assertEquals('Connexion utilisateur', $log['details']);
    }
    
    /**
     * Test de connexion avec email invalide
     */
    public function testLoginWithInvalidEmail() {
        $invalidEmails = [
            'invalid@nonexistent.com',
            'wrong@email.com',
            'invalid-email',
            ''
        ];
        
        foreach ($invalidEmails as $email) {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            $this->assertFalse($user, "Aucun utilisateur ne doit être trouvé pour l'email: $email");
        }
    }
    
    /**
     * Test de connexion avec mot de passe incorrect
     */
    public function testLoginWithWrongPassword() {
        $wrongPasswords = [
            'wrongpassword',
            'testpassword',
            '123456',
            '',
            $this->testUser['plain_password'] . 'extra'
        ];
        
        foreach ($wrongPasswords as $password) {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$this->testEmail]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->assertFalse(
                    password_verify($password, $user['password_hash']), 
                    "Le mot de passe '$password' ne doit pas être accepté"
                );
            }
        }
    }
    
    /**
     * Test de connexion avec utilisateur inactif
     */
    public function testLoginWithInactiveUser() {
        // Créer un utilisateur inactif
        $inactiveEmail = 'inactive@test.com';
        $hashedPassword = hash_password('password123');
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Inactive', 'User', ?, ?, 'client', 'inactive', NOW())
        ");
        $stmt->execute([$inactiveEmail, $hashedPassword]);
        $inactiveUserId = $this->db->lastInsertId();
        
        // Essayer de se connecter
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$inactiveEmail]);
        $user = $stmt->fetch();
        
        $this->assertFalse($user, "L'utilisateur inactif ne doit pas être trouvé");
        
        // Nettoyer
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$inactiveUserId]);
    }
    
    /**
     * Test de redirection après connexion selon le rôle
     */
    public function testPostLoginRedirectByRole() {
        $roles = [
            'client' => '/client/dashboard.php',
            'community_manager' => '/cm/dashboard.php',
            'admin' => '/admin/dashboard.php'
        ];
        
        foreach ($roles as $role => $expectedRedirect) {
            // Créer un utilisateur avec ce rôle
            $email = "test_$role@redirect.com";
            $hashedPassword = hash_password('password123');
            
            $stmt = $this->db->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
                VALUES ('Test', 'User', ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$email, $hashedPassword, $role]);
            $userId = $this->db->lastInsertId();
            
            // Simuler la détermination de la redirection
            $redirectUrl = $this->getRedirectUrlByRole($role);
            $this->assertEquals($expectedRedirect, $redirectUrl, "La redirection doit correspondre au rôle: $role");
            
            // Nettoyer
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        }
    }
    
    private function getRedirectUrlByRole($role) {
        switch ($role) {
            case 'client':
                return '/client/dashboard.php';
            case 'community_manager':
                return '/cm/dashboard.php';
            case 'admin':
                return '/admin/dashboard.php';
            default:
                return '/index.php';
        }
    }
    
    /**
     * Test de gestion des tentatives de connexion multiples
     */
    public function testMultipleLoginAttempts() {
        $loginAttempts = [];
        $maxAttempts = 3;
        
        // Simuler plusieurs tentatives de connexion
        for ($i = 1; $i <= $maxAttempts + 1; $i++) {
            $attempt = [
                'email' => $this->testEmail,
                'password' => 'wrongpassword',
                'timestamp' => time(),
                'ip' => '127.0.0.1'
            ];
            
            $loginAttempts[] = $attempt;
            
            // Vérifier les identifiants
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$attempt['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $passwordValid = password_verify($attempt['password'], $user['password_hash']);
                
                if (!$passwordValid && $i <= $maxAttempts) {
                    // Log de tentative échouée
                    log_activity($user['id'], 'failed_login_attempt', "Tentative de connexion échouée #$i");
                } else if (!$passwordValid && $i > $maxAttempts) {
                    // Compte temporairement bloqué
                    log_activity($user['id'], 'account_locked', 'Compte temporairement bloqué après trop de tentatives');
                }
            }
        }
        
        $this->assertCount($maxAttempts + 1, $loginAttempts, "Toutes les tentatives doivent être enregistrées");
        
        // Vérifier que les logs d'activité ont été créés
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM activity_logs 
            WHERE user_id = ? AND action IN ('failed_login_attempt', 'account_locked')
        ");
        $stmt->execute([$this->testUser['id']]);
        $logCount = $stmt->fetchColumn();
        
        $this->assertGreaterThan(0, $logCount, "Des logs d'activité doivent être créés");
    }
}
?>
