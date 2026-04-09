<?php
/**
 * Test script for BadgeService
 * Run: php test_badges.php
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/BadgeService.php';
require_once __DIR__ . '/src/PushNotification.php';

// Initialize DB
$db = Database::get();

echo "=== Badge System Test ===\n\n";

// Test 1: Check if badges exist in DB
echo "1. Checking badge definitions in database...\n";
$stmt = $db->query('SELECT id, name, level, criteria FROM badges ORDER BY name, level');
$badges = $stmt->fetchAll();
echo "Found " . count($badges) . " badge definitions:\n";
foreach ($badges as $badge) {
    echo "  - {$badge['name']} (level {$badge['level']}): {$badge['criteria']}\n";
}
echo "\n";

// Test 2: Test BadgeService initialization
echo "2. Testing BadgeService initialization...\n";
try {
    $badgeService = new BadgeService();
    echo "BadgeService initialized successfully\n";
} catch (Exception $e) {
    echo "ERROR: Failed to initialize BadgeService: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 3: Check if user_badges table is empty (for clean test)
echo "3. Clearing user_badges for test users (if any)...\n";
$db->exec('DELETE FROM user_badges WHERE user_id IN (1, 2, 3)');
echo "Done\n\n";

// Test 4: Simulate awarding Popularity badges
echo "4. Testing Popularity badges (user 1)...\n";
// We'll manually insert reviews to test
// First, ensure user 1 exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = 1');
$stmt->execute();
$user1 = $stmt->fetch();
if (!$user1) {
    echo "  Warning: User 1 not found, skipping Popularity test\n";
} else {
    // Simulate adding reviews for user 1
    echo "  Simulating reviews for user 1...\n";
    for ($i = 1; $i <= 22; $i++) {
        $reviewerId = ($i % 10) + 1; // Cycle through reviewers
        if ($reviewerId == 1) $reviewerId = 2; // Avoid self-review
        
        $stmt = $db->prepare('INSERT INTO reviews (reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, 5, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)');
        $stmt->execute([$reviewerId, 1, "Test review $i"]);
    }
    echo "  Added 22 reviews for user 1\n";
    
    // Trigger badge check
    $badgeService->checkAndAwardBadges(1, 'review_received');
    echo "  Triggered badge check for user 1\n";
    
    // Check awarded badges
    $stmt = $db->prepare('SELECT b.name, b.level, ub.awarded_at FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = 1 ORDER BY b.name, b.level');
    $stmt->execute();
    $awarded = $stmt->fetchAll();
    echo "  Awarded " . count($awarded) . " badges to user 1:\n";
    foreach ($awarded as $badge) {
        echo "    - {$badge['name']} level {$badge['level']} at {$badge['awarded_at']}\n";
    }
}
echo "\n";

// Test 5: Test Extrovert badges
echo "5. Testing Extrovert badges (user 2)...\n";
$stmt = $db->prepare('SELECT id FROM users WHERE id = 2');
$stmt->execute();
$user2 = $stmt->fetch();
if (!$user2) {
    echo "  Warning: User 2 not found, skipping Extrovert test\n";
} else {
    // Simulate user 2 leaving reviews
    echo "  Simulating reviews left by user 2...\n";
    for ($i = 1; $i <= 18; $i++) {
        $reviewedId = ($i % 5) + 1; // Cycle through reviewed users
        if ($reviewedId == 2) $reviewedId = 3;
        
        $stmt = $db->prepare('INSERT INTO reviews (reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, 4, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)');
        $stmt->execute([2, $reviewedId, "Extrovert test review $i"]);
    }
    echo "  Added 18 reviews by user 2\n";
    
    $badgeService->checkAndAwardBadges(2, 'review_left');
    echo "  Triggered badge check for user 2\n";
    
    $stmt = $db->prepare('SELECT b.name, b.level FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = 2');
    $stmt->execute();
    $awarded = $stmt->fetchAll();
    echo "  Awarded " . count($awarded) . " badges to user 2:\n";
    foreach ($awarded as $badge) {
        echo "    - {$badge['name']} level {$badge['level']}\n";
    }
}
echo "\n";

// Test 6: Test Discipline badges
echo "6. Testing Discipline badges (user 3)...\n";
$stmt = $db->prepare('SELECT id FROM users WHERE id = 3');
$stmt->execute();
$user3 = $stmt->fetch();
if (!$user3) {
    echo "  Warning: User 3 not found, skipping Discipline test\n";
} else {
    // Simulate completed calls > 15 min
    echo "  Simulating completed calls for user 3...\n";
    $now = date('Y-m-d H:i:s');
    for ($i = 1; $i <= 35; $i++) {
        $callerId = ($i % 2 == 0) ? 3 : 1;
        $calleeId = ($callerId == 3) ? 1 : 3;
        $duration = 900 + ($i * 60); // > 15 min
        
        $stmt = $db->prepare('INSERT INTO video_calls (caller_id, callee_id, status, started_at, ended_at, duration) VALUES (?, ?, ?, ?, ?, ?)');
        $started = date('Y-m-d H:i:s', strtotime($now) - $duration);
        $stmt->execute([$callerId, $calleeId, 'completed', $started, $now, $duration]);
    }
    echo "  Added 35 completed calls for user 3\n";
    
    $badgeService->checkAndAwardBadges(3, 'call_completed');
    echo "  Triggered badge check for user 3\n";
    
    $stmt = $db->prepare('SELECT b.name, b.level FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = 3');
    $stmt->execute();
    $awarded = $stmt->fetchAll();
    echo "  Awarded " . count($awarded) . " badges to user 3:\n";
    foreach ($awarded as $badge) {
        echo "    - {$badge['name']} level {$badge['level']}\n";
    }
}
echo "\n";

// Test 7: Test Student badges
echo "7. Testing Student badges (user 4)...\n";
$stmt = $db->prepare('SELECT id FROM users WHERE id = 4');
$stmt->execute();
$user4 = $stmt->fetch();
if (!$user4) {
    echo "  Warning: User 4 not found, skipping Student test\n";
} else {
    echo "  Adding skills to user 4...\n";
    // Add skills - need to use existing skill IDs
    $stmt = $db->query('SELECT id FROM skills LIMIT 10');
    $skillIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($skillIds) < 7) {
        echo "  Not enough skills in DB. Creating test skills...\n";
        for ($i = 1; $i <= 10; $i++) {
            $stmt = $db->prepare('INSERT INTO skills (name, description, category_id) VALUES (?, ?, 1)');
            $stmt->execute(["Test Skill $i", "Test description", 1]);
        }
        $skillIds = $db->lastInsertId();
    }
    
    // Add 8 skills: mix of teach and learn
    $types = ['teach', 'learn', 'teach', 'learn', 'teach', 'learn', 'teach', 'learn'];
    foreach ($types as $idx => $type) {
        $skillId = $skillIds[$idx % count($skillIds)];
        $stmt = $db->prepare('INSERT INTO user_skills (user_id, skill_id, type, proficiency_level) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE type = VALUES(type)');
        $stmt->execute([4, $skillId, $type]);
    }
    echo "  Added 8 skills (4 teach, 4 learn) for user 4\n";
    
    $badgeService->checkAndAwardBadges(4, 'skill_added');
    echo "  Triggered badge check for user 4\n";
    
    $stmt = $db->prepare('SELECT b.name, b.level FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = 4');
    $stmt->execute();
    $awarded = $stmt->fetchAll();
    echo "  Awarded " . count($awarded) . " badges to user 4:\n";
    foreach ($awarded as $badge) {
        echo "    - {$badge['name']} level {$badge['level']}\n";
    }
}
echo "\n";

// Test 8: Test Oldtimer badges
echo "8. Testing Oldtimer badges (user with old created_at)...\n";
// We need to create a user with old created_at date
$stmt = $db->prepare('SELECT id FROM users WHERE id = 5');
$stmt->execute();
$user5 = $stmt->fetch();
if (!$user5) {
    echo "  Warning: User 5 not found, skipping Oldtimer test\n";
} else {
    // Set created_at to 400 days ago
    $oldDate = date('Y-m-d H:i:s', strtotime('-400 days'));
    $stmt = $db->prepare('UPDATE users SET created_at = ? WHERE id = 5');
    $stmt->execute([$oldDate]);
    echo "  Set user 5 created_at to 400 days ago\n";
    
    $badgeService->checkAndAwardBadges(5, 'badge_viewed');
    echo "  Triggered badge check for user 5\n";
    
    $stmt = $db->prepare('SELECT b.name, b.level FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = 5');
    $stmt->execute();
    $awarded = $stmt->fetchAll();
    echo "  Awarded " . count($awarded) . " badges to user 5:\n";
    foreach ($awarded as $badge) {
        echo "    - {$badge['name']} level {$badge['level']}\n";
    }
}
echo "\n";

echo "=== Test Complete ===\n";
echo "Check the user_badges table to see awarded badges.\n";
