<?php
/**
 * GET /api/check_saved.php?recipe_id=123
 * Проверяет, сохранён ли рецепт у текущего пользователя
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    json_response(['saved' => false]);
}

$rid = (int)($_GET['recipe_id'] ?? 0);
$userEmail = current_user()['email'];

$exists = db_one(
    'SELECT id FROM favorites WHERE user_email = ? AND recipe_id = ?',
    [$userEmail, $rid]
);

json_response(['saved' => $exists !== null]);