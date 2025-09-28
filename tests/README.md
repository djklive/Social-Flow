# Tests SocialFlow

Ce dossier contient tous les tests pour la plateforme SocialFlow.

## Structure des Tests

```
tests/
├── unit/                    # Tests unitaires
│   ├── EmailValidationTest.php
│   └── PasswordTest.php
├── integration/             # Tests d'intégration
│   ├── UserCreationTest.php
│   └── PostCreationTest.php
├── functional/              # Tests fonctionnels
│   ├── LoginProcessTest.php
│   └── PublicationWorkflowTest.php
├── results/                 # Résultats des tests
├── coverage/                # Rapports de couverture
├── bootstrap.php            # Configuration des tests
└── README.md               # Ce fichier
```

## Types de Tests

### 1. Tests Unitaires
Testent les fonctions individuelles de manière isolée.

**Exemples :**
- Validation des emails
- Hachage des mots de passe
- Fonctions utilitaires

**Exécution :**
```bash
php vendor/bin/phpunit tests/unit
```

### 2. Tests d'Intégration
Testent l'interaction entre plusieurs composants (base de données, fonctions).

**Exemples :**
- Création d'utilisateur avec base de données
- Création de publication avec assignation
- Gestion des relations entre tables

**Exécution :**
```bash
php vendor/bin/phpunit tests/integration
```

### 3. Tests Fonctionnels
Testent des workflows complets et des scénarios utilisateur.

**Exemples :**
- Processus complet de connexion
- Workflow de création de publication
- Gestion des notifications

**Exécution :**
```bash
php vendor/bin/phpunit tests/functional
```

## Installation et Configuration

### 1. Installer PHPUnit
```bash
composer install
```

### 2. Configuration de la base de données de test
Créer une base de données de test :
```sql
CREATE DATABASE socialflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Variables d'environnement
Les variables d'environnement sont configurées dans `phpunit.xml` :
- `DB_HOST`: localhost
- `DB_NAME`: socialflow_test
- `DB_USER`: root
- `DB_PASS`: (vide)

## Exécution des Tests

### Tous les tests
```bash
# Windows
run-tests.bat

# Linux/Mac
./run-tests.sh

# Ou directement
php vendor/bin/phpunit
```

### Tests spécifiques
```bash
# Tests unitaires uniquement
php vendor/bin/phpunit tests/unit

# Tests d'intégration uniquement
php vendor/bin/phpunit tests/integration

# Tests fonctionnels uniquement
php vendor/bin/phpunit tests/functional

# Un test spécifique
php vendor/bin/phpunit tests/unit/EmailValidationTest.php
```

### Avec couverture de code
```bash
php vendor/bin/phpunit --coverage-html tests/coverage
```

## Rapports de Tests

### 1. Rapport HTML de couverture
- **Fichier :** `tests/coverage/index.html`
- **Description :** Montre quelles parties du code sont testées

### 2. Rapport JUnit
- **Fichier :** `tests/results/junit.xml`
- **Description :** Format XML pour l'intégration CI/CD

### 3. Rapport TestDox
- **Fichier :** `tests/results/testdox.html`
- **Description :** Rapport lisible des tests

## Exemples de Tests

### Test Unitaire - Validation Email
```php
public function testValidEmail() {
    $validEmails = [
        'test@example.com',
        'user.name@domain.co.uk'
    ];
    
    foreach ($validEmails as $email) {
        $this->assertTrue(validate_email($email));
    }
}
```

### Test d'Intégration - Création Utilisateur
```php
public function testCreateUserIntegration() {
    $userData = [
        'email' => 'test@integration.com',
        'password' => 'password123'
    ];
    
    $hashedPassword = hash_password($userData['password']);
    $stmt = $this->db->prepare("INSERT INTO users ...");
    $result = $stmt->execute([...]);
    
    $this->assertTrue($result);
}
```

### Test Fonctionnel - Workflow Complet
```php
public function testCompleteLoginProcess() {
    // 1. Vérifier l'utilisateur
    // 2. Valider les identifiants
    // 3. Créer la session
    // 4. Mettre à jour last_login
    // 5. Logger l'activité
}
```

## Bonnes Pratiques

### 1. Nommage des Tests
- `testValidEmail()` - Test d'un cas valide
- `testInvalidEmail()` - Test d'un cas invalide
- `testEmailValidationEdgeCases()` - Test des cas limites

### 2. Structure des Tests
```php
public function testSomething() {
    // Arrange - Préparer les données
    $input = 'test@example.com';
    
    // Act - Exécuter l'action
    $result = validate_email($input);
    
    // Assert - Vérifier le résultat
    $this->assertTrue($result);
}
```

### 3. Nettoyage des Tests
- Utiliser `setUp()` et `tearDown()` pour la configuration
- Nettoyer les données de test après chaque test
- Utiliser des emails/identifiants uniques pour éviter les conflits

### 4. Messages d'Assertion
```php
$this->assertTrue(
    validate_email($email), 
    "Email valide rejeté: $email"
);
```

## Dépannage

### Erreur de connexion à la base de données
1. Vérifier que MySQL est démarré
2. Vérifier les paramètres de connexion dans `phpunit.xml`
3. Créer la base de données de test

### Tests qui échouent
1. Vérifier les logs d'erreur
2. S'assurer que les données de test sont nettoyées
3. Vérifier les dépendances entre tests

### Problèmes de performance
1. Utiliser des transactions pour les tests de base de données
2. Limiter le nombre de tests d'intégration
3. Utiliser des mocks pour les services externes

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

### Jenkins
```groovy
pipeline {
    agent any
    stages {
        stage('Test') {
            steps {
                sh 'composer install'
                sh 'php vendor/bin/phpunit'
            }
        }
    }
}
```

## Métriques de Qualité

### Objectifs de Couverture
- **Tests Unitaires :** 80% minimum
- **Tests d'Intégration :** 60% minimum
- **Tests Fonctionnels :** 40% minimum

### Temps d'Exécution
- **Tests Unitaires :** < 1 seconde
- **Tests d'Intégration :** < 5 secondes
- **Tests Fonctionnels :** < 10 secondes

## Support

Pour toute question sur les tests :
1. Consulter la documentation PHPUnit
2. Vérifier les exemples dans le dossier `tests/`
3. Contacter l'équipe de développement
