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
    $status_filter = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["ca.community_manager_id = ?"];
    $params = [$user_id];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "ca.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Clients assignés avec filtres
    $stmt = $db->prepare("
        SELECT u.*, ca.assigned_at, ca.status as assignment_status,
               (SELECT COUNT(*) FROM posts WHERE client_id = u.id) as total_posts,
               (SELECT COUNT(*) FROM posts WHERE client_id = u.id AND status = 'published') as published_posts,
               (SELECT COUNT(*) FROM subscriptions WHERE client_id = u.id AND status = 'active') as active_subscriptions
        FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE $where_clause
        ORDER BY ca.assigned_at DESC
    ");
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
    
    // Statistiques des clients
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_clients,
            SUM(CASE WHEN ca.status = 'active' THEN 1 ELSE 0 END) as active_clients,
            SUM(CASE WHEN ca.status = 'inactive' THEN 1 ELSE 0 END) as inactive_clients
        FROM client_assignments ca 
        WHERE ca.community_manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur clients CM: " . $e->getMessage());
    $cm = null;
    $clients = [];
    $stats = ['total_clients' => 0, 'active_clients' => 0, 'inactive_clients' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Clients - SocialFlow</title>
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
        .client-card {
            transition: all 0.2s ease;
        }
        .client-card:hover {
            background-color: #f8fafc;
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
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-users mr-3 text-green-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Mes Clients</h1>
                    <p class="text-sm text-gray-600">Gérez vos clients assignés et leurs publications</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Clients</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_clients']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gray-100">
                            <i class="fas fa-pause-circle text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients Inactifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['inactive_clients']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher un client..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="clients.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des clients -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($clients)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($clients as $client): ?>
                            <div class="client-card p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-4 flex-1">
                                        <!-- Avatar -->
                                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold">
                                                <?php echo strtoupper(substr($client['first_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Informations du client -->
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                                </h3>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $client['assignment_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo ucfirst($client['assignment_status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-envelope mr-2"></i>
                                                    <?php echo htmlspecialchars($client['email']); ?>
                                                </div>
                                                <?php if ($client['phone']): ?>
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <i class="fas fa-phone mr-2"></i>
                                                        <?php echo htmlspecialchars($client['phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    Assigné <?php echo time_ago($client['assigned_at']); ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Statistiques du client -->
                                            <div class="grid grid-cols-3 gap-4">
                                                <div class="text-center">
                                                    <div class="text-lg font-semibold text-gray-900"><?php echo $client['total_posts']; ?></div>
                                                    <div class="text-xs text-gray-500">Publications</div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="text-lg font-semibold text-gray-900"><?php echo $client['published_posts']; ?></div>
                                                    <div class="text-xs text-gray-500">Publiées</div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="text-lg font-semibold text-gray-900"><?php echo $client['active_subscriptions']; ?></div>
                                                    <div class="text-xs text-gray-500">Abonnements</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center space-x-2 ml-4">
                                        <a href="posts.php?client_id=<?php echo $client['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50" 
                                           title="Voir les publications">
                                            <i class="fas fa-newspaper"></i>
                                        </a>
                                        <a href="posts.php?action=create&client_id=<?php echo $client['id']; ?>" 
                                           class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50" 
                                           title="Créer une publication">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <a href="client-details.php?id=<?php echo $client['id']; ?>" 
                                           class="text-gray-600 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-50" 
                                           title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun client trouvé</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                                Aucun client ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Vous n'avez pas encore de clients assignés.
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
