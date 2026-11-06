<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$theme = $data['theme'] ?? 'light';

if (!in_array($theme, ['light', 'dark'])) {
    echo json_encode(['ok' => false, 'error' => 'Неверная тема']);
    exit;
}

$email = current_user()['email'];

try {
    db_exec('UPDATE users SET theme = ? WHERE email = ?', [$theme, $email]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}