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
            case 'publish':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = ? AND community_manager_id = ? AND status = 'draft'");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_published', "Publication publiée: ID $post_id");
                    
                    $success_message = 'Publication publiée avec succès.';
                }
                break;
                
            case 'schedule':
                $post_id = (int)($_POST['post_id'] ?? 0);
                $scheduled_at = $_POST['scheduled_at'] ?? '';
                
                if ($post_id > 0 && !empty($scheduled_at)) {
                    $stmt = $db->prepare("UPDATE posts SET status = 'scheduled', scheduled_at = ? WHERE id = ? AND community_manager_id = ? AND status = 'draft'");
                    $stmt->execute([$scheduled_at, $post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_scheduled', "Publication programmée: ID $post_id");
                    
                    $success_message = 'Publication programmée avec succès.';
                }
                break;
                
            case 'delete':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND community_manager_id = ? AND status = 'draft'");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'draft_deleted', "Brouillon supprimé: ID $post_id");
                    
                    $success_message = 'Brouillon supprimé avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur drafts CM: " . $e->getMessage());
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
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["p.community_manager_id = ?", "p.status = 'draft'"];
    $params = [$user_id];
    
    if ($client_filter !== 'all') {
        $where_conditions[] = "p.client_id = ?";
        $params[] = $client_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Brouillons avec filtres
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id 
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $drafts = $stmt->fetchAll();
    
    // Clients assignés
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$user_id]);
    $clients = $stmt->fetchAll();
    
    // Statistiques des brouillons
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_drafts,
            COUNT(DISTINCT client_id) as clients_with_drafts
        FROM posts 
        WHERE community_manager_id = ? AND status = 'draft'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données drafts: " . $e->getMessage());
    $cm = null;
    $drafts = [];
    $clients = [];
    $stats = ['total_drafts' => 0, 'clients_with_drafts' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brouillons - SocialFlow</title>
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
        .draft-card {
            transition: all 0.2s ease;
        }
        .draft-card:hover {
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
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Mes Clients
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="drafts.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-edit mr-3 text-green-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Brouillons</h1>
                    <p class="text-sm text-gray-600">Gérez vos publications en cours de rédaction</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="posts.php?action=create" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouveau Brouillon
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gray-100">
                            <i class="fas fa-edit text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Brouillons</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients avec Brouillons</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['clients_with_drafts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher dans les brouillons..." 
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
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="drafts.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des brouillons -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($drafts)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($drafts as $draft): ?>
                            <div class="draft-card p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-3">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($draft['title'] ?: 'Sans titre'); ?>
                                            </h3>
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                                Brouillon
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-600 mb-4">
                                            <?php echo htmlspecialchars(substr($draft['content'], 0, 200)) . (strlen($draft['content']) > 200 ? '...' : ''); ?>
                                        </p>
                                        
                                        <div class="flex items-center space-x-6 text-sm text-gray-500 mb-3">
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <span>Client: <?php echo htmlspecialchars($draft['first_name'] . ' ' . $draft['last_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock mr-2"></i>
                                                <span>Créé <?php echo time_ago($draft['created_at']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($draft['platforms']): ?>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-500">Plateformes:</span>
                                                <?php
                                                $platforms = json_decode($draft['platforms'], true);
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
                                        <a href="posts.php?action=edit&id=<?php echo $draft['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Bouton Publier -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="post_id" value="<?php echo $draft['id']; ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50" 
                                                    title="Publier maintenant" onclick="return confirm('Publier ce brouillon maintenant ?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Bouton Programmer -->
                                        <button onclick="openScheduleModal(<?php echo $draft['id']; ?>)" 
                                                class="text-yellow-600 hover:text-yellow-700 p-2 rounded-lg hover:bg-yellow-50" 
                                                title="Programmer">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce brouillon ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="post_id" value="<?php echo $draft['id']; ?>">
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
                        <i class="fas fa-edit text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun brouillon trouvé</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $client_filter !== 'all'): ?>
                                Aucun brouillon ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Vous n'avez pas encore de brouillons.
                            <?php endif; ?>
                        </p>
                        <a href="posts.php?action=create" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Créer un brouillon
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de programmation -->
    <div id="scheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Programmer la publication</h3>
                    <form method="POST" id="scheduleForm">
                        <input type="hidden" name="action" value="schedule">
                        <input type="hidden" name="post_id" id="schedulePostId">
                        
                        <div class="mb-4">
                            <label for="scheduled_at" class="block text-sm font-medium text-gray-700 mb-2">Date et heure de publication</label>
                            <input type="datetime-local" id="scheduled_at" name="scheduled_at" 
                                   min="<?php echo date('Y-m-d\TH:i'); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeScheduleModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-calendar-plus mr-2"></i>Programmer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openScheduleModal(postId) {
            document.getElementById('schedulePostId').value = postId;
            document.getElementById('scheduleModal').classList.remove('hidden');
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.add('hidden');
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
