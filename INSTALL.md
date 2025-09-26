# Guide d'installation - SocialFlow

## 🚀 Installation rapide

### 1. Prérequis

- **XAMPP** installé et fonctionnel
- **PHP 8.0+** (inclus dans XAMPP)
- **MySQL 8.0+** (inclus dans XAMPP)
- **Navigateur web** moderne

### 2. Installation étape par étape

#### Étape 1 : Préparation de XAMPP

1. Téléchargez et installez [XAMPP](https://www.apachefriends.org/)
2. Lancez XAMPP Control Panel
3. Démarrez **Apache** et **MySQL**
4. Vérifiez que les services sont actifs (icônes vertes)

#### Étape 2 : Installation du projet

1. **Copiez le dossier SF2** dans `C:\xampp\htdocs\`
2. **Structure finale** : `C:\xampp\htdocs\SF2\`

#### Étape 3 : Configuration de la base de données

1. Ouvrez **phpMyAdmin** : http://localhost/phpmyadmin
2. Cliquez sur **"Nouvelle base de données"**
3. Nom : `socialflow_db`
4. Interclassement : `utf8mb4_unicode_ci`
5. Cliquez sur **"Créer"**

#### Étape 4 : Importation des données

1. Sélectionnez la base `socialflow_db`
2. Cliquez sur l'onglet **"Importer"**
3. Cliquez sur **"Choisir un fichier"**
4. Sélectionnez `database/socialflow_db.sql`
5. Cliquez sur **"Exécuter"**

#### Étape 5 : Vérification de l'installation

1. Ouvrez votre navigateur
2. Allez sur : http://localhost/SF2
3. Vous devriez voir la page d'accueil de SocialFlow

### 3. Comptes de test

| Rôle | Email | Mot de passe | Description |
|------|-------|--------------|-------------|
| **Client** | client@socialflow.com | password | Compte client de démonstration |
| **Community Manager** | cm@socialflow.com | password | Compte CM avec client assigné |
| **Administrateur** | admin@socialflow.com | password | Compte admin avec accès complet |

### 4. Test de l'installation

#### Test 1 : Connexion
1. Cliquez sur **"Connexion"**
2. Sélectionnez le rôle **"Client"**
3. Email : `client@socialflow.com`
4. Mot de passe : `password`
5. Vous devriez accéder au dashboard client

#### Test 2 : Navigation
1. Testez la navigation dans le dashboard
2. Vérifiez que les statistiques s'affichent
3. Consultez les publications de démonstration

#### Test 3 : Abonnement
1. Allez dans **"Abonnement"**
2. Testez la sélection d'un plan
3. Simulez un paiement (système de démonstration)

### 5. Configuration avancée

#### Modifier la configuration de la base de données

Si vous avez des problèmes de connexion, modifiez `config/database.php` :

```php
// Configuration par défaut XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialflow_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Mot de passe vide par défaut
```

#### Changer le port MySQL

Si MySQL n'utilise pas le port 3306 :

```php
define('DB_HOST', 'localhost:3307');  // Port personnalisé
```

### 6. Dépannage

#### Problème : Page blanche
- Vérifiez que Apache est démarré
- Consultez les logs d'erreur PHP
- Vérifiez la syntaxe PHP

#### Problème : Erreur de base de données
- Vérifiez que MySQL est démarré
- Vérifiez les paramètres dans `config/database.php`
- Testez la connexion dans phpMyAdmin

#### Problème : Fichiers non trouvés
- Vérifiez que le dossier est dans `htdocs`
- Vérifiez les permissions des fichiers
- Vérifiez l'URL : `http://localhost/SF2`

#### Problème : Erreur 403 Forbidden
- Vérifiez les permissions du dossier
- Vérifiez la configuration Apache
- Redémarrez Apache

### 7. Première utilisation

#### En tant qu'Administrateur

1. **Connectez-vous** avec `admin@socialflow.com`
2. **Explorez le dashboard** administrateur
3. **Consultez les statistiques** globales
4. **Gérez les utilisateurs** si nécessaire

#### En tant que Community Manager

1. **Connectez-vous** avec `cm@socialflow.com`
2. **Consultez vos clients** assignés
3. **Créez des publications** de test
4. **Planifiez des publications**

#### En tant que Client

1. **Connectez-vous** avec `client@socialflow.com`
2. **Consultez vos publications**
3. **Vérifiez vos statistiques**
4. **Testez l'abonnement**

### 8. Personnalisation

#### Modifier les couleurs

Éditez les classes Tailwind CSS dans les fichiers :
- `client/dashboard.php`
- `cm/dashboard.php`
- `admin/dashboard.php`

#### Ajouter des fonctionnalités

1. **Nouvelles pages** : Créez dans le dossier approprié
2. **Nouvelles tables** : Ajoutez dans `database/socialflow_db.sql`
3. **Nouvelles fonctions** : Ajoutez dans `includes/functions.php`

### 9. Sauvegarde

#### Sauvegarde de la base de données

```bash
# Via phpMyAdmin
1. Sélectionnez socialflow_db
2. Onglet "Exporter"
3. Format : SQL
4. Cliquez "Exécuter"

# Via ligne de commande
mysqldump -u root -p socialflow_db > backup.sql
```

#### Sauvegarde des fichiers

Copiez le dossier `SF2` vers un emplacement de sauvegarde.

### 10. Mise à jour

1. **Sauvegardez** la base de données
2. **Sauvegardez** les fichiers personnalisés
3. **Remplacez** les fichiers par les nouvelles versions
4. **Exécutez** les scripts de mise à jour SQL
5. **Testez** l'application

---

## ✅ Checklist d'installation

- [ ] XAMPP installé et démarré
- [ ] Dossier SF2 copié dans htdocs
- [ ] Base de données socialflow_db créée
- [ ] Script SQL importé avec succès
- [ ] Page d'accueil accessible
- [ ] Connexion admin fonctionnelle
- [ ] Connexion CM fonctionnelle
- [ ] Connexion client fonctionnelle
- [ ] Dashboard affiché correctement
- [ ] Système de paiement testé

---

## 🆘 Support

Si vous rencontrez des problèmes :

1. **Vérifiez** ce guide d'installation
2. **Consultez** le README.md principal
3. **Vérifiez** les logs d'erreur PHP
4. **Testez** avec les comptes de démonstration
5. **Redémarrez** Apache et MySQL

**SocialFlow** est prêt à être utilisé ! 🎉
