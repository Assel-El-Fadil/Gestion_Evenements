<?php

require "database.php";

$current_user_id = 1;
$upcoming_events = get_upcoming_events(5);
$user_clubs = get_user_clubs($current_user_id, 2);

?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Student Dashboard</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="sidebar-title">ClubConnect</h1>
                <p class="sidebar-subtitle">Student Dashboard</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item nav-item-active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>Discover Events</span>
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>My Events</span>
                </a>
                <a href="createevent.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Create Event</span>
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>My Clubs</span>
                </a>
                <a href="communication.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Communications</span>
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="7"></circle>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                    </svg>
                    <span>Certificates</span>
                </a>
            </nav>
            
            <div class="sidebar-profile">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <span>JS</span>
                    </div>
                    <div class="profile-info">
                        <p class="profile-name">John Smith</p>
                        <p class="profile-department">Computer Science</p>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-content">
                    <div class="header-text">
                        <h2 class="header-title">Welcome back, John!</h2>
                        <p class="header-subtitle">Here's what's happening with your clubs today</p>
                    </div>
                    
                    <div class="header-actions">
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" placeholder="Search events, clubs..." class="search-input">
                        </div>
                        
                        <button class="notification-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="notification-dot"></span>
                        </button>
                    </div>
                </div>
            </header>
            
            <div class="content-container">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Upcoming Events</span>
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
                            <span class="stat-title">Joined Clubs</span>
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
                            <p class="stat-change">Your active clubs</p>
                        </div>
                    </div>
                </div>
                
                <!-- Events and Clubs Grid -->
                <div class="content-grid">
                    <!-- Upcoming Events -->
                    <div class="events-section">
                        <div class="section-card">
                            <div class="section-header">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <h3 class="section-title">Upcoming Events</h3>
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
                                                <span class="badge badge-blue">Event</span>
                                            </div>
                                            
                                            <div class="event-details">
                                                <div class="event-detail">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    </svg>
                                                    <span><?php echo date('M j, Y', strtotime($event['date'])); ?></span>
                                                </div>
                                                <div class="event-detail">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                    <span><?php echo date('g:i A', strtotime($event['date'])); ?></span>
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
                                                    <span><?php echo $event['nbrParticipants']; ?> attending</span>
                                                </div>
                                                <button class="btn btn-secondary">View Details</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-events">
                                        <p>No upcoming events found in the database.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn btn-outline btn-full">View All Events</button>
                        </div>
                    </div>
                    
                    <!-- Joined Clubs -->
                    <div class="clubs-section">
                        <div class="section-card">
                            <div class="section-header">
                                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <h3 class="section-title">My Clubs</h3>
                            </div>
                            
                            <div class="clubs-list">
                                <?php if (count($user_clubs) > 0): ?>
                                    <?php foreach ($user_clubs as $club): ?>
                                        <?php 
                                        $event_count = get_club_event_count($club['idClub']);
                                        $badge_class = 'badge-blue';
                                        $club_emoji = '🚀'; // Default emoji
                                        
                                        // Assign different badge colors and emojis based on club name or type
                                        if (stripos($club['nom'], 'tech') !== false || stripos($club['nom'], 'computer') !== false) {
                                            $badge_class = 'badge-blue';
                                            $club_emoji = '🚀';
                                        } elseif (stripos($club['nom'], 'international') !== false || stripos($club['nom'], 'cultural') !== false) {
                                            $badge_class = 'badge-purple';
                                            $club_emoji = '🌍';
                                        } elseif (stripos($club['nom'], 'business') !== false || stripos($club['nom'], 'entrepreneur') !== false) {
                                            $badge_class = 'badge-green';
                                            $club_emoji = '💼';
                                        } elseif (stripos($club['nom'], 'photo') !== false || stripos($club['nom'], 'art') !== false) {
                                            $badge_class = 'badge-yellow';
                                            $club_emoji = '📸';
                                        } elseif (stripos($club['nom'], 'ai') !== false || stripos($club['nom'], 'robot') !== false) {
                                            $badge_class = 'badge-indigo';
                                            $club_emoji = '🤖';
                                        }
                                        ?>
                                        <div class="club-item">
                                            <div class="club-header-content">
                                                <div class="club-main">
                                                    <div class="club-avatar"><?php echo $club_emoji; ?></div>
                                                    <div class="club-info">
                                                        <h4 class="club-name"><?php echo htmlspecialchars($club['nom']); ?></h4>
                                                        <p class="club-role"><?php echo htmlspecialchars($club['position'] ?? 'Member'); ?></p>
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
                                                        <span><?php echo $club['nbrMembres']; ?> members</span>
                                                    </div>
                                                    <div class="club-stat">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                                        </svg>
                                                        <span><?php echo $event_count; ?> events</span>
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
                                            
                                            <button class="btn btn-tertiary btn-full">View Club Details</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-clubs">
                                        <p>You haven't joined any clubs yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn btn-outline btn-full">Browse All Clubs</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>