<?php
/**
 * GigaChef — Оценка фьюжн-рецепта
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}

$user = current_user();

if (!$user || empty($user['id'])) {
    send_json(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}

$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'Метод не поддерживается'], 400);
}

$input    = json_decode(file_get_contents('php://input'), true);
$recipeId = (int)($input['recipe_id'] ?? $input['id'] ?? 0);
$rating   = (int)($input['rating'] ?? 0);

if (!$recipeId || $rating < 1 || $rating > 5) {
    send_json(['ok' => false, 'error' => 'Неверные параметры'], 400);
}

try {
    $stmt = db()->prepare("
        UPDATE fusion_recipes 
        SET rating = :rating, rated_at = NOW() 
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute([
        ':rating' => $rating,
        ':id'     => $recipeId,
        ':uid'    => $userId
    ]);
    
    if ($stmt->rowCount() === 0) {
        send_json(['ok' => false, 'error' => 'Рецепт не найден'], 404);
    }
    
    // Проверяем достижения
    if (function_exists('check_achievements') && !empty($user['email'])) {
        try {
            check_achievements($user['email']);
        } catch (Throwable $e) {}
    }
    
    send_json(['ok' => true, 'rating' => $rating]);
    
} catch (Throwable $e) {
    error_log('Fusion rate error: ' . $e->getMessage());
    send_json(['ok' => false, 'error' => 'Ошибка сохранения оценки'], 500);
}