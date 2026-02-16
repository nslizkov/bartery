<?php

$action = $id ?? '';

if ($action === 'register' && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        jsonResponse(['error' => 'username, email and password required'], 400);
    }
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $fullName = trim($data['full_name'] ?? '');
    try {
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, $passwordHash, $fullName]);
        $userId = (int) $db->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $db->prepare('UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?')->execute([$token, $userId]);
        $stmt = $db->prepare('SELECT id, username, email, full_name, bio, avatar_url, role, points FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        jsonResponse(['user' => $user, 'token' => $token], 201);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonResponse(['error' => 'Username or email already exists'], 409);
        }
        throw $e;
    }
}

if ($action === 'login' && $method === 'POST') {
    $data = getJsonBody();
    if (!$data || empty($data['email']) || empty($data['password'])) {
        jsonResponse(['error' => 'email and password required'], 400);
    }
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([trim($data['email'])]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($data['password'], $row['password_hash'])) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    $token = bin2hex(random_bytes(32));
    $db->prepare('UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?')->execute([$token, $row['id']]);
    $stmt = $db->prepare('SELECT id, username, email, full_name, bio, avatar_url, role, points FROM users WHERE id = ?');
    $stmt->execute([$row['id']]);
    $user = $stmt->fetch();
    jsonResponse(['user' => $user, 'token' => $token]);
}

if ($action === 'logout' && $method === 'POST') {
    $user = getAuthUser();
    if ($user) {
        $db->prepare('UPDATE users SET api_token = NULL WHERE id = ?')->execute([$user['id']]);
    }
    jsonResponse(['message' => 'Logged out']);
}

jsonResponse(['error' => 'Not found'], 404);
