<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$gameId = (int)($input['game_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);

if ($gameId <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректные данные']);
    exit;
}

// Проверяем существование игры
$game = db_one('SELECT id, rating, rating_count, rating_sum FROM games WHERE id = ?', [$gameId]);

if (!$game) {
    http_response_code(404);
    echo json_encode(['error' => 'Игра не найдена']);
    exit;
}

// Проверяем, голосовал ли пользователь
$userEmail = current_user()['email'] ?? null;
$canRate = true;

if ($userEmail) {
    // Проверяем в таблице game_ratings
    $existingRating = db_one('SELECT id FROM game_ratings WHERE game_id = ? AND user_email = ?', [$gameId, $userEmail]);
    if ($existingRating) {
        // Обновляем существующую оценку
        $oldRating = db_one('SELECT rating FROM game_ratings WHERE id = ?', [$existingRating['id']]);
        $oldRatingValue = (int)($oldRating['rating'] ?? 0);
        
        db_exec('UPDATE game_ratings SET rating = ? WHERE id = ?', [$rating, $existingRating['id']]);
        
        // Обновляем сумму рейтингов
        $newRatingSum = (float)$game['rating_sum'] - $oldRatingValue + $rating;
        $newRatingCount = (int)$game['rating_count'];
    } else {
        // Добавляем новую оценку
        db_exec('INSERT INTO game_ratings (game_id, user_email, rating) VALUES (?,?,?)', [$gameId, $userEmail, $rating]);
        
        $newRatingSum = (float)$game['rating_sum'] + $rating;
        $newRatingCount = (int)$game['rating_count'] + 1;
    }
    
    $newRating = round($newRatingSum / max(1, $newRatingCount), 1);
    
    db_exec('UPDATE games SET rating = ?, rating_count = ?, rating_sum = ? WHERE id = ?', 
        [$newRating, $newRatingCount, $newRatingSum, $gameId]);
    
    echo json_encode([
        'ok' => true,
        'new_rating' => $newRating,
        'rating_count' => $newRatingCount
    ]);
} else {
    // Для неавторизованных пользователей используем session
    $ratedGames = $_SESSION['rated_games'] ?? [];
    
    if (isset($ratedGames[$gameId])) {
        $oldRating = $ratedGames[$gameId];
        $newRatingSum = (float)$game['rating_sum'] - $oldRating + $rating;
        $newRatingCount = (int)$game['rating_count'];
        $ratedGames[$gameId] = $rating;
    } else {
        $newRatingSum = (float)$game['rating_sum'] + $rating;
        $newRatingCount = (int)$game['rating_count'] + 1;
        $ratedGames[$gameId] = $rating;
    }
    
    $newRating = round($newRatingSum / max(1, $newRatingCount), 1);
    
    db_exec('UPDATE games SET rating = ?, rating_count = ?, rating_sum = ? WHERE id = ?', 
        [$newRating, $newRatingCount, $newRatingSum, $gameId]);
    
    $_SESSION['rated_games'] = $ratedGames;
    
    echo json_encode([
        'ok' => true,
        'new_rating' => $newRating,
        'rating_count' => $newRatingCount
    ]);
}