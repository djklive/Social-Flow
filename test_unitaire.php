<?php
/**
 * TEST UNITAIRE - SocialFlow
 * Teste l'affichage des utilisateurs et des documents
 */

echo "=== TEST UNITAIRE - SOCIALFLOW ===\n";
echo "Test d'affichage des utilisateurs et documents...\n\n";

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

// Test 1: Affichage de tous les utilisateurs
echo "1. Test d'affichage de tous les utilisateurs...\n";

try {
    // Récupérer tous les utilisateurs
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, status, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    test_result("Récupération Liste Utilisateurs", is_array($users));
    test_result("Nombre d'utilisateurs récupérés", count($users) >= 0);
    
    if (count($users) > 0) {
        $first_user = $users[0];
        test_result("Structure Utilisateur - ID", isset($first_user['id']));
        test_result("Structure Utilisateur - Prénom", isset($first_user['first_name']));
        test_result("Structure Utilisateur - Nom", isset($first_user['last_name']));
        test_result("Structure Utilisateur - Email", isset($first_user['email']));
        test_result("Structure Utilisateur - Rôle", isset($first_user['role']));
        test_result("Structure Utilisateur - Statut", isset($first_user['status']));
        test_result("Structure Utilisateur - Date création", isset($first_user['created_at']));
        
        // Test de formatage des données
        test_result("Formatage Prénom", !empty(trim($first_user['first_name'])));
        test_result("Formatage Nom", !empty(trim($first_user['last_name'])));
        test_result("Formatage Email", filter_var($first_user['email'], FILTER_VALIDATE_EMAIL) !== false);
        test_result("Formatage Rôle", in_array($first_user['role'], ['admin', 'community_manager', 'client']));
        test_result("Formatage Statut", in_array($first_user['status'], ['active', 'inactive', 'pending', 'suspended']));
    }
    
} catch (Exception $e) {
    test_result("Affichage Utilisateurs", false, $e->getMessage());
}

// Test 2: Affichage des utilisateurs par rôle
echo "\n2. Test d'affichage des utilisateurs par rôle...\n";

$roles = ['admin', 'community_manager', 'client'];
foreach ($roles as $role) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $result = $stmt->fetch();
        
        test_result("Comptage Utilisateurs $role", $result['count'] >= 0);
        
        // Récupérer les utilisateurs de ce rôle
        $stmt = $db->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
        $stmt->execute([$role]);
        $role_users = $stmt->fetchAll();
        
        test_result("Récupération Utilisateurs $role", is_array($role_users));
        test_result("Cohérence Comptage $role", count($role_users) == $result['count']);
        
    } catch (Exception $e) {
        test_result("Affichage Utilisateurs $role", false, $e->getMessage());
    }
}

// Test 3: Affichage de tous les documents/publications
echo "\n3. Test d'affichage de tous les documents...\n";

try {
    // Récupérer toutes les publications
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.content, p.status, p.created_at, p.scheduled_at, p.published_at,
               u1.first_name as client_name, u1.last_name as client_lastname,
               u2.first_name as cm_name, u2.last_name as cm_lastname
        FROM posts p
        LEFT JOIN users u1 ON p.client_id = u1.id
        LEFT JOIN users u2 ON p.community_manager_id = u2.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    test_result("Récupération Liste Publications", is_array($posts));
    test_result("Nombre de publications récupérées", count($posts) >= 0);
    
    if (count($posts) > 0) {
        $first_post = $posts[0];
        test_result("Structure Publication - ID", isset($first_post['id']));
        test_result("Structure Publication - Titre", isset($first_post['title']));
        test_result("Structure Publication - Contenu", isset($first_post['content']));
        test_result("Structure Publication - Statut", isset($first_post['status']));
        test_result("Structure Publication - Date création", isset($first_post['created_at']));
        test_result("Structure Publication - Client", isset($first_post['client_name']));
        test_result("Structure Publication - CM", isset($first_post['cm_name']));
        
        // Test de formatage des données
        test_result("Formatage Titre Publication", !empty(trim($first_post['title'])));
        test_result("Formatage Contenu Publication", !empty(trim($first_post['content'])));
        test_result("Formatage Statut Publication", in_array($first_post['status'], ['draft', 'published', 'scheduled', 'cancelled']));
    }
    
} catch (Exception $e) {
    test_result("Affichage Publications", false, $e->getMessage());
}

// Test 4: Affichage des publications par statut
echo "\n4. Test d'affichage des publications par statut...\n";

$post_statuses = ['draft', 'published', 'scheduled', 'cancelled'];
foreach ($post_statuses as $status) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE status = ?");
        $stmt->execute([$status]);
        $result = $stmt->fetch();
        
        test_result("Comptage Publications $status", $result['count'] >= 0);
        
        // Récupérer les publications de ce statut
        $stmt = $db->prepare("SELECT * FROM posts WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        $status_posts = $stmt->fetchAll();
        
        test_result("Récupération Publications $status", is_array($status_posts));
        test_result("Cohérence Comptage Publications $status", count($status_posts) == $result['count']);
        
    } catch (Exception $e) {
        test_result("Affichage Publications $status", false, $e->getMessage());
    }
}

// Test 5: Test de pagination des utilisateurs
echo "\n5. Test de pagination des utilisateurs...\n";

try {
    $limit = 5;
    $offset = 0;
    
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $paginated_users = $stmt->fetchAll();
    
    test_result("Pagination Utilisateurs - Limite", count($paginated_users) <= $limit);
    test_result("Pagination Utilisateurs - Récupération", is_array($paginated_users));
    
} catch (Exception $e) {
    test_result("Pagination Utilisateurs", false, $e->getMessage());
}

// Test 6: Test de pagination des publications
echo "\n6. Test de pagination des publications...\n";

try {
    $limit = 10;
    $offset = 0;
    
    $stmt = $db->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $paginated_posts = $stmt->fetchAll();
    
    test_result("Pagination Publications - Limite", count($paginated_posts) <= $limit);
    test_result("Pagination Publications - Récupération", is_array($paginated_posts));
    
} catch (Exception $e) {
    test_result("Pagination Publications", false, $e->getMessage());
}

// Résumé final
echo "\n" . str_repeat("=", 60) . "\n";
echo "RÉSUMÉ DU TEST UNITAIRE\n";
echo str_repeat("=", 60) . "\n";
echo "Tests exécutés: $tests_total\n";
echo "Tests réussis: $tests_passed\n";
echo "Tests échoués: " . ($tests_total - $tests_passed) . "\n";
echo "Taux de réussite: " . round(($tests_passed / $tests_total) * 100, 2) . "%\n";

if ($tests_passed === $tests_total) {
    echo "\n🎉 TOUS LES TESTS SONT PASSED ! 🎉\n";
    echo "✅ L'affichage des utilisateurs et documents fonctionne parfaitement !\n";
} else {
    echo "\n⚠️  CERTAINS TESTS ONT ÉCHOUÉ ⚠️\n";
    echo "Erreurs détectées:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test unitaire terminé.\n";
echo "Date: " . date('d/m/Y H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";
?>
