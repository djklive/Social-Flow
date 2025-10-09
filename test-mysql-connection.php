<?php
/**
 * Test de diagnostic de connexion MySQL
 */

echo "=== Diagnostic de Connexion MySQL ===\n";

// Test 1: Vérifier si le port 3306 est ouvert
echo "1. Test du port 3306...\n";
$connection = @fsockopen('localhost', 3306, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Port 3306 ouvert\n";
    fclose($connection);
} else {
    echo "❌ Port 3306 fermé: $errstr ($errno)\n";
}

// Test 2: Essayer différentes configurations
$configs = [
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3307],
    ['host' => '127.0.0.1', 'port' => 3307],
];

echo "\n2. Test des différentes configurations...\n";
foreach ($configs as $config) {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname=socialflow_db;charset=utf8mb4";
    echo "Test: {$config['host']}:{$config['port']}... ";
    
    try {
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "✅ Connexion réussie!\n";
        break;
    } catch (PDOException $e) {
        echo "❌ Échec: " . $e->getMessage() . "\n";
    }
}

// Test 3: Vérifier les processus MySQL
echo "\n3. Vérification des processus MySQL...\n";
$output = shell_exec('tasklist | findstr mysql');
if ($output) {
    echo "✅ Processus MySQL trouvés:\n$output\n";
} else {
    echo "❌ Aucun processus MySQL trouvé\n";
}

// Test 4: Vérifier les services
echo "\n4. Vérification des services...\n";
$services = ['mysql', 'mysqld', 'xampp'];
foreach ($services as $service) {
    $output = shell_exec("sc query $service 2>nul");
    if ($output && strpos($output, 'RUNNING') !== false) {
        echo "✅ Service $service: RUNNING\n";
    } elseif ($output) {
        echo "⚠️ Service $service: " . (strpos($output, 'STOPPED') !== false ? 'STOPPED' : 'UNKNOWN') . "\n";
    } else {
        echo "❌ Service $service: Non trouvé\n";
    }
}

echo "\n=== Fin du diagnostic ===\n";
?>
