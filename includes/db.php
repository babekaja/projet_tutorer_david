<?php
/**
 * Configuration et connexion à la base de données
 * UCB Transport System
 */

// Lecture des variables d'environnement (avec valeurs par défaut)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: 3306;
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'ucb_transport';

// Connexion MySQL avec gestion d'erreurs
try {
    // Note : $db_port doit être un int
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);

    // Définir le charset pour éviter les problèmes d'encodage
    $conn->set_charset("utf8mb4");

    // Vérifier la connexion
    if ($conn->connect_error) {
        throw new Exception("Erreur de connexion : " . $conn->connect_error);
    }
    
} catch (Exception $e) {
    // Log l'erreur et afficher un message générique
    error_log("Erreur DB : " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// Fonction pour sécuriser les requêtes
function secure_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Fonction pour exécuter des requêtes préparées
function execute_query($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur de préparation : " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception("Erreur d'exécution : " . $stmt->error);
    }
    
    return $stmt;
}
?>
