<?php
require_once 'config.php';

header('Content-Type: application/json');

$suggestions = [];

if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    $search_query = $conn->real_escape_string($search_query);

    $sql_suggestions = "SELECT DISTINCT title FROM albums WHERE title LIKE '" . $search_query . "%' OR title LIKE '%" . $search_query . "%' LIMIT 10";
    $result = $conn->query($sql_suggestions);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row['title'];
        }
    }
}

$conn->close();

echo json_encode($suggestions);
?>