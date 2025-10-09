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
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si c'est une demande de récupération de mot de passe
    if (isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
        $email = sanitize_input($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error_message = 'Veuillez saisir votre adresse email.';
        } elseif (!validate_email($email)) {
            $error_message = 'Adresse email invalide.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Générer un token de récupération
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Sauvegarder le token (vous devrez créer une table password_resets)
                    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                    $stmt->execute([$email, $reset_token, $expires_at, $reset_token, $expires_at]);
                    
                    // Ici, vous devriez envoyer un email avec le lien de réinitialisation
                    // Pour la démo, on affiche juste un message de succès
                    $success_message = 'Un email de récupération a été envoyé à ' . $email . '. Vérifiez votre boîte de réception.';
                    
                    // Logger l'activité
                    log_activity($user['id'], 'password_reset_requested', 'Demande de récupération de mot de passe');
                } else {
                    $error_message = 'Aucun compte trouvé avec cette adresse email.';
                }
            } catch (Exception $e) {
                $error_message = 'Erreur lors de la récupération. Veuillez réessayer.';
                error_log("Erreur récupération mot de passe: " . $e->getMessage());
            }
        }
    } else {
        // Connexion normale
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .hero-bg {
            background-image: 
                linear-gradient(135deg, rgba(30, 58, 138, 0.9), rgba(59, 130, 246, 0.8)),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/><circle cx="200" cy="150" r="3" fill="rgba(255,255,255,0.2)"/><circle cx="800" cy="300" r="2" fill="rgba(255,255,255,0.15)"/><circle cx="1000" cy="600" r="4" fill="rgba(255,255,255,0.1)"/><circle cx="300" cy="700" r="2" fill="rgba(255,255,255,0.2)"/><circle cx="900" cy="100" r="3" fill="rgba(255,255,255,0.15)"/></svg>');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        body {
            overflow-y: auto !important;
            height: auto !important;
        }
        
        @media (max-height: 800px) {
            .hero-bg {
                min-height: auto;
                padding: 1rem 0;
            }
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Amélioration de la visibilité des titres */
        .text-white {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            font-weight: 600;
        }
        
        h1, h2, h3 {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
            font-weight: 700;
        }
        
        /* Labels plus contrastés */
        label {
            color: #1f2937 !important;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .btn-modern {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .pulse-glow {
            animation: pulseGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes pulseGlow {
            from { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
            to { box-shadow: 0 0 40px rgba(59, 130, 246, 0.8); }
        }
    </style>
</head>
<body class="hero-bg min-h-screen flex items-center justify-center p-4 relative">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/5 rounded-full blur-3xl floating-animation"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white/5 rounded-full blur-3xl floating-animation" style="animation-delay: 3s;"></div>
    </div>
    
    <div class="w-full max-w-lg relative z-10">
        <!-- Logo et titre -->
        <div class="text-center mb-4 fade-in-up">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-400 to-blue-600 rounded-2xl mb-3 pulse-glow">
                <i class="fas fa-share-alt text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black text-white mb-2 tracking-tight">SocialFlow</h1>
            <p class="text-white/90 text-sm">Accédez à votre espace</p>
        </div>

        <!-- Formulaire de connexion -->
        <div class="glass-effect rounded-2xl p-6 fade-in-up">
            <div class="text-center mb-4">
                <h2 class="text-xl font-bold text-white mb-1">Connexion</h2>
                <p class="text-white/80 text-sm">Heureux de vous revoir</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="bg-red-500/20 border border-red-400/30 text-red-100 px-6 py-4 rounded-xl mb-6 backdrop-blur-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 text-red-400 text-lg"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="bg-green-500/20 border border-green-400/30 text-green-100 px-6 py-4 rounded-xl mb-6 backdrop-blur-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-400 text-lg"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Sélection du rôle -->
                <div>
                    <label class="block text-white text-sm font-semibold mb-3">
                        <i class="fas fa-user-tag mr-2 text-blue-400"></i>Type de compte
                    </label>
                    <select name="role" class="form-input w-full px-4 py-4 rounded-xl text-white focus:outline-none">
                        <option value="" class="text-gray-800">Sélectionnez votre rôle</option>
                        <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?> class="text-gray-800">Client</option>
                        <option value="community_manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'community_manager') ? 'selected' : ''; ?> class="text-gray-800">Community Manager</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?> class="text-gray-800">Administrateur</option>
                    </select>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-white text-sm font-semibold mb-3">
                        <i class="fas fa-envelope mr-2 text-blue-400"></i>Adresse email
                    </label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="form-input w-full px-4 py-4 rounded-xl  focus:outline-none"
                           placeholder="votre@email.com">
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-white text-sm font-semibold mb-3">
                        <i class="fas fa-lock mr-2 text-blue-400"></i>Mot de passe
                    </label>
                    <input type="password" name="password" required 
                           class="form-input w-full px-4 py-4 rounded-xl  focus:outline-none"
                           placeholder="Votre mot de passe">
                </div>

                <!-- Bouton de connexion -->
                <button type="submit" class="btn-modern w-full text-white font-bold py-4 px-6 rounded-xl focus:outline-none focus:ring-2 focus:ring-white/50">
                    <i class="fas fa-sign-in-alt mr-3"></i>Se connecter
                </button>
            </form>

            <!-- Formulaire de récupération de mot de passe -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/20"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-transparent text-white/60">Mot de passe oublié ?</span>
            </div>
        </div>

                <form method="POST" class="mt-4">
                    <input type="hidden" name="action" value="forgot_password">
                    <div class="flex space-x-2">
                        <input type="email" name="email" required 
                               class="form-input flex-1 px-3 py-2 rounded-lg focus:outline-none text-sm"
                               placeholder="Votre adresse email">
                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                            <i class="fas fa-paper-plane mr-1"></i>Récupérer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Liens -->
            <div class="mt-8 text-center">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/20"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-transparent text-white/60">Options</span>
                    </div>
                </div>
                <div class="mt-4 space-y-3">
                    <a href="register.php" class="block text-white/90 hover:text-white transition duration-300 font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Pas encore de compte ? S'inscrire
                    </a>
                    <a href="forgot-password.php" class="block text-white/90 hover:text-white transition duration-300 font-medium">
                        <i class="fas fa-key mr-2"></i>Mot de passe oublié ?
                    </a>
                </div>
            </div>
        </div>

        <!-- Comptes de démonstration -->
        <div class="mt-8 glass-effect rounded-2xl p-8 fade-in-up">
            <h3 class="text-white font-bold mb-6 text-center text-lg">
                <i class="fas fa-key mr-2 text-blue-400"></i>Comptes de démonstration
            </h3>
            <div class="space-y-4">
                <div class="bg-white/10 rounded-xl p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-user mr-3 text-green-400"></i>
                        <span class="text-white font-semibold">Client</span>
                    </div>
                    <div class="text-white/80 text-sm">
                        <div>Email: <span class="font-mono">client@socialflow.com</span></div>
                        <div>Mot de passe: <span class="font-mono">password</span></div>
                    </div>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-users mr-3 text-blue-400"></i>
                        <span class="text-white font-semibold">Community Manager</span>
                    </div>
                    <div class="text-white/80 text-sm">
                        <div>Email: <span class="font-mono">cm@socialflow.com</span></div>
                        <div>Mot de passe: <span class="font-mono">password</span></div>
                    </div>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-crown mr-3 text-yellow-400"></i>
                        <span class="text-white font-semibold">Administrateur</span>
                </div>
                    <div class="text-white/80 text-sm">
                        <div>Email: <span class="font-mono">admin@socialflow.com</span></div>
                        <div>Mot de passe: <span class="font-mono">password</span></div>
                </div>
            </div>
            </div>
        </div>

        <!-- Retour à l'accueil -->
        <div class="text-center mt-8">
            <a href="../index.php" class="inline-flex items-center text-white/80 hover:text-white transition duration-300 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour à l'accueil
            </a>
        </div>
    </div>

    <script>
        // Animation d'entrée améliorée
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in-up');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                    element.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 100 + (index * 200));
            });
        });

        // Validation en temps réel de l'email
        document.querySelector('input[name="email"]').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                this.style.boxShadow = 'none';
            }
        });

        // Effet de focus amélioré pour tous les inputs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Animation du bouton au survol
        document.querySelector('.btn-modern').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });
        
        document.querySelector('.btn-modern').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });

        // Auto-remplissage des comptes de démonstration
        document.querySelectorAll('.bg-white\\/10').forEach((card, index) => {
            card.addEventListener('click', function() {
                const emails = ['client@socialflow.com', 'cm@socialflow.com', 'admin@socialflow.com'];
                const roles = ['client', 'community_manager', 'admin'];
                
                document.querySelector('input[name="email"]').value = emails[index];
                document.querySelector('input[name="password"]').value = 'password';
                document.querySelector('select[name="role"]').value = roles[index];
                
                // Animation de confirmation
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            // Effet hover pour les cartes
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.background = 'rgba(255, 255, 255, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.background = 'rgba(255, 255, 255, 0.1)';
            });
        });
    </script>
</body>
</html>
