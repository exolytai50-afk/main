<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$communityId = (int)($_POST['community_id'] ?? 0);
$email = trim($_POST['email'] ?? '');

if ($communityId <= 0 || empty($email)) {
    echo json_encode(['ok' => false, 'error' => 'Недостаточно данных'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем сообщество
$community = db_one('SELECT * FROM communities WHERE id = ?', [$communityId]);
if (!$community) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Сообщество не найдено'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем права
$currentEmail = current_user()['email'];
if ($community['author_email'] !== $currentEmail && !is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Только владелец может приглашать'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем, что пользователь существует
$invitedUser = db_one('SELECT * FROM users WHERE email = ?', [$email]);
if (!$invitedUser) {
    echo json_encode(['ok' => false, 'error' => 'Пользователь не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Создаем уведомление
db_insert(
    'INSERT INTO notifications (user_email, type, title, message, related_email, is_read, created_at) 
     VALUES (CONVERT(? USING utf8mb4), ?, CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4), 0, NOW())',
    [
        $email,
        'community_invite',
        'Приглашение в сообщество',
        'Вас пригласили в сообщество «' . $community['name'] . '»',
        $currentEmail
    ]
);

echo json_encode(['ok' => true, 'message' => 'Приглашение отправлено'], JSON_UNESCAPED_UNICODE);
exit;