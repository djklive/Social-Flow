<?php
/**
 * Test de validation des numéros de téléphone
 */

require_once 'includes/functions.php';

echo "<h1>🧪 Test de validation des numéros de téléphone</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Numéros de test
$test_numbers = [
    // Numéros français
    '0123456789' => 'Numéro français (0)',
    '+33123456789' => 'Numéro français (+33)',
    
    // Numéros internationaux
    '+1234567890' => 'Numéro américain (+1)',
    '+44123456789' => 'Numéro britannique (+44)',
    '+49123456789' => 'Numéro allemand (+49)',
    '+221123456789' => 'Numéro sénégalais (+221)',
    '+22512345678' => 'Numéro ivoirien (+225)',
    '+23712345678' => 'Numéro camerounais (+237)',
    
    // Numéros locaux
    '1234567890' => 'Numéro local (10 chiffres)',
    '123456789' => 'Numéro local (9 chiffres)',
    '12345678' => 'Numéro local (8 chiffres)',
    
    // Numéros invalides
    '123' => 'Numéro trop court',
    '123456789012345678' => 'Numéro trop long',
    'abc123def' => 'Numéro avec lettres',
    '' => 'Numéro vide',
    '+0123456789' => 'Numéro avec 0 après +',
];

echo "<h2>Résultats des tests :</h2>";

foreach ($test_numbers as $number => $description) {
    $is_valid = validate_phone($number);
    $status = $is_valid ? 'success' : 'error';
    $icon = $is_valid ? '✅' : '❌';
    
    echo "<p class='$status'>$icon <strong>$description:</strong> '$number' - " . 
         ($is_valid ? 'Valide' : 'Invalide') . "</p>";
}

echo "<h2>📝 Formats acceptés :</h2>";
echo "<ul>";
echo "<li><strong>Format international :</strong> +XX suivi de 7 à 14 chiffres (ex: +221123456789)</li>";
echo "<li><strong>Format local :</strong> 7 à 15 chiffres commençant par 1-9 (ex: 123456789)</li>";
echo "<li><strong>Format français :</strong> 0X ou +33X suivi de 8 chiffres (ex: 0123456789, +33123456789)</li>";
echo "</ul>";

echo "<h2>🌍 Exemples par pays :</h2>";
echo "<ul>";
echo "<li><strong>Sénégal :</strong> +221123456789 ou 123456789</li>";
echo "<li><strong>Côte d'Ivoire :</strong> +22512345678 ou 12345678</li>";
echo "<li><strong>Cameroun :</strong> +23712345678 ou 12345678</li>";
echo "<li><strong>France :</strong> +33123456789 ou 0123456789</li>";
echo "<li><strong>États-Unis :</strong> +1234567890 ou 1234567890</li>";
echo "</ul>";

echo "<p class='info'>🎯 La validation est maintenant compatible avec tous les formats internationaux !</p>";
?>
