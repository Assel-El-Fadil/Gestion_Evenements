<?php
require "database.php";

$conn = db_connect();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@etu.uae.ac.ma')) {
        $login_error = "Veuillez utiliser une adresse email @etu.uae.ac.ma valide.";
    }
    else {
        if ($conn) {
            $stmt = $conn->prepare("SELECT idUtilisateur, mdp, role, nom, prenom FROM Utilisateur WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($idUtilisateur, $hashed_password, $role, $nom, $prenom);
                    $stmt->fetch();

                    if (password_verify($password, $hashed_password)) {
                        session_start();
                        $_SESSION["user_id"] = $idUtilisateur;
                        $_SESSION["user_role"] = $role;
                        $_SESSION["user_name"] = $nom . " " . $prenom;

                        switch ($role) {
                            case 'organisateur':
                                header("Location: organisateur/home.php");
                                break;
                            case 'admin':
                                header("Location: admin/admin.php");
                                break;
                            case 'utilisateur':
                                header("Location: utilisateur/home.php");
                                break;
                            default:
                                $login_error = "Rôle d'utilisateur non reconnu.";
                                break;
                        }
                        exit();
                    } else {
                        $login_error = "Mot de passe incorrect.";
                    }
                } else {
                    $login_error = "Aucun compte trouvé avec cet email.";
                }
                $stmt->close();
            } else {
                $login_error = "Erreur de préparation de la requête.";
            }
        } else {
            $login_error = "Erreur de connexion à la base de données.";
        }
    }
}
?>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire de Connexion - ClubConnect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #000000;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom right, #000000, rgba(17, 24, 39, 0.5), #000000);
            z-index: 0;
        }

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

        .login-card {
            width: 100%;
            max-width: 28rem;
            position: relative;
            z-index: 10;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgba(59,130,246,0.2);
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .icon-container {
            width: 4rem;
            height: 4rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .lock-icon {
            width: 2rem;
            height: 2rem;
            color: white;
        }

        .title {
            color: white;
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 1rem;
            line-height: 1.5;
        }

        .form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .label {
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .input-container {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.25rem;
            height: 1.25rem;
            color: rgba(255,255,255,0.6);
        }

        .input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.5rem;
            color: white;
            font-size: 1rem;
            line-height: 1.5;
            transition: all 0.2s;
            outline: none;
        }

        .input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .input:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
        }

        .password-input {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.25rem;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            width: 2rem;
            height: 2rem;
        }

        .password-toggle:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            border-color: rgba(255,255,255,0.4);
        }

        .password-toggle svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
        }

        .checkbox {
            margin-right: 0.5rem;
            accent-color: #60a5fa;
        }

        .forgot-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: white;
        }

        .submit-button {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 0.5rem;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        .submit-button:hover {
            background: rgba(255,255,255,0.3);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            transform: scale(1.02);
        }

        .submit-button:active {
            transform: scale(0.98);
        }

        .signup-link {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255,255,255,0.7);
            font-size: 1rem;
            line-height: 1.5;
        }

        .signup-link a {
            color: white;
            text-decoration: underline;
            transition: color 0.2s;
        }

        .signup-link a:hover {
            color: rgba(255,255,255,0.8);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 640px) {
            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <div class="header">
            <div class="icon-container">
                <svg class="lock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                    <path d="m7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1 class="title">Content de vous revoir</h1>
            <p class="subtitle">Connectez-vous à votre compte</p>
        </div>

        <?php if (isset($login_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form class="form" method="post" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="email" class="label">Email</label>
                <div class="input-container">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="m4 7 6.94 4.338a2 2 0 0 0 2.12 0L20 7"/>
                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                    </svg>
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        class="input" 
                        placeholder="Entrez votre email" 
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="label">Mot de passe</label>
                <div class="input-container">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                        <path d="m7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        class="input password-input" 
                        placeholder="Entrez votre mot de passe" 
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <svg id="eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                            <path d="m10.73 5.08-1.4-.14a11.5 11.5 0 0 0-7.4 3.15"/>
                            <path d="M9.88 9.88 3.5 3.5"/>
                            <path d="m6.61 6.61 7.78 7.78"/>
                            <path d="M12 16a4 4 0 0 1-4-4"/>
                            <path d="M21 21 3 3"/>
                            <path d="m15 9-6 6"/>
                            <path d="m21 12-1.6-1.6"/>
                        </svg>
                        <svg id="eye-open" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-footer">
                <label class="checkbox-container">
                    <input type="checkbox" class="checkbox" name="remember">
                    Se souvenir de moi
                </label>
            </div>

            <button type="submit" class="submit-button">
                Se connecter
            </button>
        </form>

        <div class="signup-link">
            <p>
                Vous n'avez pas de compte ? 
                <a href="signup.php">S'inscrire</a>
            </p>
        </div>
    </div>

    <script>
        function validateForm() {
            const email = document.getElementById('email').value;
            
            if (!email.endsWith('@etu.uae.ac.ma') || !email.includes('@')) {
                alert('Veuillez utiliser une adresse email @etu.uae.ac.ma valide.');
                return false;
            }
            
            return true;
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeClosed = document.getElementById('eye-closed');
            const eyeOpen = document.getElementById('eye-open');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeClosed.classList.add('hidden');
                eyeOpen.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeClosed.classList.remove('hidden');
                eyeOpen.classList.add('hidden');
            }
        }

        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email && (!email.endsWith('@etu.uae.ac.ma') || !email.includes('@'))) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = 'rgba(255,255,255,0.2)';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.style.opacity = '0';
                errorMessage.style.transform = 'translateY(-10px)';
                errorMessage.style.display = 'block';
                
                setTimeout(() => {
                    errorMessage.style.transition = 'all 0.3s ease';
                    errorMessage.style.opacity = '1';
                    errorMessage.style.transform = 'translateY(0)';
                }, 100);
            }
        });
    </script>
</body>
</html>