<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$message = '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Messages d'erreur prédéfinis
$error_messages = [
    'login_required' => 'Veuillez vous connecter pour accéder à cette page.',
    'invalid_credentials' => 'Nom d\'utilisateur ou mot de passe incorrect.',
    'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.'
];

if ($error && isset($error_messages[$error])) {
    $message = alert('danger', $error_messages[$error]);
}

// Traitement de la connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = secure_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = alert('warning', 'Veuillez remplir tous les champs.');
    } else {
        try {
            $stmt = execute_query(
                "SELECT id, username, nom, email, password, role FROM admins WHERE username = ?",
                [$username],
                's'
            );
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // ⚠️ Comparaison directe (mot de passe en clair)
                if ($password === $admin['password']) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_nom'] = $admin['nom'];
                    $_SESSION['admin_role'] = $admin['role'];
                    
                    // Mettre à jour la dernière connexion
                    execute_query(
                        "UPDATE admins SET derniere_connexion = NOW() WHERE id = ?",
                        [$admin['id']],
                        'i'
                    );
                    
                    log_action('LOGIN', 'ADMIN', $admin['id'], "Username: {$username}");
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $message = alert('danger', 'Nom d\'utilisateur ou mot de passe incorrect.');
                }
            } else {
                $message = alert('danger', 'Nom d\'utilisateur ou mot de passe incorrect.');
            }
        } catch (Exception $e) {
            error_log("Erreur connexion admin : " . $e->getMessage());
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
    <title>Administration - UCB Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock text-danger" style="font-size: 3rem;"></i>
                            <h2 class="mt-3 mb-1">Administration</h2>
                            <p class="text-muted">UCB Transport - Accès sécurisé</p>
                        </div>

                        <?php if ($message) echo $message; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="bi bi-person-gear me-2"></i>Nom d'utilisateur
                                </label>
                                <input type="text" 
                                       name="username" 
                                       id="username" 
                                       class="form-control form-control-lg" 
                                       required>
                                <div class="invalid-feedback">
                                    Veuillez entrer votre nom d'utilisateur.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-key me-2"></i>Mot de passe
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

                            <button type="submit" class="btn btn-danger btn-lg w-100 mb-3">
                                <i class="bi bi-shield-check me-2"></i>Accéder à l'administration
                            </button>
                        </form>

                        <div class="text-center">
                            <small class="text-muted">
                                Accès réservé aux administrateurs autorisés<br>
                                <a href="../index.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i>Retour à l'accueil étudiant
                                </a>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        © 2025 Université Catholique de Bukavu - Administration
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
