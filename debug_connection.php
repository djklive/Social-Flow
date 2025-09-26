<?php
/**
 * Script de diagnostic pour les problèmes de connexion
 * Ce fichier aide à identifier les problèmes de base de données et d'authentification
 */

echo "<h1>🔍 Diagnostic de Connexion SocialFlow</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .section{margin:20px 0;padding:15px;border:1px solid #ddd;border-radius:5px;}</style>";

// Test 1: Vérification de la configuration PHP
echo "<div class='section'>";
echo "<h2>1. Configuration PHP</h2>";
echo "<p class='info'>Version PHP: " . PHP_VERSION . "</p>";
echo "<p class='info'>Extensions PDO: " . (extension_loaded('pdo') ? '<span class="success">✓ Installée</span>' : '<span class="error">✗ Manquante</span>') . "</p>";
echo "<p class='info'>Extension PDO MySQL: " . (extension_loaded('pdo_mysql') ? '<span class="success">✓ Installée</span>' : '<span class="error">✗ Manquante</span>') . "</p>";
echo "</div>";

// Test 2: Vérification de la connexion à la base de données
echo "<div class='section'>";
echo "<h2>2. Connexion à la Base de Données</h2>";

try {
    require_once 'config/database.php';
    $db = getDB();
    echo "<p class='success'>✓ Connexion à la base de données réussie</p>";
    echo "<p class='info'>Base de données: " . DB_NAME . "</p>";
    echo "<p class='info'>Hôte: " . DB_HOST . "</p>";
    echo "<p class='info'>Utilisateur: " . DB_USER . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur de connexion: " . $e->getMessage() . "</p>";
    echo "<p class='info'>Vérifiez que MySQL est démarré dans XAMPP</p>";
    echo "</div>";
    exit;
}
echo "</div>";

// Test 3: Vérification de l'existence des tables
echo "<div class='section'>";
echo "<h2>3. Vérification des Tables</h2>";

$required_tables = ['users', 'subscriptions', 'payments', 'posts', 'notifications', 'client_assignments'];
$existing_tables = [];

try {
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "<p class='success'>✓ Table '$table' existe</p>";
            $existing_tables[] = $table;
        } else {
            echo "<p class='error'>✗ Table '$table' manquante</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur lors de la vérification des tables: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Vérification de la structure de la table users
echo "<div class='section'>";
echo "<h2>4. Structure de la Table 'users'</h2>";

if (in_array('users', $existing_tables)) {
    try {
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $required_columns = ['id', 'email', 'password_hash', 'first_name', 'last_name', 'role', 'status'];
        
        foreach ($required_columns as $col) {
            $found = false;
            foreach ($columns as $column) {
                if ($column['Field'] === $col) {
                    echo "<p class='success'>✓ Colonne '$col' existe (" . $column['Type'] . ")</p>";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "<p class='error'>✗ Colonne '$col' manquante</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erreur lors de la vérification de la structure: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>✗ Impossible de vérifier la structure - table 'users' manquante</p>";
}
echo "</div>";

// Test 5: Vérification des données de test
echo "<div class='section'>";
echo "<h2>5. Données de Test</h2>";

if (in_array('users', $existing_tables)) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p class='info'>Nombre d'utilisateurs dans la base: " . $result['count'] . "</p>";
        
        if ($result['count'] > 0) {
            $stmt = $db->query("SELECT id, email, role, status FROM users LIMIT 5");
            $users = $stmt->fetchAll();
            
            echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
            echo "<tr><th>ID</th><th>Email</th><th>Rôle</th><th>Statut</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td>" . $user['email'] . "</td>";
                echo "<td>" . $user['role'] . "</td>";
                echo "<td>" . $user['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>✗ Aucun utilisateur trouvé dans la base de données</p>";
            echo "<p class='info'>Vous devez importer le fichier socialflow_db.sql</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erreur lors de la vérification des données: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Test 6: Test d'authentification
echo "<div class='section'>";
echo "<h2>6. Test d'Authentification</h2>";

if (in_array('users', $existing_tables)) {
    try {
        // Test avec les comptes de démonstration
        $test_accounts = [
            ['email' => 'admin@socialflow.com', 'role' => 'admin'],
            ['email' => 'cm@socialflow.com', 'role' => 'community_manager'],
            ['email' => 'client@socialflow.com', 'role' => 'client']
        ];
        
        foreach ($test_accounts as $account) {
            $stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name, role, status FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$account['email'], $account['role']]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p class='success'>✓ Compte trouvé: " . $account['email'] . " (" . $account['role'] . ")</p>";
                echo "<p class='info'>  - ID: " . $user['id'] . "</p>";
                echo "<p class='info'>  - Nom: " . $user['first_name'] . " " . $user['last_name'] . "</p>";
                echo "<p class='info'>  - Statut: " . $user['status'] . "</p>";
                echo "<p class='info'>  - Mot de passe hashé: " . (empty($user['password_hash']) ? '<span class="error">✗ Vide</span>' : '<span class="success">✓ Présent</span>') . "</p>";
            } else {
                echo "<p class='error'>✗ Compte non trouvé: " . $account['email'] . " (" . $account['role'] . ")</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erreur lors du test d'authentification: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Test 7: Vérification des fonctions
echo "<div class='section'>";
echo "<h2>7. Vérification des Fonctions</h2>";

try {
    require_once 'includes/functions.php';
    
    // Test de la fonction validate_email
    $email_test = validate_email('test@example.com');
    echo "<p class='info'>Fonction validate_email: " . ($email_test ? '<span class="success">✓ Fonctionne</span>' : '<span class="error">✗ Problème</span>') . "</p>";
    
    // Test de la fonction verify_password
    $test_password = 'password';
    $test_hash = password_hash($test_password, PASSWORD_DEFAULT);
    $password_test = password_verify($test_password, $test_hash);
    echo "<p class='info'>Fonction password_verify: " . ($password_test ? '<span class="success">✓ Fonctionne</span>' : '<span class="error">✗ Problème</span>') . "</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur lors de la vérification des fonctions: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Recommandations
echo "<div class='section'>";
echo "<h2>8. Recommandations</h2>";

if (!in_array('users', $existing_tables)) {
    echo "<p class='error'>⚠️ ACTION REQUISE: Importez le fichier socialflow_db.sql dans phpMyAdmin</p>";
    echo "<ol>";
    echo "<li>Ouvrez phpMyAdmin (http://localhost/phpmyadmin)</li>";
    echo "<li>Créez une base de données nommée 'socialflow_db'</li>";
    echo "<li>Importez le fichier database/socialflow_db.sql</li>";
    echo "</ol>";
} else {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        echo "<p class='error'>⚠️ ACTION REQUISE: Aucun utilisateur trouvé. Importez les données de test</p>";
    } else {
        echo "<p class='success'>✓ Base de données configurée correctement</p>";
        echo "<p class='info'>Si vous avez encore des problèmes de connexion, vérifiez:</p>";
        echo "<ul>";
        echo "<li>Que vous utilisez les bons identifiants (voir les comptes de démonstration)</li>";
        echo "<li>Que le rôle sélectionné correspond à l'email</li>";
        echo "<li>Que le compte est actif (status = 'active')</li>";
        echo "</ul>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>9. Comptes de Démonstration</h2>";
echo "<p class='info'>Utilisez ces comptes pour tester:</p>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin@socialflow.com / password</li>";
echo "<li><strong>Community Manager:</strong> cm@socialflow.com / password</li>";
echo "<li><strong>Client:</strong> client@socialflow.com / password</li>";
echo "</ul>";
echo "</div>";

echo "<p style='margin-top:30px;text-align:center;'><a href='auth/login.php' style='background:#007cba;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Retour à la connexion</a></p>";
?>
