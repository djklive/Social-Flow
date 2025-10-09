<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('community_manager');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$success_message = '';
$error_message = '';

// Traitement des modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'update_profile':
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $error_message = 'Veuillez remplir tous les champs obligatoires.';
                } elseif (!validate_email($email)) {
                    $error_message = 'Adresse email invalide.';
                } elseif (!empty($phone) && !validate_phone($phone)) {
                    $error_message = 'Numéro de téléphone invalide.';
                } else {
                    // Vérifier si l'email existe déjà pour un autre utilisateur
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $error_message = 'Cette adresse email est déjà utilisée.';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
                        
                        // Mettre à jour la session
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        
                        // Logger l'activité
                        log_activity($user_id, 'profile_updated', 'Profil mis à jour');
                        
                        $success_message = 'Profil mis à jour avec succès.';
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'Veuillez remplir tous les champs.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'Les nouveaux mots de passe ne correspondent pas.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                } else {
                    // Vérifier le mot de passe actuel
                    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!verify_password($current_password, $user['password'])) {
                        $error_message = 'Mot de passe actuel incorrect.';
                    } else {
                        $hashed_password = hash_password($new_password);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        
                        // Logger l'activité
                        log_activity($user_id, 'password_changed', 'Mot de passe modifié');
                        
                        $success_message = 'Mot de passe modifié avec succès.';
                    }
                }
                break;
                
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                
                // Vérifier si les colonnes existent dans la table users
                $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'email_notifications'");
                $stmt->execute();
                $email_col_exists = $stmt->fetch();
                
                if ($email_col_exists) {
                    // Les colonnes existent, on peut faire la mise à jour
                    $stmt = $db->prepare("UPDATE users SET email_notifications = ?, push_notifications = ?, sms_notifications = ? WHERE id = ?");
                    $stmt->execute([$email_notifications, $push_notifications, $sms_notifications, $user_id]);
                } else {
                    // Les colonnes n'existent pas, on les ajoute d'abord
                    $db->exec("ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) DEFAULT 1");
                    $db->exec("ALTER TABLE users ADD COLUMN push_notifications TINYINT(1) DEFAULT 1");
                    $db->exec("ALTER TABLE users ADD COLUMN sms_notifications TINYINT(1) DEFAULT 0");
                    
                    // Puis on fait la mise à jour
                    $stmt = $db->prepare("UPDATE users SET email_notifications = ?, push_notifications = ?, sms_notifications = ? WHERE id = ?");
                    $stmt->execute([$email_notifications, $push_notifications, $sms_notifications, $user_id]);
                }
                
                // Logger l'activité
                log_activity($user_id, 'notifications_updated', 'Préférences de notifications mises à jour');
                
                $success_message = 'Préférences de notifications mises à jour.';
                break;
                
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur paramètres CM: " . $e->getMessage());
    }
}

// Récupérer les données du Community Manager
try {
    $db = getDB();
    
    // Informations du CM
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $cm = $stmt->fetch();
    
    // Statistiques du CM
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT ca.client_id) as total_clients,
            COUNT(p.id) as total_posts,
            SUM(CASE WHEN p.status = 'published' THEN 1 ELSE 0 END) as published_posts
        FROM client_assignments ca
        LEFT JOIN posts p ON ca.client_id = p.client_id AND p.community_manager_id = ?
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données paramètres CM: " . $e->getMessage());
    $cm = null;
    $stats = ['total_clients' => 0, 'total_posts' => 0, 'published_posts' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar-transition {
            transition: all 0.3s ease;
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        /* Styles pour le mode sombre */
        .theme-dark {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #475569;
            --sidebar-bg: #1e293b;
            --card-bg: #334155;
        }
        
        /* Application globale du mode sombre */
        .theme-dark,
        .theme-dark * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        .theme-dark body {
            background-color: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }
        
        .theme-dark .bg-gray-50 {
            background-color: var(--bg-primary) !important;
        }
        
        .theme-dark .bg-white {
            background-color: var(--card-bg) !important;
            color: var(--text-primary) !important;
        }
        
        .theme-dark .bg-blue-100 {
            background-color: var(--sidebar-bg) !important;
        }
        
        .theme-dark .bg-blue-200 {
            background-color: var(--bg-tertiary) !important;
        }
        
        .theme-dark .text-gray-900 {
            color: var(--text-primary) !important;
        }
        
        .theme-dark .text-gray-700 {
            color: var(--text-secondary) !important;
        }
        
        .theme-dark .text-gray-500 {
            color: var(--text-muted) !important;
        }
        
        .theme-dark .text-blue-800 {
            color: var(--text-primary) !important;
        }
        
        .theme-dark .text-blue-700 {
            color: var(--text-secondary) !important;
        }
        
        .theme-dark .text-blue-900 {
            color: var(--text-primary) !important;
        }
        
        .theme-dark .border-gray-300 {
            border-color: var(--border-color) !important;
        }
        
        .theme-dark .bg-green-600 {
            background-color: #059669 !important;
        }
        
        .theme-dark .bg-green-700 {
            background-color: #047857 !important;
        }
        
        .theme-dark .hover\\:bg-green-700:hover {
            background-color: #047857 !important;
        }
        
        .theme-dark .from-blue-500 {
            --tw-gradient-from: #3b82f6 !important;
        }
        
        .theme-dark .to-blue-600 {
            --tw-gradient-to: #2563eb !important;
        }
        
        .theme-dark .from-green-600 {
            --tw-gradient-from: #059669 !important;
        }
        
        .theme-dark .to-blue-600 {
            --tw-gradient-to: #2563eb !important;
        }
        
        /* Styles spécifiques pour les éléments de formulaire */
        .theme-dark input,
        .theme-dark select,
        .theme-dark textarea {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        .theme-dark input:focus,
        .theme-dark select:focus,
        .theme-dark textarea:focus {
            border-color: #059669 !important;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1) !important;
        }
        
        /* Styles pour les boutons */
        .theme-dark button {
            background-color: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        .theme-dark button:hover {
            background-color: var(--bg-secondary) !important;
        }
        
        /* Styles pour les cartes et conteneurs */
        .theme-dark .shadow-sm {
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important;
        }
        
        .theme-dark .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-green-600 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($cm['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?></p>
                        <p class="text-xs text-gray-500">Community Manager</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Mes Clients
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="drafts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-edit mr-3"></i>
                    Brouillons
                </a>
                <a href="scheduled.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Programmé
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Analytics
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg relative">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $unread_notifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-cog mr-3 text-green-600"></i>
                    Paramètres
                </a>
            </div>
        </nav>
        
        <div class="absolute bottom-0 w-full p-4">
            <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-sign-out-alt mr-3"></i>
                Déconnexion
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Paramètres</h1>
                    <p class="text-sm text-gray-600">Gérez vos préférences et informations personnelles</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600 relative">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                                <?php echo $unread_notifications; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?php display_flash_message(); ?>
            
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques du CM -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients Assignés</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-newspaper text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publications Créées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publications Publiées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['published_posts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Profil -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Informations personnelles</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                                    <input type="text" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($cm['first_name']); ?>" 
                                           required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                                    <input type="text" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($cm['last_name']); ?>" 
                                           required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Adresse email *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($cm['email']); ?>" 
                                       required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($cm['phone']); ?>" 
                                       placeholder="+33 6 12 34 56 78"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-6 w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-save mr-2"></i>Mettre à jour le profil
                        </button>
                    </form>
                </div>

                <!-- Mot de passe -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Changer le mot de passe</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe actuel *</label>
                                <input type="password" id="current_password" name="current_password" 
                                       required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe *</label>
                                <input type="password" id="new_password" name="new_password" 
                                       required minlength="6"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le nouveau mot de passe *</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       required minlength="6"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-6 w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-key mr-2"></i>Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Notifications -->
            <div class="mt-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Préférences de notifications</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Notifications par email</h3>
                                    <p class="text-sm text-gray-500">Recevez des notifications par email</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="email_notifications" 
                                           <?php echo (isset($cm['email_notifications']) && $cm['email_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Notifications push</h3>
                                    <p class="text-sm text-gray-500">Recevez des notifications push dans le navigateur</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="push_notifications" 
                                           <?php echo (isset($cm['push_notifications']) && $cm['push_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Notifications SMS</h3>
                                    <p class="text-sm text-gray-500">Recevez des notifications par SMS</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="sms_notifications" 
                                           <?php echo (isset($cm['sms_notifications']) && $cm['sms_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-6 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-bell mr-2"></i>Mettre à jour les notifications
                        </button>
                    </form>
                </div>
            </div>

            <!-- Préférences d'affichage -->
            <div class="mt-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Préférences d'affichage</h2>
                    
                    <div class="space-y-6">
                        <!-- Langue -->
                        <div>
                            <label for="language" class="block text-sm font-medium text-gray-700 mb-2">Langue</label>
                            <select id="language" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="fr">Français</option>
                                <option value="en">English</option>
                                <option value="es">Español</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Choisissez votre langue préférée pour l'interface</p>
                        </div>
                        
                        <!-- Mode d'éclairage -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mode d'éclairage</label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="radio" name="theme_mode" value="light" class="mr-3 text-green-600 focus:ring-green-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-sun text-yellow-500 mr-2"></i>
                                        <span class="text-sm text-gray-700">Mode clair</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="radio" name="theme_mode" value="dark" class="mr-3 text-green-600 focus:ring-green-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-moon text-indigo-500 mr-2"></i>
                                        <span class="text-sm text-gray-700">Mode sombre</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="radio" name="theme_mode" value="auto" class="mr-3 text-green-600 focus:ring-green-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-adjust text-gray-500 mr-2"></i>
                                        <span class="text-sm text-gray-700">Automatique (suit les préférences système)</span>
                                    </div>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Choisissez le mode d'éclairage qui vous convient le mieux</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations du compte -->
            <div class="mt-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Informations du compte</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-2">Rôle</h3>
                            <p class="text-sm text-gray-900"><?php echo ucfirst($cm['role']); ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-2">Membre depuis</h3>
                            <p class="text-sm text-gray-900"><?php echo format_date_fr($cm['created_at'], 'd/m/Y'); ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-2">Dernière connexion</h3>
                            <p class="text-sm text-gray-900">
                                <?php echo $cm['last_login'] ? format_date_fr($cm['last_login'], 'd/m/Y H:i') : 'Jamais'; ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-2">Statut</h3>
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $cm['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($cm['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Validation du formulaire de changement de mot de passe
        document.querySelector('form[action=""]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Les nouveaux mots de passe ne correspondent pas.');
            }
        });

        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessages = document.querySelectorAll('[role="alert"]');
            flashMessages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 500);
            });
        }, 5000);

        // Gestion des préférences d'affichage
        class DisplayPreferences {
            constructor() {
                this.init();
            }

            init() {
                this.loadPreferences();
                this.bindEvents();
                this.applyTheme();
            }

            loadPreferences() {
                // Charger les préférences depuis localStorage
                const savedLanguage = localStorage.getItem('user_language') || 'fr';
                const savedTheme = localStorage.getItem('user_theme') || 'light';

                // Appliquer les valeurs aux contrôles
                const languageSelect = document.getElementById('language');
                const themeRadios = document.querySelectorAll('input[name="theme_mode"]');

                if (languageSelect) {
                    languageSelect.value = savedLanguage;
                }

                themeRadios.forEach(radio => {
                    if (radio.value === savedTheme) {
                        radio.checked = true;
                    }
                });
            }

            bindEvents() {
                // Événement pour la langue
                const languageSelect = document.getElementById('language');
                if (languageSelect) {
                    languageSelect.addEventListener('change', (e) => {
                        localStorage.setItem('user_language', e.target.value);
                        this.showNotification('Langue mise à jour !');
                    });
                }

                // Événements pour le thème
                const themeRadios = document.querySelectorAll('input[name="theme_mode"]');
                themeRadios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        localStorage.setItem('user_theme', e.target.value);
                        this.applyTheme();
                        this.showNotification('Mode d\'éclairage mis à jour !');
                    });
                });
            }

            applyTheme() {
                const theme = localStorage.getItem('user_theme') || 'light';
                const body = document.body;
                const html = document.documentElement;

                // Supprimer les classes de thème existantes
                body.classList.remove('theme-light', 'theme-dark');
                html.classList.remove('theme-light', 'theme-dark');

                let actualTheme = theme;
                if (theme === 'auto') {
                    // Détecter la préférence système
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    actualTheme = prefersDark ? 'dark' : 'light';
                }

                // Appliquer la classe au body et html
                body.classList.add(`theme-${actualTheme}`);
                html.classList.add(`theme-${actualTheme}`);
                
                // Forcer le re-rendu
                body.style.display = 'none';
                body.offsetHeight; // Trigger reflow
                body.style.display = '';
                
                console.log(`Thème appliqué: ${actualTheme}`);
            }

            showNotification(message) {
                // Créer une notification temporaire
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        }

        // Initialiser les préférences d'affichage
        new DisplayPreferences();
    </script>
</body>
</html>
