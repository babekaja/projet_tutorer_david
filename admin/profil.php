<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
check_admin_login();

$admin_id = $_SESSION['admin_id'];
$message = '';

// Récupérer les informations de l'admin
$stmt = execute_query("SELECT * FROM admins WHERE id = ?", [$admin_id], 'i');
$admin = $stmt->get_result()->fetch_assoc();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $nom = secure_input($_POST['nom']);
        $email = secure_input($_POST['email']);
        
        try {
            execute_query(
                "UPDATE admins SET nom = ?, email = ? WHERE id = ?",
                [$nom, $email, $admin_id],
                'ssi'
            );
            
            $_SESSION['admin_nom'] = $nom;
            $message = alert('success', 'Profil mis à jour avec succès.');
            
            // Recharger les données
            $stmt = execute_query("SELECT * FROM admins WHERE id = ?", [$admin_id], 'i');
            $admin = $stmt->get_result()->fetch_assoc();
            
        } catch (Exception $e) {
            error_log("Erreur mise à jour profil admin : " . $e->getMessage());
            $message = alert('danger', 'Erreur lors de la mise à jour.');
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
        } elseif (!password_verify($current_password, $admin['password'])) {
            $message = alert('danger', 'Mot de passe actuel incorrect.');
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                execute_query(
                    "UPDATE admins SET password = ? WHERE id = ?",
                    [$hashed_password, $admin_id],
                    'si'
                );
                
                log_action('PASSWORD_CHANGED', 'ADMIN', $admin_id);
                $message = alert('success', 'Mot de passe modifié avec succès.');
                
            } catch (Exception $e) {
                error_log("Erreur changement mot de passe admin : " . $e->getMessage());
                $message = alert('danger', 'Erreur lors du changement de mot de passe.');
            }
        }
    }
}

// Statistiques de l'admin
$stats = [];
$stats['connexions'] = $conn->query("SELECT COUNT(*) as count FROM admins WHERE derniere_connexion IS NOT NULL")->fetch_assoc()['count'];
$stats['trajets_crees'] = $conn->query("SELECT COUNT(*) as count FROM trajets")->fetch_assoc()['count'];
$stats['total_reservations'] = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Admin UCB Transport</title>
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
                                    <?php echo strtoupper(substr($admin['nom'], 0, 2)); ?>
                                </div>
                            </div>
                            <div>
                                <h2 class="mb-1"><?php echo safe_output($admin['nom']); ?></h2>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Administrateur <?php echo ucfirst($admin['role']); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-envelope me-1"></i>
                                    <?php echo safe_output($admin['email']); ?>
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
                                    <label for="username" class="form-label">Nom d'utilisateur</label>
                                    <input type="text" id="username" class="form-control" 
                                           value="<?php echo safe_output($admin['username']); ?>" disabled>
                                    <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Rôle</label>
                                    <input type="text" id="role" class="form-control" 
                                           value="<?php echo ucfirst($admin['role']); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom complet</label>
                                    <input type="text" name="nom" id="nom" class="form-control" 
                                           value="<?php echo safe_output($admin['nom']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           value="<?php echo safe_output($admin['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date de création</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('d/m/Y H:i', strtotime($admin['date_creation'])); ?>" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dernière connexion</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $admin['derniere_connexion'] ? date('d/m/Y H:i', strtotime($admin['derniere_connexion'])) : 'Jamais'; ?>" disabled>
                                </div>
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

            <!-- Statistiques -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up text-success me-2"></i>
                            Statistiques
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0">Trajets créés</h6>
                                <small class="text-muted">Total dans le système</small>
                            </div>
                            <span class="badge bg-primary fs-6"><?php echo $stats['trajets_crees']; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0">Réservations</h6>
                                <small class="text-muted">Total gérées</small>
                            </div>
                            <span class="badge bg-success fs-6"><?php echo $stats['total_reservations']; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Statut</h6>
                                <small class="text-muted">Compte</small>
                            </div>
                            <span class="badge bg-info fs-6">Actif</span>
                        </div>
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
                            <a href="trajets.php" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i>Nouveau trajet
                            </a>
                            <a href="reservations.php" class="btn btn-outline-success">
                                <i class="bi bi-list-check me-1"></i>Voir réservations
                            </a>
                            <a href="validation.php" class="btn btn-outline-warning">
                                <i class="bi bi-qr-code-scan me-1"></i>Scanner QR
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