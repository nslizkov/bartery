<?php
/**
 * Admin panel — просмотр и редактирование БД. Без авторизации (временно).
 * Удалить перед продакшеном.
 */
require_once __DIR__ . '/../../src/Database.php';

$db = Database::get();
$tables = ['users', 'categories', 'skills', 'user_skills', 'messages', 'reviews', 'video_calls', 'badges', 'user_badges'];
$stats = [];
foreach ($tables as $t) {
    try {
        $stats[$t] = $db->query("SELECT COUNT(*) as c FROM `$t`")->fetch()['c'];
    } catch (Throwable $e) {
        $stats[$t] = '-';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Админка — БД</title>
    <link rel="stylesheet" href="../css/style.css">
    <style> table.admin-table { width: 100%; border-collapse: collapse; } .admin-table th, .admin-table td { border: 1px solid #ddd; padding: .5rem; text-align: left; } .admin-table th { background: #f5f5f5; } .admin-table tr:hover { background: #f9f9f9; } </style>
</head>
<body>
<div class="nav">
    <a href="../index.html">← На сайт</a>
    <span class="right">Админка (без авторизации)</span>
</div>
<div class="container">
    <h1>Таблицы БД</h1>
    <ul>
        <?php foreach ($tables as $t): ?>
            <li><a href="table.php?name=<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></a> — <?= $stats[$t] ?> записей</li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>
