<?php

// GET /api/users - list all users (auth required, pagination: limit, offset)
if (!$id && $method === 'GET') {
    requireAuth();
    $limit = min(100, max(1, getInt($_GET ?? [], 'limit', 50)));
    $offset = max(0, getInt($_GET ?? [], 'offset', 0));
    $totalStmt = $db->query('SELECT COUNT(*) as total FROM users WHERE is_active = 1');
    $total = (int) $totalStmt->fetch()['total'];
    $stmt = $db->prepare('
        SELECT id, username, full_name, bio, avatar_url, points, created_at
        FROM users WHERE is_active = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?
    ');
    $stmt->execute([$limit, $offset]);
    jsonResponse(['users' => $stmt->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
}

// GET /api/users/me - current user
if ($id === 'me' && $method === 'GET') {
    $user = requireAuth();
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.email, u.full_name, u.bio, u.avatar_url, u.role, u.points, u.created_at,
            (SELECT COUNT(*) FROM user_skills WHERE user_id = u.id AND type = "teach") as teach_count,
            (SELECT COUNT(*) FROM user_skills WHERE user_id = u.id AND type = "learn") as learn_count
        FROM users u WHERE u.id = ?
    ');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
    $stmt = $db->prepare('
        SELECT us.skill_id, us.type, us.proficiency_level, us.description, s.name as skill_name, c.name as category_name
        FROM user_skills us
        JOIN skills s ON us.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE us.user_id = ?
    ');
    $stmt->execute([$user['id']]);
    $profile['skills'] = $stmt->fetchAll();
    jsonResponse(['user' => $profile]);
}

// POST /api/users/me/avatar - upload avatar
if ($id === 'me' && $sub === 'avatar' && $method === 'POST') {
    $user = requireAuth();
    $file = $_FILES['avatar'] ?? $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'File upload required or upload failed'], 400);
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        jsonResponse(['error' => 'Only JPEG, PNG, GIF, WebP allowed'], 400);
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonResponse(['error' => 'File too large (max 2MB)'], 400);
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
    $uploadDir = __DIR__ . '/../../uploads/avatars';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = $user['id'] . '_' . time() . '.' . $ext;
    $path = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        jsonResponse(['error' => 'Failed to save file'], 500);
    }
    $avatarUrl = '/uploads/avatars/' . $filename;
    $stmt = $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
    $stmt->execute([$avatarUrl, $user['id']]);
    $stmt = $db->prepare('SELECT id, username, email, full_name, bio, avatar_url, role, points FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    jsonResponse(['user' => $stmt->fetch(), 'avatar_url' => $avatarUrl]);
}

// PUT /api/users/me - update profile
if ($id === 'me' && $method === 'PUT') {
    $user = requireAuth();
    $data = getJsonBody();
    if (!$data) {
        jsonResponse(['error' => 'Invalid body'], 400);
    }
    $updates = [];
    $params = [];
    $allowed = ['full_name', 'bio', 'avatar_url'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    if (empty($updates)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }
    $params[] = $user['id'];
    $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    $stmt = $db->prepare('SELECT id, username, email, full_name, bio, avatar_url, role, points FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    jsonResponse(['user' => $stmt->fetch()]);
}

// GET /api/users/me/skills - my skills
if ($id === 'me' && $sub === 'skills' && $method === 'GET') {
    $user = requireAuth();
    $stmt = $db->prepare('
        SELECT us.skill_id, us.type, us.proficiency_level, us.description, s.name as skill_name, c.name as category_name
        FROM user_skills us
        JOIN skills s ON us.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE us.user_id = ?
    ');
    $stmt->execute([$user['id']]);
    jsonResponse(['skills' => $stmt->fetchAll()]);
}

// POST /api/users/me/skills - add skill
if ($id === 'me' && $sub === 'skills' && $method === 'POST') {
    $user = requireAuth();
    $data = getJsonBody();
    if (!$data || empty($data['skill_id']) || empty($data['type']) || !in_array($data['type'], ['teach', 'learn'])) {
        jsonResponse(['error' => 'skill_id and type (teach|learn) required'], 400);
    }
    $skillId = (int) $data['skill_id'];
    $type = $data['type'];
    $level = getInt($data, 'proficiency_level', 1);
    $description = trim($data['description'] ?? '');
    try {
        $db->prepare('INSERT INTO user_skills (user_id, skill_id, type, proficiency_level, description) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE type = VALUES(type), proficiency_level = VALUES(proficiency_level), description = VALUES(description)')
            ->execute([$user['id'], $skillId, $type, $level, $description]);
        $stmt = $db->prepare('SELECT us.skill_id, us.type, us.proficiency_level, us.description, s.name as skill_name FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ? AND us.skill_id = ?');
        $stmt->execute([$user['id'], $skillId]);
        jsonResponse(['skill' => $stmt->fetch()], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Invalid skill_id'], 400);
    }
}

// DELETE /api/users/me/skills/{skillId}
if ($id === 'me' && $sub === 'skills' && $method === 'DELETE' && $subId) {
    $user = requireAuth();
    $skillId = (int) $subId;
    $db->prepare('DELETE FROM user_skills WHERE user_id = ? AND skill_id = ?')->execute([$user['id'], $skillId]);
    jsonResponse(['message' => 'Skill removed']);
}

// GET /api/users/search - find exchange partners
if ($id === 'search' && $method === 'GET') {
    $teachSkill = getInt($_GET ?? [], 'teach');
    $learnSkill = getInt($_GET ?? [], 'learn');
    if (!$teachSkill || !$learnSkill) {
        jsonResponse(['error' => 'teach and learn (skill ids) query params required'], 400);
    }
    // Users who teach "learnSkill" and want to learn "teachSkill"
    $stmt = $db->prepare('
        SELECT DISTINCT u.id, u.username, u.full_name, u.bio, u.avatar_url, u.points
        FROM users u
        JOIN user_skills t ON t.user_id = u.id AND t.skill_id = ? AND t.type = "teach"
        JOIN user_skills l ON l.user_id = u.id AND l.skill_id = ? AND l.type = "learn"
        WHERE u.is_active = 1
        LIMIT 50
    ');
    $stmt->execute([$learnSkill, $teachSkill]);
    $users = $stmt->fetchAll();
    foreach ($users as &$u) {
        $st = $db->prepare('SELECT s.name, us.type FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?');
        $st->execute([$u['id']]);
        $u['skills'] = $st->fetchAll();
    }
    jsonResponse(['users' => $users]);
}

// GET /api/users/{id} - public profile
if (is_numeric($id) && !$sub && $method === 'GET') {
    $userId = (int) $id;
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.full_name, u.bio, u.avatar_url, u.points, u.created_at
        FROM users u WHERE u.id = ? AND u.is_active = 1
    ');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        jsonResponse(['error' => 'User not found'], 404);
    }
    $stmt = $db->prepare('
        SELECT us.skill_id, us.type, us.proficiency_level, us.description, s.name as skill_name, c.name as category_name
        FROM user_skills us
        JOIN skills s ON us.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE us.user_id = ?
    ');
    $stmt->execute([$userId]);
    $profile['skills'] = $stmt->fetchAll();
    jsonResponse(['user' => $profile]);
}

jsonResponse(['error' => 'Not found'], 404);
