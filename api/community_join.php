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
$userEmail = current_user()['email'];

if ($communityId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Не указано сообщество'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Создаем таблицу
try {
    db_exec("CREATE TABLE IF NOT EXISTS `community_members` (
        `id` int NOT NULL AUTO_INCREMENT,
        `community_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_member` (`community_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
} catch (Throwable $e) {}

// Проверяем существование сообщества
$community = db_one('SELECT * FROM communities WHERE id = ?', [$communityId]);
if (!$community) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Сообщество не найдено'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем членство
$existingMember = db_one(
    'SELECT id FROM community_members 
     WHERE community_id = ? AND CONVERT(user_email USING utf8mb4) = CONVERT(? USING utf8mb4)',
    [$communityId, $userEmail]
);

if ($existingMember) {
    echo json_encode(['ok' => true, 'already_member' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// Добавляем
db_insert(
    'INSERT INTO community_members (community_id, user_email, joined_at) 
     VALUES (?, CONVERT(? USING utf8mb4), NOW())',
    [$communityId, $userEmail]
);
$userId = (int)current_user()['id'];
add_user_points($userId, 5, 'community', 'Вступил в сообщество #' . $communityId);

if (function_exists('check_achievements')) {
    check_achievements($userEmail);
}

// Обновляем счетчик
// Обновляем счетчик
db_exec(
    'UPDATE communities SET members_count = COALESCE(members_count, 0) + 1 WHERE id = ?',
    [$communityId]
);

// ⬇ НАЧИСЛЯЕМ ОЧКИ ЗА ВСТУПЛЕНИЕ
try {
    $userId = (int)current_user()['id'];
    add_user_points(
        $userId,
        5,
        'community',
        'Вступил в сообщество: ' . ($community['name'] ?? '')
    );
} catch (Throwable $e) {
    error_log('add_user_points (community_join): ' . $e->getMessage());
}

// ⬇ ПРОВЕРЯЕМ ДОСТИЖЕНИЯ
try {
    if (function_exists('check_achievements')) {
        check_achievements($userEmail);
    }
} catch (Throwable $e) {
    error_log('check_achievements (community_join): ' . $e->getMessage());
}

// ⬇ ЛОГИРУЕМ АКТИВНОСТЬ (опционально)
try {
    if (function_exists('add_activity')) {
        add_activity(
            $userEmail,
            'community',
            $communityId,
            'community',
            'Вступил в сообщество: ' . ($community['name'] ?? '')
        );
    }
} catch (Throwable $e) {
    error_log('add_activity (community_join): ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'message' => 'Вы вступили в сообщество'], JSON_UNESCAPED_UNICODE);
exit;