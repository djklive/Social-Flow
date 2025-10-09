<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier la connexion et le rôle
if (!is_logged_in() || $_SESSION['user_role'] !== 'community_manager') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les informations du Community Manager
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $cm = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
} catch (Exception $e) {
    error_log("Erreur récupération CM: " . $e->getMessage());
    $cm = null;
    $unread_notifications = 0;
}

// Traitement du formulaire de proposition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'propose_content') {
        $title = sanitize_input($_POST['title'] ?? '');
        $content = sanitize_input($_POST['content'] ?? '');
        $platform = sanitize_input($_POST['platform'] ?? '');
        $client_id = (int)($_POST['client_id'] ?? 0);
        $scheduled_date = $_POST['scheduled_date'] ?? '';
        $hashtags = sanitize_input($_POST['hashtags'] ?? '');
        
        if (empty($title) || empty($content) || empty($platform) || empty($client_id)) {
            $error_message = 'Tous les champs obligatoires doivent être remplis.';
        } else {
            try {
                $db = getDB();
                
                // Créer la proposition de contenu
                $stmt = $db->prepare("INSERT INTO posts (title, content, platform, community_manager_id, client_id, approval_status, scheduled_date, hashtags, status) VALUES (?, ?, ?, ?, ?, 'pending_approval', ?, ?, 'draft')");
                $stmt->execute([$title, $content, $platform, $user_id, $client_id, $scheduled_date, $hashtags]);
                
                $post_id = $db->lastInsertId();
                
                // Créer l'enregistrement d'approbation
                $stmt = $db->prepare("INSERT INTO content_approvals (post_id, community_manager_id, client_id, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$post_id, $user_id, $client_id]);
                
                // Créer une notification pour le client
                $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$client_id, 'Nouvelle proposition de contenu', "Une nouvelle proposition de contenu '$title' vous attend pour approbation.", 'info']);
                
                $success_message = 'Proposition de contenu envoyée avec succès !';
                
            } catch (Exception $e) {
                $error_message = 'Erreur lors de l\'envoi de la proposition.';
                error_log("Erreur proposition contenu: " . $e->getMessage());
            }
        }
    }
}

// Récupérer les clients assignés au CM
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email FROM users u INNER JOIN assignments a ON u.id = a.client_id WHERE a.community_manager_id = ? AND u.status = 'active'");
    $stmt->execute([$user_id]);
    $clients = $stmt->fetchAll();
} catch (Exception $e) {
    $clients = [];
    error_log("Erreur récupération clients: " . $e->getMessage());
}

// Récupérer les propositions en attente
try {
    $stmt = $db->prepare("SELECT p.*, u.first_name, u.last_name FROM posts p INNER JOIN users u ON p.client_id = u.id WHERE p.community_manager_id = ? AND p.approval_status = 'pending_approval' ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
    $pending_proposals = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_proposals = [];
    error_log("Erreur récupération propositions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propositions de Contenu - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar-transition {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-blue-100 shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-blue-500 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($cm['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-900"><?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?></p>
                        <p class="text-xs text-blue-700">Community Manager</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Mes Clients
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Publications
                </a>
                <a href="content_proposals.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-200 rounded-lg">
                    <i class="fas fa-paper-plane mr-3 text-blue-800"></i>
                    Propositions
                </a>
                <a href="drafts.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-edit mr-3"></i>
                    Brouillons
                </a>
                <a href="scheduled.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Programmé
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Analytics
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg relative">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="ml-auto bg-blue-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $unread_notifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-cog mr-3"></i>
                    Paramètres
                </a>
            </div>
        </nav>
        
        <div class="absolute bottom-0 w-full p-4">
            <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
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
                    <h1 class="text-2xl font-semibold text-gray-900">Propositions de Contenu</h1>
                    <p class="text-sm text-gray-600">Proposez du contenu à vos clients pour approbation</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <button class="p-2 text-gray-400 hover:text-gray-600 relative">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                                <?php echo $unread_notifications; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Propositions de Contenu</h1>
                <p class="mt-2 text-gray-600">Proposez du contenu à vos clients pour approbation</p>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Formulaire de proposition -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>Nouvelle Proposition
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="propose_content">
                        
                        <!-- Client -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client *</label>
                            <select name="client_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionnez un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Titre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Titre *</label>
                            <input type="text" name="title" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Titre du contenu">
                        </div>

                        <!-- Contenu -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contenu *</label>
                            <textarea name="content" required rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Contenu de la publication"></textarea>
                        </div>

                        <!-- Plateforme -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Plateforme *</label>
                            <select name="platform" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionnez une plateforme</option>
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="twitter">Twitter</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="tiktok">TikTok</option>
                            </select>
                        </div>

                        <!-- Date de publication -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de publication</label>
                            <input type="datetime-local" name="scheduled_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Hashtags -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hashtags</label>
                            <input type="text" name="hashtags"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="#hashtag1 #hashtag2">
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i>Envoyer la Proposition
                        </button>
                    </form>
                </div>

                <!-- Propositions en attente -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">
                        <i class="fas fa-clock text-orange-600 mr-2"></i>En Attente d'Approbation
                    </h2>
                    
                    <?php if (empty($pending_proposals)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>Aucune proposition en attente</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($pending_proposals as $proposal): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($proposal['title']); ?></h3>
                                        <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                                            En attente
                                        </span>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-2">Client: <?php echo htmlspecialchars($proposal['first_name'] . ' ' . $proposal['last_name']); ?></p>
                                    <p class="text-gray-600 text-sm mb-2">Plateforme: <?php echo ucfirst($proposal['platform']); ?></p>
                                    <p class="text-gray-500 text-xs">Envoyé le <?php echo date('d/m/Y H:i', strtotime($proposal['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
