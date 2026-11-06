<?php
/**
 * POST /api/cookbook_update_recipes.php
 * Обновляет recipes_json существующей книги (UPDATE).
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Только POST'], 405);
}
if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Требуется вход'], 401);
}

$in = json_input();
$bookId = (int)($in['book_id'] ?? 0);
$recipes = $in['recipes'] ?? [];

if ($bookId <= 0) {
    json_response(['ok' => false, 'error' => 'Не указан book_id'], 400);
}
if (!is_array($recipes)) {
    json_response(['ok' => false, 'error' => 'recipes должен быть массивом'], 400);
}

$user = current_user();
$book = db_one('SELECT id, author_email FROM cookbooks WHERE id = ?', [$bookId]);
if (!$book) {
    json_response(['ok' => false, 'error' => 'Книга не найдена'], 404);
}
if ($book['author_email'] !== $user['email']) {
    json_response(['ok' => false, 'error' => 'Нет доступа'], 403);
}

db_exec(
    'UPDATE cookbooks SET recipes_json = ?, updated_at = NOW() WHERE id = ?',
    [json_encode($recipes, JSON_UNESCAPED_UNICODE), $bookId]
);

json_response(['ok' => true, 'count' => count($recipes)]);