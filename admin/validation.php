<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$message = '';
$validation_result = null;
$scan_mode = isset($_GET['scan']) ? true : false;

// Traitement de la validation
if (isset($_POST['validate_code']) || isset($_GET['code'])) {
    $code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : (isset($_GET['code']) ? trim($_GET['code']) : '');
    
    if (!empty($code)) {
        try {
            // Analyser le code QR
            $parts = explode('|', $code);
            
            if (count($parts) >= 4 && $parts[0] === 'UCB' && $parts[1] === 'RESERVATION') {
                $reservation_id = intval($parts[2]);
                $etudiant_id = intval($parts[3]);
                $trajet_id = intval($parts[4]);
                
                // Récupérer les détails de la réservation
                $query = "
                    SELECT r.*, 
                           e.nom, e.prenom, e.matricule, e.email,
                           t.nom_trajet, t.point_depart, t.point_arrivee, 
                           t.date_depart, t.heure_depart, t.prix
                    FROM reservations r
                    JOIN etudiants e ON r.etudiant_id = e.id
                    JOIN trajets t ON r.trajet_id = t.id
                    WHERE r.id = ? AND r.etudiant_id = ? AND r.trajet_id = ?
                ";
                
                $stmt = execute_query($query, [$reservation_id, $etudiant_id, $trajet_id], 'iii');
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $reservation = $result->fetch_assoc();
                    
                    // Vérifier le statut et la validité
                    $now = new DateTime();
                    $depart_time = new DateTime($reservation['date_depart'] . ' ' . $reservation['heure_depart']);
                    $time_diff = $now->diff($depart_time);
                    
                    if ($reservation['statut'] === 'reserve') {
                        if ($depart_time > $now) {
                            // Réservation valide
                            $validation_result = [
                                'status' => 'valid',
                                'message' => 'Réservation valide - Accès autorisé',
                                'data' => $reservation,
                                'time_info' => [
                                    'hours_until_departure' => $time_diff->h + ($time_diff->days * 24),
                                    'minutes_until_departure' => $time_diff->i
                                ]
                            ];
                            
                            // Optionnel: Marquer comme validé
                            if (isset($_POST['mark_validated'])) {
                                execute_query(
                                    "UPDATE reservations SET statut = 'valide' WHERE id = ?",
                                    [$reservation_id],
                                    'i'
                                );
                                log_action('RESERVATION_VALIDATED', 'ADMIN', $_SESSION['admin_id'], "Reservation ID: {$reservation_id}");
                                $validation_result['message'] = 'Réservation validée avec succès';
                            }
                        } else {
                            $validation_result = [
                                'status' => 'expired',
                                'message' => 'Réservation expirée - Le trajet est déjà parti',
                                'data' => $reservation
                            ];
                        }
                    } elseif ($reservation['statut'] === 'valide') {
                        $validation_result = [
                            'status' => 'already_validated',
                            'message' => 'Réservation déjà validée',
                            'data' => $reservation
                        ];
                    } elseif ($reservation['statut'] === 'utilise') {
                        $validation_result = [
                            'status' => 'used',
                            'message' => 'Billet déjà utilisé',
                            'data' => $reservation
                        ];
                    } else {
                        $validation_result = [
                            'status' => 'cancelled',
                            'message' => 'Réservation annulée',
                            'data' => $reservation
                        ];
                    }
                } else {
                    $validation_result = [
                        'status' => 'not_found',
                        'message' => 'Réservation introuvable ou données incorrectes'
                    ];
                }
            } else {
                $validation_result = [
                    'status' => 'invalid_format',
                    'message' => 'Format de QR code invalide'
                ];
            }
        } catch (Exception $e) {
            error_log("Erreur validation QR : " . $e->getMessage());
            $validation_result = [
                'status' => 'error',
                'message' => 'Erreur lors de la validation'
            ];
        }
    }
}

// Statistiques de validation
$stats = [];
$stats['today_validations'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM reservations 
    WHERE statut = 'valide' AND DATE(date_reservation) = CURDATE()
")->fetch_assoc()['count'];

$stats['pending_reservations'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    WHERE r.statut = 'reserve' AND CONCAT(t.date_depart, ' ', t.heure_depart) > NOW()
")->fetch_assoc()['count'];

$stats['today_departures'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM trajets 
    WHERE date_depart = CURDATE()
")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation QR Code - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .scanner-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            text-align: center;
        }
        
        .validation-result {
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            animation: slideIn 0.5s ease-out;
        }
        
        .validation-result.valid {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }
        
        .validation-result.invalid {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }
        
        .validation-result.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .qr-scanner {
            border: 3px dashed #fff;
            border-radius: 15px;
            padding: 3rem;
            margin: 2rem 0;
            background: rgba(255,255,255,0.1);
        }
        
        .scan-animation {
            width: 100px;
            height: 100px;
            border: 3px solid #fff;
            border-radius: 15px;
            margin: 0 auto 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .scan-line {
            width: 100%;
            height: 2px;
            background: #00ff00;
            position: absolute;
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .manual-input {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .reservation-details {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="validation.php">
                            <i class="bi bi-qr-code-scan me-1"></i>Validation
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo safe_output($_SESSION['admin_nom']); ?>
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
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="bi bi-qr-code-scan text-primary me-2"></i>
                                    Validation des réservations
                                </h2>
                                <p class="text-muted mb-0">Scanner ou saisir manuellement les codes QR des billets</p>
                            </div>
                            <div>
                                <div class="btn-group">
                                    <a href="?scan=1" class="btn btn-primary <?php echo $scan_mode ? 'active' : ''; ?>">
                                        <i class="bi bi-camera me-1"></i>Mode Scanner
                                    </a>
                                    <a href="validation.php" class="btn btn-outline-primary <?php echo !$scan_mode ? 'active' : ''; ?>">
                                        <i class="bi bi-keyboard me-1"></i>Saisie manuelle
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card stats-card border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['today_validations']; ?></h3>
                        <p class="mb-0">Validations aujourd'hui</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stats-card border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['pending_reservations']; ?></h3>
                        <p class="mb-0">Réservations en attente</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stats-card border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-day display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['today_departures']; ?></h3>
                        <p class="mb-0">Départs aujourd'hui</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Scanner / Saisie -->
            <div class="col-lg-6 mb-4">
                <?php if ($scan_mode): ?>
                    <!-- Mode Scanner -->
                    <div class="scanner-container">
                        <h4 class="mb-3">
                            <i class="bi bi-camera me-2"></i>
                            Scanner QR Code
                        </h4>
                        
                        <div class="qr-scanner">
                            <div class="scan-animation">
                                <div class="scan-line"></div>
                            </div>
                            <h5 class="mb-3">Positionnez le QR code devant la caméra</h5>
                            <button class="btn btn-light btn-lg" onclick="startScanner()">
                                <i class="bi bi-camera me-2"></i>Démarrer le scanner
                            </button>
                        </div>
                        
                        <div id="scanner-result" style="display: none;">
                            <div class="alert alert-light">
                                <strong>Code détecté:</strong>
                                <div id="scanned-code" class="mt-2"></div>
                                <button class="btn btn-success mt-2" onclick="validateScannedCode()">
                                    <i class="bi bi-check-circle me-1"></i>Valider
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Assurez-vous que l'éclairage est suffisant et que le QR code est bien visible
                            </small>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Mode Saisie manuelle -->
                    <div class="manual-input">
                        <div class="card-body p-4">
                            <h4 class="mb-3">
                                <i class="bi bi-keyboard text-primary me-2"></i>
                                Saisie manuelle
                            </h4>
                            
                            <form method="POST" class="mb-3">
                                <div class="mb-3">
                                    <label for="qr_code" class="form-label">Code QR ou numéro de billet</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text">
                                            <i class="bi bi-qr-code"></i>
                                        </span>
                                        <input type="text" 
                                               name="qr_code" 
                                               id="qr_code" 
                                               class="form-control" 
                                               placeholder="UCB|RESERVATION|123|456|789 ou scannez le code"
                                               required 
                                               autofocus>
                                    </div>
                                    <div class="form-text">
                                        Format attendu: UCB|RESERVATION|ID|ETUDIANT|TRAJET
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="validate_code" class="btn btn-primary btn-lg">
                                        <i class="bi bi-search me-2"></i>Valider le code
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Vous pouvez aussi scanner directement avec votre appareil photo
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Résultat de validation -->
            <div class="col-lg-6 mb-4">
                <?php if ($validation_result): ?>
                    <div class="validation-result <?php 
                        echo $validation_result['status'] === 'valid' ? 'valid' : 
                            ($validation_result['status'] === 'already_validated' ? 'warning' : 'invalid'); 
                    ?>">
                        <div class="text-center mb-3">
                            <?php if ($validation_result['status'] === 'valid'): ?>
                                <i class="bi bi-check-circle display-4 mb-2"></i>
                                <h3>✅ ACCÈS AUTORISÉ</h3>
                            <?php elseif ($validation_result['status'] === 'already_validated'): ?>
                                <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                                <h3>⚠️ DÉJÀ VALIDÉ</h3>
                            <?php else: ?>
                                <i class="bi bi-x-circle display-4 mb-2"></i>
                                <h3>❌ ACCÈS REFUSÉ</h3>
                            <?php endif; ?>
                            <p class="mb-0"><?php echo safe_output($validation_result['message']); ?></p>
                        </div>
                        
                        <?php if (isset($validation_result['data'])): ?>
                            <div class="reservation-details mt-4">
                                <div class="card-body">
                                    <h5 class="text-dark mb-3">Détails de la réservation</h5>
                                    
                                    <div class="row text-dark">
                                        <div class="col-md-6">
                                            <p><strong>Étudiant:</strong><br>
                                            <?php echo safe_output($validation_result['data']['prenom'] . ' ' . $validation_result['data']['nom']); ?></p>
                                            
                                            <p><strong>Matricule:</strong><br>
                                            <?php echo safe_output($validation_result['data']['matricule']); ?></p>
                                            
                                            <p><strong>Trajet:</strong><br>
                                            <?php echo safe_output($validation_result['data']['nom_trajet']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Date de départ:</strong><br>
                                            <?php echo format_date_fr($validation_result['data']['date_depart']); ?></p>
                                            
                                            <p><strong>Heure:</strong><br>
                                            <?php echo substr($validation_result['data']['heure_depart'], 0, 5); ?></p>
                                            
                                            <p><strong>N° Billet:</strong><br>
                                            <code><?php echo safe_output($validation_result['data']['numero_billet']); ?></code></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($validation_result['status'] === 'valid' && isset($validation_result['time_info'])): ?>
                                        <div class="alert alert-info text-dark">
                                            <i class="bi bi-clock me-2"></i>
                                            Départ dans <?php echo $validation_result['time_info']['hours_until_departure']; ?>h 
                                            <?php echo $validation_result['time_info']['minutes_until_departure']; ?>min
                                        </div>
                                        
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="qr_code" value="<?php echo safe_output($_POST['qr_code'] ?? $_GET['code'] ?? ''); ?>">
                                            <button type="submit" name="mark_validated" class="btn btn-success">
                                                <i class="bi bi-check-circle me-2"></i>Marquer comme validé
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-qr-code-scan text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 text-muted">En attente de validation</h4>
                            <p class="text-muted">
                                <?php echo $scan_mode ? 'Scannez un QR code pour voir les détails de la réservation' : 'Saisissez un code QR pour valider la réservation'; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique des validations récentes -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-primary me-2"></i>
                            Validations récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $recent_validations = $conn->query("
                            SELECT r.*, e.nom, e.prenom, e.matricule, t.nom_trajet, t.date_depart, t.heure_depart
                            FROM reservations r
                            JOIN etudiants e ON r.etudiant_id = e.id
                            JOIN trajets t ON r.trajet_id = t.id
                            WHERE r.statut = 'valide' AND DATE(r.date_reservation) = CURDATE()
                            ORDER BY r.date_reservation DESC
                            LIMIT 10
                        ");
                        ?>
                        
                        <?php if ($recent_validations && $recent_validations->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Étudiant</th>
                                            <th>Trajet</th>
                                            <th>Départ</th>
                                            <th>Validé le</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($validation = $recent_validations->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo safe_output($validation['prenom'] . ' ' . $validation['nom']); ?></strong><br>
                                                    <small class="text-muted"><?php echo safe_output($validation['matricule']); ?></small>
                                                </td>
                                                <td><?php echo safe_output($validation['nom_trajet']); ?></td>
                                                <td>
                                                    <?php echo format_date_fr($validation['date_depart']); ?><br>
                                                    <small class="text-muted"><?php echo substr($validation['heure_depart'], 0, 5); ?></small>
                                                </td>
                                                <td><?php echo date('H:i:s', strtotime($validation['date_reservation'])); ?></td>
                                                <td><span class="badge bg-success">Validé</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">Aucune validation aujourd'hui</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let scannerActive = false;
        let scannedCode = '';

        // Simuler le démarrage du scanner (dans un vrai projet, utilisez une bibliothèque comme QuaggaJS ou ZXing)
        function startScanner() {
            if (scannerActive) return;
            
            scannerActive = true;
            const scanButton = document.querySelector('.qr-scanner button');
            scanButton.innerHTML = '<i class="bi bi-camera me-2"></i>Scanner en cours...';
            scanButton.disabled = true;
            
            // Simuler la détection d'un code après 3 secondes
            setTimeout(() => {
                // Dans un vrai scanner, ce serait le résultat de la caméra
                showScanResult('UCB|RESERVATION|123|456|789|UCB2024001');
            }, 3000);
        }

        function showScanResult(code) {
            scannedCode = code;
            document.getElementById('scanned-code').innerHTML = `<code>${code}</code>`;
            document.getElementById('scanner-result').style.display = 'block';
            
            const scanButton = document.querySelector('.qr-scanner button');
            scanButton.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Scanner à nouveau';
            scanButton.disabled = false;
            scanButton.onclick = resetScanner;
        }

        function validateScannedCode() {
            if (scannedCode) {
                window.location.href = `validation.php?code=${encodeURIComponent(scannedCode)}`;
            }
        }

        function resetScanner() {
            scannerActive = false;
            scannedCode = '';
            document.getElementById('scanner-result').style.display = 'none';
            
            const scanButton = document.querySelector('.qr-scanner button');
            scanButton.innerHTML = '<i class="bi bi-camera me-2"></i>Démarrer le scanner';
            scanButton.onclick = startScanner;
        }

        // Auto-focus sur le champ de saisie
        document.addEventListener('DOMContentLoaded', function() {
            const qrInput = document.getElementById('qr_code');
            if (qrInput) {
                qrInput.focus();
                
                // Effacer le champ après validation
                qrInput.addEventListener('focus', function() {
                    this.select();
                });
            }
        });

        // Raccourci clavier pour scanner
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (typeof startScanner === 'function') {
                    startScanner();
                }
            }
        });

        // Actualisation automatique des statistiques toutes les 30 secondes
        setInterval(function() {
            // Dans un vrai projet, vous pourriez faire un appel AJAX pour mettre à jour les stats
            console.log('Actualisation des statistiques...');
        }, 30000);
    </script>
</body>
</html>