# Badge System Documentation

## Overview
The badge system awards users for their activity and achievements on the Bartery platform. Badges are automatically checked and awarded via the `BadgeService` class.

## Badge Types

### 1. Популярность (Popularity)
**Trigger:** When a review is received (`review_received` event)
**Criteria:** Number of reviews received
- Level 1: 1+ review
- Level 2: 5+ reviews
- Level 3: 10+ reviews
- Level 4: 15+ reviews
- Level 5: 20+ reviews

### 2. Экстраверт (Extrovert)
**Trigger:** When a review is left (`review_left` event)
**Criteria:** Number of reviews written
- Level 1: 1+ review
- Level 2: 5+ reviews
- Level 3: 10+ reviews
- Level 4: 15+ reviews
- Level 5: 20+ reviews

### 3. Дисциплина (Discipline)
**Trigger:** When a video call is completed (`call_completed` event)
**Criteria:** Number of completed calls with duration > 15 minutes (900 seconds)
- Level 1: 3+ calls
- Level 2: 5+ calls
- Level 3: 10+ calls
- Level 4: 20+ calls
- Level 5: 30+ calls

### 4. Студент (Student)
**Trigger:** When a skill is added (`skill_added` event)
**Criteria:** Number of distinct skills added
- Level 1: 1+ skill
- Level 2: 2+ skills
- Level 3: 3+ skills
- Level 4: 5+ skills (requires both 'teach' and 'learn' types)
- Level 5: 7+ skills (requires both 'teach' and 'learn' types)

### 5. Старожил (Oldtimer)
**Trigger:** On any badge-related event (always checked)
**Criteria:** Days on platform (based on `users.created_at`)
- Level 1: 1+ day
- Level 2: 10+ days
- Level 3: 30+ days (1 month)
- Level 4: 180+ days (6 months)
- Level 5: 365+ days (1 year)

### 6. Доверие (Trust) ⚠️ Removable
**Trigger:** When a review is received (`review_received` event)
**Criteria:** Average rating (calculated from all reviews received)
- Level 1: > 4.5
- Level 2: > 4.6
- Level 3: > 4.7
- Level 4: > 4.8
- Level 5: > 4.9

**Special Behavior:** This badge is **removed** if the average rating drops below the threshold. All other badges are permanent.

## Integration Points

Badge checks are automatically triggered in the following API endpoints:

| Endpoint | Method | Trigger Event | Badges Checked |
|-----------|--------|---------------|----------------|
| `/api/reviews` (POST) | Create review | `review_received` (for reviewed user) | Popularity, Trust, Oldtimer |
| | | `review_left` (for reviewer) | Extrovert, Oldtimer |
| `/api/calls` (POST /end) | End call | `call_completed` (if duration > 15min) | Discipline, Oldtimer |
| `/api/users/me/skills` (POST) | Add skill | `skill_added` | Student, Oldtimer |
| `/api/badges/user/{userId}` (GET) | View badges | `badge_viewed` | Oldtimer |
| `/api/badges/check` (POST) | Manual check | `manual_check` | All badges |

## Database Schema

### Tables
- `badges` - Badge definitions (name, image_url, criteria, level)
- `user_badges` - User badge awards (user_id, badge_id, awarded_at)
- `users` - User data (created_at used for Oldtimer badge)

### Badge Icons
All badge icons are stored in `uploads/badges/`:
- `ic_badge_popularity.png` - Popularity
- `ic_badge_discipline.png` - Discipline
- `ic_badge_oldtimer.png` - Oldtimer
- `ic_badge_extrovert.png` - Extrovert
- `ic_badge_stuent.png` - Student
- `ic_badge_raiting.png` - Trust

## API Endpoints

### GET /api/badges

Returns all badges with statistics about how many users have each badge.

**Response:**
```json
{
  "badges": [
    {
      "id": 1,
      "name": "Популярность",
      "image_url": "uploads/badges/ic_badge_popularity.png",
      "criteria": "1 отзыв получен",
      "level": 1,
      "user_count": 8,
      "total_users": 12,
      "percentage": 66.67
    }
  ]
}
```

**Fields:**
- `user_count` - Number of users who have this badge
- `total_users` - Total number of users in the system
- `percentage` - Percentage of users with this badge (0-100)

### GET /api/badges/user/{userId}

Returns badges owned by a specific user. Also triggers Oldtimer badge check.

**Response:**
```json
{
  "badges": [
    {
      "id": 1,
      "name": "Популярность",
      "image_url": "uploads/badges/ic_badge_popularity.png",
      "level": 3,
      "awarded_at": "2026-02-15T12:00:00Z"
    }
  ]
}
```

### POST /api/badges/check

Manually trigger badge check for the current user (requires auth).

**Response:**
```json
{
  "message": "Badge check completed"
}
```

## Notifications

When a badge is awarded, the user receives an FCM push notification:
- **Title:** "Получен навык"
- **Body:** "{Badge Name} (уровень {level})"
- **Data:** `{type: 'badge_award', badge_name, badge_level}`

## Initial Data

The `db/init.sql` file includes:
- All 30 badge definitions (6 types × 5 levels)
- 12 default users with varied activity
- Pre-calculated `user_badges` entries based on existing data (reviews, calls, skills)
- `created_at` dates for all users between 2026-02-01 and 2026-04-07

## Testing

Run `php test_badges.php` to verify the badge system works correctly. The script tests all badge types with sample data.

## Files

- `src/BadgeService.php` - Core badge logic
- `src/api/badges.php` - Badge API endpoints
- `src/api/reviews.php` - Review endpoints (integrates Popularity, Extrovert, Trust)
- `src/api/calls.php` - Video call endpoints (integrates Discipline)
- `src/api/users.php` - User skill endpoints (integrates Student)
- `db/init.sql` - Database schema and seed data
