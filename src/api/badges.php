<?php

require_once __DIR__ . '/../helpers.php';

// GET /api/badges - list all badges
if (!$id && $method === 'GET') {
    try {
        $stmt = $db->query('SELECT id, name, description, image_url, criteria FROM badges ORDER BY name');
        jsonResponse(['badges' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        logApiError('Error fetching badges list', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        jsonResponse(['error' => 'Failed to fetch badges'], 500);
    }
}

// GET /api/badges/user/{userId} - user's badges
if ($id === 'user' && is_numeric($sub) && $method === 'GET') {
    try {
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
    } catch (Exception $e) {
        logApiError('Error fetching user badges', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => $userId ?? null,
        ]);
        jsonResponse(['error' => 'Failed to fetch user badges'], 500);
    }
}

jsonResponse(['error' => 'Not found'], 404);