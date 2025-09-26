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
    
    // Paramètres de filtrage
    $client_filter = $_GET['client_id'] ?? 'all';
    $date_range = $_GET['date_range'] ?? '30_days';
    $platform_filter = $_GET['platform'] ?? 'all';
    
    // Calculer les dates selon la période
    $date_condition = '';
    switch ($date_range) {
        case '7_days':
            $date_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30_days':
            $date_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90_days':
            $date_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case '1_year':
            $date_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
    
    // Construire la requête avec filtres
    $where_conditions = ["p.community_manager_id = ?", "p.status = 'published'"];
    $params = [$user_id];
    
    if ($client_filter !== 'all') {
        $where_conditions[] = "p.client_id = ?";
        $params[] = $client_filter;
    }
    
    if ($platform_filter !== 'all') {
        $where_conditions[] = "p.platforms LIKE ?";
        $params[] = "%$platform_filter%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Statistiques globales
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            COUNT(DISTINCT p.client_id) as total_clients,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_likes')), 0) as total_likes,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_shares')), 0) as total_shares,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_comments')), 0) as total_comments,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_reach')), 0) as total_reach
        FROM posts p 
        WHERE $where_clause $date_condition
    ");
    $stmt->execute($params);
    $global_stats = $stmt->fetch();
    
    // Statistiques par client
    $stmt = $db->prepare("
        SELECT 
            u.first_name,
            u.last_name,
            COUNT(p.id) as post_count,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_likes')), 0) as total_likes,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_shares')), 0) as total_shares,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_comments')), 0) as total_comments,
            COALESCE(SUM(JSON_EXTRACT(p.engagement_data, '$.total_reach')), 0) as total_reach,
            COALESCE(AVG(JSON_EXTRACT(p.engagement_data, '$.total_likes')), 0) as avg_likes_per_post
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id
        WHERE $where_clause $date_condition
        GROUP BY p.client_id, u.first_name, u.last_name
        ORDER BY total_likes DESC
    ");
    $stmt->execute($params);
    $client_stats = $stmt->fetchAll();
    
    // Statistiques par plateforme
    $stmt = $db->prepare("
        SELECT 
            platforms,
            COUNT(*) as post_count,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_likes')), 0) as total_likes,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_shares')), 0) as total_shares,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_comments')), 0) as total_comments,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_reach')), 0) as total_reach
        FROM posts 
        WHERE $where_clause $date_condition AND platforms IS NOT NULL
        GROUP BY platforms
        ORDER BY post_count DESC
    ");
    $stmt->execute($params);
    $platform_stats = $stmt->fetchAll();
    
    // Statistiques par mois (derniers 12 mois)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as post_count,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_likes')), 0) as total_likes,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_shares')), 0) as total_shares,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_comments')), 0) as total_comments,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_reach')), 0) as total_reach
        FROM posts 
        WHERE $where_clause 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute($params);
    $monthly_stats = $stmt->fetchAll();
    
    // Top publications
    $stmt = $db->prepare("
        SELECT 
            p.title,
            p.content,
            p.platforms,
            p.engagement_data,
            p.created_at,
            u.first_name,
            u.last_name,
            (COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_likes'), 0) + 
             COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_shares'), 0) + 
             COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_comments'), 0)) as total_engagement
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id
        WHERE $where_clause $date_condition AND p.engagement_data IS NOT NULL
        ORDER BY total_engagement DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $top_posts = $stmt->fetchAll();
    
    // Clients assignés
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$user_id]);
    $clients = $stmt->fetchAll();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur analytics CM: " . $e->getMessage());
    $cm = null;
    $global_stats = ['total_posts' => 0, 'total_clients' => 0, 'total_likes' => 0, 'total_shares' => 0, 'total_comments' => 0, 'total_reach' => 0];
    $client_stats = [];
    $platform_stats = [];
    $monthly_stats = [];
    $top_posts = [];
    $clients = [];
    $unread_notifications = 0;
    
    // Initialiser les variables de filtrage par défaut
    $client_filter = $_GET['client_id'] ?? 'all';
    $date_range = $_GET['date_range'] ?? '30_days';
    $platform_filter = $_GET['platform'] ?? 'all';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - SocialFlow</title>
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
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-green-600 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo $cm ? strtoupper(substr($cm['first_name'], 0, 1)) : 'CM'; ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo $cm ? htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']) : 'Community Manager'; ?></p>
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
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3 text-green-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Analytics</h1>
                    <p class="text-sm text-gray-600">Analysez les performances de vos publications</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-download text-lg"></i>
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
            
            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div>
                        <select name="date_range" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="7_days" <?php echo $date_range === '7_days' ? 'selected' : ''; ?>>7 derniers jours</option>
                            <option value="30_days" <?php echo $date_range === '30_days' ? 'selected' : ''; ?>>30 derniers jours</option>
                            <option value="90_days" <?php echo $date_range === '90_days' ? 'selected' : ''; ?>>90 derniers jours</option>
                            <option value="1_year" <?php echo $date_range === '1_year' ? 'selected' : ''; ?>>1 an</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="client_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all" <?php echo $client_filter === 'all' ? 'selected' : ''; ?>>Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client_filter == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="platform" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all" <?php echo $platform_filter === 'all' ? 'selected' : ''; ?>>Toutes les plateformes</option>
                            <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="twitter" <?php echo $platform_filter === 'twitter' ? 'selected' : ''; ?>>Twitter</option>
                            <option value="linkedin" <?php echo $platform_filter === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="tiktok" <?php echo $platform_filter === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Appliquer
                    </button>
                </form>
            </div>
            
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-newspaper text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publications</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['total_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-users text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['total_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-heart text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Likes</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($global_stats['total_likes']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-share text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Partages</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($global_stats['total_shares']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-comment text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Commentaires</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($global_stats['total_comments']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100">
                            <i class="fas fa-eye text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Portée</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($global_stats['total_reach']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Graphique des publications par mois -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Publications par mois</h3>
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Graphique des plateformes -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition par plateforme</h3>
                    <canvas id="platformChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Performance par client -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance par client</h3>
                    <?php if (!empty($client_stats)): ?>
                        <div class="space-y-4">
                            <?php foreach ($client_stats as $client): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                        </h4>
                                        <span class="text-sm text-gray-500"><?php echo $client['post_count']; ?> publications</span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($client['total_likes']); ?></div>
                                            <div class="text-gray-500">Likes</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($client['total_shares']); ?></div>
                                            <div class="text-gray-500">Partages</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($client['total_comments']); ?></div>
                                            <div class="text-gray-500">Commentaires</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($client['avg_likes_per_post'], 1); ?></div>
                                            <div class="text-gray-500">Likes/post</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-bar text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-500">Aucune donnée disponible</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top publications -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top publications</h3>
                    <?php if (!empty($top_posts)): ?>
                        <div class="space-y-4">
                            <?php foreach ($top_posts as $index => $post): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center">
                                            <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                                                <?php echo $index + 1; ?>
                                            </span>
                                            <h4 class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                            </h4>
                                        </div>
                                        <span class="text-sm text-gray-500">
                                            <?php echo number_format($post['total_engagement']); ?> engagements
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <?php echo htmlspecialchars(substr($post['content'], 0, 100)) . (strlen($post['content']) > 100 ? '...' : ''); ?>
                                    </p>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <span>Client: <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?php echo format_date_fr($post['created_at'], 'd/m/Y'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-trophy text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-500">Aucune publication avec engagement</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Graphique des publications par mois
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_stats); ?>;
        
        if (monthlyData && monthlyData.length > 0) {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                    }).reverse(),
                    datasets: [{
                        label: 'Publications',
                        data: monthlyData.map(item => item.post_count).reverse(),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        } else {
            // Afficher un message si pas de données
            monthlyCtx.canvas.parentNode.innerHTML = '<div class="text-center py-8"><i class="fas fa-chart-line text-gray-400 text-3xl mb-2"></i><p class="text-gray-500">Aucune donnée disponible</p></div>';
        }

        // Graphique des plateformes
        const platformCtx = document.getElementById('platformChart').getContext('2d');
        const platformData = <?php echo json_encode($platform_stats); ?>;
        
        if (platformData && platformData.length > 0) {
            new Chart(platformCtx, {
                type: 'doughnut',
                data: {
                    labels: platformData.map(item => {
                        try {
                            const platforms = JSON.parse(item.platforms);
                            return Array.isArray(platforms) ? platforms.join(', ') : item.platforms;
                        } catch (e) {
                            return item.platforms || 'Inconnu';
                        }
                    }),
                    datasets: [{
                        data: platformData.map(item => item.post_count),
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        } else {
            // Afficher un message si pas de données
            platformCtx.canvas.parentNode.innerHTML = '<div class="text-center py-8"><i class="fas fa-chart-pie text-gray-400 text-3xl mb-2"></i><p class="text-gray-500">Aucune donnée disponible</p></div>';
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
