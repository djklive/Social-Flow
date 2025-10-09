# 📋 Guide d'Installation - SocialFlow

## 🎯 Vue d'ensemble

SocialFlow est une plateforme web complète de gestion de contenu pour les réseaux sociaux. Ce guide vous accompagnera dans l'installation et la configuration de l'application sur votre serveur.

## 📋 Prérequis

### Serveur Web
- **Apache** 2.4+ ou **Nginx** 1.18+
- **PHP** 7.4+ (recommandé : PHP 8.0+)
- **MySQL** 5.7+ ou **MariaDB** 10.3+

### Extensions PHP requises
```bash
- php-mysql (PDO MySQL)
- php-json
- php-curl
- php-mbstring
- php-xml
- php-zip (pour Composer)
- php-gd (pour les images)
- php-intl (pour l'internationalisation)
```

### Outils optionnels
- **Composer** (pour les dépendances PHP)
- **Git** (pour le versioning)

## 🚀 Installation

### Étape 1 : Téléchargement

1. **Cloner le repository** (si disponible) :
```bash
git clone https://github.com/votre-repo/socialflow.git
cd socialflow
```

2. **Ou télécharger l'archive** et l'extraire dans votre répertoire web :
```bash
# Exemple pour XAMPP
C:\xampp\htdocs\socialflow\
# Exemple pour WAMP
C:\wamp64\www\socialflow\
# Exemple pour Linux
/var/www/html/socialflow/
```

### Étape 2 : Configuration de la base de données

1. **Créer la base de données** :
```sql
-- Se connecter à MySQL
mysql -u root -p

-- Créer la base de données
CREATE DATABASE socialflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié (recommandé)
CREATE USER 'socialflow_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe_securise';
GRANT ALL PRIVILEGES ON socialflow_db.* TO 'socialflow_user'@'localhost';
FLUSH PRIVILEGES;
```

2. **Importer la structure** :
```bash
# Via ligne de commande
mysql -u socialflow_user -p socialflow_db < database/socialflow_db.sql

# Ou via phpMyAdmin
# - Ouvrir phpMyAdmin
# - Sélectionner la base socialflow_db
# - Importer le fichier database/socialflow_db.sql
```

### Étape 3 : Configuration de l'application

1. **Configurer la base de données** :
Éditer le fichier `config/database.php` :
```php
<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialflow_db');
define('DB_USER', 'socialflow_user');  // Votre utilisateur
define('DB_PASS', 'votre_mot_de_passe_securise');  // Votre mot de passe
define('DB_CHARSET', 'utf8mb4');
?>
```

2. **Configurer l'application** :
Éditer le fichier `config/app.php` :
```php
<?php
// Configuration générale
define('APP_NAME', 'SocialFlow');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://votre-domaine.com/socialflow');  // Votre URL
define('APP_TIMEZONE', 'Europe/Paris');  // Votre timezone

// Configuration de l'environnement
define('ENVIRONMENT', 'production');  // production ou development
define('DEBUG_MODE', false);  // true en développement, false en production
?>
```

### Étape 4 : Permissions et sécurité

1. **Définir les permissions** (Linux/Mac) :
```bash
# Permissions pour les dossiers
chmod 755 uploads/
chmod 755 logs/
chmod 755 cache/

# Permissions pour les fichiers
chmod 644 config/*.php
chmod 644 includes/*.php
```

2. **Sécuriser les fichiers sensibles** :
```apache
# .htaccess dans le dossier config/
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>
```

### Étape 5 : Installation des dépendances (optionnel)

Si vous utilisez Composer :
```bash
# Installer Composer (si pas déjà installé)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Installer les dépendances
composer install
```

### Étape 6 : Configuration du serveur web

#### Apache (.htaccess)
Créer un fichier `.htaccess` à la racine :
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Sécurité
<Files "*.sql">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.md">
    Order Deny,Allow
    Deny from all
</Files>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/html/socialflow;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

## 🔧 Configuration avancée

### Configuration des emails (production)

1. **SMTP** dans `config/app.php` :
```php
// Configuration des emails
define('SMTP_HOST', 'smtp.votre-fournisseur.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@domaine.com');
define('SMTP_PASSWORD', 'votre_mot_de_passe_email');
define('FROM_EMAIL', 'noreply@votre-domaine.com');
define('FROM_NAME', 'SocialFlow');
```

### Configuration des paiements

1. **Intégration Mobile Money** :
```php
// Dans config/app.php
define('PAYMENT_CURRENCY', 'FCFA');
define('PAYMENT_PLANS', [
    'monthly' => 25000,  // 25 000 FCFA
    'yearly' => 300000   // 300 000 FCFA
]);
```

### Configuration des réseaux sociaux

1. **APIs des plateformes** :
```php
// Dans config/app.php
define('SUPPORTED_PLATFORMS', [
    'facebook', 'instagram', 'twitter', 
    'linkedin', 'tiktok', 'telegram', 'youtube'
]);
```

## 🧪 Tests et vérification

### Test de connexion à la base de données

Créer un fichier `test_connection.php` :
```php
<?php
require_once 'config/database.php';

try {
    $db = getDB();
    echo "✅ Connexion à la base de données réussie !";
    echo "<br>Base de données : " . DB_NAME;
    echo "<br>Serveur : " . DB_HOST;
} catch (Exception $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage();
}
?>
```

### Test des fonctionnalités

1. **Accéder à l'application** :
   - URL : `http://votre-domaine.com/socialflow`
   - Vérifier que la page d'accueil s'affiche

2. **Tester la connexion** :
   - Email : `admin@socialflow.com`
   - Mot de passe : `password` (par défaut)

3. **Vérifier les comptes de test** :
   - Community Manager : `cm@socialflow.com`
   - Client : `client@socialflow.com`

## 🔒 Sécurisation (Production)

### 1. Changer les mots de passe par défaut

```sql
-- Se connecter à MySQL
mysql -u root -p socialflow_db

-- Changer le mot de passe admin
UPDATE users SET password_hash = '$2y$10$nouveau_hash_securise' WHERE email = 'admin@socialflow.com';
```

### 2. Configuration HTTPS

```apache
# .htaccess - Redirection HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Sauvegarde automatique

Créer un script de sauvegarde `backup.sh` :
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u socialflow_user -p socialflow_db > backup_$DATE.sql
tar -czf backup_files_$DATE.tar.gz /var/www/html/socialflow
```

## 🚨 Dépannage

### Problèmes courants

1. **Erreur de connexion à la base de données** :
   - Vérifier les paramètres dans `config/database.php`
   - Vérifier que MySQL est démarré
   - Vérifier les permissions utilisateur

2. **Erreur 500** :
   - Vérifier les logs d'erreur Apache/Nginx
   - Vérifier les permissions des fichiers
   - Vérifier la syntaxe PHP

3. **Pages blanches** :
   - Activer l'affichage des erreurs PHP
   - Vérifier la configuration PHP
   - Vérifier les extensions requises

### Logs utiles

```bash
# Logs Apache
tail -f /var/log/apache2/error.log

# Logs PHP
tail -f /var/log/php_errors.log

# Logs de l'application
tail -f logs/app.log
```

## 📞 Support

### Informations système

Pour obtenir de l'aide, fournir :
- Version de PHP : `php -v`
- Version de MySQL : `mysql --version`
- Logs d'erreur récents
- Configuration des fichiers `config/`

### Contacts

- **Documentation** : Voir `README.md`
- **Tests** : Voir `TESTS_DOCUMENTATION.md`
- **Issues** : Créer une issue sur le repository

---

## ✅ Checklist d'installation

- [ ] Serveur web configuré (Apache/Nginx)
- [ ] PHP 7.4+ installé avec extensions requises
- [ ] MySQL/MariaDB installé et configuré
- [ ] Base de données créée et importée
- [ ] Fichiers de configuration modifiés
- [ ] Permissions définies correctement
- [ ] Test de connexion réussi
- [ ] Application accessible via navigateur
- [ ] Comptes de test fonctionnels
- [ ] HTTPS configuré (production)
- [ ] Sauvegarde automatique configurée
- [ ] Monitoring en place

**🎉 Félicitations ! Votre installation SocialFlow est maintenant opérationnelle !**
