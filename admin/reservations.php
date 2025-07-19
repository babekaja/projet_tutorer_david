<?php
// Include necessary files for database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if the user is an admin and logged in, otherwise redirect
check_admin_login();

// Initialize filter variables from GET request, providing default values
$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filter_trajet = isset($_GET['trajet']) ? intval($_GET['trajet']) : 0; // Ensure integer type
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_numero_billet = isset($_GET['numero_billet']) ? $_GET['numero_billet'] : ''; // New filter for QR code scan

// Prepare conditions for the SQL WHERE clause and parameters for prepared statement
$where_conditions = []; // Array to hold individual WHERE clauses
$params = [];          // Array to hold parameters for the prepared statement
$types = '';           // String to hold parameter types for bind_param

// Add status filter if provided
if ($filter_statut) {
    $where_conditions[] = "r.statut = ?"; // Add condition
    $params[] = $filter_statut;           // Add parameter value
    $types .= 's';                        // Add string type
}

// Add trajet filter if provided (and greater than 0)
if ($filter_trajet) {
    $where_conditions[] = "r.trajet_id = ?"; // Add condition
    $params[] = $filter_trajet;           // Add parameter value
    $types .= 'i';                        // Add integer type
}

// Add date filter if provided
if ($filter_date) {
    $where_conditions[] = "DATE(r.date_reservation) = ?"; // Add condition (compare only date part)
    $params[] = $filter_date;           // Add parameter value
    $types .= 's';                        // Add string type
}

// Add ticket number filter if provided (from QR scan)
if ($filter_numero_billet) {
    $where_conditions[] = "r.numero_billet = ?"; // Add condition
    $params[] = $filter_numero_billet;           // Add parameter value
    $types .= 's';                               // Add string type
}

// Construct the WHERE clause: if conditions exist, join them with ' AND '
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Main query to fetch reservations with associated student and trip details
$reservations_query = "
    SELECT r.*, 
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.matricule, e.email,
           t.nom_trajet, t.point_depart, t.point_arrivee, t.date_depart, t.heure_depart, t.prix
    FROM reservations r
    JOIN etudiants e ON r.etudiant_id = e.id
    JOIN trajets t ON r.trajet_id = t.id
    {$where_clause}
    ORDER BY t.date_depart DESC, t.heure_depart DESC, r.date_reservation DESC
";

// Execute the query, using prepared statements if there are filters
global $conn; // Ensure $conn is accessible if execute_query doesn't pass it
if (!empty($params)) {
    // Call a custom function to execute the prepared query (assumed to be in functions.php)
    $stmt = execute_query($reservations_query, $params, $types);
    $reservations = $stmt->get_result(); // Get the result set from the statement
} else {
    // If no filters, execute a simple query
    $reservations = $conn->query($reservations_query);
}

// Fetch list of trips for the filter dropdown
$trajets_filter = $conn->query("SELECT id, nom_trajet FROM trajets ORDER BY date_depart DESC");

// Fetch statistics for reservation statuses
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$stats['reserve'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'reserve'")->fetch_assoc()['count'];
$stats['valide'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'valide'")->fetch_assoc()['count'];
$stats['annule'] = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE statut = 'annule'")->fetch_assoc()['count'];
// Note: 'utilise' status is in the filter but not in stats, ensure consistency if needed.
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations - UCB Transport</title>
    <!-- Bootstrap CSS for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons for icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Style for the QR reader container */
        #qr-reader {
            width: 100%;
            max-width: 500px; /* Limit width for better scanning experience */
            margin: auto;
        }
        /* Hide the dashboard section of html5-qrcode, as we only need the scanner */
        #qr-reader__dashboard_section_csr {
            display: none !important; 
        }
        /* Highlight scan region for better user guidance */
        #qr-reader__scan_region {
            border: 2px solid #007bff; 
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>Admin UCB Transport
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour au dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Page Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="bi bi-ticket-perforated text-primary me-2"></i>
                                    Gestion des réservations
                                </h2>
                                <p class="text-muted mb-0">Consulter et gérer toutes les réservations</p>
                            </div>
                            <div>
                                <!-- Modified button to open the QR scanner modal -->
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                                    <i class="bi bi-qr-code-scan me-1"></i>Scanner QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards Section -->
        <div class="row mb-4">
            <!-- Total Reservations Card -->
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-perforated display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                        <p class="mb-0">Total</p>
                    </div>
                </div>
            </div>
            <!-- Reserved Status Card -->
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['reserve']; ?></h3>
                        <p class="mb-0">Réservées</p>
                    </div>
                </div>
            </div>
            <!-- Validated Status Card -->
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-check display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['valide']; ?></h3>
                        <p class="mb-0">Validées</p>
                    </div>
                </div>
            </div>
            <!-- Cancelled Status Card -->
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['annule']; ?></h3>
                        <p class="mb-0">Annulées</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3" id="filterForm">
                            <!-- Status Filter -->
                            <div class="col-md-3">
                                <label for="statut" class="form-label">Statut</label>
                                <select name="statut" id="statut" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="reserve" <?php echo $filter_statut === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                                    <option value="valide" <?php echo $filter_statut === 'valide' ? 'selected' : ''; ?>>Validé</option>
                                    <option value="annule" <?php echo $filter_statut === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                    <option value="utilise" <?php echo $filter_statut === 'utilise' ? 'selected' : ''; ?>>Utilisé</option>
                                </select>
                            </div>
                            <!-- Trajet Filter -->
                            <div class="col-md-3">
                                <label for="trajet" class="form-label">Trajet</label>
                                <select name="trajet" id="trajet" class="form-select">
                                    <option value="">Tous les trajets</option>
                                    <?php 
                                    // Loop through available trips for the dropdown
                                    while ($t = $trajets_filter->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo $filter_trajet === $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo safe_output($t['nom_trajet']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Reservation Date Filter -->
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date de réservation</label>
                                <input type="date" name="date" id="date" class="form-control" value="<?php echo $filter_date; ?>">
                            </div>
                            <!-- Hidden input for QR scanned ticket number -->
                            <input type="hidden" name="numero_billet" id="numero_billet_filter" value="<?php echo safe_output($filter_numero_billet); ?>">
                            
                            <!-- Filter and Reset Buttons -->
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel me-1"></i>Filtrer
                                </button>
                                <a href="reservations.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservations List Section -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-list text-primary me-2"></i>
                            Liste des réservations
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php 
                        // Check if there are any reservations to display
                        if ($reservations && $reservations->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-person me-1"></i>Étudiant</th>
                                            <th><i class="bi bi-geo-alt me-1"></i>Trajet</th>
                                            <th><i class="bi bi-calendar me-1"></i>Voyage</th>
                                            <th><i class="bi bi-ticket me-1"></i>Billet</th>
                                            <th><i class="bi bi-clock me-1"></i>Réservé le</th>
                                            <th><i class="bi bi-flag me-1"></i>Statut</th>
                                            <th><i class="bi bi-currency-dollar me-1"></i>Prix</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Loop through each reservation and display its details
                                        while ($r = $reservations->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($r['etu_prenom'] . ' ' . $r['etu_nom']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-person-badge me-1"></i>
                                                            <?php echo safe_output($r['matricule']); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-envelope me-1"></i>
                                                            <?php echo safe_output($r['email']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($r['nom_trajet']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo safe_output($r['point_depart']); ?>
                                                            <i class="bi bi-arrow-right mx-1"></i>
                                                            <?php echo safe_output($r['point_arrivee']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo format_date_fr($r['date_depart']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo substr($r['heure_depart'], 0, 5); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo safe_output($r['numero_billet']); ?></code>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($r['date_reservation'])); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Display status badge using a custom function (assumed to be in functions.php)
                                                    echo get_status_badge($r['statut']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($r['prix'], 0, ',', ' '); ?> FC</strong>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <!-- Message displayed if no reservations are found -->
                            <div class="text-center py-5">
                                <i class="bi bi-ticket-perforated text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Aucune réservation trouvée</h4>
                                <p class="text-muted">Aucune réservation ne correspond aux critères sélectionnés.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrScannerModalLabel">Scanner le code QR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qr-reader"></div>
                    <div id="qr-reader-results" class="mt-3 text-success fw-bold"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        // Initialize Html5QrcodeScanner
        let html5QrcodeScanner = null;

        function onScanSuccess(decodedText, decodedResult) {
            // Handle success, e.g., display the text and submit the form
            console.log(`Code scanné : ${decodedText}`, decodedResult);
            document.getElementById('qr-reader-results').innerText = `Code scanné : ${decodedText}`;
            
            // Set the scanned ticket number to the hidden filter input
            document.getElementById('numero_billet_filter').value = decodedText;
            
            // Stop the scanner and close the modal
            html5QrcodeScanner.stop().then(() => {
                const qrScannerModal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
                if (qrScannerModal) {
                    qrScannerModal.hide();
                }
                // Submit the form to filter reservations based on the scanned ticket number
                document.getElementById('filterForm').submit();
            }).catch((err) => {
                console.error("Erreur lors de l'arrêt du scanner :", err);
            });
        }

        function onScanError(errorMessage) {
            // Handle scan error, usually just log it
            // console.warn(`Erreur de scan : ${errorMessage}`);
        }

        // Get the QR scanner modal element
        const qrScannerModalElement = document.getElementById('qrScannerModal');

        // Event listener for when the modal is shown
        qrScannerModalElement.addEventListener('shown.bs.modal', () => {
            // Create and start the scanner only when the modal is fully shown
            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader",
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    /* verbose= */ false
                );
            }
            html5QrcodeScanner.render(onScanSuccess, onScanError);
        });

        // Event listener for when the modal is hidden
        qrScannerModalElement.addEventListener('hidden.bs.modal', () => {
            // Stop the scanner when the modal is closed to release camera resources
            if (html5QrcodeScanner && html5QrcodeScanner.isScanning) { // Check if scanner is active
                html5QrcodeScanner.stop().then(() => {
                    console.log("Scanner arrêté.");
                }).catch((err) => {
                    console.error("Erreur lors de l'arrêt du scanner :", err);
                });
            }
            // Clear previous results
            document.getElementById('qr-reader-results').innerText = '';
        });
    </script>
</body>
</html>
