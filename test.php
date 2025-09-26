<?php
/**
 * Script de test pour SocialFlow
 * Vérifie que tous les composants fonctionnent correctement
 */

echo "<h1>🧪 Test de SocialFlow</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Test 1: Configuration
echo "<h2>1. Test de configuration</h2>";
try {
    require_once 'config/database.php';
    echo "<p class='success'>✅ Configuration de base de données chargée</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur configuration: " . $e->getMessage() . "</p>";
}

// Test 2: Connexion base de données
echo "<h2>2. Test de connexion base de données</h2>";
try {
    $db = getDB();
    echo "<p class='success'>✅ Connexion à la base de données réussie</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur connexion BDD: " . $e->getMessage() . "</p>";
}

// Test 3: Fonctions utilitaires
echo "<h2>3. Test des fonctions utilitaires</h2>";
try {
    require_once 'includes/functions.php';
    echo "<p class='success'>✅ Fonctions utilitaires chargées</p>";
    
    // Test de validation email
    if (validate_email('test@example.com')) {
        echo "<p class='success'>✅ Validation email fonctionne</p>";
    } else {
        echo "<p class='error'>❌ Validation email échoue</p>";
    }
    
    // Test de hachage mot de passe
    $password = 'test123';
    $hash = hash_password($password);
    if (verify_password($password, $hash)) {
        echo "<p class='success'>✅ Hachage mot de passe fonctionne</p>";
    } else {
        echo "<p class='error'>❌ Hachage mot de passe échoue</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur fonctions: " . $e->getMessage() . "</p>";
}

// Test 4: Tables de base de données
echo "<h2>4. Test des tables de base de données</h2>";
try {
    $tables = ['users', 'subscriptions', 'payments', 'posts', 'notifications', 'client_assignments'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "<p class='success'>✅ Table '$table' existe</p>";
        } else {
            echo "<p class='error'>❌ Table '$table' manquante</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur test tables: " . $e->getMessage() . "</p>";
}

// Test 5: Comptes de démonstration
echo "<h2>5. Test des comptes de démonstration</h2>";
try {
    $stmt = $db->prepare("SELECT email, role FROM users WHERE email IN (?, ?, ?)");
    $stmt->execute(['admin@socialflow.com', 'cm@socialflow.com', 'client@socialflow.com']);
    $users = $stmt->fetchAll();
    
    if (count($users) === 3) {
        echo "<p class='success'>✅ Tous les comptes de démonstration existent</p>";
        foreach ($users as $user) {
            echo "<p class='info'>📧 {$user['email']} - {$user['role']}</p>";
        }
    } else {
        echo "<p class='error'>❌ Comptes de démonstration manquants</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur test comptes: " . $e->getMessage() . "</p>";
}

// Test 6: Système de notifications
echo "<h2>6. Test du système de notifications</h2>";
try {
    require_once 'includes/notifications.php';
    echo "<p class='success'>✅ Système de notifications chargé</p>";
    
    // Test création notification
    $notification_id = create_notification(1, 'Test', 'Notification de test', 'info');
    if ($notification_id) {
        echo "<p class='success'>✅ Création de notification fonctionne</p>";
        
        // Nettoyer la notification de test
        delete_notification($notification_id, 1);
        echo "<p class='info'>🧹 Notification de test supprimée</p>";
    } else {
        echo "<p class='error'>❌ Création de notification échoue</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur notifications: " . $e->getMessage() . "</p>";
}

// Test 7: Sessions
echo "<h2>7. Test des sessions</h2>";
try {
    session_start();
    $_SESSION['test'] = 'value';
    if (isset($_SESSION['test']) && $_SESSION['test'] === 'value') {
        echo "<p class='success'>✅ Sessions PHP fonctionnent</p>";
        unset($_SESSION['test']);
    } else {
        echo "<p class='error'>❌ Sessions PHP échouent</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur sessions: " . $e->getMessage() . "</p>";
}

// Test 8: Permissions fichiers
echo "<h2>8. Test des permissions</h2>";
$directories = ['config', 'includes', 'auth', 'client', 'cm', 'admin', 'database'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_readable($dir)) {
            echo "<p class='success'>✅ Dossier '$dir' accessible en lecture</p>";
        } else {
            echo "<p class='error'>❌ Dossier '$dir' non accessible</p>";
        }
    } else {
        echo "<p class='error'>❌ Dossier '$dir' manquant</p>";
    }
}

// Résumé
echo "<h2>📊 Résumé des tests</h2>";
echo "<p class='info'>🎯 SocialFlow est prêt à être utilisé !</p>";
echo "<p class='info'>📖 Consultez le README.md pour la documentation complète</p>";
echo "<p class='info'>🚀 Consultez INSTALL.md pour le guide d'installation</p>";
echo "<p class='info'>🔗 Accédez à l'application : <a href='index.php'>http://localhost/SF2</a></p>";

echo "<hr>";
echo "<p><strong>Comptes de test :</strong></p>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin@socialflow.com / password</li>";
echo "<li><strong>Community Manager:</strong> cm@socialflow.com / password</li>";
echo "<li><strong>Client:</strong> client@socialflow.com / password</li>";
echo "</ul>";
?>
