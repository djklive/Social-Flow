<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('community_manager');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Récupérer les données du Community Manager
try {
    $db = getDB();
    
    // Informations du CM
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $cm = $stmt->fetch();
    
    // Clients assignés
    $stmt = $db->prepare("
        SELECT u.*, ca.assigned_at 
        FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
        ORDER BY ca.assigned_at DESC
    ");
    $stmt->execute([$user_id]);
    $assigned_clients = $stmt->fetchAll();
    
    // Publications récentes
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id 
        WHERE p.community_manager_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_posts = $stmt->fetchAll();
    
    // Statistiques globales
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts
        FROM posts 
        WHERE community_manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Publications programmées pour aujourd'hui
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id 
        WHERE p.community_manager_id = ? 
        AND p.status = 'scheduled' 
        AND DATE(p.scheduled_at) = CURDATE()
        ORDER BY p.scheduled_at ASC
    ");
    $stmt->execute([$user_id]);
    $today_scheduled = $stmt->fetchAll();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur dashboard CM: " . $e->getMessage());
    $cm = null;
    $assigned_clients = [];
    $recent_posts = [];
    $stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0, 'draft_posts' => 0];
    $today_scheduled = [];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Community Manager - SocialFlow</title>
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
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($cm['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-900"><?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?></p>
                        <p class="text-xs text-blue-700">Community Manager</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-200 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3 text-blue-800"></i>
                    Dashboard
                </a>
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Mes Clients
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="content_proposals.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-paper-plane mr-3"></i>
                    Propositions
                </a>
                <a href="drafts.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-edit mr-3"></i>
                    Brouillons
                </a>
                <a href="scheduled.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Programmé
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
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-600">Bienvenue, <?php echo htmlspecialchars($cm['first_name']); ?> !</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="posts.php?action=create" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Publication
                    </a>
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
                        <div class="p-3 rounded-full bg-gray-100">
                            <i class="fas fa-edit text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Brouillons</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['draft_posts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Clients assignés -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Mes Clients</h3>
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?php echo count($assigned_clients); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($assigned_clients)): ?>
                            <div class="space-y-3">
                                <?php foreach ($assigned_clients as $client): ?>
                                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                        <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($client['first_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <p class="font-medium text-gray-900 text-sm">
                                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                Assigné <?php echo time_ago($client['assigned_at']); ?>
                                            </p>
                                        </div>
                                        <a href="clients.php?id=<?php echo $client['id']; ?>" class="text-green-600 hover:text-green-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="clients.php" class="mt-4 block w-full text-center bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                Voir tous les clients
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">Aucun client assigné</p>
                                <p class="text-sm text-gray-400">Contactez l'administrateur</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Publications d'aujourd'hui -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Aujourd'hui</h3>
                        <?php if (!empty($today_scheduled)): ?>
                            <div class="space-y-3">
                                <?php foreach ($today_scheduled as $post): ?>
                                    <div class="border-l-4 border-yellow-400 pl-4 py-2">
                                        <p class="font-medium text-gray-900 text-sm">
                                            <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo format_date_fr($post['scheduled_at'], 'H:i'); ?> - 
                                            <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-check text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500 text-sm">Aucune publication programmée</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Publications récentes -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Publications récentes</h3>
                            <a href="posts.php" class="text-green-600 hover:text-green-700 text-sm font-medium">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        
                        <?php if (!empty($recent_posts)): ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_posts as $post): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <h4 class="font-medium text-gray-900 mr-2">
                                                        <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                                    </h4>
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
                                                <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                                    <?php echo htmlspecialchars(substr($post['content'], 0, 150)) . (strlen($post['content']) > 150 ? '...' : ''); ?>
                                                </p>
                                                <div class="flex items-center text-xs text-gray-500">
                                                    <span>Client: <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                                    <span class="mx-2">•</span>
                                                    <span><?php echo time_ago($post['created_at']); ?></span>
                                                    <?php if ($post['scheduled_at']): ?>
                                                        <span class="mx-2">•</span>
                                                        <span>Programmé: <?php echo format_date_fr($post['scheduled_at'], 'd/m H:i'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex space-x-2">
                                                <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="posts.php?action=view&id=<?php echo $post['id']; ?>" class="text-green-600 hover:text-green-700">
                                                    <i class="fas fa-eye"></i>
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
                                <a href="posts.php?action=create" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                    <i class="fas fa-plus mr-2"></i>Créer une publication
                                </a>
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
