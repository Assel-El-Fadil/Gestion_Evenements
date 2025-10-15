<?php
session_start();
require_once '../database.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 1;

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

// Handle join request
$join_success = false;
try {
    $conn = db_connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_club'], $_POST['club_id'])) {
        $club_id_join = intval($_POST['club_id']);

        // Avoid duplicate pending requests
        $check = $conn->prepare("SELECT 1 FROM requete WHERE idUtilisateur = ? AND idClub = ? LIMIT 1");
        $check->bind_param('ii', $user_id, $club_id_join);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$exists) {
            $ins = $conn->prepare("INSERT INTO requete (idUtilisateur, idClub) VALUES (?, ?)");
            $ins->bind_param('ii', $user_id, $club_id_join);
            $ins->execute();
            $ins->close();
        }
        $join_success = true;
    }

    // 1) Top counters
    $total_sql = "SELECT COUNT(*) AS total FROM club";
    $total_res = $conn->query($total_sql);
    $total_row = $total_res ? $total_res->fetch_assoc() : ['total' => 0];
    $total_clubs = (int)($total_row['total'] ?? 0);

    // Managing = clubs where current user has position 'organisateur'
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM adherence WHERE idUtilisateur = ? AND position = 'organisateur'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $managing_clubs = (int)($row['cnt'] ?? 0);
    $stmt->close();

    // Member Of = clubs where current user has position 'membre'
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM adherence WHERE idUtilisateur = ? AND position = 'membre'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $member_of_clubs = (int)($row['cnt'] ?? 0);
    $stmt->close();

    // 2) List ALL clubs with this user's role (if any) and event count
    $sql = "SELECT c.*, a.position, COUNT(e.idEvenement) AS event_count
            FROM club c
            LEFT JOIN adherence a ON a.idClub = c.idClub AND a.idUtilisateur = ?
            LEFT JOIN evenement e ON e.idClub = c.idClub
            GROUP BY c.idClub
            ORDER BY c.nom ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $clubs = [];
    while ($club = $result->fetch_assoc()) {
        $club['id'] = $club['idClub'];
        $club['event_count'] = (int)($club['event_count'] ?? 0);
        unset($club['idClub']);
        $clubs[] = $club;
    }

    // Count of user's clubs for tabs
    $user_clubs_count = count(array_filter($clubs, function($c){ return !empty($c['position']); }));

    $stmt->close();
    db_close();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $clubs = [];
    $total_clubs = $managing_clubs = $member_of_clubs = 0;
}

// Function to get club details by ID
function getClubDetails($club_id) {
    try {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT * FROM club WHERE idClub = ?");
        $stmt->bind_param('i', $club_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $club = $result->fetch_assoc();
        if ($club) {
            $club['id'] = $club['idClub'];
            unset($club['idClub']);
        }
        
        $stmt->close();
        db_close();
        return $club;
        
    } catch (Exception $e) {
        return null;
    }
}

// Function to get club events by club ID
function getClubEvents($club_id) {
    try {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT * FROM evenement WHERE idClub = ? ORDER BY date ASC");
        $stmt->bind_param('i', $club_id);
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
        
        $stmt->close();
        db_close();
        return $events;
        
    } catch (Exception $e) {
        return [];
    }
}

// Handle club details request
if (isset($_GET['club_id'])) {
    $club_id = intval($_GET['club_id']);
    $club_details = getClubDetails($club_id);
    
    if ($club_details) {
        echo json_encode($club_details);
    } else {
        echo json_encode(['error' => 'Club not found']);
    }
    exit();
}

// Handle club events request
if (isset($_GET['club_events'])) {
    $club_id = intval($_GET['club_events']);
    $club_events = getClubEvents($club_id);
    echo json_encode($club_events);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ClubConnect - My Clubs</title>
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
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
          'Helvetica Neue', Arial, sans-serif;
        background: #000000;
        color: #ffffff;
        overflow: hidden;
      }

      .app-container {
        display: flex;
        height: 100vh;
      }

      /* Sidebar */
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

      /* Main */
      .main-content {
        flex: 1;
        overflow-y: auto;
      }

      .header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(40px);
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
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #ffffff;
        font-size: 15px;
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
      }

      /* Stats */
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
      }

      .blue { background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; }
      .purple { background: rgba(147, 51, 234, 0.2); border: 1px solid rgba(147, 51, 234, 0.3); color: #a78bfa; }
      .green { background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; }

      .stat-value {
        font-size: 30px;
        font-weight: 600;
        margin-bottom: 4px;
      }

      .stat-meta {
        font-size: 14px;
        color: #9ca3af;
      }

      /* Tabs */
      .content-area {
        max-width: 1280px;
        margin: 0 auto;
        padding: 32px;
      }

      .tabs-list {
        display: flex;
        gap: 4px;
        padding: 4px;
        background: rgba(255, 255, 255, 0.05);
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

      .tab-trigger.active {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
      }

      .tab-content { display: none; }
      .tab-content.active { display: block; }

      /* Club Cards */
      .clubs-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
      }

      .club-card {
        padding: 24px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        backdrop-filter: blur(20px);
        transition: all 0.2s;
      }

      .club-card:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
      }

      .club-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
      .club-logo { font-size: 32px; display:none; }
      .club-name { font-size: 18px; font-weight: 500; margin-bottom: 4px; }
      .club-category { color: #9ca3af; font-size: 14px; }
      .club-badge { background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 8px; border-radius: 6px; font-size: 12px; }

      @media (max-width: 1024px) { .clubs-grid { grid-template-columns: 1fr; } }
      /* Modal */
      .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 50;
      }
      .modal {
        width: 90%;
        max-width: 800px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 12px;
        backdrop-filter: blur(20px);
        overflow: hidden;
      }
      .modal-header { display:flex; justify-content: space-between; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.12); }
      .modal-title { font-size: 18px; font-weight:600; }
      .modal-close { background: transparent; border: 1px solid rgba(255,255,255,0.2); color:#d1d5db; border-radius:8px; padding:6px 10px; cursor:pointer; }
      .modal-body { padding: 20px; }
      .events-list { display:grid; gap:12px; max-height: 50vh; overflow-y: auto; padding-right: 8px; }
      .events-list::-webkit-scrollbar { width: 8px; }
      .events-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
      .events-list::-webkit-scrollbar-track { background: transparent; }
      .event-row { padding:12px; border:1px solid rgba(255,255,255,0.12); border-radius:10px; background: rgba(255,255,255,0.04); display:flex; justify-content:space-between; align-items:center; }
      .muted { color:#9ca3af; }
    </style>
</head>
    <body>
        <div class="bg-gradient"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="app-container">
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
              <a href="MyClubs.php" class="nav-item nav-item-active">
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
                  <span>JS</span>
                </div>
                <div class="profile-info">
                  <p class="profile-name">Jean Smith</p>
                  <p class="profile-department">Informatique</p>
                </div>
              </div>
            </div>
          </div>

    <!-- MAIN CONTENT -->
          <div class="main-content">
            <div class="header">
              <div class="header-content">
                <div class="header-top">
                  <div class="header-title">
                    <h1>My Clubs</h1>
                    <p>Manage your club memberships and stay connected</p>
                  </div>
                  <div class="header-actions">
                    <div class="search-wrapper">
                      <svg
                        class="search-icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                      </svg>
                      <input
                        type="text"
                        class="search-input"
                        placeholder="Search clubs..."
                        id="searchInput"
                      />
                    </div>
                    <button class="notification-btn">
                      <svg
                        width="24"
                        height="24"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                      </svg>
                      <span class="notification-badge"></span>
                    </button>
                  </div>
                </div>

                <div class="stats-grid">
                  <div class="stat-card">
                    <div class="stat-header">
                      <p class="stat-label">Total Clubs</p>
                      <div class="stat-icon blue">🏛️</div>
                    </div>
                    <p class="stat-value"><?php echo $total_clubs; ?></p>
                    <p class="stat-meta">All time</p>
                  </div>

                  <div class="stat-card">
                    <div class="stat-header">
                      <p class="stat-label">Managing</p>
                      <div class="stat-icon purple">⭐</div>
                    </div>
                    <p class="stat-value"><?php echo $managing_clubs; ?></p>
                    <p class="stat-meta">Leadership positions</p>
                  </div>

                  <div class="stat-card">
                    <div class="stat-header">
                      <p class="stat-label">Member Of</p>
                      <div class="stat-icon green">👥</div>
                    </div>
                    <p class="stat-value"><?php echo $member_of_clubs; ?></p>
                    <p class="stat-meta">Active memberships</p>
                  </div>
                </div>
              </div>
            </div>

              <div class="content-area">
                <div class="tabs-list">
                  <button class="tab-trigger active" onclick="switchTab('all')">All (<?php echo isset($user_clubs_count) ? $user_clubs_count : (isset($clubs) ? count($clubs) : 0); ?>)</button>
                  <button class="tab-trigger" onclick="switchTab('managing')">Managing (<?php echo $managing_clubs; ?>)</button>
                  <button class="tab-trigger" onclick="switchTab('member')">Member Of (<?php echo $member_of_clubs; ?>)</button>
                </div>

                <!-- All Tab -->
                <div id="tab-all" class="tab-content active">
                  <div class="clubs-grid">
                    <?php
                    if (empty($clubs)) {
                        echo '<div style="text-align:center; color:#9ca3af; grid-column:1 / -1; padding:24px;">No clubs found</div>';
                    } else {
                        foreach ($clubs as $club) {
                            echo createClubCardHTML($club);
                        }
                    }
                    ?>
                  </div>
                </div>

                <!-- Managing Tab -->
                <div id="tab-managing" class="tab-content">
                  <div class="clubs-grid">
                    <?php
                    $managing_clubs_list = array_values(array_filter($clubs ?? [], function($c){ return isset($c['position']) && $c['position'] === 'organisateur'; }));
                    if (empty($managing_clubs_list)) {
                        echo '<div style="text-align:center; color:#9ca3af; grid-column:1 / -1; padding:24px;">No managing roles found</div>';
                    } else {
                        foreach ($managing_clubs_list as $club) {
                            echo createClubCardHTML($club);
                        }
                    }
                    ?>
                  </div>
                </div>

                <!-- Member Tab -->
                <div id="tab-member" class="tab-content">
                  <div class="clubs-grid">
                    <?php
                    $member_clubs_list = array_values(array_filter($clubs ?? [], function($c){ return isset($c['position']) && $c['position'] === 'membre'; }));
                    if (empty($member_clubs_list)) {
                        echo '<div style="text-align:center; color:#9ca3af; grid-column:1 / -1; padding:24px;">No club memberships found</div>';
                    } else {
                        foreach ($member_clubs_list as $club) {
                            echo createClubCardHTML($club);
                        }
                    }
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

      <script>
        function switchTab(tab) {
          document.querySelectorAll('.tab-trigger').forEach((t) => t.classList.remove('active'));
          document.querySelectorAll('.tab-content').forEach((c) => c.classList.remove('active'));
          document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
          document.getElementById(`tab-${tab}`).classList.add('active');
        }

        async function fetchClub(id) {
          const res = await fetch('MyClubs.php?club_id=' + encodeURIComponent(id));
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json();
        }

        async function fetchClubEvents(id) {
          const res = await fetch('MyClubs.php?club_events=' + encodeURIComponent(id));
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json();
        }

        async function showClubDetails(id) {
          try {
            toggleModal(true, '<div class="muted">Loading...</div>');
            const [club, events] = await Promise.all([fetchClub(id), fetchClubEvents(id)]);
            const body = `
              <div style="margin-bottom:12px;">
                <div class="muted">Members</div>
                <div style="font-size:22px; font-weight:600;">${club.nbrMembres ?? 0}</div>
              </div>
              <div class="muted" style="margin:12px 0 6px;">Events</div>
              <div class="events-list">
                ${Array.isArray(events) && events.length > 0 ? events.map(ev => `
                  <div class=\"event-row\">
                    <div>
                      <div style=\"font-weight:600\">${ev.nom}</div>
                      <div class=\"muted\">${ev.date_evenement} • ${ev.lieu || 'TBA'}</div>
                    </div>
                    <div class=\"muted\">${ev.participants_inscrits || 0} going</div>
                  </div>
                `).join('') : '<div class="muted">No events for this club</div>'}
              </div>
            `;
            setModalContent(club.nom || 'Club Details', body);
          } catch (e) {
            setModalContent('Error', '<div class="muted">Failed to load club details: ' + e.message + '</div>');
          }
        }

        function setModalContent(title, html) {
          const titleEl = document.getElementById('clubModalTitle');
          const bodyEl = document.getElementById('clubModalBody');
          if (titleEl) titleEl.textContent = title;
          if (bodyEl) bodyEl.innerHTML = html;
          toggleModal(true);
        }

        function toggleModal(show, placeholderHtml) {
          const backdrop = document.getElementById('clubModal');
          if (!backdrop) return;
          if (typeof placeholderHtml === 'string') {
            const bodyEl = document.getElementById('clubModalBody');
            if (bodyEl) bodyEl.innerHTML = placeholderHtml;
          }
          backdrop.style.display = show ? 'flex' : 'none';
        }
      </script>
      <?php if (!empty($join_success)) { echo "<script>alert('Attendez que votre demande soit approuvée');</script>"; } ?>
      <!-- Club Details Modal -->
      <div id="clubModal" class="modal-backdrop" onclick="if(event.target===this) toggleModal(false)">
        <div class="modal">
          <div class="modal-header">
            <div id="clubModalTitle" class="modal-title">Club Details</div>
            <button class="modal-close" onclick="toggleModal(false)">Close</button>
          </div>
          <div id="clubModalBody" class="modal-body"></div>
        </div>
      </div>
    </body>
  </html>

<?php
function createClubCardHTML($club) {
    $has_role = isset($club['position']) && in_array($club['position'], ['membre', 'organisateur']);
    $join_form = '';
    if (!$has_role) {
        $join_form = '
            <form method="POST" onsubmit="event.stopPropagation();" style="margin-top:12px;">
                <input type="hidden" name="join_club" value="1" />
                <input type="hidden" name="club_id" value="' . intval($club['id']) . '" />
                <button type="submit" onclick="event.stopPropagation(); alert(\'Attendez que votre demande soit approuvée\');" style="cursor:pointer; padding:8px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.08); color:#fff;">Join</button>
            </form>';
    }

    return '
        <div class="club-card" onclick="showClubDetails(' . $club['id'] . ')">
            <div class="club-header">
                <div>
                    <h3 class="club-name">' . htmlspecialchars($club['nom']) . '</h3>
                    <p class="club-category">Events: ' . ($club['event_count'] ?? 0) . '</p>
                </div>
                <span class="club-badge">Members: ' . ($club['nbrMembres'] ?? 0) . '</span>
            </div>
            ' . $join_form . '
        </div>
    ';
}
?>