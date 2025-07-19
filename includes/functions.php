<?php
/**
 * Fonctions utilitaires pour UCB Transport
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'étudiant est connecté
 */
function check_student_login() {
    if (!isset($_SESSION['etudiant_id']) || !isset($_SESSION['etudiant_matricule'])) {
        header("Location: index.php?error=login_required");
        exit();
    }
}

/**
 * Vérifier si l'admin est connecté
 */
function check_admin_login() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        header("Location: login.php?error=login_required");
        exit();
    }
}

/**
 * Générer une alerte Bootstrap
 */
function alert($type, $message, $dismissible = true) {
    $dismiss = $dismissible ? 'alert-dismissible fade show' : '';
    $button = $dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
    return "<div class='alert alert-{$type} {$dismiss}' role='alert'>{$message}{$button}</div>";
}

/**
 * Générer un numéro de billet unique
 */
function generate_ticket_number($trajet_id, $etudiant_id) {
    return 'UCB' . date('Y') . str_pad($trajet_id, 3, '0', STR_PAD_LEFT) . str_pad($etudiant_id, 4, '0', STR_PAD_LEFT) . rand(100, 999);
}

/**
 * Vérifier les places disponibles pour un trajet
 */
function check_available_seats($trajet_id) {
    global $conn;
    
    $stmt = execute_query(
        "SELECT t.capacite, COUNT(r.id) as reservations 
         FROM trajets t 
         LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut IN ('reserve', 'valide')
         WHERE t.id = ? 
         GROUP BY t.id",
        [$trajet_id],
        'i'
    );
    
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? ($result['capacite'] - $result['reservations']) : 0;
}

/**
 * Vérifier si un étudiant a déjà réservé un trajet
 */
function has_existing_reservation($etudiant_id, $trajet_id) {
    global $conn;
    
    $stmt = execute_query(
        "SELECT id FROM reservations WHERE etudiant_id = ? AND trajet_id = ? AND statut IN ('reserve', 'valide')",
        [$etudiant_id, $trajet_id],
        'ii'
    );
    
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Formater la date en français
 */
function format_date_fr($date) {
    $mois = [
        '01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril',
        '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août',
        '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'
    ];
    
    $timestamp = strtotime($date);
    $jour = date('d', $timestamp);
    $mois_num = date('m', $timestamp);
    $annee = date('Y', $timestamp);
    
    return $jour . ' ' . $mois[$mois_num] . ' ' . $annee;
}

/**
 * Générer le badge de statut
 */
function get_status_badge($status) {
    $badges = [
        'reserve' => '<span class="badge bg-primary">Réservé</span>',
        'valide' => '<span class="badge bg-success">Validé</span>',
        'annule' => '<span class="badge bg-danger">Annulé</span>',
        'utilise' => '<span class="badge bg-secondary">Utilisé</span>',
        'actif' => '<span class="badge bg-success">Actif</span>',
        'complet' => '<span class="badge bg-warning">Complet</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Inconnu</span>';
}

/**
 * Sécuriser les données de sortie
 */
function safe_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Valider l'email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider le matricule UCB
 */
function validate_matricule($matricule) {
    return preg_match('/^UCB\d{7}$/', $matricule);
}

/**
 * Logger les actions importantes
 */
function log_action($action, $user_type, $user_id, $details = '') {
    $log_file = __DIR__ . '/../logs/actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$user_type} ID:{$user_id} - {$action} - {$details}" . PHP_EOL;
    
    // Créer le dossier logs s'il n'existe pas
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>