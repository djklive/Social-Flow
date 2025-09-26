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
            case 'create_assignment':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $community_manager_id = (int)($_POST['community_manager_id'] ?? 0);
                
                if ($client_id > 0 && $community_manager_id > 0) {
                    // Vérifier si l'assignation existe déjà
                    $stmt = $db->prepare("SELECT id FROM client_assignments WHERE client_id = ? AND community_manager_id = ?");
                    $stmt->execute([$client_id, $community_manager_id]);
                    if ($stmt->fetch()) {
                        $error_message = 'Cette assignation existe déjà.';
                        break;
                    }
                    
                    // Créer l'assignation
                    $stmt = $db->prepare("INSERT INTO client_assignments (client_id, community_manager_id, assigned_by, assigned_at, status) VALUES (?, ?, ?, NOW(), 'active')");
                    $stmt->execute([$client_id, $community_manager_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'assignment_created', "Assignation créée: Client $client_id -> CM $community_manager_id");
                    
                    $success_message = 'Assignation créée avec succès.';
                } else {
                    $error_message = 'Veuillez sélectionner un client et un Community Manager.';
                }
                break;
                
            case 'update_assignment':
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                $status = sanitize_input($_POST['status'] ?? 'active');
                
                if ($assignment_id > 0) {
                    $stmt = $db->prepare("UPDATE client_assignments SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $assignment_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'assignment_updated', "Assignation modifiée: ID $assignment_id");
                    
                    $success_message = 'Assignation modifiée avec succès.';
                }
                break;
                
            case 'delete_assignment':
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                if ($assignment_id > 0) {
                    $stmt = $db->prepare("DELETE FROM client_assignments WHERE id = ?");
                    $stmt->execute([$assignment_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'assignment_deleted', "Assignation supprimée: ID $assignment_id");
                    
                    $success_message = 'Assignation supprimée avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur gestion assignations: " . $e->getMessage());
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
    $cm_filter = $_GET['cm_id'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "ca.status = ?";
        $params[] = $status_filter;
    }
    
    if ($cm_filter !== 'all') {
        $where_conditions[] = "ca.community_manager_id = ?";
        $params[] = $cm_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR cm.first_name LIKE ? OR cm.last_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Assignations avec filtres
    $stmt = $db->prepare("
        SELECT ca.*, 
               c.first_name as client_first_name, c.last_name as client_last_name, c.email as client_email,
               cm.first_name as cm_first_name, cm.last_name as cm_last_name, cm.email as cm_email,
               admin.first_name as assigned_by_first_name, admin.last_name as assigned_by_last_name,
               (SELECT COUNT(*) FROM posts WHERE client_id = ca.client_id AND community_manager_id = ca.community_manager_id) as post_count
        FROM client_assignments ca
        INNER JOIN users c ON ca.client_id = c.id
        INNER JOIN users cm ON ca.community_manager_id = cm.id
        LEFT JOIN users admin ON ca.assigned_by = admin.id
        WHERE $where_clause
        ORDER BY ca.assigned_at DESC
    ");
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
    
    // Clients disponibles (non assignés ou assignés)
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM client_assignments WHERE client_id = u.id AND status = 'active') as active_assignments
        FROM users u 
        WHERE u.role = 'client' AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll();
    
    // Community Managers disponibles
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM client_assignments WHERE community_manager_id = u.id AND status = 'active') as active_assignments
        FROM users u 
        WHERE u.role = 'community_manager' AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $community_managers = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_assignments,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_assignments,
            COUNT(DISTINCT client_id) as assigned_clients,
            COUNT(DISTINCT community_manager_id) as active_cms
        FROM client_assignments
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données assignations: " . $e->getMessage());
    $admin = null;
    $assignments = [];
    $clients = [];
    $community_managers = [];
    $stats = ['total_assignments' => 0, 'active_assignments' => 0, 'inactive_assignments' => 0, 'assigned_clients' => 0, 'active_cms' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Assignations - SocialFlow</title>
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
        .assignment-card {
            transition: all 0.2s ease;
        }
        .assignment-card:hover {
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
                <a href="assignments.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-user-friends mr-3 text-orange-600"></i>
                    Assignations
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Gestion des Assignations</h1>
                    <p class="text-sm text-gray-600">Assignez les clients aux Community Managers</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openCreateAssignmentModal()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Assignation
                    </button>
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-download text-lg"></i>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-user-friends text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Assignations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_assignments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Actives</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_assignments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Inactives</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['inactive_assignments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-user text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Clients Assignés</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['assigned_clients']; ?></p>
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
                               placeholder="Rechercher par nom client ou CM..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
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
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="assignments.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des assignations -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($assignments)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Community Manager</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Publications</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigné par</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr class="assignment-card">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($assignment['client_first_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($assignment['client_first_name'] . ' ' . $assignment['client_last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($assignment['client_email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($assignment['cm_first_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($assignment['cm_first_name'] . ' ' . $assignment['cm_last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($assignment['cm_email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'inactive' => 'bg-red-100 text-red-800'
                                            ];
                                            $status_color = $status_colors[$assignment['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $assignment['post_count']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($assignment['assigned_by_first_name'] . ' ' . $assignment['assigned_by_last_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo format_date_fr($assignment['assigned_at'], 'd/m/Y H:i'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditAssignmentModal(<?php echo htmlspecialchars(json_encode($assignment)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-700" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette assignation ?')">
                                                    <input type="hidden" name="action" value="delete_assignment">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-700" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-user-friends text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune assignation trouvée</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $status_filter !== 'all' || $cm_filter !== 'all'): ?>
                                Aucune assignation ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucune assignation dans le système.
                            <?php endif; ?>
                        </p>
                        <button onclick="openCreateAssignmentModal()" class="mt-4 inline-block bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Créer une assignation
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Créer Assignation -->
    <div id="createAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Créer une nouvelle assignation</h3>
                    <form method="POST" id="createAssignmentForm">
                        <input type="hidden" name="action" value="create_assignment">
                        
                        <div class="mb-4">
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <select id="client_id" name="client_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                        (<?php echo $client['active_assignments']; ?> assignation(s))
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label for="community_manager_id" class="block text-sm font-medium text-gray-700 mb-2">Community Manager</label>
                            <select id="community_manager_id" name="community_manager_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Sélectionner un Community Manager</option>
                                <?php foreach ($community_managers as $cm): ?>
                                    <option value="<?php echo $cm['id']; ?>">
                                        <?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?>
                                        (<?php echo $cm['active_assignments']; ?> client(s))
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeCreateAssignmentModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </button>
                            <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                                <i class="fas fa-plus mr-2"></i>Créer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Assignation -->
    <div id="editAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Modifier l'assignation</h3>
                    <form method="POST" id="editAssignmentForm">
                        <input type="hidden" name="action" value="update_assignment">
                        <input type="hidden" name="assignment_id" id="edit_assignment_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_client_name" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Community Manager</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_cm_name" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="active">Actif</option>
                                <option value="inactive">Inactif</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeEditAssignmentModal()" 
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
        function openCreateAssignmentModal() {
            document.getElementById('createAssignmentModal').classList.remove('hidden');
        }

        function closeCreateAssignmentModal() {
            document.getElementById('createAssignmentModal').classList.add('hidden');
            document.getElementById('createAssignmentForm').reset();
        }

        function openEditAssignmentModal(assignment) {
            document.getElementById('edit_assignment_id').value = assignment.id;
            document.getElementById('edit_client_name').textContent = assignment.client_first_name + ' ' + assignment.client_last_name;
            document.getElementById('edit_cm_name').textContent = assignment.cm_first_name + ' ' + assignment.cm_last_name;
            document.getElementById('edit_status').value = assignment.status;
            document.getElementById('editAssignmentModal').classList.remove('hidden');
        }

        function closeEditAssignmentModal() {
            document.getElementById('editAssignmentModal').classList.add('hidden');
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
