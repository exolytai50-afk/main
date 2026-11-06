<?php
/**
 * GigaChef — AI-поиск рецептов в базе (умный, без SQL-ограничений)
 */
error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/cookbook_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('is_logged_in') || !is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$bookId = (int)($_GET['book_id'] ?? 0);
$theme = trim((string)($_GET['theme'] ?? '')); // Название книги
$cuisine = trim((string)($_GET['cuisine'] ?? ''));
$diet = trim((string)($_GET['diet'] ?? ''));
$limit = (int)($_GET['limit'] ?? 5);

if (!$bookId) {
    echo json_encode(['ok' => false, 'error' => 'Не указана книга']);
    exit;
}

$book = find_user_cookbook($userId, $bookId);
if (!$book) {
    echo json_encode(['ok' => false, 'error' => 'Книга не найдена']);
    exit;
}

// 1. Берем 400 последних рецептов (ID и Название), чтобы не перегружать токены
$sql = "SELECT id, title, description, cuisine, calories, difficulty, prep_time, cook_time, servings, ingredients, steps, slug, image_url 
        FROM recipes 
        ORDER BY id DESC LIMIT 400";
$recipes = db_all($sql);

if (empty($recipes)) {
    echo json_encode(['ok' => true, 'recipes' => []]);
    exit;
}

// Формируем список для AI
$listForAi = "";
foreach ($recipes as $r) {
    $listForAi .= "ID: {$r['id']} | Название: {$r['title']}\n";
}

// 2. Спрашиваем AI
$prompt = "Ты — умный кулинарный эксперт. Пользователь собирает книгу на тему: «{$theme}».\n";
if ($cuisine) $prompt .= "Предпочтение по кухне: {$cuisine}.\n";
if ($diet) $prompt .= "Тип питания: {$diet}.\n";
$prompt .= "\nВот список ВСЕХ доступных рецептов в базе (ID и Название):\n{$listForAi}\n";
$prompt .= "\nЗАДАЧА: Выбери до {$limit} рецептов (по ID), которые ИДЕАЛЬНО подходят под тему «{$theme}».\n";
$prompt .= "ПРАВИЛА:\n";
$prompt .= "1. Оценивай СМЫСЛ названия. Если тема «Фруктовые салаты», то «Салат из яблок и груш» подходит, а «Салат с курицей» или «Паста» — НЕТ.\n";
$prompt .= "2. Если подходящих рецептов нет — верни пустой массив: [].\n";
$prompt .= "3. Верни ТОЛЬКО валидный JSON-массив с ID, например: [12, 45, 78].";

try {
    // Вызываем AI
    require_once ROOT_DIR . '/includes/yandex.php';
    $text = yandex_gpt_text($prompt, 500);
    
    // Извлекаем JSON-массив из ответа
    preg_match('/\[[\d,\s]+\]/', $text, $matches);
    $selectedIds = [];
    if (!empty($matches[0])) {
        $selectedIds = json_decode($matches[0], true);
    }

    if (empty($selectedIds)) {
        echo json_encode(['ok' => true, 'recipes' => []]);
        exit;
    }

    // 3. Достаем полные данные выбранных рецептов
    $ph = implode(',', array_fill(0, count($selectedIds), '?'));
    $foundRecipes = db_all("SELECT * FROM recipes WHERE id IN ({$ph})", $selectedIds);

    // 4. Добавляем в книгу
    $bookRecipes = json_field($book['recipes_json']);
    $existingIds = array_column($bookRecipes, 'created_recipe_id');
    $added = [];

    foreach ($foundRecipes as $r) {
        if (in_array((int)$r['id'], $existingIds)) continue; // Пропускаем дубликаты

        // Нормализация сложности
        $diff = mb_strtolower(trim((string)($r['difficulty'] ?? 'medium')));
        if (in_array($diff, ['easy', 'легко'])) $diffRu = 'Легко';
        elseif (in_array($diff, ['hard', 'сложно', 'сложный'])) $diffRu = 'Сложно';
        else $diffRu = 'Средне';

        $bookRecipes[] = [
            'title' => $r['title'],
            'description' => $r['description'],
            'cuisine' => $r['cuisine'],
            'difficulty' => $diffRu,
            'prep_time' => $r['prep_time'],
            'cook_time' => $r['cook_time'],
            'servings' => $r['servings'],
            'calories' => $r['calories'],
            'ingredients' => json_decode((string)$r['ingredients'], true),
            'steps' => json_decode((string)$r['steps'], true),
            'image_url' => $r['image_url'],
            'created_recipe_id' => (int)$r['id'],
            'created_slug' => $r['slug'],
        ];
        $added[] = $r['title'];
    }

    // Сохраняем книгу
    db_exec("UPDATE cookbooks SET recipes_json = ?, updated_at = NOW() WHERE id = ?", [
        json_encode($bookRecipes, JSON_UNESCAPED_UNICODE), 
        $bookId
    ]);

    echo json_encode(['ok' => true, 'recipes' => $added]);

} catch (Throwable $e) {
    error_log('search_recipes_ai error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Ошибка AI поиска: ' . $e->getMessage()]);
}