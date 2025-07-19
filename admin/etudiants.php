<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $etudiant_id = intval($_POST['etudiant_id']);
    
    try {
        if ($action === 'toggle_status') {
            $new_status = $_POST['new_status'];
            execute_query(
                "UPDATE etudiants SET statut = ? WHERE id = ?",
                [$new_status, $etudiant_id],
                'si'
            );
            $message = alert('success', 'Statut de l\'étudiant mis à jour.');
        }
    } catch (Exception $e) {
        error_log("Erreur étudiant : " . $e->getMessage());
        $message = alert('danger', 'Erreur lors de l\'opération.');
    }
}

// Filtres
$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$search = isset($_GET['search']) ? secure_input($_GET['search']) : '';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];
$types = '';

if ($filter_statut) {
    $where_conditions[] = "e.statut = ?";
    $params[] = $filter_statut;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.email LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$etudiants_query = "
    SELECT e.*, 
           COUNT(r.id) as total_reservations,
           COUNT(CASE WHEN r.statut = 'reserve' THEN 1 END) as reservations_actives
    FROM etudiants e
    LEFT JOIN reservations r ON e.id = r.etudiant_id
    {$where_clause}
    GROUP BY e.id
    ORDER BY e.date_creation DESC
";

if (!empty($params)) {
    $stmt = execute_query($etudiants_query, $params, $types);
    $etudiants = $stmt->get_result();
} else {
    $etudiants = $conn->query($etudiants_query);
}

// Statistiques
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM etudiants")->fetch_assoc()['count'];
$stats['actif'] = $conn->query("SELECT COUNT(*) as count FROM etudiants WHERE statut = 'actif'")->fetch_assoc()['count'];
$stats['inactif'] = $conn->query("SELECT COUNT(*) as count FROM etudiants WHERE statut = 'inactif'")->fetch_assoc()['count'];
$stats['avec_reservations'] = $conn->query("SELECT COUNT(DISTINCT etudiant_id) as count FROM reservations")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des étudiants - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
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
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="mb-1">
                            <i class="bi bi-people text-primary me-2"></i>
                            Gestion des étudiants
                        </h2>
                        <p class="text-muted mb-0">Consulter et gérer les comptes étudiants</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                        <p class="mb-0">Total étudiants</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['actif']; ?></h3>
                        <p class="mb-0">Comptes actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['inactif']; ?></h3>
                        <p class="mb-0">Comptes inactifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white border-0">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-perforated display-6 mb-2"></i>
                        <h3 class="mb-1"><?php echo $stats['avec_reservations']; ?></h3>
                        <p class="mb-0">Avec réservations</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Nom, prénom, matricule ou email..." value="<?php echo safe_output($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="statut" class="form-label">Statut</label>
                                <select name="statut" id="statut" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="actif" <?php echo $filter_statut === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo $filter_statut === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                </select>
                            </div>
                            <div class="col-md-5 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search me-1"></i>Rechercher
                                </button>
                                <a href="etudiants.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des étudiants -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-list text-primary me-2"></i>
                            Liste des étudiants
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($etudiants && $etudiants->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-person me-1"></i>Étudiant</th>
                                            <th><i class="bi bi-person-badge me-1"></i>Matricule</th>
                                            <th><i class="bi bi-envelope me-1"></i>Contact</th>
                                            <th><i class="bi bi-ticket-perforated me-1"></i>Réservations</th>
                                            <th><i class="bi bi-calendar me-1"></i>Inscription</th>
                                            <th><i class="bi bi-flag me-1"></i>Statut</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($e = $etudiants->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <?php echo strtoupper(substr($e['prenom'], 0, 1) . substr($e['nom'], 0, 1)); ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo safe_output($e['prenom'] . ' ' . $e['nom']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo safe_output($e['matricule']); ?></code>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="text-muted">
                                                            <i class="bi bi-envelope me-1"></i>
                                                            <?php echo safe_output($e['email']); ?>
                                                        </small>
                                                        <?php if ($e['telephone']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bi bi-phone me-1"></i>
                                                                <?php echo safe_output($e['telephone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge bg-primary">
                                                            <?php echo $e['total_reservations']; ?> total
                                                        </span>
                                                        <?php if ($e['reservations_actives'] > 0): ?>
                                                            <br>
                                                            <small class="text-success">
                                                                <?php echo $e['reservations_actives']; ?> actives
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($e['date_creation'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($e['statut'] === 'actif'): ?>
                                                        <span class="badge bg-success">Actif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="etudiant_id" value="<?php echo $e['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $e['statut'] === 'actif' ? 'inactif' : 'actif'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $e['statut'] === 'actif' ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                                                onclick="return confirm('Changer le statut de cet étudiant ?')">
                                                            <?php if ($e['statut'] === 'actif'): ?>
                                                                <i class="bi bi-x-circle me-1"></i>Désactiver
                                                            <?php else: ?>
                                                                <i class="bi bi-check-circle me-1"></i>Activer
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Aucun étudiant trouvé</h4>
                                <p class="text-muted">Aucun étudiant ne correspond aux critères de recherche.</p>
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