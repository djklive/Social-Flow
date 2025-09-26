<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('community_manager');

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
            case 'publish_now':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW(), scheduled_at = NULL WHERE id = ? AND community_manager_id = ? AND status = 'scheduled'");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'scheduled_post_published', "Publication programmée publiée maintenant: ID $post_id");
                    
                    $success_message = 'Publication publiée immédiatement.';
                }
                break;
                
            case 'reschedule':
                $post_id = (int)($_POST['post_id'] ?? 0);
                $scheduled_at = $_POST['scheduled_at'] ?? '';
                
                if ($post_id > 0 && !empty($scheduled_at)) {
                    $stmt = $db->prepare("UPDATE posts SET scheduled_at = ? WHERE id = ? AND community_manager_id = ? AND status = 'scheduled'");
                    $stmt->execute([$scheduled_at, $post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_rescheduled', "Publication reprogrammée: ID $post_id");
                    
                    $success_message = 'Publication reprogrammée avec succès.';
                }
                break;
                
            case 'move_to_draft':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("UPDATE posts SET status = 'draft', scheduled_at = NULL WHERE id = ? AND community_manager_id = ? AND status = 'scheduled'");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'scheduled_to_draft', "Publication programmée remise en brouillon: ID $post_id");
                    
                    $success_message = 'Publication remise en brouillon.';
                }
                break;
                
            case 'delete':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND community_manager_id = ? AND status = 'scheduled'");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'scheduled_deleted', "Publication programmée supprimée: ID $post_id");
                    
                    $success_message = 'Publication programmée supprimée.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur scheduled CM: " . $e->getMessage());
    }
}

// Récupérer les données
try {
    $db = getDB();
    
    // Informations du CM
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $cm = $stmt->fetch();
    
    // Paramètres de filtrage
    $client_filter = $_GET['client_id'] ?? 'all';
    $date_filter = $_GET['date'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["p.community_manager_id = ?", "p.status = 'scheduled'"];
    $params = [$user_id];
    
    if ($client_filter !== 'all') {
        $where_conditions[] = "p.client_id = ?";
        $params[] = $client_filter;
    }
    
    if ($date_filter !== 'all') {
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = "DATE(p.scheduled_at) = CURDATE()";
                break;
            case 'tomorrow':
                $where_conditions[] = "DATE(p.scheduled_at) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $where_conditions[] = "p.scheduled_at BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)";
                break;
            case 'next_week':
                $where_conditions[] = "p.scheduled_at BETWEEN DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 13 DAY)";
                break;
        }
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Publications programmées avec filtres
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id 
        WHERE $where_clause
        ORDER BY p.scheduled_at ASC
    ");
    $stmt->execute($params);
    $scheduled_posts = $stmt->fetchAll();
    
    // Clients assignés
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$user_id]);
    $clients = $stmt->fetchAll();
    
    // Statistiques des publications programmées
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_scheduled,
            COUNT(CASE WHEN DATE(scheduled_at) = CURDATE() THEN 1 END) as today_scheduled,
            COUNT(CASE WHEN DATE(scheduled_at) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as tomorrow_scheduled,
            COUNT(CASE WHEN scheduled_at < NOW() THEN 1 END) as overdue_scheduled
        FROM posts 
        WHERE community_manager_id = ? AND status = 'scheduled'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données scheduled: " . $e->getMessage());
    $cm = null;
    $scheduled_posts = [];
    $clients = [];
    $stats = ['total_scheduled' => 0, 'today_scheduled' => 0, 'tomorrow_scheduled' => 0, 'overdue_scheduled' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications Programmées - SocialFlow</title>
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
        .scheduled-card {
            transition: all 0.2s ease;
        }
        .scheduled-card:hover {
            background-color: #f8fafc;
        }
        .overdue {
            border-left: 4px solid #ef4444;
            background-color: #fef2f2;
        }
        .today {
            border-left: 4px solid #f59e0b;
            background-color: #fffbeb;
        }
        .upcoming {
            border-left: 4px solid #10b981;
            background-color: #f0fdf4;
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
                <a href="scheduled.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-calendar-alt mr-3 text-green-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Publications Programmées</h1>
                    <p class="text-sm text-gray-600">Gérez vos publications programmées pour l'avenir</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="posts.php?action=create" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Publication
                    </a>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-calendar-alt text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Programmées</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-calendar-day text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['today_scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-calendar-plus text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Demain</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['tomorrow_scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">En Retard</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['overdue_scheduled']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher dans les publications programmées..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
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
                        <select name="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>Toutes les dates</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Demain</option>
                            <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="next_week" <?php echo $date_filter === 'next_week' ? 'selected' : ''; ?>>Semaine prochaine</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="scheduled.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des publications programmées -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($scheduled_posts)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($scheduled_posts as $post): ?>
                            <?php
                            $now = new DateTime();
                            $scheduled = new DateTime($post['scheduled_at']);
                            $is_overdue = $scheduled < $now;
                            $is_today = $scheduled->format('Y-m-d') === $now->format('Y-m-d');
                            $card_class = $is_overdue ? 'overdue' : ($is_today ? 'today' : 'upcoming');
                            ?>
                            <div class="scheduled-card p-6 <?php echo $card_class; ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-3">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                            </h3>
                                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                                Programmé
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                                    En retard
                                                </span>
                                            <?php elseif ($is_today): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">
                                                    Aujourd'hui
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-gray-600 mb-4">
                                            <?php echo htmlspecialchars(substr($post['content'], 0, 200)) . (strlen($post['content']) > 200 ? '...' : ''); ?>
                                        </p>
                                        
                                        <div class="flex items-center space-x-6 text-sm text-gray-500 mb-3">
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <span>Client: <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar mr-2"></i>
                                                <span>Programmé: <?php echo format_date_fr($post['scheduled_at'], 'd/m/Y H:i'); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock mr-2"></i>
                                                <span>Créé <?php echo time_ago($post['created_at']); ?></span>
                                            </div>
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
                                        <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Bouton Publier maintenant -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="publish_now">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50" 
                                                    title="Publier maintenant" onclick="return confirm('Publier cette publication maintenant ?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Bouton Reprogrammer -->
                                        <button onclick="openRescheduleModal(<?php echo $post['id']; ?>, '<?php echo $post['scheduled_at']; ?>')" 
                                                class="text-yellow-600 hover:text-yellow-700 p-2 rounded-lg hover:bg-yellow-50" 
                                                title="Reprogrammer">
                                            <i class="fas fa-calendar-edit"></i>
                                        </button>
                                        
                                        <!-- Bouton Remettre en brouillon -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="move_to_draft">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="text-gray-600 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-50" 
                                                    title="Remettre en brouillon" onclick="return confirm('Remettre cette publication en brouillon ?')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette publication programmée ?')">
                                            <input type="hidden" name="action" value="delete">
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
                        <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune publication programmée</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $client_filter !== 'all' || $date_filter !== 'all'): ?>
                                Aucune publication programmée ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Vous n'avez pas encore de publications programmées.
                            <?php endif; ?>
                        </p>
                        <a href="posts.php?action=create" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Créer une publication
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de reprogrammation -->
    <div id="rescheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Reprogrammer la publication</h3>
                    <form method="POST" id="rescheduleForm">
                        <input type="hidden" name="action" value="reschedule">
                        <input type="hidden" name="post_id" id="reschedulePostId">
                        
                        <div class="mb-4">
                            <label for="reschedule_at" class="block text-sm font-medium text-gray-700 mb-2">Nouvelle date et heure de publication</label>
                            <input type="datetime-local" id="reschedule_at" name="scheduled_at" 
                                   min="<?php echo date('Y-m-d\TH:i'); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeRescheduleModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-calendar-edit mr-2"></i>Reprogrammer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openRescheduleModal(postId, currentScheduled) {
            document.getElementById('reschedulePostId').value = postId;
            // Convertir la date actuelle au format datetime-local
            const date = new Date(currentScheduled);
            const localDateTime = date.toISOString().slice(0, 16);
            document.getElementById('reschedule_at').value = localDateTime;
            document.getElementById('rescheduleModal').classList.remove('hidden');
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.add('hidden');
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
