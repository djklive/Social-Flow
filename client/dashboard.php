<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('client');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Récupérer les données du client
try {
    $db = getDB();
    
    // Informations du client
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch();
    
    // Abonnement actuel
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE client_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $subscription = $stmt->fetch();
    
    // Community Manager assigné
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.community_manager_id 
        WHERE ca.client_id = ? AND ca.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $community_manager = $stmt->fetch();
    
    // Publications récentes
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.community_manager_id = u.id 
        WHERE p.client_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_posts = $stmt->fetchAll();
    
    // Statistiques globales
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts
        FROM posts 
        WHERE client_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Statistiques d'engagement (simulation)
    $engagement_stats = [
        'total_likes' => 0,
        'total_shares' => 0,
        'total_comments' => 0,
        'total_reach' => 0
    ];
    
    if ($stats['published_posts'] > 0) {
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_likes')), 0) as total_likes,
                COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_shares')), 0) as total_shares,
                COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_comments')), 0) as total_comments,
                COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_reach')), 0) as total_reach
            FROM posts 
            WHERE client_id = ? AND status = 'published'
        ");
        $stmt->execute([$user_id]);
        $engagement_data = $stmt->fetch();
        
        if ($engagement_data) {
            $engagement_stats = [
                'total_likes' => $engagement_data['total_likes'] ?: 0,
                'total_shares' => $engagement_data['total_shares'] ?: 0,
                'total_comments' => $engagement_data['total_comments'] ?: 0,
                'total_reach' => $engagement_data['total_reach'] ?: 0
            ];
        }
    }
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur dashboard client: " . $e->getMessage());
    $client = $community_manager = $subscription = null;
    $recent_posts = [];
    $stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0];
    $engagement_stats = ['total_likes' => 0, 'total_shares' => 0, 'total_comments' => 0, 'total_reach' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Client - SocialFlow</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-purple-600 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($client['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></p>
                        <p class="text-xs text-gray-500">Client</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-purple-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3 text-purple-600"></i>
                    Dashboard
                </a>
                <a href="publications.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Mes Publications
                </a>
                <a href="statistics.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Statistiques
                </a>
                <a href="subscription.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnement
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg relative">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $unread_notifications; ?>
                        </span>
                    <?php endif; ?>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-600">Bienvenue, <?php echo htmlspecialchars($client['first_name']); ?> !</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search text-lg"></i>
                    </button>
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
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-newspaper text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Publications</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publiées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['published_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Programmées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['scheduled_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-heart text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Likes</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($engagement_stats['total_likes']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Community Manager Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Votre Community Manager</h3>
                        <?php if ($community_manager): ?>
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                                    <span class="text-white font-semibold">
                                        <?php echo strtoupper(substr($community_manager['first_name'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <p class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($community_manager['first_name'] . ' ' . $community_manager['last_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">Community Manager</p>
                                </div>
                            </div>
                            <div class="space-y-2 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope mr-2"></i>
                                    <?php echo htmlspecialchars($community_manager['email']); ?>
                                </div>
                                <?php if ($community_manager['phone']): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-phone mr-2"></i>
                                        <?php echo htmlspecialchars($community_manager['phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-plus text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">Aucun Community Manager assigné</p>
                                <p class="text-sm text-gray-400">Contactez l'administrateur</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Abonnement Info -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Abonnement</h3>
                        <?php if ($subscription): ?>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Plan:</span>
                                    <span class="font-medium">
                                        <?php echo $subscription['plan_type'] === 'monthly' ? 'Mensuel' : 'Annuel'; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Prix:</span>
                                    <span class="font-medium"><?php echo number_format($subscription['price'], 2); ?> €</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Statut:</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <?php echo ucfirst($subscription['status']); ?>
                                    </span>
                                </div>
                                <?php if ($subscription['end_date']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Expire le:</span>
                                        <span class="font-medium"><?php echo format_date_fr($subscription['end_date'], 'd/m/Y'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="subscription.php" class="mt-4 block w-full text-center bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 transition duration-300">
                                Gérer l'abonnement
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-credit-card text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">Aucun abonnement actif</p>
                                <a href="subscription.php" class="mt-2 block w-full text-center bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 transition duration-300">
                                    Souscrire un abonnement
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Publications récentes -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Publications récentes</h3>
                            <a href="publications.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        
                        <?php if (!empty($recent_posts)): ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_posts as $post): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-900 mb-2">
                                                    <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                                </h4>
                                                <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                                    <?php echo htmlspecialchars(substr($post['content'], 0, 150)) . (strlen($post['content']) > 150 ? '...' : ''); ?>
                                                </p>
                                                <div class="flex items-center text-xs text-gray-500">
                                                    <span class="px-2 py-1 rounded-full bg-gray-100 mr-2">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                    <span>Par <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                                    <span class="mx-2">•</span>
                                                    <span><?php echo time_ago($post['created_at']); ?></span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <?php
                                                $status_colors = [
                                                    'published' => 'bg-green-100 text-green-800',
                                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                    'draft' => 'bg-gray-100 text-gray-800',
                                                    'failed' => 'bg-red-100 text-red-800'
                                                ];
                                                $status_color = $status_colors[$post['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                    <?php echo ucfirst($post['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">Aucune publication pour le moment</p>
                                <p class="text-sm text-gray-400">Votre Community Manager créera bientôt du contenu pour vous</p>
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
