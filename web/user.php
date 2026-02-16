<?php
require_once 'config.php';
session_start();

$token = $_SESSION['token'] ?? null;
$userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$badgesAll = isset($_GET['badges']) && $_GET['badges'] === 'all';
$badgesForUser = isset($_GET['badges_user']) ? (int)$_GET['badges_user'] : null;

// Если запрошены все бейджи
if ($badgesAll) {
    $resp = apiRequest('GET', '/badges');
    if ($resp['httpCode'] === 200) {
        $badges = $resp['body']['badges'] ?? [];
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка загрузки бейджей';
    }
    require 'header.php';
    echo "<h2>Все бейджи</h2>";
    if (isset($error)) echo "<div class='error'>$error</div>";
    if (empty($badges)) echo "<p>Бейджей пока нет.</p>";
    else {
        echo "<table border='1' cellpadding='5'><tr><th>Название</th><th>Описание</th><th>Изображение</th></tr>";
        foreach ($badges as $badge) {
            echo "<tr><td>" . htmlspecialchars($badge['name']) . "</td><td>" . htmlspecialchars($badge['description'] ?? '') . "</td><td>";
            if ($badge['image_url']) echo "<img src='" . API_BASE_URL . $badge['image_url'] . "' width='50'>";
            else echo "нет";
            echo "</td></tr>";
        }
        echo "</table>";
    }
    echo "<p><a href='javascript:history.back()'>Назад</a></p>";
    require 'footer.php';
    exit;
}

// Если запрошены бейджи конкретного пользователя
if ($badgesForUser) {
    $resp = apiRequest('GET', '/badges/user/' . $badgesForUser);
    if ($resp['httpCode'] === 200) {
        $badges = $resp['body']['badges'] ?? [];
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка загрузки бейджей';
    }
    // Получаем имя пользователя
    $respUser = apiRequest('GET', '/users/' . $badgesForUser);
    $userName = ($respUser['httpCode'] === 200) ? ($respUser['body']['user']['full_name'] ?: $respUser['body']['user']['username']) : 'пользователя';
    require 'header.php';
    echo "<h2>Бейджи пользователя " . htmlspecialchars($userName) . "</h2>";
    if (isset($error)) echo "<div class='error'>$error</div>";
    if (empty($badges)) echo "<p>У пользователя пока нет бейджей.</p>";
    else {
        echo "<table border='1' cellpadding='5'><tr><th>Название</th><th>Описание</th><th>Изображение</th><th>Дата получения</th></tr>";
        foreach ($badges as $badge) {
            echo "<tr><td>" . htmlspecialchars($badge['name']) . "</td><td>" . htmlspecialchars($badge['description'] ?? '') . "</td><td>";
            if ($badge['image_url']) echo "<img src='" . API_BASE_URL . $badge['image_url'] . "' width='50'>";
            else echo "нет";
            echo "</td><td>" . $badge['awarded_at'] . "</td></tr>";
        }
        echo "</table>";
    }
    echo "<p><a href='user.php?id=$badgesForUser'>Вернуться к профилю</a></p>";
    require 'footer.php';
    exit;
}

// Если нет параметров, показываем публичный профиль пользователя
if (!$userId) {
    header('Location: index.php');
    exit;
}

// Получаем профиль пользователя
$resp = apiRequest('GET', '/users/' . $userId);
if ($resp['httpCode'] !== 200) {
    $error = $resp['body']['error'] ?? 'Пользователь не найден';
    $user = null;
} else {
    $user = $resp['body']['user'];
}

// Получаем отзывы
$reviews = [];
$avgRating = 0;
$respRev = apiRequest('GET', '/reviews/' . $userId);
if ($respRev['httpCode'] === 200) {
    $reviews = $respRev['body']['reviews'] ?? [];
    $avgRating = $respRev['body']['average_rating'] ?? 0;
}

require 'header.php';
?>

<?php if (isset($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php elseif ($user): ?>
    <h2>Профиль пользователя <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h2>
    <table>
        <tr><th>Имя пользователя</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
        <tr><th>Полное имя</th><td><?= htmlspecialchars($user['full_name'] ?? '') ?></td></tr>
        <tr><th>О себе</th><td><?= nl2br(htmlspecialchars($user['bio'] ?? '')) ?></td></tr>
        <tr><th>Аватар</th><td>
            <?php if ($user['avatar_url']): ?>
                <img src="<?= API_BASE_URL . $user['avatar_url'] ?>" width="100">
            <?php else: ?> нет <?php endif; ?>
        </td></tr>
        <tr><th>Очки</th><td><?= $user['points'] ?></td></tr>
        <tr><th>Дата регистрации</th><td><?= $user['created_at'] ?></td></tr>
    </table>

    <h3>Навыки</h3>
    <?php if (empty($user['skills'])): ?>
        <p>Нет навыков</p>
    <?php else: ?>
        <ul>
            <?php foreach ($user['skills'] as $skill): ?>
                <li><?= htmlspecialchars($skill['skill_name']) ?> (<?= $skill['type'] == 'teach' ? 'учит' : 'хочет научиться' ?>) уровень <?= $skill['proficiency_level'] ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Отзывы (средняя оценка: <?= $avgRating ?>)</h3>
    <?php if (empty($reviews)): ?>
        <p>Пока нет отзывов.</p>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div style="border:1px solid #ddd; margin:10px 0; padding:10px;">
                <strong><a href="user.php?id=<?= $rev['reviewer_id'] ?>"><?= htmlspecialchars($rev['reviewer_name'] ?: $rev['reviewer_username']) ?></a></strong> оценил(а) на <?= $rev['rating'] ?>/5<br>
                <?= nl2br(htmlspecialchars($rev['comment'] ?? '')) ?><br>
                <small><?= $rev['created_at'] ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($token && $_SESSION['user']['id'] != $userId): ?>
        <h3>Оставить отзыв</h3>
        <form action="user.php?id=<?= $userId ?>" method="post">
            <input type="hidden" name="action" value="add_review">
            <input type="hidden" name="reviewed_id" value="<?= $userId ?>">
            <div><label>Оценка (1-5):</label> <input type="number" name="rating" min="1" max="5" required></div>
            <div><label>Комментарий:</label><br><textarea name="comment" rows="4" cols="40"></textarea></div>
            <button type="submit">Отправить</button>
        </form>
        <p><a href="messages.php?user=<?= $userId ?>">Написать сообщение</a></p>
    <?php endif; ?>

    <p><a href="user.php?badges_user=<?= $userId ?>">Посмотреть бейджи пользователя</a></p>
<?php endif; ?>

<?php
// Обработка добавления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review' && $token) {
    $reviewed_id = (int)$_POST['reviewed_id'];
    $rating = (int)$_POST['rating'];
    $comment = $_POST['comment'] ?? '';
    $resp = apiRequest('POST', '/reviews', [
        'reviewed_id' => $reviewed_id,
        'rating' => $rating,
        'comment' => $comment
    ], $token);
    if ($resp['httpCode'] === 201) {
        echo "<script>location.href='user.php?id=$reviewed_id&success=1';</script>";
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка добавления отзыва';
        echo "<div class='error'>$error</div>";
    }
}
?>

<p><a href="javascript:history.back()">Назад</a></p>
<?php require 'footer.php'; ?>
</body>
</html>