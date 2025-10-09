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
            case 'create':
            case 'update':
                $title = sanitize_input($_POST['title'] ?? '');
                $content = sanitize_input($_POST['content'] ?? '');
                $client_id = (int)($_POST['client_id'] ?? 0);
                $platforms = $_POST['platforms'] ?? [];
                $scheduled_at = $_POST['scheduled_at'] ?? null;
                $status = sanitize_input($_POST['status'] ?? '');
                
                // Validation spécifique pour la création
                if ($action === 'create' && empty($status)) {
                    $error_message = 'Veuillez sélectionner un statut de publication.';
                } elseif ($status === 'scheduled' && empty($scheduled_at)) {
                    $error_message = 'Veuillez sélectionner une date de programmation pour les publications programmées.';
                } elseif (empty($title) || empty($content) || $client_id <= 0) {
                    $error_message = 'Veuillez remplir tous les champs obligatoires.';
                } else {
                    $platforms_json = json_encode($platforms);
                    $media_files = [];
                    
                    // Traitement des fichiers médias
                    if (isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'][0])) {
                        $upload_dir = '../uploads/posts/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/avi', 'video/mov'];
                        $max_size = 10 * 1024 * 1024; // 10MB
                        
                        for ($i = 0; $i < count($_FILES['media_files']['name']); $i++) {
                            if ($_FILES['media_files']['error'][$i] === UPLOAD_ERR_OK) {
                                $file_type = $_FILES['media_files']['type'][$i];
                                $file_size = $_FILES['media_files']['size'][$i];
                                
                                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                                    $file_extension = pathinfo($_FILES['media_files']['name'][$i], PATHINFO_EXTENSION);
                                    $new_filename = 'post_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                    $upload_path = $upload_dir . $new_filename;
                                    
                                    if (move_uploaded_file($_FILES['media_files']['tmp_name'][$i], $upload_path)) {
                                        $media_files[] = [
                                            'filename' => $new_filename,
                                            'original_name' => $_FILES['media_files']['name'][$i],
                                            'type' => $file_type,
                                            'size' => $file_size
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    
                    $media_json = json_encode($media_files);
                    
                    if ($action === 'create') {
                        $stmt = $db->prepare("
                            INSERT INTO posts (title, content, client_id, community_manager_id, platforms, scheduled_at, status, media_files) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$title, $content, $client_id, $user_id, $platforms_json, $scheduled_at, $status, $media_json]);
                        $post_id = $db->lastInsertId();
                        
                        // Logger l'activité
                        log_activity($user_id, 'post_created', "Publication créée: $title");
                        
                        $success_message = 'Publication créée avec succès.';
                    } else {
                        $post_id = (int)($_POST['post_id'] ?? 0);
                        $stmt = $db->prepare("
                            UPDATE posts SET title = ?, content = ?, client_id = ?, platforms = ?, scheduled_at = ?, status = ?, media_files = ? 
                            WHERE id = ? AND community_manager_id = ?
                        ");
                        $stmt->execute([$title, $content, $client_id, $platforms_json, $scheduled_at, $status, $media_json, $post_id, $user_id]);
                        
                        // Logger l'activité
                        log_activity($user_id, 'post_updated', "Publication mise à jour: $title");
                        
                        $success_message = 'Publication mise à jour avec succès.';
                    }
                }
                break;
                
            case 'delete':
                $post_id = (int)($_POST['post_id'] ?? 0);
                if ($post_id > 0) {
                    $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND community_manager_id = ?");
                    $stmt->execute([$post_id, $user_id]);
                    
                    // Logger l'activité
                    log_activity($user_id, 'post_deleted', "Publication supprimée: ID $post_id");
                    
                    $success_message = 'Publication supprimée avec succès.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        error_log("Erreur posts CM: " . $e->getMessage());
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
    $status_filter = $_GET['status'] ?? 'all';
    $client_filter = $_GET['client_id'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $action = $_GET['action'] ?? 'list';
    $post_id = $_GET['id'] ?? 0;
    
    // Construire la requête avec filtres
    $where_conditions = ["p.community_manager_id = ?"];
    $params = [$user_id];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "p.status = ?";
        $params[] = $status_filter;
    }
    
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
    
    // Publications avec filtres
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name 
        FROM posts p 
        INNER JOIN users u ON p.client_id = u.id 
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Clients assignés
    $stmt = $db->prepare("
        SELECT u.* FROM users u 
        INNER JOIN client_assignments ca ON u.id = ca.client_id 
        WHERE ca.community_manager_id = ? AND ca.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$user_id]);
    $clients = $stmt->fetchAll();
    
    // Post à éditer
    $post_to_edit = null;
    if ($action === 'edit' && $post_id > 0) {
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND community_manager_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $post_to_edit = $stmt->fetch();
    }
    
    // Statistiques des publications
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts
        FROM posts 
        WHERE community_manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données posts: " . $e->getMessage());
    $cm = null;
    $posts = [];
    $clients = [];
    $post_to_edit = null;
    $stats = ['total_posts' => 0, 'published_posts' => 0, 'scheduled_posts' => 0, 'draft_posts' => 0];
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications - SocialFlow</title>
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
        .post-card {
            transition: all 0.2s ease;
        }
        .post-card:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-blue-50">
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
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="clients.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    Mes Clients
                </a>
                <a href="posts.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-green-100 rounded-lg">
                    <i class="fas fa-newspaper mr-3 text-green-600"></i>
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
                    <h1 class="text-2xl font-semibold text-gray-900">
                        <?php echo $action === 'create' ? 'Nouvelle Publication' : ($action === 'edit' ? 'Modifier Publication' : 'Publications'); ?>
                    </h1>
                    <p class="text-sm text-gray-600">
                        <?php echo $action === 'create' ? 'Créez une nouvelle publication pour vos clients' : ($action === 'edit' ? 'Modifiez votre publication' : 'Gérez toutes vos publications'); ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($action !== 'create' && $action !== 'edit'): ?>
                        <a href="posts.php?action=create" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                            <i class="fas fa-plus mr-2"></i>Nouvelle Publication
                        </a>
                    <?php endif; ?>
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

            <?php if ($action === 'create' || $action === 'edit'): ?>
                <!-- Formulaire de création/édition -->
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $action; ?>">
                        <?php if ($action === 'edit' && $post_to_edit): ?>
                            <input type="hidden" name="post_id" value="<?php echo $post_to_edit['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-2">
                                <label for="title" class="block text-xs font-medium text-gray-700 mb-1">Titre *</label>
                                <input type="text" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($post_to_edit['title'] ?? ''); ?>" 
                                       required
                                       class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="client_id" class="block text-xs font-medium text-gray-700 mb-1">Client *</label>
                                <select id="client_id" name="client_id" required
                                        class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="">Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" 
                                                <?php echo (isset($post_to_edit['client_id']) && $post_to_edit['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($action === 'create'): ?>
                            <input type="hidden" id="selected-status" name="status" value="">
                            <?php else: ?>
                            <div>
                                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Statut</label>
                                <select id="status" name="status"
                                        class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="draft" <?php echo (isset($post_to_edit['status']) && $post_to_edit['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="scheduled" <?php echo (isset($post_to_edit['status']) && $post_to_edit['status'] === 'scheduled') ? 'selected' : ''; ?>>Programmé</option>
                                    <option value="published" <?php echo (isset($post_to_edit['status']) && $post_to_edit['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <label for="scheduled_at" class="block text-xs font-medium text-gray-700 mb-1">Date de publication</label>
                                <input type="datetime-local" id="scheduled_at" name="scheduled_at" 
                                       value="<?php echo isset($post_to_edit['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($post_to_edit['scheduled_at'])) : ''; ?>"
                                       class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Plateformes</label>
                                <div class="space-y-1">
                                    <?php 
                                    $platforms = ['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok'];
                                    $selected_platforms = isset($post_to_edit['platforms']) ? json_decode($post_to_edit['platforms'], true) : [];
                                    ?>
                                    <?php foreach ($platforms as $platform): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="platforms[]" value="<?php echo $platform; ?>"
                                                   <?php echo in_array($platform, $selected_platforms) ? 'checked' : ''; ?>
                                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-1 text-xs text-gray-700"><?php echo ucfirst($platform); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="lg:col-span-2">
                                <label for="content" class="block text-xs font-medium text-gray-700 mb-1">Contenu *</label>
                                <textarea id="content" name="content" rows="4" required
                                          class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"><?php echo htmlspecialchars($post_to_edit['content'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="lg:col-span-2">
                                <label for="media_files" class="block text-xs font-medium text-gray-700 mb-1">Médias (Images/Vidéos)</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 text-center hover:border-green-400 transition-colors duration-200" id="media-dropzone">
                                    <input type="file" id="media_files" name="media_files[]" multiple accept="image/*,video/*" class="hidden">
                                    <div id="media-dropzone-content">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-gray-600 text-sm mb-1">Glissez-déposez vos fichiers ici ou</p>
                                        <button type="button" onclick="document.getElementById('media_files').click()" class="bg-green-600 text-white px-3 py-1.5 text-sm rounded-lg hover:bg-green-700 transition duration-300">
                                            <i class="fas fa-plus mr-1"></i>Choisir des fichiers
                                        </button>
                                        <p class="text-xs text-gray-500 mt-1">Formats: JPG, PNG, GIF, WebP, MP4, AVI, MOV (Max: 10MB)</p>
                                    </div>
                                    <div id="media-preview" class="hidden mt-2">
                                        <h4 class="text-xs font-medium text-gray-700 mb-1">Fichiers sélectionnés:</h4>
                                        <div id="media-list" class="grid grid-cols-2 md:grid-cols-4 gap-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-3 mt-4">
                            <a href="posts.php" class="bg-gray-600 text-white px-4 py-1.5 text-sm rounded-lg hover:bg-gray-700 transition duration-300">
                                Annuler
                            </a>
                            <?php if ($action === 'create'): ?>
<<<<<<< HEAD
                            <button type="button" id="publish-btn" class="bg-green-600 text-white px-4 py-1.5 text-sm rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-paper-plane mr-1"></i>
                                Publier
                            </button>
                            <?php else: ?>
                            <button type="submit" class="bg-green-600 text-white px-4 py-1.5 text-sm rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-save mr-1"></i>
                                Mettre à jour
                            </button>
=======
                            <button type="button" id="publish-btn" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Publier
                            </button>
                            <?php else: ?>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-save mr-2"></i>
                                Mettre à jour
                            </button>
>>>>>>> 1d200c683625b15a36efea56ea544c54e79cdde8
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Liste des publications -->
                
                <!-- Statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100">
                                <i class="fas fa-newspaper text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_posts']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Publiées</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['published_posts']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Programmées</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['scheduled_posts']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-gray-100">
                                <i class="fas fa-edit text-gray-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Brouillons</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['draft_posts']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <form method="GET" class="flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-64">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Rechercher dans les publications..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                                <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publiées</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Programmées</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Brouillons</option>
                            </select>
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
                        
                        <a href="posts.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                            <i class="fas fa-times mr-2"></i>Effacer
                        </a>
                    </form>
                </div>

                <!-- Liste des publications -->
                <div class="bg-white rounded-lg shadow-sm">
                    <?php if (!empty($posts)): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($posts as $post): ?>
                                <div class="post-card p-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-3">
                                                <h3 class="text-lg font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($post['title'] ?: 'Sans titre'); ?>
                                                </h3>
                                                <?php
                                                $status_colors = [
                                                    'published' => 'bg-green-100 text-green-800',
                                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                    'draft' => 'bg-gray-100 text-gray-800',
                                                    'failed' => 'bg-red-100 text-red-800'
                                                ];
                                                $status_color = $status_colors[$post['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                                    <?php echo ucfirst($post['status']); ?>
                                                </span>
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
                                                    <i class="fas fa-clock mr-2"></i>
                                                    <span><?php echo time_ago($post['created_at']); ?></span>
                                                </div>
                                                <?php if ($post['scheduled_at']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar mr-2"></i>
                                                        <span>Programmé: <?php echo format_date_fr($post['scheduled_at'], 'd/m/Y H:i'); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($post['platforms']): ?>
                                                <div class="flex items-center space-x-2 mb-3">
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
                                            
                                            <?php if ($post['media_files'] && $post['media_files'] !== '[]'): ?>
                                                <div class="flex items-center space-x-2 mb-3">
                                                    <span class="text-sm text-gray-500">Médias:</span>
                                                    <?php
                                                    $media_files = json_decode($post['media_files'], true);
                                                    if (is_array($media_files) && !empty($media_files)):
                                                        $media_count = count($media_files);
                                                    ?>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">
                                                                <i class="fas fa-images mr-1"></i><?php echo $media_count; ?> fichier<?php echo $media_count > 1 ? 's' : ''; ?>
                                                            </span>
                                                            <div class="flex space-x-1">
                                                                <?php foreach (array_slice($media_files, 0, 3) as $media): ?>
                                                                    <?php if (strpos($media['type'], 'image/') === 0): ?>
                                                                        <img src="../uploads/posts/<?php echo htmlspecialchars($media['filename']); ?>" 
                                                                             alt="<?php echo htmlspecialchars($media['original_name']); ?>" 
                                                                             class="w-8 h-8 object-cover rounded border border-gray-200">
                                                                    <?php else: ?>
                                                                        <div class="w-8 h-8 bg-gray-200 rounded border border-gray-200 flex items-center justify-center">
                                                                            <i class="fas fa-video text-xs text-gray-500"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php if (count($media_files) > 3): ?>
                                                                    <div class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                                                        <span class="text-xs text-gray-500">+<?php echo count($media_files) - 3; ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
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
                                            <a href="posts.php?action=view&id=<?php echo $post['id']; ?>" 
                                               class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50" 
                                               title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette publication ?')">
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
                            <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune publication trouvée</h3>
                            <p class="text-gray-500">
                                <?php if (!empty($search) || $status_filter !== 'all' || $client_filter !== 'all'): ?>
                                    Aucune publication ne correspond à vos critères de recherche.
                                <?php else: ?>
                                    Vous n'avez pas encore créé de publications.
                                <?php endif; ?>
                            </p>
                            <a href="posts.php?action=create" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                                <i class="fas fa-plus mr-2"></i>Créer une publication
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal de sélection du statut -->
    <div id="status-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                    <i class="fas fa-paper-plane text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 text-center mb-6">Choisir le statut de publication</h3>
                
                <div class="space-y-3">
                    <button type="button" id="modal-btn-draft" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-4 rounded-lg border-2 border-gray-300 hover:border-gray-400 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-edit mr-3 text-lg"></i>
                        <div class="text-left">
                            <div class="font-semibold text-base">Brouillon</div>
                            <div class="text-sm text-gray-500">Sauvegarder comme brouillon</div>
                        </div>
                    </button>
                    
                    <button type="button" id="modal-btn-scheduled" class="w-full bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-6 py-4 rounded-lg border-2 border-yellow-300 hover:border-yellow-400 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <div class="text-left">
                            <div class="font-semibold text-base">Programmé</div>
                            <div class="text-sm text-yellow-600">Programmer la publication</div>
                        </div>
                    </button>
                    
                    <button type="button" id="modal-btn-published" class="w-full bg-green-100 hover:bg-green-200 text-green-700 px-6 py-4 rounded-lg border-2 border-green-300 hover:border-green-400 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-3 text-lg"></i>
                        <div class="text-left">
                            <div class="font-semibold text-base">Publié immédiatement</div>
                            <div class="text-sm text-green-600">Publier maintenant</div>
                        </div>
                    </button>
                </div>
                
                <div class="flex justify-center mt-6">
                    <button type="button" id="close-modal" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-300">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
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

        // Gestion du modal de sélection du statut
        document.addEventListener('DOMContentLoaded', function() {
            const publishBtn = document.getElementById('publish-btn');
            const modal = document.getElementById('status-modal');
            const closeModalBtn = document.getElementById('close-modal');
            const selectedStatusInput = document.getElementById('selected-status');
            const form = document.querySelector('form');
            
            // Boutons du modal
            const modalDraftBtn = document.getElementById('modal-btn-draft');
            const modalScheduledBtn = document.getElementById('modal-btn-scheduled');
            const modalPublishedBtn = document.getElementById('modal-btn-published');
            
            if (publishBtn && modal && selectedStatusInput && form) {
                // Ouvrir le modal quand on clique sur "Publier"
                publishBtn.addEventListener('click', function() {
                    modal.classList.remove('hidden');
                });
                
                // Fermer le modal
                function closeModal() {
                    modal.classList.add('hidden');
                }
                
                closeModalBtn.addEventListener('click', closeModal);
                
                // Fermer le modal en cliquant à l'extérieur
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
                
                // Fermer le modal avec la touche Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
                
                // Gestion des sélections dans le modal
                function handleStatusSelection(status, buttonText, buttonClass) {
                    selectedStatusInput.value = status;
                    
                    // Mettre à jour le bouton "Publier"
                    publishBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>' + buttonText;
                    publishBtn.className = buttonClass + ' text-white px-6 py-2 rounded-lg transition duration-300';
                    
                    // Fermer le modal
                    closeModal();
                    
                    // Soumettre le formulaire
                    form.submit();
                }
                
                // Événements pour les boutons du modal
                modalDraftBtn.addEventListener('click', function() {
                    handleStatusSelection('draft', 'Créer comme brouillon', 'bg-gray-600 hover:bg-gray-700');
                });
                
                modalScheduledBtn.addEventListener('click', function() {
                    // Vérifier si une date est sélectionnée
                    const scheduledAtInput = document.getElementById('scheduled_at');
                    if (!scheduledAtInput.value) {
                        alert('Veuillez sélectionner une date de programmation avant de continuer.');
                        closeModal();
                        scheduledAtInput.focus();
                        return;
                    }
                    handleStatusSelection('scheduled', 'Programmer la publication', 'bg-yellow-600 hover:bg-yellow-700');
                });
                
                modalPublishedBtn.addEventListener('click', function() {
                    handleStatusSelection('published', 'Publier immédiatement', 'bg-green-600 hover:bg-green-700');
                });
            }
<<<<<<< HEAD
            
            // Gestion du drag & drop pour les médias
            const dropzone = document.getElementById('media-dropzone');
            const fileInput = document.getElementById('media_files');
            const mediaPreview = document.getElementById('media-preview');
            const mediaList = document.getElementById('media-list');
            const dropzoneContent = document.getElementById('media-dropzone-content');
            
            if (dropzone && fileInput) {
                // Empêcher le comportement par défaut du navigateur
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, preventDefaults, false);
                    document.body.addEventListener(eventName, preventDefaults, false);
                });
                
                // Mettre en évidence la zone de drop
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropzone.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, unhighlight, false);
                });
                
                // Gérer le drop
                dropzone.addEventListener('drop', handleDrop, false);
                
                // Gérer la sélection de fichiers
                fileInput.addEventListener('change', handleFiles);
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                function highlight(e) {
                    dropzone.classList.add('border-green-400', 'bg-green-50');
                }
                
                function unhighlight(e) {
                    dropzone.classList.remove('border-green-400', 'bg-green-50');
                }
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    handleFiles({ target: { files: files } });
                }
                
                function handleFiles(e) {
                    const files = Array.from(e.target.files);
                    if (files.length > 0) {
                        displayMediaPreview(files);
                    }
                }
                
                function displayMediaPreview(files) {
                    mediaList.innerHTML = '';
                    mediaPreview.classList.remove('hidden');
                    dropzoneContent.classList.add('hidden');
                    
                    files.forEach((file, index) => {
                        const fileItem = document.createElement('div');
                        fileItem.className = 'relative bg-gray-100 rounded-lg p-1';
                        
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                fileItem.innerHTML = `
                                    <img src="${e.target.result}" alt="${file.name}" class="w-full h-16 object-cover rounded">
                                    <div class="mt-1">
                                        <p class="text-xs text-gray-600 truncate">${file.name}</p>
                                        <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                                    </div>
                                    <button type="button" onclick="removeFile(${index})" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                            };
                            reader.readAsDataURL(file);
                        } else if (file.type.startsWith('video/')) {
                            fileItem.innerHTML = `
                                <div class="w-full h-16 bg-gray-200 rounded flex items-center justify-center">
                                    <i class="fas fa-video text-lg text-gray-500"></i>
                                </div>
                                <div class="mt-1">
                                    <p class="text-xs text-gray-600 truncate">${file.name}</p>
                                    <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                                </div>
                                <button type="button" onclick="removeFile(${index})" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            `;
                        }
                        
                        mediaList.appendChild(fileItem);
                    });
                }
                
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
                
                // Fonction globale pour supprimer un fichier
                window.removeFile = function(index) {
                    const dt = new DataTransfer();
                    const files = Array.from(fileInput.files);
                    files.splice(index, 1);
                    
                    files.forEach(file => dt.items.add(file));
                    fileInput.files = dt.files;
                    
                    if (files.length === 0) {
                        mediaPreview.classList.add('hidden');
                        dropzoneContent.classList.remove('hidden');
                    } else {
                        displayMediaPreview(files);
                    }
                };
            }
=======
>>>>>>> 1d200c683625b15a36efea56ea544c54e79cdde8
        });
    </script>
</body>
</html>
