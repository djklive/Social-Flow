<?php
/**
 * Fonctions utilitaires pour SocialFlow
 * 
 * Ce fichier contient toutes les fonctions utilitaires utilisées
 * dans l'application pour la sécurité, la validation, etc.
 */

/**
 * Sécurise les données d'entrée
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Valide une adresse email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valide un numéro de téléphone (format international)
 */
function validate_phone($phone) {
    // Nettoyer le numéro (garder seulement les chiffres et le +)
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Vérifier si c'est un numéro valide (entre 7 et 15 chiffres)
    // Format international: +XX ou format local
    if (preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
        return true; // Format international avec +
    }
    
    if (preg_match('/^[1-9]\d{6,14}$/', $phone)) {
        return true; // Format local sans +
    }
    
    // Format français spécifique
    if (preg_match('/^(\+33|0)[1-9](\d{8})$/', $phone)) {
        return true; // Format français
    }
    
    return false;
}

/**
 * Génère un mot de passe sécurisé
 */
function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Hash un mot de passe
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Génère un token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 */
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirige vers une page avec un message
 */
function redirect_with_message($url, $message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Affiche les messages flash
 */
function display_flash_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        
        switch($type) {
            case 'success':
                $alert_class = 'bg-green-100 border-green-400 text-green-700';
                break;
            case 'error':
                $alert_class = 'bg-red-100 border-red-400 text-red-700';
                break;
            case 'warning':
                $alert_class = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                break;
            default:
                $alert_class = 'bg-blue-100 border-blue-400 text-blue-700';
                break;
        }
        
        echo "<div class='$alert_class border px-4 py-3 rounded mb-4' role='alert'>
                <span class='block sm:inline'>$message</span>
              </div>";
        
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}

/**
 * Formate une date en français
 */
function format_date_fr($date, $format = 'd/m/Y H:i') {
    $timestamp = is_string($date) ? strtotime($date) : $date;
    return date($format, $timestamp);
}

/**
 * Calcule le temps écoulé depuis une date
 */
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'à l\'instant';
    if ($time < 3600) return floor($time/60) . ' min';
    if ($time < 86400) return floor($time/3600) . ' h';
    if ($time < 2592000) return floor($time/86400) . ' j';
    if ($time < 31536000) return floor($time/2592000) . ' mois';
    return floor($time/31536000) . ' an(s)';
}

/**
 * Génère un slug à partir d'un texte
 */
function generate_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Valide une URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Upload sécurisé d'un fichier
 */
function upload_file($file, $upload_dir = 'uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Paramètres de fichier invalides'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'Aucun fichier envoyé'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'Fichier trop volumineux'];
        default:
            return ['success' => false, 'message' => 'Erreur inconnue'];
    }

    if ($file['size'] > 5000000) { // 5MB max
        return ['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }

    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Échec de l\'upload'];
    }

    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

/**
 * Envoie un email (simulation)
 */
function send_email($to, $subject, $message) {
    // En production, utiliser PHPMailer ou SwiftMailer
    // Pour la démo, on simule l'envoi
    error_log("Email envoyé à $to: $subject");
    return true;
}

/**
 * Génère un code de vérification
 */
function generate_verification_code($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Vérifie les permissions d'accès
 */
function check_permission($required_role) {
    if (!is_logged_in()) {
        redirect_with_message('auth/login.php', 'Vous devez être connecté pour accéder à cette page.', 'error');
    }
    
    if (!has_role($required_role)) {
        redirect_with_message('index.php', 'Accès non autorisé.', 'error');
    }
}

/**
 * Log une activité utilisateur
 */
function log_activity($user_id, $action, $details = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details]);
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement de l'activité: " . $e->getMessage());
    }
}
?>
