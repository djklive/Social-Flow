<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirection basée sur le rôle de l'utilisateur connecté
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'];
    switch ($user_role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'community_manager':
            header('Location: cm/dashboard.php');
            break;
        case 'client':
            header('Location: client/dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialFlow - Gestion de Contenu Réseaux Sociaux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .modern-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-modern:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            background: rgba(255, 255, 255, 1);
        }
        
        .floating-animation {
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-15px) rotate(1deg); }
            50% { transform: translateY(-25px) rotate(0deg); }
            75% { transform: translateY(-15px) rotate(-1deg); }
        }
        
        .fade-in-up {
            animation: fadeInUp 1.2s ease-out;
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(60px); 
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
        
        .text-gradient {
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb, #f5576c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .hero-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            top: 0;
            left: 0;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: particleFloat 20s infinite linear;
        }
        
        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        .section-divider {
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
            height: 1px;
            margin: 4rem 0;
        }
        
        .feature-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: all 0.3s ease;
        }
        
        .feature-icon:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
        }
        
        .stats-counter {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .testimonial-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .scroll-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            transform-origin: left;
            transform: scaleX(0);
            z-index: 1000;
        }
        
        /* Optimisation de l'image d'arrière-plan */
        .hero-bg-image {
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transition: all 0.3s ease;
            filter: brightness(0.7) blur(1px);
        }
        
        /* Effet parallax pour l'image */
        @media (min-width: 768px) {
            .hero-bg-image {
                background-attachment: fixed;
            }
        }
        
        /* Fallback pour les appareils mobiles */
        @media (max-width: 767px) {
            .hero-bg-image {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body class="modern-gradient min-h-screen">
    <!-- Scroll Progress Indicator -->
    <div class="scroll-indicator" id="scrollIndicator"></div>
    
    <!-- Navigation -->
    <nav class="glass-effect fixed w-full top-0 z-50 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-400 to-blue-600 rounded-xl flex items-center justify-center mr-4 pulse-glow">
                        <i class="fas fa-share-alt text-white text-lg"></i>
                    </div>
                    <h1 class="text-white text-2xl font-bold tracking-tight">SocialFlow</h1>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="#features" class="text-white/90 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 hover:bg-white/10">Fonctionnalités</a>
                    <a href="#pricing" class="text-white/90 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 hover:bg-white/10">Tarifs</a>
                    <a href="#testimonials" class="text-white/90 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 hover:bg-white/10">Témoignages</a>
                </div>
                <div class="flex space-x-4">
                    <a href="auth/login.php" class="text-white/90 hover:text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 hover:bg-white/10">
                        <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                    </a>
                    <a href="auth/register.php" class="btn-modern text-white px-6 py-2 rounded-lg text-sm font-semibold">
                        <i class="fas fa-rocket mr-2"></i>Commencer
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Image Background -->
        <div class="absolute inset-0 z-0">
            <div class="w-full h-full hero-bg-image" 
                 style="background-image: url('boost-your-sales-with-effective-digital-marketing-templates-flyers-banners-social-media-graphics_960330-1421.jpg');">
            </div>
            <!-- Overlay pour améliorer la lisibilité -->
            <div class="absolute inset-0 bg-gradient-to-r from-purple-900/85 via-pink-900/85 to-purple-900/85"></div>
        </div>
        
        <!-- Animated Background Particles -->
        <div class="hero-particles" id="particles"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 pt-32 relative z-10">
            <div class="text-center fade-in-up">
                <div class="mb-8">
                    <span class="inline-block bg-gradient-to-r from-purple-500/30 to-pink-500/30 text-white px-6 py-3 rounded-full text-sm font-medium backdrop-blur-sm border border-white/20">
                        <i class="fas fa-star mr-2 text-yellow-300"></i>Plateforme #1 en Afrique
                    </span>
                </div>
                
                <h1 class="text-5xl md:text-7xl font-black text-white mb-8 leading-tight drop-shadow-2xl">
                    Révolutionnez vos
                    <span class="text-gradient block mt-2 drop-shadow-2xl">Réseaux Sociaux</span>
            </h1>
                
                <p class="text-xl md:text-2xl text-white/95 mb-12 max-w-4xl mx-auto leading-relaxed drop-shadow-lg">
                    SocialFlow transforme votre présence digitale avec des <strong>Community Managers dédiés</strong>, 
                    des <strong>publications automatiques</strong> et des <strong>analyses avancées</strong>.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-6 justify-center mb-16">
                    <a href="auth/register.php" class="btn-modern text-white px-10 py-4 rounded-xl text-lg font-bold transform hover:scale-105">
                        <i class="fas fa-rocket mr-3"></i>Commencer gratuitement
                    </a>
                    <a href="#features" class="glass-effect text-white hover:bg-white/20 px-10 py-4 rounded-xl text-lg font-semibold transition-all duration-300">
                        <i class="fas fa-play mr-3"></i>Voir la démo
                </a>
            </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                    <div class="text-center">
                        <div class="stats-counter drop-shadow-lg">500+</div>
                        <p class="text-white/90 text-lg drop-shadow-md">Clients satisfaits</p>
                    </div>
                    <div class="text-center">
                        <div class="stats-counter drop-shadow-lg">50K+</div>
                        <p class="text-white/90 text-lg drop-shadow-md">Publications créées</p>
                    </div>
                    <div class="text-center">
                        <div class="stats-counter drop-shadow-lg">99%</div>
                        <p class="text-white/90 text-lg drop-shadow-md">Taux de satisfaction</p>
                    </div>
            </div>
        </div>
    </div>
        
        <!-- Scroll Down Indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <i class="fas fa-chevron-down text-white/60 text-2xl"></i>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="bg-gradient-to-b from-white via-purple-50/30 to-pink-50/30 py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-20">
                <span class="inline-block bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 px-4 py-2 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-star mr-2 text-yellow-500"></i>Fonctionnalités Premium
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-gray-900 mb-6">
                    Pourquoi <span class="text-gradient">SocialFlow</span> ?
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Une solution révolutionnaire qui transforme votre présence digitale avec des outils professionnels et un accompagnement personnalisé.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 mb-16">
                <!-- Feature 1 -->
                <div class="text-center card-modern p-10 rounded-2xl">
                    <div class="feature-icon w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-8 floating-animation">
                        <i class="fas fa-users text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Community Manager Dédié</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Un professionnel expérimenté assigné exclusivement à votre compte pour créer du contenu engageant et gérer votre stratégie sociale.
                    </p>
                    <div class="mt-6 flex justify-center">
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-check mr-1"></i>Inclus dans tous les plans
                        </span>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="text-center card-modern p-10 rounded-2xl">
                    <div class="feature-icon w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-8 floating-animation" style="animation-delay: 2s;">
                        <i class="fas fa-chart-line text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Analytics Avancées</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Suivez vos performances en temps réel avec des tableaux de bord intuitifs, des rapports détaillés et des insights actionables.
                    </p>
                    <div class="mt-6 flex justify-center">
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-chart-bar mr-1"></i>Données en temps réel
                        </span>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="text-center card-modern p-10 rounded-2xl">
                    <div class="feature-icon w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-8 floating-animation" style="animation-delay: 4s;">
                        <i class="fas fa-mobile-alt text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Paiements Sécurisés</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Paiement flexible et sécurisé via Mobile Money, Orange Money, MTN Money ou carte bancaire avec facturation automatique.
                    </p>
                    <div class="mt-6 flex justify-center">
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-shield-alt mr-1"></i>100% sécurisé
                        </span>
            </div>
        </div>
    </div>
            
            <!-- Additional Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <i class="fas fa-clock text-blue-600 text-2xl mb-3"></i>
                    <h4 class="font-semibold text-gray-900 mb-2">Publication Automatique</h4>
                    <p class="text-sm text-gray-600">Planifiez vos posts à l'avance</p>
                </div>
                <div class="text-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <i class="fas fa-hashtag text-green-600 text-2xl mb-3"></i>
                    <h4 class="font-semibold text-gray-900 mb-2">Optimisation SEO</h4>
                    <p class="text-sm text-gray-600">Hashtags et mots-clés optimisés</p>
                </div>
                <div class="text-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <i class="fas fa-headset text-purple-600 text-2xl mb-3"></i>
                    <h4 class="font-semibold text-gray-900 mb-2">Support 24/7</h4>
                    <p class="text-sm text-gray-600">Assistance disponible en permanence</p>
                </div>
                <div class="text-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <i class="fas fa-rocket text-orange-600 text-2xl mb-3"></i>
                    <h4 class="font-semibold text-gray-900 mb-2">Croissance Garantie</h4>
                    <p class="text-sm text-gray-600">Augmentation de votre audience</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works Section -->
    <section class="bg-gradient-to-b from-pink-50/30 via-white to-purple-50/30 py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-20">
                <span class="inline-block bg-gradient-to-r from-pink-100 to-purple-100 text-pink-800 px-4 py-2 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-cogs mr-2 text-purple-600"></i>Processus Simple
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-gray-900 mb-6">
                    Comment ça <span class="text-gradient">marche</span> ?
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Trois étapes simples pour transformer votre présence digitale et obtenir des résultats concrets
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-12 relative">
                <!-- Connection Lines -->
                <div class="hidden md:block absolute top-20 left-1/2 transform -translate-x-1/2 w-full h-0.5 bg-gradient-to-r from-purple-200 via-pink-400 to-purple-200"></div>
                
                <!-- Step 1 -->
                <div class="text-center relative z-10">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-500 w-24 h-24 rounded-2xl flex items-center justify-center mx-auto mb-8 text-white text-3xl font-bold shadow-lg">
                        1
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Inscrivez-vous</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Créez votre compte en 2 minutes et choisissez votre plan d'abonnement adapté à vos besoins et votre budget.
                    </p>
                    <div class="mt-6">
                        <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-sm font-medium">
                            <i class="fas fa-user-plus mr-2"></i>Gratuit et rapide
                        </span>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="text-center relative z-10">
                    <div class="bg-gradient-to-r from-pink-500 to-purple-500 w-24 h-24 rounded-2xl flex items-center justify-center mx-auto mb-8 text-white text-3xl font-bold shadow-lg">
                        2
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">CM Dédié</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Un Community Manager professionnel vous est assigné pour créer du contenu engageant et gérer votre stratégie sociale.
                    </p>
                    <div class="mt-6">
                        <span class="bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium">
                            <i class="fas fa-users mr-2"></i>Expert dédié
                        </span>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="text-center relative z-10">
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 w-24 h-24 rounded-2xl flex items-center justify-center mx-auto mb-8 text-white text-3xl font-bold shadow-lg">
                        3
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Résultats</h3>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Consultez vos publications, statistiques et performances depuis votre dashboard personnalisé en temps réel.
                    </p>
                    <div class="mt-6">
                        <span class="bg-purple-100 text-purple-800 px-4 py-2 rounded-full text-sm font-medium">
                            <i class="fas fa-chart-line mr-2"></i>Suivi en temps réel
                        </span>
                </div>
            </div>
        </div>
    </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="bg-gradient-to-b from-white to-gray-50 py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-20">
                <span class="inline-block bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-tags mr-2"></i>Tarifs Transparents
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-gray-900 mb-6">
                    Choisissez votre <span class="text-gradient">plan</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Des tarifs compétitifs pour tous les budgets, sans engagement et avec une garantie de satisfaction
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <!-- Plan Mensuel -->
                <div class="card-modern p-10 rounded-2xl relative">
                    <div class="text-center">
                        <div class="mb-6">
                            <i class="fas fa-calendar-alt text-blue-600 text-4xl mb-4"></i>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2">Plan Mensuel</h3>
                            <p class="text-gray-600 text-lg">Flexibilité maximale</p>
                        </div>
                        
                        <div class="mb-8">
                            <div class="text-5xl font-black text-blue-600 mb-2">25 000</div>
                            <div class="text-gray-600 text-lg">FCFA / mois</div>
                            <div class="text-sm text-gray-500 mt-2">Annulable à tout moment</div>
                        </div>
                        
                        <ul class="text-left space-y-4 mb-10">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Community Manager dédié</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Publications illimitées</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Statistiques détaillées</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Support prioritaire</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Paiement sécurisé</span>
                            </li>
                        </ul>
                        
                        <a href="auth/register.php" class="w-full btn-modern text-white py-4 px-6 rounded-xl text-lg font-bold block text-center">
                            <i class="fas fa-rocket mr-2"></i>Commencer maintenant
                        </a>
                    </div>
                </div>

                <!-- Plan Annuel -->
                <div class="card-modern p-10 rounded-2xl relative border-2 border-blue-500">
                    <div class="absolute -top-6 left-1/2 transform -translate-x-1/2">
                        <span class="bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-bold px-6 py-2 rounded-full shadow-lg">
                            <i class="fas fa-star mr-2"></i>Économisez 20%
                        </span>
                    </div>
                    
                    <div class="text-center">
                        <div class="mb-6">
                            <i class="fas fa-crown text-yellow-500 text-4xl mb-4"></i>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2">Plan Annuel</h3>
                            <p class="text-gray-600 text-lg">Le plus populaire</p>
                        </div>
                        
                        <div class="mb-8">
                            <div class="text-5xl font-black text-blue-600 mb-2">300 000</div>
                            <div class="text-gray-600 text-lg">FCFA / an</div>
                            <div class="text-sm text-green-600 mt-2 font-semibold">Économisez 60 000 FCFA</div>
                        </div>
                        
                        <ul class="text-left space-y-4 mb-10">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Community Manager dédié</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Publications illimitées</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Statistiques avancées</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Support prioritaire 24/7</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Rapports personnalisés</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-4 text-lg"></i>
                                <span class="text-gray-700">Consultation stratégique</span>
                            </li>
                        </ul>
                        
                        <a href="auth/register.php" class="w-full btn-modern text-white py-4 px-6 rounded-xl text-lg font-bold block text-center">
                            <i class="fas fa-crown mr-2"></i>Choisir ce plan
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Money Back Guarantee -->
            <div class="text-center mt-16">
                <div class="inline-flex items-center bg-green-50 text-green-800 px-6 py-3 rounded-full">
                    <i class="fas fa-shield-alt mr-3 text-lg"></i>
                    <span class="font-semibold">Garantie satisfait ou remboursé - 30 jours</span>
            </div>
        </div>
    </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="bg-gradient-to-b from-gray-50 to-white py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-20">
                <span class="inline-block bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-heart mr-2"></i>Témoignages Clients
                </span>
                <h2 class="text-4xl md:text-5xl font-black text-gray-900 mb-6">
                    Ce que disent nos <span class="text-gradient">clients</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Découvrez les témoignages authentiques de nos utilisateurs qui ont transformé leur présence digitale
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="testimonial-card p-8 rounded-2xl">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
                            DB
                        </div>
                        <div class="ml-4">
                            <h4 class="font-bold text-gray-900 text-lg">M.DONGMO Bertin</h4>
                            <p class="text-sm text-gray-600">Entrepreneur</p>
                            <div class="flex text-yellow-400 mt-1">
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-700 text-lg leading-relaxed italic">
                        "SocialFlow a révolutionné ma présence sur les réseaux sociaux. Mon Community Manager est exceptionnel et mes statistiques ont explosé de 300% !"
                    </p>
                </div>

                <!-- Testimonial 2 -->
                <div class="testimonial-card p-8 rounded-2xl">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-green-500 to-green-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
                            NP
                        </div>
                        <div class="ml-4">
                            <h4 class="font-bold text-gray-900 text-lg">Mme NGOUMTSA Pulcherie</h4>
                            <p class="text-sm text-gray-600">E-commerce</p>
                            <div class="flex text-yellow-400 mt-1">
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-700 text-lg leading-relaxed italic">
                        "Interface intuitive, paiement sécurisé et résultats concrets. Mes ventes ont augmenté de 150% grâce à SocialFlow !"
                    </p>
                </div>

                <!-- Testimonial 3 -->
                <div class="testimonial-card p-8 rounded-2xl">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
                            NK
                        </div>
                        <div class="ml-4">
                            <h4 class="font-bold text-gray-900 text-lg">Mme NZEUTAP Kamille</h4>
                            <p class="text-sm text-gray-600">Consultant</p>
                            <div class="flex text-yellow-400 mt-1">
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                                <i class="fas fa-star text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-700 text-lg leading-relaxed italic">
                        "Gain de temps énorme ! Mon Community Manager gère tout pendant que je me concentre sur mon business. ROI exceptionnel !"
                    </p>
                    </div>
                </div>
            </div>
    </section>

    <!-- CTA Section -->
    <section class="relative bg-gradient-to-r from-purple-900 via-pink-800 to-purple-900 py-24 overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');"></div>
    </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-4xl md:text-6xl font-black text-white mb-8 leading-tight">
                    Prêt à <span class="text-gradient">révolutionner</span> votre présence digitale ?
            </h2>
                <p class="text-xl md:text-2xl text-white/90 mb-12 leading-relaxed">
                    Rejoignez plus de <strong>500 entreprises</strong> qui font confiance à SocialFlow pour transformer leur stratégie sociale
                </p>
                
                <div class="flex flex-col sm:flex-row gap-6 justify-center mb-12">
                    <a href="auth/register.php" class="btn-modern text-white px-12 py-4 rounded-xl text-lg font-bold transform hover:scale-105">
                        <i class="fas fa-rocket mr-3"></i>Commencer gratuitement
                    </a>
                    <a href="auth/login.php" class="glass-effect text-white hover:bg-white/20 px-12 py-4 rounded-xl text-lg font-semibold transition-all duration-300">
                        <i class="fas fa-sign-in-alt mr-3"></i>Se connecter
                </a>
            </div>
                
                <!-- Trust Indicators -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-3xl mx-auto">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2">500+</div>
                        <p class="text-white/80">Clients satisfaits</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2">50K+</div>
                        <p class="text-white/80">Publications créées</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2">99%</div>
                        <p class="text-white/80">Taux de satisfaction</p>
                    </div>
            </div>
        </div>
    </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gradient-to-b from-gray-900 to-black text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-12">
                <!-- Logo et description -->
                <div class="md:col-span-2">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-share-alt text-white text-xl"></i>
                        </div>
                        <h3 class="text-white text-2xl font-bold">SocialFlow</h3>
                    </div>
                    <p class="text-gray-400 mb-6 text-lg leading-relaxed">
                        La plateforme de référence pour automatiser votre présence sur les réseaux sociaux avec des professionnels dédiés et des outils avancés.
                    </p>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-400 hover:text-blue-400 transition duration-300 transform hover:scale-110">
                            <i class="fab fa-facebook text-2xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-blue-400 transition duration-300 transform hover:scale-110">
                            <i class="fab fa-twitter text-2xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-blue-400 transition duration-300 transform hover:scale-110">
                            <i class="fab fa-linkedin text-2xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-blue-400 transition duration-300 transform hover:scale-110">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div>
                    <h4 class="text-white font-bold mb-6 text-lg">Liens rapides</h4>
                    <ul class="space-y-3">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-arrow-right mr-2 text-sm"></i>Fonctionnalités</a></li>
                        <li><a href="#pricing" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-arrow-right mr-2 text-sm"></i>Tarifs</a></li>
                        <li><a href="auth/register.php" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-arrow-right mr-2 text-sm"></i>S'inscrire</a></li>
                        <li><a href="auth/login.php" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-arrow-right mr-2 text-sm"></i>Se connecter</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-white font-bold mb-6 text-lg">Support</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-book mr-2 text-sm"></i>Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-envelope mr-2 text-sm"></i>Contact</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-question-circle mr-2 text-sm"></i>FAQ</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300 flex items-center"><i class="fas fa-file-contract mr-2 text-sm"></i>Conditions</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-12 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 mb-4 md:mb-0">&copy; 2024 SocialFlow. Tous droits réservés.</p>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300 text-sm">Politique de confidentialité</a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300 text-sm">Conditions d'utilisation</a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300 text-sm">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Scroll Progress Indicator
        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset;
            const docHeight = document.body.offsetHeight - window.innerHeight;
            const scrollPercent = (scrollTop / docHeight) * 100;
            document.getElementById('scrollIndicator').style.transform = `scaleX(${scrollPercent / 100})`;
        });

        // Navbar Background on Scroll
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.15)';
                navbar.style.backdropFilter = 'blur(20px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.1)';
                navbar.style.backdropFilter = 'blur(20px)';
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Advanced Animation on Scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.classList.add('animate-fade-in-up');
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.card-modern, .testimonial-card, .feature-icon').forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(50px)';
            element.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(element);
        });

        // Particle Animation
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.width = Math.random() * 4 + 2 + 'px';
                particle.style.height = particle.style.width;
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Image background management
        const heroImage = document.querySelector('.bg-cover');
        if (heroImage) {
            // Optimiser le chargement de l'image
            heroImage.addEventListener('load', function() {
                console.log('Image d\'arrière-plan chargée avec succès');
            });
            
            // Gérer les erreurs de chargement d'image
            heroImage.addEventListener('error', function() {
                console.log('Image non disponible, utilisation du fallback');
                this.style.backgroundImage = 'linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%)';
            });
        }

        // Counter Animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-counter');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/\D/g, ''));
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current) + (counter.textContent.includes('+') ? '+' : '') + (counter.textContent.includes('%') ? '%' : '');
                }, 20);
            });
        }

        // Trigger counter animation when stats section is visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.stats-counter');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.fade-in-up');
            if (hero && scrolled < window.innerHeight) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
        });

        // Typing effect for hero title
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // Initialize typing effect when page loads
        window.addEventListener('load', () => {
            const heroTitle = document.querySelector('.text-gradient');
            if (heroTitle) {
                const originalText = heroTitle.textContent;
                setTimeout(() => {
                    typeWriter(heroTitle, originalText, 80);
                }, 1000);
            }
        });

        // Add hover effects to buttons
        document.querySelectorAll('.btn-modern').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn-modern').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .animate-fade-in-up {
                animation: fadeInUp 0.8s ease-out forwards;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
