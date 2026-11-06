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
$challengeId = (int)($_POST['challenge_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($challengeId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Не указан ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем участие (БЕЗ COLLATE)
$participation = db_one(
    'SELECT id FROM challenge_participants WHERE challenge_id = ? AND user_email = ?',
    [$challengeId, $userEmail]
);

if (!$participation) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Вы не участвуете'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Загружаем фото
$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    if ($file['size'] <= 5 * 1024 * 1024) {
        $uploadDir = ROOT_DIR . '/uploads/challenges/' . $challengeId;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'sub_' . time() . '_' . uniqid() . '.' . $extension;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
            $photoUrl = '/uploads/challenges/' . $challengeId . '/' . $fileName;
        }
    }
}

db_insert(
    'INSERT INTO challenge_submissions (challenge_id, user_email, photo_url, comment) VALUES (?, ?, ?, ?)',
    [$challengeId, $userEmail, $photoUrl, $comment]
);
/* ─── Логируем отправку работы ─── */
$challengeInfo = db_one('SELECT title FROM challenges WHERE id = ?', [$challengeId]);
$challengeTitle = $challengeInfo['title'] ?? ('#' . $challengeId);

log_activity(
    'challenge_submitted',
    $challengeId,
    'challenge',
    'Отправил работу на челлендж: ' . $challengeTitle
);

echo json_encode(['ok' => true, 'message' => 'Работа отправлена!'], JSON_UNESCAPED_UNICODE);
exit;