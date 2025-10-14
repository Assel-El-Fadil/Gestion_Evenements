<?php

session_start();
require_once '../database.php';

$user_id = $_SESSION['user_id'] ?? 1;

$conn = db_connect();

$user = null;
$initials = "JS"; 

if ($user_id) {
    $user_sql = "SELECT nom, prenom, filiere FROM utilisateur WHERE idUtilisateur = ?";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $first_initial = substr($user['prenom'], 0, 1);
        $last_initial = substr($user['nom'], 0, 1);
        $initials = strtoupper($first_initial . $last_initial);
    }
    $stmt->close();
}

$stats = [];

$events_month_sql = "SELECT COUNT(*) as count FROM evenement 
                    WHERE MONTH(date) = MONTH(CURRENT_DATE()) 
                    AND YEAR(date) = YEAR(CURRENT_DATE())";
$result = $conn->query($events_month_sql);
$stats['events_this_month'] = $result->fetch_assoc()['count'];

$active_clubs_sql = "SELECT COUNT(DISTINCT idClub) as count FROM evenement";
$result = $conn->query($active_clubs_sql);
$stats['active_clubs'] = $result->fetch_assoc()['count'];

$categories_sql = "SELECT COUNT(DISTINCT titre) as count FROM evenement";
$result = $conn->query($categories_sql);
$stats['categories'] = $result->fetch_assoc()['count'];

$events_sql = "SELECT e.*, c.nom as club_nom 
               FROM evenement e 
               JOIN club c ON e.idClub = c.idClub 
               ORDER BY e.date ASC 
               LIMIT 6";
$events_result = $conn->query($events_sql);
$events_count = $events_result->num_rows;

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
            background-color: #13141a;
            border-right: 1px solid #1f2029;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 24px;
            border-bottom: 1px solid #1f2029;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 500;
            line-height: 1.5;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .logo p {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
        }

        nav {
            flex: 1;
            padding: 16px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: #9ca3af;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 4px;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
        }

        .nav-link:hover {
            background-color: #1f2029;
            color: #ffffff;
        }

        .nav-link.active {
            background-color: #1f2029;
            color: #ffffff;
        }

        .nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .user-profile {
            padding: 16px;
            border-top: 1px solid #1f2029;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background-color: #1f2029;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            color: #ffffff;
        }

        .user-role {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #9ca3af;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <h1>ClubConnect</h1>
                <p>Tableau de Bord Étudiant</p>
            </div>

            <nav>
                <a href="home.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Tableau de Bord</span>
                </a>
                <a href="discoverevents.php" class="nav-link active">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span>Découvrir les Événements</span>
                </a>
                <a href="MyEvents.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Mes Événements</span>
                </a>
                <a href="createevent.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Créer un Événement</span>
                </a>
                <a href="MyClubs" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Mes Clubs</span>
                </a>
                <a href="communication.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>Communications</span>
                </a>
                <a href="certificats.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <span>Certificats</span>
                </a>
            </nav>

            <div class="user-profile">
                <div class="user-card">
                    <div class="user-avatar"><?= $initials ?></div>
                    <div class="user-info">
                        <?php if ($user): ?>
                            <p class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></p>
                            <p class="user-role"><?= htmlspecialchars($user['filiere']) ?></p>
                        <?php else: ?>
                            <p class="user-name">Utilisateur</p>
                            <p class="user-role">Non connecté</p>
                        <?php endif; ?>
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
                        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Rechercher des événements, clubs...">
                    </div>
                </div>

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
                                
                                $image_url = "https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=MnwxfDB8MXxyYW5kb218MHx8ZXZlbnR8fHx8fHwxNzI4NjU2ODAw&ixlib=rb-4.0.3&q=80&w=1080";
                                
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
                                
                                $nbrParticipants = isset($event['nbrParticipants']) ? $event['nbrParticipants'] : 0;
                                $capacite = isset($event['capacite']) ? $event['capacite'] : 50;
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
                                            <button class="btn btn-primary">S'inscrire</button>
                                            <button class="btn btn-secondary">Voir Détails</button>
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