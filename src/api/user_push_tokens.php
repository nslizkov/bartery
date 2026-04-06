<?php

$user = requireAuth();

// GET /api/push-tokens - get my push tokens
if (!$id && $method === 'GET') {
    $stmt = $db->prepare('SELECT * FROM user_push_tokens WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$user['id']]);
    jsonResponse(['tokens' => $stmt->fetchAll()]);
}

// POST /api/push-tokens - register or update push token
if (!$id && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['push_token'])) {
        jsonResponse(['error' => 'push_token required'], 400);
    }

    $pushToken = $data['push_token'];
    $platform = isset($data['platform']) ? $data['platform'] : 'android';
    $deviceName = isset($data['device_name']) ? $data['device_name'] : null;
    $deviceId = isset($data['device_id']) ? $data['device_id'] : null;

    // Validate platform
    if (!in_array($platform, ['android', 'ios', 'web'])) {
        jsonResponse(['error' => 'Invalid platform. Must be android, ios, or web'], 400);
    }

    // Check if token already exists for this user (update)
    $stmt = $db->prepare('SELECT id FROM user_push_tokens WHERE user_id = ? AND push_token = ?');
    $stmt->execute([$user['id'], $pushToken]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing token
        $stmt = $db->prepare('
            UPDATE user_push_tokens
            SET platform = ?, device_name = ?, device_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $stmt->execute([$platform, $deviceName, $deviceId, $existing['id']]);
        jsonResponse(['message' => 'Token updated']);
    } else {
        // Insert new token
        $stmt = $db->prepare('
            INSERT INTO user_push_tokens (user_id, push_token, platform, device_name, device_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$user['id'], $pushToken, $platform, $deviceName, $deviceId]);
        jsonResponse(['message' => 'Token registered', 'id' => (int)$db->lastInsertId()], 201);
    }
}

// DELETE /api/push-tokens/{id} - delete a push token
if (is_numeric($id) && $method === 'DELETE') {
    $tokenId = (int) $id;
    $stmt = $db->prepare('SELECT id FROM user_push_tokens WHERE id = ? AND user_id = ?');
    $stmt->execute([$tokenId, $user['id']]);
    $token = $stmt->fetch();

    if (!$token) {
        jsonResponse(['error' => 'Token not found'], 404);
    }

    $stmt = $db->prepare('DELETE FROM user_push_tokens WHERE id = ?');
    $stmt->execute([$tokenId]);
    jsonResponse(['message' => 'Token deleted']);
}

jsonResponse(['error' => 'Not found'], 404);
