<?php
/**
 * Système de notifications pour SocialFlow
 * 
 * Ce fichier contient toutes les fonctions liées à la gestion
 * des notifications dans l'application.
 */

/**
 * Créer une nouvelle notification
 */
function create_notification($user_id, $title, $message, $type = 'info', $related_entity_type = null, $related_entity_id = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $title, $message, $type, $related_entity_type, $related_entity_id]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Marquer une notification comme lue
 */
function mark_notification_as_read($notification_id, $user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Erreur marquage notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Marquer toutes les notifications comme lues
 */
function mark_all_notifications_as_read($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Erreur marquage toutes notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les notifications d'un utilisateur
 */
function get_user_notifications($user_id, $limit = 20, $unread_only = false) {
    try {
        $db = getDB();
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Compter les notifications non lues
 */
function count_unread_notifications($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        return $stmt->fetch()['count'];
    } catch (Exception $e) {
        error_log("Erreur comptage notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Supprimer une notification
 */
function delete_notification($notification_id, $user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Erreur suppression notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Supprimer toutes les notifications lues
 */
function delete_read_notifications($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Erreur suppression notifications lues: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifications automatiques pour les événements système
 */

/**
 * Notification de nouvelle publication
 */
function notify_new_post($client_id, $post_title) {
    $title = "Nouvelle publication";
    $message = "Votre Community Manager a créé une nouvelle publication : \"$post_title\"";
    return create_notification($client_id, $title, $message, 'info', 'post');
}

/**
 * Notification de publication programmée
 */
function notify_scheduled_post($client_id, $post_title, $scheduled_time) {
    $title = "Publication programmée";
    $message = "Une publication a été programmée pour le " . format_date_fr($scheduled_time) . " : \"$post_title\"";
    return create_notification($client_id, $title, $message, 'info', 'post');
}

/**
 * Notification de publication publiée
 */
function notify_published_post($client_id, $post_title) {
    $title = "Publication publiée";
    $message = "Votre publication \"$post_title\" a été publiée avec succès sur vos réseaux sociaux.";
    return create_notification($client_id, $title, $message, 'success', 'post');
}

/**
 * Notification d'échec de publication
 */
function notify_failed_post($client_id, $post_title, $error_message = '') {
    $title = "Échec de publication";
    $message = "La publication \"$post_title\" n'a pas pu être publiée. " . ($error_message ? "Erreur : $error_message" : "");
    return create_notification($client_id, $title, $message, 'error', 'post');
}

/**
 * Notification de nouveau client assigné
 */
function notify_new_client_assignment($cm_id, $client_name) {
    $title = "Nouveau client assigné";
    $message = "Un nouveau client vous a été assigné : $client_name";
    return create_notification($cm_id, $title, $message, 'info', 'client');
}

/**
 * Notification de paiement réussi
 */
function notify_payment_success($client_id, $amount, $plan_type) {
    $title = "Paiement réussi";
    $message = "Votre paiement de " . number_format($amount, 2) . " € pour votre abonnement " . ($plan_type === 'monthly' ? 'mensuel' : 'annuel') . " a été traité avec succès.";
    return create_notification($client_id, $title, $message, 'success', 'payment');
}

/**
 * Notification d'échec de paiement
 */
function notify_payment_failed($client_id, $amount, $plan_type) {
    $title = "Échec de paiement";
    $message = "Le paiement de " . number_format($amount, 2) . " € pour votre abonnement " . ($plan_type === 'monthly' ? 'mensuel' : 'annuel') . " a échoué. Veuillez réessayer.";
    return create_notification($client_id, $title, $message, 'error', 'payment');
}

/**
 * Notification d'expiration d'abonnement
 */
function notify_subscription_expiring($client_id, $days_left) {
    $title = "Abonnement expirant";
    $message = "Votre abonnement expire dans $days_left jour(s). Pensez à le renouveler pour continuer à bénéficier de nos services.";
    return create_notification($client_id, $title, $message, 'warning', 'subscription');
}

/**
 * Notification d'abonnement expiré
 */
function notify_subscription_expired($client_id) {
    $title = "Abonnement expiré";
    $message = "Votre abonnement a expiré. Renouvelez-le pour continuer à utiliser SocialFlow.";
    return create_notification($client_id, $title, $message, 'error', 'subscription');
}

/**
 * Notification de statistiques disponibles
 */
function notify_statistics_available($client_id, $period) {
    $title = "Statistiques disponibles";
    $message = "Vos statistiques pour la période $period sont maintenant disponibles. Consultez-les pour suivre vos performances.";
    return create_notification($client_id, $title, $message, 'info', 'statistics');
}

/**
 * Notification de maintenance système
 */
function notify_system_maintenance($user_id, $maintenance_time) {
    $title = "Maintenance système";
    $message = "Une maintenance système est prévue le " . format_date_fr($maintenance_time) . ". Le service pourrait être temporairement indisponible.";
    return create_notification($user_id, $title, $message, 'warning', 'system');
}

/**
 * Notification de mise à jour
 */
function notify_system_update($user_id, $version) {
    $title = "Mise à jour disponible";
    $message = "Une nouvelle version de SocialFlow ($version) est disponible avec de nouvelles fonctionnalités et améliorations.";
    return create_notification($user_id, $title, $message, 'info', 'system');
}

/**
 * Fonction pour envoyer des notifications en masse
 */
function send_bulk_notifications($user_ids, $title, $message, $type = 'info', $related_entity_type = null, $related_entity_id = null) {
    $success_count = 0;
    foreach ($user_ids as $user_id) {
        if (create_notification($user_id, $title, $message, $type, $related_entity_type, $related_entity_id)) {
            $success_count++;
        }
    }
    return $success_count;
}

/**
 * Fonction pour nettoyer les anciennes notifications
 */
function cleanup_old_notifications($days_old = 30) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        return $stmt->execute([$days_old]);
    } catch (Exception $e) {
        error_log("Erreur nettoyage notifications: " . $e->getMessage());
        return false;
    }
}
?>
