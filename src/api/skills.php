<?php

require_once __DIR__ . '/../helpers.php';

$categoryId = getInt($_GET ?? [], 'category_id');
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    // New endpoint: GET /api/skills?action=sorted-for-me
    if ($action === 'sorted-for-me') {
        try {
            $user = requireAuth();
            $currentUserId = $user['id'];

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

            // Get all skills from skills table (skill definitions)
            $stmt = $db->query('
                SELECT s.id, s.name, s.description, s.category_id, c.name as category_name
                FROM skills s
                LEFT JOIN categories c ON s.category_id = c.id
            ');
            $allSkills = $stmt->fetchAll();

            // Pre-calculate which skills have complementary users (opposite type exists)
            $skillIds = array_column($allSkills, 'id');
            $hasComplementary = [];
            
            // Check for each skill if there exists at least one user with opposite type
            foreach ($skillIds as $skillId) {
                $hasComplementary[$skillId] = false;
                
                // Check if current user has this skill
                $userHasTeach = in_array($skillId, $currentUserTeaches);
                $userHasLearn = in_array($skillId, $currentUserLearns);
                
                if ($userHasTeach) {
                    // User teaches this skill - check if someone wants to learn it
                    $stmtCheck = $db->prepare('
                        SELECT COUNT(*) as count
                        FROM user_skills
                        WHERE skill_id = ? AND type = "learn" AND user_id != ?
                    ');
                    $stmtCheck->execute([$skillId, $currentUserId]);
                    $result = $stmtCheck->fetch();
                    if ($result['count'] > 0) {
                        $hasComplementary[$skillId] = true;
                    }
                } elseif ($userHasLearn) {
                    // User learns this skill - check if someone teaches it
                    $stmtCheck = $db->prepare('
                        SELECT COUNT(*) as count
                        FROM user_skills
                        WHERE skill_id = ? AND type = "teach" AND user_id != ?
                    ');
                    $stmtCheck->execute([$skillId, $currentUserId]);
                    $result = $stmtCheck->fetch();
                    if ($result['count'] > 0) {
                        $hasComplementary[$skillId] = true;
                    }
                }
            }

            // Sort by usefulness:
            // Priority 1 (score +100): User has this skill AND there's at least one person with opposite type
            // Priority 2 (score +20): Skill belongs to a category the user has skills in
            // Priority 3 (score +0): All other skills
            usort($allSkills, function ($a, $b) use ($hasComplementary, $currentUserCategories) {
                $scoreA = 0;
                $scoreB = 0;
                
                $aId = (int) $a['id'];
                $bId = (int) $b['id'];
                $aCategoryId = $a['category_id'] ?? null;
                $bCategoryId = $b['category_id'] ?? null;

                // Priority 1: User has skill + complementary exists
                if (isset($hasComplementary[$aId]) && $hasComplementary[$aId]) {
                    $scoreA += 100;
                }
                if (isset($hasComplementary[$bId]) && $hasComplementary[$bId]) {
                    $scoreB += 100;
                }

                // Priority 2: Same category as user's skills
                if ($aCategoryId !== null && in_array($aCategoryId, $currentUserCategories)) {
                    $scoreA += 20;
                }
                if ($bCategoryId !== null && in_array($bCategoryId, $currentUserCategories)) {
                    $scoreB += 20;
                }

                // Higher score = more useful = comes first
                return $scoreB - $scoreA;
            });

            jsonResponse(['skills' => $allSkills]);
        } catch (Exception $e) {
            logApiError('Error in sorted-for-me skills endpoint', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            jsonResponse(['error' => 'Failed to get sorted skills'], 500);
        }
    }

    // New endpoint: GET /api/skills?action=recommended
    if ($action === 'recommended') {
        try {
            $user = requireAuth();
            $currentUserId = $user['id'];

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

            // Get all skills from all users (excluding the current user)
            $stmt = $db->prepare('
                SELECT us.user_id, us.skill_id, us.type, us.proficiency_level, us.description, us.created_at,
                       u.username, u.full_name, u.avatar_url,
                       s.id as skill_id_def, s.name as skill_name, s.description as skill_description, s.category_id, c.name as category_name
                FROM user_skills us
                JOIN users u ON us.user_id = u.id
                JOIN skills s ON us.skill_id = s.id
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE us.user_id != ? AND u.is_active = 1
            ');
            $stmt->execute([$currentUserId]);
            $allSkills = $stmt->fetchAll();

            // Sort by relevance:
            // Priority 1 (score +100): complementary - someone teaches what current user wants to learn
            // Priority 2 (score +50): complementary - someone wants to learn what current user teaches
            // Priority 3 (score +20): same category as current user's skills
            // Priority 4 (score +5): same type as current user's skills (both teach or both learn)
            // Least relevant: no match
            usort($allSkills, function ($a, $b) use ($currentUserTeaches, $currentUserLearns, $currentUserCategories) {
                $scoreA = 0;
                $scoreB = 0;

                $aSkillId = (int) $a['skill_id'];
                $bSkillId = (int) $b['skill_id'];
                $aCategoryId = $a['category_id'] ?? null;
                $bCategoryId = $b['category_id'] ?? null;

                // Someone teaches what current user wants to learn - highest priority
                if ($a['type'] === 'teach' && in_array($aSkillId, $currentUserLearns)) {
                    $scoreA += 100;
                }
                // Someone wants to learn what current user teaches - second priority
                if ($a['type'] === 'learn' && in_array($aSkillId, $currentUserTeaches)) {
                    $scoreA += 50;
                }
                // Same category as current user's skills - third priority
                if ($aCategoryId !== null && in_array($aCategoryId, $currentUserCategories)) {
                    $scoreA += 20;
                }
                // Same type as current user's skills for this skill - fourth priority
                if ($a['type'] === 'teach' && in_array($aSkillId, $currentUserTeaches)) {
                    $scoreA += 5;
                }
                if ($a['type'] === 'learn' && in_array($aSkillId, $currentUserLearns)) {
                    $scoreA += 5;
                }

                // Same scoring for B
                if ($b['type'] === 'teach' && in_array($bSkillId, $currentUserLearns)) {
                    $scoreB += 100;
                }
                if ($b['type'] === 'learn' && in_array($bSkillId, $currentUserTeaches)) {
                    $scoreB += 50;
                }
                if ($bCategoryId !== null && in_array($bCategoryId, $currentUserCategories)) {
                    $scoreB += 20;
                }
                if ($b['type'] === 'teach' && in_array($bSkillId, $currentUserTeaches)) {
                    $scoreB += 5;
                }
                if ($b['type'] === 'learn' && in_array($bSkillId, $currentUserLearns)) {
                    $scoreB += 5;
                }

                // Higher score = more relevant = comes first
                return $scoreB - $scoreA;
            });

            jsonResponse(['skills' => $allSkills]);
        } catch (Exception $e) {
            logApiError('Error in recommended skills endpoint', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            jsonResponse(['error' => 'Failed to get recommended skills'], 500);
        }
    }

    // Original GET skills logic
    try {
        if ($categoryId) {
            $stmt = $db->prepare('SELECT s.id, s.name, s.description, s.category_id, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id WHERE s.category_id = ? ORDER BY s.name');
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $db->query('SELECT s.id, s.name, s.description, s.category_id, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id ORDER BY s.name');
        }
        $skills = $stmt->fetchAll();
        jsonResponse(['skills' => $skills]);
    } catch (Exception $e) {
        logApiError('Error fetching skills list', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'category_id' => $categoryId,
        ]);
        jsonResponse(['error' => 'Failed to fetch skills'], 500);
    }
}

if ($method === 'POST') {
    try {
        $user = requireAuth();
        $data = getJsonBody();
        if (!$data || empty($data['name'])) {
            jsonResponse(['error' => 'name required'], 400);
        }
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $categoryId = getInt($data, 'category_id');
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
        logApiError('Error creating skill', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'skill_name' => $name ?? null,
        ]);
        jsonResponse(['error' => 'Failed to create skill'], 500);
    } catch (Exception $e) {
        logApiError('Error creating skill (non-DB)', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        jsonResponse(['error' => 'Failed to create skill'], 500);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
