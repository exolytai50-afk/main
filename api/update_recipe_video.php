<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}
if (!csrf_check()) {
    json_response(['ok' => false, 'error' => 'Неверный CSRF-токен'], 403);
}

$in = json_input();
$recipeId = (int)($in['recipe_id'] ?? 0);
$videoUrl = trim((string)($in['video_url'] ?? ''));

if ($recipeId <= 0) {
    json_response(['ok' => false, 'error' => 'Неверный ID рецепта'], 400);
}
if ($videoUrl === '') {
    json_response(['ok' => false, 'error' => 'Ссылка не указана'], 400);
}

$user = current_user();
$recipe = db_one('SELECT id, author_email FROM recipes WHERE id = ?', [$recipeId]);
if (!$recipe) {
    json_response(['ok' => false, 'error' => 'Рецепт не найден'], 404);
}
if ($recipe['author_email'] !== $user['email'] && !is_admin()) {
    json_response(['ok' => false, 'error' => 'Нет прав на редактирование'], 403);
}

try {
    db_exec(
        'UPDATE recipes SET video_url = ?, video_owner_id = NULL, video_id = NULL WHERE id = ?',
        [$videoUrl, $recipeId]
    );
    json_response(['ok' => true, 'message' => 'Ссылка сохранена']);
} catch (Throwable $e) {
    error_log('update_recipe_video error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Ошибка сохранения'], 500);
}