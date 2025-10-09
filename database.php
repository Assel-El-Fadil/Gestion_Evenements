<?php

$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'clubevents';

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

?>