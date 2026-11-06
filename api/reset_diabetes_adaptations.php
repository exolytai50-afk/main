<?php
/**
 * Скрытие всех адаптаций для рецепта (только для админа)
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

if (!is_admin()) {
    echo json_encode(['ok' => false, 'error' => 'Требуются права администратора']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Только POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$recipeId = (int)($input['recipe_id'] ?? 0);

if ($recipeId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Некорректный ID рецепта']);
    exit;
}

// НЕ удаляем, а просто скрываем (is_hidden = 1)
db_exec('UPDATE diabetes_adapted_recipes SET is_hidden = 1 WHERE original_recipe_id = ?', [$recipeId]);

echo json_encode(['ok' => true, 'message' => 'Все адаптации скрыты']);