<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Отладка
error_log('POST: ' . json_encode($_POST));
error_log('FILES: ' . json_encode($_FILES));

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];
$submissionId = (int)($_POST['submission_id'] ?? 0);

if ($submissionId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Не указан ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем владельца
$submission = db_one(
    'SELECT * FROM challenge_submissions WHERE id = ? AND user_email = ?',
    [$submissionId, $userEmail]
);

if (!$submission) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Вы не владелец этой работы'], JSON_UNESCAPED_UNICODE);
    exit;
}

$comment = trim($_POST['comment'] ?? '');
$photoRemoved = ($_POST['photo_removed'] ?? '0') === '1';
$photoUrl = $submission['photo_url'] ?? null;

error_log('Current photo: ' . ($photoUrl ?? 'null'));
error_log('Photo removed: ' . ($photoRemoved ? 'yes' : 'no'));

// Если фото нужно удалить
if ($photoRemoved && $photoUrl) {
    $photoPath = ROOT_DIR . $photoUrl;
    if (file_exists($photoPath)) {
        @unlink($photoPath);
        error_log('Deleted old photo: ' . $photoPath);
    }
    $photoUrl = null;
}

// Если загружено новое фото
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    error_log('New photo: ' . $file['name'] . ' size: ' . $file['size']);
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'Файл больше 5 МБ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем тип
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['ok' => false, 'error' => 'Недопустимый формат'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $challengeId = (int)$submission['challenge_id'];
    $uploadDir = ROOT_DIR . '/uploads/challenges/' . $challengeId;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        error_log('Created dir: ' . $uploadDir);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!$extension) {
        $extension = 'jpg';
    }
    
    $fileName = 'sub_' . time() . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . '/' . $fileName;
    
    error_log('Upload path: ' . $uploadPath);
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Удаляем старое фото
        if ($photoUrl && file_exists(ROOT_DIR . $photoUrl)) {
            @unlink(ROOT_DIR . $photoUrl);
            error_log('Deleted old photo: ' . ROOT_DIR . $photoUrl);
        }
        $photoUrl = '/uploads/challenges/' . $challengeId . '/' . $fileName;
        error_log('New photo URL: ' . $photoUrl);
    } else {
        error_log('Failed to move uploaded file');
    }
}

// Обновляем запись
db_exec(
    'UPDATE challenge_submissions SET photo_url = ?, comment = ? WHERE id = ?',
    [$photoUrl, $comment, $submissionId]
);

error_log('Updated submission ' . $submissionId . ' with photo: ' . ($photoUrl ?? 'null'));

echo json_encode([
    'ok' => true, 
    'message' => 'Работа обновлена',
    'photo_url' => $photoUrl
], JSON_UNESCAPED_UNICODE);
exit;