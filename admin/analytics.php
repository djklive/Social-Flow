<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('admin');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Récupérer les données
try {
    $db = getDB();
    
    // Informations de l'admin
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();
    
    // Paramètres de filtrage
    $date_range = $_GET['date_range'] ?? '30_days';
    $period = $_GET['period'] ?? 'daily';
    
    // Calculer les dates selon la période
    $date_condition = '';
    switch ($date_range) {
        case '7_days':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30_days':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90_days':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case '1_year':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
    
    // Statistiques globales
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'client') as total_clients,
            (SELECT COUNT(*) FROM users WHERE role = 'community_manager') as total_cms,
            (SELECT COUNT(*) FROM posts) as total_posts,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
            (SELECT COUNT(*) FROM payments WHERE status = 'completed') as completed_payments,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed') as total_revenue,
            (SELECT COUNT(*) FROM client_assignments WHERE status = 'active') as active_assignments
    ");
    $stmt->execute();
    $global_stats = $stmt->fetch();
    
    // Statistiques des utilisateurs par mois
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(CASE WHEN role = 'client' THEN 1 END) as clients,
            COUNT(CASE WHEN role = 'community_manager' THEN 1 END) as cms,
            COUNT(*) as total_users
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $user_stats = $stmt->fetchAll();
    
    // Statistiques des publications par mois
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_posts,
            COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_posts,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_posts
        FROM posts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $post_stats = $stmt->fetchAll();
    
    // Statistiques des revenus par mois
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_payments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as revenue
        FROM payments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $revenue_stats = $stmt->fetchAll();
    
    // Top Community Managers par nombre de publications
    $stmt = $db->prepare("
        SELECT 
            u.first_name, u.last_name, u.email,
            COUNT(p.id) as post_count,
            COUNT(CASE WHEN p.status = 'published' THEN 1 END) as published_count,
            COUNT(DISTINCT p.client_id) as client_count
        FROM users u
        LEFT JOIN posts p ON u.id = p.community_manager_id
        WHERE u.role = 'community_manager'
        GROUP BY u.id, u.first_name, u.last_name, u.email
        ORDER BY post_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_cms = $stmt->fetchAll();
    
    // Top clients par nombre de publications
    $stmt = $db->prepare("
        SELECT 
            u.first_name, u.last_name, u.email,
            COUNT(p.id) as post_count,
            COUNT(CASE WHEN p.status = 'published' THEN 1 END) as published_count,
            s.plan_type,
            s.price
        FROM users u
        LEFT JOIN posts p ON u.id = p.client_id
        LEFT JOIN subscriptions s ON u.id = s.client_id AND s.status = 'active'
        WHERE u.role = 'client'
        GROUP BY u.id, u.first_name, u.last_name, u.email, s.plan_type, s.price
        ORDER BY post_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_clients = $stmt->fetchAll();
    
    // Répartition des abonnements
    $stmt = $db->prepare("
        SELECT 
            plan_type,
            COUNT(*) as count,
            COALESCE(SUM(price), 0) as total_revenue
        FROM subscriptions 
        WHERE status = 'active'
        GROUP BY plan_type
    ");
    $stmt->execute();
    $subscription_distribution = $stmt->fetchAll();
    
    // Méthodes de paiement
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM payments 
        WHERE status = 'completed'
        GROUP BY payment_method
    ");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur analytics admin: " . $e->getMessage());
    $admin = null;
    $global_stats = ['total_clients' => 0, 'total_cms' => 0, 'total_posts' => 0, 'active_subscriptions' => 0, 'completed_payments' => 0, 'total_revenue' => 0, 'active_assignments' => 0];
    $user_stats = [];
    $post_stats = [];
    $revenue_stats = [];
    $top_cms = [];
    $top_clients = [];
    $subscription_distribution = [];
    $payment_methods = [];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Globales - SocialFlow</title>
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
                <a href="payments.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Paiements
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3 text-orange-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Analytics Globales</h1>
                    <p class="text-sm text-gray-600">Vue d'ensemble des performances du système</p>
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
            
            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div>
                        <select name="date_range" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="7_days" <?php echo $date_range === '7_days' ? 'selected' : ''; ?>>7 derniers jours</option>
                            <option value="30_days" <?php echo $date_range === '30_days' ? 'selected' : ''; ?>>30 derniers jours</option>
                            <option value="90_days" <?php echo $date_range === '90_days' ? 'selected' : ''; ?>>90 derniers jours</option>
                            <option value="1_year" <?php echo $date_range === '1_year' ? 'selected' : ''; ?>>1 an</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="period" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="daily" <?php echo $period === 'daily' ? 'selected' : ''; ?>>Quotidien</option>
                            <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                            <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-300">
                        <i class="fas fa-filter mr-2"></i>Appliquer
                    </button>
                </form>
            </div>
            
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-user text-purple-600 text-xl"></i>
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
                            <i class="fas fa-user-tie text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Community Managers</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['total_cms']; ?></p>
                        </div>
                    </div>
                </div>
                
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
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-credit-card text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Abonnements Actifs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['active_subscriptions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Paiements Réussis</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['completed_payments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100">
                            <i class="fas fa-chart-line text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Revenus Totaux</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($global_stats['total_revenue']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Assignations Actives</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $global_stats['active_assignments']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Graphique des utilisateurs par mois -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Utilisateurs par mois</h3>
                    <canvas id="usersChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Graphique des publications par mois -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Publications par mois</h3>
                    <canvas id="postsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Graphique des revenus -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenus par mois</h3>
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Répartition des abonnements -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition des abonnements</h3>
                    <canvas id="subscriptionsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Top Community Managers -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Community Managers</h3>
                    <?php if (!empty($top_cms)): ?>
                        <div class="space-y-4">
                            <?php foreach ($top_cms as $index => $cm): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <span class="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo $cm['client_count']; ?> client(s)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900"><?php echo $cm['post_count']; ?> publications</div>
                                        <div class="text-sm text-gray-500"><?php echo $cm['published_count']; ?> publiées</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-user-tie text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-500">Aucune donnée disponible</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Clients -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Clients</h3>
                    <?php if (!empty($top_clients)): ?>
                        <div class="space-y-4">
                            <?php foreach ($top_clients as $index => $client): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <span class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo ucfirst($client['plan_type'] ?: 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900"><?php echo $client['post_count']; ?> publications</div>
                                        <div class="text-sm text-gray-500"><?php echo $client['published_count']; ?> publiées</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-user text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-500">Aucune donnée disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Méthodes de paiement -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Méthodes de paiement</h3>
                <?php if (!empty($payment_methods)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($payment_methods as $method): ?>
                            <div class="text-center p-6 border border-gray-200 rounded-lg">
                                <div class="text-3xl font-bold text-gray-900 mb-2">
                                    <?php echo number_format($method['count']); ?>
                                </div>
                                <div class="text-sm text-gray-500 mb-2">
                                    <?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?>
                                </div>
                                <div class="text-lg font-semibold text-green-600">
                                    <?php echo number_format($method['total_amount']); ?> FCFA
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-credit-card text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500">Aucune donnée de paiement disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Graphique des utilisateurs
        const usersCtx = document.getElementById('usersChart').getContext('2d');
        const usersData = <?php echo json_encode($user_stats); ?>;
        
        if (usersData && usersData.length > 0) {
            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: usersData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                    }).reverse(),
                    datasets: [{
                        label: 'Clients',
                        data: usersData.map(item => item.clients).reverse(),
                        borderColor: 'rgb(147, 51, 234)',
                        backgroundColor: 'rgba(147, 51, 234, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Community Managers',
                        data: usersData.map(item => item.cms).reverse(),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
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
        }

        // Graphique des publications
        const postsCtx = document.getElementById('postsChart').getContext('2d');
        const postsData = <?php echo json_encode($post_stats); ?>;
        
        if (postsData && postsData.length > 0) {
            new Chart(postsCtx, {
                type: 'bar',
                data: {
                    labels: postsData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                    }).reverse(),
                    datasets: [{
                        label: 'Total',
                        data: postsData.map(item => item.total_posts).reverse(),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)'
                    }, {
                        label: 'Publiées',
                        data: postsData.map(item => item.published_posts).reverse(),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
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
        }

        // Graphique des revenus
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($revenue_stats); ?>;
        
        if (revenueData && revenueData.length > 0) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                    }).reverse(),
                    datasets: [{
                        label: 'Revenus (FCFA)',
                        data: revenueData.map(item => item.revenue).reverse(),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique des abonnements
        const subscriptionsCtx = document.getElementById('subscriptionsChart').getContext('2d');
        const subscriptionsData = <?php echo json_encode($subscription_distribution); ?>;
        
        if (subscriptionsData && subscriptionsData.length > 0) {
            new Chart(subscriptionsCtx, {
                type: 'doughnut',
                data: {
                    labels: subscriptionsData.map(item => ucfirst(item.plan_type)),
                    datasets: [{
                        data: subscriptionsData.map(item => item.count),
                        backgroundColor: [
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
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
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
