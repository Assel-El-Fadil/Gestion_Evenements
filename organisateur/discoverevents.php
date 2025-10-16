<?php
 
session_start();
require_once '../database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["user_id"])) {
    header("Location: ../signin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$conn = db_connect();

// Récupérer les informations de l'utilisateur
$user_sql = "SELECT nom, prenom, annee, filiere FROM Utilisateur WHERE idUtilisateur = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../signin.php");
    exit();
}

$user_name = $user['prenom'] . ' ' . $user['nom'];
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$user_department = $user['annee'] . ' - ' . $user['filiere'];

$stats = [];
$search_query = trim($_GET['q'] ?? '');

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'register' && $user_id) {
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            // Check if already registered
            $stmt = $conn->prepare('SELECT 1 FROM Inscription WHERE idUtilisateur = ? AND idEvenement = ?');
            $stmt->bind_param('ii', $user_id, $event_id);
            $stmt->execute();
            $already = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($already) {
                $error_message = "Vous êtes déjà inscrit à cet événement.";
            } else {
                // Get capacity and participants
                $stmt = $conn->prepare('SELECT capacité, nbrParticipants FROM Evenement WHERE idEvenement = ?');
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $evt = $res->fetch_assoc();
                $stmt->close();

                if (!$evt) {
                    $error_message = "Événement introuvable.";
                } else if (intval($evt['nbrParticipants']) >= intval($evt['capacité'])) {
                    $error_message = "La capacité de l'événement a été atteinte.";
                } else {
                    // Register and increment
                    $stmt = $conn->prepare('INSERT INTO Inscription (idUtilisateur, idEvenement) VALUES (?, ?)');
                    $stmt->bind_param('ii', $user_id, $event_id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        $up = $conn->prepare('UPDATE Evenement SET nbrParticipants = nbrParticipants + 1 WHERE idEvenement = ?');
                        $up->bind_param('i', $event_id);
                        $up->execute();
                        $up->close();
                        $success_message = "Inscription réussie.";
                    } else {
                        $error_message = "Erreur lors de l'inscription.";
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$events_month_sql = "SELECT COUNT(*) as count FROM Evenement 
                    WHERE MONTH(dateEvenement) = MONTH(CURRENT_DATE()) 
                    AND YEAR(dateEvenement) = YEAR(CURRENT_DATE())";
$result = $conn->query($events_month_sql);
$stats['events_this_month'] = $result->fetch_assoc()['count'];

$active_clubs_sql = "SELECT COUNT(DISTINCT idClub) as count FROM Evenement";
$result = $conn->query($active_clubs_sql);
$stats['active_clubs'] = $result->fetch_assoc()['count'];

$categories_sql = "SELECT COUNT(DISTINCT titre) as count FROM Evenement";
$result = $conn->query($categories_sql);
$stats['categories'] = $result->fetch_assoc()['count'];

$events_sql = "SELECT e.*, c.nom as club_nom 
               FROM Evenement e 
               JOIN Club c ON e.idClub = c.idClub 
               WHERE e.dateEvenement >= CURDATE()";
if ($search_query !== '') {
    $events_sql .= " AND (e.titre LIKE ? OR c.nom LIKE ? OR e.lieu LIKE ?)";
}
$events_sql .= " ORDER BY e.dateEvenement ASC";

if ($search_query !== '') {
    $like = "%" . $search_query . "%";
    $stmtEv = $conn->prepare($events_sql);
    $stmtEv->bind_param('sss', $like, $like, $like);
    $stmtEv->execute();
    $events_result = $stmtEv->get_result();
} else {
    $events_result = $conn->query($events_sql);
}
$events_count = $events_result ? $events_result->num_rows : 0;

// Preload user's registered events to disable the button
$user_event_ids = [];
if ($user_id) {
    $reg = $conn->prepare('SELECT idEvenement FROM Inscription WHERE idUtilisateur = ?');
    $reg->bind_param('i', $user_id);
    $reg->execute();
    $rres = $reg->get_result();
    while ($row = $rres->fetch_assoc()) { $user_event_ids[intval($row['idEvenement'])] = true; }
    $reg->close();
}

db_close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Découvrir les Événements</title>
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

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background-color: #0a0b0f;
            color: #ffffff;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 256px;
            background: linear-gradient(to bottom, #0f0f17, #0a0a0f);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            margin-bottom: 48px;
        }

        .sidebar-header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 500;
        }

        .sidebar-header p {
            color: #9ca3af;
            margin-top: 4px;
            font-size: 14px;
        }

        .sidebar-nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: #d1d5db;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .nav-item svg {
            width: 20px;
            height: 20px;
        }

        .user-profile {
            margin-top: auto;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .user-profile-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #9333ea);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            color: #ffffff;
            font-size: 16px;
        }

        .user-info h3 {
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
        }

        .user-info p {
            color: #9ca3af;
            font-size: 12px;
        }

        .main-content {
            flex: 1;
            overflow: auto;
        }

        .content-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .header-left {
            flex: 1;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .header-title svg {
            width: 32px;
            height: 32px;
            color: #3b82f6;
        }

        .header-title h1 {
            font-size: 32px;
            font-weight: 500;
            line-height: 1.5;
            color: #ffffff;
        }

        .header-subtitle {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        .search-container {
            position: relative;
            width: 384px;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #9ca3af;
        }

        .search-input {
            width: 100%;
            background-color: #13141a;
            border: 1px solid #1f2029;
            border-radius: 8px;
            padding: 12px 16px 12px 48px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            outline: none;
        }

        .search-input:focus {
            border-color: #3b82f6;
        }

        .search-input::placeholder {
            color: #6b7280;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background-color: #13141a;
            border: 1px solid #1f2029;
            border-radius: 16px;
            padding: 24px;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue {
            background-color: rgba(37, 99, 235, 0.2);
        }

        .stat-icon.purple {
            background-color: rgba(147, 51, 234, 0.2);
        }

        .stat-icon.green {
            background-color: rgba(34, 197, 94, 0.2);
        }

        .stat-icon svg {
            width: 20px;
            height: 20px;
        }

        .stat-icon.blue svg {
            color: #3b82f6;
        }

        .stat-icon.purple svg {
            color: #a855f7;
        }

        .stat-icon.green svg {
            color: #22c55e;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 400;
            line-height: 1.5;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #6b7280;
        }

        .category-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            overflow-x: auto;
            padding-bottom: 8px;
        }

        .category-btn {
            padding: 8px 20px;
            border-radius: 8px;
            white-space: nowrap;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
        }

        .category-btn.active {
            background-color: #2563eb;
            color: #ffffff;
        }

        .category-btn:not(.active) {
            background-color: #13141a;
            border: 1px solid #1f2029;
            color: #9ca3af;
        }

        .category-btn:not(.active):hover {
            border-color: #3b82f6;
            color: #ffffff;
        }

        .events-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .events-header h2 {
            font-size: 24px;
            font-weight: 500;
            line-height: 1.5;
            color: #ffffff;
        }

        .events-count {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .event-card {
            background-color: #13141a;
            border: 1px solid #1f2029;
            border-radius: 16px;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .event-card:hover {
            border-color: rgba(59, 130, 246, 0.5);
        }

        .event-image {
            position: relative;
            height: 192px;
            overflow: hidden;
        }

        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .event-card:hover .event-image img {
            transform: scale(1.05);
        }

        .event-category {
            position: absolute;
            top: 16px;
            right: 16px;
            background-color: #2563eb;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
        }

        .event-content {
            padding: 24px;
        }

        .event-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-size: 18px;
            font-weight: 500;
            line-height: 1.5;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .event-club {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        .event-details {
            margin-bottom: 16px;
        }

        .event-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9ca3af;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .event-detail svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .event-detail-separator {
            color: #4b5563;
        }

        .attendance-poll {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 12px 16px;
            background-color: #1a1b23;
            border-radius: 8px;
            border: 1px solid #2a2b35;
        }

        .attendance-count {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attendance-icon {
            width: 20px;
            height: 20px;
            color: #9ca3af;
        }

        .attendance-text {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        .attendance-number {
            font-weight: 500;
            color: #ffffff;
        }

        .event-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
        }

        .btn-primary {
            flex: 1;
            background-color: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            padding: 10px 16px;
            background-color: #1f2029;
            color: #d1d5db;
        }

        .btn-secondary:hover {
            background-color: #2a2b35;
        }

        @media (max-width: 1024px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .search-container {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }
        /* Canonical Sidebar Overrides */
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

        .sidebar-header { margin-bottom: 2rem; }
        .sidebar-title { font-size: 1.5rem; font-weight: 700; color: #ffffff; line-height: 1.5; margin-bottom: 0.25rem; }
        .sidebar-subtitle { font-size: 0.875rem; color: #9ca3af; font-weight: 400; line-height: 1.5; }

        .sidebar-nav { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item { display: flex; align-items: center; width: 100%; padding: 0.5rem 1rem; border-radius: 0.375rem; text-decoration: none; color: #d1d5db; font-size: 1rem; font-weight: 500; line-height: 1.5; transition: all 0.2s; border: 1px solid transparent; gap: 0; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
        .nav-item-active { background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); }
        .nav-icon { width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; flex-shrink: 0; }

        .sidebar-profile { margin-top: auto; }
        .profile-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.75rem; padding: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .profile-avatar { width: 2.5rem; height: 2.5rem; background: linear-gradient(to right, #3b82f6, #9333ea); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .profile-avatar span { color: #ffffff; font-weight: 600; font-size: 1rem; }
        .profile-info { flex: 1; }
        .profile-name { color: #ffffff; font-weight: 500; font-size: 1rem; line-height: 1.5; }
        .profile-department { color: #9ca3af; font-size: 0.875rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="container">
        <div class="sidebar">
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
                <a href="discoverevents.php" class="nav-item nav-item-active">
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
                <a href="communication.php" class="nav-item">
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
        </div>

        <div class="main-content">
            <div class="content-wrapper">
                <div class="header">
                    <div class="header-left">
                        <div class="header-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <h1>Découvrir les Événements</h1>
                        </div>
                        <p class="header-subtitle">Trouvez et rejoignez des événements passionnants sur votre campus</p>
                    </div>
                    <div class="search-container">
                        <form method="GET" style="position:relative;">
                            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" name="q" class="search-input" placeholder="Rechercher des événements, clubs..." value="<?= htmlspecialchars($search_query) ?>">
                        </form>
                    </div>
                </div>
                <?php if (!empty($success_message)): ?>
                    <div style="margin-bottom:16px; padding:12px 16px; border-radius:8px; border:1px solid rgba(34,197,94,0.3); background: rgba(34,197,94,0.12); color:#86efac;">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div style="margin-bottom:16px; padding:12px 16px; border-radius:8px; border:1px solid rgba(239,68,68,0.3); background: rgba(239,68,68,0.12); color:#fca5a5;">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Événements Disponibles</h3>
                            <div class="stat-icon blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="stat-value"><?= $stats['events_this_month'] ?></p>
                        <p class="stat-label">Ce mois-ci</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Clubs Actifs</h3>
                            <div class="stat-icon purple">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="stat-value"><?= $stats['active_clubs'] ?></p>
                        <p class="stat-label">Organisent des événements</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Types d'Événements</h3>
                            <div class="stat-icon green">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="stat-value"><?= $stats['categories'] ?></p>
                        <p class="stat-label">Événements différents</p>
                    </div>
                </div>

                <div class="category-filters">
                    <button class="category-btn active">Tous les Événements</button>
                    <button class="category-btn">Atelier</button>
                    <button class="category-btn">Sports</button>
                    <button class="category-btn">Exposition</button>
                    <button class="category-btn">Concert</button>
                    <button class="category-btn">Social</button>
                </div>

                <div>
                    <div class="events-header">
                        <h2>Tous les Événements</h2>
                        <p class="events-count"><?= $events_count ?> événements trouvés</p>
                    </div>

                    <div class="events-grid">
                        <?php if ($events_result->num_rows > 0): ?>
                            <?php 
                            $events_result->data_seek(0);
                            while($event = $events_result->fetch_assoc()): 
                            ?>
                                <?php
                                $category = "Événement";
                                $title_lower = strtolower($event['titre']);
                                
                                if (strpos($title_lower, 'formation') !== false || strpos($title_lower, 'git') !== false || strpos($title_lower, 'web') !== false || strpos($title_lower, 'tech') !== false) {
                                    $category = "Technology";
                                } elseif (strpos($title_lower, 'cinéma') !== false || strpos($title_lower, 'cinema') !== false || strpos($title_lower, 'film') !== false) {
                                    $category = "Social";
                                } elseif (strpos($title_lower, 'sport') !== false || strpos($title_lower, 'basket') !== false) {
                                    $category = "Sports";
                                } else {
                                    $category = "Événement";
                                }
                                
                                $image_url = "../" . $event['photo'];
                                $title_lower = strtolower($event['titre']);
                                
                                if (strpos($title_lower, 'formationgit') !== false || strpos($title_lower, 'formation git') !== false || strpos($title_lower, 'git') !== false) {
                                    $image_url = "images/Git-vs-GitHub.png";
                                } elseif (strpos($title_lower, 'cinéma') !== false || strpos($title_lower, 'cinema') !== false || strpos($title_lower, 'film') !== false) {
                                    $image_url = "images/cinema.jpg";
                                } elseif (!empty($event['photo'])) {
                                    $photo_url = trim($event['photo']);
                                    if (filter_var($photo_url, FILTER_VALIDATE_URL)) {
                                        $image_url = $photo_url;
                                    }
                                }
                                
                                $date = date('M j, Y', strtotime($event['date']));
                                
                                $nbrParticipants = intval($event['nbrParticipants'] ?? 0);
                                $capacite = intval($event['capacité'] ?? 0);
                                ?>
                                
                                <div class="event-card">
                                    <div class="event-image">
                                        <img src="<?= htmlspecialchars($image_url) ?>" alt="<?= htmlspecialchars($event['titre']) ?>" onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=MnwxfDB8MXxyYW5kb218MHx8ZXZlbnR8fHx8fHwxNzI4NjU2ODAw&ixlib=rb-4.0.3&q=80&w=1080'">
                                        <div class="event-category"><?= $category ?></div>
                                    </div>
                                    <div class="event-content">
                                        <div class="event-header">
                                            <div class="event-info">
                                                <h3 class="event-title"><?= htmlspecialchars($event['titre']) ?></h3>
                                                <p class="event-club"><?= htmlspecialchars($event['club_nom']) ?></p>
                                            </div>
                                        </div>
                                        <div class="event-details">
                                            <div class="event-detail">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <span><?= $date ?></span>
                                            </div>
                                            <div class="event-detail">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span><?= htmlspecialchars($event['lieu']) ?></span>
                                            </div>
                                        </div>
                                        <div class="attendance-poll">
                                            <div class="attendance-count">
                                                <svg class="attendance-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                                <span class="attendance-text">
                                                    <span class="attendance-number"><?= $nbrParticipants ?></span> 
                                                    / <?= $capacite ?> participants
                                                </span>
                                            </div>
                                        </div>
                                        <div class="event-actions">
                                            <form method="POST" style="flex:1; display:flex; gap:12px;">
                                                <input type="hidden" name="action" value="register">
                                                <input type="hidden" name="event_id" value="<?= intval($event['idEvenement']) ?>">
                                                <?php $isRegistered = isset($user_event_ids[intval($event['idEvenement'])]); ?>
                                                <?php $isFull = $nbrParticipants >= $capacite && $capacite > 0; ?>
                                                <button type="submit" class="btn btn-primary" <?= ($isRegistered || $isFull) ? 'disabled style="opacity:.6; cursor:not-allowed;"' : '' ?>>
                                                    <?= $isRegistered ? "Inscrit" : ( $isFull ? "Complet" : "S'inscrire") ?>
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="showEventDetails(<?= intval($event['idEvenement']) ?>)">Voir Détails</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #9ca3af;">
                                Aucun événement trouvé.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<script>
async function showEventDetails(id) {
    try {
        toggleModal(true, '<div style="color:#9ca3af;">Chargement...</div>');
        const response = await fetch('MyEvents.php?event_id=' + id);
        const event = await response.json();
        if (event.error) {
            throw new Error(event.error);
        }
        const dateObj = new Date(event.date_evenement);
        const formattedDate = dateObj.toLocaleDateString('fr-FR', { month:'short', day:'numeric', year:'numeric' });
        const html = `
            <div style="display:grid; gap:12px;">
                <div><span class="muted">Club</span><div>${event.club_nom || ''}</div></div>
                <div style="display:flex; gap:16px;">
                    <div><span class="muted">Date</span><div>${formattedDate}</div></div>
                    <div><span class="muted">Statut</span><div>${event.statut}</div></div>
                </div>
                <div><span class="muted">Lieu</span><div>${event.lieu || 'À déterminer'}</div></div>
                <div><span class="muted">Description</span><div>${event.description || ''}</div></div>
                <div style="display:flex; gap:16px;">
                    <div><span class="muted">Participants</span><div>${event.participants_inscrits || 0}</div></div>
                    <div><span class="muted">Capacité</span><div>${event.capacite_max || 0}</div></div>
                </div>
            </div>
        `;
        setModalContent(event.nom, html);
    } catch (e) {
        setModalContent('Erreur', '<div style="color:#ef4444;">Échec du chargement de l\'événement: ' + e.message + '</div>');
    }
}

function toggleModal(show, placeholderHtml) {
    const backdrop = document.getElementById('eventModal');
    if (!backdrop) return;
    if (typeof placeholderHtml === 'string') {
        const bodyEl = document.getElementById('eventModalBody');
        if (bodyEl) bodyEl.innerHTML = placeholderHtml;
    }
    backdrop.style.display = show ? 'flex' : 'none';
}

function setModalContent(title, html) {
    const titleEl = document.getElementById('eventModalTitle');
    const bodyEl = document.getElementById('eventModalBody');
    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.innerHTML = html;
    toggleModal(true);
}
</script>

<style>
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:50; }
.modal { width: 90%; max-width: 800px; background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:12px; backdrop-filter: blur(20px); overflow:hidden; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.12); }
.modal-title { font-size:18px; font-weight:600; }
.modal-close { background: transparent; border: 1px solid rgba(255,255,255,0.2); color:#d1d5db; border-radius:8px; padding:6px 10px; cursor:pointer; }
.modal-body { padding: 20px; }
.muted { color:#9ca3af; font-size:12px; }
</style>

<div id="eventModal" class="modal-backdrop" onclick="if(event.target===this) toggleModal(false)">
    <div class="modal">
        <div class="modal-header">
            <div id="eventModalTitle" class="modal-title">Détails de l'Événement</div>
            <button class="modal-close" onclick="toggleModal(false)">Fermer</button>
        </div>
        <div id="eventModalBody" class="modal-body"></div>
    </div>
</div>