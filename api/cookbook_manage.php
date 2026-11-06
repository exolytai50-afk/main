<?php
/**
 * GigaChef — Управление кулинарными книгами и рецептами внутри них
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

$user = current_user();
if (!$user || empty($user['id'])) {
    send_json(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}

$userId    = (int)$user['id'];
$userEmail = $user['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'Только POST'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = trim((string)($input['action'] ?? ''));

// ============================================================
//  CREATE
// ============================================================
if ($action === 'create') {
    $title       = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $theme       = trim((string)($input['theme'] ?? $title));
    $recipes     = $input['recipes'] ?? [];
    
    if ($title === '') {
        send_json(['ok' => false, 'error' => 'Укажите название книги'], 400);
    }
    
    if (!is_array($recipes)) $recipes = [];
    
    try {
        $bookId = create_cookbook($userId, $userEmail, $title, $description, $recipes);
        
        if (function_exists('add_activity')) {
            add_activity($userEmail, 'cookbook_created', $bookId, 'cookbook', 'Создал книгу: ' . $title);
        }
        
        if (function_exists('add_user_points')) {
            add_user_points($userId, 15, 'cookbook_create', 'Создание книги');
        }
        
        // Отдаём книгу
        $book = db_one('SELECT * FROM cookbooks WHERE id = ?', [$bookId]);
        if ($book) {
            $book['recipes'] = json_field($book['recipes_json']);
            unset($book['recipes_json']);
        }
        
        send_json([
            'ok' => true,
            'book_id' => $bookId,
            'book' => $book,
            'message' => 'Книга создана'
        ]);
        
    } catch (Throwable $e) {
        error_log('cookbook create: ' . $e->getMessage());
        send_json(['ok' => false, 'error' => 'Ошибка создания книги'], 500);
    }
}

// ============================================================
//  DELETE
// ============================================================
if ($action === 'delete') {
    $bookId = (int)($input['book_id'] ?? 0);
    
    if (!$bookId) {
        send_json(['ok' => false, 'error' => 'Не указана книга'], 400);
    }
    
    $book = find_user_cookbook($userId, $bookId);
    if (!$book) {
        send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
    }
    
    try {
        db_exec('DELETE FROM cookbooks WHERE id = ?', [$bookId]);
        send_json(['ok' => true, 'message' => 'Книга удалена']);
    } catch (Throwable $e) {
        send_json(['ok' => false, 'error' => 'Ошибка удаления'], 500);
    }
}

// ============================================================
//  UPDATE (title/description)
// ============================================================
if ($action === 'update') {
    $bookId      = (int)($input['book_id'] ?? 0);
    $title       = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $isPublic    = isset($input['is_public']) ? (int)(bool)$input['is_public'] : null;
    
    if (!$bookId) {
        send_json(['ok' => false, 'error' => 'Не указана книга'], 400);
    }
    
    $book = find_user_cookbook($userId, $bookId);
    if (!$book) {
        send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
    }
    
    try {
        $fields = [];
        $params = [];
        
        if ($title !== '') {
            $fields[] = 'title = ?';
            $params[] = $title;
        }
        if ($description !== '') {
            $fields[] = 'description = ?';
            $params[] = $description;
        }
        if (isset($input['theme']) && trim((string)$input['theme']) !== '') {
            $fields[] = 'theme = ?';
            $params[] = trim((string)$input['theme']);
        }
        if ($isPublic !== null) {
            $fields[] = 'is_public = ?';
            $params[] = $isPublic;
        }
        
        if (empty($fields)) {
            send_json(['ok' => false, 'error' => 'Нет данных для обновления'], 400);
        }
        
        $fields[] = 'updated_at = NOW()';
        $params[] = $bookId;
        
        db_exec('UPDATE cookbooks SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        
        send_json(['ok' => true, 'message' => 'Книга обновлена']);
        
    } catch (Throwable $e) {
        send_json(['ok' => false, 'error' => 'Ошибка обновления'], 500);
    }
}

// ============================================================
//  LINK_RECIPES — привязать найденные из БД к книге
// ============================================================
if ($action === 'link_recipes') {
    $bookId = (int)($input['book_id'] ?? 0);
    $indexes = array_map('intval', (array)($input['indexes'] ?? []));
    $recipeIds = array_map('intval', (array)($input['recipe_ids'] ?? []));

    if (!$bookId || !$indexes || count($indexes) !== count($recipeIds)) {
        send_json(['ok' => false, 'error' => 'Неверные параметры'], 400);
    }

    $book = find_user_cookbook($userId, $bookId);
    if (!$book) send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);

    $rows = [];
    if ($recipeIds) {
        $ph = implode(',', array_fill(0, count($recipeIds), '?'));
        $rows = db_all("SELECT id, slug, title FROM recipes WHERE id IN ($ph)", $recipeIds);
    }
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;

    $recipes = json_field($book['recipes_json']);
    $added = 0;
    foreach ($indexes as $i => $idx) {
        if (!isset($recipes[$idx])) continue;
        $rid = $recipeIds[$i] ?? 0;
        if (!$rid || !isset($byId[$rid])) continue;
        $recipes[$idx]['created_recipe_id'] = $rid;
        $recipes[$idx]['created_slug']      = (string)$byId[$rid]['slug'];
        $recipes[$idx]['created_at']        = date('Y-m-d H:i:s');
        $added++;
    }
    if ($added) update_cookbook_recipes($bookId, $recipes);
    send_json(['ok' => true, 'linked' => $added]);
}

// ============================================================
//  ADD_RECIPE
// ============================================================
if ($action === 'add_recipe') {
    $bookId = (int)($input['book_id'] ?? 0);
    $recipe = $input['recipe'] ?? null;
    
    if (!$bookId || !is_array($recipe) || empty($recipe['title'])) {
        send_json(['ok' => false, 'error' => 'Не указана книга или рецепт'], 400);
    }
    
    $book = find_user_cookbook($userId, $bookId);
    if (!$book) {
        send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
    }
    
    $recipe['ingredients'] = normalize_ingredients($recipe['ingredients'] ?? []);
    $recipe['steps']       = $recipe['steps'] ?? [];
    $recipe['added_at']    = date('Y-m-d H:i:s');
    
    $similar = find_similar_recipe($recipe['ingredients'], $userId, 0.85);
    if ($similar) {
        $recipe['existing_recipe'] = $similar;
    }
    
    $result = add_recipe_to_cookbook($bookId, $recipe);
    
    if (!$result['ok']) {
        send_json($result, 400);
    }
    
    send_json([
        'ok' => true,
        'message' => 'Рецепт добавлен в книгу',
        'count' => $result['count'],
        'existing_recipe' => $similar
    ]);
}

// ============================================================
//  REMOVE_RECIPE
// ============================================================
if ($action === 'remove_recipe') {
    $bookId = (int)($input['book_id'] ?? 0);
    $index  = (int)($input['index'] ?? -1);
    
    if (!$bookId || $index < 0) {
        send_json(['ok' => false, 'error' => 'Неверные параметры'], 400);
    }
    
    $book = find_user_cookbook($userId, $bookId);
    if (!$book) {
        send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
    }
    
    $result = remove_recipe_from_cookbook($bookId, $index);
    
    if (!$result['ok']) {
        send_json($result, 400);
    }
    
    send_json([
        'ok' => true,
        'message' => 'Рецепт удалён из книги',
        'count' => $result['count']
    ]);
}

// ============================================================
//  REORDER
// ============================================================
if ($action === 'reorder') {
    $bookId = (int)($input['book_id'] ?? 0);
    $order  = $input['order'] ?? [];
    
    if (!$bookId || !is_array($order)) {
        send_json(['ok' => false, 'error' => 'Неверные параметры'], 400);
    }
    
    $book = find_user_cookbook($userId, $bookId);
    if (!$book) {
        send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
    }
    
    $recipes = json_field($book['recipes_json']);
    $newRecipes = [];
    
    foreach ($order as $idx) {
        $idx = (int)$idx;
        if (isset($recipes[$idx])) {
            $newRecipes[] = $recipes[$idx];
        }
    }
    
    if (!update_cookbook_recipes($bookId, $newRecipes)) {
        send_json(['ok' => false, 'error' => 'Не удалось сохранить'], 500);
    }
    
    send_json(['ok' => true, 'message' => 'Порядок обновлён']);
}

send_json(['ok' => false, 'error' => 'Неизвестное действие'], 400);