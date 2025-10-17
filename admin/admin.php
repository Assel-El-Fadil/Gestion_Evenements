<?php
session_start();
require_once '../database.php';

$is_admin = true;
if (!$is_admin) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit();
}

$conn = db_connect();
$conn->set_charset('utf8mb4');

$success_message = '';
$error_message = '';

$users_per_page = 30;
$current_page = max(1, intval($_GET['page'] ?? 1));
$search_query = trim($_GET['search'] ?? '');
$selected_club = intval($_GET['club'] ?? 0);

function sanitizeInt($value) {
    return intval($value ?? 0);
}

function isUserOrganizerInAnyClub($conn, $userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Adherence WHERE idUtilisateur = ? AND position = 'organisateur'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count > 0;
}

function updateUserRole($conn, $userId, $newRole) {
    $stmt = $conn->prepare("UPDATE Utilisateur SET role = ? WHERE idUtilisateur = ?");
    $stmt->bind_param('si', $newRole, $userId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete_user') {
            $uid = sanitizeInt($_POST['idUtilisateur'] ?? 0);
            if ($uid > 0) {
                $stmt = $conn->prepare('DELETE FROM Utilisateur WHERE idUtilisateur = ?');
                $stmt->bind_param('i', $uid);
                if ($stmt->execute()) {
                    $success_message = "Utilisateur supprimé avec succès.";
                } else {
                    throw new Exception('Erreur lors de la suppression de l\'utilisateur');
                }
                $stmt->close();
            }
        }

        if ($action === 'toggle_role') {
            $uid = sanitizeInt($_POST['idUtilisateur'] ?? 0);
            $cid = sanitizeInt($_POST['idClub'] ?? 0);
            $new_role = ($_POST['new_role'] ?? '') === 'organisateur' ? 'organisateur' : 'membre';
            if ($uid > 0 && $cid > 0) {
                $stmtC = $conn->prepare('SELECT position FROM Adherence WHERE idUtilisateur = ? AND idClub = ?');
                $stmtC->bind_param('ii', $uid, $cid);
                $stmtC->execute();
                $res = $stmtC->get_result();
                if ($res->num_rows > 0) {
                    $stmtU = $conn->prepare('UPDATE Adherence SET position = ? WHERE idUtilisateur = ? AND idClub = ?');
                    $stmtU->bind_param('sii', $new_role, $uid, $cid);
                    if ($stmtU->execute()) {
                        if ($new_role === 'organisateur') {
                            if (updateUserRole($conn, $uid, 'organisateur')) {
                                $success_message = "Utilisateur promu organisateur et rôle mis à jour en \"$new_role\".";
                            } else {
                                $success_message = "Rôle mis à jour en \"$new_role\" mais erreur lors de la mise à jour du rôle utilisateur.";
                            }
                        } else {
                            if (isUserOrganizerInAnyClub($conn, $uid)) {
                                $success_message = "Rôle mis à jour en \"$new_role\" (utilisateur reste organisateur dans d'autres clubs).";
                            } else {
                                if (updateUserRole($conn, $uid, 'utilisateur')) {
                                    $success_message = "Utilisateur rétrogradé et rôle mis à jour en \"utilisateur\".";
                                } else {
                                    $success_message = "Rôle mis à jour en \"$new_role\" mais erreur lors de la mise à jour du rôle utilisateur.";
                                }
                            }
                        }
                    } else {
                        throw new Exception('Erreur lors de la mise à jour du rôle');
                    }
                    $stmtU->close();
                } else {
                    throw new Exception("Le membre n'existe pas dans ce club");
                }
                $stmtC->close();
            }
        }

        if ($action === 'add_club') {
            $organisateur_email = trim($_POST['organisateur_email'] ?? '');
            $club_nom = trim($_POST['club_nom'] ?? '');
            if ($organisateur_email === '' || $club_nom === '') {
                throw new Exception("L'email de l'organisateur et le nom du club sont requis");
            }
            if (!filter_var($organisateur_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Veuillez saisir un email valide");
            }
            $user_stmt = $conn->prepare('SELECT idUtilisateur FROM Utilisateur WHERE email = ?');
            $user_stmt->bind_param('s', $organisateur_email);
            $user_stmt->execute();
            $user_res = $user_stmt->get_result();
            $user = $user_res->fetch_assoc();
            $user_stmt->close();
            if (!$user) {
                throw new Exception("Aucun utilisateur avec cet email");
            }
            $organisateur_id = intval($user['idUtilisateur']);

            $chk = $conn->prepare('SELECT 1 FROM Club WHERE nom = ?');
            $chk->bind_param('s', $club_nom);
            $chk->execute();
            $exists = $chk->get_result()->num_rows > 0;
            $chk->close();
            if ($exists) {
                throw new Exception('Un club avec ce nom existe déjà');
            }
            $stmt = $conn->prepare('INSERT INTO Club (nom,nbrMembres) VALUES (?, 1)');
            $stmt->bind_param('s', $club_nom);
            if ($stmt->execute()) {
                $new_club_id = intval($conn->insert_id);
                $memb = $conn->prepare("INSERT INTO Adherence (idUtilisateur, idClub, position) VALUES (?, ?, 'organisateur')");
                $memb->bind_param('ii', $organisateur_id, $new_club_id);
                $memb->execute();
                $memb->close();

                if (updateUserRole($conn, $organisateur_id, 'organisateur')) {
                    $success_message = "Club ajouté avec succès, organisateur associé et rôle mis à jour.";
                } else {
                    $success_message = "Club ajouté avec succès et organisateur associé (erreur lors de la mise à jour du rôle).";
                }
                $selected_club = $new_club_id;
            } else {
                throw new Exception("Erreur lors de l'ajout du club");
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$users = [];
$clubs_memberships = [];
$clubs = [];
$total_users = 0;
$total_pages = 0;

$result = $conn->query("SELECT idClub, nom FROM Club ORDER BY nom");
if ($result) {
    while ($row = $result->fetch_assoc()) { $clubs[] = $row; }
    $result->close();
}

if ($selected_club === 0 && !empty($clubs)) {
    $selected_club = intval($clubs[0]['idClub']);
}

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR filiere LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) . ' AND idUtilisateur != 1' : 'WHERE idUtilisateur != 1';

$count_sql = "SELECT COUNT(*) as total FROM Utilisateur $where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_users = $result->fetch_assoc()['total'];
    $stmt->close();
} else {
    $result = $conn->query($count_sql);
    $total_users = $result->fetch_assoc()['total'];
    $result->close();
}

$total_pages = ceil($total_users / $users_per_page);
$offset = ($current_page - 1) * $users_per_page;

$users_sql = "SELECT idUtilisateur, nom, prenom, email, filiere FROM Utilisateur $where_clause ORDER BY nom, prenom LIMIT ? OFFSET ?";
$params[] = $users_per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($users_sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $users[] = $row; }
$stmt->close();

$sql_members = "SELECT a.idUtilisateur, a.idClub, COALESCE(a.position, 'membre') AS position,
                       u.nom AS user_nom, u.prenom AS user_prenom, u.email AS user_email,
                       c.nom AS club_nom
                FROM Adherence a
                JOIN Utilisateur u ON u.idUtilisateur = a.idUtilisateur
                JOIN Club c ON c.idClub = a.idClub
                WHERE a.idClub = ?
                ORDER BY u.nom, u.prenom";
$stmt = $conn->prepare($sql_members);
$stmt->bind_param('i', $selected_club);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $clubs_memberships[] = $row; }
$stmt->close();

db_close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClubConnect - Administration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #000000;
            color: #ffffff;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .bg-gradient { position: fixed; inset: 0; background: linear-gradient(to bottom right, #000, rgba(17,24,39,0.5), #000); z-index: -2; }
        .orb { position: fixed; border-radius: 50%; filter: blur(96px); animation: pulse 4s cubic-bezier(0.4,0,0.6,1) infinite; z-index: -1; }
        .orb-1 { top: 25%; left: 25%; width: 384px; height: 384px; background-color: rgba(59,130,246,0.1); }
        .orb-2 { bottom: 25%; right: 25%; width: 384px; height: 384px; background-color: rgba(168,85,247,0.1); animation-delay: 1s; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .5; } }

        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar {
            width: 256px; height: 100vh;
            background: rgba(0,0,0,0.3); backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: none; border-top: none; border-bottom: none;
            border-radius: 0 1rem 1rem 0;
            padding: 1.5rem; display: flex; flex-direction: column;
            position: sticky; top: 0; z-index: 10;
        }
        .sidebar-header { margin-bottom: 2rem; }
        .sidebar-title { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: .25rem; }
        .sidebar-subtitle { font-size: .875rem; color: #9ca3af; }
        .sidebar-nav { flex: 1; display: flex; flex-direction: column; gap: .5rem; }
        .nav-item { display: flex; align-items: center; width: 100%; padding: .5rem 1rem; border-radius: .375rem; text-decoration: none; color: #d1d5db; font-size: 1rem; font-weight: 500; border: 1px solid transparent; transition: all .2s; gap: 0; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-item-active { background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
        .nav-icon { width: 1.25rem; height: 1.25rem; margin-right: .75rem; flex-shrink: 0; }
        .sidebar-profile { margin-top: auto; }
        .profile-card { background: rgba(255,255,255,0.05); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); border-radius: .75rem; padding: 1rem; display: flex; align-items: center; gap: .75rem; }
        .profile-avatar { width: 2.5rem; height: 2.5rem; background: linear-gradient(to right,#3b82f6,#9333ea); border-radius: 50%; display:flex; align-items:center; justify-content:center; }
        .profile-avatar span { color:#fff; font-weight:600; font-size:1rem; }
        .profile-name { color:#fff; font-weight:500; font-size:1rem; }
        .profile-department { color:#9ca3af; font-size:.875rem; }

        .main-content { flex: 1; padding: 1.5rem; overflow-y: auto; }
        .content-container { max-width: 80rem; margin: 0 auto; }
        .header { background: rgba(0,0,0,0.3); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .header-title { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: .25rem; }
        .header-subtitle { color: #9ca3af; }

        .section-card { background: rgba(255,255,255,0.05); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); border-radius: .5rem; padding: 1.25rem; margin-bottom: 1rem; }
        .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: .75rem; }
        .section-title { color:#fff; font-size:1.125rem; font-weight:600; }
        .muted { color:#9ca3af; font-size:.875rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { text-align: left; padding: .75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .table th { color:#d1d5db; font-weight:600; }
        .table tr:hover td { background: rgba(255,255,255,0.03); }

        .btn { padding: .5rem .875rem; border-radius: .375rem; font-size: .9rem; font-weight:500; cursor: pointer; border: 1px solid transparent; transition: all .2s; color:#fff; background: rgba(255,255,255,0.1); }
        .btn:hover { background: rgba(255,255,255,0.2); }
        .btn-danger { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.3); color:#fca5a5; }
        .btn-danger:hover { background: rgba(239,68,68,0.3); }
        .badge { display:inline-flex; align-items:center; padding:.2rem .5rem; border-radius:.4rem; font-size:.75rem; border:1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06); }

        select {
            background-color: rgba(17,24,39,0.95);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        select option {
            background-color: #111827;
            color: #ffffff;
        }
        select option:hover, select option:checked {
            background-color: #374151;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .dashboard { flex-direction: column; }
            .sidebar { width: 100%; height: auto; border-radius: 0 0 1rem 1rem; position: static; }
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
                <p class="sidebar-subtitle">Administration</p>
            </div>
            <nav class="sidebar-nav">
                <a href="#users" class="nav-item nav-item-active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 1 1 0 7.75"/></svg>
                    <span>Utilisateurs</span>
                </a>
                <a href="#clubs" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>
                    <span>Clubs</span>
                </a>
            </nav>
            <div class="sidebar-profile">
                <div class="profile-card">
                    <div class="profile-avatar"><span>AD</span></div>
                    <div class="profile-info">
                        <p class="profile-name">Admin</p>
                        <p class="profile-department">Contrôle</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-container">
                <div class="header">
                    <h2 class="header-title">Panneau d'Administration</h2>
                    <p class="header-subtitle">Gérez les utilisateurs et les clubs</p>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="section-card" style="border-color: rgba(34,197,94,0.3); background: rgba(34,197,94,0.1); color:#86efac;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="section-card" style="border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.1); color:#fca5a5;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <section id="users" class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">Utilisateurs</h3>
                        <span class="muted">Total: <?php echo $total_users; ?> | Page: <?php echo $current_page; ?>/<?php echo $total_pages; ?></span>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center;">
                        <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="search" placeholder="Rechercher par nom, email, filière..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   style="padding: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.375rem; background: rgba(255,255,255,0.1); color: #fff; min-width: 300px;">
                            <input type="hidden" name="club" value="<?php echo $selected_club; ?>">
                            <button type="submit" class="btn">Rechercher</button>
                            <?php if (!empty($search_query)): ?>
                                <a href="?club=<?php echo $selected_club; ?>" class="btn" style="text-decoration: none;">Effacer</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Filière</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) === 0): ?>
                                    <tr><td colspan="6" class="muted">Aucun utilisateur</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo intval($u['idUtilisateur']); ?></td>
                                            <td><?php echo htmlspecialchars($u['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($u['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['filiere'] ?? ''); ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="idUtilisateur" value="<?php echo intval($u['idUtilisateur']); ?>">
                                                    <button type="submit" class="btn btn-danger">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_query); ?>&club=<?php echo $selected_club; ?>" class="btn">‹ Précédent</a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?page=1&search=<?php echo urlencode($search_query); ?>&club=<?php echo $selected_club; ?>" class="btn">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="muted">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="btn" style="background: rgba(59,130,246,0.3); border-color: rgba(59,130,246,0.5);"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&club=<?php echo $selected_club; ?>" class="btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="muted">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search_query); ?>&club=<?php echo $selected_club; ?>" class="btn"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_query); ?>&club=<?php echo $selected_club; ?>" class="btn">Suivant ›</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section id="add-club" class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">Ajouter club</h3>
                        <span class="muted">Créer un nouveau club</span>
                    </div>
                    <form method="POST" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                        <input type="hidden" name="action" value="add_club">
                        <input type="text" name="club_nom" placeholder="Nom du club" required
                               style="padding: .5rem .75rem; min-width: 300px; border:1px solid rgba(255,255,255,0.2); border-radius:.375rem; background: rgba(255,255,255,0.1); color:#fff;">
                                <input type="email" name="organisateur_email" placeholder="Email de l'organisateur" required
                                style="padding: .5rem .75rem; min-width: 300px; border:1px solid rgba(255,255,255,0.2); border-radius:.375rem; background: rgba(255,255,255,0.1); color:#fff;">       
                        <button type="submit" class="btn" style="background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.3); color: #86efac;">Ajouter</button>
                    </form>
                </section>

                <section id="clubs" class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">Gestion des Clubs</h3>
                        <span class="muted">Membres listés: <?php echo count($clubs_memberships); ?></span>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                            <label for="club_select" style="color: #d1d5db; font-weight: 500;">Sélectionner un club:</label>
                            <select name="club" id="club_select" onchange="this.form.submit()" 
                                    style="padding: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.375rem; background: rgba(255,255,255,0.1); color: #fff; min-width: 200px;">
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?php echo $club['idClub']; ?>" <?php echo $selected_club == $club['idClub'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Club</th>
                                    <th>Utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($clubs_memberships) === 0): ?>
                                    <tr>
                                        <td colspan="5" class="muted">Aucun membre trouvé dans ce club</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clubs_memberships as $m): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($m['club_nom']); ?></td>
                                            <td><?php echo htmlspecialchars($m['user_nom'] . ' ' . $m['user_prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($m['user_email'] ?? ''); ?></td>
                                            <td>
                                                <span class="badge" style="<?php echo strtolower($m['position']) === 'organisateur' ? 'background: rgba(59,130,246,0.2); border-color: rgba(59,130,246,0.3); color: #93c5fd;' : ''; ?>">
                                                    <?php echo htmlspecialchars($m['position']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $next = strtolower($m['position']) === 'organisateur' ? 'membre' : 'organisateur'; ?>
                                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Changer le rôle de <?php echo htmlspecialchars($m['user_nom'] . ' ' . $m['user_prenom']); ?> en <?php echo $next; ?> ?');">
                                                    <input type="hidden" name="action" value="toggle_role">
                                                    <input type="hidden" name="idUtilisateur" value="<?php echo intval($m['idUtilisateur']); ?>">
                                                    <input type="hidden" name="idClub" value="<?php echo intval($m['idClub']); ?>">
                                                    <input type="hidden" name="new_role" value="<?php echo $next; ?>">
                                                    <button type="submit" class="btn" style="<?php echo $next === 'organisateur' ? 'background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.3); color: #86efac;' : 'background: rgba(156,163,175,0.2); border-color: rgba(156,163,175,0.3); color: #d1d5db;'; ?>">
                                                        <?php echo $next === 'organisateur' ? 'Promouvoir' : 'Rétrograder'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>