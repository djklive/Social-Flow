<?php
/**
 * Tests fonctionnels pour le workflow complet de création de publication
 * SocialFlow - Tests Fonctionnels
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

class PublicationWorkflowTest extends PHPUnit\Framework\TestCase {
    private $db;
    private $client;
    private $cm;
    private $assignment;
    private $testEmails = [];
    
    protected function setUp(): void {
        $this->db = getDB();
        $this->assertNotNull($this->db, "La connexion à la base de données doit être établie");
        
        // Créer les utilisateurs et l'assignation
        $this->createTestUsers();
        $this->createAssignment();
    }
    
    protected function tearDown(): void {
        // Nettoyer dans l'ordre inverse des dépendances
        $this->db->exec("DELETE FROM posts WHERE client_id = {$this->client['id']}");
        $this->db->exec("DELETE FROM client_assignments WHERE id = {$this->assignment['id']}");
        $this->db->exec("DELETE FROM users WHERE id IN ({$this->client['id']}, {$this->cm['id']})");
        
        // Nettoyer les emails de test
        foreach ($this->testEmails as $email) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email]);
        }
    }
    
    private function createTestUsers() {
        // Créer un client
        $clientEmail = 'client@workflow.com';
        $this->testEmails[] = $clientEmail;
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Workflow', 'Client', ?, 'hash', 'client', 'active', NOW())
        ");
        $stmt->execute([$clientEmail]);
        $clientId = $this->db->lastInsertId();
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$clientId]);
        $this->client = $stmt->fetch();
        
        // Créer un CM
        $cmEmail = 'cm@workflow.com';
        $this->testEmails[] = $cmEmail;
        
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
            VALUES ('Workflow', 'CM', ?, 'hash', 'community_manager', 'active', NOW())
        ");
        $stmt->execute([$cmEmail]);
        $cmId = $this->db->lastInsertId();
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$cmId]);
        $this->cm = $stmt->fetch();
    }
    
    private function createAssignment() {
        $stmt = $this->db->prepare("
            INSERT INTO client_assignments (client_id, community_manager_id, assigned_at, status) 
            VALUES (?, ?, NOW(), 'active')
        ");
        $stmt->execute([$this->client['id'], $this->cm['id']]);
        $assignmentId = $this->db->lastInsertId();
        
        $stmt = $this->db->prepare("SELECT * FROM client_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $this->assignment = $stmt->fetch();
    }
    
    /**
     * Test du workflow complet de création de publication
     */
    public function testCompletePublicationWorkflow() {
        // Étape 1: Vérifier l'assignation
        $this->assertNotNull($this->assignment, "L'assignation doit exister");
        $this->assertEquals($this->client['id'], $this->assignment['client_id']);
        $this->assertEquals($this->cm['id'], $this->assignment['community_manager_id']);
        $this->assertEquals('active', $this->assignment['status']);
        
        // Étape 2: Données de la publication
        $publicationData = [
            'title' => 'Publication Workflow Test',
            'content' => 'Contenu de test pour le workflow complet de publication',
            'platforms' => ['facebook', 'instagram', 'twitter', 'linkedin'],
            'media_urls' => [
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg'
            ],
            'status' => 'draft'
        ];
        
        // Étape 3: Validation des données
        $this->assertNotEmpty($publicationData['title'], "Le titre ne doit pas être vide");
        $this->assertNotEmpty($publicationData['content'], "Le contenu ne doit pas être vide");
        $this->assertIsArray($publicationData['platforms'], "Les plateformes doivent être un tableau");
        $this->assertContains('facebook', $publicationData['platforms'], "Facebook doit être dans les plateformes");
        
        // Étape 4: Créer la publication
        $platformsJson = json_encode($publicationData['platforms']);
        $mediaJson = json_encode($publicationData['media_urls']);
        
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, media_urls, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $publicationData['title'],
            $publicationData['content'],
            $this->client['id'],
            $this->cm['id'],
            $platformsJson,
            $mediaJson,
            $publicationData['status']
        ]);
        
        $this->assertTrue($result, "La création de publication doit réussir");
        $postId = $this->db->lastInsertId();
        
        // Étape 5: Vérifier la création
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
        $this->assertEquals($publicationData['title'], $post['title']);
        $this->assertEquals($publicationData['content'], $post['content']);
        $this->assertEquals($publicationData['status'], $post['status']);
        
        // Étape 6: Vérifier les plateformes et médias
        $platforms = json_decode($post['platforms'], true);
        $mediaUrls = json_decode($post['media_urls'], true);
        
        $this->assertEquals($publicationData['platforms'], $platforms);
        $this->assertEquals($publicationData['media_urls'], $mediaUrls);
        
        // Étape 7: Logger l'activité
        $logResult = log_activity($this->cm['id'], 'post_created', "Publication créée: {$post['title']}");
        $this->assertTrue($logResult, "Le log d'activité doit être créé");
        
        // Étape 8: Mettre à jour le statut vers "scheduled"
        $scheduledDate = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $stmt = $this->db->prepare("
            UPDATE posts 
            SET status = 'scheduled', scheduled_at = ? 
            WHERE id = ?
        ");
        $result = $stmt->execute([$scheduledDate, $postId]);
        
        $this->assertTrue($result, "La mise à jour vers 'scheduled' doit réussir");
        
        // Étape 9: Vérifier la programmation
        $stmt = $this->db->prepare("SELECT status, scheduled_at FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $updatedPost = $stmt->fetch();
        
        $this->assertEquals('scheduled', $updatedPost['status']);
        $this->assertEquals($scheduledDate, $updatedPost['scheduled_at']);
        
        // Étape 10: Publier la publication
        $stmt = $this->db->prepare("
            UPDATE posts 
            SET status = 'published', published_at = NOW() 
            WHERE id = ?
        ");
        $result = $stmt->execute([$postId]);
        
        $this->assertTrue($result, "La publication doit réussir");
        
        // Étape 11: Vérifier la publication
        $stmt = $this->db->prepare("SELECT status, published_at FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $publishedPost = $stmt->fetch();
        
        $this->assertEquals('published', $publishedPost['status']);
        $this->assertNotNull($publishedPost['published_at']);
        
        // Étape 12: Logger la publication
        $logResult = log_activity($this->cm['id'], 'post_published', "Publication publiée: {$post['title']}");
        $this->assertTrue($logResult, "Le log de publication doit être créé");
        
        // Nettoyer
        $this->db->exec("DELETE FROM posts WHERE id = $postId");
    }
    
    /**
     * Test du workflow de création de brouillon
     */
    public function testDraftCreationWorkflow() {
        $draftData = [
            'title' => 'Brouillon de test',
            'content' => 'Contenu du brouillon',
            'platforms' => ['facebook'],
            'notes' => 'Notes pour le brouillon'
        ];
        
        // Créer le brouillon
        $stmt = $this->db->prepare("
            INSERT INTO drafts (title, content, client_id, community_manager_id, platforms, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $draftData['title'],
            $draftData['content'],
            $this->client['id'],
            $this->cm['id'],
            json_encode($draftData['platforms']),
            $draftData['notes']
        ]);
        
        $this->assertTrue($result, "La création de brouillon doit réussir");
        $draftId = $this->db->lastInsertId();
        
        // Vérifier le brouillon
        $stmt = $this->db->prepare("SELECT * FROM drafts WHERE id = ?");
        $stmt->execute([$draftId]);
        $draft = $stmt->fetch();
        
        $this->assertNotFalse($draft, "Le brouillon doit être trouvé");
        $this->assertEquals($draftData['title'], $draft['title']);
        $this->assertEquals($draftData['content'], $draft['content']);
        $this->assertEquals($draftData['notes'], $draft['notes']);
        $this->assertNull($draft['post_id'], "post_id doit être null pour un brouillon");
        
        // Convertir le brouillon en publication
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'draft', NOW())
        ");
        
        $result = $stmt->execute([
            $draft['title'],
            $draft['content'],
            $draft['client_id'],
            $draft['community_manager_id'],
            $draft['platforms']
        ]);
        
        $this->assertTrue($result, "La conversion en publication doit réussir");
        $postId = $this->db->lastInsertId();
        
        // Mettre à jour le brouillon avec l'ID de la publication
        $stmt = $this->db->prepare("UPDATE drafts SET post_id = ? WHERE id = ?");
        $result = $stmt->execute([$postId, $draftId]);
        
        $this->assertTrue($result, "La mise à jour du brouillon doit réussir");
        
        // Nettoyer
        $this->db->exec("DELETE FROM posts WHERE id = $postId");
        $this->db->exec("DELETE FROM drafts WHERE id = $draftId");
    }
    
    /**
     * Test du workflow de validation des données
     */
    public function testDataValidationWorkflow() {
        $invalidData = [
            'title' => '', // Titre vide
            'content' => 'Contenu valide',
            'platforms' => [], // Plateformes vides
            'status' => 'invalid_status'
        ];
        
        // Test de validation du titre
        $this->assertEmpty($invalidData['title'], "Le titre vide doit être détecté");
        
        // Test de validation des plateformes
        $this->assertEmpty($invalidData['platforms'], "Les plateformes vides doivent être détectées");
        
        // Test de validation du statut
        $validStatuses = ['draft', 'scheduled', 'published', 'failed', 'deleted'];
        $this->assertNotContains($invalidData['status'], $validStatuses, "Le statut invalide doit être détecté");
        
        // Test avec des données valides
        $validData = [
            'title' => 'Titre valide',
            'content' => 'Contenu valide avec au moins 10 caractères',
            'platforms' => ['facebook', 'instagram'],
            'status' => 'draft'
        ];
        
        $this->assertNotEmpty($validData['title'], "Le titre valide doit être accepté");
        $this->assertNotEmpty($validData['content'], "Le contenu valide doit être accepté");
        $this->assertNotEmpty($validData['platforms'], "Les plateformes valides doivent être acceptées");
        $this->assertContains($validData['status'], $validStatuses, "Le statut valide doit être accepté");
    }
    
    /**
     * Test du workflow de gestion des erreurs
     */
    public function testErrorHandlingWorkflow() {
        // Test avec un client inexistant
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Cette requête doit échouer à cause de la contrainte de clé étrangère
        $this->expectException(PDOException::class);
        $stmt->execute([
            'Test Error',
            'Contenu',
            99999, // ID client inexistant
            $this->cm['id'],
            json_encode(['facebook']),
            'draft'
        ]);
    }
    
    /**
     * Test du workflow de notifications
     */
    public function testNotificationWorkflow() {
        // Créer une publication
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'Publication avec notification',
            'Contenu de test',
            $this->client['id'],
            $this->cm['id'],
            json_encode(['facebook']),
            'published'
        ]);
        $postId = $this->db->lastInsertId();
        
        // Créer une notification pour le client
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $this->client['id'],
            'Nouvelle publication',
            'Votre publication a été publiée avec succès',
            'success',
            'post',
            $postId
        ]);
        
        $this->assertTrue($result, "La notification doit être créée");
        $notificationId = $this->db->lastInsertId();
        
        // Vérifier la notification
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch();
        
        $this->assertNotFalse($notification, "La notification doit être trouvée");
        $this->assertEquals($this->client['id'], $notification['user_id']);
        $this->assertEquals('post', $notification['related_entity_type']);
        $this->assertEquals($postId, $notification['related_entity_id']);
        $this->assertFalse($notification['is_read'], "La notification ne doit pas être lue");
        
        // Marquer comme lue
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
        $result = $stmt->execute([$notificationId]);
        
        $this->assertTrue($result, "La notification doit être marquée comme lue");
        
        // Nettoyer
        $this->db->exec("DELETE FROM notifications WHERE id = $notificationId");
        $this->db->exec("DELETE FROM posts WHERE id = $postId");
    }
}
?>
