<?php
/**
 * Просмотр и редактирование таблицы. Без авторизации (временно).
 */
require_once __DIR__ . '/../../src/Database.php';

$db = Database::get();
$table = isset($_GET['name']) ? preg_replace('/[^a-z_0-9]/', '', $_GET['name']) : '';
$allowed = ['users', 'categories', 'skills', 'user_skills', 'messages', 'reviews', 'video_calls', 'badges', 'user_badges'];
if (!in_array($table, $allowed, true)) {
    header('Location: index.php');
    exit;
}

// Actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && !empty($_POST['pk'])) {
        $pk = json_decode($_POST['pk'], true);
        if (is_array($pk)) {
            $where = [];
            $vals = [];
            foreach ($pk as $col => $val) {
                $where[] = "`" . preg_replace('/[^a-z_0-9_]/', '', $col) . "` = ?";
                $vals[] = $val;
            }
            try {
                $db->prepare('DELETE FROM `' . $table . '` WHERE ' . implode(' AND ', $where))->execute($vals);
                $msg = 'Запись удалена.';
            } catch (Throwable $e) {
                $msg = 'Ошибка: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
    if ($action === 'insert') {
        $cols = [];
        $vals = [];
        $placeholders = [];
        foreach ($_POST as $k => $v) {
            if ($k === 'action') continue;
            if (!preg_match('/^[a-z_0-9]+$/i', $k)) continue;
            $cols[] = "`$k`";
            $placeholders[] = '?';
            $vals[] = $v === '' ? null : $v;
        }
        if (!empty($cols)) {
            try {
                $db->prepare('INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')')->execute($vals);
                $msg = 'Запись добавлена.';
            } catch (Throwable $e) {
                $msg = 'Ошибка: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Columns
$cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');

// Primary key
$pkCols = array_filter($cols, fn($c) => ($c['Key'] ?? '') === 'PRI');
$pkNames = array_column($pkCols, 'Field');

// Fetch rows
$order = 'ORDER BY ' . ($pkNames ? '`' . $pkNames[0] . '`' : '1') . ' LIMIT 200';
$rows = $db->query("SELECT * FROM `$table` $order")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Таблица <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table.admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .admin-table th, .admin-table td { border: 1px solid #ddd; padding: .4rem .6rem; text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
        .admin-table th { background: #f0f0f0; }
        .admin-table tr:hover { background: #f9f9f9; }
        .admin-table td input { width: 100%; max-width: 180px; padding: .2rem; }
        .btn-sm { padding: .2rem .4rem; font-size: 12px; }
    </style>
</head>
<body>
<div class="nav">
    <a href="index.php">← К списку таблиц</a>
</div>
<div class="container">
    <h1><?= htmlspecialchars($table) ?></h1>
    <?php if ($msg): ?><p class="alert <?= strpos($msg, 'Ошибка') !== false ? 'alert-danger' : 'alert-success' ?>"><?= $msg ?></p><?php endif; ?>

    <div class="card">
        <h2>Добавить запись</h2>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <input type="hidden" name="action" value="insert">
            <?php foreach ($colNames as $c):
                $colMeta = $cols[array_search($c, array_column($cols, 'Field'))] ?? [];
                $isAuto = !empty($colMeta['Extra']) && stripos($colMeta['Extra'], 'auto_increment') !== false;
                if ($isAuto) continue;
            ?>
                <div class="form-group" style="margin:0; min-width: 120px;">
                    <label style="font-size:12px;"><?= htmlspecialchars($c) ?></label>
                    <input type="text" name="<?= htmlspecialchars($c) ?>" placeholder="<?= htmlspecialchars($c) ?>" style="width:100%;">
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-sm">Добавить</button>
        </form>
    </div>

    <p>Записей: <?= count($rows) ?> (не более 200)</p>
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead><tr>
                <?php foreach ($colNames as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($colNames as $c): ?>
                        <td title="<?= htmlspecialchars((string)$r[$c]) ?>"><?= htmlspecialchars(mb_substr((string)$r[$c], 0, 50)) ?><?= mb_strlen((string)$r[$c]) > 50 ? '…' : '' ?></td>
                    <?php endforeach; ?>
                    <td>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Удалить?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="pk" value="<?= htmlspecialchars(json_encode(array_intersect_key($r, array_flip($pkNames)))) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
