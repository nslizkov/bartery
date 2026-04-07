<?php

require_once __DIR__ . '/../helpers.php';

// GET /api/user-skills - all user skills (auth required)
// GET /api/user-skills/{userId} - skills for a specific user, sorted by relevance (auth required)

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();
$currentUserId = $user['id'];

// GET /api/user-skills - return all user skills
if (!$id) {
    try {
        $stmt = $db->query('
            SELECT us.user_id, us.skill_id, us.type, us.proficiency_level, us.description, us.created_at,
                   u.username, u.full_name, u.avatar_url,
                   s.name as skill_name, c.name as category_name
            FROM user_skills us
            JOIN users u ON us.user_id = u.id
            JOIN skills s ON us.skill_id = s.id
            LEFT JOIN categories c ON s.category_id = c.id
            WHERE u.is_active = 1
            ORDER BY us.user_id, s.name
        ');
        $skills = $stmt->fetchAll();
        jsonResponse(['user_skills' => $skills]);
    } catch (Exception $e) {
        logApiError('Error fetching all user skills', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        jsonResponse(['error' => 'Failed to fetch skills'], 500);
    }
}

// GET /api/user-skills/{userId} - return skills for a specific user, sorted by relevance
if (is_numeric($id)) {
    try {
        $targetUserId = (int) $id;

        // Check if user exists
        $stmt = $db->prepare('SELECT id, username, full_name, avatar_url FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            jsonResponse(['error' => 'User not found'], 404);
        }

        // Get the requesting user's skills to determine relevance
        $stmt = $db->prepare('
            SELECT us.skill_id, us.type, s.category_id
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
        ');
        $stmt->execute([$currentUserId]);
        $currentUserSkills = $stmt->fetchAll();

        // Build maps of what the current user teaches and wants to learn
        $currentUserTeaches = [];
        $currentUserLearns = [];
        $currentUserCategories = [];
        foreach ($currentUserSkills as $skill) {
            if ($skill['type'] === 'teach') {
                $currentUserTeaches[] = (int) $skill['skill_id'];
            } else {
                $currentUserLearns[] = (int) $skill['skill_id'];
            }
            $currentUserCategories[] = (int) $skill['category_id'];
        }
        $currentUserCategories = array_unique($currentUserCategories);

        // Get all skills for the target user
        $stmt = $db->prepare('
            SELECT us.user_id, us.skill_id, us.type, us.proficiency_level, us.description, us.created_at,
                   u.username, u.full_name, u.avatar_url,
                   s.name as skill_name, s.category_id, c.name as category_name
            FROM user_skills us
            JOIN users u ON us.user_id = u.id
            JOIN skills s ON us.skill_id = s.id
            LEFT JOIN categories c ON s.category_id = c.id
            WHERE us.user_id = ? AND u.is_active = 1
        ');
        $stmt->execute([$targetUserId]);
        $targetSkills = $stmt->fetchAll();

        // Sort by relevance:
        // Priority 1 (score +10): target teaches what current user wants to learn (complementary)
        // Priority 2 (score +5): target wants to learn what current user teaches (complementary)
        // Priority 3 (score +2): same category as current user's skills
        // Least relevant: non-complementary skills
        usort($targetSkills, function ($a, $b) use ($currentUserTeaches, $currentUserLearns, $currentUserCategories) {
            $scoreA = 0;
            $scoreB = 0;

            // Target teaches something current user wants to learn - highest priority
            if ($a['type'] === 'teach' && in_array((int) $a['skill_id'], $currentUserLearns)) {
                $scoreA += 10;
            }
            // Target wants to learn something current user teaches - second priority
            if ($a['type'] === 'learn' && in_array((int) $a['skill_id'], $currentUserTeaches)) {
                $scoreA += 5;
            }
            // Same category as current user's skills - third priority
            $aCategoryId = $a['category_id'] ?? null;
            if ($aCategoryId !== null && in_array($aCategoryId, $currentUserCategories)) {
                $scoreA += 2;
            }

            if ($b['type'] === 'teach' && in_array((int) $b['skill_id'], $currentUserLearns)) {
                $scoreB += 10;
            }
            if ($b['type'] === 'learn' && in_array((int) $b['skill_id'], $currentUserTeaches)) {
                $scoreB += 5;
            }
            $bCategoryId = $b['category_id'] ?? null;
            if ($bCategoryId !== null && in_array($bCategoryId, $currentUserCategories)) {
                $scoreB += 2;
            }

            // Higher score = more relevant = comes first
            return $scoreB - $scoreA;
        });

        jsonResponse(['user' => $targetUser, 'user_skills' => $targetSkills]);
    } catch (Exception $e) {
        logApiError('Error fetching user skills by relevance', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'target_user_id' => $targetUserId ?? null,
            'current_user_id' => $currentUserId,
        ]);
        jsonResponse(['error' => 'Failed to fetch user skills'], 500);
    }
}

jsonResponse(['error' => 'Not found'], 404);
