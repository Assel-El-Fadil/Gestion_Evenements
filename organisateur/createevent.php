<?php

require "../database.php";

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = db_connect();
        
        $conn->set_charset("utf8mb4");
        
        $titre = $conn->real_escape_string($_POST['event_title'] ?? '');
        $description = $conn->real_escape_string($_POST['event_description'] ?? '');
        $date = $conn->real_escape_string($_POST['event_date'] ?? '');
        $lieu = $conn->real_escape_string($_POST['event_location'] ?? '');
        $capacite = intval($_POST['event_capacity'] ?? 0);
        $categorie = $conn->real_escape_string($_POST['event_category'] ?? '');
        $idClub = intval($_POST['event_club'] ?? 1);
        
        if (empty($titre) || empty($description) || empty($date) || empty($lieu) || empty($categorie)) {
            throw new Exception("Veuillez remplir tous les champs obligatoires.");
        }
        
        // Handle image upload
        $photo_path = '';
        if (isset($_FILES['event_photo']) && $_FILES['event_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Format d'image non supporté. Utilisez JPG, PNG ou GIF.");
            }
            
            $file_size = $_FILES['event_photo']['size'];
            if ($file_size > 10 * 1024 * 1024) { // 10MB limit
                throw new Exception("L'image est trop volumineuse. Taille maximale: 10MB.");
            }
            
            // Generate unique filename
            $unique_filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $upload_path)) {
                $photo_path = 'images/' . $unique_filename;
            } else {
                throw new Exception("Erreur lors du téléchargement de l'image.");
            }
        }
        
        $sql = "INSERT INTO evenement(titre, description, capacité, date, lieu, photo, statut, nbrParticipants, idClub) 
                VALUES (?, ?, ?, ?, ?, ?, 'upcoming', 0, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête: " . $conn->error);
        }
        
        $stmt->bind_param("ssisssi", $titre, $description, $capacite, $date, $lieu, $photo_path, $idClub);
        
        if ($stmt->execute()) {
            $success_message = "Événement créé avec succès !";
            $_POST = array();
            $_FILES = array();
        } else {
            throw new Exception("Erreur lors de la création de l'événement: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

function getFormValue($field) {
    return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : '';
}

function getFormattedDate($dateField) {
    if (isset($_POST[$dateField]) && !empty($_POST[$dateField])) {
        return date('j M Y', strtotime($_POST[$dateField]));
    }
    return 'Sélectionner une date';
}

?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Créer un Événement</title>
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

        .content-container {
            max-width: 80rem;
            margin: 0 auto;
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

        .header-text {
            flex: 1;
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

        /* Messages */
        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }

        .form-fields {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        .form-card label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            margin-bottom: 0.5rem;
        }

        .form-card input,
        .form-card textarea,
        .form-card select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-card input::placeholder,
        .form-card textarea::placeholder {
            color: #9ca3af;
        }

        .form-card input:focus,
        .form-card textarea:focus,
        .form-card select:focus {
            background: rgba(0, 0, 0, 0.6);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3);
        }

        .form-card textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-card select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }

        .form-card select option {
            background-color: rgba(0, 0, 0, 0.9);
            color: #ffffff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            background: rgba(0, 0, 0, 0.6);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3);
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.02);
            transition: all 0.2s;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.05);
        }

        .upload-icon {
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .upload-text {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            color: #9ca3af;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .upload-button {
            background: rgba(59, 130, 246, 0.8);
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .upload-button:hover {
            background: rgba(59, 130, 246, 1);
        }

        .file-preview {
            margin-top: 1rem;
            display: none;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 0.5rem;
        }

        /* Preview Column */
        .preview-column {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .preview-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            position: sticky;
            top: 1.5rem;
        }

        .preview-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .preview-content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .preview-image {
            width: 100%;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }

        .preview-text {
            flex: 1;
        }

        .preview-event-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .preview-event-desc {
            color: #9ca3af;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .preview-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .preview-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .preview-detail-item svg {
            color: #6b7280;
        }

        /* Button Group */
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn {
            background: rgba(37, 99, 235, 0.8);
            color: #ffffff;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn:hover {
            background: rgba(37, 99, 235, 1);
        }

        .btn-outline {
            background: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Date input styling */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .preview-card {
                position: static;
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

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .main-content {
                padding: 1rem;
            }

            .form-card {
                padding: 1rem;
            }

            .preview-card {
                padding: 1rem;
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
                    <a href="createevent.php" class="nav-item nav-item-active">
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
                        <span>JS</span>
                    </div>
                    <div class="profile-info">
                        <p class="profile-name">Jean Smith</p>
                        <p class="profile-department">Informatique</p>
                    </div>
                    </div>
                </div>
            </aside>
            <main class="main-content">
            <div class="content-container">
                <div class="header">
                    <div class="header-content">
                        <div class="header-text">
                            <h2 class="header-title">Créer un Nouvel Événement</h2>
                            <p class="header-subtitle">Remplissez les détails ci-dessous pour créer votre événement</p>
                        </div>
                    </div>
                    </div>

                    <?php if ($success_message): ?>
                    <div class="success-message">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="error-message">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="form-grid">
                        <div class="form-fields">
                            <div class="form-card">
                            <label>Titre de l'Événement *</label>
                            <input type="text" name="event_title" placeholder="Entrez le titre de l'événement" 
                                       value="<?php echo getFormValue('event_title'); ?>" required>
                            </div>

                            <div class="form-card">
                            <label>Description de l'Événement *</label>
                            <textarea name="event_description" rows="5" 
                                          placeholder="Décrivez votre événement en détail..." required><?php echo getFormValue('event_description'); ?></textarea>
                            </div>

                            <div class="form-card">
                            <label>Date de l'Événement *</label>
                            <input type="date" name="event_date" 
                                       value="<?php echo getFormValue('event_date'); ?>" required>
                            </div>

                            <div class="form-card">
                            <label>Lieu *</label>
                            <input type="text" name="event_location" placeholder="Entrez le lieu ou le numéro de salle"
                                       value="<?php echo getFormValue('event_location'); ?>" required>
                            </div>

                            <div class="form-row">
                            <div class="form-group">
                                <label>Capacité Maximale</label>
                                <input type="number" name="event_capacity" placeholder="50"
                                           value="<?php echo getFormValue('event_capacity'); ?>">
                                </div>
                            <div class="form-group">
                                <label>Catégorie *</label>
                                <select name="event_category" required>
                                        <option value="">Sélectionnez une catégorie</option>
                                        <option value="workshop" <?php echo (getFormValue('event_category') === 'workshop') ? 'selected' : ''; ?>>Atelier</option>
                                        <option value="seminar" <?php echo (getFormValue('event_category') === 'seminar') ? 'selected' : ''; ?>>Séminaire</option>
                                        <option value="networking" <?php echo (getFormValue('event_category') === 'networking') ? 'selected' : ''; ?>>Réseautage</option>
                                        <option value="social" <?php echo (getFormValue('event_category') === 'social') ? 'selected' : ''; ?>>Social</option>
                                        <option value="competition" <?php echo (getFormValue('event_category') === 'competition') ? 'selected' : ''; ?>>Compétition</option>
                                        <option value="meeting" <?php echo (getFormValue('event_category') === 'meeting') ? 'selected' : ''; ?>>Réunion</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-card">
                            <label>Club Organisateur *</label>
                            <select name="event_club" required>
                                    <option value="">Sélectionnez un club</option>
                                    <?php 
                                    $clubs = get_user_clubs(1, 10);
                                    foreach ($clubs as $club): ?>
                                        <option value="<?php echo $club['idClub']; ?>" 
                                                <?php echo (getFormValue('event_club') == $club['idClub']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($club['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-card">
                            <label>Image de l'Événement</label>
                                <div class="file-upload" onclick="document.getElementById('event_photo').click()">
                                    <svg class="upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    <p class="upload-text">Glissez-déposez une image ici, ou cliquez pour parcourir</p>
                                    <p class="upload-subtext">PNG, JPG, GIF jusqu'à 10MB</p>
                                <input type="file" name="event_photo" id="event_photo" accept="image/*" style="display: none;">
                                    
                                    <div class="file-preview" id="file-preview"></div>
                                </div>
                            </div>
                        </div>

                        <div class="preview-column">
                            <div class="preview-card">
                                <h3 class="preview-title">Aperçu de l'Événement</h3>
                                <div class="preview-content">
                                    <div class="preview-image" id="preview-image-container">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                    </div>
                                    <div class="preview-text">
                                        <h4 class="preview-event-title"><?php echo getFormValue('event_title') ?: 'Titre de l\'événement ici'; ?></h4>
                                        <p class="preview-event-desc"><?php echo getFormValue('event_description') ?: 'La description de l\'événement apparaîtra ici...'; ?></p>
                                    </div>
                                    <div class="preview-details">
                                        <div class="preview-detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            <span><?php echo getFormattedDate('event_date'); ?></span>
                                        </div>
                                        <div class="preview-detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/>
                                                <polyline points="12 6 12 12 16 14"/>
                                            </svg>
                                            <span>Toute la journée</span>
                                        </div>
                                        <div class="preview-detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <span><?php echo getFormValue('event_location') ?: 'Entrez le lieu'; ?></span>
                                        </div>
                                        <div class="preview-detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                            <span>0 / <?php echo isset($_POST['event_capacity']) ? intval($_POST['event_capacity']) : 0; ?> participants</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="button-group">
                            <button type="submit" class="btn">Créer l'Événement</button>
                            <a href="home.php" class="btn btn-outline">Annuler</a>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('input, textarea, select');
            const fileInput = document.getElementById('event_photo');
            
            formInputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const filePreview = document.getElementById('file-preview');
                        filePreview.innerHTML = `
                            <img src="${e.target.result}" alt="Aperçu" style="max-width: 100%; max-height: 150px; border-radius: 8px; margin-bottom: 8px;">
                            <div style="color: #9ca3af; font-size: 0.875rem;">${file.name}</div>
                        `;
                        filePreview.style.display = 'block';
                        
                        const previewContainer = document.getElementById('preview-image-container');
                        previewContainer.innerHTML = `
                            <img src="${e.target.result}" alt="Aperçu de l'événement" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            function updatePreview() {
                const title = document.querySelector('input[name="event_title"]').value || 'Titre de l\'événement ici';
                const description = document.querySelector('textarea[name="event_description"]').value || 'La description de l\'événement apparaîtra ici...';
                const date = document.querySelector('input[name="event_date"]').value;
                const location = document.querySelector('input[name="event_location"]').value || 'Entrez le lieu';
                const capacity = document.querySelector('input[name="event_capacity"]').value || 0;
                
                document.querySelector('.preview-event-title').textContent = title;
                document.querySelector('.preview-event-desc').textContent = description;
                
                if (date) {
                    const dateObj = new Date(date + 'T00:00:00');
                    document.querySelector('.preview-detail-item:nth-child(1) span').textContent = 
                        dateObj.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
                } else {
                    document.querySelector('.preview-detail-item:nth-child(1) span').textContent = 'Sélectionner une date';
                }
                
                document.querySelector('.preview-detail-item:nth-child(3) span').textContent = location;
                document.querySelector('.preview-detail-item:nth-child(4) span').textContent = `0 / ${capacity} participants`;
            }
            
            updatePreview();
        });
    </script>
</body>
</html>