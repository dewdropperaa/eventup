<?php
require 'database.php';

$pdo = getDatabaseConnection();
$stmt = $pdo->query("SELECT id, titre, description, lieu, date FROM events ORDER BY date ASC LIMIT 5");
$events = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
