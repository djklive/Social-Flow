<?php
/**
 * Script pour réinitialiser les comptes de test
 * Ce script crée ou met à jour les comptes de démonstration avec les bons mots de passe
 */

echo "<h1>🔧 Réinitialisation des Comptes de Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .section{margin:20px 0;padding:15px;border:1px solid #ddd;border-radius:5px;}</style>";

try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    $db = getDB();
    echo "<p class='success'>✓ Connexion à la base de données réussie</p>";
    
    // Comptes de test à créer/mettre à jour
    $test_accounts = [
        [
            'email' => 'admin@socialflow.com',
            'password' => 'password',
            'first_name' => 'Admin',
            'last_name' => 'System',
            'role' => 'admin',
            'phone' => '+237 6XX XX XX XX'
        ],
        [
            'email' => 'cm@socialflow.com',
            'password' => 'password',
            'first_name' => 'Community',
            'last_name' => 'Manager',
            'role' => 'community_manager',
            'phone' => '+237 6XX XX XX XX'
        ],
        [
            'email' => 'client@socialflow.com',
            'password' => 'password',
            'first_name' => 'Client',
            'last_name' => 'Demo',
            'role' => 'client',
            'phone' => '+237 6XX XX XX XX'
        ]
    ];
    
    echo "<div class='section'>";
    echo "<h2>Création/Mise à jour des comptes de test</h2>";
    
    foreach ($test_accounts as $account) {
        // Vérifier si l'utilisateur existe
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$account['email']]);
        $existing_user = $stmt->fetch();
        
        $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);
        
        if ($existing_user) {
            // Mettre à jour l'utilisateur existant
            $stmt = $db->prepare("UPDATE users SET 
                password_hash = ?, 
                first_name = ?, 
                last_name = ?, 
                role = ?, 
                status = 'active',
                phone = ?,
                email_verified = 1,
                updated_at = NOW()
                WHERE email = ?");
            $stmt->execute([
                $hashed_password,
                $account['first_name'],
                $account['last_name'],
                $account['role'],
                $account['phone'],
                $account['email']
            ]);
            echo "<p class='success'>✓ Compte mis à jour: " . $account['email'] . " (" . $account['role'] . ")</p>";
        } else {
            // Créer un nouvel utilisateur
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, status, phone, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', ?, 1, NOW(), NOW())");
            $stmt->execute([
                $account['email'],
                $hashed_password,
                $account['first_name'],
                $account['last_name'],
                $account['role'],
                $account['phone']
            ]);
            echo "<p class='success'>✓ Compte créé: " . $account['email'] . " (" . $account['role'] . ")</p>";
        }
    }
    echo "</div>";
    
    // Créer des abonnements de test pour le client
    echo "<div class='section'>";
    echo "<h2>Création des abonnements de test</h2>";
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'client@socialflow.com'");
    $stmt->execute();
    $client = $stmt->fetch();
    
    if ($client) {
        // Vérifier si l'abonnement existe
        $stmt = $db->prepare("SELECT id FROM subscriptions WHERE client_id = ?");
        $stmt->execute([$client['id']]);
        $existing_subscription = $stmt->fetch();
        
        if (!$existing_subscription) {
            // Créer un abonnement mensuel actif
            $stmt = $db->prepare("INSERT INTO subscriptions (client_id, plan_type, price, status, start_date, end_date, created_at, updated_at) VALUES (?, 'monthly', 25000, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), NOW(), NOW())");
            $stmt->execute([$client['id']]);
            echo "<p class='success'>✓ Abonnement mensuel créé pour le client</p>";
        } else {
            echo "<p class='info'>ℹ️ Abonnement existant trouvé pour le client</p>";
        }
    }
    echo "</div>";
    
    // Créer des assignations de test
    echo "<div class='section'>";
    echo "<h2>Création des assignations de test</h2>";
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'client@socialflow.com'");
    $stmt->execute();
    $client = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'cm@socialflow.com'");
    $stmt->execute();
    $cm = $stmt->fetch();
    
    if ($client && $cm) {
        // Vérifier si l'assignation existe
        $stmt = $db->prepare("SELECT id FROM client_assignments WHERE client_id = ? AND community_manager_id = ?");
        $stmt->execute([$client['id'], $cm['id']]);
        $existing_assignment = $stmt->fetch();
        
        if (!$existing_assignment) {
            // Créer une assignation
            $stmt = $db->prepare("INSERT INTO client_assignments (client_id, community_manager_id, assigned_by, assigned_at, status) VALUES (?, ?, 1, NOW(), 'active')");
            $stmt->execute([$client['id'], $cm['id']]);
            echo "<p class='success'>✓ Assignation client-CM créée</p>";
        } else {
            echo "<p class='info'>ℹ️ Assignation existante trouvée</p>";
        }
    }
    echo "</div>";
    
    // Créer quelques publications de test
    echo "<div class='section'>";
    echo "<h2>Création de publications de test</h2>";
    
    if ($client && $cm) {
        $test_posts = [
            [
                'title' => 'Bienvenue sur SocialFlow !',
                'content' => 'Découvrez notre plateforme de gestion de contenu pour les réseaux sociaux. Automatisez vos publications et maximisez votre engagement.',
                'platforms' => json_encode(['facebook', 'instagram']),
                'status' => 'published'
            ],
            [
                'title' => 'Nouvelle fonctionnalité disponible',
                'content' => 'Nous avons ajouté de nouvelles fonctionnalités pour améliorer votre expérience. Consultez votre dashboard pour en savoir plus.',
                'platforms' => json_encode(['twitter', 'linkedin']),
                'status' => 'scheduled'
            ]
        ];
        
        foreach ($test_posts as $post) {
            $stmt = $db->prepare("INSERT INTO posts (client_id, community_manager_id, title, content, platforms, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $client['id'],
                $cm['id'],
                $post['title'],
                $post['content'],
                $post['platforms'],
                $post['status']
            ]);
        }
        echo "<p class='success'>✓ Publications de test créées</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>✅ Réinitialisation terminée !</h2>";
    echo "<p class='success'>Tous les comptes de test ont été créés/mis à jour avec succès.</p>";
    echo "<p class='info'>Vous pouvez maintenant vous connecter avec :</p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@socialflow.com / password</li>";
    echo "<li><strong>Community Manager:</strong> cm@socialflow.com / password</li>";
    echo "<li><strong>Client:</strong> client@socialflow.com / password</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur: " . $e->getMessage() . "</p>";
    echo "<p class='info'>Vérifiez que la base de données est correctement configurée.</p>";
}

echo "<p style='margin-top:30px;text-align:center;'>";
echo "<a href='debug_connection.php' style='background:#007cba;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-right:10px;'>Diagnostic</a>";
echo "<a href='auth/login.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Se connecter</a>";
echo "</p>";
?>
