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
    $profile_photo = 'default-avatar.png'; // Photo par défaut
    
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
        // Traitement de l'upload de photo de profil
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['profile_photo']['type'];
            $file_size = $_FILES['profile_photo']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.';
            } elseif ($file_size > $max_size) {
                $error_message = 'Le fichier est trop volumineux. Taille maximale: 5MB.';
            } else {
                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $profile_photo = $new_filename;
                } else {
                    $error_message = 'Erreur lors de l\'upload de la photo.';
                }
            }
        }
        
        if (empty($error_message)) {
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
                $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, profile_picture) VALUES (?, ?, ?, ?, ?, ?, 'active', FALSE, ?)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $role, $profile_photo]);
                
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
        <!-- Titre principal -->
        <div class="text-center mb-4 fade-in-up">
            <h1 class="text-2xl font-black text-white mb-2 tracking-tight">SocialFlow</h1>
            <p class="text-white/90 text-sm">Rejoignez la révolution digitale</p>
        </div>

        <!-- Formulaire d'inscription -->
        <div class="glass-effect rounded-2xl p-6 fade-in-up">
            <!-- Photo de profil en haut du formulaire -->
            <div class="text-center mb-4">
                <div class="relative inline-block">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full mb-2 pulse-glow relative overflow-hidden">
                        <img id="profile-preview" src="../uploads/profiles/default-avatar.png" alt="Photo de profil" class="w-full h-full object-cover rounded-full hidden">
                        <i class="fas fa-user text-white text-xl" id="default-icon"></i>
                    </div>
                    <!-- Bouton + pour changer la photo -->
                    <button type="button" onclick="document.getElementById('profile-photo-input').click()" 
                            class="absolute -bottom-1 -right-1 w-6 h-6 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200 hover:scale-110 shadow-lg">
                        <i class="fas fa-plus"></i>
                    </button>
                    <!-- Champ de fichier caché -->
                    <input type="file" id="profile-photo-input" name="profile_photo" accept="image/*" 
                           class="hidden" onchange="updateProfilePreview(this)">
                </div>
                <h2 class="text-xl font-bold text-white mb-1">Créer un compte</h2>
                <p class="text-white/80 text-sm">Commencez votre transformation digitale</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-green-500/20 border border-green-400/30 text-green-100 px-4 py-2 rounded-lg mb-4 backdrop-blur-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-400 text-sm"></i>
                        <span class="font-medium text-sm"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-500/20 border border-red-400/30 text-red-100 px-4 py-2 rounded-lg mb-4 backdrop-blur-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 text-red-400 text-sm"></i>
                        <span class="font-medium text-sm"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <!-- Sélection du rôle -->
                <div>
                    <label class="block text-white text-xs font-semibold mb-1">
                        <i class="fas fa-user-tag mr-1 text-blue-400"></i>Type de compte *
                    </label>
                    <select name="role" required class="form-input w-full px-3 py-2 rounded-lg focus:outline-none text-sm">
                        <option value="" class="text-gray-800">Sélectionnez votre rôle</option>
                        <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?> class="text-gray-800">Client</option>
                        <option value="community_manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'community_manager') ? 'selected' : ''; ?> class="text-gray-800">Community Manager</option>
                    </select>
                </div>

                <!-- Prénom et Nom -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-white text-xs font-semibold mb-1">
                            <i class="fas fa-user mr-1 text-blue-400"></i>Prénom *
                        </label>
                        <input type="text" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               class="form-input w-full px-3 py-2 rounded-lg focus:outline-none text-sm"
                               placeholder="Prénom">
                    </div>
                    <div>
                        <label class="block text-white text-xs font-semibold mb-1">
                            <i class="fas fa-user mr-1 text-blue-400"></i>Nom *
                        </label>
                        <input type="text" name="last_name" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               class="form-input w-full px-3 py-2 rounded-lg focus:outline-none text-sm"
                               placeholder="Nom">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-white text-xs font-semibold mb-1">
                        <i class="fas fa-envelope mr-1 text-blue-400"></i>Adresse email *
                    </label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="form-input w-full px-3 py-2 rounded-lg focus:outline-none text-sm"
                           placeholder="votre@email.com">
                </div>


                <!-- Téléphone -->
                <div>
                    <label class="block text-white text-xs font-semibold mb-1">
                        <i class="fas fa-phone mr-1 text-blue-400"></i>Numéro de téléphone
                    </label>
                    <input type="tel" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="form-input w-full px-3 py-2 rounded-lg focus:outline-none text-sm"
                           placeholder="+237 6 12 34 56 78">
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-white text-sm font-semibold mb-3">
                        <i class="fas fa-lock mr-2 text-blue-400"></i>Mot de passe *
                    </label>
                    <input type="password" name="password" required 
                           class="form-input w-full px-4 py-4 rounded-xl  focus:outline-none"
                           placeholder="Minimum 8 caractères">
                </div>

                <!-- Confirmation mot de passe -->
                <div>
                    <label class="block text-white text-sm font-semibold mb-3">
                        <i class="fas fa-lock mr-2 text-blue-400"></i>Confirmer le mot de passe *
                    </label>
                    <input type="password" name="confirm_password" required 
                           class="form-input w-full px-4 py-4 rounded-xl  focus:outline-none"
                           placeholder="Répétez votre mot de passe">
                </div>

                <!-- Conditions d'utilisation -->
                <div class="flex items-start space-x-3">
                    <input type="checkbox" name="terms" required 
                           class="mt-1 w-5 h-5 rounded border-white/20 bg-white/10 text-blue-600 focus:ring-blue-500 focus:ring-2">
                    <label class="text-white/90 text-sm leading-relaxed">
                        J'accepte les <a href="#" class="text-blue-400 hover:text-blue-300 underline">conditions d'utilisation</a> et la <a href="#" class="text-blue-400 hover:text-blue-300 underline">politique de confidentialité</a> *
                    </label>
                </div>

                <!-- Bouton d'inscription -->
                <button type="submit" class="btn-modern w-full text-white font-bold py-4 px-6 rounded-xl focus:outline-none focus:ring-2 focus:ring-white/50">
                    <i class="fas fa-rocket mr-3"></i>Créer mon compte
                </button>
            </form>

            <!-- Lien de connexion -->
            <div class="mt-8 text-center">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/20"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-transparent text-white/60">Déjà un compte ?</span>
                    </div>
                </div>
                <a href="login.php" class="mt-4 inline-flex items-center text-white/90 hover:text-white transition duration-300 font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
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
            
            // Gestion du chargement de l'image d'arrière-plan
            const heroBg = document.querySelector('.hero-bg');
            if (heroBg) {
                const img = new Image();
                img.onload = function() {
                    console.log('Image d\'arrière-plan chargée avec succès');
                };
                img.onerror = function() {
                    console.log('Erreur de chargement de l\'image, utilisation du fallback');
                    heroBg.style.backgroundImage = 'linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%)';
                    heroBg.style.backgroundSize = '400% 400%';
                    heroBg.style.animation = 'gradientShift 15s ease infinite';
                };
                img.src = '../boost-your-sales-with-effective-digital-marketing-templates-flyers-banners-social-media-graphics_960330-1421.jpg';
            }
        });

        // Validation en temps réel du mot de passe
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                this.style.boxShadow = 'none';
            }
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

        // Fonction de mise à jour de l'aperçu de profil
        function updateProfilePreview(input) {
            const profilePreview = document.getElementById('profile-preview');
            const defaultIcon = document.getElementById('default-icon');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                    profilePreview.classList.remove('hidden');
                    defaultIcon.classList.add('hidden');
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                profilePreview.classList.add('hidden');
                defaultIcon.classList.remove('hidden');
            }
        }

        // Animation du bouton au survol
        document.querySelector('.btn-modern').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });
        
        document.querySelector('.btn-modern').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    </script>
</body>
</html>
