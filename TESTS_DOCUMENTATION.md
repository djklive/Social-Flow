# Documentation des Tests - SocialFlow

## Vue d'ensemble

Cette documentation présente la stratégie de tests complète pour la plateforme SocialFlow, incluant les tests unitaires, d'intégration et fonctionnels.

## Phase de Tests - SocialFlow

### 1. Tests Unitaires

Les tests unitaires vérifient le bon fonctionnement des fonctions individuelles de manière isolée.

#### Test 1 : Validation de l'email
**Objectif :** Vérifier que la fonction `validate_email()` fonctionne correctement

**Cas de test :**
- ✅ Emails valides : `test@example.com`, `user.name@domain.co.uk`
- ❌ Emails invalides : `invalid-email`, `@domain.com`, `user@`
- 🔍 Cas limites : emails très longs, caractères spéciaux

**Implémentation :**
```php
public function testValidEmail() {
    $validEmails = ['test@example.com', 'user.name@domain.co.uk'];
    foreach ($validEmails as $email) {
        $this->assertTrue(validate_email($email));
    }
}
```

#### Test 2 : Hachage des mots de passe
**Objectif :** Vérifier que le hachage et la vérification des mots de passe fonctionnent

**Cas de test :**
- ✅ Hachage correct avec bcrypt
- ✅ Vérification avec bon mot de passe
- ❌ Rejet des mauvais mots de passe
- 🔍 Unicité des hachages (salt aléatoire)

**Implémentation :**
```php
public function testPasswordHashing() {
    $password = 'motdepasse123';
    $hashed = hash_password($password);
    $this->assertStringStartsWith('$2y$', $hashed);
    $this->assertTrue(password_verify($password, $hashed));
}
```

### 2. Tests d'Intégration

Les tests d'intégration vérifient l'interaction entre plusieurs composants du système.

#### Test 1 : Création d'utilisateur avec base de données
**Objectif :** Vérifier le processus complet de création d'utilisateur

**Étapes testées :**
1. Validation des données d'entrée
2. Hachage du mot de passe
3. Insertion en base de données
4. Vérification de l'enregistrement
5. Test de connexion

**Implémentation :**
```php
public function testCreateUserIntegration() {
    $userData = ['email' => 'test@integration.com', 'password' => 'password123'];
    $hashedPassword = hash_password($userData['password']);
    
    $stmt = $this->db->prepare("INSERT INTO users ...");
    $result = $stmt->execute([...]);
    
    $this->assertTrue($result);
    $userId = $this->db->lastInsertId();
    $this->assertGreaterThan(0, $userId);
}
```

#### Test 2 : Création de publication avec assignation
**Objectif :** Vérifier le processus de création de publication avec les relations

**Étapes testées :**
1. Vérification de l'assignation client-CM
2. Création de la publication
3. Validation des données JSON (plateformes)
4. Mise à jour du statut
5. Programmation de publication

**Implémentation :**
```php
public function testCreatePostWithAssignment() {
    $postData = ['title' => 'Test Post', 'content' => 'Content', 'platforms' => ['facebook']];
    $platformsJson = json_encode($postData['platforms']);
    
    $stmt = $this->db->prepare("INSERT INTO posts ...");
    $result = $stmt->execute([...]);
    
    $this->assertTrue($result);
}
```

### 3. Tests Fonctionnels

Les tests fonctionnels vérifient des workflows complets et des scénarios utilisateur.

#### Test 1 : Processus complet de connexion
**Objectif :** Vérifier le workflow complet de connexion utilisateur

**Étapes testées :**
1. Vérification de l'existence de l'utilisateur
2. Validation des identifiants
3. Création de session
4. Mise à jour de `last_login`
5. Logging de l'activité
6. Redirection selon le rôle

**Implémentation :**
```php
public function testCompleteLoginProcess() {
    // 1. Vérifier l'utilisateur
    $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // 2. Valider les identifiants
    $this->assertTrue(password_verify($password, $user['password_hash']));
    
    // 3. Mettre à jour last_login
    $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $result = $stmt->execute([$user['id']]);
    
    // 4. Logger l'activité
    $logResult = log_activity($user['id'], 'user_login', 'Connexion utilisateur');
    $this->assertTrue($logResult);
}
```

#### Test 2 : Workflow complet de création de publication
**Objectif :** Vérifier le processus complet de création et publication

**Étapes testées :**
1. Vérification de l'assignation
2. Validation des données
3. Création de la publication
4. Mise à jour du statut (draft → scheduled → published)
5. Création de notifications
6. Logging des activités

**Implémentation :**
```php
public function testCompletePublicationWorkflow() {
    // 1. Vérifier l'assignation
    $this->assertNotNull($this->assignment);
    
    // 2. Créer la publication
    $stmt = $this->db->prepare("INSERT INTO posts ...");
    $result = $stmt->execute([...]);
    
    // 3. Programmer la publication
    $stmt = $this->db->prepare("UPDATE posts SET status = 'scheduled' ...");
    $result = $stmt->execute([...]);
    
    // 4. Publier
    $stmt = $this->db->prepare("UPDATE posts SET status = 'published' ...");
    $result = $stmt->execute([...]);
    
    // 5. Créer notification
    $stmt = $this->db->prepare("INSERT INTO notifications ...");
    $result = $stmt->execute([...]);
}
```

## Outils et Technologies Utilisés

### 1. PHPUnit
- **Version :** 9.5+
- **Configuration :** `phpunit.xml`
- **Bootstrap :** `tests/bootstrap.php`

### 2. Base de Données de Test
- **Base :** `socialflow_test`
- **Nettoyage :** Automatique après chaque test
- **Données :** Générées dynamiquement

### 3. Rapports
- **Couverture :** HTML et texte
- **JUnit :** Pour l'intégration CI/CD
- **TestDox :** Rapports lisibles

## Métriques de Qualité

### Couverture de Code
- **Tests Unitaires :** 85% des fonctions utilitaires
- **Tests d'Intégration :** 70% des interactions base de données
- **Tests Fonctionnels :** 60% des workflows critiques

### Performance
- **Tests Unitaires :** < 1 seconde
- **Tests d'Intégration :** < 3 secondes
- **Tests Fonctionnels :** < 5 secondes

### Fiabilité
- **Taux de réussite :** 95% minimum
- **Tests flaky :** 0%
- **Couverture des cas d'erreur :** 80%

## Exécution des Tests

### Installation
```bash
# Installer PHPUnit
composer install

# Créer la base de test
mysql -u root -p -e "CREATE DATABASE socialflow_test;"
```

### Exécution
```bash
# Tous les tests
php vendor/bin/phpunit

# Tests unitaires
php vendor/bin/phpunit tests/unit

# Tests d'intégration
php vendor/bin/phpunit tests/integration

# Tests fonctionnels
php vendor/bin/phpunit tests/functional

# Avec couverture
php vendor/bin/phpunit --coverage-html tests/coverage
```

### Scripts d'automatisation
- **Windows :** `run-tests.bat`
- **Linux/Mac :** `run-tests.sh`

## Intégration Continue

### GitHub Actions
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: php vendor/bin/phpunit
```

## Bonnes Pratiques Implémentées

### 1. Isolation des Tests
- Chaque test est indépendant
- Nettoyage automatique des données
- Utilisation de transactions

### 2. Données de Test
- Emails uniques avec timestamp
- Données réalistes mais fictives
- Nettoyage après chaque test

### 3. Assertions Détaillées
- Messages d'erreur explicites
- Vérifications multiples
- Tests des cas limites

### 4. Structure AAA
- **Arrange :** Préparation des données
- **Act :** Exécution de l'action
- **Assert :** Vérification du résultat

## Cas d'Usage Testés

### 1. Gestion des Utilisateurs
- ✅ Création d'utilisateur
- ✅ Connexion/Déconnexion
- ✅ Validation des rôles
- ✅ Gestion des permissions

### 2. Gestion des Publications
- ✅ Création de publication
- ✅ Programmation
- ✅ Publication immédiate
- ✅ Gestion des statuts

### 3. Gestion des Assignations
- ✅ Assignation client-CM
- ✅ Vérification des relations
- ✅ Gestion des permissions

### 4. Gestion des Abonnements
- ✅ Création d'abonnement
- ✅ Gestion des statuts
- ✅ Calcul des revenus

## Maintenance des Tests

### 1. Mise à Jour
- Tests mis à jour avec le code
- Nouvelles fonctionnalités testées
- Refactoring des tests obsolètes

### 2. Monitoring
- Surveillance des temps d'exécution
- Détection des tests flaky
- Analyse de la couverture

### 3. Documentation
- README mis à jour
- Exemples de tests
- Guide de contribution

## Conclusion

Cette suite de tests assure la qualité et la fiabilité de la plateforme SocialFlow en couvrant :

- **Fonctions individuelles** (tests unitaires)
- **Interactions système** (tests d'intégration)
- **Workflows complets** (tests fonctionnels)

L'implémentation suit les meilleures pratiques de développement et permet une maintenance facile et une évolution continue du système de tests.
