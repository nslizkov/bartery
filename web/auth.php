<?php
require_once 'config.php';
session_start();

$action = $_GET['action'] ?? '';

// Выход
if ($action === 'logout') {
    if (isset($_SESSION['token'])) {
        apiRequest('POST', '/auth/logout', [], $_SESSION['token']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// Обработка форм
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $resp = apiRequest('POST', '/auth/login', ['email' => $email, 'password' => $password]);
        if ($resp['httpCode'] === 200 && isset($resp['body']['token'])) {
            $_SESSION['token'] = $resp['body']['token'];
            $_SESSION['user'] = $resp['body']['user'];
            header('Location: index.php');
            exit;
        } else {
            $error = $resp['body']['error'] ?? 'Ошибка входа';
        }
    } elseif ($action === 'register') {
        $data = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'full_name' => $_POST['full_name'] ?? ''
        ];
        $resp = apiRequest('POST', '/auth/register', $data);
        if ($resp['httpCode'] === 201 && isset($resp['body']['token'])) {
            $_SESSION['token'] = $resp['body']['token'];
            $_SESSION['user'] = $resp['body']['user'];
            header('Location: index.php');
            exit;
        } else {
            $error = $resp['body']['error'] ?? ('Ошибка регистрации'.((string)$resp['body']));
        }
    }
}

require 'header.php';
?>

<?php if ($action === 'login'): ?>
    <h2>Вход</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <div><label>Email:</label> <input type="email" name="email" required></div>
        <div><label>Пароль:</label> <input type="password" name="password" required></div>
        <button type="submit">Войти</button>
    </form>
<?php elseif ($action === 'register'): ?>
    <h2>Регистрация</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <div><label>Имя пользователя:</label> <input type="text" name="username" required></div>
        <div><label>Email:</label> <input type="email" name="email" required></div>
        <div><label>Пароль (мин. 6 символов):</label> <input type="password" name="password" required></div>
        <div><label>Полное имя:</label> <input type="text" name="full_name"></div>
        <button type="submit">Зарегистрироваться</button>
    </form>
<?php else: ?>
    <?php header('Location: index.php'); exit; ?>
<?php endif; ?>

<?php require 'footer.php'; ?>
</body>
</html>