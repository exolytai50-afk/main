<?php
/**
 * GigaChef — Проверка существования рецепта по ингредиентам
 * 
 * POST { ingredients: [...] }
 * → { ok: true, exists: bool, recipe: {...} | null }
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/cookbook_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}

$user   = current_user();
$userId = (int)($user['id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ingredients = $input['ingredients'] ?? [];

if (!is_array($ingredients) || empty($ingredients)) {
    send_json(['ok' => false, 'error' => 'Ингредиенты не указаны'], 400);
}

$threshold = (float)($input['threshold'] ?? 0.75);
$threshold = max(0.3, min(1.0, $threshold));

$found = find_similar_recipe($ingredients, $userId, $threshold);

send_json([
    'ok'     => true,
    'exists' => $found !== null,
    'recipe' => $found,
    'threshold' => $threshold
]);