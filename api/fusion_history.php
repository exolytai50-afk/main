<?php
/**
 * GigaChef — История фьюжн-рецептов пользователя
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

$limit     = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset    = max(0, (int)($_GET['offset'] ?? 0));
$savedOnly = !empty($_GET['saved']);

try {
    $where = "user_id = :uid";
    if ($savedOnly) {
        $where .= " AND is_saved = 1";
    }
    
    $stmt = db()->prepare("
        SELECT id, title, description, cuisine_a, cuisine_b, cuisine_a_name, cuisine_b_name,
               dish_type, difficulty, cook_time, calories, rating, is_saved, 
               recipe_json, created_at
        FROM fusion_recipes 
        WHERE {$where}
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recipes as &$r) {
        if (!empty($r['recipe_json'])) {
            $parsed = json_decode($r['recipe_json'], true);
            if ($parsed) {
                $r = array_merge($r, $parsed);
            }
        }
        unset($r['recipe_json']);
    }
    
    $stmt = db()->prepare("SELECT COUNT(*) FROM fusion_recipes WHERE {$where}");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();
    
    send_json([
        'ok'      => true,
        'recipes' => $recipes,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset
    ]);
    
} catch (Throwable $e) {
    error_log('Fusion history error: ' . $e->getMessage());
    send_json(['ok' => false, 'error' => 'Ошибка загрузки истории'], 500);
}