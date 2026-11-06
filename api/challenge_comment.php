<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];
$submissionId = (int)($_POST['submission_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($submissionId <= 0 || empty($comment)) {
    echo json_encode(['ok' => false, 'error' => 'Недостаточно данных'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Создаем таблицу, если нет
try {
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_comments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `submission_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `comment` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_submission` (`submission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating comments table: ' . $e->getMessage());
}

db_insert(
    'INSERT INTO challenge_comments (submission_id, user_email, comment) VALUES (?, ?, ?)',
    [$submissionId, $userEmail, $comment]
);

echo json_encode(['ok' => true, 'message' => 'Комментарий добавлен'], JSON_UNESCAPED_UNICODE);
exit;