<?php
/**
 * Configuration de l'application SocialFlow
 */

// Configuration générale
define('APP_NAME', 'SocialFlow');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/SF2');
define('APP_TIMEZONE', 'Europe/Paris');

// Configuration des sessions
define('SESSION_LIFETIME', 3600); // 1 heure
define('SESSION_NAME', 'SOCIALFLOW_SESSION');

// Configuration des uploads
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov']);
define('UPLOAD_PATH', 'uploads/');

// Configuration des notifications
define('NOTIFICATION_CLEANUP_DAYS', 30);
define('NOTIFICATION_MAX_PER_USER', 100);

// Configuration des paiements
define('PAYMENT_CURRENCY', 'FCFA');
define('PAYMENT_PLANS', [
    'monthly' => 25000,
    'yearly' => 300000
]);

// Configuration de sécurité
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Configuration des réseaux sociaux
define('SUPPORTED_PLATFORMS', [
    'facebook', 'instagram', 'twitter', 
    'linkedin', 'tiktok', 'telegram', 'youtube'
]);

// Configuration de l'environnement
define('ENVIRONMENT', 'development'); // development, production
define('DEBUG_MODE', true);

// Configuration des emails (pour production)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@socialflow.com');
define('FROM_NAME', 'SocialFlow');

// Configuration des logs
define('LOG_PATH', 'logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Initialisation
date_default_timezone_set(APP_TIMEZONE);

// Configuration des erreurs selon l'environnement
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?>
