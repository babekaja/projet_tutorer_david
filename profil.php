<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

check_student_login();

$etudiant_id = $_SESSION['etudiant_id'];
$message = '';

// Récupérer les informations de l'étudiant
$stmt = execute_query("SELECT * FROM etudiants WHERE id = ?", [$etudiant_id], 'i');
$etudiant = $stmt->get_result()->fetch_assoc();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $nom = secure_input($_POST['nom']);
        $prenom = secure_input($_POST['prenom']);
        $email = secure_input($_POST['email']);
        $telephone = secure_input($_POST['telephone']);
        
        if (!validate_email($email)) {
            $message = alert('danger', 'Adresse email invalide.');
        } else {
            try {
                execute_query(
                    "UPDATE etudiants SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?",
                    [$nom, $prenom, $email, $telephone, $etudiant_id],
                    'ssssi'
                );
                
                $_SESSION['etudiant_nom'] = $nom;
                $_SESSION['etudiant_prenom'] = $prenom;
                $_SESSION['etudiant_email'] = $email;
                
                log_action('PROFILE_UPDATED', 'ETUDIANT', $etudiant_id);
                $message = alert('success', 'Profil mis à jour avec succès.');
                
                // Recharger les données
                $stmt = execute_query("SELECT * FROM etudiants WHERE id = ?", [$etudiant_id], 'i');
                $etudiant = $stmt->get_result()->fetch_assoc();
                
            } catch (Exception $e) {
                error_log("Erreur mise à jour profil étudiant : " . $e->getMessage());
                $message = alert('danger', 'Erreur lors de la mise à jour.');
            }
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = alert('warning', 'Veuillez remplir tous les champs.');
        } elseif ($new_password !== $confirm_password) {
            $message = alert('danger', 'Les nouveaux mots de passe ne correspondent pas.');
        } elseif (strlen($new_password) < 6) {
            $message = alert('warning', 'Le mot de passe doit contenir au moins 6 caractères.');
        } elseif (!password_verify($current_password, $etudiant['password'])) {
            $message = alert('danger', 'Mot de passe actuel incorrect.');
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                execute_query(
                    "UPDATE etudiants SET password = ? WHERE id = ?",
                    [$hashed_password, $etudiant_id],
                    'si'
                );
                
                log_action('PASSWORD_CHANGED', 'ETUDIANT', $etudiant_id);
                $message = alert('success', 'Mot de passe modifié avec succès.');
                
            } catch (Exception $e) {
                error_log("Erreur changement mot de passe étudiant : " . $e->getMessage());
                $message = alert('danger', 'Erreur lors du changement de mot de passe.');
            }
        }
    }
}

// Statistiques de l'étudiant
$stats_query = "
    SELECT 
        COUNT(*) as total_reservations,
        COUNT(CASE WHEN statut = 'reserve' THEN 1 END) as reservations_actives,
        COUNT(CASE WHEN statut = 'valide' THEN 1 END) as reservations_validees,
        COUNT(CASE WHEN statut = 'utilise' THEN 1 END) as reservations_utilisees,
        COUNT(CASE WHEN statut = 'annule' THEN 1 END) as reservations_annulees
    FROM reservations 
    WHERE etudiant_id = ?
";

$stmt = execute_query($stats_query, [$etudiant_id], 'i');
$stats = $stmt->get_result()->fetch_assoc();

// Dernières réservations
$recent_reservations = execute_query(
    "SELECT r.*, t.nom_trajet, t.date_depart, t.heure_depart 
     FROM reservations r 
     JOIN trajets t ON r.trajet_id = t.id 
     WHERE r.etudiant_id = ? 
     ORDER BY r.date_reservation DESC 
     LIMIT 3",
    [$etudiant_id],
    'i'
)->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-bus-front me-2"></i>UCB Transport
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <?php echo strtoupper(substr($etudiant['prenom'], 0, 1) . substr($etudiant['nom'], 0, 1)); ?>
                                </div>
                            </div>
                            <div>
                                <h2 class="mb-1"><?php echo safe_output($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h2>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-person-badge me-1"></i>
                                    Matricule: <?php echo safe_output($etudiant['matricule']); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-envelope me-1"></i>
                                    <?php echo safe_output($etudiant['email']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <div class="row">
            <!-- Informations du profil -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-person text-primary me-2"></i>
                            Informations personnelles
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="matricule" class="form-label">Matricule</label>
                                    <input type="text" id="matricule" class="form-control" 
                                           value="<?php echo safe_output($etudiant['matricule']); ?>" disabled>
                                    <small class="text-muted">Le matricule ne peut pas être modifié.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="statut" class="form-label">Statut du compte</label>
                                    <input type="text" id="statut" class="form-control" 
                                           value="<?php echo ucfirst($etudiant['statut']); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" name="prenom" id="prenom" class="form-control" 
                                           value="<?php echo safe_output($etudiant['prenom']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" name="nom" id="nom" class="form-control" 
                                           value="<?php echo safe_output($etudiant['nom']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           value="<?php echo safe_output($etudiant['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" name="telephone" id="telephone" class="form-control" 
                                           value="<?php echo safe_output($etudiant['telephone']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Date d'inscription</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('d/m/Y H:i', strtotime($etudiant['date_creation'])); ?>" disabled>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Mettre à jour le profil
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Changement de mot de passe -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-key text-warning me-2"></i>
                            Changer le mot de passe
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" name="current_password" id="current_password" 
                                       class="form-control" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" name="new_password" id="new_password" 
                                           class="form-control" required minlength="6">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="form-control" required minlength="6">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-shield-lock me-1"></i>Changer le mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistiques et activités -->
            <div class="col-lg-4">
                <!-- Statistiques -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up text-success me-2"></i>
                            Mes statistiques
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0">Total réservations</h6>
                                <small class="text-muted">Depuis l'inscription</small>
                            </div>
                            <span class="badge bg-primary fs-6"><?php echo $stats['total_reservations']; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0">Réservations actives</h6>
                                <small class="text-muted">En cours</small>
                            </div>
                            <span class="badge bg-success fs-6"><?php echo $stats['reservations_actives']; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0">Voyages effectués</h6>
                                <small class="text-muted">Terminés</small>
                            </div>
                            <span class="badge bg-info fs-6"><?php echo $stats['reservations_utilisees']; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Annulations</h6>
                                <small class="text-muted">Total</small>
                            </div>
                            <span class="badge bg-secondary fs-6"><?php echo $stats['reservations_annulees']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dernières réservations -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-info me-2"></i>
                            Dernières réservations
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_reservations && $recent_reservations->num_rows > 0): ?>
                            <?php while ($reservation = $recent_reservations->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?php echo safe_output($reservation['nom_trajet']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo format_date_fr($reservation['date_depart']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php echo get_status_badge($reservation['statut']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div class="text-center mt-3">
                                <a href="historique.php" class="btn btn-sm btn-outline-primary">
                                    Voir tout l'historique
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-ticket-perforated text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">Aucune réservation</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning text-warning me-2"></i>
                            Actions rapides
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="reserver.php" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i>Nouvelle réservation
                            </a>
                            <a href="historique.php" class="btn btn-outline-success">
                                <i class="bi bi-clock-history me-1"></i>Mes réservations
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-info">
                                <i class="bi bi-house me-1"></i>Tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des mots de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>