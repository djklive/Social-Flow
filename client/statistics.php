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
    
    // Statistiques globales des publications
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_posts
        FROM posts 
        WHERE client_id = ?
    ");
    $stmt->execute([$user_id]);
    $post_stats = $stmt->fetch();
    
    // Statistiques d'engagement globales
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
    $engagement_stats = $stmt->fetch();
    
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
        WHERE client_id = ? AND status = 'published' AND platforms IS NOT NULL
        GROUP BY platforms
        ORDER BY post_count DESC
    ");
    $stmt->execute([$user_id]);
    $platform_stats = $stmt->fetchAll();
    
    // Statistiques par mois (derniers 6 mois)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as post_count,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_likes')), 0) as total_likes,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_shares')), 0) as total_shares,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_comments')), 0) as total_comments,
            COALESCE(SUM(JSON_EXTRACT(engagement_data, '$.total_reach')), 0) as total_reach
        FROM posts 
        WHERE client_id = ? AND status = 'published' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$user_id]);
    $monthly_stats = $stmt->fetchAll();
    
    // Top publications (par engagement)
    $stmt = $db->prepare("
        SELECT 
            title,
            content,
            platforms,
            engagement_data,
            created_at,
            (COALESCE(JSON_EXTRACT(engagement_data, '$.total_likes'), 0) + 
             COALESCE(JSON_EXTRACT(engagement_data, '$.total_shares'), 0) + 
             COALESCE(JSON_EXTRACT(engagement_data, '$.total_comments'), 0)) as total_engagement
        FROM posts 
        WHERE client_id = ? AND status = 'published' AND engagement_data IS NOT NULL
        ORDER BY total_engagement DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $top_posts = $stmt->fetchAll();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur statistiques client: " . $e->getMessage());
    $client = null;
    $post_stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0, 'draft_posts' => 0, 'failed_posts' => 0];
    $engagement_stats = ['total_likes' => 0, 'total_shares' => 0, 'total_comments' => 0, 'total_reach' => 0];
    $platform_stats = [];
    $monthly_stats = [];
    $top_posts = [];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - SocialFlow</title>
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
        .bg-blue-25 {
            background-color: #f0f8ff;
        }
        .content-card {
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            border: 1px solid #b3d9ff;
        }
    </style>
</head>
<body class="bg-blue-25">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-blue-800 to-blue-900">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-700 to-blue-800 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($client['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></p>
                        <p class="text-xs text-gray-500">Client</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="publications.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Mes Publications
                </a>
                <a href="statistics.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3 text-blue-800"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Statistiques</h1>
                    <p class="text-sm text-gray-600">Analysez les performances de vos publications sur les réseaux sociaux</p>
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
            
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-newspaper text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publications</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $post_stats['total_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-heart text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Likes</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($engagement_stats['total_likes']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-share text-blue-800 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Partages</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($engagement_stats['total_shares']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-eye text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Portée</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($engagement_stats['total_reach']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Graphique des publications par mois -->
                <div class="content-card rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Publications par mois</h3>
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Graphique des plateformes -->
                <div class="content-card rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition par plateforme</h3>
                    <canvas id="platformChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Statistiques par plateforme -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance par plateforme</h3>
                    <?php if (!empty($platform_stats)): ?>
                        <div class="space-y-4">
                            <?php foreach ($platform_stats as $platform): ?>
                                <?php
                                $platforms = json_decode($platform['platforms'], true);
                                $platform_name = is_array($platforms) ? implode(', ', $platforms) : $platform['platforms'];
                                ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($platform_name); ?></h4>
                                        <span class="text-sm text-gray-500"><?php echo $platform['post_count']; ?> publications</span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($platform['total_likes']); ?></div>
                                            <div class="text-gray-500">Likes</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($platform['total_shares']); ?></div>
                                            <div class="text-gray-500">Partages</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($platform['total_comments']); ?></div>
                                            <div class="text-gray-500">Commentaires</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900"><?php echo number_format($platform['total_reach']); ?></div>
                                            <div class="text-gray-500">Portée</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-bar text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-500">Aucune donnée de plateforme disponible</p>
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
                                            <span class="w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
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
                                        <i class="fas fa-calendar mr-1"></i>
                                        <span><?php echo format_date_fr($post['created_at'], 'd/m/Y'); ?></span>
                                        <?php if ($post['platforms']): ?>
                                            <span class="mx-2">•</span>
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                                                <?php 
                                                $platforms = json_decode($post['platforms'], true);
                                                echo is_array($platforms) ? implode(', ', $platforms) : $post['platforms'];
                                                ?>
                                            </span>
                                        <?php endif; ?>
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
                    borderColor: 'rgb(139, 92, 246)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
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

        // Graphique des plateformes
        const platformCtx = document.getElementById('platformChart').getContext('2d');
        const platformData = <?php echo json_encode($platform_stats); ?>;
        
        new Chart(platformCtx, {
            type: 'doughnut',
            data: {
                labels: platformData.map(item => {
                    const platforms = JSON.parse(item.platforms);
                    return Array.isArray(platforms) ? platforms.join(', ') : item.platforms;
                }),
                datasets: [{
                    data: platformData.map(item => item.post_count),
                    backgroundColor: [
                        'rgba(139, 92, 246, 0.8)',
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
