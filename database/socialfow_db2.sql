

-- ========================================
-- Base de données : SocialFlow (version FR)
-- ========================================

CREATE DATABASE IF NOT EXISTS socialflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; USE socialflow_db;



-- ========================
-- TABLE : utilisateurs
-- ========================
CREATE TABLE utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    role ENUM('client','community_manager','admin') NOT NULL,
    statut ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verifie BOOLEAN DEFAULT FALSE,
    telephone_verifie BOOLEAN DEFAULT FALSE,
    photo_profil VARCHAR(255),
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (statut)
);

-- ========================
-- TABLE : abonnements
-- ========================
CREATE TABLE abonnements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    type_forfait ENUM('mensuel','annuel') NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    statut ENUM('actif','expire','annule','en_attente') DEFAULT 'en_attente',
    date_debut DATE,
    date_fin DATE,
    renouvellement_auto BOOLEAN DEFAULT TRUE,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_status (statut)
);

-- ========================
-- TABLE : paiements
-- ========================
CREATE TABLE paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    abonnement_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    moyen_paiement ENUM('mobile_money','orange_money','carte') NOT NULL,
    reference_paiement VARCHAR(255),
    statut ENUM('en_attente','effectue','echoue','rembourse') DEFAULT 'en_attente',
    transaction_id VARCHAR(255),
    donnees_paiement JSON,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (abonnement_id) REFERENCES abonnements(id) ON DELETE CASCADE,
    INDEX idx_abonnement_id (abonnement_id),
    INDEX idx_status (statut)
);

-- ========================
-- TABLE : assignations_clients
-- ========================
CREATE TABLE assignations_clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    community_manager_id INT NOT NULL,
    assigne_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    notes TEXT,
    FOREIGN KEY (client_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (community_manager_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (client_id, community_manager_id),
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (community_manager_id),
);

-- ========================
-- TABLE : comptes_reseaux_sociaux
-- ========================
CREATE TABLE comptes_reseaux_sociaux (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    plateforme ENUM('facebook','instagram','twitter','linkedin','tiktok','youtube') NOT NULL,
    nom_compte VARCHAR(255) NOT NULL,
    identifiant_compte VARCHAR(255),
    jeton_acces TEXT,
    jeton_actualisation TEXT,
    expiration_jeton TIMESTAMP NULL,
    actif BOOLEAN DEFAULT TRUE,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur_id (utilisateur_id),
    INDEX idx_plateforme (plateforme)
);

-- ========================
-- TABLE : publications
-- ========================
CREATE TABLE publications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    gestionnaire_communautaire_id INT,
    titre VARCHAR(255),
    contenu TEXT NOT NULL,
    urls_medias JSON,
    plateformes JSON NOT NULL, -- Array des plateformes ciblées
    statut ENUM('brouillon','planifie','publie','echoue','supprime') DEFAULT 'brouillon',
    date_planification TIMESTAMP NULL,
    date_publication TIMESTAMP NULL,
    donnees_engagement JSON, -- Array des données d'engagement
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (gestionnaire_communautaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (gestionnaire_communautaire_id),
    INDEX idx_status (statut),
    INDEX idx_date_planification (date_planification),
);

-- ========================
-- TABLE : brouillons
-- ========================
CREATE TABLE brouillons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    publication_id INT,
    client_id INT NOT NULL,
    gestionnaire_communautaire_id INT,
    titre VARCHAR(255),
    contenu TEXT NOT NULL,
    urls_medias JSON,
    plateformes JSON,
    notes TEXT,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (gestionnaire_communautaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (gestionnaire_communautaire_id),
);

-- ========================
-- TABLE : notifications
-- ========================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(255),
    message TEXT NOT NULL,
    type ENUM('info','succes','avertissement','erreur') DEFAULT 'info',
    lu BOOLEAN DEFAULT FALSE,
    type_entite_associee VARCHAR(50), -- 'post', 'payment', 'subscription', etc.
    entite_associee_id INT,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur_id (utilisateur_id),
    INDEX idx_lu (lu),
    INDEX idx_cree_le (cree_le)
);

-- ========================
-- TABLE : parametres_utilisateur
-- ========================
CREATE TABLE parametres_utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    cle_parametre VARCHAR(100) NOT NULL,
    valeur_parametre TEXT,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (utilisateur_id, cle_parametre),
    INDEX idx_utilisateur_id (utilisateur_id),
);

-- ========================
-- TABLE : journaux_activites
-- ========================
CREATE TABLE journaux_activites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    adresse_ip VARCHAR(50),
    navigateur_client TEXT,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_utilisateur_id (utilisateur_id),
    INDEX idx_action (action),
    INDEX idx_cree_le (cree_le)
);

-- ========================
-- TABLE : invitations_collaboration
-- ========================
CREATE TABLE invitations_collaboration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gestionnaire_communautaire_id INT NOT NULL,
    email_invite VARCHAR(255) NOT NULL,
    role ENUM('collaborateur','lecteur') DEFAULT 'collaborateur' NOT NULL,
    jeton VARCHAR(255) UNIQUE NOT NULL,
    statut ENUM('en_attente','accepte','refuse','expire') DEFAULT 'en_attente',
    expire_le TIMESTAMP NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gestionnaire_communautaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_gestionnaire_communautaire_id (gestionnaire_communautaire_id),
    INDEX idx_statut (statut),
    INDEX idx_jeton (jeton)
);

-- ========================
-- TABLE : statistiques
-- ========================
CREATE TABLE statistiques (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT,
    nom_indicateur VARCHAR(100) NOT NULL,
    valeur_indicateur DECIMAL(15,2) NOT NULL,
    plateforme ENUM('facebook','instagram','twitter','linkedin','tiktok','youtube'),
    date_enregistrement DATE NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur_id (utilisateur_id),
    INDEX idx_nom_indicateur (nom_indicateur),
    INDEX idx_date_enregistrement (date_enregistrement)
);

-- ========================
-- TABLE : corbeille
-- ========================
CREATE TABLE corbeille (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_entite VARCHAR(100) NOT NULL, -- 'user', 'post', 'draft', etc.
    entite_id INT NOT NULL,
    donnees_entite JSON NOT NULL,
    supprime_par INT,
    supprime_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supprime_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_type_entite (type_entite),
    INDEX idx_entite_id (entite_id),
    INDEX idx_supprime_le (supprime_le)
);

-- Insertion des données initiales

-- Administrateur par défaut
INSERT INTO utilisateurs (email, password_hash, prenom, nom, role, statut, email_verified) 
VALUES ('admin@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'SocialFlow', 'admin', 'actif', TRUE);

-- Community Manager de démonstration
INSERT INTO utilisateurs (email, password_hash, prenom, nom, role, statut, email_verified) 
VALUES ('cm@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie', 'Dubois', 'community_manager', 'actif', TRUE);

-- Client de démonstration
INSERT INTO utilisateurs (email, password_hash, prenom, nom, role, statut, email_verified) 
VALUES ('client@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jean', 'Martin', 'client', 'actif', TRUE);

-- Abonnement pour le client de démonstration
INSERT INTO abonnements (client_id, type_forfait, prix, statut, date_debut, date_fin) 
VALUES (3, 'mensuel', 29.99, 'actif', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH));

-- Assignation du client au community manager
INSERT INTO assignations_clients (client_id, community_manager_id) 
VALUES (3, 2);

-- Paramètres par défaut
INSERT INTO parametres_utilisateur (user_id, setting_key, setting_value) VALUES
(1, 'notifications_email', 'true'),
(1, 'notifications_push', 'true'),
(1, 'language', 'fr'),
(2, 'notifications_email', 'true'),
(2, 'notifications_push', 'true'),
(2, 'language', 'fr'),
(3, 'notifications_email', 'true'),
(3, 'notifications_push', 'false'),
(3, 'language', 'fr');

-- Notifications de démonstration
INSERT INTO notifications (user_id, title, message, type) VALUES
(3, 'Bienvenue sur SocialFlow !', 'Votre compte a été créé avec succès. Votre Community Manager vous contactera bientôt.', 'success'),
(2, 'Nouveau client assigné', 'Un nouveau client (Jean Martin) vous a été assigné.', 'info');

-- Comptes réseaux sociaux de démonstration
INSERT INTO social_accounts (user_id, platform, account_name, account_id, is_active) VALUES
(3, 'facebook', 'Jean Martin Business', '123456789', TRUE),
(3, 'instagram', '@jeanmartin_business', '987654321', TRUE),
(3, 'linkedin', 'Jean Martin', '456789123', TRUE);

-- Publications de démonstration
INSERT INTO posts (client_id, community_manager_id, title, content, platforms, status, published_at) VALUES
(3, 2, 'Première publication', 'Bienvenue sur notre page ! Nous sommes ravis de partager notre aventure avec vous. #nouveau #business', '["facebook", "instagram"]', 'published', NOW() - INTERVAL 2 DAY),
(3, 2, 'Conseils business', "Voici nos 5 conseils pour développer votre entreprise en 2024. Qu'en pensez-vous ?", '["linkedin", "facebook"]', 'published', NOW() - INTERVAL 1 DAY);

-- Statistiques de démonstration
INSERT INTO statistics (user_id, metric_name, metric_value, platform, date_recorded) VALUES
(3, 'likes', 45, 'facebook', CURDATE() - INTERVAL 2 DAY),
(3, 'shares', 12, 'facebook', CURDATE() - INTERVAL 2 DAY),
(3, 'comments', 8, 'facebook', CURDATE() - INTERVAL 2 DAY),
(3, 'likes', 67, 'instagram', CURDATE() - INTERVAL 2 DAY),
(3, 'likes', 23, 'linkedin', CURDATE() - INTERVAL 1 DAY),
(3, 'shares', 5, 'linkedin', CURDATE() - INTERVAL 1 DAY);

-- Logs d'activité de démonstration
INSERT INTO activity_logs (user_id, action, details) VALUES
(3, 'account_created', 'Compte client créé'),
(2, 'client_assigned', 'Client Jean Martin assigné'),
(2, 'post_created', 'Publication "Première publication" créée'),
(2, 'post_published', 'Publication "Première publication" publiée sur Facebook et Instagram');

