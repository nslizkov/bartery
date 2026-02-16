<?php

$user = requireAuth();

// GET /api/video-calls - my calls
if (!$id && $method === 'GET') {
    $stmt = $db->prepare('
        SELECT vc.id, vc.caller_id, vc.callee_id, vc.started_at, vc.ended_at, vc.duration, vc.status,
            u.username as other_username, u.full_name as other_name, u.avatar_url as other_avatar
        FROM video_calls vc
        JOIN users u ON u.id = CASE WHEN vc.caller_id = ? THEN vc.callee_id ELSE vc.caller_id END
        WHERE vc.caller_id = ? OR vc.callee_id = ?
        ORDER BY vc.started_at DESC
        LIMIT 50
    ');
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    jsonResponse(['calls' => $stmt->fetchAll()]);
}

// POST /api/video-calls - start call
if (!$id && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['callee_id'])) {
        jsonResponse(['error' => 'callee_id required'], 400);
    }
    $calleeId = (int) $data['callee_id'];
    if ($calleeId === $user['id']) {
        jsonResponse(['error' => 'Cannot call yourself'], 400);
    }
    $db->prepare('INSERT INTO video_calls (caller_id, callee_id, status) VALUES (?, ?, ?)')
        ->execute([$user['id'], $calleeId, 'pending']);
    $id = (int) $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$id]);
    jsonResponse(['call' => $stmt->fetch()], 201);
}

// PATCH /api/video-calls/{id} - update status (active, completed, cancelled)
if (is_numeric($id) && $method === 'PATCH') {
    $callId = (int) $id;
    $data = getJsonBody();
    if (!$data || empty($data['status'])) {
        jsonResponse(['error' => 'status required'], 400);
    }
    $status = $data['status'];
    if (!in_array($status, ['active', 'completed', 'cancelled'])) {
        jsonResponse(['error' => 'Invalid status'], 400);
    }
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ? AND (caller_id = ? OR callee_id = ?)');
    $stmt->execute([$callId, $user['id'], $user['id']]);
    $call = $stmt->fetch();
    if (!$call) {
        jsonResponse(['error' => 'Call not found'], 404);
    }
    if ($status === 'completed') {
        $ended = date('Y-m-d H:i:s');
        $duration = isset($call['started_at']) ? (strtotime($ended) - strtotime($call['started_at'])) : 0;
        $db->prepare('UPDATE video_calls SET status = ?, ended_at = ?, duration = ? WHERE id = ?')
            ->execute([$status, $ended, $duration, $callId]);
    } else {
        $db->prepare('UPDATE video_calls SET status = ? WHERE id = ?')->execute([$status, $callId]);
    }
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    jsonResponse(['call' => $stmt->fetch()]);
}

jsonResponse(['error' => 'Not found'], 404);
