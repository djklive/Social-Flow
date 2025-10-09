<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier les permissions
check_permission('client');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$success_message = '';
$error_message = '';

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    $plan_type = sanitize_input($_POST['plan_type'] ?? '');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');
    
    if (empty($plan_type) || empty($payment_method)) {
        $error_message = 'Veuillez sélectionner un plan et une méthode de paiement.';
    } else {
        try {
            $db = getDB();
            
            // Prix selon le plan
            $prices = [
                'monthly' => 25000,
                'yearly' => 300000
            ];
            $price = $prices[$plan_type];
            
            // Calculer les dates
            $start_date = date('Y-m-d');
            $end_date = $plan_type === 'monthly' 
                ? date('Y-m-d', strtotime('+1 month'))
                : date('Y-m-d', strtotime('+1 year'));
            
            // Créer l'abonnement
            $stmt = $db->prepare("
                INSERT INTO subscriptions (client_id, plan_type, price, status, start_date, end_date) 
                VALUES (?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$user_id, $plan_type, $price, $start_date, $end_date]);
            $subscription_id = $db->lastInsertId();
            
            // Générer une référence de paiement
            $payment_reference = 'SF' . date('Ymd') . str_pad($subscription_id, 6, '0', STR_PAD_LEFT);
            
            // Créer le paiement
            $stmt = $db->prepare("
                INSERT INTO payments (subscription_id, amount, payment_method, payment_reference, status, transaction_id) 
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $transaction_id = uniqid('txn_');
            $stmt->execute([$subscription_id, $price, $payment_method, $payment_reference, $transaction_id]);
            
            // Simuler le traitement du paiement
            $payment_success = simulate_payment($payment_method, $price, $payment_reference);
            
            if ($payment_success) {
                // Mettre à jour le statut du paiement et de l'abonnement
                $stmt = $db->prepare("UPDATE payments SET status = 'completed' WHERE subscription_id = ?");
                $stmt->execute([$subscription_id]);
                
                $stmt = $db->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?");
                $stmt->execute([$subscription_id]);
                
                // Créer une notification
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (?, ?, ?, 'success')
                ");
                $stmt->execute([
                    $user_id, 
                    'Abonnement activé !', 
                    'Votre abonnement ' . ($plan_type === 'monthly' ? 'mensuel' : 'annuel') . ' a été activé avec succès.',
                    'success'
                ]);
                
                // Logger l'activité
                log_activity($user_id, 'subscription_created', "Abonnement $plan_type créé - $price FCFA");
                
                $success_message = 'Votre abonnement a été activé avec succès !';
            } else {
                $error_message = 'Le paiement a échoué. Veuillez réessayer.';
            }
            
        } catch (Exception $e) {
            $error_message = 'Erreur lors de la création de l\'abonnement. Veuillez réessayer.';
            error_log("Erreur abonnement: " . $e->getMessage());
        }
    }
}

// Récupérer les données
try {
    $db = getDB();
    
    // Abonnement actuel
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE client_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $current_subscription = $stmt->fetch();
    
    // Historique des paiements
    $stmt = $db->prepare("
        SELECT p.*, s.plan_type 
        FROM payments p 
        INNER JOIN subscriptions s ON p.subscription_id = s.id 
        WHERE s.client_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $payment_history = $stmt->fetchAll();

    // Notifications non lues
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    error_log("Erreur récupération données abonnement: " . $e->getMessage());
    $current_subscription = null;
    $payment_history = [];
    $unread_notifications = 0;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement - SocialFlow</title>
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
        .plan-card {
            transition: all 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .plan-card.selected {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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
                <a href="subscription.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-100 rounded-lg">
                    <i class="fas fa-credit-card mr-3 text-blue-800"></i>
                    Abonnement
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-bell mr-3"></i>
                    Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
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
                    <h1 class="text-2xl font-semibold text-gray-900">Abonnement</h1>
                    <p class="text-sm text-gray-600">Gérez votre abonnement SocialFlow</p>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Plans d'abonnement -->
                <div class="lg:col-span-2">
                    <div class="content-card rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Choisissez votre plan</h2>
                        
                        <form method="POST" id="subscriptionForm">
                            <input type="hidden" name="action" value="subscribe">
                            
                            <div class="grid md:grid-cols-2 gap-6 mb-6">
                                <!-- Plan Mensuel -->
                                <div class="plan-card border-2 border-gray-200 rounded-lg p-6 cursor-pointer" onclick="selectPlan('monthly')">
                                    <div class="text-center">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Plan Mensuel</h3>
                                        <div class="text-3xl font-bold text-blue-800 mb-4">25000 FCFA</div>
                                        <p class="text-gray-600 mb-4">Par mois</p>
                                        <ul class="text-sm text-gray-600 space-y-2 mb-6">
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Community Manager dédié
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Publications illimitées
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Statistiques détaillées
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Support prioritaire
                                            </li>
                                        </ul>
                                        <input type="radio" name="plan_type" value="monthly" class="hidden" id="plan_monthly">
                                    </div>
                                </div>

                                <!-- Plan Annuel -->
                                <div class="plan-card border-2 border-gray-200 rounded-lg p-6 cursor-pointer relative" onclick="selectPlan('yearly')">
                                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                        <span class="bg-green-500 text-white text-xs font-medium px-3 py-1 rounded-full">
                                            Économisez 20%
                                        </span>
                                    </div>
                                    <div class="text-center">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Plan Annuel</h3>
                                        <div class="text-3xl font-bold text-blue-800 mb-4">300000 FCFA</div>
                                        <p class="text-gray-600 mb-4">Par an</p>
                                        <ul class="text-sm text-gray-600 space-y-2 mb-6">
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Community Manager dédié
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Publications illimitées
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Statistiques avancées
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Support prioritaire
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                Rapports personnalisés
                                            </li>
                                        </ul>
                                        <input type="radio" name="plan_type" value="yearly" class="hidden" id="plan_yearly">
                                    </div>
                                </div>
                            </div>

                            <!-- Méthodes de paiement -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Méthode de paiement</h3>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <label class="payment-method border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-purple-300" onclick="selectPaymentMethod('mobile_money')">
                                        <div class="text-center">
                                            <i class="fas fa-mobile-alt text-2xl text-gray-600 mb-2"></i>
                                            <p class="font-medium text-gray-900">Mobile Money</p>
                                            <p class="text-sm text-gray-500">Orange Money, MTN</p>
                                        </div>
                                        <input type="radio" name="payment_method" value="mobile_money" class="hidden">
                                    </label>
                                    
                                    <label class="payment-method border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-purple-300" onclick="selectPaymentMethod('orange_money')">
                                        <div class="text-center">
                                            <i class="fas fa-mobile-alt text-2xl text-orange-500 mb-2"></i>
                                            <p class="font-medium text-gray-900">Orange Money</p>
                                            <p class="text-sm text-gray-500">Paiement Orange</p>
                                        </div>
                                        <input type="radio" name="payment_method" value="orange_money" class="hidden">
                                    </label>
                                    
                                    <label class="payment-method border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-purple-300" onclick="selectPaymentMethod('card')">
                                        <div class="text-center">
                                            <i class="fas fa-credit-card text-2xl text-blue-600 mb-2"></i>
                                            <p class="font-medium text-gray-900">Carte bancaire</p>
                                            <p class="text-sm text-gray-500">Visa, Mastercard</p>
                                        </div>
                                        <input type="radio" name="payment_method" value="card" class="hidden">
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-blue-800 text-white py-3 px-4 rounded-lg hover:bg-blue-900 transition duration-300 font-semibold">
                                <i class="fas fa-credit-card mr-2"></i>Souscrire maintenant
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Abonnement actuel et historique -->
                <div class="lg:col-span-1">
                    <!-- Abonnement actuel -->
                    <div class="content-card rounded-lg shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Abonnement actuel</h3>
                        <?php if ($current_subscription): ?>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Plan:</span>
                                    <span class="font-medium">
                                        <?php echo $current_subscription['plan_type'] === 'monthly' ? 'Mensuel' : 'Annuel'; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Prix: </span>
                                    <span class="font-medium"><?php echo number_format($current_subscription['price']); ?> FCFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Statut:</span>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $current_subscription['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($current_subscription['status']); ?>
                                    </span>
                                </div>
                                <?php if ($current_subscription['end_date']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Expire le:</span>
                                        <span class="font-medium"><?php echo format_date_fr($current_subscription['end_date'], 'd/m/Y'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-credit-card text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">Aucun abonnement actif</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Historique des paiements -->
                    <div class="content-card rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Historique des paiements</h3>
                        <?php if (!empty($payment_history)): ?>
                            <div class="space-y-3">
                                <?php foreach ($payment_history as $payment): ?>
                                    <div class="border border-gray-200 rounded-lg p-3">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <p class="font-medium text-gray-900">
                                                    <?php echo $payment['plan_type'] === 'monthly' ? 'Abonnement mensuel' : 'Abonnement annuel'; ?>
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo format_date_fr($payment['created_at'], 'd/m/Y H:i'); ?>
                                                </p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                            <span class="font-medium"><?php echo number_format($payment['amount']); ?> FCFA</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500 text-sm">Aucun paiement</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function selectPlan(plan) {
            // Désélectionner tous les plans
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Sélectionner le plan cliqué
            event.currentTarget.classList.add('selected');
            document.getElementById('plan_' + plan).checked = true;
        }

        function selectPaymentMethod(method) {
            // Désélectionner toutes les méthodes
            document.querySelectorAll('.payment-method').forEach(method => {
                method.classList.remove('border-purple-500', 'bg-purple-50');
                method.classList.add('border-gray-200');
            });
            
            // Sélectionner la méthode cliquée
            event.currentTarget.classList.remove('border-gray-200');
            event.currentTarget.classList.add('border-purple-500', 'bg-purple-50');
            document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
        }

        // Validation du formulaire
        document.getElementById('subscriptionForm').addEventListener('submit', function(e) {
            const planSelected = document.querySelector('input[name="plan_type"]:checked');
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            
            if (!planSelected || !paymentSelected) {
                e.preventDefault();
                alert('Veuillez sélectionner un plan et une méthode de paiement.');
            }
        });
    </script>
</body>
</html>
