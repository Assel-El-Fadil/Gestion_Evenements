<?php
require "database.php";
require "email_config.php";
require "organisateur/vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$conn = db_connect();

// Start session for verification
session_start();

// Function to generate 6-digit verification code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to send verification email
function sendVerificationEmail($email, $code, $name) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Code de vérification - ClubConnect';
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2563eb; text-align: center;'>Vérification de votre compte ClubConnect</h2>
            <p>Bonjour $name,</p>
            <p>Merci de vous être inscrit sur ClubConnect. Pour finaliser votre inscription, veuillez utiliser le code de vérification suivant :</p>
            <div style='background-color: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                <h1 style='color: #1f2937; font-size: 32px; letter-spacing: 5px; margin: 0;'>$code</h1>
            </div>
            <p>Ce code est valide pendant 10 minutes. Si vous n'avez pas demandé cette inscription, vous pouvez ignorer cet email.</p>
            <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>Cordialement,<br>L'équipe ClubConnect</p>
        </div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Handle verification code submission
    if (isset($_POST['verification_code'])) {
        $enteredCode = trim($_POST['verification_code']);
        $storedCode = $_SESSION['verification_code'] ?? '';
        $codeExpiry = $_SESSION['code_expiry'] ?? 0;
        
        if ($enteredCode === $storedCode && time() < $codeExpiry) {
            // Code is valid, proceed with user registration
            $prenom = $_SESSION['temp_prenom'];
            $nom = $_SESSION['temp_nom'];
            $dateNaissance = $_SESSION['temp_dateNaissance'];
            $email = $_SESSION['temp_email'];
            $apogee = $_SESSION['temp_apogee'];
            $annee = $_SESSION['temp_annee'];
            $filiere = $_SESSION['temp_filiere'];
            $mdp = $_SESSION['temp_mdp'];
            $role = "utilisateur";
            
            // Clear temporary session data
            unset($_SESSION['verification_code'], $_SESSION['code_expiry'], $_SESSION['temp_prenom'], 
                  $_SESSION['temp_nom'], $_SESSION['temp_dateNaissance'], $_SESSION['temp_email'], 
                  $_SESSION['temp_apogee'], $_SESSION['temp_annee'], $_SESSION['temp_filiere'], $_SESSION['temp_mdp']);
        } else {
            $verification_error = "Code de vérification invalide ou expiré.";
        }
    } else {
        // Handle initial form submission
        $prenom = htmlspecialchars(trim($_POST['firstName']));
        $nom = htmlspecialchars(trim($_POST['lastName']));
        $dateNaissance = $_POST['dateOfBirth'];
        $email = htmlspecialchars(trim($_POST['institutionalEmail']));
        $apogee = htmlspecialchars(trim($_POST['studentId']));
        $annee = htmlspecialchars(trim($_POST['yearOfStudy']));
        $filiere = isset($_POST['fieldOfStudy']) ? htmlspecialchars(trim($_POST['fieldOfStudy'])) : null;
        $mdp = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role = "utilisateur";
    }

    // Only validate and check database if not in verification mode
    if (!isset($_POST['verification_code'])) {
        // 🔒 VALIDATION DATE DE NAISSANCE (17-50 ans)
        $today = new DateTime();
        $birthDate = new DateTime($dateNaissance);
        $age = $today->diff($birthDate)->y;
        
        if ($age < 17 || $age > 50) {
            echo "<script>alert('L\\'âge doit être compris entre 17 et 50 ans.'); window.history.back();</script>";
            db_close();
            exit;
        }

        // 🔒 VALIDATION NUMERO APOGEE (exactement 8 chiffres)
        if (!preg_match('/^\d{8}$/', $apogee)) {
            echo "<script>alert('Le numéro Apogée doit contenir exactement 8 chiffres.'); window.history.back();</script>";
            db_close();
            exit;
        }

        $check = $conn->prepare("SELECT idUtilisateur FROM Utilisateur WHERE email = ? OR apogee = ?");
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
        
        // Generate verification code and send email
        $verificationCode = generateVerificationCode();
        $codeExpiry = time() + (CODE_EXPIRY_MINUTES * 60); // Configurable expiry time
        
        // Store verification data in session
        $_SESSION['verification_code'] = $verificationCode;
        $_SESSION['code_expiry'] = $codeExpiry;
        $_SESSION['temp_prenom'] = $prenom;
        $_SESSION['temp_nom'] = $nom;
        $_SESSION['temp_dateNaissance'] = $dateNaissance;
        $_SESSION['temp_email'] = $email;
        $_SESSION['temp_apogee'] = $apogee;
        $_SESSION['temp_annee'] = $annee;
        $_SESSION['temp_filiere'] = $filiere;
        $_SESSION['temp_mdp'] = $mdp;
        
        // Send verification email
        if (sendVerificationEmail($email, $verificationCode, $prenom . ' ' . $nom)) {
            $verification_sent = true;
        } else {
            echo "<script>alert('Erreur lors de l\\'envoi de l\\'email de vérification. Veuillez réessayer.'); window.history.back();</script>";
            db_close();
            exit;
        }
    }

    // Only insert into database if verification is complete
    if (isset($_POST['verification_code']) && !isset($verification_error)) {
        $stmt = $conn->prepare("
            INSERT INTO Utilisateur (nom, prenom, dateNaissance, annee, filiere, email, mdp, apogee, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt === false) {
            die("Erreur de préparation: " . $conn->error);
        }

        $stmt->bind_param("sssssssss", $nom, $prenom, $dateNaissance, $annee, $filiere, $email, $mdp, $apogee, $role);

        if ($stmt->execute()) {
            // Récupérer l'ID du nouvel utilisateur
            $new_user_id = $stmt->insert_id;
            
            // Démarrer la session pour le nouvel utilisateur
            $_SESSION["user_id"] = $new_user_id;
            $_SESSION["user_role"] = $role;
            $_SESSION["user_name"] = $prenom . " " . $nom;
            
            // Rediriger vers la page d'accueil
            echo "<script>
                alert('Inscription réussie ! Bienvenue $prenom.');
                window.location.href = 'utilisateur/home.php'; 
            </script>";
        } else {
            echo "<script>alert('Erreur lors de l\\'inscription: " . addslashes($stmt->error) . "'); window.history.back();</script>";
        }

        $stmt->close();
    }
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

        /* Verification form styles */
        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .resend-section p {
            color: rgba(209, 213, 219, 0.8);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .resend-btn {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .resend-btn:hover {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(59, 130, 246, 0.5);
        }

        .resend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Verification code input */
        #verification_code {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: 600;
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
        <div class="header">
            <h1>ClubConnect</h1>
            <p class="subtitle">Inscription étudiant</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><?php echo isset($verification_sent) ? 'Vérifiez votre email' : 'Créez votre compte'; ?></h2>
                <p class="card-description">
                    <?php 
                    if (isset($verification_sent)) {
                        echo 'Un code de vérification a été envoyé à votre adresse email.';
                    } else {
                        echo 'Rejoignez la communauté des clubs de votre université';
                    }
                    ?>
                </p>
            </div>

            <?php if (isset($verification_sent)): ?>
                <!-- Verification Form -->
                <form id="verificationForm" method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="verification_code">Code de vérification</label>
                        <input 
                            type="text" 
                            id="verification_code" 
                            name="verification_code" 
                            placeholder="123456"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            title="Veuillez entrer le code à 6 chiffres"
                            required
                            oninput="this.value = this.value.replace(/[^\d]/g, '')"
                        >
                        <span class="error" id="verificationError">
                            <?php echo isset($verification_error) ? $verification_error : ''; ?>
                        </span>
                    </div>

                    <button type="submit">Vérifier le code</button>
                </form>

                <div class="resend-section">
                    <p>Vous n'avez pas reçu le code ?</p>
                    <button type="button" id="resendCode" class="resend-btn">Renvoyer le code</button>
                </div>
            <?php else: ?>
                <!-- Signup Form -->
                <form id="signupForm" method="POST" action="" novalidate>
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

                <div class="form-group">
                    <label for="dateOfBirth">Date de naissance</label>
                    <input 
                        type="date" 
                        id="dateOfBirth" 
                        name="dateOfBirth"
                        required
                        min="<?php echo date('Y-m-d', strtotime('-50 years')); ?>"
                        max="<?php echo date('Y-m-d', strtotime('-17 years')); ?>"
                    >
                    <span class="error" id="dateOfBirthError"></span>
                </div>

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
                        oninput="this.value = this.value.replace(/[^\d]/g, '')"
                    >
                    <span class="error" id="studentIdError"></span>
                </div>

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

                <button type="submit">Créer un compte</button>
                </form>

                <div class="login-link">
                    <p>
                        Vous avez déjà un compte ?
                        <a href="signin.php">Se connecter</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Fonction de validation de la date de naissance
        function validateBirthDate(dateString) {
            const today = new Date();
            const birthDate = new Date(dateString);
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            // Ajuster l'âge si l'anniversaire n'est pas encore arrivé cette année
            const adjustedAge = monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate()) 
                ? age - 1 
                : age;
            
            return adjustedAge >= 17 && adjustedAge <= 50;
        }

        // Fonction de validation du numéro Apogée
        function validateApogee(apogee) {
            return /^\d{8}$/.test(apogee);
        }

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

        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const errorElement = document.getElementById(this.name + 'Error');
                if (errorElement) {
                    errorElement.textContent = '';
                }
            });
        });

        // 🔒 Validation en temps réel pour le champ Apogée
        document.getElementById('studentId').addEventListener('input', function(e) {
            const value = e.target.value;
            const errorElement = document.getElementById('studentIdError');
            
            // Autoriser uniquement les chiffres
            e.target.value = value.replace(/[^\d]/g, '');
            
            // Limiter à 8 caractères
            if (e.target.value.length > 8) {
                e.target.value = e.target.value.slice(0, 8);
            }
            
            errorElement.textContent = '';
        });

        // Signup form validation
        if (document.getElementById('signupForm')) {
            document.getElementById('signupForm').addEventListener('submit', function(e) {
                let isValid = true;

                document.querySelectorAll('.error').forEach(error => {
                    error.textContent = '';
                });

                const firstName = document.getElementById('firstName').value.trim();
                const lastName = document.getElementById('lastName').value.trim();
                const dateOfBirth = document.getElementById('dateOfBirth').value;
                const institutionalEmail = document.getElementById('institutionalEmail').value.trim();
                const studentId = document.getElementById('studentId').value.trim();
                const yearOfStudy = document.getElementById('yearOfStudy').value;
                const fieldOfStudy = document.getElementById('fieldOfStudy').value;
                const password = document.getElementById('password').value;

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
                } else {
                    // 🔒 NOUVELLE VALIDATION DATE DE NAISSANCE
                    if (!validateBirthDate(dateOfBirth)) {
                        document.getElementById('dateOfBirthError').textContent = 'L\'âge doit être compris entre 17 et 50 ans';
                        isValid = false;
                    }
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
                } else {
                    // 🔒 NOUVELLE VALIDATION NUMERO APOGEE
                    if (!validateApogee(studentId)) {
                        document.getElementById('studentIdError').textContent = 'Le numéro Apogée doit contenir exactement 8 chiffres';
                        isValid = false;
                    }
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
        }

        // Verification form validation
        if (document.getElementById('verificationForm')) {
            document.getElementById('verificationForm').addEventListener('submit', function(e) {
                const verificationCode = document.getElementById('verification_code').value.trim();
                const errorElement = document.getElementById('verificationError');
                
                if (!verificationCode) {
                    errorElement.textContent = 'Le code de vérification est requis';
                    e.preventDefault();
                } else if (!/^\d{6}$/.test(verificationCode)) {
                    errorElement.textContent = 'Le code doit contenir exactement 6 chiffres';
                    e.preventDefault();
                } else {
                    errorElement.textContent = '';
                }
            });
        }

        // Resend code functionality
        if (document.getElementById('resendCode')) {
            let resendCooldown = <?php echo RESEND_COOLDOWN_SECONDS; ?>; // Configurable cooldown
            const resendBtn = document.getElementById('resendCode');
            
            function updateResendButton() {
                if (resendCooldown > 0) {
                    resendBtn.textContent = `Renvoyer dans ${resendCooldown}s`;
                    resendBtn.disabled = true;
                    resendCooldown--;
                    setTimeout(updateResendButton, 1000);
                } else {
                    resendBtn.textContent = 'Renvoyer le code';
                    resendBtn.disabled = false;
                }
            }
            
            // Start cooldown on page load
            updateResendButton();
            
            resendBtn.addEventListener('click', function() {
                if (resendCooldown <= 0) {
                    // Reload page to resend code
                    window.location.reload();
                }
            });
        }
    </script>
</body>
</html>