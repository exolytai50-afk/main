<?php
/**
 * POST /api/save_cookbook.php
 *
 * Сохраняет НОВУЮ кулинарную книгу.
 * Для добавления рецептов в существующую — cookbook_add_recipes.php.
 *
 * JSON:
 * {
 *   "title": "Название",
 *   "description": "Описание",
 *   "recipes_json": [...]
 * }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/cookbook_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Только POST'], 405);
}

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Требуется вход'], 401);
}

$user = current_user();
if (!is_array($user) || empty($user['email']) || empty($user['id'])) {
    json_response(['ok' => false, 'error' => 'Не удалось определить пользователя'], 401);
}

$userId    = (int)$user['id'];
$userEmail = (string)$user['email'];

$in = json_input();
if (!is_array($in)) {
    json_response(['ok' => false, 'error' => 'Некорректные данные запроса'], 400);
}

$title       = trim((string)($in['title'] ?? ''));
$description = trim((string)($in['description'] ?? ''));
$recipes     = $in['recipes_json'] ?? $in['recipes'] ?? [];

if ($title === '') {
    json_response(['ok' => false, 'error' => 'Укажите название книги'], 400);
}

if (mb_strlen($title) > 200) {
    json_response(['ok' => false, 'error' => 'Название книги слишком длинное'], 400);
}

// Нормализация recipes — поддерживаем массив и JSON-строку
if (is_string($recipes)) {
    try {
        $recipes = json_decode($recipes, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        json_response(['ok' => false, 'error' => 'Некорректный JSON рецептов'], 400);
    }
}

if (!is_array($recipes) || empty($recipes)) {
    json_response(['ok' => false, 'error' => 'Книга должна содержать хотя бы один рецепт'], 400);
}

if (count($recipes) > 500) {
    json_response(['ok' => false, 'error' => 'Слишком много рецептов'], 400);
}

// Нормализуем каждый рецепт
$normalizedRecipes = [];
foreach ($recipes as $r) {
    if (is_array($r)) {
        $normalizedRecipes[] = normalize_recipe($r);
    }
}

if (empty($normalizedRecipes)) {
    json_response(['ok' => false, 'error' => 'Не удалось обработать рецепты'], 400);
}

try {
    $recipesJson = json_encode(
        $normalizedRecipes,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (JsonException $e) {
    json_response(['ok' => false, 'error' => 'Ошибка сериализации рецептов'], 400);
}

if (strlen($recipesJson) > 5 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'Размер книги слишком большой'], 400);
}

// Создаём книгу через хелпер
$bookId = (int)($in['book_id'] ?? 0);

try {
    if ($bookId > 0) {
        // Обновляем существующую книгу
        $existing = db_one('SELECT id FROM cookbooks WHERE id = ? AND author_email = ?', [$bookId, $userEmail]);
        if (!$existing) {
            json_response(['ok' => false, 'error' => 'Книга не найдена или нет доступа'], 404);
        }
        
        db_exec(
            'UPDATE cookbooks SET title = ?, description = ?, recipes_json = ?, updated_at = NOW() WHERE id = ?',
            [$title, $description, $recipesJson, $bookId]
        );
        
        $saved = db_one('SELECT slug FROM cookbooks WHERE id = ?', [$bookId]);
        $slug = $saved['slug'] ?? '';
    } else {
        // Создаём новую книгу
        $bookId = create_cookbook($userId, $userEmail, $title, $description, $normalizedRecipes);
        if ($bookId <= 0) {
            throw new RuntimeException('Не удалось получить ID книги');
        }
        $saved = db_one('SELECT slug FROM cookbooks WHERE id = ?', [$bookId]);
        $slug = $saved['slug'] ?? '';
    }
} catch (Throwable $e) {
    error_log('save_cookbook DB error: ' . $e->getMessage());
    json_response([
        'ok'    => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Ошибка при сохранении книги',
    ], 500);
}

// Слаг из БД
$saved = db_one('SELECT slug FROM cookbooks WHERE id = ?', [$bookId]);
$slug  = $saved['slug'] ?? '';

// Начисления — не критично
try {
    add_user_points($userId, 15, 'cookbook_created', 'Создал кулинарную книгу: ' . $title);
} catch (Throwable $e) {
    error_log('add_user_points (cookbook): ' . $e->getMessage());
}

try {
    if (function_exists('check_achievements')) {
        check_achievements($userEmail);
    }
} catch (Throwable $e) {
    error_log('check_achievements (cookbook): ' . $e->getMessage());
}

try {
    if (function_exists('add_activity')) {
        add_activity($userEmail, 'cookbook_created', $bookId, 'cookbook', 'Создал книгу: ' . $title);
    }
} catch (Throwable $e) {
    error_log('add_activity (cookbook): ' . $e->getMessage());
}

json_response([
    'ok'      => true,
    'id'      => $bookId,
    'slug'    => $slug,
    'book'    => [
        'id'          => $bookId,
        'slug'        => $slug,
        'title'       => $title,
        'description' => $description,
        'recipes'     => $normalizedRecipes,
    ],
    'message' => 'Книга сохранена',
]);