<?php

require "database.php";

$current_user_id = 1;
$upcoming_events = get_upcoming_events(5);
$user_clubs = get_user_clubs($current_user_id, 2);

?>


<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Tableau de Bord</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>
    <div class="dashboard">
        <!-- Barre latérale -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="sidebar-title">ClubConnect</h1>
                <p class="sidebar-subtitle">Tableau de Bord Étudiant</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item nav-item-active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Tableau de Bord</span>
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>Découvrir Événements</span>
                </a>
                <a href="#" class="nav-item">
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
                <a href="#" class="nav-item">
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
                <a href="#" class="nav-item">
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
        </aside>
        
        <!-- Contenu Principal -->
        <main class="main-content">
            <!-- En-tête -->
            <header class="header">
                <div class="header-content">
                    <div class="header-text">
                        <h2 class="header-title">Bon retour, Jean !</h2>
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
                <!-- Cartes de Statistiques -->
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
                
                <!-- Grille Événements et Clubs -->
                <div class="content-grid">
                    <!-- Événements à Venir -->
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
                            
                            <button class="btn btn-outline btn-full">Voir Tous les Événements</button>
                        </div>
                    </div>
                    
                    <!-- Clubs Rejoints -->
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
                                        $club_emoji = '🚀'; // Émoji par défaut
                                        
                                        // Assigner différentes couleurs et émojis basés sur le nom du club
                                        if (stripos($club['nom'], 'tech') !== false || stripos($club['nom'], 'computer') !== false || stripos($club['nom'], 'informatique') !== false) {
                                            $badge_class = 'badge-blue';
                                            $club_emoji = '🚀';
                                        } elseif (stripos($club['nom'], 'international') !== false || stripos($club['nom'], 'cultural') !== false || stripos($club['nom'], 'culture') !== false) {
                                            $badge_class = 'badge-purple';
                                            $club_emoji = '🌍';
                                        } elseif (stripos($club['nom'], 'business') !== false || stripos($club['nom'], 'entrepreneur') !== false || stripos($club['nom'], 'commerce') !== false) {
                                            $badge_class = 'badge-green';
                                            $club_emoji = '💼';
                                        } elseif (stripos($club['nom'], 'photo') !== false || stripos($club['nom'], 'art') !== false || stripos($club['nom'], 'artist') !== false) {
                                            $badge_class = 'badge-yellow';
                                            $club_emoji = '📸';
                                        } elseif (stripos($club['nom'], 'ai') !== false || stripos($club['nom'], 'robot') !== false || stripos($club['nom'], 'intelligence') !== false) {
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
                            
                            <button class="btn btn-outline btn-full">Parcourir Tous les Clubs</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>