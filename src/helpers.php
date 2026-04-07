<?php

function formatDatesForApi(mixed $value): mixed
{
    if (is_array($value)) {
        return array_map(__FUNCTION__, $value);
    }
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        return str_replace(' ', 'T', $value) . 'Z';
    }
    return $value;
}

function logApiError(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/api_errors.log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'request' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ],
    ];
    
    // Add authenticated user info if available
    try {
        $user = getAuthUser();
        if ($user) {
            $logEntry['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
            ];
        }
    } catch (Exception $e) {
        // Ignore if auth fails
    }
    
    // Add request body if available (for POST/PUT/PATCH)
    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        if ($body) {
            $logEntry['request']['body'] = $body;
            // Reset input stream for further processing
            file_put_contents('php://input', $body);
        }
    }
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

function jsonResponse(array $data, int $status = 200): void
{
    $data = formatDatesForApi($data);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): ?array
{
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function getAuthUser(): ?array
{
    $auth = getallheaders()['Authorization'] ?? getallheaders()['authorization'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
        return null;
    }
    $token = $m[1];
    $db = Database::get();
    $stmt = $db->prepare('SELECT id, username, email, full_name, bio, avatar_url, role, points FROM users WHERE api_token = ? AND is_active = 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireAuth(): array
{
    $user = getAuthUser();
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    return $user;
}

function getInt(array $data, string $key, ?int $default = null): ?int
{
    if (!isset($data[$key])) {
        return $default;
    }
    return filter_var($data[$key], FILTER_VALIDATE_INT) ?: $default;
}
