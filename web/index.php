<?php
require_once 'config.php';
require_once 'header.php';

// Получаем категории
$categories = [];
$resp = apiRequest('GET', '/categories');
if ($resp['httpCode'] === 200) {
    $categories = $resp['body']['categories'] ?? [];
}

// Получаем популярные навыки
$skills = [];
$resp = apiRequest('GET', '/skills', ['limit' => 10]);
if ($resp['httpCode'] === 200) {
    $skills = $resp['body']['skills'] ?? [];
}

// Поиск партнёров
$searchResults = null;
$searchError = null;
if (isset($_GET['teach']) && isset($_GET['learn'])) {
    $teach = (int)$_GET['teach'];
    $learn = (int)$_GET['learn'];
    $resp = apiRequest('GET', '/users/search', ['teach' => $teach, 'learn' => $learn]);
    if ($resp['httpCode'] === 200) {
        $searchResults = $resp['body']['users'] ?? [];
    } else {
        $searchError = $resp['body']['error'] ?? 'Ошибка поиска';
    }
}
?>

<h1>Skills Exchange</h1>

<h2>Категории</h2>
<ul>
    <?php foreach ($categories as $cat): ?>
        <li><?= htmlspecialchars($cat['name']) ?></li>
    <?php endforeach; ?>
</ul>

<h2>Популярные навыки</h2>
<ul>
    <?php foreach ($skills as $skill): ?>
        <li><?= htmlspecialchars($skill['name']) ?> (<?= htmlspecialchars($skill['category_name'] ?? '') ?>)</li>
    <?php endforeach; ?>
</ul>

<h2>Поиск партнёра</h2>
<form method="get">
    <label>Я учу (skill ID): <input type="number" name="teach" required></label>
    <label>Хочу учить (skill ID): <input type="number" name="learn" required></label>
    <button type="submit">Найти</button>
</form>

<?php if ($searchResults !== null): ?>
    <h3>Результаты поиска</h3>
    <?php if (empty($searchResults)): ?>
        <p>Ничего не найдено</p>
    <?php else: ?>
        <?php foreach ($searchResults as $user): ?>
            <div>
                <a href="user.php?id=<?= $user['id'] ?>">
                    <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php elseif (isset($searchError)): ?>
    <div class="error"><?= htmlspecialchars($searchError) ?></div>
<?php endif; ?>

<?php require 'footer.php'; ?>
</body>
</html>