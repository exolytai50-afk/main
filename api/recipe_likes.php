<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// GET — получение лайков
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipeId = (int)($_GET['recipe_id'] ?? 0);
    
    if ($recipeId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // Берем значение из таблицы recipes
        $recipe = db_one('SELECT likes_count FROM recipes WHERE id = ?', [$recipeId]);
        $likesCount = (int)($recipe['likes_count'] ?? 0);
        
        $liked = false;
        if (is_logged_in()) {
            $userEmail = current_user()['email'];
            $existingLike = db_one(
                'SELECT id FROM recipe_likes WHERE recipe_id = ? AND user_email = ?',
                [$recipeId, $userEmail]
            );
            $liked = $existingLike !== null;
        }
        
        echo json_encode([
            'ok' => true,
            'liked' => $liked,
            'likes_count' => $likesCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST — переключение лайка
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $userEmail = current_user()['email'];
    $input = json_input();
    $recipeId = (int)($input['recipe_id'] ?? 0);
    
    if ($recipeId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // Получаем текущее значение
        $recipe = db_one('SELECT likes_count FROM recipes WHERE id = ?', [$recipeId]);
        $currentLikes = (int)($recipe['likes_count'] ?? 0);
        
        $existingLike = db_one(
            'SELECT id FROM recipe_likes WHERE recipe_id = ? AND user_email = ?',
            [$recipeId, $userEmail]
        );
        
        if ($existingLike) {
    db_exec('DELETE FROM recipe_likes WHERE id = ?', [$existingLike['id']]);
    $liked = false;
    $newCount = max(0, $currentLikes - 1);
} else {
    db_insert('INSERT INTO recipe_likes (recipe_id, user_email) VALUES (?, ?)', [$recipeId, $userEmail]);
    $liked = true;
    $newCount = $currentLikes + 1;
}
        
        // Обновляем счетчик
        db_exec('UPDATE recipes SET likes_count = ? WHERE id = ?', [$newCount, $recipeId]);
        
        echo json_encode([
            'ok' => true,
            'liked' => $liked,
            'likes_count' => $newCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}