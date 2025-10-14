<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require '../database.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $recipient = $_POST['recipient'] ?? '';
        $recipient_type = $_POST['recipient-type'] ?? 'custom';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (empty($subject) || empty($message)) {
            throw new Exception("Le sujet et le message sont obligatoires.");
        }
        
        $recipients = [];
        
        if ($recipient_type === 'custom' && !empty($recipient)) {
            $recipients = array_map('trim', explode(',', $recipient));
        } else {
            $conn = db_connect();
            
            $conn->set_charset("utf8mb4");
            
            switch ($recipient_type) {
                case 'all-members':
                    $sql = "SELECT DISTINCT u.email 
                            FROM Utilisateur u 
                            JOIN Adhérence a ON u.idUtilisateur = a.idUtilisateur 
                            WHERE a.idClub = ?";
                    $stmt = $conn->prepare($sql);
                    $club_id = 1;
                    $stmt->bind_param("i", $club_id);
                    break;
                    
                case 'event-attendees':
                    $sql = "SELECT DISTINCT u.email 
                            FROM Utilisateur u 
                            JOIN Inscription i ON u.idUtilisateur = i.idUtilisateur 
                            JOIN Événement e ON i.idEvenement = e.idEvenement 
                            WHERE e.idClub = ?";
                    $stmt = $conn->prepare($sql);
                    $club_id = 1;
                    $stmt->bind_param("i", $club_id);
                    break;
                    
                default:
                    throw new Exception("Type de destinataire invalide.");
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row['email'];
            }
            
            $stmt->close();
            $conn->close();
            
            if (empty($recipients)) {
                throw new Exception("Aucun destinataire trouvé pour le type sélectionné.");
            }
        }
        
        $valid_recipients = [];
        foreach ($recipients as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_recipients[] = $email;
            }
        }
        
        if (empty($valid_recipients)) {
            throw new Exception("Aucune adresse email valide trouvée.");
        }
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'xassil7@gmail.com';
            $mail->Password = 'ehow xvqr vkyd zmrh';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('xassil7@gmail.com', 'ClubConnect');
            $mail->addReplyTo('xassil7@gmail.com', 'ClubConnect');
            
            foreach ($valid_recipients as $email) {
                $mail->addBCC($email);
            }
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($message));
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            
            $conn = db_connect();
            if (!$conn->connect_error) {
                $conn->set_charset("utf8mb4");
                
                $user_id = 1;
                $sql = "INSERT INTO Email (destinataire, sujet, message, dateEnvoi, idUtilisateur) 
                        VALUES (?, ?, ?, NOW(), ?)";
                
                $stmt = $conn->prepare($sql);
                $recipient_list = implode(', ', $valid_recipients);
                $stmt->bind_param("sssi", $recipient_list, $subject, $message, $user_id);
                $stmt->execute();
                $stmt->close();
                $conn->close();
            }
            
            $success_message = "Email envoyé avec succès à " . count($valid_recipients) . " destinataires !";
            
        } catch (Exception $e) {
            throw new Exception("L'email n'a pas pu être envoyé. Erreur: " . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

function getFormValue($field) {
    return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Envoyer un Email</title>
    <link rel="stylesheet" href="communication.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h1>ClubConnect</h1>
            <p>Tableau de Bord</p>
        </div>

        <nav class="nav">
            <a href="home.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                    <line x1="16" x2="16" y1="2" y2="6"/>
                    <line x1="8" x2="8" y1="2" y2="6"/>
                    <line x1="3" x2="21" y1="10" y2="10"/>
                </svg>
                <span>Tableau de Bord</span>
            </a>
            <a href="discoverevents.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span>Découvrir Événements</span>
            </a>
            <a href="MyEvents.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                    <line x1="16" x2="16" y1="2" y2="6"/>
                    <line x1="8" x2="8" y1="2" y2="6"/>
                    <line x1="3" x2="21" y1="10" y2="10"/>
                </svg>
                <span>Mes Événements</span>
            </a>
            <a href="createevent.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Créer Événement</span>
            </a>
            <a href="MyClubs.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Mes Clubs</span>
            </a>
            <a href="communication.php" class="nav-item active">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Communications</span>
            </a>
            <a href="certificats.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="8" r="6"/>
                    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                </svg>
                <span>Certificats</span>
            </a>
        </nav>

        <div class="user-profile">
            <div class="user-profile-content">
                <div class="user-avatar">JS</div>
                <div class="user-info">
                    <p>Jean Smith</p>
                    <p class="user-major">Informatique</p>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <button class="back-button" onclick="window.history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div class="header-title">
                    <h1>Envoyer un Email</h1>
                    <p>Composez et envoyez des messages aux membres du club</p>
                </div>
            </div>
            <div class="notification-dot"></div>
        </div>

        <?php if ($success_message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="email-container">
            <div class="email-form-wrapper">
                <form method="POST">
                    <div class="form-group">
                        <label for="recipient" class="form-label">À</label>
                        <input 
                            type="text" 
                            id="recipient" 
                            name="recipient"
                            class="form-input" 
                            placeholder="Entrez les adresses email (séparées par des virgules)"
                            value="<?php echo getFormValue('recipient'); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Envoyer à</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="recipient-type" value="all-members" class="radio-input"
                                    <?php echo (getFormValue('recipient-type') === 'all-members') ? 'checked' : ''; ?>>
                                <span>Tous les Membres du Club</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="recipient-type" value="event-attendees" class="radio-input"
                                    <?php echo (getFormValue('recipient-type') === 'event-attendees') ? 'checked' : ''; ?>>
                                <span>Participants aux Événements</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="recipient-type" value="custom" class="radio-input"
                                    <?php echo (getFormValue('recipient-type') === 'custom' || empty(getFormValue('recipient-type'))) ? 'checked' : ''; ?>>
                                <span>Destinataires Personnalisés</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject" class="form-label">Sujet</label>
                        <input 
                            type="text" 
                            id="subject" 
                            name="subject"
                            class="form-input" 
                            placeholder="Entrez le sujet de l'email"
                            value="<?php echo getFormValue('subject'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label">Message</label>
                        <textarea 
                            id="message" 
                            name="message"
                            class="form-textarea" 
                            placeholder="Composez votre message..."
                            required
                        ><?php echo getFormValue('message'); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <div class="actions-left">
                            <button type="button" class="btn btn-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                </svg>
                                <span>Joindre un Fichier</span>
                            </button>
                        </div>

                        <div class="actions-right">
                            <button type="button" class="btn btn-outline">
                                Sauvegarder le Brouillon
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13"/>
                                </svg>
                                <span>Envoyer l'Email</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div>
                            <p class="stat-value">156</p>
                            <p class="stat-label">Destinataires Totaux</p>
                        </div>
                        <svg class="stat-icon gray" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-content">
                        <div>
                            <p class="stat-value">23</p>
                            <p class="stat-label">Emails Envoyés Aujourd'hui</p>
                        </div>
                        <svg class="stat-icon green" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const recipientInput = document.getElementById('recipient');
            const radioButtons = document.querySelectorAll('input[name="recipient-type"]');
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        recipientInput.disabled = false;
                        recipientInput.placeholder = 'Entrez les adresses email (séparées par des virgules)';
                    } else {
                        recipientInput.disabled = true;
                        recipientInput.placeholder = 'Les destinataires seront sélectionnés automatiquement';
                        recipientInput.value = '';
                    }
                });
            });
            
            const selectedRadio = document.querySelector('input[name="recipient-type"]:checked');
            if (selectedRadio && selectedRadio.value !== 'custom') {
                recipientInput.disabled = true;
                recipientInput.placeholder = 'Les destinataires seront sélectionnés automatiquement';
            }
        });
    </script>
</body>
</html>