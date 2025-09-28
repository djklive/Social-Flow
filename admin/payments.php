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
            case 'update_payment_status':
                $payment_id = (int)($_POST['payment_id'] ?? 0);
                $status = sanitize_input($_POST['status'] ?? '');
                
                if ($payment_id > 0 && !empty($status)) {
                    $stmt = $db->prepare("UPDATE payments SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $payment_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'payment_status_updated', "Statut paiement modifié: ID $payment_id -> $status");
                    
                    $success_message = 'Statut du paiement modifié avec succès.';
                }
                break;
                
            case 'refund_payment':
                $payment_id = (int)($_POST['payment_id'] ?? 0);
                if ($payment_id > 0) {
                    $stmt = $db->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'payment_refunded', "Paiement remboursé: ID $payment_id");
                    
                    $success_message = 'Paiement remboursé avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur gestion paiements: " . $e->getMessage());
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
    $method_filter = $_GET['payment_method'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($method_filter !== 'all') {
        $where_conditions[] = "p.payment_method = ?";
        $params[] = $method_filter;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(p.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(p.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Paiements avec filtres
    $stmt = $db->prepare("
        SELECT p.*, 
               u.first_name, u.last_name, u.email,
               s.plan_type, s.price as subscription_price
        FROM payments p
        INNER JOIN subscriptions s ON p.subscription_id = s.id
        INNER JOIN users u ON s.client_id = u.id
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_payments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
            COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_payments,
            COUNT(CASE WHEN payment_method = 'mobile_money' THEN 1 END) as mobile_money_payments,
            COUNT(CASE WHEN payment_method = 'orange_money' THEN 1 END) as orange_money_payments,
            COUNT(CASE WHEN payment_method = 'credit_card' THEN 1 END) as credit_card_payments,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END), 0) as avg_payment_amount
        FROM payments
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données paiements: " . $e->getMessage());
    $admin = null;
    $payments = [];
    $stats = ['total_payments' => 0, 'completed_payments' => 0, 'pending_payments' => 0, 'failed_payments' => 0, 'refunded_payments' => 0, 'mobile_money_payments' => 0, 'orange_money_payments' => 0, 'credit_card_payments' => 0, 'total_revenue' => 0, 'avg_payment_amount' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - SocialFlow</title>
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
        .payment-card {
            transition: all 0.2s ease;
        }
        .payment-card:hover {
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
                <a href="subscriptions.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnements
                </a>
                <a href="payments.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-money-bill-wave mr-3 text-orange-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Gestion des Paiements</h1>
                    <p class="text-sm text-gray-600">Surveillez tous les paiements du système</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Réussis</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['completed_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">En attente</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['pending_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Échoués</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['failed_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gray-100">
                            <i class="fas fa-undo text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Remboursés</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['refunded_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-mobile-alt text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Mobile Money</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['mobile_money_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-mobile-alt text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Orange Money</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['orange_money_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-credit-card text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Carte</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['credit_card_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Revenus</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_revenue']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100">
                            <i class="fas fa-calculator text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Moyenne</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['avg_payment_amount']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Rechercher par nom, email ou ID transaction..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Réussi</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                            <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Remboursé</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="payment_method" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="all" <?php echo $method_filter === 'all' ? 'selected' : ''; ?>>Toutes les méthodes</option>
                            <option value="mobile_money" <?php echo $method_filter === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="orange_money" <?php echo $method_filter === 'orange_money' ? 'selected' : ''; ?>>Orange Money</option>
                            <option value="credit_card" <?php echo $method_filter === 'credit_card' ? 'selected' : ''; ?>>Carte bancaire</option>
                        </select>
                    </div>
                    
                    <div>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               placeholder="Date de début" 
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               placeholder="Date de fin" 
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    
                    <a href="payments.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </a>
                </form>
            </div>

            <!-- Liste des paiements -->
            <div class="bg-white rounded-lg shadow-sm">
                <?php if (!empty($payments)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Méthode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($payments as $payment): ?>
                                    <tr class="payment-card">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-semibold text-sm">
                                                        <?php echo strtoupper(substr($payment['first_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($payment['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="font-semibold"><?php echo number_format($payment['amount']); ?> FCFA</div>
                                            <?php if ($payment['plan_type']): ?>
                                                <div class="text-xs text-gray-500"><?php echo ucfirst($payment['plan_type']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $method_colors = [
                                                'mobile_money' => 'bg-purple-100 text-purple-800',
                                                'orange_money' => 'bg-orange-100 text-orange-800',
                                                'credit_card' => 'bg-blue-100 text-blue-800'
                                            ];
                                            $method_color = $method_colors[$payment['payment_method']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $method_color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'completed' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'failed' => 'bg-red-100 text-red-800',
                                                'refunded' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $status_color = $status_colors[$payment['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo format_date_fr($payment['created_at'], 'd/m/Y H:i'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditPaymentModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-700" title="Modifier le statut">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($payment['status'] === 'completed'): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Rembourser ce paiement ?')">
                                                        <input type="hidden" name="action" value="refund_payment">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <button type="submit" class="text-yellow-600 hover:text-yellow-700" title="Rembourser">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-money-bill-wave text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun paiement trouvé</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($search) || $status_filter !== 'all' || $method_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                                Aucun paiement ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucun paiement dans le système.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Modifier Paiement -->
    <div id="editPaymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Modifier le statut du paiement</h3>
                    <form method="POST" id="editPaymentForm">
                        <input type="hidden" name="action" value="update_payment_status">
                        <input type="hidden" name="payment_id" id="edit_payment_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_client_name" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Montant</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                <span id="edit_amount" class="text-gray-900"></span>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Nouveau statut</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="completed">Réussi</option>
                                <option value="pending">En attente</option>
                                <option value="failed">Échoué</option>
                                <option value="refunded">Remboursé</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeEditPaymentModal()" 
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
        function openEditPaymentModal(payment) {
            document.getElementById('edit_payment_id').value = payment.id;
            document.getElementById('edit_client_name').textContent = payment.first_name + ' ' + payment.last_name;
            document.getElementById('edit_amount').textContent = new Intl.NumberFormat('fr-FR').format(payment.amount) + ' FCFA';
            document.getElementById('edit_status').value = payment.status;
            document.getElementById('editPaymentModal').classList.remove('hidden');
        }

        function closeEditPaymentModal() {
            document.getElementById('editPaymentModal').classList.add('hidden');
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
