<?php
/**
 * GigaChef — Сохранение фьюжн-рецепта в кулинарную книгу
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

// ============================================================
//  АВТОРИЗАЦИЯ
// ============================================================
if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}

$user = current_user();

if (!$user || empty($user['id'])) {
    send_json(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}

$userId    = (int)$user['id'];
$userEmail = $user['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'Метод не поддерживается'], 400);
}

$input    = json_decode(file_get_contents('php://input'), true);
$recipeId = (int)($input['recipe_id'] ?? $input['id'] ?? 0);

if (!$recipeId) {
    send_json(['ok' => false, 'error' => 'Не указан рецепт'], 400);
}

try {
    // Проверяем принадлежность
    $stmt = db()->prepare("
        SELECT id, is_saved, title, recipe_json 
        FROM fusion_recipes 
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute([':id' => $recipeId, ':uid' => $userId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipe) {
        send_json(['ok' => false, 'error' => 'Рецепт не найден'], 404);
    }
    
    if ($recipe['is_saved']) {
        send_json(['ok' => true, 'message' => 'Уже сохранён']);
    }
    
    // Обновляем
    $stmt = db()->prepare("
        UPDATE fusion_recipes 
        SET is_saved = 1, saved_at = NOW() 
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute([':id' => $recipeId, ':uid' => $userId]);
    
    // Добавляем в кулинарную книгу
    try {
        $difficulty = difficulty_normalize($_POST['difficulty'] ?? 'medium');
        db_insert(
            'INSERT INTO saved_recipes 
            (user_id, recipe_type, recipe_id, title, recipe_json, created_at)
            VALUES (?, "fusion", ?, ?, ?, NOW())',
            [$userId, $recipeId, $recipe['title'], $recipe['recipe_json']]
        );
    } catch (Throwable $e) {
        error_log('saved_recipes insert failed: ' . $e->getMessage());
    }
    
    // Начисляем баллы
    if (function_exists('add_user_points')) {
        add_user_points($userId, 10, 'fusion_save', 'Сохранение фьюжн-рецепта');
    }
    
    // Активность
    if (function_exists('add_activity') && $userEmail) {
        add_activity(
            $userEmail,
            'fusion_save',
            $recipeId,
            'fusion',
            'Сохранил фьюжн-рецепт: ' . $recipe['title']
        );
    }
    
    send_json(['ok' => true, 'message' => 'Рецепт сохранён', 'points' => 10]);
    
} catch (Throwable $e) {
    error_log('Fusion save error: ' . $e->getMessage());
    send_json(['ok' => false, 'error' => 'Ошибка сохранения'], 500);
}