# SocialFlow - Plateforme de Gestion de Contenu Réseaux Sociaux

## 📋 Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Fonctionnalités](#fonctionnalités)
3. [Architecture technique](#architecture-technique)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Utilisation](#utilisation)
7. [Structure du projet](#structure-du-projet)
8. [Base de données](#base-de-données)
9. [Sécurité](#sécurité)
10. [Améliorations apportées](#améliorations-apportées)
11. [Support](#support)

## 🎯 Vue d'ensemble

SocialFlow est une plateforme web complète permettant d'automatiser la gestion et la publication de contenus sur les réseaux sociaux. L'application met en relation trois types d'acteurs :

- **Clients** : Utilisateurs finaux souhaitant automatiser leurs publications
- **Community Managers** : Professionnels assignés aux clients pour gérer leurs publications
- **Administrateurs** : Superviseurs du système avec accès complet

### Objectifs du projet

- Automatiser la publication de contenus sur les réseaux sociaux
- Faciliter la collaboration entre clients et community managers
- Fournir des statistiques détaillées sur les performances
- Intégrer un système de paiement sécurisé
- Offrir une interface moderne et intuitive

## ✨ Fonctionnalités

### 🔵 Interface Client

- **Dashboard personnalisé** avec vue d'ensemble des publications et statistiques
- **Consultation des publications** créées par le Community Manager
- **Statistiques détaillées** (likes, partages, commentaires, portée)
- **Gestion de l'abonnement** avec paiement sécurisé
- **Notifications en temps réel** pour les événements importants
- **Paramètres personnalisables** (préférences, notifications)

### 🟢 Interface Community Manager

- **Dashboard de gestion** avec vue d'ensemble des clients assignés
- **Création et gestion des publications** (texte, images, vidéos)
- **Planification des publications** avec calendrier intégré
- **Gestion des brouillons** et de la corbeille
- **Analytics avancées** pour optimiser les performances
- **Collaboration** avec invitation d'autres professionnels
- **Gestion des comptes réseaux sociaux** des clients

### 🟠 Interface Administrateur

- **Supervision globale** du système et des utilisateurs
- **Gestion des utilisateurs** (création, modification, suspension)
- **Assignation des clients** aux Community Managers
- **Statistiques globales** et rapports de performance
- **Gestion des abonnements** et paiements
- **Monitoring de l'activité** avec logs détaillés

### 💳 Système de Paiement

- **Paiement Mobile Money** (Orange Money, MTN)
- **Paiement par carte bancaire** (Visa, Mastercard)
- **Abonnements flexibles** (mensuel/annuel)
- **Gestion automatique** des renouvellements
- **Historique des transactions** complet

### 🔔 Système de Notifications

- **Notifications en temps réel** pour tous les événements
- **Types de notifications** : info, succès, avertissement, erreur
- **Notifications personnalisées** selon le rôle utilisateur
- **Gestion des préférences** de notification
- **Historique complet** des notifications

## 🏗️ Architecture technique

### Technologies utilisées

- **Backend** : PHP 8.0+ avec PDO pour la base de données
- **Frontend** : HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS** : Tailwind CSS pour un design moderne
- **Base de données** : MySQL 8.0+ avec InnoDB
- **Serveur web** : Apache (XAMPP)
- **Icônes** : Font Awesome 6.0

### Architecture MVC simplifiée

```
├── config/          # Configuration (base de données)
├── includes/        # Fonctions utilitaires et notifications
├── auth/           # Authentification (login, register, logout)
├── client/         # Interface client
├── cm/             # Interface Community Manager
├── admin/          # Interface administrateur
├── database/       # Scripts SQL
└── assets/         # Ressources statiques (CSS, JS, images)
```

### Sécurité

- **Authentification sécurisée** avec hachage des mots de passe
- **Protection CSRF** avec tokens
- **Validation et sanitisation** de toutes les entrées
- **Gestion des sessions** sécurisée
- **Permissions basées sur les rôles**
- **Logs d'activité** pour le monitoring

## 🚀 Installation

### Prérequis

- **XAMPP** (Apache, MySQL, PHP 8.0+)
- **Navigateur web** moderne (Chrome, Firefox, Safari, Edge)
- **Compte administrateur** MySQL

### Étapes d'installation

1. **Cloner le projet**
   ```bash
   git clone [URL_DU_REPO]
   cd SF2
   ```

2. **Démarrer XAMPP**
   - Lancer Apache et MySQL depuis le panneau de contrôle XAMPP

3. **Créer la base de données**
   ```sql
   -- Ouvrir phpMyAdmin (http://localhost/phpmyadmin)
   -- Exécuter le script database/socialflow_db.sql
   ```

4. **Configurer la base de données**
   ```php
   // Modifier config/database.php si nécessaire
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'socialflow_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

5. **Accéder à l'application**
   ```
   http://localhost/SF2
   ```

### Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Client | client@socialflow.com | password |
| Community Manager | cm@socialflow.com | password |
| Administrateur | admin@socialflow.com | password |

## ⚙️ Configuration

### Variables d'environnement

```php
// config/database.php
define('DB_HOST', 'localhost');        // Hôte MySQL
define('DB_NAME', 'socialflow_db');    // Nom de la base
define('DB_USER', 'root');             // Utilisateur MySQL
define('DB_PASS', '');                 // Mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');       // Encodage
```

### Paramètres de sécurité

```php
// includes/functions.php
// Configuration des sessions
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => false,  // true en HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

## 📖 Utilisation

### Premier démarrage

1. **Connexion administrateur**
   - Se connecter avec `admin@socialflow.com`
   - Créer des comptes Community Manager
   - Assigner des clients aux Community Managers

2. **Inscription client**
   - Créer un compte client via l'interface d'inscription
   - Choisir un plan d'abonnement
   - Effectuer le paiement

3. **Gestion des publications**
   - Le Community Manager crée et planifie les publications
   - Le client peut consulter ses publications et statistiques
   - Notifications automatiques pour tous les événements

### Workflow typique

1. **Client** s'inscrit et souscrit à un abonnement
2. **Administrateur** assigne un Community Manager au client
3. **Community Manager** crée et planifie les publications
4. **Système** publie automatiquement sur les réseaux sociaux
5. **Client** consulte les statistiques et performances
6. **Notifications** informent de tous les événements

## 📁 Structure du projet

```
SF2/
├── index.php                 # Page d'accueil
├── config/
│   └── database.php         # Configuration base de données
├── includes/
│   ├── functions.php        # Fonctions utilitaires
│   └── notifications.php    # Système de notifications
├── auth/
│   ├── login.php           # Page de connexion
│   ├── register.php        # Page d'inscription
│   └── logout.php          # Déconnexion
├── client/
│   ├── dashboard.php       # Dashboard client
│   ├── subscription.php    # Gestion abonnement
│   └── notifications.php   # Notifications client
├── cm/
│   └── dashboard.php       # Dashboard Community Manager
├── admin/
│   └── dashboard.php       # Dashboard administrateur
├── database/
│   └── socialflow_db.sql   # Script de création BDD
└── README.md               # Documentation
```

## 🗄️ Base de données

### Tables principales

#### `users`
- Stockage des informations utilisateurs (clients, CM, admins)
- Gestion des rôles et permissions
- Authentification sécurisée

#### `subscriptions`
- Gestion des abonnements clients
- Plans mensuels/annuels
- Statuts et dates d'expiration

#### `payments`
- Historique des paiements
- Méthodes de paiement (Mobile Money, Orange Money, Carte)
- Statuts des transactions

#### `posts`
- Publications créées par les Community Managers
- Contenu, médias, plateformes ciblées
- Statuts et planification

#### `notifications`
- Système de notifications en temps réel
- Types et priorités
- Gestion des lectures

#### `client_assignments`
- Liaison clients ↔ Community Managers
- Gestion des assignations
- Historique des changements

### Relations

```
users (1) ←→ (N) subscriptions
users (1) ←→ (N) posts
users (1) ←→ (N) notifications
subscriptions (1) ←→ (N) payments
client_assignments (N) ←→ (1) users (clients)
client_assignments (N) ←→ (1) users (CM)
```

## 🔒 Sécurité

### Mesures implémentées

1. **Authentification**
   - Hachage sécurisé des mots de passe (password_hash)
   - Vérification des mots de passe (password_verify)
   - Gestion des sessions sécurisée

2. **Autorisation**
   - Vérification des rôles pour chaque action
   - Redirection automatique si non autorisé
   - Protection des routes sensibles

3. **Validation des données**
   - Sanitisation de toutes les entrées utilisateur
   - Validation des emails et téléphones
   - Protection contre l'injection SQL (PDO prepared statements)

4. **Protection CSRF**
   - Génération de tokens CSRF
   - Vérification des tokens sur les formulaires
   - Protection contre les attaques cross-site

5. **Logs de sécurité**
   - Enregistrement de toutes les activités
   - Monitoring des connexions
   - Détection d'anomalies

## 🚀 Améliorations apportées

### Fonctionnalités ajoutées

1. **Interface moderne et responsive**
   - Design avec Tailwind CSS
   - Animations et transitions fluides
   - Interface adaptative mobile/desktop

2. **Système de notifications avancé**
   - Notifications en temps réel
   - Types de notifications personnalisés
   - Gestion des préférences utilisateur

3. **Système de paiement intégré**
   - Support Mobile Money et Orange Money
   - Paiement par carte bancaire
   - Gestion automatique des abonnements

4. **Dashboard personnalisés**
   - Interface adaptée à chaque rôle
   - Statistiques visuelles
   - Navigation intuitive

5. **Sécurité renforcée**
   - Protection CSRF
   - Validation stricte des données
   - Logs d'activité complets

### Optimisations techniques

1. **Performance**
   - Requêtes SQL optimisées
   - Chargement asynchrone des données
   - Mise en cache des sessions

2. **Maintenabilité**
   - Code modulaire et documenté
   - Séparation des responsabilités
   - Fonctions utilitaires réutilisables

3. **Expérience utilisateur**
   - Feedback visuel immédiat
   - Messages d'erreur clairs
   - Navigation intuitive

## 🛠️ Développement

### Ajout de nouvelles fonctionnalités

1. **Créer une nouvelle page**
   ```php
   // Vérifier les permissions
   check_permission('role_required');
   
   // Récupérer les données
   $data = get_data_from_database();
   
   // Afficher la page
   include 'template.php';
   ```

2. **Ajouter une nouvelle notification**
   ```php
   // Dans includes/notifications.php
   function notify_new_event($user_id, $message) {
       return create_notification($user_id, 'Titre', $message, 'info');
   }
   ```

3. **Créer une nouvelle table**
   ```sql
   -- Dans database/socialflow_db.sql
   CREATE TABLE new_table (
       id INT PRIMARY KEY AUTO_INCREMENT,
       -- colonnes...
   );
   ```

### Tests

1. **Tests fonctionnels**
   - Tester tous les parcours utilisateur
   - Vérifier les permissions par rôle
   - Valider les formulaires

2. **Tests de sécurité**
   - Tenter des injections SQL
   - Tester les tokens CSRF
   - Vérifier les validations

3. **Tests de performance**
   - Chargement des pages
   - Requêtes de base de données
   - Gestion de la mémoire

## 📞 Support

### Documentation

- **README.md** : Documentation complète du projet
- **Commentaires dans le code** : Documentation technique
- **Base de données** : Schéma et relations

### Contact

Pour toute question ou problème :

1. **Vérifier la documentation** dans ce README
2. **Consulter les logs** dans les fichiers d'erreur PHP
3. **Tester avec les comptes de démonstration**
4. **Vérifier la configuration** de la base de données

### Maintenance

- **Sauvegardes régulières** de la base de données
- **Mise à jour des dépendances** (PHP, MySQL)
- **Monitoring des performances** et logs d'erreur
- **Tests de sécurité** périodiques

---

## 📝 Notes de version

### Version 1.0.0 (Décembre 2024)

- ✅ Interface client complète
- ✅ Interface Community Manager
- ✅ Interface administrateur
- ✅ Système de paiement intégré
- ✅ Notifications en temps réel
- ✅ Base de données optimisée
- ✅ Sécurité renforcée
- ✅ Design moderne et responsive

### Prochaines versions

- 🔄 Intégration API réseaux sociaux
- 🔄 Système de templates de publications
- 🔄 Analytics avancées
- 🔄 Application mobile
- 🔄 API REST complète

---

**SocialFlow** - Automatisez votre présence sur les réseaux sociaux avec des professionnels dédiés.

*Développé avec ❤️ pour simplifier la gestion de contenu social media.*
