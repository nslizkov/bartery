<?php

// GET /api/badges - list all badges
if (!$id && $method === 'GET') {
    $stmt = $db->query('SELECT id, name, description, image_url, criteria FROM badges ORDER BY name');
    jsonResponse(['badges' => $stmt->fetchAll()]);
}

// GET /api/badges/user/{userId} - user's badges
if ($id === 'user' && is_numeric($sub) && $method === 'GET') {
    $userId = (int) $sub;
    $stmt = $db->prepare('
        SELECT b.id, b.name, b.description, b.image_url, ub.awarded_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.awarded_at DESC
    ');
    $stmt->execute([$userId]);
    jsonResponse(['badges' => $stmt->fetchAll()]);
}

jsonResponse(['error' => 'Not found'], 404);