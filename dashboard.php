<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

check_student_login();

$etudiant_id = $_SESSION['etudiant_id'];
$etudiant_nom = $_SESSION['etudiant_nom'];
$etudiant_prenom = $_SESSION['etudiant_prenom'];

// Récupérer les trajets disponibles (futurs uniquement)
$trajets_query = "
    SELECT t.*, 
           (t.capacite - COALESCE(COUNT(r.id), 0)) as places_restantes,
           COALESCE(COUNT(r.id), 0) as reservations_count
    FROM trajets t 
    LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut IN ('reserve', 'valide')
    WHERE t.date_depart >= CURDATE() AND t.statut = 'actif'
    GROUP BY t.id 
    ORDER BY t.date_depart ASC, t.heure_depart ASC
";

$trajets_result = $conn->query($trajets_query);

// Statistiques de l'étudiant
$stats_query = "
    SELECT 
        COUNT(*) as total_reservations,
        COUNT(CASE WHEN statut = 'reserve' THEN 1 END) as reservations_actives,
        COUNT(CASE WHEN statut = 'valide' THEN 1 END) as reservations_validees,
        COUNT(CASE WHEN statut = 'utilise' THEN 1 END) as reservations_utilisees
    FROM reservations 
    WHERE etudiant_id = ?
";

$stmt = execute_query($stats_query, [$etudiant_id], 'i');
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - UCB Transport</title>
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house me-1"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reserver.php">
                            <i class="bi bi-plus-circle me-1"></i>Réserver
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historique.php">
                            <i class="bi bi-clock-history me-1"></i>Mes réservations
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo safe_output($etudiant_prenom . ' ' . $etudiant_nom); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person me-2"></i>Mon profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- En-tête de bienvenue -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="mb-3">
                            <i class="bi bi-emoji-smile text-primary me-2"></i>
                            Bienvenue, <?php echo safe_output($etudiant_prenom); ?> !
                        </h2>
                        <p class="text-muted mb-0">
                            Gérez vos réservations de transport universitaire en toute simplicité.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-perforated display-4 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['total_reservations']; ?></h3>
                        <p class="mb-0">Total réservations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-4 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['reservations_actives']; ?></h3>
                        <p class="mb-0">Réservations actives</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-check display-4 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['reservations_validees']; ?></h3>
                        <p class="mb-0">Validées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check2-all display-4 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['reservations_utilisees']; ?></h3>
                        <p class="mb-0">Utilisées</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trajets disponibles -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-calendar-event text-primary me-2"></i>
                                Trajets disponibles
                            </h4>
                            <a href="reserver.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Nouvelle réservation
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($trajets_result && $trajets_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-geo-alt me-1"></i>Trajet</th>
                                            <th><i class="bi bi-calendar me-1"></i>Date</th>
                                            <th><i class="bi bi-clock me-1"></i>Heure</th>
                                            <th><i class="bi bi-people me-1"></i>Places</th>
                                            <th><i class="bi bi-currency-dollar me-1"></i>Prix</th>
                                            <th><i class="bi bi-gear me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($trajet = $trajets_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($trajet['nom_trajet']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo safe_output($trajet['point_depart']); ?> 
                                                            <i class="bi bi-arrow-right mx-1"></i>
                                                            <?php echo safe_output($trajet['point_arrivee']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-bold">
                                                        <?php echo format_date_fr($trajet['date_depart']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo substr($trajet['heure_depart'], 0, 5); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($trajet['places_restantes'] > 0): ?>
                                                        <span class="badge bg-success">
                                                            <?php echo $trajet['places_restantes']; ?>/<?php echo $trajet['capacite']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Complet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($trajet['prix'], 0, ',', ' '); ?> FC</strong>
                                                </td>
                                                <td>
                                                    <?php if ($trajet['places_restantes'] > 0): ?>
                                                        <a href="reserver.php?trajet_id=<?php echo $trajet['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-plus-circle me-1"></i>Réserver
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="bi bi-x-circle me-1"></i>Complet
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                                <h5 class="mt-3 text-muted">Aucun trajet disponible</h5>
                                <p class="text-muted">Les nouveaux trajets seront affichés ici dès qu'ils seront programmés.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>