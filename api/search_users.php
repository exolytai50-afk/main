<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim($_GET['q'] ?? '');
$currentEmail = current_user()['email'];

if (empty($q)) {
    echo json_encode(['users' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$users = db_all(
    "SELECT id, email, display_name, avatar_emoji, avatar_url 
     FROM users 
     WHERE email != ? 
     AND (display_name LIKE ? OR email LIKE ?)
     LIMIT 10",
    [$currentEmail, "%{$q}%", "%{$q}%"]
);

echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
exit;