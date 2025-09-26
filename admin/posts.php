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
            case 'update_post':
                $post_id = (int)($_POST['post_id'] ?? 0);
                $status = sanitize_input($_POST['status'] ?? '');
                
                if ($post_id > 0 && !empty($status)) {
                    $stmt = $db->prepare("UPDATE posts SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $post_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_status_updated', "Statut publication modifié: ID $post_id -> $status");
                    
                    $success_message = 'Statut de la publication modifié avec succès.';
                }
                break;
                
            case 'delete_post':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
                    $stmt->execute([$post_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_deleted', "Publication supprimée: ID $post_id");
                    
                    $success_message = 'Publication supprimée avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur gestion publications admin: " . $e->getMessage());
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
    $status_filter = $_GET['status'] ?? 'all';
    $client_filter = $_GET['client_id'] ?? 'all';
    $cm_filter = $_GET['cm_id'] ?? 'all';
    $platform_filter = $_GET['platform'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($client_filter !== 'all') {
        $where_conditions[] = "p.client_id = ?";
        $params[] = $client_filter;
    }
    
    if ($cm_filter !== 'all') {
        $where_conditions[] = "p.community_manager_id = ?";
        $params[] = $cm_filter;
    }
    
    if ($platform_filter !== 'all') {
        $where_conditions[] = "p.platforms LIKE ?";
        $params[] = "%$platform_filter%";
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR cm.first_name LIKE ? OR cm.last_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Publications avec filtres
    $stmt = $db->prepare("
        SELECT p.*, 
               c.first_name as client_first_name, c.last_name as client_last_name, c.email as client_email,
               cm.first_name as cm_first_name, cm.last_name as cm_last_name, cm.email as cm_email,
               (COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_likes'), 0) + 
                COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_shares'), 0) + 
                COALESCE(JSON_EXTRACT(p.engagement_data, '$.total_comments'), 0)) as total_engagement
        FROM posts p
        INNER JOIN users c ON p.client_id = c.id
        INNER JOIN users cm ON p.community_manager_id = cm.id
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Clients disponibles
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        WHERE u.role = 'client' AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll();
    
    // Community Managers disponibles
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        WHERE u.role = 'community_manager' AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $community_managers = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_posts,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_posts,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_posts,
            COUNT(DISTINCT client_id) as active_clients,
            COUNT(DISTINCT community_manager_id) as active_cms
        FROM posts
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données publications admin: " . $e->getMessage());
    $admin = null;
    $posts = [];
    $clients = [];
    $community_managers = [];
    $stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0, 'draft_posts' => 0, 'failed_posts' => 0, 'active_clients' => 0, 'active_cms' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervision des Publications - SocialFlow</title>
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
                <a href="users.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                </a>
                <a href="assignments.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-user-friends mr-3"></i>
                    Assignations
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3 text-orange-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Supervision des Publications</h1>
                    <p class="text-sm text-gray-600">Surveillez toutes les publications du système</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-download text-lg"></i>
                    </button>
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-filter text-lg"></i>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
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
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Échouées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['failed_posts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-user text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-user-tie text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">CM Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_cms']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher dans les publications..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publié</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Programmé</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="client_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $client_filter === 'all' ? 'selected' : ''; ?>>Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client_filter == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="cm_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $cm_filter === 'all' ? 'selected' : ''; ?>>Tous les CM</option>
                            <?php foreach ($community_managers as $cm): ?>
                                <option value="<?php echo $cm['id']; ?>" <?php echo $cm_filter == $cm['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="platform" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $platform_filter === 'all' ? 'selected' : ''; ?>>Toutes les plateformes</option>
                            <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="twitter" <?php echo $platform_filter === 'twitter' ? 'selected' : ''; ?>>Twitter</option>
                            <option value="linkedin" <?php echo $platform_filter === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="tiktok" <?php echo $platform_filter === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="posts.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
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
                                            <?php if ($post['total_engagement'] > 0): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo number_format($post['total_engagement']); ?> engagements
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-gray-600 mb-4">
                                            <?php echo htmlspecialchars(substr($post['content'], 0, 200)) . (strlen($post['content']) > 200 ? '...' : ''); ?>
                                        </p>
                                        
                                        <div class="flex items-center space-x-6 text-sm text-gray-500 mb-3">
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <span>Client: <?php echo htmlspecialchars($post['client_first_name'] . ' ' . $post['client_last_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-user-tie mr-2"></i>
                                                <span>CM: <?php echo htmlspecialchars($post['cm_first_name'] . ' ' . $post['cm_last_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock mr-2"></i>
                                                <span>Créé <?php echo time_ago($post['created_at']); ?></span>
                                            </div>
                                            <?php if ($post['scheduled_at']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <span>Programmé: <?php echo format_date_fr($post['scheduled_at'], 'd/m/Y H:i'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($post['platforms']): ?>
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
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button onclick="openEditPostModal(<?php echo htmlspecialchars(json_encode($post)); ?>)" 
                                                class="text-blue-600 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50" 
                                                title="Modifier le statut">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette publication ?')">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
                            <?php if (!empty($search) || $status_filter !== 'all' || $client_filter !== 'all' || $cm_filter !== 'all' || $platform_filter !== 'all'): ?>
                                Aucune publication ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucune publication dans le système.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Modifier Publication -->
    <div id="editPostModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Modifier le statut de la publication</h3>
                    <form method="POST" id="editPostForm">
                        <input type="hidden" name="action" value="update_post">
                        <input type="hidden" name="post_id" id="edit_post_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Titre</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_post_title" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_post_client" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Nouveau statut</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="published">Publié</option>
                                <option value="scheduled">Programmé</option>
                                <option value="draft">Brouillon</option>
                                <option value="failed">Échoué</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeEditPostModal()" 
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

    <script>
        function openEditPostModal(post) {
            document.getElementById('edit_post_id').value = post.id;
            document.getElementById('edit_post_title').textContent = post.title || 'Sans titre';
            document.getElementById('edit_post_client').textContent = post.client_first_name + ' ' + post.client_last_name;
            document.getElementById('edit_status').value = post.status;
            document.getElementById('editPostModal').classList.remove('hidden');
        }

        function closeEditPostModal() {
            document.getElementById('editPostModal').classList.add('hidden');
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
