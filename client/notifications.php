<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Vérifier les permissions
check_permission('client');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                mark_notification_as_read($notification_id, $user_id);
                redirect_with_message('notifications.php', 'Notification marquée comme lue.', 'success');
            }
            break;
            
        case 'mark_all_read':
            mark_all_notifications_as_read($user_id);
            redirect_with_message('notifications.php', 'Toutes les notifications ont été marquées comme lues.', 'success');
            break;
            
        case 'delete':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                delete_notification($notification_id, $user_id);
                redirect_with_message('notifications.php', 'Notification supprimée.', 'success');
            }
            break;
            
        case 'delete_read':
            delete_read_notifications($user_id);
            redirect_with_message('notifications.php', 'Toutes les notifications lues ont été supprimées.', 'success');
            break;
    }
}

// Récupérer les notifications
$notifications = get_user_notifications($user_id, 50);
$unread_count = count_unread_notifications($user_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar-transition {
            transition: all 0.3s ease;
        }
        .notification-item {
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f8fafc;
        }
        .notification-unread {
            border-left: 4px solid #3b82f6;
            background-color: #eff6ff;
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
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
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
                <a href="statistics.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Statistiques
                </a>
                <a href="subscription.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnement
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-100 rounded-lg">
                    <i class="fas fa-bell mr-3 text-blue-800"></i>
                    Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $unread_count; ?>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Notifications</h1>
                    <p class="text-sm text-gray-600">
                        <?php echo $unread_count > 0 ? "$unread_count notification(s) non lue(s)" : "Toutes vos notifications sont lues"; ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300">
                                <i class="fas fa-check-double mr-2"></i>Marquer tout comme lu
                            </button>
                        </form>
                    <?php endif; ?>
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-cog text-lg"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?php display_flash_message(); ?>
            
            <!-- Actions en masse -->
            <?php if (!empty($notifications)): ?>
                <div class="content-card rounded-lg shadow-sm p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">
                                <?php echo count($notifications); ?> notification(s) au total
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete_read">
                                <button type="submit" class="text-red-600 hover:text-red-700 text-sm font-medium" 
                                        onclick="return confirm('Supprimer toutes les notifications lues ?')">
                                    <i class="fas fa-trash mr-1"></i>Supprimer les lues
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des notifications -->
            <div class="content-card rounded-lg shadow-sm">
                <?php if (!empty($notifications)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item p-6 <?php echo !$notification['is_read'] ? 'notification-unread' : ''; ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-4 flex-1">
                                        <!-- Icône selon le type -->
                                        <div class="flex-shrink-0">
                                            <?php
                                            $icon_class = 'fas fa-info-circle text-blue-500';
                                            switch ($notification['type']) {
                                                case 'success':
                                                    $icon_class = 'fas fa-check-circle text-green-500';
                                                    break;
                                                case 'error':
                                                    $icon_class = 'fas fa-exclamation-circle text-red-500';
                                                    break;
                                                case 'warning':
                                                    $icon_class = 'fas fa-exclamation-triangle text-yellow-500';
                                                    break;
                                                default:
                                                    $icon_class = 'fas fa-info-circle text-blue-500';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon_class; ?> text-xl"></i>
                                        </div>
                                        
                                        <!-- Contenu de la notification -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <h3 class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h3>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-2">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <div class="flex items-center text-xs text-gray-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                <span><?php echo time_ago($notification['created_at']); ?></span>
                                                <?php if ($notification['related_entity_type']): ?>
                                                    <span class="mx-2">•</span>
                                                    <span class="px-2 py-1 bg-gray-100 rounded-full">
                                                        <?php echo ucfirst($notification['related_entity_type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center space-x-2 ml-4">
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="text-blue-600 hover:text-blue-700 text-sm" title="Marquer comme lu">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700 text-sm" 
                                                    title="Supprimer" onclick="return confirm('Supprimer cette notification ?')">
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
                        <i class="fas fa-bell-slash text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune notification</h3>
                        <p class="text-gray-500">Vous n'avez pas encore de notifications.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh des notifications toutes les 30 secondes
        setInterval(function() {
            // Vérifier s'il y a de nouvelles notifications
            fetch('api/check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        // Mettre à jour le compteur dans la sidebar
                        const badge = document.querySelector('.bg-red-500');
                        if (badge) {
                            badge.textContent = data.unread_count;
                        } else {
                            // Créer le badge s'il n'existe pas
                            const link = document.querySelector('a[href="notifications.php"]');
                            if (link) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                                newBadge.textContent = data.unread_count;
                                link.appendChild(newBadge);
                            }
                        }
                    }
                })
                .catch(error => console.log('Erreur vérification notifications:', error));
        }, 30000);

        // Animation d'entrée pour les notifications
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach((notification, index) => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    notification.style.transition = 'all 0.3s ease';
                    notification.style.opacity = '1';
                    notification.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>
