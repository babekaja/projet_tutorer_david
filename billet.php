<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

check_student_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: historique.php");
    exit();
}

$reservation_id = intval($_GET['id']);
$etudiant_id = $_SESSION['etudiant_id'];

// Récupérer les détails de la réservation
$query = "
    SELECT r.*, t.nom_trajet, t.point_depart, t.point_arrivee, 
           t.date_depart, t.heure_depart, t.prix, t.description,
           e.nom, e.prenom, e.matricule, e.email
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN etudiants e ON r.etudiant_id = e.id
    WHERE r.id = ? AND r.etudiant_id = ?
";

$stmt = execute_query($query, [$reservation_id, $etudiant_id], 'ii');
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: historique.php");
    exit();
}

$billet = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billet de transport - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .card { box-shadow: none !important; border: 2px solid #000 !important; }
            body { background: white !important; }
        }
        .ticket-border {
            border: 3px dashed #0d6efd;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation (masquée à l'impression) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-bus-front me-2"></i>UCB Transport
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="historique.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour aux réservations
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Billet -->
                <div class="card border-0 shadow-lg ticket-border">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h2 class="mb-0">
                            <i class="bi bi-ticket-perforated me-2"></i>
                            BILLET DE TRANSPORT
                        </h2>
                        <p class="mb-0">Université Catholique de Bukavu</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Statut -->
                        <div class="text-center mb-4">
                            <?php echo get_status_badge($billet['statut']); ?>
                        </div>

                        <!-- Informations du trajet -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="bi bi-geo-alt me-2"></i>Informations du trajet
                                </h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Trajet :</strong></td>
                                        <td><?php echo safe_output($billet['nom_trajet']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Départ :</strong></td>
                                        <td><?php echo safe_output($billet['point_depart']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Arrivée :</strong></td>
                                        <td><?php echo safe_output($billet['point_arrivee']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date :</strong></td>
                                        <td><?php echo format_date_fr($billet['date_depart']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Heure :</strong></td>
                                        <td><?php echo substr($billet['heure_depart'], 0, 5); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Prix :</strong></td>
                                        <td><?php echo number_format($billet['prix'], 0, ',', ' '); ?> FC</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="bi bi-person me-2"></i>Informations du passager
                                </h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nom :</strong></td>
                                        <td><?php echo safe_output($billet['nom'] . ' ' . $billet['prenom']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Matricule :</strong></td>
                                        <td><?php echo safe_output($billet['matricule']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email :</strong></td>
                                        <td><?php echo safe_output($billet['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>N° Billet :</strong></td>
                                        <td><code><?php echo safe_output($billet['numero_billet']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Réservé le :</strong></td>
                                        <td><?php echo date('d/m/Y à H:i', strtotime($billet['date_reservation'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- QR Code -->
                        <div class="text-center mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="bi bi-qr-code me-2"></i>Code de validation
                            </h5>
                            <?php if ($billet['qr_code_path'] && file_exists($billet['qr_code_path'])): ?>
                                <div class="qr-container d-inline-block">
                                    <img src="<?php echo $billet['qr_code_path']; ?>" 
                                         alt="QR Code de validation" 
                                         class="img-fluid"
                                         style="max-width: 200px;">
                                </div>
                                <p class="text-muted mt-2">
                                    <small>Présentez ce QR code lors de l'embarquement</small>
                                </p>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    QR Code non disponible
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Instructions -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="bi bi-info-circle me-2"></i>Instructions importantes
                            </h6>
                            <ul class="mb-0">
                                <li>Présentez-vous au point de départ <strong>15 minutes avant l'heure</strong></li>
                                <li>Munissez-vous de votre <strong>carte d'étudiant</strong> et de ce billet</li>
                                <li>Le QR code sera scanné lors de l'embarquement</li>
                                <li>En cas de problème, contactez l'administration : <strong>+243 970 123 456</strong></li>
                            </ul>
                        </div>

                        <?php if ($billet['description']): ?>
                            <div class="alert alert-light">
                                <h6 class="alert-heading">Informations supplémentaires</h6>
                                <p class="mb-0"><?php echo safe_output($billet['description']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer bg-light text-center">
                        <small class="text-muted">
                            © 2025 Université Catholique de Bukavu - Service de Transport
                        </small>
                    </div>
                </div>

                <!-- Actions (masquées à l'impression) -->
                <div class="text-center mt-4 no-print">
                    <button onclick="window.print()" class="btn btn-primary btn-lg me-2">
                        <i class="bi bi-printer me-2"></i>Imprimer le billet
                    </button>
                    <a href="historique.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>