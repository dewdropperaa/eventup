<?php
require 'database.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

$pdo = getDatabaseConnection();

$sql = "
    SELECT
        id,
        titre,
        DATE_FORMAT(date, '%Y-%m-%d %H:%i:%s') AS date,
        lieu
    FROM events
    WHERE (
        LOWER(titre) LIKE :search_title
        OR LOWER(description) LIKE :search_description
        OR LOWER(lieu) LIKE :search_location
    )
    AND date >= NOW()
    ORDER BY date ASC
    LIMIT 20
";

$searchTerm = '%' . mb_strtolower($query, 'UTF-8') . '%';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':search_title' => $searchTerm,
    ':search_description' => $searchTerm,
    ':search_location' => $searchTerm,
]);

$events = $stmt->fetchAll();

echo json_encode($events, JSON_UNESCAPED_UNICODE);
?>
