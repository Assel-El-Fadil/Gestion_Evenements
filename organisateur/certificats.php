<?php
require "../database.php"; 

$conn = db_connect();

session_start();
$user_id = $_SESSION['user_id'] ?? 1;

$sql_user = "SELECT nom, prenom, filiere FROM Utilisateur WHERE idUtilisateur = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

if (!$user) {
    $user = [
        'nom' => 'Dupont',
        'prenom' => 'Jean',
        'filiere' => 'Informatique'
    ];
}

$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

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
        ORDER BY a.dateGeneration DESC";

$result = $conn->query($sql);
$attestations = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attestations[] = $row;
    }
} else {
    echo "Erreur lors de la récupération des certificats : " . $conn->error;
}

$sql_total_certificats = "SELECT COUNT(*) as total FROM Attestation";
$result_total = $conn->query($sql_total_certificats);
$total_certificats = $result_total ? $result_total->fetch_assoc()['total'] : 0;

$sql_certificats_mois = "SELECT COUNT(*) as total_mois FROM Attestation 
                         WHERE MONTH(dateGeneration) = MONTH(CURRENT_DATE()) 
                         AND YEAR(dateGeneration) = YEAR(CURRENT_DATE())";
$result_mois = $conn->query($sql_certificats_mois);
$certificats_mois = $result_mois ? $result_mois->fetch_assoc()['total_mois'] : 0;

$sql_events_semestre = "SELECT COUNT(DISTINCT idEvenement) as total_events 
                        FROM Inscription 
                        WHERE dateInscription >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
$result_events = $conn->query($sql_events_semestre);
$events_semestre = $result_events ? $result_events->fetch_assoc()['total_events'] : 0;

$sql_clubs_actifs = "SELECT COUNT(DISTINCT idClub) as total_clubs FROM Adherence";
$result_clubs = $conn->query($sql_clubs_actifs);
$clubs_actifs = $result_clubs ? $result_clubs->fetch_assoc()['total_clubs'] : 0;

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
    </style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
            <div class="sidebar-header">
                <h1>ClubConnect</h1>
                <p>Tableau de Bord Étudiant</p>
            </div>

            <nav class="sidebar-nav">
                <a href="home.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Tableau de Bord</span>
                </a>

                <a href="discoverevents.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span>Découvre les Événements</span>
                </a>

                <a href="MyEvents.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Mes Événements</span>
                </a>

                <a href="createevent.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Créer un Événement</span>
                </a>

                <a href="MyClubs.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Mes Clubs</span>
                </a>

                <a href="communication.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>Communications</span>
                </a>

                <a href="certificats.php" class="nav-item active">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <span>Certificats</span>
                </a>
            </nav>

            <div class="user-profile">
                <div class="user-profile-content">
                    <div class="user-avatar"><?= $initials ?></div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h3>
                        <p><?= htmlspecialchars($user['filiere']) ?></p>
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