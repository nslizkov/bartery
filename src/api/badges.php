<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../BadgeService.php';

// GET /api/badges - list all badges with user statistics
if (!$id && $method === 'GET') {
    try {
        // Get all badges
        $badgesStmt = $db->query('SELECT id, name, image_url, criteria, level FROM badges ORDER BY name, level');
        $badges = $badgesStmt->fetchAll();
        
        // Get total user count
        $totalUsersStmt = $db->query('SELECT COUNT(*) as count FROM users');
        $totalUsers = (int) $totalUsersStmt->fetchColumn();
        
        // Get badge user counts
        $badgeStatsStmt = $db->query('
            SELECT badge_id, COUNT(DISTINCT user_id) as user_count
            FROM user_badges
            GROUP BY badge_id
        ');
        $badgeUserCounts = [];
        while ($row = $badgeStatsStmt->fetch()) {
            $badgeUserCounts[$row['badge_id']] = (int) $row['user_count'];
        }
        
        // Calculate percentage for each badge
        $badgesWithStats = [];
        foreach ($badges as $badge) {
            $badgeId = $badge['id'];
            $userCount = $badgeUserCounts[$badgeId] ?? 0;
            $percentage = $totalUsers > 0 ? round(($userCount / $totalUsers) * 100, 2) : 0;
            
            $badgesWithStats[] = [
                'id' => $badgeId,
                'name' => $badge['name'],
                'image_url' => $badge['image_url'],
                'criteria' => $badge['criteria'],
                'level' => $badge['level'],
                'user_count' => $userCount,
                'total_users' => $totalUsers,
                'percentage' => $percentage,
            ];
        }
        
        jsonResponse(['badges' => $badgesWithStats]);
    } catch (Exception $e) {
        logApiError('Error fetching badges list', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        jsonResponse(['error' => 'Failed to fetch badges'], 500);
    }
}

// POST /api/badges/check - manually trigger badge check for current user (auth)
if ($id === 'check' && $method === 'POST') {
    try {
        $user = requireAuth();
        $badgeService = new BadgeService();
        $badgeService->checkAndAwardBadges($user['id'], 'manual_check');
        jsonResponse(['message' => 'Badge check completed']);
    } catch (Exception $e) {
        logApiError('Manual badge check failed', [
            'error' => $e->getMessage(),
            'user_id' => $user['id'] ?? null,
        ]);
        jsonResponse(['error' => 'Badge check failed'], 500);
    }
}

// GET /api/badges/user/{userId} - user's badges (unique by name, max level)
if ($id === 'user' && is_numeric($sub) && $method === 'GET') {
    try {
        $userId = (int) $sub;
        
        // Check and award Oldtimer badges (time-based) when viewing badges
        try {
            $badgeService = new BadgeService();
            $badgeService->checkAndAwardBadges($userId, 'badge_viewed');
        } catch (Exception $badgeEx) {
            logApiError('Badge check failed (oldtimer)', [
                'error' => $badgeEx->getMessage(),
                'user_id' => $userId,
            ]);
        }
        
        // Get all badges for user, ordered by name and level (desc) to get max level first per name
        $stmt = $db->prepare('
            SELECT b.id, b.name, b.image_url, b.level, ub.awarded_at
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ?
            ORDER BY b.name, b.level DESC, ub.awarded_at DESC
        ');
        $stmt->execute([$userId]);
        $allBadges = $stmt->fetchAll();
        
        // Deduplicate by badge name, keeping only the highest level (first occurrence after ordering)
        $uniqueBadges = [];
        $seenNames = [];
        foreach ($allBadges as $badge) {
            $name = $badge['name'];
            if (!isset($seenNames[$name])) {
                $uniqueBadges[] = $badge;
                $seenNames[$name] = true;
            }
        }
        
        jsonResponse(['badges' => $uniqueBadges]);
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