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
$submissionId = (int)($_GET['id'] ?? 0);

if ($submissionId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Не указан ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем владельца
$submission = db_one(
    "SELECT * FROM challenge_submissions WHERE id = ? AND user_email = ? COLLATE utf8mb4_unicode_ci",
    [$submissionId, $userEmail]
);

if (!$submission) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Вы не владелец этой работы'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Удаляем фото, если есть
if (!empty($submission['photo_url'])) {
    $photoPath = ROOT_DIR . $submission['photo_url'];
    if (file_exists($photoPath)) {
        @unlink($photoPath);
    }
}

db_exec('DELETE FROM challenge_submissions WHERE id = ?', [$submissionId]);

echo json_encode(['ok' => true, 'message' => 'Работа удалена'], JSON_UNESCAPED_UNICODE);
exit;