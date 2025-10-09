# 🚀 Guide de Démarrage Rapide - SocialFlow

## 🎯 Objectif

Ce guide vous permet de **démarrer rapidement** avec SocialFlow en 15 minutes. Parfait pour les tests, démonstrations ou installations de développement.

## ⚡ Installation Express (5 minutes)

### Prérequis minimaux
- **XAMPP** installé et démarré
- **Navigateur web** (Chrome, Firefox, Safari)
- **5 minutes** de votre temps

### Étapes d'installation

#### 1. Télécharger et extraire (1 minute)
```bash
# Télécharger SocialFlow
# Extraire dans le dossier htdocs de XAMPP
C:\xampp\htdocs\socialflow\
```

#### 2. Créer la base de données (2 minutes)
1. **Ouvrir phpMyAdmin** : `http://localhost/phpmyadmin`
2. **Créer une base** : `socialflow_db`
3. **Importer le script** : `database/socialflow_db.sql`

#### 3. Configurer l'application (1 minute)
Éditer `config/database.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialflow_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

#### 4. Tester l'installation (1 minute)
1. **Accéder à l'application** : `http://localhost/socialflow`
2. **Se connecter** avec les identifiants par défaut
3. **Vérifier** que tout fonctionne

## 🔐 Connexion rapide

### Comptes de test disponibles

| Rôle | Email | Mot de passe | Interface |
|------|-------|--------------|-----------|
| **Admin** | `admin@socialflow.com` | `password` | `admin/dashboard.php` |
| **Community Manager** | `cm@socialflow.com` | `password` | `cm/dashboard.php` |
| **Client** | `client@socialflow.com` | `password` | `client/dashboard.php` |

### URLs d'accès direct
- **Page d'accueil** : `http://localhost/socialflow/`
- **Connexion** : `http://localhost/socialflow/auth/login.php`
- **Admin** : `http://localhost/socialflow/admin/dashboard.php`
- **CM** : `http://localhost/socialflow/cm/dashboard.php`
- **Client** : `http://localhost/socialflow/client/dashboard.php`

## 🎮 Test rapide des fonctionnalités

### Test 1 : Connexion (30 secondes)
1. Aller sur la page de connexion
2. Se connecter avec `admin@socialflow.com` / `password`
3. Vérifier la redirection vers le dashboard admin

### Test 2 : Création d'utilisateur (1 minute)
1. **Admin** → `Utilisateurs` → `Nouvel utilisateur`
2. Remplir les informations :
   - Nom : `Test`
   - Prénom : `User`
   - Email : `test@example.com`
   - Rôle : `Client`
3. Cliquer sur `Créer`
4. Vérifier l'ajout dans la liste

### Test 3 : Assignation client-CM (1 minute)
1. **Admin** → `Assignations` → `Nouvelle assignation`
2. Sélectionner :
   - Client : `Test User`
   - Community Manager : `Marie Dubois`
3. Cliquer sur `Assigner`
4. Vérifier l'assignation

### Test 4 : Création de publication (2 minutes)
1. Se connecter avec `cm@socialflow.com` / `password`
2. **CM** → `Publications` → `Nouvelle publication`
3. Remplir :
   - Client : `Jean Martin`
   - Titre : `Test publication`
   - Contenu : `Ceci est un test de publication !`
   - Plateformes : `Facebook`, `Instagram`
4. Cliquer sur `Publier maintenant`
5. Vérifier la publication

### Test 5 : Consultation client (1 minute)
1. Se connecter avec `client@socialflow.com` / `password`
2. **Client** → `Publications`
3. Vérifier la publication créée
4. Consulter les statistiques

## 📊 Données de démonstration

### Utilisateurs pré-configurés
- **3 utilisateurs** avec différents rôles
- **1 abonnement** actif
- **1 assignation** client-CM
- **2 publications** d'exemple
- **Statistiques** de démonstration

### Comptes réseaux sociaux
- **Facebook** : Jean Martin Business
- **Instagram** : @jeanmartin_business
- **LinkedIn** : Jean Martin

### Publications d'exemple
1. **"Première publication"** - Publiée il y a 2 jours
2. **"Conseils business"** - Publiée hier

## 🔧 Configuration rapide

### Changer le mot de passe admin
1. Se connecter avec `admin@socialflow.com`
2. **Paramètres** → `Sécurité`
3. Changer le mot de passe
4. Confirmer

### Configurer les emails (optionnel)
Éditer `config/app.php` :
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@gmail.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
```

### Personnaliser l'application
Éditer `config/app.php` :
```php
define('APP_NAME', 'Mon SocialFlow');
define('APP_URL', 'http://mon-domaine.com');
define('PAYMENT_PLANS', [
    'monthly' => 20000,  // 20 000 FCFA
    'yearly' => 200000   // 200 000 FCFA
]);
```

## 🚨 Dépannage rapide

### Problème : Page blanche
**Solution** : Vérifier que PHP et MySQL sont démarrés dans XAMPP

### Problème : Erreur de base de données
**Solution** : Vérifier les paramètres dans `config/database.php`

### Problème : Erreur 404
**Solution** : Vérifier que les fichiers sont dans le bon dossier

### Problème : Connexion impossible
**Solution** : Vérifier que la base de données est importée

## 📱 Test sur mobile

### Accès mobile
1. **Trouver l'IP** de votre ordinateur
2. **Accéder depuis mobile** : `http://192.168.1.100/socialflow`
3. **Tester** les fonctionnalités mobiles

### Fonctionnalités mobiles
- ✅ Interface responsive
- ✅ Navigation tactile
- ✅ Upload de photos
- ✅ Notifications

## 🎯 Prochaines étapes

### Pour la production
1. **Lire** le `GUIDE_INSTALLATION.md` complet
2. **Configurer** un serveur de production
3. **Sécuriser** l'application
4. **Configurer** les emails et paiements

### Pour le développement
1. **Lire** la documentation technique
2. **Configurer** l'environnement de développement
3. **Installer** les outils de test
4. **Commencer** le développement

### Pour les utilisateurs
1. **Lire** le `GUIDE_UTILISATEUR.md`
2. **Former** les utilisateurs
3. **Configurer** les workflows
4. **Lancer** l'utilisation

## 📞 Support rapide

### Ressources
- **Guide complet** : `GUIDE_INSTALLATION.md`
- **Guide utilisateur** : `GUIDE_UTILISATEUR.md`
- **Guide admin** : `GUIDE_ADMINISTRATEUR.md`
- **Documentation** : `README.md`

### Contacts
- **Support technique** : Via l'interface de notifications
- **Documentation** : Consulter les guides
- **Tests** : Voir `TESTS_DOCUMENTATION.md`

## ✅ Checklist de démarrage

- [ ] XAMPP installé et démarré
- [ ] Base de données créée et importée
- [ ] Configuration modifiée
- [ ] Application accessible
- [ ] Connexion admin réussie
- [ ] Test de création d'utilisateur
- [ ] Test d'assignation
- [ ] Test de publication
- [ ] Test de consultation client
- [ ] Mot de passe admin changé

---

## 🎉 Félicitations !

Vous avez maintenant **SocialFlow** opérationnel en moins de 15 minutes !

### Ce que vous pouvez faire maintenant :
- ✅ **Tester** toutes les fonctionnalités
- ✅ **Créer** des utilisateurs
- ✅ **Gérer** les publications
- ✅ **Configurer** l'application
- ✅ **Développer** de nouvelles fonctionnalités

### Prochaines étapes recommandées :
1. **Explorer** les différentes interfaces
2. **Créer** vos propres utilisateurs
3. **Tester** le workflow complet
4. **Personnaliser** l'application
5. **Préparer** le déploiement en production

**🚀 Bonne utilisation de SocialFlow !**
