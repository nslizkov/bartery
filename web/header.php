<?php
session_start();
$isLoggedIn = isset($_SESSION['token']);
$currentUser = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Skills Exchange</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .nav { background: #f0f0f0; padding: 10px; margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .nav a:hover { text-decoration: underline; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
<div class="nav">
    <a href="index.php">Главная</a>
    <?php if ($isLoggedIn): ?>
        <a href="profile.php">Мой профиль</a>
        <a href="profile.php?action=edit">Редактировать профиль</a>
        <a href="profile.php?action=skills">Мои навыки</a>
        <a href="messages.php">Сообщения</a>
        <a href="user.php?badges=all">Бейджи</a>
        <a href="auth.php?action=logout">Выход (<?= htmlspecialchars($currentUser['username'] ?? '') ?>)</a>
    <?php else: ?>
        <a href="auth.php?action=login">Вход</a>
        <a href="auth.php?action=register">Регистрация</a>
    <?php endif; ?>
</div>