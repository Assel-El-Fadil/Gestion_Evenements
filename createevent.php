<?php

require "database.php";

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
        $idClub = 1;
        
        if (empty($titre) || empty($description) || empty($date) || empty($lieu) || empty($categorie)) {
            throw new Exception("Veuillez remplir tous les champs obligatoires.");
        }
        
        $sql = "INSERT INTO evenement(titre, description, capacité, date, lieu, photo, statut, nbrParticipants, idClub, categorie) 
                VALUES (?, ?, ?, ?, ?, '', 'upcoming', 0, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête: " . $conn->error);
        }
        
        $stmt->bind_param("ssisiss", $titre, $description, $capacite, $date, $lieu, $idClub, $categorie);
        
        if ($stmt->execute()) {
            $success_message = "Événement créé avec succès !";
            $_POST = array();
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
    <link rel="stylesheet" href="createevent.css">
</head>
<body>
    <div class="app-container">
        <div class="bg-gradient"></div>
        
        <div class="main-layout">
            <aside class="sidebar">
                <div class="sidebar-shine-1"></div>
                
                <div class="logo-section">
                    <h1 class="logo-title">ClubConnect</h1>
                    <p class="logo-subtitle">Tableau de Bord</p>
                </div>

                <nav class="nav-menu">
                    <a href="home.php" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <span>Tableau de Bord</span>
                    </a>
                    <a href="discoverevents.php" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <span>Découvrir Événements</span>
                    </a>
                    <a href="#" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>Mes Événements</span>
                    </a>
                    <a href="createevent.php" class="nav-item active">
                        <div class="active-gradient"></div>
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span>Créer Événement</span>
                    </a>
                    <a href="#" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>Mes Clubs</span>
                    </a>
                    <a href="communication.php" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                        <span>Communications</span>
                    </a>
                    <a href="certificats.php" class="nav-item">
                        <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="6"/>
                            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                        </svg>
                        <span>Certificats</span>
                    </a>
                </nav>

                <div class="user-profile">
                    <div class="user-gradient"></div>
                    <div class="user-avatar">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="user-info">
                        <p class="user-name">Jean Smith</p>
                        <p class="user-dept">Informatique</p>
                    </div>
                </div>
            </aside>
            <main class="main-content">
                <div class="form-container">
                    <div class="page-header">
                        <h1 class="page-title">Créer un Nouvel Événement</h1>
                        <p class="page-subtitle">Remplissez les détails ci-dessous pour créer votre événement</p>
                    </div>

                    <?php if ($success_message): ?>
                        <div class="message success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="message error">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="form-grid">
                        <div class="form-fields">
                            <div class="form-card">
                                <div class="card-shine-1"></div>
                                <div class="card-shine-2"></div>
                                <label class="form-label">Titre de l'Événement *</label>
                                <input type="text" name="event_title" class="form-input" placeholder="Entrez le titre de l'événement" 
                                       value="<?php echo getFormValue('event_title'); ?>" required>
                            </div>

                            <div class="form-card">
                                <div class="card-shine-3"></div>
                                <label class="form-label">Description de l'Événement *</label>
                                <textarea name="event_description" class="form-textarea" rows="5" 
                                          placeholder="Décrivez votre événement en détail..." required><?php echo getFormValue('event_description'); ?></textarea>
                            </div>

                            <div class="form-card">
                                <div class="card-shine-4"></div>
                                <label class="form-label label-with-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Date de l'Événement *
                                </label>
                                <input type="date" name="event_date" class="form-input" 
                                       value="<?php echo getFormValue('event_date'); ?>" required>
                            </div>

                            <div class="form-card">
                                <div class="card-shine-6"></div>
                                <label class="form-label label-with-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    Lieu *
                                </label>
                                <input type="text" name="event_location" class="form-input" placeholder="Entrez le lieu ou le numéro de salle"
                                       value="<?php echo getFormValue('event_location'); ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-card">
                                    <div class="card-shine-7"></div>
                                    <label class="form-label label-with-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                        Capacité Maximale
                                    </label>
                                    <input type="number" name="event_capacity" class="form-input" placeholder="50"
                                           value="<?php echo getFormValue('event_capacity'); ?>">
                                </div>
                                <div class="form-card">
                                    <div class="card-shine-8"></div>
                                    <label class="form-label label-with-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                                        </svg>
                                        Catégorie *
                                    </label>
                                    <select name="event_category" class="form-select" required>
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
                                <div class="card-shine-10"></div>
                                <label class="form-label label-with-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    Club Organisateur *
                                </label>
                                <select name="event_club" class="form-select" required>
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
                                <div class="card-shine-9"></div>
                                <label class="form-label label-with-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    Image de l'Événement
                                </label>
                                <div class="file-upload">
                                    <svg class="upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    <p class="upload-text">Glissez-déposez une image ici, ou cliquez pour parcourir</p>
                                    <p class="upload-subtext">PNG, JPG, GIF jusqu'à 10MB</p>
                                    <input type="file" name="event_photo" id="event_photo" accept="image/*" class="file-input" style="display: none;">
                                    <button type="button" class="upload-button" onclick="document.getElementById('event_photo').click()">Choisir un Fichier</button>
                                    
                                    <div class="file-preview" id="file-preview"></div>
                                </div>
                            </div>
                        </div>

                        <div class="preview-column">
                            <div class="preview-card">
                                <div class="preview-shine-1"></div>
                                <div class="preview-shine-2"></div>
                                <h3 class="preview-title">Aperçu de l'Événement</h3>
                                <div class="preview-content">
                                    <div class="preview-image" id="preview-image-container">
                                        <div class="preview-image-shine"></div>
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
                                <button type="submit" class="btn btn-primary">
                                    <div class="btn-gradient"></div>
                                    <span>Créer l'Événement</span>
                                </button>
                                <a href="home.php" class="btn btn-ghost">Annuler</a>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
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
                            <img src="${e.target.result}" alt="Aperçu">
                            <div class="file-name">${file.name}</div>
                        `;
                        
                        const previewContainer = document.getElementById('preview-image-container');
                        previewContainer.innerHTML = `
                            <div class="preview-image-shine"></div>
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