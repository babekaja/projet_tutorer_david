<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$message = '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Messages d'erreur prédéfinis
$error_messages = [
    'login_required' => 'Veuillez vous connecter pour accéder à cette page.',
    'invalid_credentials' => 'Matricule ou mot de passe incorrect.',
    'account_inactive' => 'Votre compte est désactivé. Contactez l\'administration.'
];

if ($error && isset($error_messages[$error])) {
    $message = alert('danger', $error_messages[$error]);
}

// Traitement de la connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = secure_input($_POST['matricule']);
    $password = $_POST['password'];
    
    if (empty($matricule) || empty($password)) {
        $message = alert('warning', 'Veuillez remplir tous les champs.');
    } else {
        try {
            $stmt = execute_query(
                "SELECT id, matricule, nom, prenom, email, password, statut FROM etudiants WHERE matricule = ?",
                [$matricule],
                's'
            );
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $etudiant = $result->fetch_assoc();
                
                if ($etudiant['statut'] !== 'actif') {
                    $message = alert('danger', 'Votre compte est désactivé. Contactez l\'administration.');
                } 
                // ⚠️ Comparaison simple (mot de passe en clair)
                elseif ($password === $etudiant['password']) {
                    $_SESSION['etudiant_id'] = $etudiant['id'];
                    $_SESSION['etudiant_matricule'] = $etudiant['matricule'];
                    $_SESSION['etudiant_nom'] = $etudiant['nom'];
                    $_SESSION['etudiant_prenom'] = $etudiant['prenom'];
                    $_SESSION['etudiant_email'] = $etudiant['email'];
                    
                    log_action('LOGIN', 'ETUDIANT', $etudiant['id'], "Matricule: {$matricule}");
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $message = alert('danger', 'Matricule ou mot de passe incorrect.');
                }
            } else {
                $message = alert('danger', 'Matricule ou mot de passe incorrect.');
            }
        } catch (Exception $e) {
            error_log("Erreur connexion étudiant : " . $e->getMessage());
            $message = alert('danger', 'Erreur de connexion. Veuillez réessayer.');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCB Transport - Connexion Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-bus-front text-primary" style="font-size: 3rem;"></i>
                            <h2 class="mt-3 mb-1">UCB Transport</h2>
                            <p class="text-muted">Système de réservation de billets</p>
                        </div>

                        <?php if ($message) echo $message; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="matricule" class="form-label">
                                    <i class="bi bi-person-badge me-2"></i>Matricule
                                </label>
                                <input type="text" 
                                       name="matricule" 
                                       id="matricule" 
                                       class="form-control form-control-lg" 
                                       placeholder="UCB2024001"
                                       pattern="UCB\d{7}"
                                       required>
                                <div class="invalid-feedback">
                                    Veuillez entrer un matricule valide (format: UCB2024001).
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock me-2"></i>Mot de passe
                                </label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       class="form-control form-control-lg" 
                                       required>
                                <div class="invalid-feedback">
                                    Veuillez entrer votre mot de passe.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                            </button>
                        </form>

                        <div class="text-center">
                            <small class="text-muted">
                                Problème de connexion ? Contactez l'administration<br>
                                <a href="admin/login.php" class="text-decoration-none">
                                    <i class="bi bi-gear me-1"></i>Accès administrateur
                                </a>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        © 2025 Université Catholique de Bukavu - Tous droits réservés
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>