<?php
session_start();
require "../database.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["user_id"])) {
    header("Location: ../signin.php");
    exit();
}

$current_user_id = $_SESSION["user_id"];
$conn = db_connect();

// Récupérer les informations de l'utilisateur
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

// Récupérer les attestations de l'utilisateur connecté
$sql = "SELECT 
            a.idUtilisateur,
            a.idEvenement,
            a.dateGeneration, 
            a.objet,
            u.nom,
            u.prenom,
            e.titre AS evenement_titre,
            c.nom AS club_nom,
            e.date AS date_evenement,
            e.lieu,
            e.nbrParticipants
        FROM Attestation a
        JOIN Utilisateur u ON a.idUtilisateur = u.idUtilisateur
        JOIN Evenement e ON a.idEvenement = e.idEvenement
        JOIN Club c ON e.idClub = c.idClub
        WHERE a.idUtilisateur = ?
        ORDER BY a.dateGeneration DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$attestations = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attestations[] = $row;
    }
}

// Statistiques pour l'utilisateur connecté
$sql_total_certificats = "SELECT COUNT(*) as total FROM Attestation WHERE idUtilisateur = ?";
$stmt_total = $conn->prepare($sql_total_certificats);
$stmt_total->bind_param("i", $current_user_id);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_certificats = $result_total->fetch_assoc()['total'] ?? 0;

$sql_certificats_mois = "SELECT COUNT(*) as total_mois FROM Attestation 
                         WHERE idUtilisateur = ? AND MONTH(dateGeneration) = MONTH(CURRENT_DATE()) 
                         AND YEAR(dateGeneration) = YEAR(CURRENT_DATE())";
$stmt_mois = $conn->prepare($sql_certificats_mois);
$stmt_mois->bind_param("i", $current_user_id);
$stmt_mois->execute();
$result_mois = $stmt_mois->get_result();
$certificats_mois = $result_mois->fetch_assoc()['total_mois'] ?? 0;

$sql_events_semestre = "SELECT COUNT(DISTINCT idEvenement) as total_events 
                        FROM Inscription 
                        WHERE idUtilisateur = ? AND dateInscription >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
$stmt_events = $conn->prepare($sql_events_semestre);
$stmt_events->bind_param("i", $current_user_id);
$stmt_events->execute();
$result_events = $stmt_events->get_result();
$events_semestre = $result_events->fetch_assoc()['total_events'] ?? 0;

$sql_clubs_actifs = "SELECT COUNT(DISTINCT idClub) as total_clubs FROM Adherence WHERE idUtilisateur = ?";
$stmt_clubs = $conn->prepare($sql_clubs_actifs);
$stmt_clubs->bind_param("i", $current_user_id);
$stmt_clubs->execute();
$result_clubs = $stmt_clubs->get_result();
$clubs_actifs = $result_clubs->fetch_assoc()['total_clubs'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Certificats</title>
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
            background-color: #0a0a0f;
            color: #ffffff;
            min-height: 100vh;
            font-size: 16px;
            line-height: 1.5;
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
            overflow-y: auto;
            padding: 32px;
        }

        .content-wrapper {
            max-width: 1280px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title svg {
            width: 32px;
            height: 32px;
            color: #60a5fa;
        }

        .header-title h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 500;
        }

        .search-wrapper {
            position: relative;
        }

        .search-input {
            width: 320px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 8px 16px 8px 40px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .search-input:focus {
            border-color: rgba(255, 255, 255, 0.2);
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

        .page-description {
            color: #9ca3af;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .stat-header span {
            color: #9ca3af;
            font-size: 14px;
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
            background: rgba(59, 130, 246, 0.2);
        }

        .stat-icon.green {
            background: rgba(34, 197, 94, 0.2);
        }

        .stat-icon.purple {
            background: rgba(168, 85, 247, 0.2);
        }

        .stat-icon svg {
            width: 20px;
            height: 20px;
        }

        .stat-icon.blue svg {
            color: #60a5fa;
        }

        .stat-icon.green svg {
            color: #4ade80;
        }

        .stat-icon.purple svg {
            color: #a78bfa;
        }

        .stat-value {
            color: #ffffff;
            font-size: 36px;
            font-weight: 500;
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin-top: 4px;
        }

        .certificates-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .certificate-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }

        .certificate-card:hover {
            background: rgba(255, 255, 255, 0.07);
        }

        .certificate-content {
            display: flex;
            align-items: start;
            gap: 16px;
        }

        .certificate-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.3s;
        }

        .certificate-card:hover .certificate-icon {
            transform: scale(1.1);
        }

        .certificate-icon svg {
            width: 28px;
            height: 28px;
            color: #60a5fa;
        }

        .certificate-details {
            flex: 1;
        }

        .certificate-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .certificate-title h3 {
            color: #ffffff;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .certificate-title p {
            color: #60a5fa;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge.available {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-badge.processing {
            background: rgba(234, 179, 8, 0.2);
            color: #facc15;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .status-badge svg {
            width: 12px;
            height: 12px;
        }

        .certificate-meta {
            display: flex;
            align-items: center;
            gap: 24px;
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item svg {
            width: 16px;
            height: 16px;
        }

        .certificate-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            padding: 8px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn svg {
            width: 16px;
            height: 16px;
        }

        .btn-primary {
            background: linear-gradient(to right, #2563eb, #3b82f6);
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #1d4ed8, #2563eb);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-disabled {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #6b7280;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-input {
                width: 100%;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .certificate-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
        .nav-item-active, .nav-item.active { background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); }
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

                <a href="communication.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Communications</span>
                </a>

                <a href="certificats.php" class="nav-item nav-item-active">
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
        <div class="content-wrapper">
            <div class="page-header">
                <div class="header-top">
                    <div class="header-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                        <h1>Mes Certificats</h1>
                    </div>
                    <div class="search-wrapper">
                        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Rechercher des certificats...">
                    </div>
                </div>
                <p class="page-description">Téléchargez les certificats des événements auxquels vous avez participé</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span>Total des Certificats</span>
                        <div class="stat-icon blue">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_certificats ?></div>
                    <p class="stat-label">+<?= $certificats_mois ?> ce mois-ci</p>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span>Événements Participés</span>
                        <div class="stat-icon green">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $events_semestre ?></div>
                    <p class="stat-label">Ce semestre</p>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span>Clubs Actifs</span>
                        <div class="stat-icon purple">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $clubs_actifs ?></div>
                    <p class="stat-label">Clubs rejoints</p>
                </div>
            </div>

            <div class="certificates-list">
                <?php if (count($attestations) > 0): ?>
                    <?php foreach ($attestations as $att): ?>
                        <div class="certificate-card">
                            <div class="certificate-content">
                                <div class="certificate-icon">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="certificate-details">
                                    <div class="certificate-header">
                                        <div class="certificate-title">
                                            <h3><?= htmlspecialchars($att['evenement_titre']) ?></h3>
                                            <p><?= htmlspecialchars($att['club_nom']) ?></p>
                                        </div>
                                        <span class="status-badge available">Disponible</span>
                                    </div>
                                    <div class="certificate-meta">
                                        <div class="meta-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span><?= date("d M Y", strtotime($att['date_evenement'])) ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span><?= htmlspecialchars($att['lieu']) ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <span><?= intval($att['nbrParticipants']) ?> participants</span>
                                        </div>
                                    </div>
                                    <div class="certificate-actions">
                                        <a class="btn btn-primary" href="download_certificat.php?idUtilisateur=<?= $att['idUtilisateur'] ?>&idEvenement=<?= $att['idEvenement'] ?>">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Télécharger le Certificat
                                        </a>
                                        <button class="btn btn-secondary">Voir les Détails</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun certificat disponible pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    </div>
</body>
</html>