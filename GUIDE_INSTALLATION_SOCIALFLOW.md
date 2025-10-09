# 📚 GUIDE D'INSTALLATION - SocialFlow

## 1. À PROPOS DE LA PLATEFORME

**SocialFlow** est une plateforme web de gestion de réseaux sociaux accessible via navigateur. Elle peut être déployée en local ou en ligne.

**Fonctionnalités :**
- Gestion multi-rôles (Admin, CM, Client)
- Création et gestion de contenu
- Système d'approbation
- Gestion des abonnements

## 2. INSTALLATION

### Prérequis
- **XAMPP** (Apache + MySQL + PHP)
- **Navigateur web**
- **PHP 7.4+** (inclus dans XAMPP)

### Étapes d'installation

#### 1. Installer XAMPP
- Télécharger depuis : https://www.apachefriends.org/fr/index.html
- Version recommandée : 8.2.12
- Installer avec Apache, MySQL, PHP, phpMyAdmin

#### 2. Déployer SocialFlow
```
1. Copier le dossier "Social-Flow" dans C:\xampp\htdocs\
2. Chemin final : C:\xampp\htdocs\Social Flow\Social-Flow\
```

#### 3. Configurer la base de données
```
1. Démarrer XAMPP (Apache + MySQL)
2. Aller sur http://localhost/phpmyadmin
3. Créer une base : socialflow_db (utf8mb4_unicode_ci)
4. Importer le fichier : database/socialflow_db.sql
```

#### 4. Créer les dossiers
```
- Créer : uploads/
- Créer : uploads/profiles/
- Créer : uploads/posts/
- Permissions : 755
```

#### 5. Tester l'installation
```
1. Aller sur : http://localhost/Social%20Flow/Social-Flow/
2. Se connecter avec :
   - Admin : admin@socialflow.com / admin123
   - CM : cm@socialflow.com / cm123
   - Client : client@socialflow.com / client123
```

## 3. TESTS

### Tests automatiques
```bash
# Test unitaire
php test_unitaire.php

# Test d'intégration
php test_integration.php
```

## 4. DÉPANNAGE

### Problèmes courants

**Erreur de base de données :**
- Vérifier que MySQL est démarré
- Vérifier config/database.php

**Page blanche :**
- Vérifier que Apache est démarré
- Vérifier les logs dans C:\xampp\apache\logs\

**Images ne s'affichent pas :**
- Vérifier que uploads/ existe
- Vérifier les permissions (755)

## 5. DÉPLOIEMENT EN PRODUCTION

### Hébergement recommandé
- **InfinityFree** (gratuit)
- **Hostinger** (payant)

### Étapes
1. Sauvegarder la base locale
2. Transférer les fichiers via FTP
3. Importer la base de données
4. Configurer les paramètres de production
5. Activer HTTPS

---

**SocialFlow** - Guide d'installation v1.0