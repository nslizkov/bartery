<?php

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$stmt = $db->query('SELECT id, name, description FROM categories ORDER BY name');
$categories = $stmt->fetchAll();
jsonResponse(['categories' => $categories]);
