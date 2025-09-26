<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Logger la déconnexion si l'utilisateur est connecté
if (is_logged_in()) {
    log_activity($_SESSION['user_id'], 'logout', 'Déconnexion');
}

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
header('Location: ../index.php');
exit();
?>
