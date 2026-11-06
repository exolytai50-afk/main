<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newsId = (int)($input['news_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);

if ($newsId <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Некорректные данные']);
    exit;
}

$userEmail = current_user()['email'] ?? 'guest_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Проверяем, голосовал ли пользователь
$existing = db_one('SELECT id, rating FROM news_ratings WHERE news_id = ? AND user_email = ?', [$newsId, $userEmail]);

if ($existing) {
    // Обновляем оценку
    $oldRating = (int)$existing['rating'];
    db_exec('UPDATE news_ratings SET rating = ? WHERE id = ?', [$rating, $existing['id']]);
    db_exec('UPDATE news SET rating_sum = rating_sum - ? + ? WHERE id = ?', [$oldRating, $rating, $newsId]);
} else {
    // Новая оценка
    db_exec('INSERT INTO news_ratings (news_id, user_email, rating) VALUES (?,?,?)', [$newsId, $userEmail, $rating]);
    db_exec('UPDATE news SET rating_sum = rating_sum + ?, rating_count = rating_count + 1 WHERE id = ?', [$rating, $newsId]);
}

echo json_encode(['ok' => true]);