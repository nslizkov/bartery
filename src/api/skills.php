<?php

$categoryId = getInt($_GET ?? [], 'category_id');

if ($method === 'GET') {
    if ($categoryId) {
        $stmt = $db->prepare('SELECT s.id, s.name, s.description, s.category_id, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id WHERE s.category_id = ? ORDER BY s.name');
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $db->query('SELECT s.id, s.name, s.description, s.category_id, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id ORDER BY s.name');
    }
    $skills = $stmt->fetchAll();
    jsonResponse(['skills' => $skills]);
}

if ($method === 'POST') {
    $user = requireAuth();
    $data = getJsonBody();
    if (!$data || empty($data['name'])) {
        jsonResponse(['error' => 'name required'], 400);
    }
    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');
    $categoryId = getInt($data, 'category_id');
    try {
        $stmt = $db->prepare('INSERT INTO skills (name, description, category_id, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $description, $categoryId ?: null, $user['id']]);
        $id = (int) $db->lastInsertId();
        $stmt = $db->prepare('SELECT s.id, s.name, s.description, s.category_id, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id WHERE s.id = ?');
        $stmt->execute([$id]);
        jsonResponse(['skill' => $stmt->fetch()], 201);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonResponse(['error' => 'Skill with this name already exists'], 409);
        }
        throw $e;
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
