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

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
        $error_message = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!validate_email($email)) {
        $error_message = 'Adresse email invalide.';
    } elseif (!empty($phone) && !validate_phone($phone)) {
        $error_message = 'Numéro de téléphone invalide.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Les mots de passe ne correspondent pas.';
    } elseif (!in_array($role, ['client', 'community_manager'])) {
        $error_message = 'Rôle invalide.';
    } elseif (!$terms) {
        $error_message = 'Vous devez accepter les conditions d\'utilisation.';
    } else {
        try {
            $db = getDB();
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = 'Cette adresse email est déjà utilisée.';
            } else {
                // Créer l'utilisateur
                $password_hash = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, 'active', FALSE)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $role]);
                
                $user_id = $db->lastInsertId();
                
                // Ajouter les paramètres par défaut
                $default_settings = [
                    ['notifications_email', 'true'],
                    ['notifications_push', 'true'],
                    ['language', 'fr']
                ];
                
                foreach ($default_settings as $setting) {
                    $stmt = $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $setting[0], $setting[1]]);
                }
                
                // Créer une notification de bienvenue
                $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, 'Bienvenue sur SocialFlow !', 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.', 'success']);
                
                // Logger l'activité
                log_activity($user_id, 'account_created', 'Nouveau compte créé');
                
                $success_message = 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.';
                
                // Rediriger vers la page de connexion après 3 secondes
                header("refresh:3;url=login.php");
            }
        } catch (Exception $e) {
            $error_message = 'Erreur lors de la création du compte. Veuillez réessayer.';
            error_log("Erreur d'inscription: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - SocialFlow</title>
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
            <p class="text-white/80">Créez votre compte</p>
        </div>

        <!-- Formulaire d'inscription -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <h2 class="text-2xl font-semibold text-white mb-6 text-center">Inscription</h2>
            
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <!-- Sélection du rôle -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-user-tag mr-2"></i>Type de compte *
                    </label>
                    <select name="role" required class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent">
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?>>Client</option>
                        <option value="community_manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'community_manager') ? 'selected' : ''; ?>>Community Manager</option>
                    </select>
                </div>

                <!-- Prénom et Nom -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white text-sm font-medium mb-2">
                            <i class="fas fa-user mr-2"></i>Prénom *
                        </label>
                        <input type="text" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                               placeholder="Prénom">
                    </div>
                    <div>
                        <label class="block text-white text-sm font-medium mb-2">
                            <i class="fas fa-user mr-2"></i>Nom *
                        </label>
                        <input type="text" name="last_name" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                               placeholder="Nom">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adresse email *
                    </label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="votre@email.com">
                </div>

                <!-- Téléphone -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-phone mr-2"></i>Numéro de téléphone
                    </label>
                    <input type="tel" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="+33 6 12 34 56 78">
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-lock mr-2"></i>Mot de passe *
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="Minimum 8 caractères">
                </div>

                <!-- Confirmation mot de passe -->
                <div>
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-lock mr-2"></i>Confirmer le mot de passe *
                    </label>
                    <input type="password" name="confirm_password" required 
                           class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent"
                           placeholder="Répétez votre mot de passe">
                </div>

                <!-- Conditions d'utilisation -->
                <div class="flex items-start">
                    <input type="checkbox" name="terms" required 
                           class="mt-1 mr-3 rounded border-white/20 bg-white/10 text-white focus:ring-white/50">
                    <label class="text-white/80 text-sm">
                        J'accepte les <a href="#" class="text-white hover:underline">conditions d'utilisation</a> et la <a href="#" class="text-white hover:underline">politique de confidentialité</a> *
                    </label>
                </div>

                <!-- Bouton d'inscription -->
                <button type="submit" class="w-full bg-white text-purple-600 hover:bg-gray-100 font-semibold py-3 px-4 rounded-lg transition duration-300 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <i class="fas fa-user-plus mr-2"></i>Créer mon compte
                </button>
            </form>

            <!-- Lien de connexion -->
            <div class="mt-6 text-center">
                <a href="login.php" class="text-white/80 hover:text-white text-sm transition duration-300">
                    <i class="fas fa-sign-in-alt mr-1"></i>Déjà un compte ? Se connecter
                </a>
            </div>
        </div>

        <!-- Retour à l'accueil -->
        <div class="text-center mt-6">
            <a href="../index.php" class="text-white/80 hover:text-white transition duration-300">
                <i class="fas fa-arrow-left mr-2"></i>Retour à l'accueil
            </a>
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

        // Validation en temps réel du mot de passe
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }
        });
    </script>
</body>
</html>
