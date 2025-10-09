<?php
/**
 * Tests d'intégration pour la création de publications
 * SocialFlow - Tests d'Intégration
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

class PostCreationTest extends PHPUnit\Framework\TestCase {
    private $db;
    private $clientId;
    private $cmId;
    private $assignmentId;
    private $testEmails = [];
    
    protected function setUp(): void {
        $this->db = getDB();
        $this->assertNotNull($this->db, "La connexion à la base de données doit être établie");
        
        // Créer un client de test
        $clientEmail = 'client@posttest.com';
        $this->testEmails[] = $clientEmail;
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Client', 'PostTest', ?, 'hash', 'client', 'active', NOW())
        ");
        $stmt->execute([$clientEmail]);
        $this->clientId = $this->db->lastInsertId();
        
        // Créer un CM de test
        $cmEmail = 'cm@posttest.com';
        $this->testEmails[] = $cmEmail;
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('CM', 'PostTest', ?, 'hash', 'community_manager', 'active', NOW())
        ");
        $stmt->execute([$cmEmail]);
        $this->cmId = $this->db->lastInsertId();
        
        // Créer une assignation
        $stmt = $this->db->prepare("
            INSERT INTO client_assignments (client_id, community_manager_id, assigned_at, status) 
            VALUES (?, ?, NOW(), 'active')
        ");
        $stmt->execute([$this->clientId, $this->cmId]);
        $this->assignmentId = $this->db->lastInsertId();
    }
    
    protected function tearDown(): void {
        // Nettoyer dans l'ordre inverse des dépendances
        $this->db->exec("DELETE FROM posts WHERE client_id = $this->clientId");
        $this->db->exec("DELETE FROM client_assignments WHERE id = $this->assignmentId");
        $this->db->exec("DELETE FROM users WHERE id IN ($this->clientId, $this->cmId)");
        
        // Nettoyer les emails de test
        foreach ($this->testEmails as $email) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email]);
        }
    }
    
    /**
     * Test de création de publication avec assignation
     */
    public function testCreatePostWithAssignment() {
        $postData = [
            'title' => 'Test Post Integration',
            'content' => 'Contenu de test pour l\'intégration',
            'platforms' => ['facebook', 'instagram', 'twitter'],
            'status' => 'draft'
        ];
        
        // Créer la publication
        $platformsJson = json_encode($postData['platforms']);
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $postData['title'],
            $postData['content'],
            $this->clientId,
            $this->cmId,
            $platformsJson,
            $postData['status']
        ]);
        
        $this->assertTrue($result, "La création de publication doit réussir");
        $postId = $this->db->lastInsertId();
        $this->assertGreaterThan(0, $postId, "L'ID de publication doit être positif");
        
        // Vérifier que la publication existe
        $stmt = $this->db->prepare("
            SELECT p.*, u.first_name as client_name, cm.first_name as cm_name
            FROM posts p
            INNER JOIN users u ON p.client_id = u.id
            INNER JOIN users cm ON p.community_manager_id = cm.id
            WHERE p.id = ?
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        $this->assertNotFalse($post, "La publication doit être trouvée");
        $this->assertEquals($postData['title'], $post['title']);
        $this->assertEquals($postData['content'], $post['content']);
        $this->assertEquals($postData['status'], $post['status']);
        $this->assertEquals($this->clientId, $post['client_id']);
        $this->assertEquals($this->cmId, $post['community_manager_id']);
        
        // Vérifier les plateformes
        $platforms = json_decode($post['platforms'], true);
        $this->assertEquals($postData['platforms'], $platforms);
        
        // Vérifier les noms des utilisateurs
        $this->assertEquals('Client', $post['client_name']);
        $this->assertEquals('CM', $post['cm_name']);
    }
    
    /**
     * Test de mise à jour du statut de publication
     */
    public function testPostStatusUpdate() {
        // Créer une publication
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES ('Test Status Update', 'Content for status test', ?, ?, '[]', 'draft', NOW())
        ");
        $stmt->execute([$this->clientId, $this->cmId]);
        $postId = $this->db->lastInsertId();
        
        // Mettre à jour le statut
        $stmt = $this->db->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$postId]);
        
        $this->assertTrue($result, "La mise à jour du statut doit réussir");
        
        // Vérifier la mise à jour
        $stmt = $this->db->prepare("SELECT status, published_at FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        $this->assertEquals('published', $post['status'], "Le statut doit être 'published'");
        $this->assertNotNull($post['published_at'], "published_at ne doit pas être null");
    }
    
    /**
     * Test de programmation de publication
     */
    public function testPostScheduling() {
        $scheduledDate = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, scheduled_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            'Publication programmée',
            'Contenu programmé pour demain',
            $this->clientId,
            $this->cmId,
            json_encode(['facebook']),
            'scheduled',
            $scheduledDate
        ]);
        
        $this->assertTrue($result, "La création de publication programmée doit réussir");
        $postId = $this->db->lastInsertId();
        
        // Vérifier la programmation
        $stmt = $this->db->prepare("SELECT status, scheduled_at FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        $this->assertEquals('scheduled', $post['status'], "Le statut doit être 'scheduled'");
        $this->assertEquals($scheduledDate, $post['scheduled_at'], "La date programmée doit correspondre");
    }
    
    /**
     * Test de création de publication sans assignation
     */
    public function testCreatePostWithoutAssignment() {
        // Créer un autre client sans assignation
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Unassigned', 'Client', 'unassigned@test.com', 'hash', 'client', 'active', NOW())
        ");
        $stmt->execute();
        $unassignedClientId = $this->db->lastInsertId();
        
        // Essayer de créer une publication avec un client non assigné
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Cette requête peut réussir car il n'y a pas de contrainte de clé étrangère
        // entre posts et client_assignments, mais c'est logiquement incorrect
        $result = $stmt->execute([
            'Post sans assignation',
            'Contenu',
            $unassignedClientId,
            $this->cmId,
            json_encode(['facebook']),
            'draft'
        ]);
        
        $this->assertTrue($result, "La publication peut être créée même sans assignation");
        
        // Nettoyer
        $this->db->exec("DELETE FROM posts WHERE client_id = $unassignedClientId");
        $this->db->exec("DELETE FROM users WHERE id = $unassignedClientId");
    }
    
    /**
     * Test de récupération des publications par client
     */
    public function testGetPostsByClient() {
        // Créer plusieurs publications pour le même client
        $posts = [
            ['title' => 'Post 1', 'content' => 'Content 1', 'status' => 'published'],
            ['title' => 'Post 2', 'content' => 'Content 2', 'status' => 'draft'],
            ['title' => 'Post 3', 'content' => 'Content 3', 'status' => 'scheduled']
        ];
        
        $postIds = [];
        foreach ($posts as $postData) {
            $stmt = $this->db->prepare("
                INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
                VALUES (?, ?, ?, ?, '[]', ?, NOW())
            ");
            $stmt->execute([$postData['title'], $postData['content'], $this->clientId, $this->cmId, $postData['status']]);
            $postIds[] = $this->db->lastInsertId();
        }
        
        // Récupérer toutes les publications du client
        $stmt = $this->db->prepare("
            SELECT * FROM posts 
            WHERE client_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->clientId]);
        $clientPosts = $stmt->fetchAll();
        
        $this->assertCount(3, $clientPosts, "Le client doit avoir 3 publications");
        
        // Vérifier que toutes les publications sont bien associées au bon client
        foreach ($clientPosts as $post) {
            $this->assertEquals($this->clientId, $post['client_id'], "La publication doit être associée au bon client");
        }
        
        // Nettoyer
        foreach ($postIds as $postId) {
            $this->db->exec("DELETE FROM posts WHERE id = $postId");
        }
    }
}
?>
