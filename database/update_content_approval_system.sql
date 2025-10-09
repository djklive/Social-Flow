-- Script pour ajouter le système d'approbation de contenu
-- Exécuter ce script dans votre base de données MySQL

USE socialflow_db;

-- Ajouter une colonne status à la table posts pour gérer l'approbation
ALTER TABLE posts ADD COLUMN approval_status ENUM('draft', 'pending_approval', 'approved', 'rejected') DEFAULT 'draft' AFTER status;

-- Ajouter une colonne pour stocker les commentaires d'approbation
ALTER TABLE posts ADD COLUMN approval_comments TEXT NULL AFTER approval_status;

-- Ajouter une colonne pour la date d'approbation
ALTER TABLE posts ADD COLUMN approved_at TIMESTAMP NULL AFTER approval_comments;

-- Ajouter une colonne pour l'ID de l'utilisateur qui a approuvé
ALTER TABLE posts ADD COLUMN approved_by INT NULL AFTER approved_at;

-- Ajouter une colonne pour l'ID du client qui doit approuver
ALTER TABLE posts ADD COLUMN client_id INT NULL AFTER approved_by;

-- Ajouter les clés étrangères
ALTER TABLE posts ADD CONSTRAINT fk_posts_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE posts ADD CONSTRAINT fk_posts_client_id FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE SET NULL;

-- Créer une table pour l'historique des approbations
CREATE TABLE IF NOT EXISTS content_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    community_manager_id INT NOT NULL,
    client_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (community_manager_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Mettre à jour les posts existants
UPDATE posts SET approval_status = 'approved' WHERE status = 'published';
UPDATE posts SET approval_status = 'draft' WHERE status = 'draft';

-- Ajouter des index pour améliorer les performances
CREATE INDEX idx_posts_approval_status ON posts(approval_status);
CREATE INDEX idx_posts_client_id ON posts(client_id);
CREATE INDEX idx_content_approvals_status ON content_approvals(status);
CREATE INDEX idx_content_approvals_client_id ON content_approvals(client_id);
