<?php

// GET /api/reviews/{userId} - reviews for user (public)
if (is_numeric($id) && !$sub && $method === 'GET') {
    $userId = (int) $id;
    $stmt = $db->prepare('
        SELECT r.id, r.reviewer_id, r.rating, r.comment, r.created_at,
            u.username as reviewer_username, u.full_name as reviewer_name, u.avatar_url as reviewer_avatar
        FROM reviews r
        JOIN users u ON r.reviewer_id = u.id
        WHERE r.reviewed_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$userId]);
    $reviews = $stmt->fetchAll();
    $stmt = $db->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE reviewed_id = ?');
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    jsonResponse(['reviews' => $reviews, 'average_rating' => round((float) $stats['avg_rating'], 1), 'total' => (int) $stats['count']]);
}

// POST /api/reviews - leave review (auth)
if (!$id && $method === 'POST') {
    $user = requireAuth();
    $data = getJsonBody();
    if (!$data || empty($data['reviewed_id']) || empty($data['rating'])) {
        jsonResponse(['error' => 'reviewed_id and rating (1-5) required'], 400);
    }
    $reviewedId = (int) $data['reviewed_id'];
    $rating = (int) $data['rating'];
    if ($rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'rating must be 1-5'], 400);
    }
    if ($reviewedId === $user['id']) {
        jsonResponse(['error' => 'Cannot review yourself'], 400);
    }
    $comment = trim($data['comment'] ?? '');
    $db->prepare('INSERT INTO reviews (reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)')
        ->execute([$user['id'], $reviewedId, $rating, $comment]);
    $stmt = $db->prepare('SELECT id, reviewer_id, reviewed_id, rating, comment, created_at FROM reviews WHERE reviewer_id = ? AND reviewed_id = ?');
    $stmt->execute([$user['id'], $reviewedId]);
    $review = $stmt->fetch();
    jsonResponse(['review' => $review], 201);
}

jsonResponse(['error' => 'Not found'], 404);
