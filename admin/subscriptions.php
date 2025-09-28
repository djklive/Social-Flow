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
            case 'create_subscription':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $plan_type = sanitize_input($_POST['plan_type'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                
                if ($client_id > 0 && !empty($plan_type) && $price > 0 && !empty($start_date) && !empty($end_date)) {
                    // Vérifier si l'utilisateur a déjà un abonnement actif
                    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE client_id = ? AND status = 'active'");
                    $stmt->execute([$client_id]);
                    if ($stmt->fetch()) {
                        $error_message = 'Cet utilisateur a déjà un abonnement actif.';
                        break;
                    }
                    
                    // Créer l'abonnement
                    $stmt = $db->prepare("INSERT INTO subscriptions (client_id, plan_type, price, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([$client_id, $plan_type, $price, $start_date, $end_date]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'subscription_created', "Abonnement créé: Client $client_id ($plan_type)");
                    
                    $success_message = 'Abonnement créé avec succès.';
                } else {
                    $error_message = 'Veuillez remplir tous les champs obligatoires.';
                }
                break;
                
            case 'update_subscription':
                $subscription_id = (int)($_POST['subscription_id'] ?? 0);
                $plan_type = sanitize_input($_POST['plan_type'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $status = sanitize_input($_POST['status'] ?? 'active');
                
                if ($subscription_id > 0) {
                    $stmt = $db->prepare("UPDATE subscriptions SET plan_type = ?, price = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
                    $stmt->execute([$plan_type, $price, $start_date, $end_date, $status, $subscription_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'subscription_updated', "Abonnement modifié: ID $subscription_id");
                    
                    $success_message = 'Abonnement modifié avec succès.';
                }
                break;
                
            case 'cancel_subscription':
                $subscription_id = (int)($_POST['subscription_id'] ?? 0);
                if ($subscription_id > 0) {
                    $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$subscription_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'subscription_cancelled', "Abonnement annulé: ID $subscription_id");
                    
                    $success_message = 'Abonnement annulé avec succès.';
                }
                break;
                
            case 'delete_subscription':
                $subscription_id = (int)($_POST['subscription_id'] ?? 0);
                if ($subscription_id > 0) {
                    $stmt = $db->prepare("DELETE FROM subscriptions WHERE id = ?");
                    $stmt->execute([$subscription_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'subscription_deleted', "Abonnement supprimé: ID $subscription_id");
                    
                    $success_message = 'Abonnement supprimé avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur gestion abonnements: " . $e->getMessage());
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
    $plan_filter = $_GET['plan_type'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    }
    
    if ($plan_filter !== 'all') {
        $where_conditions[] = "s.plan_type = ?";
        $params[] = $plan_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Abonnements avec filtres
    $stmt = $db->prepare("
        SELECT s.*, 
               u.first_name, u.last_name, u.email,
               (SELECT COUNT(*) FROM payments WHERE subscription_id = s.id) as payment_count,
               (SELECT SUM(amount) FROM payments WHERE subscription_id = s.id AND status = 'completed') as total_paid
        FROM subscriptions s
        INNER JOIN users u ON s.client_id = u.id
        WHERE $where_clause
        ORDER BY s.created_at DESC
    ");
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll();
    
    // Clients disponibles (sans abonnement actif)
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        WHERE u.role = 'client' AND u.status = 'active'
        AND u.id NOT IN (SELECT client_id FROM subscriptions WHERE status = 'active')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $available_clients = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_subscriptions,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_subscriptions,
            COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_subscriptions,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_subscriptions,
            COUNT(CASE WHEN plan_type = 'monthly' THEN 1 END) as monthly_subscriptions,
            COUNT(CASE WHEN plan_type = 'yearly' THEN 1 END) as yearly_subscriptions,
            COALESCE(SUM(price), 0) as total_revenue,
            COALESCE(AVG(price), 0) as avg_subscription_value
        FROM subscriptions
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données abonnements: " . $e->getMessage());
    $admin = null;
    $subscriptions = [];
    $available_clients = [];
    $stats = ['total_subscriptions' => 0, 'active_subscriptions' => 0, 'expired_subscriptions' => 0, 'cancelled_subscriptions' => 0, 'monthly_subscriptions' => 0, 'yearly_subscriptions' => 0, 'total_revenue' => 0, 'avg_subscription_value' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Abonnements - SocialFlow</title>
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
        .subscription-card {
            transition: all 0.2s ease;
        }
        .subscription-card:hover {
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
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="subscriptions.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3 text-orange-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Gestion des Abonnements</h1>
                    <p class="text-sm text-gray-600">Gérez tous les abonnements du système</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openCreateSubscriptionModal()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-plus mr-2"></i>Nouvel Abonnement
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-8 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-credit-card text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Expirés</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['expired_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gray-100">
                            <i class="fas fa-ban text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Annulés</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['cancelled_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Mensuels</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['monthly_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100">
                            <i class="fas fa-calendar-alt text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Annuels</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['yearly_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Revenus</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_revenue']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Moyenne</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['avg_subscription_value']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher par nom ou email..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expiré</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="plan_type" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $plan_filter === 'all' ? 'selected' : ''; ?>>Tous les plans</option>
                            <option value="monthly" <?php echo $plan_filter === 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                            <option value="yearly" <?php echo $plan_filter === 'yearly' ? 'selected' : ''; ?>>Annuel</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="subscriptions.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des abonnements -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($subscriptions)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paiements</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr class="subscription-card">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($subscription['first_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($subscription['first_name'] . ' ' . $subscription['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($subscription['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $plan_colors = [
                                                'monthly' => 'bg-blue-100 text-blue-800',
                                                'yearly' => 'bg-green-100 text-green-800'
                                            ];
                                            $plan_color = $plan_colors[$subscription['plan_type']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $plan_color; ?>">
                                                <?php echo ucfirst($subscription['plan_type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo number_format($subscription['price']); ?> FCFA
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'expired' => 'bg-red-100 text-red-800',
                                                'cancelled' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $status_color = $status_colors[$subscription['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                <?php echo ucfirst($subscription['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div>
                                                <div>Début: <?php echo format_date_fr($subscription['start_date'], 'd/m/Y'); ?></div>
                                                <div>Fin: <?php echo format_date_fr($subscription['end_date'], 'd/m/Y'); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div>
                                                <div><?php echo $subscription['payment_count']; ?> paiement(s)</div>
                                                <div class="text-green-600 font-medium"><?php echo number_format($subscription['total_paid']); ?> FCFA</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditSubscriptionModal(<?php echo htmlspecialchars(json_encode($subscription)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-700" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($subscription['status'] === 'active'): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Annuler cet abonnement ?')">
                                                        <input type="hidden" name="action" value="cancel_subscription">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                                        <button type="submit" class="text-yellow-600 hover:text-yellow-700" title="Annuler">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet abonnement ?')">
                                                    <input type="hidden" name="action" value="delete_subscription">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
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
                        <i class="fas fa-credit-card text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun abonnement trouvé</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $status_filter !== 'all' || $plan_filter !== 'all'): ?>
                                Aucun abonnement ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucun abonnement dans le système.
                            <?php endif; ?>
                        </p>
                        <button onclick="openCreateSubscriptionModal()" class="mt-4 inline-block bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Créer un abonnement
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Créer Abonnement -->
    <div id="createSubscriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Créer un nouvel abonnement</h3>
                    <form method="POST" id="createSubscriptionForm">
                        <input type="hidden" name="action" value="create_subscription">
                        
                        <div class="mb-4">
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <select id="client_id" name="client_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($available_clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="plan_type" class="block text-sm font-medium text-gray-700 mb-2">Type de plan</label>
                            <select id="plan_type" name="plan_type" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Sélectionner un plan</option>
                                <option value="monthly">Mensuel</option>
                                <option value="yearly">Annuel</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Prix (FCFA)</label>
                            <input type="number" id="price" name="price" required min="0" step="100"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Date de début</label>
                                <input type="date" id="start_date" name="start_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">Date de fin</label>
                                <input type="date" id="end_date" name="end_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeCreateSubscriptionModal()" 
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

    <!-- Modal Modifier Abonnement -->
    <div id="editSubscriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Modifier l'abonnement</h3>
                    <form method="POST" id="editSubscriptionForm">
                        <input type="hidden" name="action" value="update_subscription">
                        <input type="hidden" name="subscription_id" id="edit_subscription_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_client_name" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_plan_type" class="block text-sm font-medium text-gray-700 mb-2">Type de plan</label>
                            <select id="edit_plan_type" name="plan_type" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="monthly">Mensuel</option>
                                <option value="yearly">Annuel</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_price" class="block text-sm font-medium text-gray-700 mb-2">Prix (FCFA)</label>
                            <input type="number" id="edit_price" name="price" required min="0" step="100"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="edit_start_date" class="block text-sm font-medium text-gray-700 mb-2">Date de début</label>
                                <input type="date" id="edit_start_date" name="start_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="edit_end_date" class="block text-sm font-medium text-gray-700 mb-2">Date de fin</label>
                                <input type="date" id="edit_end_date" name="end_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="active">Actif</option>
                                <option value="expired">Expiré</option>
                                <option value="cancelled">Annulé</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeEditSubscriptionModal()" 
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
        function openCreateSubscriptionModal() {
            document.getElementById('createSubscriptionModal').classList.remove('hidden');
        }

        function closeCreateSubscriptionModal() {
            document.getElementById('createSubscriptionModal').classList.add('hidden');
            document.getElementById('createSubscriptionForm').reset();
        }

        function openEditSubscriptionModal(subscription) {
            document.getElementById('edit_subscription_id').value = subscription.id;
            document.getElementById('edit_client_name').textContent = subscription.first_name + ' ' + subscription.last_name;
            document.getElementById('edit_plan_type').value = subscription.plan_type;
            document.getElementById('edit_price').value = subscription.price;
            document.getElementById('edit_start_date').value = subscription.start_date;
            document.getElementById('edit_end_date').value = subscription.end_date;
            document.getElementById('edit_status').value = subscription.status;
            document.getElementById('editSubscriptionModal').classList.remove('hidden');
        }

        function closeEditSubscriptionModal() {
            document.getElementById('editSubscriptionModal').classList.add('hidden');
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
