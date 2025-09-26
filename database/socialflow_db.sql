-- Base de données SocialFlow
-- Script de création de la base de données et des tables

CREATE DATABASE IF NOT EXISTS socialflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE socialflow_db;

-- Table des utilisateurs (clients, community managers, administrateurs)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('client', 'community_manager', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Table des abonnements clients
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    plan_type ENUM('monthly', 'yearly') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'pending',
    start_date DATE,
    end_date DATE,
    auto_renew BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_status (status)
);

-- Table des paiements
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('mobile_money', 'orange_money', 'card') NOT NULL,
    payment_reference VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    payment_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_status (status)
);

-- Table des assignations client-community manager
CREATE TABLE client_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    community_manager_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_manager_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (client_id, community_manager_id),
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (community_manager_id)
);

-- Table des comptes réseaux sociaux
CREATE TABLE social_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    platform ENUM('facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'telegram', 'youtube') NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_id VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_platform (platform)
);

-- Table des publications
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    community_manager_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    media_urls JSON,
    platforms JSON NOT NULL, -- Array des plateformes ciblées
    status ENUM('draft', 'scheduled', 'published', 'failed', 'deleted') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    engagement_data JSON, -- Likes, partages, commentaires par plateforme
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_manager_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (community_manager_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- Table des brouillons
CREATE TABLE drafts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT,
    client_id INT NOT NULL,
    community_manager_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    media_urls JSON,
    platforms JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_manager_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_cm_id (community_manager_id)
);

-- Table des notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    related_entity_type VARCHAR(50), -- 'post', 'payment', 'subscription', etc.
    related_entity_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Table des paramètres utilisateur
CREATE TABLE user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_key),
    INDEX idx_user_id (user_id)
);

-- Table des logs d'activité
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Table des invitations de collaboration
CREATE TABLE collaboration_invites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_manager_id INT NOT NULL,
    invited_email VARCHAR(255) NOT NULL,
    role ENUM('collaborator', 'viewer') NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_manager_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cm_id (community_manager_id),
    INDEX idx_token (token),
    INDEX idx_status (status)
);

-- Table des statistiques
CREATE TABLE statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) NOT NULL,
    platform VARCHAR(50),
    date_recorded DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_metric_name (metric_name),
    INDEX idx_date_recorded (date_recorded)
);

-- Table de la corbeille (pour la restauration)
CREATE TABLE trash (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type VARCHAR(50) NOT NULL, -- 'user', 'post', 'draft', etc.
    entity_id INT NOT NULL,
    entity_data JSON NOT NULL,
    deleted_by INT,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_deleted_at (deleted_at)
);

-- Insertion des données initiales

-- Administrateur par défaut
INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified) 
VALUES ('admin@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'SocialFlow', 'admin', 'active', TRUE);

-- Community Manager de démonstration
INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified) 
VALUES ('cm@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie', 'Dubois', 'community_manager', 'active', TRUE);

-- Client de démonstration
INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified) 
VALUES ('client@socialflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jean', 'Martin', 'client', 'active', TRUE);

-- Abonnement pour le client de démonstration
INSERT INTO subscriptions (client_id, plan_type, price, status, start_date, end_date) 
VALUES (3, 'monthly', 29.99, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH));

-- Assignation du client au community manager
INSERT INTO client_assignments (client_id, community_manager_id) 
VALUES (3, 2);

-- Paramètres par défaut
INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES
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
