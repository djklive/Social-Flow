# 👑 Guide Administrateur - SocialFlow

## 🎯 Introduction

Ce guide est destiné aux **Administrateurs** de la plateforme SocialFlow. Il couvre toutes les fonctionnalités d'administration, la gestion des utilisateurs, la configuration système et les bonnes pratiques de maintenance.

## 📋 Table des matières

1. [Accès administrateur](#accès-administrateur)
2. [Dashboard administrateur](#dashboard-administrateur)
3. [Gestion des utilisateurs](#gestion-des-utilisateurs)
4. [Gestion des assignations](#gestion-des-assignations)
5. [Gestion des paiements](#gestion-des-paiements)
6. [Configuration système](#configuration-système)
7. [Analytics et rapports](#analytics-et-rapports)
8. [Maintenance et sécurité](#maintenance-et-sécurité)
9. [Procédures d'urgence](#procédures-durgence)

---

## 🔐 Accès administrateur

### Compte administrateur par défaut

| Information | Valeur |
|-------------|--------|
| **Email** | `admin@socialflow.com` |
| **Mot de passe** | `password` |
| **Rôle** | Administrateur |

⚠️ **IMPORTANT** : Changez immédiatement le mot de passe par défaut après la première connexion !

### Première connexion

1. **Accéder à la page de connexion**
   ```
   http://votre-domaine.com/socialflow/auth/login.php
   ```

2. **Se connecter avec les identifiants par défaut**

3. **Changer le mot de passe**
   - Aller dans `Paramètres` → `Sécurité`
   - Saisir l'ancien mot de passe
   - Saisir le nouveau mot de passe (minimum 8 caractères)
   - Confirmer le nouveau mot de passe

4. **Configurer le profil**
   - Mettre à jour les informations personnelles
   - Ajouter une photo de profil
   - Configurer les préférences

---

## 🏠 Dashboard administrateur

### Vue d'ensemble

Le dashboard administrateur fournit une vue complète de l'état du système :

#### Métriques principales
- **👥 Utilisateurs totaux** : Nombre total d'utilisateurs inscrits
- **🆕 Nouveaux utilisateurs** : Inscriptions du mois en cours
- **💰 Revenus mensuels** : Chiffre d'affaires du mois
- **📊 Publications actives** : Publications publiées ce mois

#### Graphiques en temps réel
- **Évolution des utilisateurs** : Croissance mensuelle
- **Revenus par période** : Graphique des revenus
- **Activité des publications** : Publications par jour
- **Performance des CM** : Comparaison des Community Managers

#### Alertes et notifications
- **⚠️ Paiements en attente** : Paiements non traités
- **🔔 Abonnements expirés** : Abonnements à renouveler
- **❌ Erreurs système** : Problèmes techniques
- **👤 Demandes d'assistance** : Tickets de support

### Actions rapides

#### Boutons d'action principaux
- **➕ Nouvel utilisateur** : Créer un compte utilisateur
- **🔗 Assigner client** : Assigner un client à un CM
- **💰 Traiter paiement** : Valider un paiement en attente
- **📊 Voir rapports** : Accéder aux analytics détaillées

---

## 👥 Gestion des utilisateurs

### Liste des utilisateurs

#### Accès à la gestion
- Menu : `Utilisateurs` → `Gestion des utilisateurs`
- URL directe : `admin/users.php`

#### Informations affichées
- **ID** : Identifiant unique
- **Nom complet** : Prénom et nom
- **Email** : Adresse email
- **Rôle** : Client, Community Manager, Admin
- **Statut** : Actif, Inactif, Suspendu
- **Date d'inscription** : Date de création du compte
- **Dernière connexion** : Dernière activité
- **Abonnement** : Statut de l'abonnement (pour les clients)

#### Filtres et recherche
- **Par rôle** : Filtrer par type d'utilisateur
- **Par statut** : Actif, Inactif, Suspendu
- **Par date** : Période d'inscription
- **Recherche** : Par nom, email ou ID

### Création d'utilisateurs

#### Nouvel utilisateur
1. **Accéder à la création**
   - Bouton "➕ Nouvel utilisateur"
   - Menu : `Utilisateurs` → `Créer un utilisateur`

2. **Informations requises**
   - **Informations personnelles**
     - Prénom (obligatoire)
     - Nom (obligatoire)
     - Email (obligatoire, unique)
     - Téléphone (optionnel)
   - **Sécurité**
     - Mot de passe (minimum 8 caractères)
     - Confirmation du mot de passe
   - **Rôle et permissions**
     - Rôle : Client, Community Manager, Admin
     - Statut : Actif, Inactif, Suspendu
   - **Paramètres avancés**
     - Email vérifié : Oui/Non
     - Téléphone vérifié : Oui/Non
     - Photo de profil (optionnel)

3. **Validation et création**
   - Vérification des données
   - Création du compte
   - Envoi d'email de bienvenue (optionnel)

#### Import en masse
1. **Préparation du fichier CSV**
   ```csv
   first_name,last_name,email,phone,role,status
   Jean,Martin,jean.martin@email.com,+237123456789,client,active
   Marie,Dubois,marie.dubois@email.com,+237987654321,community_manager,active
   ```

2. **Import**
   - Menu : `Utilisateurs` → `Import en masse`
   - Sélectionner le fichier CSV
   - Validation des données
   - Confirmation de l'import

### Modification des utilisateurs

#### Édition du profil
1. **Accéder à l'édition**
   - Clic sur l'utilisateur dans la liste
   - Bouton "Modifier" dans les détails

2. **Informations modifiables**
   - Informations personnelles
   - Statut du compte
   - Rôle et permissions
   - Paramètres de sécurité

#### Actions sur les utilisateurs
- **🔒 Suspendre** : Désactiver temporairement le compte
- **✅ Réactiver** : Réactiver un compte suspendu
- **🔄 Réinitialiser mot de passe** : Envoyer un nouveau mot de passe
- **📧 Renvoyer email de vérification** : Relancer la vérification
- **🗑️ Supprimer** : Supprimer définitivement (avec confirmation)

### Gestion des rôles

#### Rôles disponibles
1. **Client**
   - Consultation des publications
   - Gestion de l'abonnement
   - Accès aux statistiques
   - Contact avec le CM

2. **Community Manager**
   - Gestion des clients assignés
   - Création de publications
   - Accès aux analytics
   - Gestion des brouillons

3. **Administrateur**
   - Accès complet au système
   - Gestion des utilisateurs
   - Configuration système
   - Accès aux rapports

#### Changement de rôle
1. **Sélectionner l'utilisateur**
2. **Modifier le rôle**
3. **Confirmer le changement**
4. **Notifier l'utilisateur** (optionnel)

---

## 🔗 Gestion des assignations

### Vue d'ensemble des assignations

#### Accès à la gestion
- Menu : `Assignations` → `Gestion des assignations`
- URL directe : `admin/assignations.php`

#### Informations affichées
- **Client** : Nom et contact
- **Community Manager** : Nom et contact
- **Date d'assignation** : Quand l'assignation a été faite
- **Statut** : Actif, Inactif
- **Notes** : Commentaires sur l'assignation
- **Performance** : Nombre de publications, satisfaction

### Création d'assignations

#### Nouvelle assignation
1. **Accéder à la création**
   - Bouton "➕ Nouvelle assignation"
   - Menu : `Assignations` → `Assigner un client`

2. **Sélection des participants**
   - **Client** : Choisir dans la liste des clients
   - **Community Manager** : Choisir dans la liste des CM
   - **Vérification** : S'assurer que le client n'est pas déjà assigné

3. **Configuration de l'assignation**
   - **Date d'assignation** : Date de début (par défaut : aujourd'hui)
   - **Notes** : Commentaires sur l'assignation
   - **Priorité** : Normal, Haute, Urgente

4. **Confirmation**
   - Vérification des informations
   - Notification aux parties concernées
   - Création de l'assignation

#### Assignation automatique
1. **Configuration des règles**
   - Répartition équitable des clients
   - Spécialisation par secteur
   - Charge de travail maximale par CM

2. **Exécution automatique**
   - Nouveaux clients assignés automatiquement
   - Rééquilibrage périodique
   - Notifications automatiques

### Modification des assignations

#### Changement d'assignation
1. **Motifs de changement**
   - Demande du client
   - Surcharge du CM
   - Spécialisation requise
   - Performance insatisfaisante

2. **Processus de changement**
   - Sélectionner l'assignation
   - Choisir le nouveau CM
   - Définir la date de changement
   - Transférer l'historique
   - Notifier les parties

#### Gestion des conflits
1. **Détection des conflits**
   - Client déjà assigné
   - CM surchargé
   - Incompatibilité de spécialisation

2. **Résolution**
   - Notification des conflits
   - Suggestions de résolution
   - Validation manuelle

### Équilibrage de la charge

#### Répartition des clients
1. **Métriques de charge**
   - Nombre de clients par CM
   - Nombre de publications par CM
   - Temps de réponse moyen
   - Satisfaction client

2. **Optimisation**
   - Répartition équitable
   - Spécialisation par secteur
   - Ajustement selon la performance

#### Tableau de bord des CM
- **Charge de travail** : Nombre de clients actifs
- **Performance** : Taux de satisfaction
- **Activité** : Publications créées
- **Disponibilité** : Statut en ligne/hors ligne

---

## 💰 Gestion des paiements

### Vue d'ensemble des paiements

#### Accès à la gestion
- Menu : `Paiements` → `Gestion des paiements`
- URL directe : `admin/payments.php`

#### Informations affichées
- **ID Transaction** : Identifiant unique
- **Client** : Nom et email
- **Montant** : Montant en FCFA
- **Méthode** : Mobile Money, Orange Money, Carte
- **Statut** : En attente, Complété, Échoué, Remboursé
- **Date** : Date de la transaction
- **Référence** : Référence de paiement

#### Filtres disponibles
- **Par statut** : Tous, En attente, Complété, Échoué
- **Par méthode** : Toutes, Mobile Money, Orange Money, Carte
- **Par période** : Aujourd'hui, Cette semaine, Ce mois
- **Par montant** : Fourchette de montants

### Traitement des paiements

#### Paiements en attente
1. **Liste des paiements en attente**
   - Transactions non validées
   - Vérification manuelle requise
   - Actions disponibles

2. **Validation d'un paiement**
   - Vérifier la référence
   - Confirmer le montant
   - Valider la transaction
   - Activer l'abonnement

3. **Rejet d'un paiement**
   - Motif du rejet
   - Notification au client
   - Possibilité de nouveau paiement

#### Paiements échoués
1. **Identification des échecs**
   - Solde insuffisant
   - Compte bloqué
   - Erreur technique
   - Fraude détectée

2. **Actions correctives**
   - Notification au client
   - Nouvelle tentative
   - Changement de méthode
   - Support client

### Gestion des remboursements

#### Demande de remboursement
1. **Motifs acceptés**
   - Erreur de facturation
   - Service non fourni
   - Demande du client
   - Problème technique

2. **Processus de remboursement**
   - Validation de la demande
   - Calcul du montant
   - Exécution du remboursement
   - Notification au client

#### Traitement des remboursements
1. **Validation**
   - Vérifier l'éligibilité
   - Calculer le montant
   - Obtenir l'approbation

2. **Exécution**
   - Initier le remboursement
   - Suivre le statut
   - Confirmer la réception

### Rapports financiers

#### Revenus
1. **Revenus par période**
   - Journalier, hebdomadaire, mensuel
   - Comparaison avec les périodes précédentes
   - Tendances et prévisions

2. **Revenus par méthode de paiement**
   - Répartition des paiements
   - Performance des méthodes
   - Optimisation des options

#### Coûts et marges
1. **Coûts d'exploitation**
   - Infrastructure
   - Personnel
   - Marketing
   - Support

2. **Analyse de rentabilité**
   - Marge par client
   - Coût d'acquisition
   - Valeur vie client

---

## ⚙️ Configuration système

### Paramètres généraux

#### Configuration de l'application
1. **Informations de base**
   - Nom de l'application
   - URL de base
   - Version
   - Description

2. **Paramètres régionaux**
   - Fuseau horaire
   - Devise par défaut
   - Format de date
   - Langue par défaut

#### Configuration des abonnements
1. **Plans d'abonnement**
   - Prix mensuel/annuel
   - Fonctionnalités incluses
   - Limites d'utilisation
   - Périodes d'essai

2. **Gestion des prix**
   - Modification des tarifs
   - Promotions temporaires
   - Remises par volume
   - Taxes et frais

### Configuration des emails

#### Paramètres SMTP
1. **Configuration du serveur**
   - Serveur SMTP
   - Port (587, 465, 25)
   - Authentification
   - Chiffrement (TLS/SSL)

2. **Comptes d'email**
   - Email d'envoi
   - Nom d'affichage
   - Emails de support
   - Emails de notification

#### Templates d'emails
1. **Emails système**
   - Bienvenue
   - Confirmation de paiement
   - Rappel d'abonnement
   - Notification de publication

2. **Personnalisation**
   - Logo et couleurs
   - Contenu personnalisé
   - Variables dynamiques
   - Traductions

### Configuration de sécurité

#### Politique de mots de passe
1. **Exigences**
   - Longueur minimale
   - Complexité requise
   - Expiration
   - Historique

2. **Authentification**
   - Tentatives de connexion
   - Blocage temporaire
   - Authentification à deux facteurs
   - Sessions multiples

#### Gestion des sessions
1. **Configuration des sessions**
   - Durée de vie
   - Renouvellement automatique
   - Déconnexion automatique
   - Sessions simultanées

2. **Sécurité des sessions**
   - Chiffrement
   - Validation IP
   - Rotation des tokens
   - Logs de connexion

### Configuration des réseaux sociaux

#### Intégration des APIs
1. **Facebook/Instagram**
   - App ID et Secret
   - Tokens d'accès
   - Permissions
   - Webhooks

2. **Autres plateformes**
   - Twitter API
   - LinkedIn API
   - TikTok API
   - YouTube API

#### Gestion des tokens
1. **Stockage sécurisé**
   - Chiffrement des tokens
   - Rotation automatique
   - Expiration
   - Sauvegarde

2. **Monitoring**
   - Statut des connexions
   - Erreurs d'API
   - Limites de taux
   - Alertes

---

## 📊 Analytics et rapports

### Dashboard analytics

#### Métriques principales
1. **Utilisateurs**
   - Croissance des utilisateurs
   - Rétention
   - Churn rate
   - Segmentation

2. **Engagement**
   - Publications par utilisateur
   - Taux d'engagement
   - Temps passé sur la plateforme
   - Fonctionnalités utilisées

#### Graphiques interactifs
1. **Évolution temporelle**
   - Utilisateurs actifs
   - Revenus
   - Publications
   - Support

2. **Comparaisons**
   - Période précédente
   - Objectifs
   - Benchmarks
   - Prédictions

### Rapports détaillés

#### Rapport utilisateurs
1. **Acquisition**
   - Sources d'acquisition
   - Coût par acquisition
   - Conversion
   - Qualité des leads

2. **Comportement**
   - Parcours utilisateur
   - Points de friction
   - Fonctionnalités populaires
   - Abandons

#### Rapport financier
1. **Revenus**
   - Revenus récurrents
   - Revenus ponctuels
   - Évolution des prix
   - Prévisions

2. **Coûts**
   - Coûts d'acquisition
   - Coûts d'exploitation
   - Coûts de support
   - ROI

#### Rapport opérationnel
1. **Performance des CM**
   - Charge de travail
   - Satisfaction client
   - Qualité des publications
   - Efficacité

2. **Support client**
   - Volume de tickets
   - Temps de résolution
   - Satisfaction
   - Types de problèmes

### Export des données

#### Formats d'export
1. **Formats supportés**
   - CSV
   - Excel
   - PDF
   - JSON

2. **Données exportables**
   - Utilisateurs
   - Paiements
   - Publications
   - Analytics

#### Planification des rapports
1. **Rapports automatiques**
   - Fréquence (quotidien, hebdomadaire, mensuel)
   - Destinataires
   - Format
   - Contenu

2. **Alertes**
   - Seuils de performance
   - Anomalies détectées
   - Notifications automatiques

---

## 🔧 Maintenance et sécurité

### Maintenance préventive

#### Sauvegardes
1. **Sauvegarde de la base de données**
   ```bash
   # Sauvegarde quotidienne
   mysqldump -u username -p socialflow_db > backup_$(date +%Y%m%d).sql
   
   # Compression
   gzip backup_$(date +%Y%m%d).sql
   ```

2. **Sauvegarde des fichiers**
   ```bash
   # Sauvegarde des fichiers
   tar -czf files_backup_$(date +%Y%m%d).tar.gz /var/www/html/socialflow
   ```

3. **Planification automatique**
   - Sauvegarde quotidienne à 2h du matin
   - Rétention de 30 jours
   - Test de restauration mensuel

#### Monitoring système
1. **Métriques à surveiller**
   - Utilisation CPU/RAM
   - Espace disque
   - Temps de réponse
   - Erreurs applicatives

2. **Alertes configurées**
   - Seuil d'utilisation > 80%
   - Temps de réponse > 5s
   - Erreurs > 10/min
   - Espace disque < 20%

#### Mises à jour
1. **Mises à jour de sécurité**
   - PHP
   - MySQL
   - Extensions
   - Dépendances

2. **Mises à jour applicatives**
   - Tests en environnement de développement
   - Déploiement en maintenance
   - Tests de régression
   - Rollback si nécessaire

### Sécurité

#### Audit de sécurité
1. **Vérifications régulières**
   - Mots de passe faibles
   - Comptes inactifs
   - Permissions de fichiers
   - Logs de sécurité

2. **Tests de pénétration**
   - Tests automatisés
   - Tests manuels
   - Correction des vulnérabilités
   - Documentation

#### Gestion des incidents
1. **Détection d'incidents**
   - Monitoring automatique
   - Alertes en temps réel
   - Escalade automatique
   - Communication

2. **Réponse aux incidents**
   - Procédure d'urgence
   - Isolation du problème
   - Correction
   - Post-mortem

### Performance

#### Optimisation de la base de données
1. **Index et requêtes**
   - Analyse des requêtes lentes
   - Optimisation des index
   - Requêtes optimisées
   - Cache de requêtes

2. **Maintenance de la base**
   - Nettoyage des logs
   - Optimisation des tables
   - Archivage des données anciennes
   - Monitoring des performances

#### Optimisation de l'application
1. **Cache**
   - Cache des requêtes
   - Cache des pages
   - Cache des sessions
   - Invalidation du cache

2. **CDN et ressources**
   - Mise en cache des assets
   - Compression
   - Minification
   - Optimisation des images

---

## 🚨 Procédures d'urgence

### Incidents critiques

#### Panne de serveur
1. **Détection**
   - Monitoring automatique
   - Alertes immédiates
   - Vérification manuelle

2. **Actions immédiates**
   - Redémarrage des services
   - Basculement sur serveur de secours
   - Communication aux utilisateurs
   - Investigation

#### Attaque de sécurité
1. **Détection**
   - Tentatives de connexion suspectes
   - Activité anormale
   - Alertes de sécurité

2. **Réponse**
   - Isolation des comptes compromis
   - Blocage des IPs suspectes
   - Changement des mots de passe
   - Investigation approfondie

#### Perte de données
1. **Détection**
   - Erreurs de base de données
   - Fichiers manquants
   - Incohérences détectées

2. **Récupération**
   - Restauration depuis sauvegarde
   - Vérification de l'intégrité
   - Tests de fonctionnement
   - Communication aux utilisateurs

### Contacts d'urgence

#### Équipe technique
- **Responsable technique** : [Contact]
- **Développeur principal** : [Contact]
- **Administrateur système** : [Contact]

#### Fournisseurs
- **Hébergement** : [Contact support]
- **Base de données** : [Contact support]
- **Paiements** : [Contact support]

#### Procédures de communication
1. **Communication interne**
   - Slack/Teams
   - Email d'urgence
   - Téléphone

2. **Communication externe**
   - Page de statut
   - Email aux utilisateurs
   - Réseaux sociaux

### Plan de continuité

#### Sauvegarde de secours
1. **Serveur de secours**
   - Configuration identique
   - Données synchronisées
   - Tests réguliers
   - Basculement automatique

2. **Procédure de basculement**
   - Détection de panne
   - Basculement DNS
   - Vérification du fonctionnement
   - Communication

#### Récupération après sinistre
1. **Objectifs de récupération**
   - RTO (Recovery Time Objective) : 4 heures
   - RPO (Recovery Point Objective) : 1 heure
   - Disponibilité : 99.9%

2. **Procédure de récupération**
   - Évaluation des dégâts
   - Restauration des sauvegardes
   - Tests de fonctionnement
   - Remise en service

---

## 📞 Support et ressources

### Documentation technique
- **Guide d'installation** : `GUIDE_INSTALLATION.md`
- **Guide utilisateur** : `GUIDE_UTILISATEUR.md`
- **Documentation API** : `API_DOCUMENTATION.md`
- **Tests** : `TESTS_DOCUMENTATION.md`

### Outils d'administration
- **phpMyAdmin** : Gestion de la base de données
- **Logs système** : `/var/log/apache2/error.log`
- **Logs applicatifs** : `logs/app.log`
- **Monitoring** : Outils de surveillance système

### Formation et support
- **Formation administrateur** : Session de formation recommandée
- **Support technique** : Via tickets ou email
- **Communauté** : Forum de discussion
- **Mises à jour** : Newsletter technique

---

**🎯 Ce guide vous donne tous les outils nécessaires pour administrer efficacement la plateforme SocialFlow. En cas de question ou de problème, n'hésitez pas à consulter la documentation ou à contacter le support technique.**
