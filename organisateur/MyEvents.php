<?php
session_start();
require_once '../database.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 1;

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

// Fetch events from database
try {
    $conn = db_connect();
    
    // Get events that the user is registered for
    $sql = "SELECT e.*, c.nom as club_nom 
            FROM evenement e 
            LEFT JOIN club c ON e.idClub = c.idClub 
            LEFT JOIN inscription i ON e.idEvenement = i.idEvenement 
            WHERE i.idUtilisateur = ? 
            ORDER BY e.date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
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
    
    // Calculate stats
    $total_events = count($events);
    $upcoming_events = array_filter($events, function($event) {
        return $event['is_upcoming'];
    });
    $past_events = array_filter($events, function($event) {
        return !$event['is_upcoming'];
    });
    
    $total_count = $total_events;
    $upcoming_count = count($upcoming_events);
    $past_count = count($past_events);
    
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
        $stmt = $conn->prepare("SELECT e.*, c.nom as club_nom FROM evenement e LEFT JOIN club c ON e.idClub = c.idClub WHERE e.idEvenement = ?");
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
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .sidebar-logo p {
            font-size: 14px;
            color: #d1d5db;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 4px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            color: #d1d5db;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 15px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .sidebar-profile {
            padding: 16px;
            margin: 16px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .sidebar-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .profile-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #9333ea);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }

        .profile-info p:first-child {
            font-weight: 500;
        }

        .profile-info p:last-child {
            font-size: 12px;
            color: #d1d5db;
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
            position: sticky;
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
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h1>ClubConnect</h1>
                <p>Student Dashboard</p>
            </div>

            <nav class="sidebar-nav">
                <button class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Dashboard</span>
                </button>

                <button class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Discover Events</span>
                </button>

                <button class="nav-item active">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>My Events</span>
                </button>

                <button class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Create Event</span>
                </button>

                <button class="nav-item" onclick="window.location.href='MyClubs.php'">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>My Clubs</span>
                </button>

                <button class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>Communications</span>
                </button>

                <button class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <span>Certificates</span>
                </button>
            </nav>

            <div class="sidebar-profile">
                <div class="profile-content">
                    <div class="profile-avatar">JS</div>
                    <div class="profile-info">
                        <p>John Smith</p>
                        <p>Computer Science</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="header-top">
                        <div class="header-title">
                            <h1>My Events</h1>
                            <p>Manage and track your event registrations</p>
                        </div>
                        <div class="header-actions">
                            <div class="search-wrapper">
                                <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" class="search-input" placeholder="Search events">
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
                                <p class="stat-label">Total Registered</p>
                                <div class="stat-icon blue">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="stat-value"><?php echo $total_count; ?></p>
                            <p class="stat-meta">All time</p>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <p class="stat-label">Upcoming Events</p>
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
                                <p class="stat-label">Attended</p>
                                <div class="stat-icon green">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="stat-value"><?php echo $past_count; ?></p>
                            <p class="stat-meta">Events completed</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <div class="tabs-list">
                    <button class="tab-trigger active" onclick="switchTab('upcoming')">Upcoming (<?php echo $upcoming_count; ?>)</button>
                    <button class="tab-trigger" onclick="switchTab('past')">Past (<?php echo $past_count; ?>)</button>
                    <button class="tab-trigger" onclick="switchTab('all')">All (<?php echo $total_count; ?>)</button>
                </div>

                <!-- Upcoming Tab -->
                <div id="tab-upcoming" class="tab-content active">
                    <div class="events-grid">
                        <?php
                        $upcoming_events = array_filter($events, function($event) {
                            return $event['is_upcoming'];
                        });
                        
                        if (empty($upcoming_events)) {
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">No upcoming events found</p>';
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
                            return !$event['is_upcoming'];
                        });
                        
                        if (empty($past_events)) {
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">No past events found</p>';
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
                            echo '<p style="text-align:center; color:#9ca3af; grid-column:1 / -1;">No events found</p>';
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
                toggleModal(true, '<div style="color:#9ca3af;">Loading...</div>');
                const response = await fetch('MyEvents.php?event_id=' + id);
                const event = await response.json();
                
                if (event.error) {
                    throw new Error(event.error);
                }
                
                const dateObj = new Date(event.date_evenement);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
                const html = `
                    <div style="display:grid; gap:12px;">
                        <div><span class="muted">Club</span><div>${event.club_nom || ''}</div></div>
                        <div style="display:flex; gap:16px;">
                            <div><span class="muted">Date</span><div>${formattedDate}</div></div>
                            <div><span class="muted">Status</span><div>${event.statut}</div></div>
                        </div>
                        <div><span class="muted">Location</span><div>${event.lieu || 'TBA'}</div></div>
                        <div><span class="muted">Description</span><div>${event.description || ''}</div></div>
                        <div style="display:flex; gap:16px;">
                            <div><span class="muted">Attending</span><div>${event.participants_inscrits || 0}</div></div>
                            <div><span class="muted">Capacity</span><div>${event.capacite_max || 0}</div></div>
                        </div>
                    </div>
                `;
                setModalContent(event.nom, html);
            } catch (e) {
                setModalContent('Error', '<div style="color:#ef4444;">Failed to load event: ' + e.message + '</div>');
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
                <div id="eventModalTitle" class="modal-title">Event Details</div>
                <button class="modal-close" onclick="toggleModal(false)">Close</button>
            </div>
            <div id="eventModalBody" class="modal-body"></div>
        </div>
    </div>
</body>
</html>

<?php
function createEventCardHTML($event) {
    $dateObj = new Date($event['date_evenement']);
    $formattedDate = $dateObj->format('M j, Y');
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