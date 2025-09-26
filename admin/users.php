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
            case 'create_user':
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = sanitize_input($_POST['role'] ?? '');
                
                // Validation
                if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
                    $error_message = 'Tous les champs sont obligatoires.';
                    break;
                }
                
                if (!validate_email($email)) {
                    $error_message = 'Adresse email invalide.';
                    break;
                }
                
                if (!empty($phone) && !validate_phone($phone)) {
                    $error_message = 'Numéro de téléphone invalide.';
                    break;
                }
                
                if (!in_array($role, ['client', 'community_manager', 'admin'])) {
                    $error_message = 'Rôle invalide.';
                    break;
                }
                
                // Vérifier si l'email existe déjà
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_message = 'Cette adresse email est déjà utilisée.';
                    break;
                }
                
                // Créer l'utilisateur
                $hashed_password = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role]);
                
                // Logger l'activité
                log_activity($user_id, 'user_created', "Utilisateur créé: $email ($role)");
                
                $success_message = 'Utilisateur créé avec succès.';
                break;
                
            case 'update_user':
                $user_id_to_update = (int)($_POST['user_id'] ?? 0);
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $status = sanitize_input($_POST['status'] ?? 'active');
                
                if ($user_id_to_update > 0) {
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $role, $status, $user_id_to_update]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'user_updated', "Utilisateur modifié: ID $user_id_to_update");
                    
                    $success_message = 'Utilisateur modifié avec succès.';
                }
                break;
                
            case 'delete_user':
                $user_id_to_delete = (int)($_POST['user_id'] ?? 0);
                if ($user_id_to_delete > 0 && $user_id_to_delete != $user_id) {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id_to_delete]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'user_deleted', "Utilisateur supprimé: ID $user_id_to_delete");
                    
                    $success_message = 'Utilisateur supprimé avec succès.';
                } else {
                    $error_message = 'Vous ne pouvez pas supprimer votre propre compte.';
                }
                break;
                
            case 'reset_password':
                $user_id_to_reset = (int)($_POST['user_id'] ?? 0);
                $new_password = $_POST['new_password'] ?? '';
                
                if ($user_id_to_reset > 0 && !empty($new_password)) {
                    $hashed_password = hash_password($new_password);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id_to_reset]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'password_reset', "Mot de passe réinitialisé: ID $user_id_to_reset");
                    
                    $success_message = 'Mot de passe réinitialisé avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur gestion utilisateurs: " . $e->getMessage());
    }
}

// Récupérer les données
try {
    $db = getDB();
    
    // Informations de l'admin
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();
    
    // Paramètres de filtrage
    $role_filter = $_GET['role'] ?? 'all';
    $status_filter = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($role_filter !== 'all') {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Utilisateurs avec filtres
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM posts WHERE client_id = u.id) as post_count,
               (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id) as subscription_count
        FROM users u 
        WHERE $where_clause
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'client' THEN 1 END) as total_clients,
            COUNT(CASE WHEN role = 'community_manager' THEN 1 END) as total_cms,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users
        FROM users
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données utilisateurs: " . $e->getMessage());
    $admin = null;
    $users = [];
    $stats = ['total_users' => 0, 'total_clients' => 0, 'total_cms' => 0, 'total_admins' => 0, 'active_users' => 0, 'inactive_users' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - SocialFlow</title>
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
        .user-card {
            transition: all 0.2s ease;
        }
        .user-card:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-orange-600 to-red-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
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
                <a href="users.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-users mr-3 text-orange-600"></i>
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
                <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-cog mr-3"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Gestion des Utilisateurs</h1>
                    <p class="text-sm text-gray-600">Gérez tous les utilisateurs du système</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openCreateUserModal()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouvel Utilisateur
                    </button>
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-download text-lg"></i>
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
            
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Utilisateurs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_users']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-user text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-user-tie text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Community Managers</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_cms']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-user-shield text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Administrateurs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_admins']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_users']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Inactifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['inactive_users']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher par nom ou email..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="role" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>Tous les rôles</option>
                            <option value="client" <?php echo $role_filter === 'client' ? 'selected' : ''; ?>>Client</option>
                            <option value="community_manager" <?php echo $role_filter === 'community_manager' ? 'selected' : ''; ?>>Community Manager</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="users.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($users)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Publications</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abonnements</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé le</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="user-card">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $role_colors = [
                                                'client' => 'bg-purple-100 text-purple-800',
                                                'community_manager' => 'bg-green-100 text-green-800',
                                                'admin' => 'bg-orange-100 text-orange-800'
                                            ];
                                            $role_color = $role_colors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $role_color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'inactive' => 'bg-red-100 text-red-800'
                                            ];
                                            $status_color = $status_colors[$user['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $user['post_count']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $user['subscription_count']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo format_date_fr($user['created_at'], 'd/m/Y'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-700" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="openResetPasswordModal(<?php echo $user['id']; ?>)" 
                                                        class="text-yellow-600 hover:text-yellow-700" title="Réinitialiser mot de passe">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($user['id'] != $user_id): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-700" title="Supprimer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun utilisateur trouvé</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                                Aucun utilisateur ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucun utilisateur dans le système.
                            <?php endif; ?>
                        </p>
                        <button onclick="openCreateUserModal()" class="mt-4 inline-block bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Créer un utilisateur
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Créer Utilisateur -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Créer un nouvel utilisateur</h3>
                    <form method="POST" id="createUserForm">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" id="first_name" name="first_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" id="last_name" name="last_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone (optionnel)</label>
                            <input type="tel" id="phone" name="phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                            <select id="role" name="role" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Sélectionner un rôle</option>
                                <option value="client">Client</option>
                                <option value="community_manager">Community Manager</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                            <input type="password" id="password" name="password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeCreateUserModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                                <i class="fas fa-plus mr-2"></i>Créer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Utilisateur -->
    <div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Modifier l'utilisateur</h3>
                    <form method="POST" id="editUserForm">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="edit_first_name" class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" id="edit_first_name" name="first_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="edit_last_name" class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" id="edit_last_name" name="last_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="edit_email" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_phone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="edit_phone" name="phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                            <select id="edit_role" name="role" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="client">Client</option>
                                <option value="community_manager">Community Manager</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="active">Actif</option>
                                <option value="inactive">Inactif</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeEditUserModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                                <i class="fas fa-save mr-2"></i>Modifier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Réinitialiser Mot de Passe -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Réinitialiser le mot de passe</h3>
                    <form method="POST" id="resetPasswordForm">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        
                        <div class="mb-6">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeResetPasswordModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                                <i class="fas fa-key mr-2"></i>Réinitialiser
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
            document.getElementById('createUserForm').reset();
        }

        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        function openResetPasswordModal(userId) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
        }

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
    </script>
</body>
</html>
