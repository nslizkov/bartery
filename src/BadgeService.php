<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PushNotification.php';

class BadgeService
{
    private PDO $db;
    private PushNotification $push;
    private array $badgeDefinitions;

    public function __construct()
    {
        $this->db = Database::get();
        $this->push = PushNotification::getInstance();
        $this->loadBadgeDefinitions();
    }

    /**
     * Get badge ID by name and level
     */
    private function getBadgeId(string $name, int $level): ?int
    {
        $key = $name . '_' . $level;
        return isset($this->badgeDefinitions[$key]) ? $this->badgeDefinitions[$key]['id'] : null;
    }

    /**
     * Load badge definitions from database
     */
    private function loadBadgeDefinitions(): void
    {
        $stmt = $this->db->query('SELECT id, name, level, criteria FROM badges ORDER BY name, level');
        $badges = $stmt->fetchAll();
        
        $this->badgeDefinitions = [];
        foreach ($badges as $badge) {
            $this->badgeDefinitions[$badge['name'] . '_' . $badge['level']] = $badge;
        }
    }

    /**
     * Check and award all badges for a user
     * Called from various API endpoints
     */
    public function checkAndAwardBadges(int $userId, string $triggerEvent, array $context = []): void
    {
        try {
            $this->checkPopularityBadges($userId, $triggerEvent, $context);
            $this->checkExtrovertBadges($userId, $triggerEvent, $context);
            $this->checkDisciplineBadges($userId, $triggerEvent, $context);
            $this->checkStudentBadges($userId, $triggerEvent, $context);
            $this->checkTrustBadges($userId, $triggerEvent, $context);
            $this->checkOldtimerBadges($userId, $triggerEvent, $context);
        } catch (Exception $e) {
            error_log("Badge check error for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Check Popularity badges (based on reviews received)
     * Trigger: When a review is added for the user
     */
    private function checkPopularityBadges(int $userId, string $triggerEvent, array $context): void
    {
        if ($triggerEvent !== 'review_received') {
            return;
        }

        // Count reviews received by user
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM reviews WHERE reviewed_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $reviewCount = (int) $result['count'];

        $this->awardBadgeForCount($userId, 'Популярность', $reviewCount, 'review_received');
    }

    /**
     * Check Extrovert badges (based on reviews left)
     * Trigger: When user leaves a review
     */
    private function checkExtrovertBadges(int $userId, string $triggerEvent, array $context): void
    {
        if ($triggerEvent !== 'review_left') {
            return;
        }

        // Count reviews left by user
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM reviews WHERE reviewer_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $reviewCount = (int) $result['count'];

        $this->awardBadgeForCount($userId, 'Экстраверт', $reviewCount, 'review_left');
    }

    /**
     * Check Discipline badges (based on completed video calls > 15 min)
     * Trigger: When a video call is completed
     */
    private function checkDisciplineBadges(int $userId, string $triggerEvent, array $context): void
    {
        if ($triggerEvent !== 'call_completed') {
            return;
        }

        // Count completed calls with duration > 15 minutes (900 seconds) for this user
        // User can be either caller or callee
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM video_calls
            WHERE status = 'completed'
            AND duration > 900
            AND (caller_id = ? OR callee_id = ?)
        ");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch();
        $callCount = (int) $result['count'];

        $this->awardBadgeForCount($userId, 'Дисциплина', $callCount, 'call_completed');
    }

    /**
     * Check Student badges (based on skills added)
     * Trigger: When user adds a skill
     * Special condition for levels 4 and 5: must have both 'teach' and 'learn' types
     */
    private function checkStudentBadges(int $userId, string $triggerEvent, array $context): void
    {
        if ($triggerEvent !== 'skill_added') {
            return;
        }

        // Count distinct skills added by user
        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT skill_id) as count FROM user_skills WHERE user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $skillCount = (int) $result['count'];

        $level = $this->determineLevel('Студент', $skillCount);
        if ($level === 0) {
            return;
        }

        // For levels 4 and 5, require both 'teach' and 'learn' types
        if ($level >= 4) {
            $stmtTypes = $this->db->prepare('SELECT COUNT(DISTINCT type) as type_count FROM user_skills WHERE user_id = ?');
            $stmtTypes->execute([$userId]);
            $typesResult = $stmtTypes->fetch();
            $hasBothTypes = (int) $typesResult['type_count'] >= 2;
            if (!$hasBothTypes) {
                return;
            }
        }

        $this->awardBadge($userId, 'Студент', $level, 'skill_added');
    }

    /**
     * Check Trust badges (based on average rating)
     * Trigger: When a review is added/updated for the user
     * Special: Removes badge if rating drops below threshold
     */
    private function checkTrustBadges(int $userId, string $triggerEvent, array $context): void
    {
        if ($triggerEvent !== 'review_received') {
            return;
        }

        // Get average rating for user
        $stmt = $this->db->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE reviewed_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        $avgRating = round((float) ($result['avg_rating'] ?? 0), 2);
        $reviewCount = (int) ($result['count'] ?? 0);

        // Determine appropriate level based on average rating
        $desiredLevel = 0;
        if ($avgRating > 4.9) {
            $desiredLevel = 5;
        } elseif ($avgRating > 4.8) {
            $desiredLevel = 4;
        } elseif ($avgRating > 4.7) {
            $desiredLevel = 3;
        } elseif ($avgRating > 4.6) {
            $desiredLevel = 2;
        } elseif ($avgRating > 4.5) {
            $desiredLevel = 1;
        }

        // Get currently owned Trust badges
        $stmt = $this->db->prepare('
            SELECT ub.badge_id, b.level
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ? AND b.name = ?
            ORDER BY b.level
        ');
        $stmt->execute([$userId, 'Доверие']);
        $ownedBadges = $stmt->fetchAll();

        // Remove Trust badges that are no longer earned (rating dropped)
        foreach ($ownedBadges as $badge) {
            if ($badge['level'] > $desiredLevel) {
                // Rating dropped below this level's threshold - remove badge
                $stmtRemove = $this->db->prepare('DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?');
                $stmtRemove->execute([$userId, $badge['badge_id']]);
                error_log("Removed Trust badge level {$badge['level']} from user {$userId} (rating dropped to {$avgRating})");
            }
        }

        // Award new level if eligible and not already owned
        if ($desiredLevel > 0) {
            $badgeId = $this->getBadgeId('Доверие', $desiredLevel);
            if ($badgeId) {
                $stmtCheck = $this->db->prepare('SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?');
                $stmtCheck->execute([$userId, $badgeId]);
                if (!$stmtCheck->fetch()) {
                    // Award the badge
                    $stmtInsert = $this->db->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
                    $stmtInsert->execute([$userId, $badgeId]);
                    $this->sendBadgeNotification($userId, 'Доверие', $desiredLevel);
                }
            }
        }
    }

    /**
     * Check Oldtimer badges (based on days on platform)
     * Trigger: Any badge event (always check)
     */
    private function checkOldtimerBadges(int $userId, string $triggerEvent, array $context): void
    {
        // Get user creation date
        $stmt = $this->db->prepare('SELECT created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result || !isset($result['created_at'])) {
            return;
        }

        $createdAt = strtotime($result['created_at']);
        $now = time();
        $daysOnPlatform = (int) floor(($now - $createdAt) / (60 * 60 * 24));

        // Determine level based on days
        $level = null;
        if ($daysOnPlatform >= 365) {
            $level = 5;
        } elseif ($daysOnPlatform >= 180) { // ~6 months
            $level = 4;
        } elseif ($daysOnPlatform >= 30) { // 1 month
            $level = 3;
        } elseif ($daysOnPlatform >= 10) {
            $level = 2;
        } elseif ($daysOnPlatform >= 1) {
            $level = 1;
        }

        if ($level !== null) {
            $this->awardBadge($userId, 'Старожил', $level, 'time_on_platform');
        }
    }

    /**
     * Award badge for a certain count threshold
     */
    private function awardBadgeForCount(int $userId, string $badgeName, int $count, string $triggerEvent, bool $extraCondition = true): void
    {
        if (!$extraCondition) {
            return; // Extra condition not met (for Student lvl 4-5)
        }

        $level = $this->determineLevel($badgeName, $count);
        if ($level > 0) {
            $this->awardBadge($userId, $badgeName, $level, $triggerEvent);
        }
    }

    /**
     * Determine badge level based on count
     * Returns 0 if no level earned, or the level number (1-5)
     */
    private function determineLevel(string $badgeName, int $count): int
    {
        switch ($badgeName) {
            case 'Популярность':
                if ($count >= 20) return 5;
                if ($count >= 15) return 4;
                if ($count >= 10) return 3;
                if ($count >= 5) return 2;
                if ($count >= 1) return 1;
                break;
            case 'Экстраверт':
                if ($count >= 20) return 5;
                if ($count >= 15) return 4;
                if ($count >= 10) return 3;
                if ($count >= 5) return 2;
                if ($count >= 1) return 1;
                break;
            case 'Дисциплина':
                if ($count >= 30) return 5;
                if ($count >= 20) return 4;
                if ($count >= 10) return 3;
                if ($count >= 5) return 2;
                if ($count >= 3) return 1;
                break;
            case 'Студент':
                if ($count >= 7) return 5;
                if ($count >= 5) return 4;
                if ($count >= 3) return 3;
                if ($count >= 2) return 2;
                if ($count >= 1) return 1;
                break;
        }
        return 0;
    }

    /**
     * Award a specific badge to user if not already owned
     */
    private function awardBadge(int $userId, string $badgeName, int $level, string $triggerEvent): void
    {
        $badgeKey = $badgeName . '_' . $level;
        
        if (!isset($this->badgeDefinitions[$badgeKey])) {
            error_log("Badge definition not found: {$badgeKey}");
            return;
        }

        $badge = $this->badgeDefinitions[$badgeKey];
        $badgeId = $badge['id'];

        // Check if user already has this badge
        $stmt = $this->db->prepare('SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?');
        $stmt->execute([$userId, $badgeId]);
        if ($stmt->fetch()) {
            return; // Already awarded
        }

        // Award the badge
        $stmt = $this->db->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
        $stmt->execute([$userId, $badgeId]);

        // Send push notification
        $this->sendBadgeNotification($userId, $badgeName, $level);
    }

    /**
     * Send push notification about badge award
     */
    private function sendBadgeNotification(int $userId, string $badgeName, int $level): void
    {
        try {
            $title = 'Получен навык';
            $body = "{$badgeName} (уровень {$level})";
            
            $data = [
                'type' => 'badge_award',
                'badge_name' => $badgeName,
                'badge_level' => (string) $level,
            ];

            $this->push->sendToUser($userId, $title, $body, $data);
        } catch (Exception $e) {
            error_log("Failed to send badge notification to user {$userId}: " . $e->getMessage());
        }
    }
}
