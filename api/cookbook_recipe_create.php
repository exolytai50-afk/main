<?php
/**
 * GigaChef — Создание полноценного рецепта из книги.
 *
 * Проверки (как раньше):
 *   1. рецепт уже создан (created_recipe_id) и реально есть в recipes;
 *   2. найден существующий / похожий рецепт (без force_create — не создаём).
 * Само создание — единый публикатор includes/recipe_publisher.php:
 * вставка в существующие колонки, КАТЕГОРИЯ и теги с проверкой записи, баллы, активность.
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/cookbook_helpers.php';
require_once dirname(__DIR__) . '/includes/recipe_guard.php';
require_once dirname(__DIR__) . '/includes/recipe_publisher.php';

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- авторизация и метод ----------
if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}
$user = current_user();
if (!$user || empty($user['id'])) {
    send_json(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'Только POST'], 405);
}

$userId    = (int)$user['id'];
$userEmail = (string)($user['email'] ?? '');

// ---------- входные данные ----------
$input         = json_decode(file_get_contents('php://input'), true) ?: [];
$bookId        = (int)($input['book_id'] ?? 0);
$index         = (int)($input['index'] ?? -1);
$publish       = !empty($input['publish']);
$overrideTitle = trim((string)($input['override_title'] ?? ''));
$forceCreate   = !empty($input['force_create']);

if (!$bookId || $index < 0) {
    send_json(['ok' => false, 'error' => 'Неверные параметры'], 400);
}

// ---------- книга и рецепт ----------
$book = find_user_cookbook($userId, $bookId);
if (!$book) {
    send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
}

$recipes = json_field($book['recipes_json']);
if (!isset($recipes[$index])) {
    send_json(['ok' => false, 'error' => 'Рецепт не найден в книге'], 404);
}

$rawRecipe = $recipes[$index];
if ($overrideTitle !== '') {
    $rawRecipe['title'] = $overrideTitle;
}

// ---------- проверка 1: уже создан ----------
if (!empty($rawRecipe['created_recipe_id'])) {
    $existing = db_one('SELECT id, title, slug FROM recipes WHERE id = ?', [(int)$rawRecipe['created_recipe_id']]);
    if ($existing) {
        send_json([
            'ok'             => true,
            'already_exists' => true,
            'recipe_id'      => (int)$existing['id'],
            'slug'           => $existing['slug'],
            'url'            => url('recipe/' . ($existing['slug'] ?: $existing['id'])),
            'message'        => 'Рецепт уже существует в приложении',
        ]);
    }
    // рецепт был удалён — создаём заново
}

// ---------- проверка 2: существующий или похожий ----------
$status = check_cookbook_recipe_status($rawRecipe, $userId);

if ($status['exists']) {
    send_json([
        'ok'             => true,
        'already_exists' => true,
        'recipe_id'      => (int)$status['recipe']['id'],
        'slug'           => $status['recipe']['slug'],
        'url'            => url('recipe/' . ($status['recipe']['slug'] ?: $status['recipe']['id'])),
        'message'        => 'Найден существующий рецепт с таким названием',
    ]);
}

if ($status['duplicate'] && !$forceCreate) {
    log_recipe_duplicate_attempt('cookbook_recipe_create', $userId, (string)($rawRecipe['title'] ?? ''), $status['duplicate']);
    send_json([
        'ok'         => true,
        'duplicate'  => true,
        'recipe_id'  => (int)$status['duplicate']['id'],
        'slug'       => $status['duplicate']['slug'],
        'url'        => url('recipe/' . ($status['duplicate']['slug'] ?: $status['duplicate']['id'])),
        'similarity' => $status['duplicate']['similarity'],
        'reason'     => $status['duplicate']['reason'],
        'message'    => 'Найден похожий рецепт: «' . $status['duplicate']['title']
                      . '» (совпадение ' . $status['duplicate']['similarity'] . '%)',
    ]);
}

// ---------- создание ----------
try {
    $res = rp_create_recipe($rawRecipe, $userId, $userEmail, [
        'publish' => $publish,
        'force'   => true,                    // дубликаты уже проверены выше
        'points'  => 20,
        'label'   => 'Создание рецепта из книги',
    ]);

    $newRecipeId = (int)$res['recipe_id'];
    $slug        = (string)$res['slug'];

    // запоминаем связь в книге
    $recipes[$index] = array_merge($recipes[$index], [
        'created_recipe_id' => $newRecipeId,
        'created_slug'      => $slug,
        'created_at'        => date('Y-m-d H:i:s'),
    ]);
    update_cookbook_recipes($bookId, $recipes);

    if ($status['duplicate'] && $forceCreate) {
        log_recipe_duplicate_attempt('cookbook_recipe_create_forced', $userId, (string)$rawRecipe['title'], $status['duplicate']);
    }

    $n = normalize_recipe($rawRecipe);
    send_json([
        'ok'             => true,
        'recipe_id'      => $newRecipeId,
        'slug'           => $slug,
        'url'            => $res['url'],
        'title'          => $n['title'],
        'category_saved' => $res['category_saved'],
        'nutrition'      => [
            'calories'       => $n['calories'],
            'proteins'       => $n['proteins'],
            'fats'           => $n['fats'],
            'carbs'          => $n['carbs'],
            'bread_units'    => $n['bread_units'],
            'glycemic_index' => $n['glycemic_index'],
            'glycemic_load'  => $n['glycemic_load'],
        ],
        'message'        => 'Рецепт создан',
    ]);

} catch (RuntimeException $e) {
    // валидационная ошибка, не 500
    send_json(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('cookbook_recipe_create error: ' . $e->getMessage());
    send_json(['ok' => false, 'error' => 'Ошибка создания рецепта'], 500);
}
