<?php
require_once 'includes/functions.php';

// Logger la déconnexion si l'utilisateur était connecté
if (isset($_SESSION['etudiant_id'])) {
    log_action('LOGOUT', 'ETUDIANT', $_SESSION['etudiant_id']);
} elseif (isset($_SESSION['admin_id'])) {
    log_action('LOGOUT', 'ADMIN', $_SESSION['admin_id']);
}

// Détruire la session
session_unset();
session_destroy();

// Rediriger vers la page d'accueil
header("Location: index.php");
exit();
?>