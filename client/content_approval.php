<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier la connexion et le rôle
if (!is_logged_in() || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les informations du client
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
} catch (Exception $e) {
    error_log("Erreur récupération client: " . $e->getMessage());
    $client = null;
    $unread_notifications = 0;
}

// Traitement des actions d'approbation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $comments = sanitize_input($_POST['comments'] ?? '');
    
    if ($post_id > 0) {
        try {
            $db = getDB();
            
            if ($_POST['action'] === 'approve') {
                // Approuver le contenu
                $stmt = $db->prepare("UPDATE posts SET approval_status = 'approved', approval_comments = ?, approved_at = NOW(), approved_by = ? WHERE id = ? AND client_id = ?");
                $stmt->execute([$comments, $user_id, $post_id, $user_id]);
                
                $stmt = $db->prepare("UPDATE content_approvals SET status = 'approved', comments = ?, updated_at = NOW() WHERE post_id = ? AND client_id = ?");
                $stmt->execute([$comments, $post_id, $user_id]);
                
                // Notifier le Community Manager
                $stmt = $db->prepare("SELECT community_manager_id FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $post = $stmt->fetch();
                
                if ($post) {
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$post['community_manager_id'], 'Contenu approuvé', "Votre proposition de contenu a été approuvée par le client.", 'success']);
                }
                
                $success_message = 'Contenu approuvé avec succès !';
                
            } elseif ($_POST['action'] === 'reject') {
                // Rejeter le contenu
                $stmt = $db->prepare("UPDATE posts SET approval_status = 'rejected', approval_comments = ?, approved_at = NOW(), approved_by = ? WHERE id = ? AND client_id = ?");
                $stmt->execute([$comments, $user_id, $post_id, $user_id]);
                
                $stmt = $db->prepare("UPDATE content_approvals SET status = 'rejected', comments = ?, updated_at = NOW() WHERE post_id = ? AND client_id = ?");
                $stmt->execute([$comments, $post_id, $user_id]);
                
                // Notifier le Community Manager
                $stmt = $db->prepare("SELECT community_manager_id FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $post = $stmt->fetch();
                
                if ($post) {
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$post['community_manager_id'], 'Contenu rejeté', "Votre proposition de contenu a été rejetée par le client.", 'warning']);
                }
                
                $success_message = 'Contenu rejeté.';
            }
            
        } catch (Exception $e) {
            $error_message = 'Erreur lors du traitement de la demande.';
            error_log("Erreur approbation: " . $e->getMessage());
        }
    }
}

// Récupérer les propositions en attente d'approbation
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email 
        FROM posts p 
        INNER JOIN users u ON p.community_manager_id = u.id 
        WHERE p.client_id = ? AND p.approval_status = 'pending_approval' 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $pending_content = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_content = [];
    error_log("Erreur récupération contenu: " . $e->getMessage());
}

// Récupérer l'historique des approbations
try {
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.community_manager_id = u.id 
        WHERE p.client_id = ? AND p.approval_status IN ('approved', 'rejected') 
        ORDER BY p.approved_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $approval_history = $stmt->fetchAll();
} catch (Exception $e) {
    $approval_history = [];
    error_log("Erreur récupération historique: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approbation de Contenu - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar-transition {
            transition: all 0.3s ease;
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
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-blue-100 shadow-lg sidebar-transition" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-blue-500 to-blue-600">
            <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
            <h1 class="text-white text-xl font-bold">SocialFlow</h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold"><?php echo strtoupper(substr($client['first_name'], 0, 1)); ?></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-900"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></p>
                        <p class="text-xs text-blue-700">Client</p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="publications.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-newspaper mr-3"></i>
                    Mes Publications
                </a>
                <a href="content_approval.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-200 rounded-lg">
                    <i class="fas fa-check-circle mr-3 text-blue-800"></i>
                    Approbations
                </a>
                <a href="statistics.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Statistiques
                </a>
                <a href="subscription.php" class="flex items-center px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-credit-card mr-3"></i>
                    Abonnement
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
        <header class="bg-blue-200 shadow-sm border-b border-blue-300">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-2xl font-semibold text-blue-900">Approbation de Contenu</h1>
                    <p class="text-sm text-blue-700">Approuvez ou rejetez les propositions de contenu de votre Community Manager</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <button class="p-2 text-blue-600 hover:text-blue-800 relative">
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

            <!-- Contenu en attente d'approbation -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">
                    <i class="fas fa-clock text-orange-600 mr-2"></i>En Attente d'Approbation
                </h2>
                
                <?php if (empty($pending_content)): ?>
                    <div class="content-card rounded-lg shadow p-8 text-center">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                        <p class="text-gray-500">Aucun contenu en attente d'approbation</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($pending_content as $content): ?>
                            <div class="content-card rounded-lg shadow p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($content['title']); ?></h3>
                                    <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                                        En attente
                                    </span>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-gray-600 text-sm mb-2">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($content['first_name'] . ' ' . $content['last_name']); ?>
                                    </p>
                                    <p class="text-gray-600 text-sm mb-2">
                                        <i class="fas fa-globe mr-1"></i>
                                        <?php echo ucfirst($content['platform']); ?>
                                    </p>
                                    <?php if ($content['scheduled_date']): ?>
                                        <p class="text-gray-600 text-sm mb-2">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($content['scheduled_date'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars($content['content'])); ?></p>
                                    <?php if ($content['hashtags']): ?>
                                        <p class="text-blue-600 text-sm mt-2"><?php echo htmlspecialchars($content['hashtags']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button onclick="openApprovalModal(<?php echo $content['id']; ?>, 'approve')" 
                                            class="flex-1 bg-green-600 text-white py-2 px-3 rounded text-sm hover:bg-green-700">
                                        <i class="fas fa-check mr-1"></i>Approuver
                                    </button>
                                    <button onclick="openApprovalModal(<?php echo $content['id']; ?>, 'reject')" 
                                            class="flex-1 bg-red-600 text-white py-2 px-3 rounded text-sm hover:bg-red-700">
                                        <i class="fas fa-times mr-1"></i>Rejeter
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Historique des approbations -->
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-6">
                    <i class="fas fa-history text-blue-600 mr-2"></i>Historique des Approbations
                </h2>
                
                <?php if (empty($approval_history)): ?>
                    <div class="content-card rounded-lg shadow p-8 text-center">
                        <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Aucun historique d'approbation</p>
                    </div>
                <?php else: ?>
                    <div class="content-card rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Community Manager</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plateforme</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($approval_history as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo ucfirst($item['platform']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($item['approval_status'] === 'approved'): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                                        <i class="fas fa-check mr-1"></i>Approuvé
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">
                                                        <i class="fas fa-times mr-1"></i>Rejeté
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($item['approved_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal d'approbation -->
    <div id="approvalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="content-card rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modalTitle">Approuver le contenu</h3>
                    
                    <form method="POST" id="approvalForm">
                        <input type="hidden" name="post_id" id="modalPostId">
                        <input type="hidden" name="action" id="modalAction">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Commentaires (optionnel)</label>
                            <textarea name="comments" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Ajoutez des commentaires..."></textarea>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" id="modalSubmitBtn" 
                                    class="flex-1 py-2 px-4 rounded-md text-white font-medium">
                            </button>
                            <button type="button" onclick="closeApprovalModal()" 
                                    class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openApprovalModal(postId, action) {
            document.getElementById('modalPostId').value = postId;
            document.getElementById('modalAction').value = action;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('modalSubmitBtn');
            
            if (action === 'approve') {
                title.textContent = 'Approuver le contenu';
                submitBtn.textContent = 'Approuver';
                submitBtn.className = 'flex-1 py-2 px-4 rounded-md text-white font-medium bg-green-600 hover:bg-green-700';
            } else {
                title.textContent = 'Rejeter le contenu';
                submitBtn.textContent = 'Rejeter';
                submitBtn.className = 'flex-1 py-2 px-4 rounded-md text-white font-medium bg-red-600 hover:bg-red-700';
            }
            
            modal.classList.remove('hidden');
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
        }
        
        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });
    </script>
        </main>
    </div>
</body>
</html>
