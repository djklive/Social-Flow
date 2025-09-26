<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirection si déjà connecté
if (is_logged_in()) {
    $user_role = $_SESSION['user_role'];
    switch ($user_role) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        case 'community_manager':
            header('Location: ../cm/dashboard.php');
            break;
        case 'client':
            header('Location: ../client/dashboard.php');
            break;
    }
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');

    if ($role === '') {
        $role = 'admin';
    }
    
    if (empty($email) || empty($password) || empty($role)) {
        $error_message = 'Tous les champs sont requis.';
    } elseif (!validate_email($email)) {
        $error_message = 'Adresse email invalide.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name, role, status FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch();
            
            if ($user && verify_password($password, $user['password_hash'])) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Mettre à jour la dernière connexion
                    $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);
                    
                    // Logger l'activité
                    log_activity($user['id'], 'login', 'Connexion réussie');
                    
                    // Redirection selon le rôle
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: ../admin/dashboard.php');
                            break;
                        case 'community_manager':
                            header('Location: ../cm/dashboard.php');
                            break;
                        case 'client':
                            header('Location: ../client/dashboard.php');
                            break;
                    }
                    exit();
                } else {
                    $error_message = 'Votre compte est désactivé. Contactez l\'administrateur.';
                }
            } else {
                $error_message = 'Email, mot de passe ou rôle incorrect.';
            }
        } catch (Exception $e) {
            $error_message = 'Erreur de connexion. Veuillez réessayer.';
            error_log("Erreur de connexion: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SocialFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                <i class="fas fa-share-alt text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">SocialFlow</h1>
            <p class="text-white/80">Connectez-vous à votre espace</p>
        </div>

        <!-- Formulaire de connexion -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <h2 class="text-2xl font-semibold text-white mb-6 text-center">Connexion</h2>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Sélection du rôle -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-user-tag mr-2"></i>Type de compte
                    </label>
                    <select name="role" class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-black placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent">
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?>>Client</option>
                        <option value="community_manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'community_manager') ? 'selected' : ''; ?>>Community Manager</option>
                        <!-- <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur</option> -->
                    </select>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse email
                    </label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-black placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="votre@email.com">
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-lock mr-2"></i>Mot de passe
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="Votre mot de passe">
                </div>

                <!-- Bouton de connexion -->
                <button type="submit" class="w-full bg-white text-purple-600 hover:bg-gray-100 font-semibold py-3 px-4 rounded-lg transition duration-300 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </button>
            </form>

            <!-- Liens -->
            <div class="mt-6 text-center space-y-2">
                <a href="register.php" class="text-white/80 hover:text-white text-sm transition duration-300">
                    <i class="fas fa-user-plus mr-1"></i>Pas encore de compte ? S'inscrire
                </a>
                <br>
                <a href="forgot-password.php" class="text-white/80 hover:text-white text-sm transition duration-300">
                    <i class="fas fa-key mr-1"></i>Mot de passe oublié ?
                </a>
            </div>
        </div>

        <!-- Retour à l'accueil -->
        <div class="text-center mt-6">
            <a href="../index.php" class="text-white/80 hover:text-white transition duration-300">
                <i class="fas fa-arrow-left mr-2"></i>Retour à l'accueil
            </a>
        </div>

        <!-- Comptes de démonstration -->
        <div class="mt-8 glass-effect rounded-xl p-6">
            <h3 class="text-white font-semibold mb-4 text-center">Comptes de démonstration</h3>
            <div class="space-y-3 text-sm">
                <div class="text-white/80">
                    <strong>Client:</strong> client@socialflow.com / password
                </div>
                <div class="text-white/80">
                    <strong>Community Manager:</strong> cm@socialflow.com / password
                </div>
                <div class="text-white/80">
                    <strong>Admin:</strong> admin@socialflow.com / password
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.glass-effect');
            form.style.opacity = '0';
            form.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                form.style.transition = 'all 0.6s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>
