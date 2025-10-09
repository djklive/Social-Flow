<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('admin');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Récupérer les données de l'administrateur
try {
    $db = getDB();
    
    // Informations de l'admin
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();
    
    // Statistiques globales
    $stats = [];
    
    // Total utilisateurs par rôle
    $stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
    $stmt->execute();
    $role_stats = $stmt->fetchAll();
    
    foreach ($role_stats as $stat) {
        $stats[$stat['role']] = $stat['count'];
    }
    
    // Total publications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM posts");
    $stmt->execute();
    $stats['total_posts'] = $stmt->fetch()['total'];
    
    // Publications par statut
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM posts GROUP BY status");
    $stmt->execute();
    $post_status_stats = $stmt->fetchAll();
    
    // Revenus (simulation)
    $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch()['total_revenue'] ?: 0;
    
    // Abonnements actifs
    $stmt = $db->prepare("SELECT COUNT(*) as active_subs FROM subscriptions WHERE status = 'active'");
    $stmt->execute();
    $stats['active_subscriptions'] = $stmt->fetch()['active_subs'];
    
    // Utilisateurs récents
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, role, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Publications récentes
    $stmt = $db->prepare("
        SELECT p.*, u1.first_name as client_first_name, u1.last_name as client_last_name,
               u2.first_name as cm_first_name, u2.last_name as cm_last_name
        FROM posts p 
        INNER JOIN users u1 ON p.client_id = u1.id 
        INNER JOIN users u2 ON p.community_manager_id = u2.id 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_posts = $stmt->fetchAll();
    
    // Activité récente
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 15
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
    // Fonction pour traduire les actions en français
    function translate_action($action) {
        $translations = [
            'login' => 'Connexion',
            'account_created' => 'Compte créé',
            'subscription_created' => 'Abonnement créé',
            'assignment_created' => 'Assignation créée',
            'user_created' => 'Utilisateur créé',
            'post_created' => 'Publication créée',
            'failed_login_attempt' => 'Tentative de connexion échouée',
            'user_login' => 'Connexion utilisateur',
            'logout' => 'Déconnexion',
            'profile_updated' => 'Profil mis à jour',
            'password_changed' => 'Mot de passe modifié',
            'settings_updated' => 'Paramètres mis à jour'
        ];
        
        return $translations[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur dashboard admin: " . $e->getMessage());
    $admin = null;
    $stats = ['clients' => 0, 'community_manager' => 0, 'admin' => 0, 'total_posts' => 0, 'total_revenue' => 0, 'active_subscriptions' => 0];
    $recent_users = [];
    $recent_posts = [];
    $recent_activity = [];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stats-card {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 1px solid #93c5fd;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e0;
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            margin-top: 8px;
        }
        .stats-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            margin-top: 4px;
        }
        .section-card {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #93c5fd;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color:rgb(113, 129, 173);
            margin-bottom: 24px;
        }
    </style>
</head>
<body class="bg-blue-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-blue-100 shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-blue-500 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-900"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></p>
                        <p class="text-xs text-blue-700">Administrateur</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-200 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3 text-blue-800"></i>
                    Dashboard
                </a>
                <a href="users.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>
                <a href="assignments.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-link mr-3"></i>
                    Assignations
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="subscriptions.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnements
                </a>
                <a href="payments.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Paiements
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Analytics
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg relative">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $unread_notifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-cog mr-3"></i>
                    Paramètres
                </a>
            </div>
        </nav>
        
        <div class="absolute bottom-0 w-full p-4">
            <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                <i class="fas fa-sign-out-alt mr-3"></i>
                Déconnexion
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64">
        <!-- Top Navigation -->
        <header class="bg-blue-200 shadow-sm border-b border-blue-300">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-2xl font-semibold text-blue-900">Dashboard Administrateur</h1>
                    <p class="text-sm text-blue-700">Vue d'ensemble du système</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <button class="p-2 text-blue-600 hover:text-blue-800 relative">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
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
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                <div class="stats-card">
                    <div class="stats-icon bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number text-blue-600"><?php echo $stats['client'] ?? 0; ?></div>
                    <div class="stats-label">Total Clients</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stats-number text-emerald-600"><?php echo $stats['community_manager'] ?? 0; ?></div>
                    <div class="stats-label">Community Managers</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon bg-gradient-to-br from-purple-500 to-purple-600 text-white">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stats-number text-purple-600"><?php echo $stats['total_posts'] ?? 0; ?></div>
                    <div class="stats-label">Total Publications</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon bg-gradient-to-br from-amber-500 to-orange-500 text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number text-amber-600"><?php echo number_format($stats['total_revenue'] ?? 0); ?></div>
                    <div class="stats-label">Revenus Totaux (FCFA)</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Utilisateurs récents -->
                <div class="lg:col-span-1">
                    <div class="section-card">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="section-title">Utilisateurs récents</h3>
                            <a href="users.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                Voir tout <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                        
                        <?php if (!empty($recent_users)): ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($recent_users, 0, 5) as $user): ?>
                                    <div class="flex items-center p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                            <span class="text-white font-bold text-sm">
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <p class="font-semibold text-gray-900 text-sm">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-600 mt-1">
                                                <?php echo ucfirst($user['role']); ?> • 
                                                <?php echo time_ago($user['created_at']); ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-800 font-medium">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">Aucun utilisateur</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Activité récente -->
                    <div class="section-card mt-8">
                        <h3 class="section-title">Activité récente</h3>
                        <?php if (!empty($recent_activity)): ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($recent_activity, 0, 6) as $activity): ?>
                                    <div class="flex items-start p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                        <div class="w-3 h-3 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mt-1 mr-4 shadow-sm"></div>
                                        <div class="flex-1">
                                            <p class="text-sm text-gray-900 font-medium">
                                                <span class="text-blue-600">
                                                    <?php if ($activity['first_name']): ?>
                                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                    <?php else: ?>
                                                        Système
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-gray-700 ml-1">
                                                    <?php echo htmlspecialchars(translate_action($activity['action'])); ?>
                                                </span>
                                            </p>
                                            <?php if (!empty($activity['details'])): ?>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    <?php echo htmlspecialchars($activity['details']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-500 mt-1 flex items-center">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo time_ago($activity['created_at']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500 text-sm">Aucune activité récente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Publications récentes -->
                <div class="lg:col-span-2">
                    <div class="section-card">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="section-title">Publications récentes</h3>
                            <a href="posts.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                Voir tout <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                        
                        <?php if (!empty($recent_posts)): ?>
                            <div class="space-y-6">
                                <?php foreach (array_slice($recent_posts, 0, 4) as $post): ?>
                                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-3">
                                                    <h4 class="font-semibold text-gray-900 mr-3 text-lg">
                                                        <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                                    </h4>
                                                    <?php
                                                    $status_colors = [
                                                        'published' => 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200',
                                                        'scheduled' => 'bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 border border-purple-200',
                                                        'draft' => 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 border border-gray-300',
                                                        'failed' => 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200'
                                                    ];
                                                    $status_color = $status_colors[$post['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="px-3 py-1 text-xs rounded-full font-medium <?php echo $status_color; ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-700 text-sm mb-4 line-clamp-2 leading-relaxed">
                                                    <?php echo htmlspecialchars(substr($post['content'], 0, 120)) . (strlen($post['content']) > 120 ? '...' : ''); ?>
                                                </p>
                                                <div class="flex items-center text-xs text-gray-600 space-x-4">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?php echo htmlspecialchars($post['client_first_name'] . ' ' . $post['client_last_name']); ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-user-tie mr-1"></i>
                                                        <?php echo htmlspecialchars($post['cm_first_name'] . ' ' . $post['cm_last_name']); ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php echo time_ago($post['created_at']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-6">
                                                <a href="posts.php?action=view&id=<?php echo $post['id']; ?>" class="inline-flex items-center justify-center w-10 h-10 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-full transition-colors duration-200">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">Aucune publication pour le moment</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
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
