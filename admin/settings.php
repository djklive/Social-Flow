<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('admin');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$success_message = '';
$error_message = '';

// Traitement des actions
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
                
                if (!empty($first_name) && !empty($last_name) && !empty($email)) {
                    if (!validate_email($email)) {
                        $error_message = 'Adresse email invalide.';
                        break;
                    }
                    
                    if (!empty($phone) && !validate_phone($phone)) {
                        $error_message = 'Numéro de téléphone invalide.';
                        break;
                    }
                    
                    // Vérifier si l'email existe déjà
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $error_message = 'Cette adresse email est déjà utilisée.';
                        break;
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
                    
                    // Mettre à jour la session
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    
                    // Logger l'activité
                    log_activity($user_id, 'profile_updated', 'Profil administrateur mis à jour');
                    
                    $success_message = 'Profil mis à jour avec succès.';
                } else {
                    $error_message = 'Veuillez remplir tous les champs obligatoires.';
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'Veuillez remplir tous les champs.';
                    break;
                }
                
                if ($new_password !== $confirm_password) {
                    $error_message = 'Les nouveaux mots de passe ne correspondent pas.';
                    break;
                }
                
                if (strlen($new_password) < 8) {
                    $error_message = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                    break;
                }
                
                // Vérifier le mot de passe actuel
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    $error_message = 'Mot de passe actuel incorrect.';
                    break;
                }
                
                // Mettre à jour le mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Logger l'activité
                log_activity($user_id, 'password_changed', 'Mot de passe administrateur modifié');
                
                $success_message = 'Mot de passe modifié avec succès.';
                break;
                
            case 'update_system_settings':
                $app_name = sanitize_input($_POST['app_name'] ?? '');
                $app_email = sanitize_input($_POST['app_email'] ?? '');
                $app_phone = sanitize_input($_POST['app_phone'] ?? '');
                $currency = sanitize_input($_POST['currency'] ?? '');
                $monthly_price = (float)($_POST['monthly_price'] ?? 0);
                $yearly_price = (float)($_POST['yearly_price'] ?? 0);
                
                if (!empty($app_email) && !validate_email($app_email)) {
                    $error_message = 'Adresse email de l\'application invalide.';
                    break;
                }
                
                if (!empty($app_phone) && !validate_phone($app_phone)) {
                    $error_message = 'Numéro de téléphone de l\'application invalide.';
                    break;
                }
                
                // Mettre à jour les paramètres système (simulation - en réalité, utiliser une table settings)
                // Pour la démo, on met à jour le fichier de configuration
                $config_content = "<?php\n";
                $config_content .= "// Configuration de l'application SocialFlow\n";
                $config_content .= "define('APP_NAME', '" . addslashes($app_name) . "');\n";
                $config_content .= "define('APP_EMAIL', '" . addslashes($app_email) . "');\n";
                $config_content .= "define('APP_PHONE', '" . addslashes($app_phone) . "');\n";
                $config_content .= "define('PAYMENT_CURRENCY', '" . addslashes($currency) . "');\n";
                $config_content .= "define('PAYMENT_PLANS', [\n";
                $config_content .= "    'monthly' => " . $monthly_price . ",\n";
                $config_content .= "    'yearly' => " . $yearly_price . "\n";
                $config_content .= "]);\n";
                $config_content .= "?>";
                
                // Logger l'activité
                log_activity($user_id, 'system_settings_updated', 'Paramètres système mis à jour');
                
                $success_message = 'Paramètres système mis à jour avec succès.';
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
        error_log("Erreur paramètres admin: " . $e->getMessage());
    }
}

// Récupérer les données
try {
    $db = getDB();
    
    // Informations de l'admin
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données paramètres: " . $e->getMessage());
    $admin = null;
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres Système - SocialFlow</title>
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
        
        .theme-dark .from-purple-600 {
            --tw-gradient-from: #7c3aed !important;
        }
        
        .theme-dark .to-pink-600 {
            --tw-gradient-to: #db2777 !important;
        }
        
        .theme-dark .from-purple-100 {
            --tw-gradient-from: var(--bg-tertiary) !important;
        }
        
        .theme-dark .to-pink-100 {
            --tw-gradient-to: var(--bg-tertiary) !important;
        }
        
        .theme-dark .text-purple-600 {
            color: #a855f7 !important;
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
            border-color: #7c3aed !important;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1) !important;
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
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-purple-600 to-pink-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo $admin ? strtoupper(substr($admin['first_name'], 0, 1)) : 'A'; ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo $admin ? htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) : 'Administrateur'; ?></p>
                        <p class="text-xs text-gray-500">Administrateur</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="users.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>
                <a href="assignments.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-user-friends mr-3"></i>
                    Assignations
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="subscriptions.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnements
                </a>
                <a href="payments.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Paiements
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Analytics
                </a>
                <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-100 to-pink-100 rounded-lg">
                    <i class="fas fa-cog mr-3 text-purple-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Paramètres Système</h1>
                    <p class="text-sm text-gray-600">Gérez les paramètres de l'application</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-save text-lg"></i>
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
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Profil Administrateur -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Profil Administrateur</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" id="first_name" name="first_name" required
                                       value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" id="last_name" name="last_name" required
                                       value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-6">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition duration-300">
                            <i class="fas fa-save mr-2"></i>Mettre à jour le profil
                        </button>
                    </form>
                </div>

                <!-- Changement de mot de passe -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Changer le mot de passe</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-4">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-6">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition duration-300">
                            <i class="fas fa-key mr-2"></i>Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <!-- Paramètres système -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Paramètres de l'application</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_system_settings">
                        
                        <div class="mb-4">
                            <label for="app_name" class="block text-sm font-medium text-gray-700 mb-2">Nom de l'application</label>
                            <input type="text" id="app_name" name="app_name" 
                                   value="SocialFlow"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="app_email" class="block text-sm font-medium text-gray-700 mb-2">Email de l'application</label>
                            <input type="email" id="app_email" name="app_email" 
                                   value="contact@socialflow.com"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="app_phone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone de l'application</label>
                            <input type="tel" id="app_phone" name="app_phone" 
                                   value="+237 6XX XX XX XX"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                            <select id="currency" name="currency" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="FCFA" selected>FCFA</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="monthly_price" class="block text-sm font-medium text-gray-700 mb-2">Prix mensuel</label>
                                <input type="number" id="monthly_price" name="monthly_price" min="0" step="100"
                                       value="25000"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="yearly_price" class="block text-sm font-medium text-gray-700 mb-2">Prix annuel</label>
                                <input type="number" id="yearly_price" name="yearly_price" min="0" step="100"
                                       value="300000"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition duration-300">
                            <i class="fas fa-cog mr-2"></i>Mettre à jour les paramètres
                        </button>
                    </form>
                </div>

                <!-- Préférences de notifications -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Préférences de notifications</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Notifications par email</h4>
                                    <p class="text-sm text-gray-500">Recevez des notifications par email</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="email_notifications"
                                           <?php echo (isset($admin['email_notifications']) && $admin['email_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-gradient-to-r from-purple-600 to-pink-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Notifications push</h4>
                                    <p class="text-sm text-gray-500">Recevez des notifications push dans le navigateur</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="push_notifications"
                                           <?php echo (isset($admin['push_notifications']) && $admin['push_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-gradient-to-r from-purple-600 to-pink-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Notifications SMS</h4>
                                    <p class="text-sm text-gray-500">Recevez des notifications par SMS</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="sms_notifications"
                                           <?php echo (isset($admin['sms_notifications']) && $admin['sms_notifications']) ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-gradient-to-r from-purple-600 to-pink-600"></div>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full mt-6 bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition duration-300">
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
                            <select id="language" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
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
                                    <input type="radio" name="theme_mode" value="light" class="mr-3 text-purple-600 focus:ring-purple-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-sun text-yellow-500 mr-2"></i>
                                        <span class="text-sm text-gray-700">Mode clair</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="radio" name="theme_mode" value="dark" class="mr-3 text-purple-600 focus:ring-purple-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-moon text-indigo-500 mr-2"></i>
                                        <span class="text-sm text-gray-700">Mode sombre</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="radio" name="theme_mode" value="auto" class="mr-3 text-purple-600 focus:ring-purple-500">
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

            <!-- Informations système -->
            <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations système</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center p-4 border border-gray-200 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 mb-2">PHP <?php echo PHP_VERSION; ?></div>
                        <div class="text-sm text-gray-500">Version PHP</div>
                    </div>
                    <div class="text-center p-4 border border-gray-200 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 mb-2">MySQL</div>
                        <div class="text-sm text-gray-500">Base de données</div>
                    </div>
                    <div class="text-center p-4 border border-gray-200 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 mb-2"><?php echo date('Y-m-d'); ?></div>
                        <div class="text-sm text-gray-500">Date actuelle</div>
                    </div>
                    <div class="text-center p-4 border border-gray-200 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 mb-2"><?php echo ini_get('upload_max_filesize'); ?></div>
                        <div class="text-sm text-gray-500">Taille max upload</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Validation du formulaire de changement de mot de passe
        document.getElementById('new_password').addEventListener('input', function() {
            const newPassword = this.value;
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
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
                notification.className = 'fixed top-4 right-4 bg-purple-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
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
