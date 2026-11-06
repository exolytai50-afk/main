<?php
/**
 * POST /api/challenges.php?join=ID — вступить в челлендж
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];
$userId = (int)current_user()['id'];
$challengeId = (int)($_GET['join'] ?? 0);

if ($challengeId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Не указан ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем челлендж
$challenge = db_one(
    'SELECT id, title, status FROM challenges WHERE id = ?',
    [$challengeId]
);

if (!$challenge) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Челлендж не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($challenge['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Челлендж не активен'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Таблица на всякий случай
try {
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_participants` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challenge_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `challenge_user` (`challenge_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Table creation error: ' . $e->getMessage());
}

// Проверяем участие
$existing = db_one(
    'SELECT id FROM challenge_participants WHERE challenge_id = ? AND user_email = ?',
    [$challengeId, $userEmail]
);

if ($existing) {
    $count = db_one(
        'SELECT COUNT(*) as cnt FROM challenge_participants WHERE challenge_id = ?',
        [$challengeId]
    );
    echo json_encode([
        'ok' => true,
        'already_joined' => true,
        'participants_count' => (int)($count['cnt'] ?? 0)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Добавляем
    db_insert(
        'INSERT INTO challenge_participants (challenge_id, user_email) VALUES (?, ?)',
        [$challengeId, $userEmail]
    );

    // Обновляем счётчик
    db_exec(
        'UPDATE challenges SET participants = COALESCE(participants, 0) + 1 WHERE id = ?',
        [$challengeId]
    );

    // Начисляем очки за вступление
    try {
        add_user_points(
            $userId,
            5,
            'challenge_join',
            'Вступил в челлендж: ' . ($challenge['title'] ?? '')
        );
    } catch (Throwable $e) {
        error_log('add_user_points (challenge_join): ' . $e->getMessage());
    }

    // Активность
    try {
        if (function_exists('log_activity')) {
            log_activity(
                'challenge_joined',
                $challengeId,
                'challenge',
                'Присоединился к челленджу: ' . ($challenge['title'] ?? '')
            );
        }
    } catch (Throwable $e) {
        error_log('log_activity (challenge_join): ' . $e->getMessage());
    }

    // Достижения
    try {
        if (function_exists('check_achievements')) {
            check_achievements($userEmail);
        }
    } catch (Throwable $e) {
        error_log('check_achievements (challenge_join): ' . $e->getMessage());
    }

} catch (Throwable $e) {
    error_log('Insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка вступления'], JSON_UNESCAPED_UNICODE);
    exit;
}

$count = db_one(
    'SELECT COUNT(*) as cnt FROM challenge_participants WHERE challenge_id = ?',
    [$challengeId]
);

echo json_encode([
    'ok' => true,
    'message' => 'Вы присоединились к челленджу',
    'participants_count' => (int)($count['cnt'] ?? 0)
], JSON_UNESCAPED_UNICODE);
exit;