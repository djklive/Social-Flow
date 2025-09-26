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
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white/10 backdrop-blur-md border-b border-white/20 fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
                    <h1 class="text-white text-xl font-bold">SocialFlow</h1>
                </div>
                <div class="flex space-x-4">
                    <a href="auth/login.php" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium transition duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                    </a>
                    <a href="auth/register.php" class="bg-white text-purple-600 hover:bg-gray-100 px-4 py-2 rounded-md text-sm font-medium transition duration-300">
                        <i class="fas fa-user-plus mr-2"></i>S'inscrire
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 pt-32">
        <div class="text-center fade-in">
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
                Automatisez vos <span class="text-yellow-300">Réseaux Sociaux</span>
            </h1>
            <p class="text-xl text-white/90 mb-8 max-w-3xl mx-auto">
                SocialFlow vous permet de gérer et publier automatiquement vos contenus sur tous vos réseaux sociaux avec l'aide de professionnels dédiés.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="auth/register.php" class="bg-yellow-400 text-gray-900 hover:bg-yellow-300 px-8 py-3 rounded-lg text-lg font-semibold transition duration-300 transform hover:scale-105">
                    <i class="fas fa-rocket mr-2"></i>Commencer maintenant
                </a>
                <a href="#features" class="bg-white/20 text-white hover:bg-white/30 px-8 py-3 rounded-lg text-lg font-semibold transition duration-300">
                    <i class="fas fa-info-circle mr-2"></i>En savoir plus
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="bg-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Pourquoi choisir SocialFlow ?
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Une solution complète pour gérer votre présence sur les réseaux sociaux
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="text-center card-hover bg-white p-8 rounded-xl shadow-lg">
                    <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 floating-animation">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Community Manager Dédié</h3>
                    <p class="text-gray-600">
                        Un professionnel assigné à votre compte pour créer et gérer vos publications de qualité.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="text-center card-hover bg-white p-8 rounded-xl shadow-lg">
                    <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 floating-animation" style="animation-delay: 2s;">
                        <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Statistiques Détaillées</h3>
                    <p class="text-gray-600">
                        Suivez les performances de vos publications avec des analyses complètes et des rapports.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="text-center card-hover bg-white p-8 rounded-xl shadow-lg">
                    <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 floating-animation" style="animation-delay: 4s;">
                        <i class="fas fa-mobile-alt text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Paiement Mobile</h3>
                    <p class="text-gray-600">
                        Paiement sécurisé via Mobile Money, Orange Money ou carte bancaire.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- How it works Section -->
    <div class="bg-gray-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Comment ça marche ?
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Trois étapes simples pour automatiser vos réseaux sociaux
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                        1
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Inscrivez-vous</h3>
                    <p class="text-gray-600">
                        Créez votre compte et choisissez votre plan d'abonnement adapté à vos besoins.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                        2
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Votre CM vous est assigné</h3>
                    <p class="text-gray-600">
                        Un Community Manager professionnel vous est dédié pour créer et gérer vos contenus.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="bg-gradient-to-r from-pink-500 to-red-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                        3
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Profitez des résultats</h3>
                    <p class="text-gray-600">
                        Consultez vos publications et statistiques depuis votre dashboard personnalisé.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div class="bg-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Nos tarifs
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Choisissez le plan qui correspond à vos besoins
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Plan Mensuel -->
                <div class="bg-white border-2 border-gray-200 rounded-xl p-8 card-hover">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Plan Mensuel</h3>
                        <div class="text-4xl font-bold text-purple-600 mb-4">25 000 FCFA</div>
                        <p class="text-gray-600 mb-6">Par mois</p>
                        <ul class="text-left space-y-3 mb-8">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Community Manager dédié
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Publications illimitées
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Statistiques détaillées
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Support prioritaire
                            </li>
                        </ul>
                        <a href="auth/register.php" class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg hover:bg-purple-700 transition duration-300 font-semibold block text-center">
                            Commencer
                        </a>
                    </div>
                </div>

                <!-- Plan Annuel -->
                <div class="bg-white border-2 border-purple-500 rounded-xl p-8 card-hover relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-green-500 text-white text-sm font-medium px-4 py-1 rounded-full">
                            Économisez 20%
                        </span>
                    </div>
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Plan Annuel</h3>
                        <div class="text-4xl font-bold text-purple-600 mb-4">300 000 FCFA</div>
                        <p class="text-gray-600 mb-6">Par an</p>
                        <ul class="text-left space-y-3 mb-8">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Community Manager dédié
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Publications illimitées
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Statistiques avancées
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Support prioritaire
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Rapports personnalisés
                            </li>
                        </ul>
                        <a href="auth/register.php" class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg hover:bg-purple-700 transition duration-300 font-semibold block text-center">
                            Commencer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="bg-gray-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Ce que disent nos clients
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Découvrez les témoignages de nos utilisateurs satisfaits
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                            JM
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-900">Jean Martin</h4>
                            <p class="text-sm text-gray-600">Entrepreneur</p>
                        </div>
                    </div>
                    <p class="text-gray-600 italic">
                        "SocialFlow a révolutionné ma présence sur les réseaux sociaux. Mon Community Manager est professionnel et mes statistiques ont explosé !"
                    </p>
                    <div class="flex text-yellow-400 mt-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                            SD
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-900">Sophie Dubois</h4>
                            <p class="text-sm text-gray-600">E-commerce</p>
                        </div>
                    </div>
                    <p class="text-gray-600 italic">
                        "Interface intuitive, paiement sécurisé et résultats concrets. Je recommande SocialFlow à tous les entrepreneurs !"
                    </p>
                    <div class="flex text-yellow-400 mt-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white font-bold">
                            PL
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-900">Pierre Leroy</h4>
                            <p class="text-sm text-gray-600">Consultant</p>
                        </div>
                    </div>
                    <p class="text-gray-600 italic">
                        "Gain de temps énorme ! Mon Community Manager gère tout pendant que je me concentre sur mon business."
                    </p>
                    <div class="flex text-yellow-400 mt-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gray-900 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">
                Prêt à transformer votre présence digitale ?
            </h2>
            <p class="text-xl text-gray-300 mb-8">
                Rejoignez des milliers d'entreprises qui font confiance à SocialFlow
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="auth/register.php" class="bg-yellow-400 text-gray-900 hover:bg-yellow-300 px-8 py-3 rounded-lg text-lg font-semibold transition duration-300 transform hover:scale-105">
                    <i class="fas fa-arrow-right mr-2"></i>Commencer maintenant
                </a>
                <a href="auth/login.php" class="bg-white/20 text-white hover:bg-white/30 px-8 py-3 rounded-lg text-lg font-semibold transition duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <!-- Logo et description -->
                <div class="md:col-span-2">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-share-alt text-white text-2xl mr-3"></i>
                        <h3 class="text-white text-xl font-bold">SocialFlow</h3>
                    </div>
                    <p class="text-gray-400 mb-4">
                        La plateforme de référence pour automatiser votre présence sur les réseaux sociaux avec des professionnels dédiés.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-linkedin text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div>
                    <h4 class="text-white font-semibold mb-4">Liens rapides</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition duration-300">Fonctionnalités</a></li>
                        <li><a href="auth/register.php" class="text-gray-400 hover:text-white transition duration-300">S'inscrire</a></li>
                        <li><a href="auth/login.php" class="text-gray-400 hover:text-white transition duration-300">Se connecter</a></li>
                        <li><a href="test.php" class="text-gray-400 hover:text-white transition duration-300">Test de l'application</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-white font-semibold mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Contact</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">FAQ</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Conditions d'utilisation</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p class="text-gray-400">&copy; 2024 SocialFlow. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
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

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.card-hover').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.fade-in');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
    </script>
</body>
</html>
