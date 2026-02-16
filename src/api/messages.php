<?php

$user = requireAuth();

// GET /api/messages - list conversations (pagination: limit, offset)
if (!$id && $method === 'GET') {
    $limit = min(50, max(1, getInt($_GET ?? [], 'limit', 50)));
    $offset = max(0, getInt($_GET ?? [], 'offset', 0));
    $stmt = $db->prepare('
        SELECT
            other.id, other.username, other.full_name, other.avatar_url,
            (SELECT content FROM messages m2 WHERE ((m2.sender_id = ? AND m2.receiver_id = other.id) OR (m2.sender_id = other.id AND m2.receiver_id = ?)) ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages m2 WHERE ((m2.sender_id = ? AND m2.receiver_id = other.id) OR (m2.sender_id = other.id AND m2.receiver_id = ?)) ORDER BY m2.created_at DESC LIMIT 1) as last_at,
            (SELECT COUNT(*) FROM messages m WHERE m.receiver_id = ? AND m.sender_id = other.id AND m.is_read = 0) as unread
        FROM (
            SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id
            FROM messages WHERE sender_id = ? OR receiver_id = ?
        ) conv
        JOIN users other ON other.id = conv.other_id
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $limit, $offset]);
    $conversations = $stmt->fetchAll();
    $totalStmt = $db->prepare('
        SELECT COUNT(*) as total FROM (
            SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id
            FROM messages WHERE sender_id = ? OR receiver_id = ?
        ) c
    ');
    $totalStmt->execute([$user['id'], $user['id'], $user['id']]);
    $total = (int) $totalStmt->fetch()['total'];
    jsonResponse(['conversations' => $conversations, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
}

// GET /api/messages/{userId} - messages with user
if (is_numeric($id) && !$sub && $method === 'GET') {
    $otherId = (int) $id;
    $stmt = $db->prepare('
        SELECT m.id, m.sender_id, m.receiver_id, m.content, m.is_read, m.created_at
        FROM messages m
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ');
    $stmt->execute([$user['id'], $otherId, $otherId, $user['id']]);
    $messages = $stmt->fetchAll();
    $db->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?')->execute([$user['id'], $otherId]);
    jsonResponse(['messages' => $messages]);
}

// POST /api/messages - send message
if (!$id && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['receiver_id']) || empty($data['content'])) {
        jsonResponse(['error' => 'receiver_id and content required'], 400);
    }
    $receiverId = (int) $data['receiver_id'];
    $content = trim($data['content']);
    if (strlen($content) > 5000) {
        jsonResponse(['error' => 'Message too long'], 400);
    }
    if ($receiverId === $user['id']) {
        jsonResponse(['error' => 'Cannot send message to yourself'], 400);
    }
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$receiverId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'User not found'], 404);
    }
    $db->prepare('INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)')
        ->execute([$user['id'], $receiverId, $content]);
    $msgId = (int) $db->lastInsertId();
    $stmt = $db->prepare('SELECT id, sender_id, receiver_id, content, is_read, created_at FROM messages WHERE id = ?');
    $stmt->execute([$msgId]);
    jsonResponse(['message' => $stmt->fetch()], 201);
}

jsonResponse(['error' => 'Not found'], 404);
