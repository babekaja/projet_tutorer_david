<?php
// Ensure these paths are correct relative to the file's location
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/phpqrcode/qrlib.php'; // Make sure phpqrcode library is correctly installed and accessible

// Check if the student is logged in, otherwise redirect
check_student_login();

// Get the student ID from the session
$etudiant_id = $_SESSION['etudiant_id'];
$message = ''; // Initialize message variable for user feedback
$trajet_preselected = isset($_GET['trajet_id']) ? intval($_GET['trajet_id']) : 0; // Pre-select a trip if ID is provided in URL

// --- Handle Trip Reservation (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trajet_id = intval($_POST['trajet_id']); // Get the selected trip ID from the form

    try {
        // Check for existing reservation for the same trip by the same student
        if (has_existing_reservation($etudiant_id, $trajet_id)) {
            $message = alert('warning', 'Vous avez déjà réservé ce trajet.');
        } 
        // Check if there are available seats for the selected trip
        elseif (check_available_seats($trajet_id) <= 0) {
            $message = alert('danger', 'Désolé, ce trajet est complet.');
        } 
        else {
            // Generate a unique ticket number
            $numero_billet = generate_ticket_number($trajet_id, $etudiant_id);
            
            // Insert the reservation into the database
            // 'iis' specifies the types of parameters: integer, integer, string
            $stmt = execute_query(
                "INSERT INTO reservations (etudiant_id, trajet_id, numero_billet, date_reservation, statut) 
                 VALUES (?, ?, ?, NOW(), 'reserve')",
                [$etudiant_id, $trajet_id, $numero_billet],
                'iis'
            );
            
            // Get the ID of the newly inserted reservation
            $reservation_id = $conn->insert_id;
            
            // --- QR Code Generation ---
            $qr_dir = __DIR__ . '/qr/'; // Directory to save QR codes
            // Create the QR directory if it doesn't exist
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true); // 0755 permissions for directory
            }
            
            // Data to be encoded in the QR code
            $qr_text = "UCB|RESERVATION|{$reservation_id}|{$etudiant_id}|{$trajet_id}|{$numero_billet}";
            $qr_file = $qr_dir . 'res_' . $reservation_id . '.png'; // Full path for the QR code image
            
            // Generate the QR code using phpqrcode library
            QRcode::png($qr_text, $qr_file, QR_ECLEVEL_L, 6); // L = low error correction, 6 = pixel size
            
            // Update the reservation record with the QR code file path
            execute_query(
                "UPDATE reservations SET qr_code_path = ? WHERE id = ?",
                ['qr/res_' . $reservation_id . '.png', $reservation_id],
                'si' // string, integer
            );
            
            // Log the successful reservation action
            log_action('RESERVATION_CREATED', 'ETUDIANT', $etudiant_id, "Trajet ID: {$trajet_id}, Billet: {$numero_billet}");
            
            // Redirect to the ticket confirmation page
            header("Location: billet.php?id={$reservation_id}");
            exit(); // Terminate script execution after redirection
        }
    } catch (Exception $e) {
        // Log any errors during the reservation process
        error_log("Erreur réservation : " . $e->getMessage());
        $message = alert('danger', 'Erreur lors de la réservation. Veuillez réessayer.');
    }
}

// --- Retrieve Available Trips from Database ---
try {
    // SQL query to fetch available trips
    // It calculates 'places_restantes' (remaining seats) and 'reservations_count'
    // Filters for future trips ('date_depart >= CURDATE()') and active trips ('statut = 'actif'')
    // Groups by trip details to correctly count reservations per trip
    // Filters out trips with no remaining seats ('HAVING places_restantes > 0')
    // Orders by departure date and time
    $trajets_query = "
        SELECT t.*, 
               (t.capacite - COALESCE(COUNT(r.id), 0)) as places_restantes,
               COALESCE(COUNT(r.id), 0) as reservations_count
        FROM trajets t 
        LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut IN ('reserve', 'valide')
        WHERE t.date_depart >= CURDATE() AND t.statut = 'actif'
        GROUP BY t.id, t.nom_trajet, t.point_depart, t.point_arrivee, t.date_depart, t.heure_depart, t.capacite, t.prix, t.description, t.statut, t.date_creation
        HAVING places_restantes > 0
        ORDER BY t.date_depart ASC, t.heure_depart ASC
    ";
    
    $trajets_result = $conn->query($trajets_query); // Execute the query
    
    // Check if the query execution was successful
    if (!$trajets_result) {
        throw new Exception("Erreur lors de la récupération des trajets : " . $conn->error);
    }
} catch (Exception $e) {
    // Log any errors during trip retrieval
    error_log("Erreur requête trajets : " . $e->getMessage());
    $trajets_result = false; // Set result to false to indicate an error
    $message = alert('warning', 'Erreur lors du chargement des trajets disponibles.');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un trajet - UCB Transport</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .trajet-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .trajet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #3b82f6; /* Tailwind blue-500 */
        }
        .trajet-card.selected {
            border-color: #3b82f6; /* Tailwind blue-500 */
            background-color: #eff6ff; /* Tailwind blue-50 */
        }
        .price-badge {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .loading-spinner {
            display: none;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb; /* Tailwind gray-200 */
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
            color: #4b5563; /* Tailwind gray-600 */
        }
        .step.active {
            background: #3b82f6; /* Tailwind blue-500 */
            color: white;
        }
        .step.completed {
            background: #10b981; /* Tailwind emerald-500 */
            color: white;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e5e7eb; /* Tailwind gray-200 */
            margin-top: 19px;
            transition: all 0.3s ease;
        }
        .step-line.completed {
            background: #10b981; /* Tailwind emerald-500 */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="bg-blue-600 shadow-md py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a class="text-white text-2xl font-bold flex items-center" href="dashboard.php">
                <i class="fas fa-bus-alt mr-2"></i>UCB Transport
            </a>
            
            <!-- Mobile menu button -->
            <button class="lg:hidden text-white focus:outline-none" id="mobile-menu-button">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <!-- Desktop Navigation -->
            <div class="hidden lg:flex items-center space-x-6" id="navbar-desktop">
                <ul class="flex space-x-6">
                    <li>
                        <a class="text-white hover:text-blue-200 transition duration-300 flex items-center" href="dashboard.php">
                            <i class="fas fa-home mr-1"></i>Accueil
                        </a>
                    </li>
                    <li>
                        <a class="text-white hover:text-blue-200 transition duration-300 flex items-center font-semibold" href="reserver.php">
                            <i class="fas fa-plus-circle mr-1"></i>Réserver
                        </a>
                    </li>
                    <li>
                        <a class="text-white hover:text-blue-200 transition duration-300 flex items-center" href="historique.php">
                            <i class="fas fa-history mr-1"></i>Mes réservations
                        </a>
                    </li>
                </ul>
                
                <div class="relative group">
                    <button class="text-white flex items-center focus:outline-none">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?php echo safe_output($_SESSION['etudiant_prenom'] . ' ' . $_SESSION['etudiant_nom']); ?>
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <ul class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden group-hover:block z-10">
                        <li>
                            <a class="block px-4 py-2 text-gray-800 hover:bg-gray-100 flex items-center" href="profil.php">
                                <i class="fas fa-user mr-2"></i>Mon profil
                            </a>
                        </li>
                        <li><hr class="border-gray-200"></li>
                        <li>
                            <a class="block px-4 py-2 text-gray-800 hover:bg-gray-100 flex items-center" href="logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation (hidden by default) -->
        <div class="lg:hidden hidden bg-blue-700 py-2 mt-2" id="navbar-mobile">
            <ul class="flex flex-col items-center space-y-2">
                <li>
                    <a class="text-white hover:text-blue-200 transition duration-300 flex items-center py-2" href="dashboard.php">
                        <i class="fas fa-home mr-1"></i>Accueil
                    </a>
                </li>
                <li>
                    <a class="text-white hover:text-blue-200 transition duration-300 flex items-center font-semibold py-2" href="reserver.php">
                        <i class="fas fa-plus-circle mr-1"></i>Réserver
                    </a>
                </li>
                <li>
                    <a class="text-white hover:text-blue-200 transition duration-300 flex items-center py-2" href="historique.php">
                        <i class="fas fa-history mr-1"></i>Mes réservations
                    </a>
                </li>
                <li class="w-full text-center py-2">
                    <hr class="border-gray-600 mx-auto w-1/2">
                </li>
                <li>
                    <a class="text-white hover:text-blue-200 transition duration-300 flex items-center py-2" href="profil.php">
                        <i class="fas fa-user mr-2"></i>Mon profil
                    </a>
                </li>
                <li>
                    <a class="text-white hover:text-blue-200 transition duration-300 flex items-center py-2" href="logout.php">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto mt-8 px-4 flex-grow" id="app">
        <div class="flex justify-center">
            <div class="w-full lg:w-10/12">
                <!-- Header -->
                <div class="bg-white rounded-lg shadow-md mb-6 p-6 text-center">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                        Nouvelle réservation
                    </h2>
                    <p class="text-gray-600">Sélectionnez votre trajet et confirmez votre réservation</p>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator mb-8">
                    <div class="step active" id="step1">1</div>
                    <div class="step-line" id="line1"></div>
                    <div class="step" id="step2">2</div>
                    <div class="step-line" id="line2"></div>
                    <div class="step" id="step3">3</div>
                </div>

                <?php if ($message): ?>
                    <div class="mb-4">
                        <?php echo $message; // Assumes 'alert' function outputs Tailwind-compatible HTML ?>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Trip Selection -->
                <div class="form-step active" id="step-1">
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="bg-blue-600 text-white py-4 px-6 rounded-t-lg">
                            <h4 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-map-marker-alt mr-3"></i>
                                Étape 1: Choisissez votre trajet
                            </h4>
                        </div>
                        <div class="p-6">
                            <?php if ($trajets_result && $trajets_result->num_rows > 0): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="trajets-container">
                                    <?php while ($trajet = $trajets_result->fetch_assoc()): ?>
                                        <div class="bg-white rounded-lg shadow-sm p-4 trajet-card h-full
                                            <?php echo ($trajet['id'] == $trajet_preselected) ? 'selected' : ''; ?>" 
                                            data-trajet='<?php echo htmlspecialchars(json_encode($trajet), ENT_QUOTES, 'UTF-8'); ?>'
                                            onclick="selectTrajet(this, <?php echo $trajet['id']; ?>)">
                                            <div class="flex justify-between items-start mb-3">
                                                <h5 class="text-lg font-semibold text-blue-600 mb-0">
                                                    <?php echo safe_output($trajet['nom_trajet']); ?>
                                                </h5>
                                                <span class="bg-blue-600 text-white px-3 py-1 rounded-full price-badge">
                                                    <?php echo number_format($trajet['prix'], 0, ',', ' '); ?> FC
                                                </span>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                                                    <small class="text-gray-500">Départ:</small>
                                                </div>
                                                <p class="mb-2 font-bold text-gray-800"><?php echo safe_output($trajet['point_depart']); ?></p>
                                                
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                                    <small class="text-gray-500">Arrivée:</small>
                                                </div>
                                                <p class="mb-3 font-bold text-gray-800"><?php echo safe_output($trajet['point_arrivee']); ?></p>
                                            </div>
                                            
                                            <hr class="border-gray-200 my-3">
                                            
                                            <div class="flex justify-around text-center mb-4">
                                                <div class="flex-1 border-r border-gray-200">
                                                    <i class="fas fa-calendar-alt text-blue-600 block mb-1"></i>
                                                    <small class="text-gray-500 block">Date</small>
                                                    <strong class="text-gray-800"><?php echo format_date_fr($trajet['date_depart']); ?></strong>
                                                </div>
                                                <div class="flex-1">
                                                    <i class="fas fa-clock text-blue-600 block mb-1"></i>
                                                    <small class="text-gray-500 block">Heure</small>
                                                    <strong class="text-gray-800"><?php echo substr($trajet['heure_depart'], 0, 5); ?></strong>
                                                </div>
                                            </div>
                                            
                                            <div class="flex justify-between items-center mt-3">
                                                <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm">
                                                    <i class="fas fa-users mr-1"></i>
                                                    <?php echo $trajet['places_restantes']; ?> places
                                                </span>
                                                <small class="text-gray-500">
                                                    <?php echo $trajet['reservations_count']; ?> réservé(s)
                                                </small>
                                            </div>
                                            
                                            <?php if ($trajet['description']): ?>
                                                <div class="mt-3 text-sm text-gray-600">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <?php echo safe_output($trajet['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <div class="text-center mt-8">
                                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" id="nextStep1" disabled onclick="nextStep(2)">
                                        <i class="fas fa-arrow-right mr-2"></i>Continuer
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-calendar-times text-gray-400 mb-4" style="font-size: 4rem;"></i>
                                    <h4 class="text-2xl font-semibold text-gray-600 mb-3">Aucun trajet disponible</h4>
                                    <p class="text-gray-500 mb-6">Les nouveaux trajets seront affichés ici dès qu'ils seront programmés.</p>
                                    <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-300">
                                        <i class="fas fa-arrow-left mr-2"></i>Retour au tableau de bord
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Confirmation Details -->
                <div class="form-step" id="step-2">
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="bg-emerald-500 text-white py-4 px-6 rounded-t-lg">
                            <h4 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-check-circle mr-3"></i>
                                Étape 2: Vérifiez les détails
                            </h4>
                        </div>
                        <div class="p-6">
                            <div id="trajet-details" class="mb-6">
                                <!-- Trip details will be populated by JavaScript -->
                            </div>
                            
                            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md mb-6" role="alert">
                                <h6 class="font-bold text-lg mb-2 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>Informations importantes
                                </h6>
                                <ul class="list-disc list-inside text-sm space-y-1">
                                    <li>Présentez-vous au point de départ <strong class="font-semibold">15 minutes avant l'heure</strong></li>
                                    <li>Munissez-vous de votre <strong class="font-semibold">carte d'étudiant</strong></li>
                                    <li>Votre QR code sera généré après confirmation</li>
                                    <li>Vous pouvez annuler jusqu'à <strong class="font-semibold">2 heures avant le départ</strong></li>
                                </ul>
                            </div>
                            
                            <div class="flex justify-between">
                                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-full shadow-lg transition duration-300" onclick="previousStep(1)">
                                    <i class="fas fa-arrow-left mr-2"></i>Retour
                                </button>
                                <button type="button" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-300" onclick="nextStep(3)">
                                    <i class="fas fa-arrow-right mr-2"></i>Confirmer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Terms and Final Validation -->
                <div class="form-step" id="step-3">
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="bg-amber-400 text-gray-800 py-4 px-6 rounded-t-lg">
                            <h4 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-shield-alt mr-3"></i>
                                Étape 3: Conditions et validation
                            </h4>
                        </div>
                        <div class="p-6">
                            <form method="POST" id="reservationForm">
                                <input type="hidden" name="trajet_id" id="selected_trajet_id">
                                
                                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                    <h6 class="text-lg font-semibold text-gray-800 mb-3">Conditions d'utilisation</h6>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                                        <div>
                                            <h6 class="text-blue-600 font-semibold mb-1">1. Réservation</h6>
                                            <p class="mb-3">Chaque étudiant ne peut réserver qu'une seule place par trajet. Les réservations sont confirmées dans l'ordre d'arrivée.</p>
                                            
                                            <h6 class="text-blue-600 font-semibold mb-1">2. Annulation</h6>
                                            <p class="mb-0">Les réservations peuvent être annulées jusqu'à 2 heures avant le départ du trajet.</p>
                                        </div>
                                        <div>
                                            <h6 class="text-blue-600 font-semibold mb-1">3. Validation</h6>
                                            <p class="mb-3">Vous devez présenter votre QR code et votre carte d'étudiant lors de l'embarquement.</p>
                                            
                                            <h6 class="text-blue-600 font-semibold mb-1">4. Responsabilité</h6>
                                            <p class="mb-0">L'université n'est pas responsable des retards ou annulations dus à des circonstances exceptionnelles.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-6">
                                    <input type="checkbox" id="acceptConditions" class="form-checkbox h-5 w-5 text-blue-600 rounded focus:ring-blue-500" required>
                                    <label class="ml-2 text-gray-700 font-semibold cursor-pointer" for="acceptConditions">
                                        J'accepte les conditions d'utilisation et confirme que les informations fournies sont exactes.
                                    </label>
                                </div>
                                
                                <div class="flex justify-between">
                                    <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-full shadow-lg transition duration-300" onclick="previousStep(2)">
                                        <i class="fas fa-arrow-left mr-2"></i>Retour
                                    </button>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-300" id="submitReservation">
                                        <span class="loading-spinner animate-spin mr-2" style="border-width: 2px; border-color: currentColor; border-top-color: transparent; border-radius: 50%; width: 1.25rem; height: 1.25rem;"></span>
                                        <i class="fas fa-check-circle mr-2"></i>Confirmer la réservation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedTrajetData = null; // Stores data of the currently selected trip
        let currentStep = 1; // Tracks the current step in the reservation process

        // Function to toggle mobile navigation menu
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('navbar-mobile');
            mobileMenu.classList.toggle('hidden');
        });

        /**
         * Selects a trip card and updates the UI.
         * @param {HTMLElement} card - The clicked trip card element.
         * @param {number} trajetId - The ID of the selected trip.
         */
        function selectTrajet(card, trajetId) {
            // Remove 'selected' class from all trip cards
            document.querySelectorAll('.trajet-card').forEach(c => c.classList.remove('selected'));
            
            // Add 'selected' class to the clicked card
            card.classList.add('selected');
            // Parse and store the trip data from the card's data attribute
            selectedTrajetData = JSON.parse(card.dataset.trajet);
            
            // Enable the 'Continue' button for the first step
            document.getElementById('nextStep1').disabled = false;
            
            // Update the hidden input field with the selected trip ID for form submission
            document.getElementById('selected_trajet_id').value = trajetId;
        }

        /**
         * Advances to the next step in the reservation form.
         * @param {number} step - The target step number.
         */
        function nextStep(step) {
            // If moving to step 2 and a trip is selected, show its details
            if (step === 2 && selectedTrajetData) {
                showTrajetDetails();
            }
            
            // Hide the current step's form content and update its indicator
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.add('completed');
            
            // Mark the connecting line as completed if moving forward
            if (currentStep < step) {
                document.getElementById(`line${currentStep}`).classList.add('completed');
            }
            
            // Update the current step and show the new step's content and indicator
            currentStep = step;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Scroll to the top of the page for better UX
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Reverts to the previous step in the reservation form.
         * @param {number} step - The target step number.
         */
        function previousStep(step) {
            // Hide the current step's form content and remove its active state
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            
            // Update the current step and show the previous step's content and indicator
            currentStep = step;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Reset indicators for subsequent steps (if any were completed)
            for (let i = currentStep + 1; i <= 3; i++) {
                document.getElementById(`step${i}`).classList.remove('active', 'completed');
                if (i > 1) {
                    document.getElementById(`line${i-1}`).classList.remove('completed');
                }
            }
            
            // Scroll to the top of the page
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Populates the trip details section in Step 2 with data from the selected trip.
         */
        function showTrajetDetails() {
            if (!selectedTrajetData) return; // Exit if no trip data is selected
            
            const detailsContainer = document.getElementById('trajet-details');
            // Use template literals to construct the HTML for trip details
            detailsContainer.innerHTML = `
                <div class="bg-gray-50 rounded-lg p-4">
                    <h5 class="text-xl font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-bus-alt mr-3"></i>
                        ${selectedTrajetData.nom_trajet}
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-map-marker-alt text-green-500 mr-2"></i>Départ:</strong>
                                <p class="text-gray-800 mb-1">${selectedTrajetData.point_depart}</p>
                            </div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-map-marker-alt text-red-500 mr-2"></i>Arrivée:</strong>
                                <p class="text-gray-800 mb-1">${selectedTrajetData.point_arrivee}</p>
                            </div>
                        </div>
                        <div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Date:</strong>
                                <p class="text-gray-800 mb-1">${formatDate(selectedTrajetData.date_depart)}</p>
                            </div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-clock text-blue-600 mr-2"></i>Heure:</strong>
                                <p class="text-gray-800 mb-1">${selectedTrajetData.heure_depart.substring(0, 5)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-dollar-sign text-green-500 mr-2"></i>Prix:</strong>
                                <span class="bg-green-500 text-white text-lg px-3 py-1 rounded-full">${parseInt(selectedTrajetData.prix).toLocaleString('fr-FR')} FC</span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-3">
                                <strong class="text-gray-700 flex items-center"><i class="fas fa-users text-blue-500 mr-2"></i>Places restantes:</strong>
                                <span class="bg-blue-500 text-white text-lg px-3 py-1 rounded-full">${selectedTrajetData.places_restantes}</span>
                            </div>
                        </div>
                    </div>
                    ${selectedTrajetData.description ? `
                        <div class="mt-4 text-sm text-gray-600">
                            <strong class="flex items-center"><i class="fas fa-info-circle text-amber-500 mr-2"></i>Description:</strong>
                            <p class="mb-0">${selectedTrajetData.description}</p>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        /**
         * Formats a date string into a localized French format including weekday.
         * @param {string} dateString - The date string to format (e.g., "YYYY-MM-DD").
         * @returns {string} The formatted date string.
         */
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            };
            return date.toLocaleDateString('fr-FR', options);
        }

        // Event listener for form submission
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitReservation');
            const spinner = submitBtn.querySelector('.loading-spinner');
            
            // Display the loading spinner and disable the button to prevent multiple submissions
            spinner.style.display = 'inline-block';
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<span class="loading-spinner animate-spin mr-2" style="border-width: 2px; border-color: currentColor; border-top-color: transparent; border-radius: 50%; width: 1.25rem; height: 1.25rem;"></span>Traitement en cours...`;
        });

        // Pre-select a trip if its ID is provided in the URL on page load
        <?php if ($trajet_preselected > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Find the card corresponding to the pre-selected trip ID
                const preselectedCard = document.querySelector(`[data-trajet*="\"id\":<?php echo $trajet_preselected; ?>"]`);
                if (preselectedCard) {
                    selectTrajet(preselectedCard, <?php echo $trajet_preselected; ?>);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
