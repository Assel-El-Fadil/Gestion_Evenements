<?php

require_once "configure.php";

$db_connection = null;

function db_connect() {
    global $db_connection, $db_host, $db_username, $db_password, $db_name;
    
    if ($db_connection === null) {
        $db_connection = new mysqli($db_host, $db_username, $db_password, $db_name);
        
        if ($db_connection->connect_error) {
            die("Erreur de connexion à la base de données: " . $db_connection->connect_error);
        }
        
        $db_connection->set_charset("utf8mb4");
    }
    
    return $db_connection;
}

function db_close() {
    global $db_connection;
    
    if ($db_connection !== null) {
        $db_connection->close();
        $db_connection = null;
    }
}

function db_escape($string) {
    $conn = db_connect();
    return $conn->real_escape_string($string);
}

function get_upcoming_events($limit = 5) {
    $conn = db_connect();
    
    $sql = "SELECT e.*, c.nom as club_nom 
            FROM evenement e 
            JOIN Club c ON e.idClub = c.idClub 
            WHERE e.date >= CURDATE() 
            ORDER BY e.date ASC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur de préparation: " . $conn->error);
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    $stmt->close();
    return $events;
}

function get_user_clubs($user_id, $limit = 2) {
    $conn = db_connect();
    
    $sql = "SELECT c.*, a.position 
            FROM Club c 
            NATURAL JOIN adherence a
            WHERE a.idUtilisateur = ? 
            ORDER BY c.nom ASC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur de préparation: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clubs = [];
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
    
    $stmt->close();
    return $clubs;
}

function get_club_event_count($club_id) {
    $conn = db_connect();
    
    $sql = "SELECT COUNT(*) as event_count 
            FROM evenement 
            WHERE idClub = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur de préparation: " . $conn->error);
    }
    
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['event_count'];
}

?>