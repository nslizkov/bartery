<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/helpers.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/+#', '', $path);
$path = preg_replace('#\?.*$#', '', $path);

$method = $_SERVER['REQUEST_METHOD'];
$segments = $path ? explode('/', $path) : [];

// Не API — отдаём веб-модуль или статику (на случай если nginx отдаёт всё в index.php)
if (($segments[0] ?? '') !== 'api') {
    $webDir = realpath(__DIR__ . '/../web') ?: (__DIR__ . '/../web');
    $requestPath = $path === '' ? 'index.html' : $path;
    $requestPath = str_replace(['../', '..\\'], '', $requestPath);
    // uploads/ — из корня проекта
    if (strpos($requestPath, 'uploads/') === 0) {
        $file = __DIR__ . '/../' . $requestPath;
        if (is_file($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            if (isset($mimes[$ext])) header('Content-Type: ' . $mimes[$ext]);
            readfile($file);
            exit;
        }
    }
    $file = $webDir . '/' . $requestPath;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            chdir(dirname($file));
            require $file;
            exit;
        }
        $mimes = ['html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json', 'ico' => 'image/x-icon', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext] . '; charset=utf-8');
        }
        readfile($file);
        exit;
    }
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.html';
        if (is_file($file)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($file);
            exit;
        }
    }
    if ($requestPath === 'index.html' || $requestPath === '') {
        $file = $webDir . '/index.html';
        if (is_file($file)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($file);
            exit;
        }
    }
    jsonResponse(['message' => 'Skills Exchange API', 'version' => '1.0']);
}

$resource = $segments[1] ?? '';
$id = $segments[2] ?? null;
$sub = $segments[3] ?? null;
$subId = $segments[4] ?? null;

try {
    $db = Database::get();
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database connection failed'], 500);
}

// Auth routes
if ($resource === 'auth') {
    require __DIR__ . '/../src/api/auth.php';
    exit;
}

// Categories
if ($resource === 'categories') {
    require __DIR__ . '/../src/api/categories.php';
    exit;
}

// Skills
if ($resource === 'skills') {
    require __DIR__ . '/../src/api/skills.php';
    exit;
}

// Users
if ($resource === 'users') {
    require __DIR__ . '/../src/api/users.php';
    exit;
}

// Messages
if ($resource === 'messages') {
    require __DIR__ . '/../src/api/messages.php';
    exit;
}

// Reviews
if ($resource === 'reviews') {
    require __DIR__ . '/../src/api/reviews.php';
    exit;
}

// Video calls
if ($resource === 'video-calls') {
    require __DIR__ . '/../src/api/video_calls.php';
    exit;
}

// Badges
if ($resource === 'badges') {
    require __DIR__ . '/../src/api/badges.php';
    exit;
}

jsonResponse(['error' => 'Not found'], 404);
