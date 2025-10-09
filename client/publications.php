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
    
    // Paramètres de filtrage
    $status_filter = $_GET['status'] ?? 'all';
    $platform_filter = $_GET['platform'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["p.client_id = ?"];
    $params = [$user_id];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($platform_filter !== 'all') {
        $where_conditions[] = "p.platforms LIKE ?";
        $params[] = "%$platform_filter%";
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Publications avec filtres
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.community_manager_id = u.id 
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Statistiques des publications
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
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur publications client: " . $e->getMessage());
    $client = null;
    $posts = [];
    $stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0, 'draft_posts' => 0, 'failed_posts' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Publications - SocialFlow</title>
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
        .post-card {
            transition: all 0.2s ease;
        }
        .post-card:hover {
            background-color: #f8fafc;
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
                <a href="publications.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3 text-blue-800"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Mes Publications</h1>
                    <p class="text-sm text-gray-600">Consultez toutes vos publications créées par votre Community Manager</p>
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
            
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-newspaper text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
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
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
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
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
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
                
                <div class="content-card rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Échecs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['failed_posts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="content-card rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher dans les publications..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publiées</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Programmées</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Brouillons</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Échecs</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="platform" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="all" <?php echo $platform_filter === 'all' ? 'selected' : ''; ?>>Toutes les plateformes</option>
                            <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="twitter" <?php echo $platform_filter === 'twitter' ? 'selected' : ''; ?>>Twitter</option>
                            <option value="linkedin" <?php echo $platform_filter === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="tiktok" <?php echo $platform_filter === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-blue-800 text-white px-6 py-2 rounded-lg hover:bg-blue-900 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="publications.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des publications -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($posts)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($posts as $post): ?>
                            <div class="post-card p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-3">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                            </h3>
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
                                        
                                        <p class="text-gray-600 mb-4">
                                            <?php echo htmlspecialchars($post['content']); ?>
                                        </p>
                                        
                                        <div class="flex items-center space-x-6 text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <span>Par <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock mr-2"></i>
                                                <span><?php echo time_ago($post['created_at']); ?></span>
                                            </div>
                                            <?php if ($post['scheduled_at']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <span>Programmé le <?php echo format_date_fr($post['scheduled_at'], 'd/m/Y H:i'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($post['platforms']): ?>
                                            <div class="mt-3">
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm text-gray-500">Plateformes:</span>
                                                    <?php
                                                    $platforms = json_decode($post['platforms'], true);
                                                    if (is_array($platforms)):
                                                        foreach ($platforms as $platform):
                                                    ?>
                                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                            <?php echo ucfirst($platform); ?>
                                                        </span>
                                                    <?php
                                                        endforeach;
                                                    endif;
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($post['engagement_data']): ?>
                                            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                                <?php
                                                $engagement = json_decode($post['engagement_data'], true);
                                                if (is_array($engagement)):
                                                ?>
                                                    <div class="text-center">
                                                        <div class="text-lg font-semibold text-gray-900">
                                                            <?php echo number_format($engagement['total_likes'] ?? 0); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">Likes</div>
                                                    </div>
                                                    <div class="text-center">
                                                        <div class="text-lg font-semibold text-gray-900">
                                                            <?php echo number_format($engagement['total_shares'] ?? 0); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">Partages</div>
                                                    </div>
                                                    <div class="text-center">
                                                        <div class="text-lg font-semibold text-gray-900">
                                                            <?php echo number_format($engagement['total_comments'] ?? 0); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">Commentaires</div>
                                                    </div>
                                                    <div class="text-center">
                                                        <div class="text-lg font-semibold text-gray-900">
                                                            <?php echo number_format($engagement['total_reach'] ?? 0); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">Portée</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune publication trouvée</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $status_filter !== 'all' || $platform_filter !== 'all'): ?>
                                Aucune publication ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Votre Community Manager n'a pas encore créé de publications pour vous.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
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
