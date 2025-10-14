<?php
session_start();
require_once '../database.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 1;

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

// Fetch clubs from database
try {
    $conn = db_connect();
    
    // Get clubs that the user is a member of
    $sql = "SELECT c.*, COUNT(e.idEvenement) as event_count 
            FROM club c 
            LEFT JOIN evenement e ON c.idClub = e.idClub 
            LEFT JOIN membre m ON c.idClub = m.idClub 
            WHERE m.idUtilisateur = ? 
            GROUP BY c.idClub 
            ORDER BY c.nom ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clubs = [];
    while ($club = $result->fetch_assoc()) {
        $club['id'] = $club['idClub'];
        $club['event_count'] = $club['event_count'] ?? 0;
        unset($club['idClub']);
        $clubs[] = $club;
    }
    
    // Calculate stats
    $total_clubs = count($clubs);
    $managing_clubs = 0; // You can modify this based on your role logic
    $member_of_clubs = $total_clubs;
    
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
    }

    .profile-info p:first-child {
      font-weight: 500;
    }

    .profile-info p:last-child {
      font-size: 12px;
      color: #d1d5db;
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
    .events-list { display:grid; gap:12px; }
    .event-row { padding:12px; border:1px solid rgba(255,255,255,0.12); border-radius:10px; background: rgba(255,255,255,0.04); display:flex; justify-content:space-between; align-items:center; }
    .muted { color:#9ca3af; }
  </style>
</head>
<body>
  <div class="app-container">
    <div class="sidebar">
      <div class="sidebar-logo">
        <h1>ClubConnect</h1>
        <p>Student Dashboard</p>
      </div>

      <nav class="sidebar-nav">
        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
            />
          </svg>
          <span>Dashboard</span>
        </button>

        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
          <span>Discover Events</span>
        </button>

        <button class="nav-item" onclick="window.location.href='MyEvents.php'">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <span>My Events</span>
        </button>

        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          <span>Create Event</span>
        </button>

        <button class="nav-item active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
            />
          </svg>
          <span>My Clubs</span>
        </button>

        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
          <span>Communications</span>
        </button>

        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
          </svg>
          <span>Certificates</span>
        </button>

        <button class="nav-item">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span>Settings</span>
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
          <button class="tab-trigger active" onclick="switchTab('all')">All (<?php echo $total_clubs; ?>)</button>
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
            $managing_clubs_list = []; // You can filter clubs where user has leadership role
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
            if (empty($clubs)) {
                echo '<div style="text-align:center; color:#9ca3af; grid-column:1 / -1; padding:24px;">No club memberships found</div>';
            } else {
                foreach ($clubs as $club) {
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
    return '
        <div class="club-card" onclick="showClubDetails(' . $club['id'] . ')">
            <div class="club-header">
                <div>
                    <h3 class="club-name">' . htmlspecialchars($club['nom']) . '</h3>
                    <p class="club-category">Events: ' . ($club['event_count'] ?? 0) . '</p>
                </div>
                <span class="club-badge">Members: ' . ($club['nbrMembres'] ?? 0) . '</span>
            </div>
        </div>
    ';
}
?>