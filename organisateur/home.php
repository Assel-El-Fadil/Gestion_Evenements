<?php
session_start();
require "../database.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["user_id"])) {
    header("Location: ../signin.php");
    exit();
}

$current_user_id = $_SESSION["user_id"];
$upcoming_events = get_upcoming_events(5);
$user_clubs = get_user_clubs($current_user_id, 2);

// Récupérer les informations complètes de l'utilisateur
$conn = db_connect();
$user_sql = "SELECT nom, prenom, annee, filiere FROM Utilisateur WHERE idUtilisateur = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$user_name = $user ? $user['prenom'] . ' ' . $user['nom'] : 'Utilisateur';
$user_initials = $user ? strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) : 'U';
$user_department = $user ? $user['annee'] . ' - ' . $user['filiere'] : 'Étudiant';
?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Tableau de Bord</title>
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-box {
            position: relative;
        }

        .search-icon {
            width: 1.25rem;
            height: 1.25rem;
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .search-input {
            padding-left: 2.5rem;
            width: 20rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.375rem;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            padding-right: 0.75rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .notification-btn {
            position: relative;
            width: 2.5rem;
            height: 2.5rem;
            background: transparent;
            border: none;
            border-radius: 0.375rem;
            color: #d1d5db;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .notification-btn svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .notification-dot {
            position: absolute;
            top: -0.25rem;
            right: -0.25rem;
            width: 0.75rem;
            height: 0.75rem;
            background: #ef4444;
            border-radius: 50%;
        }

        /* Content Grid */
        .content-container {
            max-width: 80rem;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #d1d5db;
            line-height: 1.5;
        }

        .stat-icon {
            padding: 0.5rem;
            border-radius: 0.5rem;
        }

        .stat-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .stat-icon-blue {
            background: linear-gradient(to right, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
        }

        .stat-icon-blue svg {
            color: #60a5fa;
        }

        .stat-icon-green {
            background: linear-gradient(to right, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.2));
        }

        .stat-icon-green svg {
            color: #4ade80;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.5;
        }

        .stat-change {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.25rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-icon {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.5rem;
            color: #ffffff;
        }

        .section-title {
            color: #ffffff;
            font-size: 1.125rem;
            font-weight: 500;
            line-height: 1.5;
        }

        /* Event and Club Items */
        .events-list, .clubs-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .event-item, .club-item {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .event-header, .club-header-content {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .event-title, .club-name {
            font-weight: 600;
            color: #ffffff;
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 0.25rem;
        }

        .event-club, .club-role {
            font-size: 0.875rem;
            color: #9ca3af;
            font-weight: 400;
            line-height: 1.5;
        }

        .event-details {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #9ca3af;
            margin-bottom: 0.75rem;
        }

        .event-detail {
            display: flex;
            align-items: center;
        }

        .event-detail svg {
            width: 1rem;
            height: 1rem;
            margin-right: 0.25rem;
        }

        .event-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .event-attendees {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #9ca3af;
        }

        .event-attendees svg {
            width: 1rem;
            height: 1rem;
            margin-right: 0.25rem;
        }

        .club-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #9ca3af;
            margin-bottom: 0.75rem;
        }

        .club-stats-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .club-stat {
            display: flex;
            align-items: center;
        }

        .club-stat svg {
            width: 1rem;
            height: 1rem;
            margin-right: 0.25rem;
        }

        .club-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Badge System */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .badge-blue {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .badge-purple {
            background: rgba(168, 85, 247, 0.2);
            color: #c4b5fd;
        }

        .badge-green {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .badge-yellow {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
        }

        .badge-indigo {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }

        /* Button System */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-tertiary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }

        .btn-tertiary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-full {
            width: 100%;
        }

        .btn-icon {
            width: 2rem;
            height: 2rem;
            padding: 0.25rem;
            background: transparent;
            border: none;
            border-radius: 0.25rem;
            color: #9ca3af;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .btn-icon svg {
            width: 1rem;
            height: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .event-details {
                flex-wrap: wrap;
            }

            .club-stats-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <p class="sidebar-subtitle">Tableau de Bord Organisateur</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item nav-item-active">
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
        
        <main class="main-content">
            <header class="header">
                <div class="header-content">
                    <div class="header-text">
                        <h2 class="header-title">Bon retour, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?> !</h2>
                        <p class="header-subtitle">Voici ce qui se passe dans vos clubs aujourd'hui</p>
                    </div>
                    
                    <div class="header-actions">
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" placeholder="Rechercher événements, clubs..." class="search-input">
                        </div> 
                    </div>
                </div>
            </header>
            
            <div class="content-container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Événements à Venir</span>
                            <div class="stat-icon stat-icon-blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($upcoming_events); ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Clubs Rejoints</span>
                            <div class="stat-icon stat-icon-green">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($user_clubs); ?></div>
                            <p class="stat-change">Vos clubs actifs</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-grid">
                    <div class="events-section">
                        <div class="section-card">
                            <div class="section-header">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <h3 class="section-title">Événements à Venir</h3>
                            </div>
                            
                            <div class="events-list">
                                <?php if (count($upcoming_events) > 0): ?>
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <div class="event-item">
                                            <div class="event-header">
                                                <div>
                                                    <h4 class="event-title"><?php echo htmlspecialchars($event['titre']); ?></h4>
                                                    <p class="event-club"><?php echo htmlspecialchars($event['club_nom']); ?></p>
                                                </div>
                                                <span class="badge badge-blue">Événement</span>
                                            </div>
                                            
                                            <div class="event-details">
                                                <div class="event-detail">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    </svg>
                                                    <span><?php echo date('j M Y', strtotime($event['date'])); ?></span>
                                                </div>
                                                <div class="event-detail">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                    <span><?php echo date('H:i', strtotime($event['date'])); ?></span>
                                                </div>
                                                <div class="event-detail">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                        <circle cx="12" cy="10" r="3"></circle>
                                                    </svg>
                                                    <span><?php echo htmlspecialchars($event['lieu']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="event-footer">
                                                <div class="event-attendees">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="9" cy="7" r="4"></circle>
                                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                    </svg>
                                                    <span><?php echo $event['nbrParticipants']; ?> participants</span>
                                                </div>
                                                <button class="btn btn-secondary">Voir Détails</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-events">
                                        <p>Aucun événement à venir trouvé dans la base de données.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="discoverevents.php" class="btn btn-outline btn-full">Voir Tous les Événements</a>
                        </div>
                    </div>
                    
                    <div class="clubs-section">
                        <div class="section-card">
                            <div class="section-header">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <h3 class="section-title">Mes Clubs</h3>
                            </div>
                            
                            <div class="clubs-list">
                                <?php if (count($user_clubs) > 0): ?>
                                    <?php foreach ($user_clubs as $club): ?>
                                        <?php 
                                        $event_count = get_club_event_count($club['idClub']);
                                        $badge_class = 'badge-blue';
                                        ?>
                                        <div class="club-item">
                                            <div class="club-header-content">
                                                <div class="club-main">
                                                    <div class="club-info">
                                                        <h4 class="club-name"><?php echo htmlspecialchars($club['nom']); ?></h4>
                                                        <p class="club-role"><?php echo htmlspecialchars($club['position'] ?? 'Membre'); ?></p>
                                                    </div>
                                                </div>
                                                <span class="badge <?php echo $badge_class; ?>">Club</span>
                                            </div>
                                            
                                            <div class="club-stats">
                                                <div class="club-stats-left">
                                                    <div class="club-stat">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                            <circle cx="9" cy="7" r="4"></circle>
                                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                        </svg>
                                                        <span><?php echo $club['nbrMembres']; ?> membres</span>
                                                    </div>
                                                    <div class="club-stat">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                                        </svg>
                                                        <span><?php echo $event_count; ?> événements</span>
                                                    </div>
                                                </div>
                                                <div class="club-actions">
                                                    <button class="btn-icon">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                            <polyline points="22,6 12,13 2,6"></polyline>
                                                        </svg>
                                                    </button>
                                                    <button class="btn-icon">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="12" cy="12" r="3"></circle>
                                                            <path d="M12 1v6m0 6v6m8.66-13.66l-4.24 4.24M9.34 14.66l-4.24 4.24M23 12h-6m-6 0H5m16.66 8.66l-4.24-4.24M9.34 9.34L5.1 5.1"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <button class="btn btn-tertiary btn-full">Voir Détails du Club</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-clubs">
                                        <p>Vous n'avez rejoint aucun club pour le moment.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="MyClubs.php" class="btn btn-outline btn-full">Parcourir Tous les Clubs</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>