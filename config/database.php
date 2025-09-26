<?php
/**
 * Configuration de la base de données SocialFlow
 * 
 * Ce fichier contient les paramètres de connexion à la base de données MySQL
 * et initialise la connexion PDO pour l'application.
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialflow_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

// Fonction utilitaire pour obtenir la connexion
function getDB() {
    return Database::getInstance()->getConnection();
}

// Test de connexion (optionnel - à supprimer en production)
try {
    $db = getDB();
    // echo "Connexion à la base de données réussie !";
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
