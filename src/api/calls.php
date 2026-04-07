<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../PushNotification.php';

$user = requireAuth();

/**
 * Log video call events to dedicated log file
 */
function logVideoCall(string $event, array $data = [], array $user = []): void
{
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/video_calls.log';
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? null,
        'data' => $data,
    ];
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// POST /api/call/start - Start a new call
if (!$id && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['callee_id'])) {
        logVideoCall('start_validation_error', ['error' => 'callee_id required', 'body' => $data], $user);
        jsonResponse(['error' => 'callee_id required'], 400);
    }

    $calleeId = (int) $data['callee_id'];
    if ($calleeId === $user['id']) {
        logVideoCall('start_self_call_error', ['caller_id' => $user['id'], 'callee_id' => $calleeId], $user);
        jsonResponse(['error' => 'Cannot call yourself'], 400);
    }

    // Verify callee exists
    $stmt = $db->prepare('SELECT id, username, full_name FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$calleeId]);
    $callee = $stmt->fetch();
    if (!$callee) {
        logVideoCall('start_callee_not_found', ['callee_id' => $calleeId], $user);
        jsonResponse(['error' => 'Callee not found'], 404);
    }

    // Generate room name
    $roomName = 'BarteryCall_' . $user['id'] . '_' . $calleeId . '_' . date('YmdHis');

    // Create call record
    $stmt = $db->prepare('
        INSERT INTO video_calls (caller_id, callee_id, room_name, status)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$user['id'], $calleeId, $roomName, 'pending']);
    $callId = (int) $db->lastInsertId();

    logVideoCall('call_started', [
        'call_id' => $callId,
        'caller_id' => $user['id'],
        'caller_username' => $user['username'],
        'callee_id' => $calleeId,
        'callee_username' => $callee['username'],
        'room_name' => $roomName,
    ], $user);

    // Get the created call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    // Send push notification to callee
    $push = PushNotification::getInstance();
    $pushResult = $push->sendIncomingCallNotification(
        $calleeId,
        $user['id'],
        $user['username'],
        $callId,
        $roomName
    );
    
    logVideoCall('fcm_incoming_call_notification', [
        'call_id' => $callId,
        'target_user_id' => $calleeId,
        'result' => $pushResult,
    ], $user);

    jsonResponse(['call' => $call], 201);
}

// POST /api/call/join - Accept a call
if ($sub === 'join' && $method === 'POST') {
    if (!is_numeric($id)) {
        logVideoCall('join_validation_error', ['error' => 'Call ID required', 'call_id' => $id], $user);
        jsonResponse(['error' => 'Call ID required'], 400);
    }

    $callId = (int) $id;

    logVideoCall('join_attempt', [
        'call_id' => $callId,
        'user_id' => $user['id'],
        'username' => $user['username'],
    ], $user);

    // Get the call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    if (!$call) {
        logVideoCall('join_call_not_found', ['call_id' => $callId], $user);
        jsonResponse(['error' => 'Call not found'], 404);
    }

    // Only callee can join
    if ((int) $call['callee_id'] !== $user['id']) {
        logVideoCall('join_unauthorized', [
            'call_id' => $callId,
            'user_id' => $user['id'],
            'expected_callee_id' => $call['callee_id'],
        ], $user);
        jsonResponse(['error' => 'Only callee can join this call'], 403);
    }

    // Check if call is still pending
    if ($call['status'] !== 'pending') {
        logVideoCall('join_wrong_status', [
            'call_id' => $callId,
            'current_status' => $call['status'],
            'expected_status' => 'pending',
        ], $user);
        jsonResponse(['error' => 'Call is not in pending status'], 400);
    }

    // Update call status to active
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE video_calls SET status = ?, started_at = ? WHERE id = ?');
    $stmt->execute(['active', $now, $callId]);

    logVideoCall('call_accepted', [
        'call_id' => $callId,
        'caller_id' => $call['caller_id'],
        'callee_id' => $call['callee_id'],
        'callee_username' => $user['username'],
        'room_name' => $call['room_name'],
    ], $user);

    // Get updated call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    // Notify caller that call was accepted
    $push = PushNotification::getInstance();
    $pushResult = $push->sendCallAcceptedNotification(
        (int) $call['caller_id'],
        $user['username'],
        $callId,
        (string) $call['room_name']
    );
    
    logVideoCall('fcm_call_accepted_notification', [
        'call_id' => $callId,
        'target_user_id' => $call['caller_id'],
        'result' => $pushResult,
    ], $user);

    jsonResponse(['call' => $call], 200);
}

// POST /api/call/cancel - Cancel or decline a call
if ($sub === 'cancel' && $method === 'POST') {
    if (!is_numeric($id)) {
        logVideoCall('cancel_validation_error', ['error' => 'Call ID required', 'call_id' => $id], $user);
        jsonResponse(['error' => 'Call ID required'], 400);
    }

    $callId = (int) $id;

    logVideoCall('cancel_attempt', [
        'call_id' => $callId,
        'user_id' => $user['id'],
        'username' => $user['username'],
    ], $user);

    // Get the call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    if (!$call) {
        logVideoCall('cancel_call_not_found', ['call_id' => $callId], $user);
        jsonResponse(['error' => 'Call not found'], 404);
    }

    // Only caller or callee can cancel
    if ((int) $call['caller_id'] !== $user['id'] && (int) $call['callee_id'] !== $user['id']) {
        logVideoCall('cancel_unauthorized', [
            'call_id' => $callId,
            'user_id' => $user['id'],
            'caller_id' => $call['caller_id'],
            'callee_id' => $call['callee_id'],
        ], $user);
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    // Check if call can be cancelled
    if (!in_array($call['status'], ['pending', 'active'])) {
        logVideoCall('cancel_wrong_status', [
            'call_id' => $callId,
            'current_status' => $call['status'],
        ], $user);
        jsonResponse(['error' => 'Call cannot be cancelled'], 400);
    }

    // Determine who is cancelling
    $isCallee = (int) $call['callee_id'] === $user['id'];
    $otherUserId = $isCallee ? (int) $call['caller_id'] : (int) $call['callee_id'];
    $cancelReason = $isCallee ? 'callee_declined' : 'caller_cancelled';

    logVideoCall('call_cancelled', [
        'call_id' => $callId,
        'cancelled_by' => $isCallee ? 'callee' : 'caller',
        'cancelled_by_user_id' => $user['id'],
        'cancelled_by_username' => $user['username'],
        'other_user_id' => $otherUserId,
        'reason' => $cancelReason,
        'call_status' => $call['status'],
    ], $user);

    // Update call status to cancelled
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE video_calls SET status = ?, ended_at = ? WHERE id = ?');
    $stmt->execute(['cancelled', $now, $callId]);

    // Get updated call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    // Notify the other user about cancellation
    $push = PushNotification::getInstance();
    if ($isCallee) {
        // Callee declined - notify caller
        $pushResult = $push->sendCallDeclinedNotification(
            $otherUserId,
            $user['username'],
            $callId
        );
        logVideoCall('fcm_call_declined_notification', [
            'call_id' => $callId,
            'target_user_id' => $otherUserId,
            'callee_username' => $user['username'],
            'result' => $pushResult,
        ], $user);
    } else {
        // Caller cancelled - notify callee
        $pushResult = $push->sendCallCancelledByCallerNotification(
            $otherUserId,
            $user['username'],
            $callId
        );
        logVideoCall('fcm_call_cancelled_by_caller_notification', [
            'call_id' => $callId,
            'target_user_id' => $otherUserId,
            'caller_username' => $user['username'],
            'result' => $pushResult,
        ], $user);
    }
    
    // Also notify the cancelling user about their own cancellation
    try {
        $pushResult = $push->sendCallCancelledByCallerNotification(
            $user['id'],
            $user['username'],
            $callId
        );
        logVideoCall('fcm_call_cancelled_self_notification', [
            'call_id' => $callId,
            'target_user_id' => $user['id'],
            'username' => $user['username'],
            'result' => $pushResult,
        ], $user);
    } catch (Exception $pushEx) {
        logApiError('Push notification failed (cancel self)', [
            'error' => $pushEx->getMessage(),
            'user_id' => $user['id'],
            'call_id' => $callId,
        ]);
    }

    jsonResponse(['call' => $call], 200);
}

// POST /api/call/end - End a call
if ($sub === 'end' && $method === 'POST') {
    if (!is_numeric($id)) {
        logVideoCall('end_validation_error', ['error' => 'Call ID required', 'call_id' => $id], $user);
        jsonResponse(['error' => 'Call ID required'], 400);
    }

    $callId = (int) $id;

    logVideoCall('end_attempt', [
        'call_id' => $callId,
        'user_id' => $user['id'],
        'username' => $user['username'],
    ], $user);

    // Get the call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    if (!$call) {
        logVideoCall('end_call_not_found', ['call_id' => $callId], $user);
        jsonResponse(['error' => 'Call not found'], 404);
    }

    // Only caller or callee can end
    if ((int) $call['caller_id'] !== $user['id'] && (int) $call['callee_id'] !== $user['id']) {
        logVideoCall('end_unauthorized', [
            'call_id' => $callId,
            'user_id' => $user['id'],
            'caller_id' => $call['caller_id'],
            'callee_id' => $call['callee_id'],
        ], $user);
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    // Check if call can be ended
    if ($call['status'] !== 'active') {
        logVideoCall('end_wrong_status', [
            'call_id' => $callId,
            'current_status' => $call['status'],
        ], $user);
        jsonResponse(['error' => 'Call is not active'], 400);
    }

    // Calculate duration
    $endedAt = date('Y-m-d H:i:s');
    $startedAt = $call['started_at'];
    $duration = $startedAt ? (strtotime($endedAt) - strtotime($startedAt)) : 0;

    logVideoCall('call_ended', [
        'call_id' => $callId,
        'ended_by_user_id' => $user['id'],
        'ended_by_username' => $user['username'],
        'caller_id' => $call['caller_id'],
        'callee_id' => $call['callee_id'],
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'duration_seconds' => $duration,
    ], $user);

    // Update call
    $stmt = $db->prepare('UPDATE video_calls SET status = ?, ended_at = ?, duration = ? WHERE id = ?');
    $stmt->execute(['completed', $endedAt, $duration, $callId]);

    // Get updated call
    $stmt = $db->prepare('SELECT * FROM video_calls WHERE id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();

    // Notify both users about call end
    $push = PushNotification::getInstance();
    $callerId = (int) $call['caller_id'];
    $calleeId = (int) $call['callee_id'];

    // Notify caller (if not the one who ended)
    if ($callerId !== $user['id']) {
        $pushResult = $push->sendCallEndedNotification(
            $callerId,
            $user['username'],
            $callId,
            $duration
        );
        logVideoCall('fcm_call_ended_notification', [
            'call_id' => $callId,
            'target_user_id' => $callerId,
            'ended_by_username' => $user['username'],
            'duration' => $duration,
            'result' => $pushResult,
        ], $user);
    }

    // Notify callee (if not the one who ended)
    if ($calleeId !== $user['id']) {
        $pushResult = $push->sendCallEndedNotification(
            $calleeId,
            $user['username'],
            $callId,
            $duration
        );
        logVideoCall('fcm_call_ended_notification', [
            'call_id' => $callId,
            'target_user_id' => $calleeId,
            'ended_by_username' => $user['username'],
            'duration' => $duration,
            'result' => $pushResult,
        ], $user);
    }

    jsonResponse(['call' => $call], 200);
}

// GET /api/calls/{id} - Get call info by ID
if (is_numeric($id) && $method === 'GET') {
    $callId = (int) $id;

    $stmt = $db->prepare('
        SELECT vc.*, 
            caller.username as caller_username, caller.full_name as caller_name, caller.avatar_url as caller_avatar,
            callee.username as callee_username, callee.full_name as callee_name, callee.avatar_url as callee_avatar
        FROM video_calls vc
        JOIN users caller ON caller.id = vc.caller_id
        JOIN users callee ON callee.id = vc.callee_id
        WHERE vc.id = ? AND (vc.caller_id = ? OR vc.callee_id = ?)
    ');
    $stmt->execute([$callId, $user['id'], $user['id']]);
    $call = $stmt->fetch();

    if (!$call) {
        jsonResponse(['error' => 'Call not found'], 404);
    }

    jsonResponse(['call' => $call], 200);
}

// GET /api/calls/user/{userId} - Get all calls for a user
if ($sub === 'user' && is_numeric($id) && $method === 'GET') {
    $targetUserId = (int) $id;

    // Check if user exists
    $stmt = $db->prepare('SELECT id, username, full_name FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) {
        jsonResponse(['error' => 'User not found'], 404);
    }

    // Get calls (only if authenticated user is involved or is admin)
    if ($user['id'] !== $targetUserId && $user['role'] !== 'admin') {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $stmt = $db->prepare('
        SELECT vc.*, 
            caller.username as caller_username, caller.full_name as caller_name, caller.avatar_url as caller_avatar,
            callee.username as callee_username, callee.full_name as callee_name, callee.avatar_url as callee_avatar
        FROM video_calls vc
        JOIN users caller ON caller.id = vc.caller_id
        JOIN users callee ON callee.id = vc.callee_id
        WHERE vc.caller_id = ? OR vc.callee_id = ?
        ORDER BY vc.started_at DESC
        LIMIT 100
    ');
    $stmt->execute([$targetUserId, $targetUserId]);
    $calls = $stmt->fetchAll();

    jsonResponse(['calls' => $calls], 200);
}

jsonResponse(['error' => 'Not found'], 404);
