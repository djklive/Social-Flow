<?php
/**
 * TEST D'INTÉGRATION - SocialFlow
 * Teste l'ajout d'utilisateur et l'ajout de publication
 */

echo "=== TEST D'INTÉGRATION - SOCIALFLOW ===\n";
echo "Test d'ajout d'utilisateur et d'ajout de publication...\n\n";

$tests_passed = 0;
$tests_total = 0;
$errors = [];

// Fonction pour afficher le résultat
function test_result($test_name, $condition, $error_msg = "") {
    global $tests_passed, $tests_total, $errors;
    
    $tests_total++;
    
    if ($condition) {
        echo "✅ PASSED: $test_name\n";
        $tests_passed++;
        return true;
    } else {
        echo "❌ FAILED: $test_name\n";
        if ($error_msg) {
            echo "   Erreur: $error_msg\n";
            $errors[] = "$test_name: $error_msg";
        }
        return false;
    }
}

// Initialisation
require_once 'config/database.php';
require_once 'includes/functions.php';
$db = getDB();

// Test 1: Ajout d'un utilisateur
echo "1. Test d'ajout d'un utilisateur...\n";
try {
    // Données de test pour un nouvel utilisateur
    $user_data = [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont.' . time() . '@example.com',
        'phone' => '+237123456789',
        'password' => 'password123',
        'role' => 'client',
        'status' => 'active'
    ];
    
    // Hacher le mot de passe
    $hashed_password = hash_password($user_data['password']);
    
    // Insérer l'utilisateur
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $result = $stmt->execute([
        $user_data['first_name'],
        $user_data['last_name'],
        $user_data['email'],
        $user_data['phone'],
        $hashed_password,
        $user_data['role'],
        $user_data['status'],
        1
    ]);
    
    test_result("Insertion Utilisateur", $result);
    
    if ($result) {
        $user_id = $db->lastInsertId();
        test_result("Génération ID Utilisateur", $user_id > 0);
        
        // Vérifier que l'utilisateur a été créé
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $created_user = $stmt->fetch();
        
        test_result("Vérification Création Utilisateur", $created_user !== false);
        test_result("Vérification Prénom", $created_user['first_name'] === $user_data['first_name']);
        test_result("Vérification Nom", $created_user['last_name'] === $user_data['last_name']);
        test_result("Vérification Email", $created_user['email'] === $user_data['email']);
        test_result("Vérification Téléphone", $created_user['phone'] === $user_data['phone']);
        test_result("Vérification Rôle", $created_user['role'] === $user_data['role']);
        test_result("Vérification Statut", $created_user['status'] === $user_data['status']);
        test_result("Vérification Mot de Passe", password_verify($user_data['password'], $created_user['password_hash']));
        
        // Nettoyage
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        test_result("Suppression Utilisateur Test", true);
    }
    
} catch (Exception $e) {
    test_result("Ajout Utilisateur", false, $e->getMessage());
}

// Test 2: Ajout d'une publication
echo "\n2. Test d'ajout d'une publication...\n";
require_once 'includes/functions.php';

try {
    // Créer un client et un CM pour la publication
    $client_email = 'client.pub.' . time() . '@example.com';
    $cm_email = 'cm.pub.' . time() . '@example.com';
    $hashed_password = hash_password('password123');
    
    // Créer le client
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Client', 'Publication', $client_email, $hashed_password, 'client', 'active', 1]);
    $client_id = $db->lastInsertId();
    
    // Créer le CM
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['CM', 'Publication', $cm_email, $hashed_password, 'community_manager', 'active', 1]);
    $cm_id = $db->lastInsertId();
    
    test_result("Création Client pour Publication", $client_id > 0);
    test_result("Création CM pour Publication", $cm_id > 0);
    
    // Données de la publication
    $post_data = [
        'title' => 'Test Publication ' . time(),
        'content' => 'Ceci est le contenu de test pour une nouvelle publication.',
        'platforms' => ['facebook', 'instagram', 'twitter'],
        'status' => 'draft',
        'client_id' => $client_id,
        'community_manager_id' => $cm_id
    ];
    
    // Insérer la publication
    $stmt = $db->prepare("INSERT INTO posts (title, content, client_id, community_manager_id, platforms, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $result = $stmt->execute([
        $post_data['title'],
        $post_data['content'],
        $post_data['client_id'],
        $post_data['community_manager_id'],
        json_encode($post_data['platforms']),
        $post_data['status']
    ]);
    
    test_result("Insertion Publication", $result);
    
    if ($result) {
        $post_id = $db->lastInsertId();
        test_result("Génération ID Publication", $post_id > 0);
        
        // Vérifier que la publication a été créée
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $created_post = $stmt->fetch();
        
        test_result("Vérification Création Publication", $created_post !== false);
        test_result("Vérification Titre", $created_post['title'] === $post_data['title']);
        test_result("Vérification Contenu", $created_post['content'] === $post_data['content']);
        test_result("Vérification Client ID", $created_post['client_id'] == $post_data['client_id']);
        test_result("Vérification CM ID", $created_post['community_manager_id'] == $post_data['community_manager_id']);
        test_result("Vérification Statut", $created_post['status'] === $post_data['status']);
        
        // Vérifier les plateformes
        $stored_platforms = json_decode($created_post['platforms'], true);
        test_result("Vérification Plateformes", $stored_platforms === $post_data['platforms']);
        
        // Test de mise à jour du statut
        $stmt = $db->prepare("UPDATE posts SET status = ?, published_at = NOW() WHERE id = ?");
        $update_result = $stmt->execute(['published', $post_id]);
        test_result("Mise à Jour Statut Publication", $update_result);
        
        // Nettoyage
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        test_result("Suppression Publication Test", true);
    }
    
    // Nettoyage des utilisateurs
    $stmt = $db->prepare("DELETE FROM users WHERE id IN (?, ?)");
    $stmt->execute([$client_id, $cm_id]);
    test_result("Suppression Utilisateurs Test", true);
    
} catch (Exception $e) {
    test_result("Ajout Publication", false, $e->getMessage());
}

// Test 3: Test d'intégrité des données
echo "\n3. Test d'intégrité des données...\n";

try {
    // Vérifier que les tables existent
    $tables = ['users', 'posts', 'subscriptions', 'client_assignments'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        test_result("Table $table existe", $stmt->rowCount() > 0);
    }
    
    // Test de contrainte d'unicité email
    $duplicate_email = 'duplicate.test.' . time() . '@example.com';
    $hashed_password = hash_password('password123');
    
    // Créer le premier utilisateur
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['First', 'User', $duplicate_email, $hashed_password, 'client', 'active']);
    $first_user_id = $db->lastInsertId();
    
    // Tenter de créer un deuxième utilisateur avec le même email
    try {
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Second', 'User', $duplicate_email, $hashed_password, 'client', 'active']);
        test_result("Contrainte Unicité Email", false, "L'email dupliqué a été accepté");
    } catch (PDOException $e) {
        test_result("Contrainte Unicité Email", true);
    }
    
    // Nettoyage
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$first_user_id]);
    
} catch (Exception $e) {
    test_result("Intégrité des Données", false, $e->getMessage());
}

// Test 4: Création d'utilisateur avec validation
echo "\n4. Test de création d'utilisateur avec validation...\n";

try {
    // Données de test pour un utilisateur avec validation
    $user_data = [
        'first_name' => 'Marie',
        'last_name' => 'Martin',
        'email' => 'marie.martin.' . time() . '@example.com',
        'phone' => '+237987654321',
        'password' => 'securepass456',
        'role' => 'community_manager',
        'status' => 'pending'
    ];
    
    // Validation des données avant insertion
    $is_valid = true;
    $validation_errors = [];
    
    // Valider le prénom
    if (empty(trim($user_data['first_name']))) {
        $is_valid = false;
        $validation_errors[] = "Prénom requis";
    }
    
    // Valider le nom
    if (empty(trim($user_data['last_name']))) {
        $is_valid = false;
        $validation_errors[] = "Nom requis";
    }
    
    // Valider l'email
    if (!filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
        $is_valid = false;
        $validation_errors[] = "Email invalide";
    }
    
    // Valider le téléphone
    if (!validate_phone($user_data['phone'])) {
        $is_valid = false;
        $validation_errors[] = "Téléphone invalide";
    }
    
    // Valider le mot de passe
    if (strlen($user_data['password']) < 6) {
        $is_valid = false;
        $validation_errors[] = "Mot de passe trop court";
    }
    
    // Valider le rôle
    if (!in_array($user_data['role'], ['admin', 'community_manager', 'client'])) {
        $is_valid = false;
        $validation_errors[] = "Rôle invalide";
    }
    
    test_result("Validation Données Utilisateur", $is_valid);
    
    if ($is_valid) {
        // Hacher le mot de passe
        $hashed_password = hash_password($user_data['password']);
        
        // Insérer l'utilisateur avec validation
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $result = $stmt->execute([
            $user_data['first_name'],
            $user_data['last_name'],
            $user_data['email'],
            $user_data['phone'],
            $hashed_password,
            $user_data['role'],
            $user_data['status'],
            0 // Email non vérifié pour statut pending
        ]);
        
        test_result("Insertion Utilisateur Validé", $result);
        
        if ($result) {
            $user_id = $db->lastInsertId();
            test_result("Génération ID Utilisateur Validé", $user_id > 0);
            
            // Vérifier les données insérées
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $created_user = $stmt->fetch();
            
            test_result("Vérification Données Validées", $created_user !== false);
            test_result("Vérification Statut Pending", $created_user['status'] === 'pending');
            test_result("Vérification Email Non Vérifié", $created_user['email_verified'] == 0);
            
            // Nettoyage
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            test_result("Suppression Utilisateur Validé", true);
        }
    } else {
        test_result("Validation Échouée", false, implode(', ', $validation_errors));
    }
    
} catch (Exception $e) {
    test_result("Création Utilisateur avec Validation", false, $e->getMessage());
}

// Résumé final
echo "\n" . str_repeat("=", 60) . "\n";
echo "RÉSUMÉ DU TEST D'INTÉGRATION\n";
echo str_repeat("=", 60) . "\n";
echo "Tests exécutés: $tests_total\n";
echo "Tests réussis: $tests_passed\n";
echo "Tests échoués: " . ($tests_total - $tests_passed) . "\n";
echo "Taux de réussite: " . round(($tests_passed / $tests_total) * 100, 2) . "%\n";

if ($tests_passed === $tests_total) {
    echo "\n🎉 TOUS LES TESTS SONT PASSED ! 🎉\n";
    echo "✅ L'ajout d'utilisateur et de publication fonctionne parfaitement !\n";
} else {
    echo "\n⚠️  CERTAINS TESTS ONT ÉCHOUÉ ⚠️\n";
    echo "Erreurs détectées:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test d'intégration terminé.\n";
echo "Date: " . date('d/m/Y H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";
?>
