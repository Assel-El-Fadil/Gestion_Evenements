<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../database.php';

function sendResponse($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError(string $message, int $status = 400) {
    sendResponse(['error' => $message], $status);
}

try {
    $conn = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];
    $event_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    switch ($method) {
        case 'GET':
            if ($event_id) {
                $stmt = $conn->prepare("SELECT e.*, c.nom as club_nom FROM evenement e LEFT JOIN club c ON e.idClub = c.idClub WHERE e.idEvenement = ?");
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($event = $result->fetch_assoc()) {
                    $event['id'] = $event['idEvenement'];
                    $event['nom'] = $event['titre'];
                    $event['date_evenement'] = $event['date'];
                    $event['participants_inscrits'] = $event['nbrParticipants'];
                    $event['capacite_max'] = $event['capacité'];
                    $event['club_id'] = $event['idClub'];
                    $event_date = $event['date'];
                    $event['is_upcoming'] = $event_date >= date('Y-m-d');
                    $event['statut'] = $event['is_upcoming'] ? 'upcoming' : 'past';
                    unset($event['idEvenement'], $event['titre'], $event['date'], $event['nbrParticipants'], $event['capacité'], $event['idClub']);
                    sendResponse($event);
                } else {
                    sendError('Event not found', 404);
                }
            } else {
                $club_filter = isset($_GET['club_id']) ? intval($_GET['club_id']) : null;
                $status_filter = isset($_GET['status']) ? db_escape($_GET['status']) : null;
                $sql = "SELECT e.*, c.nom as club_nom FROM evenement e LEFT JOIN club c ON e.idClub = c.idClub";
                $conditions = [];
                $params = [];
                $types = '';
                if ($club_filter) { $conditions[] = 'e.idClub = ?'; $params[] = $club_filter; $types .= 'i'; }
                if ($status_filter) { $conditions[] = 'e.statut = ?'; $params[] = $status_filter; $types .= 's'; }
                if ($conditions) { $sql .= ' WHERE ' . implode(' AND ', $conditions); }
                $sql .= ' ORDER BY e.date ASC';
                $stmt = $conn->prepare($sql);
                if ($params) { $stmt->bind_param($types, ...$params); }
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
                sendResponse($events);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['nom']) || !isset($input['date_evenement']) || !isset($input['club_id'])) {
                sendError('Missing required fields: nom, date_evenement, club_id');
            }
            $titre = db_escape($input['nom']);
            $description = db_escape($input['description'] ?? '');
            $date = db_escape($input['date_evenement']);
            $lieu = db_escape($input['lieu'] ?? '');
            $idClub = intval($input['club_id']);
            $cap = intval($input['capacite_max'] ?? 0);
            $nbr = intval($input['participants_inscrits'] ?? 0);
            $photo = db_escape($input['photo'] ?? '');
            $ck = $conn->prepare('SELECT idClub FROM club WHERE idClub = ?');
            $ck->bind_param('i', $idClub);
            $ck->execute();
            if (!$ck->get_result()->fetch_assoc()) { sendError('Invalid club ID'); }
            $stmt = $conn->prepare('INSERT INTO evenement (titre, description, date, lieu, idClub, capacité, nbrParticipants, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssiiss', $titre, $description, $date, $lieu, $idClub, $cap, $nbr, $photo);
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $statutVal = ($date >= date('Y-m-d')) ? 'upcoming' : 'past';
                $upd = $conn->prepare('UPDATE evenement SET statut = ? WHERE idEvenement = ?');
                if ($upd) { $upd->bind_param('si', $statutVal, $new_id); $upd->execute(); }
                sendResponse(['id' => $new_id, 'message' => 'Event created successfully'], 201);
            } else {
                sendError('Failed to create event: ' . $conn->error, 500);
            }
            break;

        case 'PUT':
            if (!$event_id) { sendError('Event ID required for update'); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { sendError('Invalid JSON input'); }
            $fields = [];
            $values = [];
            $types = '';
            $map = ['nom' => 'titre', 'description' => 'description', 'date_evenement' => 'date', 'lieu' => 'lieu', 'club_id' => 'idClub', 'capacite_max' => 'capacité', 'participants_inscrits' => 'nbrParticipants', 'photo' => 'photo', 'statut' => 'statut'];
            foreach ($map as $in => $col) {
                if (array_key_exists($in, $input)) {
                    $fields[] = "$col = ?";
                    if (in_array($in, ['club_id', 'capacite_max', 'participants_inscrits'])) { $values[] = intval($input[$in]); $types .= 'i'; }
                    else { $values[] = db_escape($input[$in]); $types .= 's'; }
                }
            }
            if (!$fields) { sendError('No valid fields to update'); }
            $values[] = $event_id; $types .= 'i';
            $sql = 'UPDATE evenement SET ' . implode(', ', $fields) . ' WHERE idEvenement = ?';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) { sendResponse(['message' => 'Event updated successfully']); }
            else { sendError('Failed to update event: ' . $conn->error, 500); }
            break;

        case 'DELETE':
            if (!$event_id) { sendError('Event ID required for deletion'); }
            $stmt = $conn->prepare('DELETE FROM evenement WHERE idEvenement = ?');
            $stmt->bind_param('i', $event_id);
            if ($stmt->execute()) { sendResponse(['message' => 'Event deleted successfully']); }
            else { sendError('Failed to delete event: ' . $conn->error, 500); }
            break;

        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} finally {
    db_close();
}
?>


