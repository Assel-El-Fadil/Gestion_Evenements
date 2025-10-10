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
    $club_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    switch ($method) {
        case 'GET':
            if ($club_id) {
                $stmt = $conn->prepare('SELECT * FROM club WHERE idClub = ?');
                $stmt->bind_param('i', $club_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($club = $result->fetch_assoc()) {
                    // count events
                    $ec = $conn->prepare('SELECT COUNT(*) as event_count FROM evenement WHERE idClub = ?');
                    $ec->bind_param('i', $club['idClub']);
                    $ec->execute();
                    $er = $ec->get_result();
                    $club['event_count'] = $er->fetch_assoc()['event_count'] ?? 0;
                    $ec->close();

                    // map id
                    $club['id'] = $club['idClub'];
                    unset($club['idClub']);
                    sendResponse($club);
                } else {
                    sendError('Club not found', 404);
                }
            } else {
                $result = $conn->query('SELECT * FROM club ORDER BY nom ASC');
                $clubs = [];
                while ($club = $result->fetch_assoc()) {
                    $ec = $conn->prepare('SELECT COUNT(*) as event_count FROM evenement WHERE idClub = ?');
                    $ec->bind_param('i', $club['idClub']);
                    $ec->execute();
                    $er = $ec->get_result();
                    $club['event_count'] = $er->fetch_assoc()['event_count'] ?? 0;
                    $ec->close();

                    $club['id'] = $club['idClub'];
                    unset($club['idClub']);
                    $clubs[] = $club;
                }
                sendResponse($clubs);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['nom'])) {
                sendError('Missing required fields: nom');
            }
            $nom = db_escape($input['nom']);
            $nbrMembres = intval($input['nbrMembres'] ?? 0);
            $stmt = $conn->prepare('INSERT INTO club (nom, nbrMembres) VALUES (?, ?)');
            $stmt->bind_param('si', $nom, $nbrMembres);
            if ($stmt->execute()) {
                sendResponse(['id' => $conn->insert_id, 'message' => 'Club created successfully'], 201);
            } else {
                sendError('Failed to create club: ' . $conn->error, 500);
            }
            break;

        case 'PUT':
            if (!$club_id) {
                sendError('Club ID required for update');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                sendError('Invalid JSON input');
            }
            $fields = [];
            $values = [];
            $types = '';
            foreach (['nom', 'nbrMembres'] as $field) {
                if (array_key_exists($field, $input)) {
                    $fields[] = "$field = ?";
                    if ($field === 'nbrMembres') {
                        $values[] = intval($input[$field]);
                        $types .= 'i';
                    } else {
                        $values[] = db_escape($input[$field]);
                        $types .= 's';
                    }
                }
            }
            if (empty($fields)) {
                sendError('No valid fields to update');
            }
            $values[] = $club_id;
            $types .= 'i';
            $sql = 'UPDATE club SET ' . implode(', ', $fields) . ' WHERE idClub = ?';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                if ($stmt->affected_rows >= 0) {
                    sendResponse(['message' => 'Club updated successfully']);
                } else {
                    sendError('Club not found or no changes made', 404);
                }
            } else {
                sendError('Failed to update club: ' . $conn->error, 500);
            }
            break;

        case 'DELETE':
            if (!$club_id) {
                sendError('Club ID required for deletion');
            }
            $ec = $conn->prepare('SELECT COUNT(*) as cnt FROM evenement WHERE idClub = ?');
            $ec->bind_param('i', $club_id);
            $ec->execute();
            $er = $ec->get_result();
            $cnt = $er->fetch_assoc()['cnt'] ?? 0;
            $ec->close();
            if ($cnt > 0) {
                sendError('Cannot delete club with existing events. Delete events first.', 400);
            }
            $stmt = $conn->prepare('DELETE FROM club WHERE idClub = ?');
            $stmt->bind_param('i', $club_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    sendResponse(['message' => 'Club deleted successfully']);
                } else {
                    sendError('Club not found', 404);
                }
            } else {
                sendError('Failed to delete club: ' . $conn->error, 500);
            }
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


