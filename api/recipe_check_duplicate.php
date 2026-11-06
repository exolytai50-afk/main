<?php
/**
 * POST /api/recipe_check_duplicate.php
 * Проверка, существует ли похожий рецепт.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/recipe_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Только POST']);
    exit;
}

$in = json_input();
$title = trim((string)($in['title'] ?? ''));
$ingredients = (array)($in['ingredients'] ?? []);

if (empty($title)) {
    echo json_encode(['ok' => false, 'error' => 'Не указано название']);
    exit;
}

$userId = is_logged_in() ? (int)current_user()['id'] : null;

$duplicate = check_recipe_duplicate($title, $ingredients, $userId, 0.75);

echo json_encode([
    'ok'         => true,
    'duplicate'  => $duplicate,
], JSON_UNESCAPED_UNICODE);