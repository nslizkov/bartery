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
