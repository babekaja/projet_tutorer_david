<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$admin_name = $_SESSION['admin_nom'];
$admin_role = $_SESSION['admin_role'];

// Statistiques avancées
$stats = [];

// Total trajets
$stats['total_trajets'] = $conn->query("SELECT COUNT(*) AS total FROM trajets")->fetch_assoc()['total'];

// Total réservations
$stats['total_reservations'] = $conn->query("SELECT COUNT(*) AS total FROM reservations")->fetch_assoc()['total'];

// Total étudiants
$stats['total_etudiants'] = $conn->query("SELECT COUNT(*) AS total FROM etudiants")->fetch_assoc()['total'];

// Réservations aujourd'hui
$stats['reservations_today'] = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE DATE(date_reservation) = CURDATE()")->fetch_assoc()['total'];

// Trajets cette semaine
$stats['trajets_week'] = $conn->query("SELECT COUNT(*) AS total FROM trajets WHERE date_depart BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['total'];

// Revenus du mois
$stats['revenus_mois'] = $conn->query("
    SELECT COALESCE(SUM(t.prix), 0) AS total 
    FROM reservations r 
    JOIN trajets t ON r.trajet_id = t.id 
    WHERE MONTH(r.date_reservation) = MONTH(CURDATE()) 
    AND YEAR(r.date_reservation) = YEAR(CURDATE())
    AND r.statut IN ('reserve', 'valide', 'utilise')
")->fetch_assoc()['total'];

// Activités récentes
$recent_activities = $conn->query("
    SELECT 'reservation' as type, r.date_reservation as date_action, 
           CONCAT(e.prenom, ' ', e.nom) as user_name, t.nom_trajet as details
    FROM reservations r
    JOIN etudiants e ON r.etudiant_id = e.id
    JOIN trajets t ON r.trajet_id = t.id
    ORDER BY r.date_reservation DESC
    LIMIT 5
");

// Trajets populaires
$popular_trajets = $conn->query("
    SELECT t.nom_trajet, COUNT(r.id) as reservations_count
    FROM trajets t
    LEFT JOIN reservations r ON t.id = r.trajet_id
    GROUP BY t.id
    ORDER BY reservations_count DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .stats-card.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
        .stats-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stats-card.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-card.danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stats-card.secondary { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        
        .activity-item {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>Admin UCB Transport
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="trajets.php">
                            <i class="bi bi-bus-front me-1"></i>Trajets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reservations.php">
                            <i class="bi bi-ticket-perforated me-1"></i>Réservations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="etudiants.php">
                            <i class="bi bi-people me-1"></i>Étudiants
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo safe_output($admin_name); ?>
                            <span class="badge bg-primary ms-1"><?php echo ucfirst($admin_role); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
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

    <div class="container-fluid mt-4">
        <!-- En-tête de bienvenue -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="bi bi-emoji-smile text-primary me-2"></i>
                                    Bienvenue, <?php echo safe_output($admin_name); ?> !
                                </h2>
                                <p class="text-muted mb-0">
                                    Tableau de bord administrateur - <?php echo date('d/m/Y H:i'); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <div class="btn-group">
                                    <a href="trajets.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Nouveau trajet
                                    </a>
                                    <a href="validation.php" class="btn btn-outline-primary">
                                        <i class="bi bi-qr-code-scan me-1"></i>Scanner QR
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques principales -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card primary border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-bus-front display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['total_trajets']; ?></h3>
                        <p class="mb-0">Total Trajets</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card success border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-ticket-perforated display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['total_reservations']; ?></h3>
                        <p class="mb-0">Réservations</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card info border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-people display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['total_etudiants']; ?></h3>
                        <p class="mb-0">Étudiants</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card warning border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-calendar-day display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['reservations_today']; ?></h3>
                        <p class="mb-0">Aujourd'hui</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card danger border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-calendar-week display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['trajets_week']; ?></h3>
                        <p class="mb-0">Cette semaine</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card secondary border-0 h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-currency-dollar display-4 mb-3"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['revenus_mois'], 0, ',', ' '); ?></h3>
                        <p class="mb-0">Revenus (FC)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques et activités -->
        <div class="row">
            <!-- Activités récentes -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-primary me-2"></i>
                            Activités récentes
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                    <div class="list-group-item activity-item border-0 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="bi bi-ticket-perforated text-primary fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo safe_output($activity['user_name']); ?></h6>
                                                <p class="mb-1 text-muted">
                                                    Réservation: <?php echo safe_output($activity['details']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($activity['date_action'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">Aucune activité récente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Trajets populaires -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up text-success me-2"></i>
                            Trajets populaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($popular_trajets && $popular_trajets->num_rows > 0): ?>
                            <?php while ($trajet = $popular_trajets->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?php echo safe_output($trajet['nom_trajet']); ?></h6>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $trajet['reservations_count']; ?> réservations
                                        </span>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-gradient" 
                                         style="width: <?php echo min(100, ($trajet['reservations_count'] / max(1, $stats['total_reservations'])) * 100); ?>%">
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">Aucune donnée disponible</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning text-warning me-2"></i>
                            Actions rapides
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="trajets.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="bi bi-plus-circle d-block mb-2" style="font-size: 2rem;"></i>
                                    Ajouter un trajet
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reservations.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="bi bi-list-check d-block mb-2" style="font-size: 2rem;"></i>
                                    Voir réservations
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="etudiants.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="bi bi-people d-block mb-2" style="font-size: 2rem;"></i>
                                    Gérer étudiants
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="validation.php" class="btn btn-outline-warning w-100 py-3">
                                    <i class="bi bi-qr-code-scan d-block mb-2" style="font-size: 2rem;"></i>
                                    Scanner QR Code
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>