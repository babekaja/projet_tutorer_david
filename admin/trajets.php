<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$message = '';
$edit_mode = false;
$trajet = ['id' => '', 'nom_trajet' => '', 'point_depart' => '', 'point_arrivee' => '', 'date_depart' => '', 'heure_depart' => '', 'capacite' => '', 'prix' => '', 'description' => ''];

// Ajouter ou modifier un trajet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nom_trajet = secure_input($_POST['nom_trajet']);
    $point_depart = secure_input($_POST['point_depart']);
    $point_arrivee = secure_input($_POST['point_arrivee']);
    $date_depart = $_POST['date_depart'];
    $heure_depart = $_POST['heure_depart'];
    $capacite = intval($_POST['capacite']);
    $prix = floatval($_POST['prix']);
    $description = secure_input($_POST['description']);

    try {
        if ($id > 0) {
            // Modifier
            execute_query(
                "UPDATE trajets SET nom_trajet=?, point_depart=?, point_arrivee=?, date_depart=?, heure_depart=?, capacite=?, prix=?, description=? WHERE id=?",
                [$nom_trajet, $point_depart, $point_arrivee, $date_depart, $heure_depart, $capacite, $prix, $description, $id],
                'sssssidsi'
            );
            $message = alert('success', "Trajet modifié avec succès.");
        } else {
            // Ajouter
            execute_query(
                "INSERT INTO trajets (nom_trajet, point_depart, point_arrivee, date_depart, heure_depart, capacite, prix, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$nom_trajet, $point_depart, $point_arrivee, $date_depart, $heure_depart, $capacite, $prix, $description],
                'sssssids'
            );
            $message = alert('success', "Trajet ajouté avec succès.");
        }
    } catch (Exception $e) {
        error_log("Erreur trajet : " . $e->getMessage());
        $message = alert('danger', "Erreur lors de l'opération.");
    }
}

// Supprimer un trajet
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    try {
        execute_query("DELETE FROM trajets WHERE id = ?", [$del_id], 'i');
        $message = alert('success', "Trajet supprimé avec succès.");
    } catch (Exception $e) {
        $message = alert('danger', "Erreur lors de la suppression.");
    }
}

// Passer en mode édition
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = execute_query("SELECT * FROM trajets WHERE id = ?", [$edit_id], 'i');
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $trajet = $result->fetch_assoc();
        $edit_mode = true;
    }
}

// Liste des trajets avec statistiques
$trajets = $conn->query("
    SELECT t.*, 
           COUNT(r.id) as total_reservations,
           COUNT(CASE WHEN r.statut = 'reserve' THEN 1 END) as reservations_actives
    FROM trajets t 
    LEFT JOIN reservations r ON t.id = r.trajet_id 
    GROUP BY t.id 
    ORDER BY t.date_depart DESC, t.heure_depart DESC
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des trajets - UCB Transport</title>
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="bi bi-bus-front text-primary me-2"></i>
                                    Gestion des trajets
                                </h2>
                                <p class="text-muted mb-0">Créer, modifier et gérer les trajets de transport</p>
                            </div>
                            <div>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#trajetModal">
                                    <i class="bi bi-plus-circle me-1"></i>Nouveau trajet
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Liste des trajets -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-list text-primary me-2"></i>
                            Liste des trajets
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($trajets && $trajets->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-hash me-1"></i>ID</th>
                                            <th><i class="bi bi-geo-alt me-1"></i>Trajet</th>
                                            <th><i class="bi bi-calendar me-1"></i>Date & Heure</th>
                                            <th><i class="bi bi-people me-1"></i>Capacité</th>
                                            <th><i class="bi bi-currency-dollar me-1"></i>Prix</th>
                                            <th><i class="bi bi-graph-up me-1"></i>Réservations</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($t = $trajets->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark">#<?php echo $t['id']; ?></span></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo safe_output($t['nom_trajet']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo safe_output($t['point_depart']); ?>
                                                            <i class="bi bi-arrow-right mx-1"></i>
                                                            <?php echo safe_output($t['point_arrivee']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo format_date_fr($t['date_depart']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo substr($t['heure_depart'], 0, 5); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $t['capacite']; ?> places
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($t['prix'], 0, ',', ' '); ?> FC</strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge bg-success">
                                                            <?php echo $t['total_reservations']; ?> total
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $t['reservations_actives']; ?> actives
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-warning" 
                                                                onclick="editTrajet(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <a href="?delete=<?php echo $t['id']; ?>" 
                                                           class="btn btn-outline-danger"
                                                           onclick="return confirm('Supprimer ce trajet ?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bus-front text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Aucun trajet trouvé</h4>
                                <p class="text-muted">Commencez par créer votre premier trajet.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#trajetModal">
                                    <i class="bi bi-plus-circle me-2"></i>Créer un trajet
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un trajet -->
    <div class="modal fade" id="trajetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="trajetForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="bi bi-plus-circle me-2"></i>Nouveau trajet
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="trajet_id">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="nom_trajet" class="form-label">Nom du trajet</label>
                                <input type="text" name="nom_trajet" id="nom_trajet" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="point_depart" class="form-label">Point de départ</label>
                                <input type="text" name="point_depart" id="point_depart" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="point_arrivee" class="form-label">Point d'arrivée</label>
                                <input type="text" name="point_arrivee" id="point_arrivee" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_depart" class="form-label">Date de départ</label>
                                <input type="date" name="date_depart" id="date_depart" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="heure_depart" class="form-label">Heure de départ</label>
                                <input type="time" name="heure_depart" id="heure_depart" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="capacite" class="form-label">Capacité (places)</label>
                                <input type="number" name="capacite" id="capacite" class="form-control" required min="1" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prix" class="form-label">Prix (FC)</label>
                                <input type="number" name="prix" id="prix" class="form-control" required min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (optionnel)</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check-circle me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editTrajet(trajet) {
            document.getElementById('trajet_id').value = trajet.id;
            document.getElementById('nom_trajet').value = trajet.nom_trajet;
            document.getElementById('point_depart').value = trajet.point_depart;
            document.getElementById('point_arrivee').value = trajet.point_arrivee;
            document.getElementById('date_depart').value = trajet.date_depart;
            document.getElementById('heure_depart').value = trajet.heure_depart;
            document.getElementById('capacite').value = trajet.capacite;
            document.getElementById('prix').value = trajet.prix;
            document.getElementById('description').value = trajet.description || '';
            
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier le trajet';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Modifier';
            
            new bootstrap.Modal(document.getElementById('trajetModal')).show();
        }
        
        // Réinitialiser le formulaire quand on ferme le modal
        document.getElementById('trajetModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('trajetForm').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nouveau trajet';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Enregistrer';
        });
    </script>
</body>
</html>