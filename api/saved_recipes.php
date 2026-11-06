<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Проверяем авторизацию
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];

// Создаем таблицу избранного, если её нет
try {
    db_exec("CREATE TABLE IF NOT EXISTS `favorites` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_email` varchar(255) NOT NULL,
        `recipe_id` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_favorite` (`user_email`, `recipe_id`),
        INDEX `idx_recipe` (`recipe_id`),
        INDEX `idx_user` (`user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating favorites table: ' . $e->getMessage());
}

// POST — переключение избранного
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_input();
    $recipeId = (int)($input['recipe_id'] ?? 0);
    
    if ($recipeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем, существует ли рецепт
    $recipe = db_one('SELECT id FROM recipes WHERE id = ?', [$recipeId]);
    if (!$recipe) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Рецепт не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // Проверяем, сохранен ли уже рецепт
        $existing = db_one(
            'SELECT id FROM favorites WHERE user_email = ? AND recipe_id = ?',
            [$userEmail, $recipeId]
        );
        
        if ($existing) {
            // Удаляем из сохраненных
            db_exec(
                'DELETE FROM favorites WHERE id = ?',
                [$existing['id']]
            );
            $saved = false;
        } else {
            // Добавляем в сохраненные
            db_insert(
                'INSERT INTO favorites (user_email, recipe_id) VALUES (?, ?)',
                [$userEmail, $recipeId]
            );
            $saved = true;
    
    // Начисляем очки АВТОРУ рецепта
    $recipeAuthor = db_one('SELECT author_id FROM recipes WHERE id = ?', [$recipeId]);
    if ($recipeAuthor && (int)$recipeAuthor['author_id'] !== (int)current_user()['id']) {
        add_user_points((int)$recipeAuthor['author_id'], 1, 'favorite', 'Ваш рецепт добавили в избранное');
    }
        }
        
        echo json_encode([
            'ok' => true,
            'saved' => $saved
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Save recipe error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при сохранении'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// GET — проверка статуса избранного
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipeId = (int)($_GET['recipe_id'] ?? 0);
    
    if ($recipeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $existing = db_one(
            'SELECT id FROM favorites WHERE user_email = ? AND recipe_id = ?',
            [$userEmail, $recipeId]
        );
        
        echo json_encode([
            'ok' => true,
            'saved' => $existing !== null
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при проверке'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;