<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

check_student_login();

$etudiant_id = $_SESSION['etudiant_id'];
$etudiant_nom = $_SESSION['etudiant_nom'];
$etudiant_prenom = $_SESSION['etudiant_prenom'];

// Traitement de l'annulation
if (isset($_GET['annuler']) && is_numeric($_GET['annuler'])) {
    $reservation_id = intval($_GET['annuler']);
    
    try {
        // Vérifier que la réservation appartient à l'étudiant et peut être annulée
        $check_query = "
            SELECT r.*, t.date_depart, t.heure_depart 
            FROM reservations r 
            JOIN trajets t ON r.trajet_id = t.id 
            WHERE r.id = ? AND r.etudiant_id = ? AND r.statut = 'reserve'
            AND CONCAT(t.date_depart, ' ', t.heure_depart) > DATE_ADD(NOW(), INTERVAL 2 HOUR)
        ";
        
        $stmt = execute_query($check_query, [$reservation_id, $etudiant_id], 'ii');
        
        if ($stmt->get_result()->num_rows === 1) {
            // Annuler la réservation
            execute_query(
                "UPDATE reservations SET statut = 'annule' WHERE id = ?",
                [$reservation_id],
                'i'
            );
            
            log_action('RESERVATION_CANCELLED', 'ETUDIANT', $etudiant_id, "Reservation ID: {$reservation_id}");
            
            $message = alert('success', 'Réservation annulée avec succès.');
        } else {
            $message = alert('danger', 'Impossible d\'annuler cette réservation.');
        }
    } catch (Exception $e) {
        error_log("Erreur annulation : " . $e->getMessage());
        $message = alert('danger', 'Erreur lors de l\'annulation.');
    }
}

// Récupérer les réservations de l'étudiant
$reservations_query = "
    SELECT r.*, t.nom_trajet, t.point_depart, t.point_arrivee, 
           t.date_depart, t.heure_depart, t.prix
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    WHERE r.etudiant_id = ?
    ORDER BY t.date_depart DESC, t.heure_depart DESC, r.date_reservation DESC
";

$stmt = execute_query($reservations_query, [$etudiant_id], 'i');
$reservations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes réservations - UCB Transport</title>
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
                        <h2 class="mb-1">
                            <i class="bi bi-clock-history text-primary me-2"></i>
                            Mes réservations
                        </h2>
                        <p class="text-muted mb-0">
                            Étudiant : <strong><?php echo safe_output($etudiant_prenom . ' ' . $etudiant_nom); ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($message)) echo $message; ?>

        <!-- Liste des réservations -->
        <div class="row">
            <div class="col-12">
                <?php if ($reservations && $reservations->num_rows > 0): ?>
                    <?php while ($reservation = $reservations->fetch_assoc()): ?>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <i class="bi bi-ticket-perforated text-primary" style="font-size: 2rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1">
                                                    <?php echo safe_output($reservation['nom_trajet']); ?>
                                                    <?php echo get_status_badge($reservation['statut']); ?>
                                                </h5>
                                                <p class="text-muted mb-2">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?php echo safe_output($reservation['point_depart']); ?>
                                                    <i class="bi bi-arrow-right mx-2"></i>
                                                    <?php echo safe_output($reservation['point_arrivee']); ?>
                                                </p>
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar me-1"></i>
                                                            <?php echo format_date_fr($reservation['date_depart']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo substr($reservation['heure_depart'], 0, 5); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="row mt-1">
                                                    <div class="col-sm-6">
                                                        <small class="text-muted">
                                                            <i class="bi bi-ticket me-1"></i>
                                                            Billet : <?php echo safe_output($reservation['numero_billet']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <small class="text-muted">
                                                            <i class="bi bi-currency-dollar me-1"></i>
                                                            <?php echo number_format($reservation['prix'], 0, ',', ' '); ?> FC
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="mb-2">
                                            <?php if ($reservation['qr_code_path'] && file_exists($reservation['qr_code_path'])): ?>
                                                <img src="<?php echo $reservation['qr_code_path']; ?>" 
                                                     alt="QR Code" 
                                                     class="img-fluid" 
                                                     style="max-width: 100px;">
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group-vertical d-grid gap-1">
                                            <a href="billet.php?id=<?php echo $reservation['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>Voir le billet
                                            </a>
                                            
                                            <?php if ($reservation['statut'] === 'reserve'): ?>
                                                <?php
                                                $can_cancel = strtotime($reservation['date_depart'] . ' ' . $reservation['heure_depart']) > (time() + 2 * 3600);
                                                ?>
                                                <?php if ($can_cancel): ?>
                                                    <a href="?annuler=<?php echo $reservation['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')">
                                                        <i class="bi bi-x-circle me-1"></i>Annuler
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled>
                                                        <i class="bi bi-clock me-1"></i>Trop tard
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-2">
                                <small class="text-muted">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Réservé le <?php echo date('d/m/Y à H:i', strtotime($reservation['date_reservation'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Pagination si nécessaire -->
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination">
                                <!-- Pagination à implémenter si nécessaire -->
                            </ul>
                        </nav>
                    </div>
                    
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-ticket-perforated text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 text-muted">Aucune réservation</h4>
                            <p class="text-muted">Vous n'avez pas encore effectué de réservation.</p>
                            <a href="reserver.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Faire ma première réservation
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>