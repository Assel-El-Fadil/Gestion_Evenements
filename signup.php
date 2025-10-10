<?php
// --- CONFIGURATION DE LA BASE DE DONNÉES ---
require "database.php";

// --- CONNEXION À LA BASE DE DONNÉES ---
$conn = db_connect();

// --- TRAITEMENT DU FORMULAIRE ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Récupération et sécurisation des champs
    $prenom = htmlspecialchars(trim($_POST['firstName']));
    $nom = htmlspecialchars(trim($_POST['lastName']));
    $dateNaissance = $_POST['dateOfBirth'];
    $email = htmlspecialchars(trim($_POST['institutionalEmail']));
    $apogee = htmlspecialchars(trim($_POST['studentId']));
    $annee = htmlspecialchars(trim($_POST['yearOfStudy']));
    $filiere = isset($_POST['fieldOfStudy']) ? htmlspecialchars(trim($_POST['fieldOfStudy'])) : null;
    $mdp = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = "utilisateur";

    // Vérifier si l'email ou l'apogée existe déjà
    $check = $conn->prepare("SELECT idUtilisateur FROM utilisateur WHERE email = ? OR apogee = ?");
    $check->bind_param("ss", $email, $apogee);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Cet email ou numéro d\\'apogée existe déjà.'); window.history.back();</script>";
        $check->close();
        db_close();
        exit;
    }
    $check->close();

    // --- INSERTION DANS LA TABLE ---
    $stmt = $conn->prepare("
        INSERT INTO utilisateur (nom, prenom, dateNaissance, annee, filiere, email, mdp, apogee, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt === false) {
        die("Erreur de préparation: " . $conn->error);
    }

    $stmt->bind_param("sssssssss", $nom, $prenom, $dateNaissance, $annee, $filiere, $email, $mdp, $apogee, $role);

    if ($stmt->execute()) {
        // Création de cookies valables 1 jour
        setcookie("user_email", $email, time() + 86400, "/");
        setcookie("user_nom", $prenom . " " . $nom, time() + 86400, "/");
        setcookie("user_role", $role, time() + 86400, "/");

        //ici après sign up on sera dirigé vers captcha.php
        echo "<script>
            alert('Inscription réussie ! Bienvenue $prenom.');
            window.location.href = 'captcha.php'; 
        </script>";
    } else {
        echo "<script>alert('Erreur lors de l\\'inscription: " . addslashes($stmt->error) . "'); window.history.back();</script>";
    }

    $stmt->close();
}

db_close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Inscription étudiant</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #000000;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Background layers */
        .bg-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom right, #000000, rgba(17, 24, 39, 0.5), #000000);
            z-index: 0;
        }

        /* Animated orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(96px);
            animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .orb-1 {
            top: 25%;
            left: 25%;
            width: 384px;
            height: 384px;
            background-color: rgba(59, 130, 246, 0.1);
        }

        .orb-2 {
            bottom: 25%;
            right: 25%;
            width: 384px;
            height: 384px;
            background-color: rgba(168, 85, 247, 0.1);
            animation-delay: 1s;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .container {
            width: 100%;
            max-width: 28rem;
            position: relative;
            z-index: 10;
        }

        /* Header */
        .header {
            margin-bottom: 2rem;
            text-align: center;
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-size: 1rem;
            color: #9ca3af;
        }

        /* Main card */
        .card {
            background-color: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(24px);
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            margin-bottom: 1.5rem;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .card-description {
            font-size: 0.875rem;
            color: rgba(209, 213, 219, 0.8);
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
        }

        input, select {
            width: 100%;
            padding: 0.625rem 0.75rem;
            background-color: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.375rem;
            color: #ffffff;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }

        input::placeholder {
            color: #9ca3af;
        }

        input:focus, select:focus {
            background-color: rgba(0, 0, 0, 0.6);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3);
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }

        select option {
            background-color: rgba(0, 0, 0, 0.9);
            color: #ffffff;
        }

        /* Error messages */
        .error {
            font-size: 0.75rem;
            color: #f87171;
            margin-top: -0.25rem;
        }

        /* Hidden field */
        .hidden {
            display: none;
        }

        /* Submit button */
        button[type="submit"] {
            width: 100%;
            padding: 0.875rem;
            background-color: rgba(37, 99, 235, 0.8);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.375rem;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2);
        }

        button[type="submit"]:hover {
            background-color: rgba(37, 99, 235, 1);
        }

        button[type="submit"]:active {
            transform: scale(0.98);
        }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: rgba(209, 213, 219, 0.8);
        }

        .login-link a {
            color: #60a5fa;
            text-decoration: none;
            transition: color 0.2s;
        }

        .login-link a:hover {
            color: #93c5fd;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        /* Date input styling */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1.875rem;
            }

            h2 {
                font-size: 1.25rem;
            }

            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>ClubConnect</h1>
            <p class="subtitle">Inscription étudiant</p>
        </div>

        <!-- Main Form Card -->
        <div class="card">
            <div class="card-header">
                <h2>Créez votre compte</h2>
                <p class="card-description">Rejoignez la communauté des clubs de votre université</p>
            </div>

            <form id="signupForm" method="POST" action="" novalidate>
                <!-- Personal Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">Prénom</label>
                        <input 
                            type="text" 
                            id="firstName" 
                            name="firstName" 
                            placeholder="Jean"
                            required
                        >
                        <span class="error" id="firstNameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Nom</label>
                        <input 
                            type="text" 
                            id="lastName" 
                            name="lastName" 
                            placeholder="Dupont"
                            required
                        >
                        <span class="error" id="lastNameError"></span>
                    </div>
                </div>

                <!-- Date of Birth -->
                <div class="form-group">
                    <label for="dateOfBirth">Date de naissance</label>
                    <input 
                        type="date" 
                        id="dateOfBirth" 
                        name="dateOfBirth"
                        required
                    >
                    <span class="error" id="dateOfBirthError"></span>
                </div>

                <!-- Email institutionnel -->
                <div class="form-group">
                    <label for="institutionalEmail">Email institutionnel</label>
                    <input 
                        type="email" 
                        id="institutionalEmail" 
                        name="institutionalEmail" 
                        placeholder="jean.dupont@etu.uae.ac.ma"
                        required
                    >
                    <span class="error" id="institutionalEmailError"></span>
                </div>

                <!-- Apogée -->
                <div class="form-group">
                    <label for="studentId">Apogée</label>
                    <input 
                        type="text" 
                        id="studentId" 
                        name="studentId" 
                        placeholder="12345678"
                        pattern="[0-9]{8}"
                        title="Veuillez entrer exactement 8 chiffres"
                        maxlength="8"
                        required
                    >
                    <span class="error" id="studentIdError"></span>
                </div>

                <!-- Academic Information -->
                <div class="form-group">
                    <label for="yearOfStudy">Année d'études</label>
                    <select id="yearOfStudy" name="yearOfStudy" required>
                        <option value="">Sélectionnez l'année</option>
                        <option value="2ap1">2AP1</option>
                        <option value="2ap2">2AP2</option>
                        <option value="ci1">CI1</option>
                        <option value="ci2">CI2</option>
                        <option value="ci3">CI3</option>
                    </select>
                    <span class="error" id="yearOfStudyError"></span>
                </div>

                <!-- Field of Study (conditional) -->
                <div class="form-group hidden" id="fieldOfStudyGroup">
                    <label for="fieldOfStudy">Filière d'études</label>
                    <select id="fieldOfStudy" name="fieldOfStudy">
                        <option value="">Sélectionnez votre filière</option>
                        <option value="gi">GI (Génie Informatique)</option>
                        <option value="bdai">BDAI (Big Data & AI)</option>
                        <option value="gstr">GSTR (Génie des Systèmes de Télécommunication et Réseaux)</option>
                        <option value="gc">GC (Génie Civil)</option>
                        <option value="gm">GM (Génie Mécatronique)</option>
                        <option value="scm">SCM (Supply Chain Management)</option>
                        <option value="cs">CS (Cybersecurity)</option>
                    </select>
                    <span class="error" id="fieldOfStudyError"></span>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••"
                        required
                    >
                    <span class="error" id="passwordError"></span>
                </div>

                <!-- Submit Button -->
                <button type="submit">Créer un compte</button>
            </form>

            <!-- Login Link -->
            <div class="login-link">
                <p>
                    Vous avez déjà un compte ?
                    <a href="signin.php">Se connecter</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Show/hide field of study based on year selection
        document.getElementById('yearOfStudy').addEventListener('change', function() {
            const fieldOfStudyGroup = document.getElementById('fieldOfStudyGroup');
            const fieldOfStudy = document.getElementById('fieldOfStudy');
            const ciYears = ['ci1', 'ci2', 'ci3'];
            
            if (ciYears.includes(this.value)) {
                fieldOfStudyGroup.classList.remove('hidden');
                fieldOfStudy.required = true;
            } else {
                fieldOfStudyGroup.classList.add('hidden');
                fieldOfStudy.required = false;
                fieldOfStudy.value = '';
                document.getElementById('fieldOfStudyError').textContent = '';
            }
        });

        // Clear error on input
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const errorElement = document.getElementById(this.name + 'Error');
                if (errorElement) {
                    errorElement.textContent = '';
                }
            });
        });

        // Form validation and submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            let isValid = true;

            // Clear all errors
            document.querySelectorAll('.error').forEach(error => {
                error.textContent = '';
            });

            // Get form values
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const dateOfBirth = document.getElementById('dateOfBirth').value;
            const institutionalEmail = document.getElementById('institutionalEmail').value.trim();
            const studentId = document.getElementById('studentId').value.trim();
            const yearOfStudy = document.getElementById('yearOfStudy').value;
            const fieldOfStudy = document.getElementById('fieldOfStudy').value;
            const password = document.getElementById('password').value;

            // Validation
            if (!firstName) {
                document.getElementById('firstNameError').textContent = 'Le prénom est requis';
                isValid = false;
            }

            if (!lastName) {
                document.getElementById('lastNameError').textContent = 'Le nom est requis';
                isValid = false;
            }

            if (!dateOfBirth) {
                document.getElementById('dateOfBirthError').textContent = 'La date de naissance est requise';
                isValid = false;
            }

            if (!institutionalEmail) {
                document.getElementById('institutionalEmailError').textContent = "L'email institutionnel est requis";
                isValid = false;
            } else {
                const institutionalEmailRegex = /^[^\s@]+@etu\.uae\.ac\.ma$/;
                if (!institutionalEmailRegex.test(institutionalEmail)) {
                    document.getElementById('institutionalEmailError').textContent = "L'email institutionnel doit se terminer par @etu.uae.ac.ma";
                    isValid = false;
                }
            }

            if (!studentId) {
                document.getElementById('studentIdError').textContent = 'L\'Apogée est requis';
                isValid = false;
            }

            if (!yearOfStudy) {
                document.getElementById('yearOfStudyError').textContent = "L'année d'études est requise";
                isValid = false;
            }

            const ciYears = ['ci1', 'ci2', 'ci3'];
            if (ciYears.includes(yearOfStudy) && !fieldOfStudy) {
                document.getElementById('fieldOfStudyError').textContent = "La filière d'études est requise";
                isValid = false;
            }

            if (!password) {
                document.getElementById('passwordError').textContent = 'Le mot de passe est requis';
                isValid = false;
            } else if (password.length < 8) {
                document.getElementById('passwordError').textContent = 'Le mot de passe doit contenir au moins 8 caractères';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>