<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['token'])) {
    header('Location: auth.php?action=login');
    exit;
}

$token = $_SESSION['token'];
$otherId = isset($_GET['user']) ? (int)$_GET['user'] : null;

// Отправка сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $otherId && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content !== '') {
        $resp = apiRequest('POST', '/messages', [
            'receiver_id' => $otherId,
            'content' => $content
        ], $token);
        if ($resp['httpCode'] === 201) {
            // Перенаправляем, чтобы избежать повторной отправки
            header('Location: messages.php?user=' . $otherId);
            exit;
        } else {
            $error = $resp['body']['error'] ?? 'Ошибка отправки';
        }
    }
}

require 'header.php';
?>

<?php if ($otherId): ?>
    <?php
    // Показываем диалог с конкретным пользователем
    $resp = apiRequest('GET', '/messages/' . $otherId, null, $token);
    if ($resp['httpCode'] === 200) {
        $messages = $resp['body']['messages'] ?? [];
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка загрузки сообщений';
        $messages = [];
    }

    // Информация о собеседнике
    $respUser = apiRequest('GET', '/users/' . $otherId);
    $otherUser = ($respUser['httpCode'] === 200) ? $respUser['body']['user'] : null;
    ?>
    <h2>Диалог с <?= $otherUser ? htmlspecialchars($otherUser['full_name'] ?: $otherUser['username']) : 'пользователем' ?></h2>
    <?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="height: 400px; overflow-y: scroll; border:1px solid #ccc; padding:10px; margin-bottom:20px;">
        <?php foreach ($messages as $msg): ?>
            <div style="margin:10px 0; <?= $msg['sender_id'] == $_SESSION['user']['id'] ? 'text-align:right' : '' ?>">
                <div style="display:inline-block; background:<?= $msg['sender_id'] == $_SESSION['user']['id'] ? '#dcf8c6' : '#f1f0f0' ?>; padding:5px 10px; border-radius:10px;">
                    <strong><?= $msg['sender_id'] == $_SESSION['user']['id'] ? 'Я' : htmlspecialchars($otherUser['username'] ?? '') ?>:</strong><br>
                    <?= nl2br(htmlspecialchars($msg['content'])) ?><br>
                    <small><?= $msg['created_at'] ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post">
        <textarea name="content" rows="3" cols="50" required placeholder="Введите сообщение..."></textarea><br>
        <button type="submit">Отправить</button>
    </form>
    <p><a href="messages.php">Назад к списку диалогов</a></p>

<?php else: ?>
    <?php
    // Список диалогов
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    $resp = apiRequest('GET', '/messages', ['limit' => $limit, 'offset' => $offset], $token);
    if ($resp['httpCode'] === 200) {
        $conversations = $resp['body']['conversations'] ?? [];
        $total = $resp['body']['total'] ?? 0;
    } else {
        $error = $resp['body']['error'] ?? 'Ошибка загрузки диалогов';
    }
    ?>
    <h2>Диалоги</h2>
    <?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (empty($conversations)): ?>
        <p>У вас пока нет сообщений.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <tr><th>Собеседник</th><th>Последнее сообщение</th><th>Дата</th><th>Непрочитанные</th><th></th></tr>
            <?php foreach ($conversations as $conv): ?>
                <tr>
                    <td>
                        <a href="user.php?id=<?= $conv['id'] ?>">
                            <?php if ($conv['avatar_url']): ?>
                                <img src="<?= API_BASE_URL . $conv['avatar_url'] ?>" width="30">
                            <?php endif; ?>
                            <?= htmlspecialchars($conv['full_name'] ?: $conv['username']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($conv['last_message']) ?></td>
                    <td><?= $conv['last_at'] ?></td>
                    <td><?= $conv['unread'] ?></td>
                    <td><a href="messages.php?user=<?= $conv['id'] ?>">Открыть</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<?php require 'footer.php'; ?>
</body>
</html>