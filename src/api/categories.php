<?php

require_once __DIR__ . '/../helpers.php';

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $stmt = $db->query('SELECT id, name, description FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
    jsonResponse(['categories' => $categories]);
} catch (Exception $e) {
    logApiError('Error fetching categories', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    jsonResponse(['error' => 'Failed to fetch categories'], 500);
}
