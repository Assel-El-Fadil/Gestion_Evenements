<?php
session_start();
require_once '../database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["user_id"])) {
    header("Location: ../signin.php");
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 1;
$search_query = trim($_GET['q'] ?? '');




$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$conn = db_connect();
$user_sql = "SELECT nom, prenom, annee, filiere FROM Utilisateur WHERE idUtilisateur = ?";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("i", $user_id);
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

// Fetch events from database
try {
    $conn = db_connect();
    
    // Counters for events where the user is registered
    $total_sql = "SELECT COUNT(*) AS total
                  FROM Inscription i
                  JOIN Evenement e ON e.idEvenement = i.idEvenement
                  WHERE i.idUtilisateur = ?";
    $stmt_cnt = $conn->prepare($total_sql);
    $stmt_cnt->bind_param('i', $user_id);
    $stmt_cnt->execute();
    $total_res = $stmt_cnt->get_result();
    $total_row = $total_res ? $total_res->fetch_assoc() : ['total' => 0];
    $total_count = (int)($total_row['total'] ?? 0);
    $stmt_cnt->close();

    $upcoming_sql = "SELECT COUNT(*) AS cnt
                     FROM Inscription i
                     JOIN Evenement e ON e.idEvenement = i.idEvenement
                     WHERE i.idUtilisateur = ? AND e.dateEvenement >= CURDATE()";
    $stmt_up = $conn->prepare($upcoming_sql);
    $stmt_up->bind_param('i', $user_id);
    $stmt_up->execute();
    $upcoming_res = $stmt_up->get_result();
    $upcoming_row = $upcoming_res ? $upcoming_res->fetch_assoc() : ['cnt' => 0];
    $upcoming_count = (int)($upcoming_row['cnt'] ?? 0);
    $stmt_up->close();

    $past_sql = "SELECT COUNT(*) AS cnt
                 FROM Inscription i
                 JOIN Evenement e ON e.idEvenement = i.idEvenement
                 WHERE i.idUtilisateur = ? AND e.dateEvenement < CURDATE()";
    $stmt_ps = $conn->prepare($past_sql);
    $stmt_ps->bind_param('i', $user_id);
    $stmt_ps->execute();
    $past_res = $stmt_ps->get_result();
    $past_row = $past_res ? $past_res->fetch_assoc() : ['cnt' => 0];
    $past_count = (int)($past_row['cnt'] ?? 0);
    $stmt_ps->close();

    // Load only events the user is registered for
    $sql = "SELECT e.*, c.nom as club_nom
            FROM Inscription i
            JOIN Evenement e ON e.idEvenement = i.idEvenement
            LEFT JOIN Club c ON e.idClub = c.idClub
            WHERE i.idUtilisateur = ?";
    if ($search_query !== '') {
        $sql .= " AND (e.titre LIKE ? OR c.nom LIKE ? OR e.lieu LIKE ?)";
    }
    $sql .= " ORDER BY e.dateEvenement ASC";
    
    $stmt = $conn->prepare($sql);
    if ($search_query !== '') {
        $like = "%" . $search_query . "%";
        $stmt->bind_param('isss', $user_id, $like, $like, $like);
    } else {
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($event = $result->fetch_assoc()) {
        $event_date = $event['date'];
        $event['is_upcoming'] = $event_date >= date('Y-m-d');
        $event['statut'] = $event['is_upcoming'] ? 'upcoming' : 'past';
        $event['id'] = $event['idEvenement'];
        $event['nom'] = $event['titre'];
        $event['date_evenement'] = $event['date'];
        $event['participants_inscrits'] = $event['nbrParticipants'];
        $event['capacite_max'] = $event['capacité'];
        $event['club_id'] = $event['idClub'];
        unset($event['idEvenement'], $event['titre'], $event['date'], $event['nbrParticipants'], $event['capacité'], $event['idClub']);
        $events[] = $event;
    }
    
    // Compteurs pour l'utilisateur connecté
    $total_count = count($events);
    $upcoming_count = count(array_filter($events, function($e) { return $e['is_upcoming']; }));
    $past_count = $total_count - $upcoming_count;
    
    $stmt->close();
    db_close();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $events = [];
    $total_count = $upcoming_count = $past_count = 0;
}

// Function to get event details by ID
function getEventDetails($event_id) {
    try {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT e.*, c.nom as club_nom FROM Evenement e LEFT JOIN Club c ON e.idClub = c.idClub WHERE e.idEvenement = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($event = $result->fetch_assoc()) {
            $event_date = $event['date'];
            $event['is_upcoming'] = $event_date >= date('Y-m-d');
            $event['statut'] = $event['is_upcoming'] ? 'upcoming' : 'past';
            $event['id'] = $event['idEvenement'];
            $event['nom'] = $event['titre'];
            $event['date_evenement'] = $event['date'];
            $event['participants_inscrits'] = $event['nbrParticipants'];
            $event['capacite_max'] = $event['capacité'];
            $event['club_id'] = $event['idClub'];
            unset($event['idEvenement'], $event['titre'], $event['date'], $event['nbrParticipants'], $event['capacité'], $event['idClub']);
        }
        
        $stmt->close();
        db_close();
        return $event;
        
    } catch (Exception $e) {
        return null;
    }
}

// Handle event details request
if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $event_details = getEventDetails($event_id);
    
    if ($event_details) {
        echo json_encode($event_details);
    } else {
        echo json_encode(['error' => 'Event not found']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - My Events</title>
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #000000;
            color: #ffffff;
            overflow: hidden;
        }

        .app-container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar Styles */
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
            overflow-y: auto;
        }

        .header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            top: 0;
            z-index: 10;
        }

        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 32px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .header-title h1 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .header-title p {
            color: #d1d5db;
            font-size: 15px;
        }

        .header-actions {
            display: flex;
            gap: 16px;
        }

        .search-wrapper {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #9ca3af;
        }

        .search-input {
            width: 320px;
            padding: 8px 16px 8px 40px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #ffffff;
            font-size: 15px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .notification-btn {
            position: relative;
            padding: 8px;
            background: transparent;
            border: none;
            color: #d1d5db;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            padding: 24px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.2s;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #d1d5db;
            font-size: 15px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .stat-icon.blue {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .stat-icon.purple {
            background: rgba(147, 51, 234, 0.2);
            border: 1px solid rgba(147, 51, 234, 0.3);
            color: #a78bfa;
        }

        .stat-icon.green {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-meta {
            font-size: 14px;
            color: #9ca3af;
        }

        .stat-meta.success {
            color: #4ade80;
        }

        /* Content Area */
        .content-area {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px;
        }

        /* Tabs */
        .tabs-list {
            display: flex;
            gap: 4px;
            padding: 4px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .tab-trigger {
            padding: 8px 16px;
            background: transparent;
            border: none;
            color: #d1d5db;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 15px;
        }

        .tab-trigger:hover {
            color: #ffffff;
        }

        .tab-trigger.active {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Event Card */
        .event-card {
            padding: 24px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.2s;
        }

        .event-card:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 25px -5px rgba(255, 255, 255, 0.05);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .event-info h3 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .event-club {
            font-size: 14px;
            color: #d1d5db;
        }

        .event-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid;
        }

        .event-badge.workshop {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.2);
        }

        .event-badge.competition {
            background: rgba(147, 51, 234, 0.1);
            color: #a78bfa;
            border-color: rgba(147, 51, 234, 0.2);
        }

        .event-badge.seminar {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.2);
        }

        .event-badge.social {
            background: rgba(251, 146, 60, 0.1);
            color: #fb923c;
            border-color: rgba(251, 146, 60, 0.2);
        }

        .event-badge.conference {
            background: rgba(236, 72, 153, 0.1);
            color: #f472b6;
            border-color: rgba(236, 72, 153, 0.2);
        }

        .event-badge.career {
            background: rgba(14, 165, 233, 0.1);
            color: #38bdf8;
            border-color: rgba(14, 165, 233, 0.2);
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #d1d5db;
        }

        .event-detail-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .event-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            flex: 1;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            background: #e5e7eb;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            padding: 8px 16px;
            background: transparent;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #d1d5db;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="app-container">
        <!-- Sidebar -->
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
                <a href="MyEvents.php" class="nav-item nav-item-active">
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
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="header-top">
                        <div class="header-title">
                            <h1>Mes Événements</h1>
                            <p>Gérez et suivez vos inscriptions aux événements</p>
                        </div>
                        <div class="header-actions">
                            <div class="search-wrapper">
                                <form method="GET" class="search-wrapper">
                                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" name="q" class="search-input" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Rechercher des événements, clubs...">
                                </form>
                            </div>
                            <button class="notification-btn">
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <span class="notification-badge"></span>
                            </button>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <p class="stat-label">Total Inscrits</p>
                                <div class="stat-icon blue">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="stat-value"><?php echo $total_count; ?></p>
                            <p class="stat-meta">Tous les temps</p>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <p class="stat-label">Événements à Venir</p>
                                <div class="stat-icon purple">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="stat-value"><?php echo $upcoming_count; ?></p>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <p class="stat-label">Participés</p>
                                <div class="stat-icon green">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="stat-value"><?php echo $past_count; ?></p>
                            <p class="stat-meta">Événements terminés</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <div class="tabs-list">
                    <button class="tab-trigger active" onclick="switchTab('upcoming')">À Venir (<?php echo $upcoming_count; ?>)</button>
                    <button class="tab-trigger" onclick="switchTab('past')">Passés (<?php echo $past_count; ?>)</button>
                    <button class="tab-trigger" onclick="switchTab('all')">Tous (<?php echo $total_count; ?>)</button>
                </div>

                <!-- Upcoming Tab -->
                <div id="tab-upcoming" class="tab-content active">
                    <div class="events-grid">
                        <?php
                        $upcoming_events = array_filter($events, function($event) {
                            return isset($event['is_upcoming']) && $event['is_upcoming'];
                        });
                        
                        if (empty($upcoming_events)) {
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">Aucun événement à venir trouvé</p>';
                        } else {
                            foreach ($upcoming_events as $event) {
                                echo createEventCardHTML($event);
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Past Tab -->
                <div id="tab-past" class="tab-content">
                    <div class="events-grid">
                        <?php
                        $past_events = array_filter($events, function($event) {
                            return isset($event['is_upcoming']) && !$event['is_upcoming'];
                        });
                        
                        if (empty($past_events)) {
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">Aucun événement passé trouvé</p>';
                        } else {
                            foreach ($past_events as $event) {
                                echo createEventCardHTML($event);
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- All Tab -->
                <div id="tab-all" class="tab-content">
                    <div class="events-grid">
                        <?php
                        if (empty($events)) {
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">Aucun événement trouvé</p>';
                        } else {
                            foreach ($events as $event) {
                                echo createEventCardHTML($event);
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            const tabContents = document.querySelectorAll('.tab-content');
            tabTriggers.forEach(trigger => trigger.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }

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
    <!-- Event Details Modal -->
    <div id="eventModal" class="modal-backdrop" onclick="if(event.target===this) toggleModal(false)">
        <div class="modal">
            <div class="modal-header">
                <div id="eventModalTitle" class="modal-title">Détails de l'Événement</div>
                <button class="modal-close" onclick="toggleModal(false)">Fermer</button>
            </div>
            <div id="eventModalBody" class="modal-body"></div>
        </div>
    </div>
</body>
</html>

<?php
function createEventCardHTML($event) {
    $formattedDate = '';
    try {
        $dateObj = new DateTime($event['date_evenement']);
        $formattedDate = $dateObj->format('M j, Y');
    } catch (Exception $ex) {
        $formattedDate = htmlspecialchars($event['date_evenement'] ?? '');
    }
    $formattedTime = '2:00 PM'; // You can extract this from your database if available
    
    $badge_class = $event['statut'] === 'upcoming' ? 'workshop' : 'seminar';
    
    return '
        <div class="event-card" onclick="showEventDetails(' . $event['id'] . ')">
            <div class="event-header">
                <div class="event-info">
                    <h3>' . htmlspecialchars($event['nom']) . '</h3>
                    <p class="event-club">' . htmlspecialchars($event['club_nom'] ?? '') . '</p>
                </div>
                <span class="event-badge ' . $badge_class . '">' . ($event['statut'] === 'upcoming' ? 'Upcoming' : 'Completed') . '</span>
            </div>
            <div class="event-details">
                <div class="event-detail-item">
                    <svg class="event-detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>' . $formattedDate . '</span>
                </div>
                <div class="event-detail-item">
                    <svg class="event-detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>' . $formattedTime . '</span>
                </div>
                <div class="event-detail-item">
                    <svg class="event-detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>' . htmlspecialchars($event['lieu'] ?? 'TBA') . '</span>
                </div>
                <div class="event-detail-item">
                    <svg class="event-detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>' . ($event['participants_inscrits'] ?? 0) . ' attending</span>
                </div>
            </div>
        </div>
    ';
}
?>