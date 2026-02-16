<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['token'])) {
    header('Location: auth.php?action=login');
    exit;
}

$token = $_SESSION['token'];
$action = $_GET['action'] ?? 'view';
$message = '';
$error = '';

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit') {
        $data = [];
        if (isset($_POST['full_name'])) $data['full_name'] = $_POST['full_name'];
        if (isset($_POST['bio'])) $data['bio'] = $_POST['bio'];
        if (!empty($data)) {
            $resp = apiRequest('PUT', '/users/me', $data, $token);
            if ($resp['httpCode'] === 200) {
                $_SESSION['user'] = $resp['body']['user'];
                $message = 'Профиль обновлён';
            } else {
                $error = $resp['body']['error'] ?? 'Ошибка обновления';
            }
        }
    } elseif ($action === 'add_skill') {
        $skill_id = (int)$_POST['skill_id'];
        $type = $_POST['type'];
        $level = (int)($_POST['proficiency_level'] ?? 1);
        $desc = $_POST['description'] ?? '';
        $data = [
            'skill_id' => $skill_id,
            'type' => $type,
            'proficiency_level' => $level,
            'description' => $desc
        ];
        $resp = apiRequest('POST', '/users/me/skills', $data, $token);
        if ($resp['httpCode'] === 201) {
            $message = 'Навык добавлен';
        } else {
            $error = $resp['body']['error'] ?? 'Ошибка добавления навыка';
        }
    } elseif ($action === 'avatar') {
        $resp = apiRequest('POST', '/users/me/avatar', null, $token, 'avatar');
        if ($resp['httpCode'] === 200) {
            $_SESSION['user'] = $resp['body']['user'];
            $message = 'Аватар загружен';
        } else {
            $error = $resp['body']['error'] ?? 'Ошибка загрузки аватара';
        }
    }
}

// Удаление навыка
if ($action === 'delete_skill' && isset($_GET['skill_id'])) {
    $skillId = (int)$_GET['skill_id'];
    $resp = apiRequest('DELETE', '/users/me/skills/' . $skillId, null, $token);
    if ($resp['httpCode'] === 200) {
        $message = 'Навык удалён';
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка удаления';
    }
    // Перенаправляем, чтобы убрать параметры
    header('Location: profile.php?action=skills&msg=' . urlencode($message));
    exit;
}

// Получаем данные профиля (всегда, кроме случаев, когда мы только что удалили и перенаправили)
$resp = apiRequest('GET', '/users/me', null, $token);
if ($resp['httpCode'] !== 200) {
    session_destroy();
    header('Location: auth.php?action=login');
    exit;
}
$user = $resp['body']['user'];

// Получаем навыки пользователя
$mySkills = [];
$respSkills = apiRequest('GET', '/users/me/skills', null, $token);
if ($respSkills['httpCode'] === 200) {
    $mySkills = $respSkills['body']['skills'] ?? [];
}

// Для формы добавления навыка нужен список всех навыков
$allSkills = [];
$respAll = apiRequest('GET', '/skills');
if ($respAll['httpCode'] === 200) {
    $allSkills = $respAll['body']['skills'] ?? [];
}

require 'header.php';
?>

<?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'view'): ?>
    <h2>Мой профиль</h2>
    <table>
        <tr><th>ID</th><td><?= $user['id'] ?></td></tr>
        <tr><th>Username</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
        <tr><th>Имя</th><td><?= htmlspecialchars($user['full_name'] ?? '') ?></td></tr>
        <tr><th>О себе</th><td><?= nl2br(htmlspecialchars($user['bio'] ?? '')) ?></td></tr>
        <tr><th>Аватар</th><td>
            <?php if ($user['avatar_url']): ?>
                <img src="<?= API_BASE_URL . $user['avatar_url'] ?>" width="100">
            <?php else: ?> нет <?php endif; ?>
        </td></tr>
        <tr><th>Очки</th><td><?= $user['points'] ?></td></tr>
        <tr><th>Дата регистрации</th><td><?= $user['created_at'] ?></td></tr>
    </table>
    <p><a href="profile.php?action=edit">Редактировать</a> | <a href="profile.php?action=skills">Управление навыками</a></p>

<?php elseif ($action === 'edit'): ?>
    <h2>Редактирование профиля</h2>
    <form method="post">
        <div><label>Имя:</label> <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"></div>
        <div><label>О себе:</label><br><textarea name="bio" rows="5" cols="40"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea></div>
        <button type="submit">Сохранить</button>
    </form>
    <h3>Загрузить новый аватар</h3>
    <form action="profile.php?action=avatar" method="post" enctype="multipart/form-data">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <button type="submit">Загрузить</button>
    </form>
    <p><a href="profile.php">Вернуться к профилю</a></p>

<?php elseif ($action === 'skills'): ?>
    <h2>Мои навыки</h2>
    <?php if (empty($mySkills)): ?>
        <p>У вас пока нет навыков.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <tr><th>Навык</th><th>Тип</th><th>Уровень</th><th>Описание</th><th>Действие</th></tr>
            <?php foreach ($mySkills as $skill): ?>
                <tr>
                    <td><?= htmlspecialchars($skill['skill_name']) ?></td>
                    <td><?= $skill['type'] == 'teach' ? 'Учу' : 'Хочу научиться' ?></td>
                    <td><?= $skill['proficiency_level'] ?></td>
                    <td><?= nl2br(htmlspecialchars($skill['description'] ?? '')) ?></td>
                    <td><a href="profile.php?action=delete_skill&skill_id=<?= $skill['skill_id'] ?>" onclick="return confirm('Удалить?')">Удалить</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Добавить навык</h3>
    <form method="post" action="profile.php?action=add_skill">
        <div>
            <label>Навык:</label>
            <select name="skill_id" required>
                <option value="">Выберите...</option>
                <?php foreach ($allSkills as $skill): ?>
                    <option value="<?= $skill['id'] ?>"><?= htmlspecialchars($skill['name']) ?> (<?= htmlspecialchars($skill['category_name'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Тип:</label>
            <input type="radio" name="type" value="teach" required> Учу
            <input type="radio" name="type" value="learn" required> Хочу научиться
        </div>
        <div>
            <label>Уровень (1-5):</label>
            <input type="number" name="proficiency_level" min="1" max="5" value="1">
        </div>
        <div>
            <label>Описание (необязательно):</label><br>
            <textarea name="description" rows="3" cols="40"></textarea>
        </div>
        <button type="submit">Добавить</button>
    </form>
    <p><a href="profile.php">Вернуться в профиль</a></p>

<?php elseif ($action === 'avatar'): ?>
    <?php // Обработка уже выполнена в начале, здесь просто показываем результат ?>
    <p>Аватар обработан. <a href="profile.php">Вернуться</a></p>
<?php endif; ?>

<?php require 'footer.php'; ?>
</body>
</html>