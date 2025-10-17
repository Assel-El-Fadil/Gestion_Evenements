<?php
session_start();
require "../database.php";
require "../email_config.php";
require "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["user_id"])) {
    header("Location: ../signin.php");
    exit();
}

$current_user_id = $_SESSION["user_id"];

// Récupérer les informations de l'utilisateur
$conn = db_connect();
$user_sql = "SELECT nom, prenom, annee, filiere FROM Utilisateur WHERE idUtilisateur = ?";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("i", $current_user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

if (!$user) {
    header("Location: ../signin.php");
    exit();
}

$user_name = $user['prenom'] . ' ' . $user['nom'];
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$user_department = $user['annee'] . ' - ' . $user['filiere'];

$success_message = '';
$error_message = '';

// Function to get clubs where user is organizer
function getOrganizerClubs($conn, $userId) {
    $clubs = [];
    $sql = "SELECT c.idClub, c.nom FROM Club c 
            JOIN Adherence a ON c.idClub = a.idClub 
            WHERE a.idUtilisateur = ? AND a.position = 'organisateur'
            ORDER BY c.nom";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
    $stmt->close();
    return $clubs;
}

// Function to get events from clubs where user is organizer
function getOrganizerEvents($conn, $userId) {
    $events = [];
    $sql = "SELECT e.idEvenement, e.titre, c.nom as club_nom 
            FROM Evenement e 
            JOIN Club c ON e.idClub = c.idClub 
            JOIN Adherence a ON c.idClub = a.idClub 
            WHERE a.idUtilisateur = ? AND a.position = 'organisateur'
            ORDER BY c.nom, e.titre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// Function to send email using PHPMailer
function sendEmail($to, $subject, $message, $fromName = 'ClubConnect') {
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
        $mail->setFrom(FROM_EMAIL, $fromName);
        
        // Add recipients
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Get clubs and events where user is organizer
$organizer_clubs = getOrganizerClubs($conn, $current_user_id);
$organizer_events = getOrganizerEvents($conn, $current_user_id);

// Load join requests for clubs managed by current user (organisateur)
$requests = [];
try {
    $conn = db_connect();
    $conn->set_charset('utf8mb4');

    $sql = "SELECT r.idUtilisateur, r.idClub, u.nom, u.prenom, u.apogee, u.email, c.nom AS club_nom
            FROM Requete r
            JOIN Utilisateur u ON u.idUtilisateur = r.idUtilisateur
            JOIN Club c ON c.idClub = r.idClub
            WHERE r.idClub IN (
                SELECT idClub FROM Adherence WHERE idUtilisateur = ? AND position = 'organisateur'
            )
            ORDER BY c.nom ASC, u.nom ASC, u.prenom ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // keep $requests empty on error
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle approval/rejection of join requests
        if (isset($_POST['request_action'], $_POST['req_user_id'], $_POST['req_club_id'])) {
            $action = $_POST['request_action'];
            $reqUserId = intval($_POST['req_user_id']);
            $reqClubId = intval($_POST['req_club_id']);

            $conn = db_connect();
            $conn->set_charset('utf8mb4');

            // Verify current user is organiser of this club
            $authStmt = $conn->prepare("SELECT 1 FROM Adherence WHERE idUtilisateur = ? AND idClub = ? AND position = 'organisateur' LIMIT 1");
            $authStmt->bind_param('ii', $current_user_id, $reqClubId);
            $authStmt->execute();
            $isOrganiser = $authStmt->get_result()->num_rows > 0;
            $authStmt->close();

            if (!$isOrganiser) {
                throw new Exception("Action non autorisée: vous n'êtes pas organisateur de ce club.");
            }

            if ($action === 'approve') {
                // Add to adherence if not already present
                $chkStmt = $conn->prepare("SELECT 1 FROM Adherence WHERE idUtilisateur = ? AND idClub = ? LIMIT 1");
                $chkStmt->bind_param('ii', $reqUserId, $reqClubId);
                $chkStmt->execute();
                $alreadyMember = $chkStmt->get_result()->num_rows > 0;
                $chkStmt->close();

                if (!$alreadyMember) {
                    $insStmt = $conn->prepare("INSERT INTO Adherence (idUtilisateur, idClub, position) VALUES (?, ?, 'membre')");
                    $insStmt->bind_param('ii', $reqUserId, $reqClubId);
                    $insStmt->execute();
                    $insStmt->close();
                }

                // Remove from requete
                $delStmt = $conn->prepare("DELETE FROM Requete WHERE idUtilisateur = ? AND idClub = ?");
                $delStmt->bind_param('ii', $reqUserId, $reqClubId);
                $delStmt->execute();
                $delStmt->close();

                $success_message = "Requête approuvée et membre ajouté.";
                
                // Refresh requests list
                $requests = array_filter($requests, function($req) use ($reqUserId, $reqClubId) {
                    return !($req['idUtilisateur'] == $reqUserId && $req['idClub'] == $reqClubId);
                });
                
            } elseif ($action === 'reject') {
                // Just remove from requete
                $delStmt = $conn->prepare("DELETE FROM Requete WHERE idUtilisateur = ? AND idClub = ?");
                $delStmt->bind_param('ii', $reqUserId, $reqClubId);
                $delStmt->execute();
                $delStmt->close();
                $success_message = "Requête rejetée et supprimée.";
                
                // Refresh requests list
                $requests = array_filter($requests, function($req) use ($reqUserId, $reqClubId) {
                    return !($req['idUtilisateur'] == $reqUserId && $req['idClub'] == $reqClubId);
                });
            } else {
                throw new Exception("Action de requête invalide.");
            }

        } else {
            // Handle email sending
            $recipient = $_POST['recipient'] ?? '';
            $recipient_type = $_POST['recipient-type'] ?? 'custom';
            $selected_club = $_POST['selected_club'] ?? '';
            $selected_event = $_POST['selected_event'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $message = $_POST['message'] ?? '';
        
            if (empty($subject) || empty($message)) {
                throw new Exception("Le sujet et le message sont obligatoires.");
            }
            
            $recipients = [];
            $conn = db_connect();
            $conn->set_charset("utf8mb4");
            
            if ($recipient_type === 'custom' && !empty($recipient)) {
                $recipients = array_map('trim', explode(',', $recipient));
            } else {
                switch ($recipient_type) {
                    case 'all-members':
                        if (empty($selected_club)) {
                            throw new Exception("Veuillez sélectionner un club.");
                        }
                        // Get all members of selected club
                        $sql = "SELECT DISTINCT u.email 
                                FROM Utilisateur u 
                                JOIN Adherence a ON u.idUtilisateur = a.idUtilisateur 
                                WHERE a.idClub = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $selected_club);
                        break;
                        
                    case 'event-attendees':
                        if (empty($selected_event)) {
                            throw new Exception("Veuillez sélectionner un événement.");
                        }
                        // Get attendees of selected event
                        $sql = "SELECT DISTINCT u.email 
                                FROM Utilisateur u 
                                JOIN Inscription i ON u.idUtilisateur = i.idUtilisateur 
                                WHERE i.idEvenement = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $selected_event);
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
            
            // Send email using PHPMailer
            if (sendEmail($valid_recipients, $subject, $message, $user_name)) {
                $success_message = "Email envoyé avec succès à " . count($valid_recipients) . " destinataires !<br>";
                $success_message .= "<strong>Sujet:</strong> " . htmlspecialchars($subject) . "<br>";
                $success_message .= "<strong>Destinataires:</strong> " . implode(', ', $valid_recipients);
            } else {
                throw new Exception("Erreur lors de l'envoi de l'email. Veuillez réessayer.");
            }
            
            $conn->close();
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
    <title>ClubConnect - Communications</title>
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
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Background layers */
        .bg-gradient {
            position: fixed;
            inset: 0;
            background: linear-gradient(to bottom right, #000000, rgba(17, 24, 39, 0.5), #000000);
            z-index: -2;
        }

        /* Animated orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(96px);
            animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            z-index: -1;
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

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        .sidebar {
            width: 256px;
            height: 100vh;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: none;
            border-top: none;
            border-bottom: none;
            border-radius: 0 1rem 1rem 0;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .sidebar-header {
            margin-bottom: 2rem;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.5;
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: #9ca3af;
            font-weight: 400;
            line-height: 1.5;
        }

        .sidebar-nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            color: #d1d5db;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .nav-item-active {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .nav-icon {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .sidebar-profile {
            margin-top: auto;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(to right, #3b82f6, #9333ea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .profile-avatar span {
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            color: #ffffff;
            font-weight: 500;
            font-size: 1rem;
            line-height: 1.5;
        }

        .profile-department {
            color: #9ca3af;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .content-container {
            max-width: 80rem;
            margin: 0 auto;
        }

        .header {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-text {
            flex: 1;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.5;
            margin-bottom: 0.25rem;
        }

        .header-subtitle {
            color: #9ca3af;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
        }

        /* Messages */
        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Card */
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Tabs */
        .tabs-list {
            display: flex;
            gap: 0.25rem;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .tab-trigger {
            padding: 0.5rem 0.75rem;
            background: transparent;
            border: none;
            color: #d1d5db;
            cursor: pointer;
            border-radius: 0.375rem;
            transition: all 0.2s;
            font-size: 0.9375rem;
        }
        .tab-trigger.active {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Table */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            text-align: left;
            color: #e5e7eb;
            font-size: 0.9375rem;
        }
        .table th { color: #9ca3af; font-weight: 600; }
        .muted { color: #9ca3af; }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }

        .form-group select option {
            background-color: rgba(17, 24, 39, 0.95);
            color: #ffffff;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #9ca3af;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            background: rgba(0, 0, 0, 0.6);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Radio Group */
        .radio-group {
            display: flex;
            flex-direction: row;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .radio-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            transition: all 0.2s;
            flex: 1;
            min-width: 0;
        }

        .radio-label:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .radio-input {
            width: 1rem;
            height: 1rem;
            accent-color: #60a5fa;
            flex-shrink: 0;
        }

        .radio-label span {
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 500;
            flex: 1;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn {
            background: rgba(37, 99, 235, 0.8);
            color: #ffffff;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn:hover {
            background: rgba(37, 99, 235, 1);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        .stat-card-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #9ca3af;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            padding: 0.75rem;
            border-radius: 0.75rem;
        }

        .stat-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .stat-icon.gray {
            background: rgba(107, 114, 128, 0.2);
        }

        .stat-icon.gray svg {
            color: #9ca3af;
        }

        .stat-icon.green {
            background: rgba(34, 197, 94, 0.2);
        }

        .stat-icon.green svg {
            color: #4ade80;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                border-radius: 0 0 1rem 1rem;
                position: static;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
            }
        }

        @media (max-width: 640px) {
            .main-content {
                padding: 1rem;
            }

            .card {
                padding: 1rem;
            }

            .radio-group {
                gap: 0.5rem;
            }

            .radio-label {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="sidebar-title">ClubConnect</h1>
                <p class="sidebar-subtitle">Tableau de Bord Étudiant</p>
            </div>

            <nav class="sidebar-nav">
                <a href="home.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Tableau de Bord</span>
                </a>
                <a href="discoverevents.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>Découvrir Événements</span>
                </a>
                <a href="MyEvents.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
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
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Mes Clubs</span>
                </a>
                <a href="communication.php" class="nav-item nav-item-active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Communications</span>
                </a>
                <a href="certificats.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="7"></circle>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                    </svg>
                    <span>Certificats</span>
                </a>
            </nav>

            <div class="sidebar-profile">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <span><?php echo $user_initials; ?></span>
                    </div>
                    <div class="profile-info">
                        <p class="profile-name"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="profile-department"><?php echo htmlspecialchars($user_department); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="header-text">
                        <h2 class="header-title">Communications</h2>
                        <p class="header-subtitle">Envoyer des emails et gérer les requêtes d'adhésion</p>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="content-container">
                <div class="tabs-list">
                    <button class="tab-trigger active" onclick="switchTab('email')">Email</button>
                    <button class="tab-trigger" onclick="switchTab('requetes')">Requêtes d'Adhésion (<?php echo count($requests); ?>)</button>
                </div>

                <!-- Email Tab -->
                <div id="tab-email" class="tab-content active">
                    <form method="POST" class="card">
                        <div class="form-group">
                            <label>Envoyer à</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="recipient-type" value="all-members" class="radio-input"
                                        <?php echo (getFormValue('recipient-type') === 'all-members') ? 'checked' : ''; ?>>
                                    <span>Tous les Membres d'un Club</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="recipient-type" value="event-attendees" class="radio-input"
                                        <?php echo (getFormValue('recipient-type') === 'event-attendees') ? 'checked' : ''; ?>>
                                    <span>Participants à un Événement</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="recipient-type" value="custom" class="radio-input"
                                        <?php echo (getFormValue('recipient-type') === 'custom' || empty(getFormValue('recipient-type'))) ? 'checked' : ''; ?>>
                                    <span>Destinataires Personnalisés</span>
                                </label>
                            </div>
                        </div>

                        <!-- Club Selection (for all-members) -->
                        <div class="form-group" id="club-selection" style="display: none;">
                            <label for="selected_club">Sélectionner un Club</label>
                            <select id="selected_club" name="selected_club" class="form-group">
                                <option value="">Choisissez un club</option>
                                <?php foreach ($organizer_clubs as $club): ?>
                                    <option value="<?php echo $club['idClub']; ?>" 
                                        <?php echo (getFormValue('selected_club') == $club['idClub']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Event Selection (for event-attendees) -->
                        <div class="form-group" id="event-selection" style="display: none;">
                            <label for="selected_event">Sélectionner un Événement</label>
                            <select id="selected_event" name="selected_event" class="form-group">
                                <option value="">Choisissez un événement</option>
                                <?php foreach ($organizer_events as $event): ?>
                                    <option value="<?php echo $event['idEvenement']; ?>" 
                                        <?php echo (getFormValue('selected_event') == $event['idEvenement']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['titre'] . ' (' . $event['club_nom'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom Recipients (for custom) -->
                        <div class="form-group" id="custom-recipients">
                            <label for="recipient">À (adresses email séparées par des virgules)</label>
                            <input 
                                type="text" 
                                id="recipient" 
                                name="recipient"
                                placeholder="Entrez les adresses email (séparées par des virgules)"
                                value="<?php echo getFormValue('recipient'); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="subject">Sujet</label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject"
                                placeholder="Entrez le sujet de l'email"
                                value="<?php echo getFormValue('subject'); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea 
                                id="message" 
                                name="message"
                                placeholder="Composez votre message..."
                                required
                            ><?php echo getFormValue('message'); ?></textarea>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn">Envoyer l'Email</button>
                            <a href="home.php" class="btn btn-secondary">Retour</a>
                        </div>
                    </form>
                </div>

                <!-- Requêtes Tab -->
                <div id="tab-requetes" class="tab-content">
                    <div class="card">
                        <?php if (empty($requests)) { ?>
                            <div class="muted">Aucune requête d'adhésion en attente pour vos clubs.</div>
                        <?php } else { ?>
                            <div style="overflow-x:auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Apogee</th>
                                            <th>Email</th>
                                            <th>Club</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $rq) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rq['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($rq['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($rq['apogee']); ?></td>
                                                <td><?php echo htmlspecialchars($rq['email']); ?></td>
                                                <td><?php echo htmlspecialchars($rq['club_nom']); ?></td>
                                                <td>
                                                    <div style="display:flex; gap:8px;">
                                                        <form method="POST" onsubmit="return confirm('Approuver cette requête ?');">
                                                            <input type="hidden" name="request_action" value="approve" />
                                                            <input type="hidden" name="req_user_id" value="<?php echo (int)$rq['idUtilisateur']; ?>" />
                                                            <input type="hidden" name="req_club_id" value="<?php echo (int)$rq['idClub']; ?>" />
                                                            <button type="submit" class="btn btn-sm">Approuver</button>
                                                        </form>
                                                        <form method="POST" onsubmit="return confirm('Rejeter cette requête ?');">
                                                            <input type="hidden" name="request_action" value="reject" />
                                                            <input type="hidden" name="req_user_id" value="<?php echo (int)$rq['idUtilisateur']; ?>" />
                                                            <input type="hidden" name="req_club_id" value="<?php echo (int)$rq['idClub']; ?>" />
                                                            <button type="submit" class="btn btn-secondary btn-sm">Rejeter</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-trigger').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const recipientInput = document.getElementById('recipient');
            const clubSelection = document.getElementById('club-selection');
            const eventSelection = document.getElementById('event-selection');
            const customRecipients = document.getElementById('custom-recipients');
            const radioButtons = document.querySelectorAll('input[name="recipient-type"]');
            
            function toggleFormFields() {
                const selectedRadio = document.querySelector('input[name="recipient-type"]:checked');
                
                // Hide all conditional fields
                clubSelection.style.display = 'none';
                eventSelection.style.display = 'none';
                customRecipients.style.display = 'none';
                
                // Show relevant field based on selection
                if (selectedRadio) {
                    switch (selectedRadio.value) {
                        case 'all-members':
                            clubSelection.style.display = 'block';
                            break;
                        case 'event-attendees':
                            eventSelection.style.display = 'block';
                            break;
                        case 'custom':
                            customRecipients.style.display = 'block';
                            break;
                    }
                }
            }
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', toggleFormFields);
            });
            
            // Initialize form fields on page load
            toggleFormFields();
        });
    </script>
</body>
</html>