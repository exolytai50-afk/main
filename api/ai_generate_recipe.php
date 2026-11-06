<?php
/**
 * POST /api/ai_generate_recipe.php
 * Генерирует несколько рецептов с проверкой дубликатов
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in = json_input();
$dish = trim((string)($in['dish'] ?? ''));
$cuisine = trim((string)($in['cuisine'] ?? ''));
$diet = trim((string)($in['diet'] ?? ''));
$difficulty = trim((string)($in['difficulty'] ?? ''));
$servings = max(1, (int)($in['servings'] ?? 2));
$exclude = trim((string)($in['exclude'] ?? ''));
$count = min(4, max(1, (int)($in['count'] ?? 4)));

if (empty($dish) && empty($cuisine)) {
    json_response(['error' => 'Опишите блюдо или выберите кухню'], 400);
}

$schema = 'Структура JSON-массива из рецептов (ВСЕ поля на русском): [{
  "title": "название",
  "description": "описание",
  "cuisine": "кухня",
  "difficulty": "Легко|Средне|Сложно",
  "prep_time": число, "cook_time": число, "servings": число,
  "calories": число, "proteins": число, "fats": число, "carbs": число,
  "diet_type": ["тип"],
  "category_slug": "slug категории",
  "tag_slugs": ["slug", "slug", "slug"],
  "ingredients": [{"name":"","amount":"","unit":"","is_optional":false}],
  "steps": [{"order":1,"instruction":"","timer_minutes":0,"tip":""}]
}]';

$parts = [];
if ($dish) $parts[] = "Блюдо: {$dish}";
if ($cuisine) $parts[] = "Кухня: {$cuisine}";
if ($diet) $parts[] = "Тип питания: {$diet}";
if ($difficulty) $parts[] = "Сложность: {$difficulty}";
if ($servings > 1) $parts[] = "Порций: {$servings}";
if ($exclude) $parts[] = "Исключить: {$exclude}";

if ($count === 1) {
    $prompt = "Ты — профессиональный шеф-повар. Сгенерируй РОВНО ОДИН рецепт на русском языке.\n"
            . "Условия: " . implode('. ', $parts) . ".\n"
            . "Рассчитай БЖУ для него.\n"
            . "ОБЯЗАТЕЛЬНО верни ВСЕ поля: "
            . "title, description, cuisine, difficulty, prep_time, cook_time, servings, "
            . "calories, proteins, fats, carbs, diet_type, "
            . "category_slug, tag_slugs, ingredients, steps. "
            . "Поле category_slug — ОДНА строка. Поле tag_slugs — массив из 3-7 строк. "
            . "Ответь СТРОГО массивом JSON из ОДНОГО объекта (даже если рецепт один, он должен быть внутри массива [ ... ]).\n"
            . "НЕ добавляй никакого текста или markdown до или после JSON.";
} else {
    $prompt = "Ты — профессиональный шеф-повар. Сгенерируй РОВНО {$count} РАЗНЫХ рецепта на русском языке.\n"
            . "Условия: " . implode('. ', $parts) . ".\n"
            . "ВАЖНО: Все {$count} рецепта должны быть СУЩЕСТВЕННО разными — "
            . "разные названия, разные ингредиенты, разные способы приготовления. "
            . "Не создавай вариации одного и того же блюда.\n"
            . "Рассчитай БЖУ для каждого.\n"
            . "ОБЯЗАТЕЛЬНО верни для КАЖДОГО рецепта ВСЕ поля: "
            . "title, description, cuisine, difficulty, prep_time, cook_time, servings, "
            . "calories, proteins, fats, carbs, diet_type, "
            . "category_slug, tag_slugs, ingredients, steps. "
            . "Поле category_slug — ОДНА строка. Поле tag_slugs — массив из 3-7 строк. "
            . "Ответь СТРОГО массивом JSON из {$count} объектов.\n"
            . "НЕ добавляй никакого текста или markdown до или после JSON.";
}

try {
    // Добавляем справочник категорий и тегов из config.php
    if (defined('AI_TAXONOMY_APPENDIX')) {
        $prompt .= "\n\n" . AI_TAXONOMY_APPENDIX;
    }

    $data = yandex_gpt_json(
        $prompt,
        $schema,
        1,
        AI_RECIPE_GENERATE_MAX_TOKENS
    );
    
    // Извлекаем рецепты (может быть массив или объект с ключом recipes)
    $generatedRecipes = [];
    if (isset($data['recipes']) && is_array($data['recipes'])) {
        $generatedRecipes = $data['recipes'];
    } elseif (is_array($data) && isset($data[0])) {
        $generatedRecipes = $data;
    } else {
        $generatedRecipes = [$data];
    }
    
    // Получаем существующие рецепты для проверки дубликатов
    $existingTitles = db_all('SELECT LOWER(title) as title FROM recipes');
    $existingTitles = array_map(fn($r) => mb_strtolower(trim($r['title'])), $existingTitles);
    
    $seenTitles = [];
    $finalRecipes = [];
    
    foreach ($generatedRecipes as $recipe) {
        if (empty($recipe['title'])) continue;
        
        $title = trim((string)$recipe['title']);
        $titleLower = mb_strtolower($title);
        
        // Проверяем на дубликат среди существующих
        $isDuplicate = in_array($titleLower, $existingTitles);
        
        // Проверяем на дубликат среди сгенерированных
        if (!$isDuplicate && in_array($titleLower, $seenTitles)) {
            $isDuplicate = true;
        }
        
        // Если дубликат — добавляем уточнение к названию
        if ($isDuplicate) {
            $ingredients = array_slice((array)($recipe['ingredients'] ?? []), 0, 2);
            $keyIngs = implode(' и ', array_map(fn($i) => $i['name'] ?? '', $ingredients));
            if ($keyIngs) {
                $title = $title . ' (' . $keyIngs . ')';
            } else {
                $title = $title . ' (вариант ' . (count($finalRecipes) + 1) . ')';
            }
        }
        
        $seenTitles[] = $titleLower;
        
        $tagSlugs = $recipe['tag_slugs'] ?? [];
        
        if (!is_array($tagSlugs)) {
            $tagSlugs = [];
        }
        
        $tagSlugs = array_values(array_unique(array_filter(
            array_map(
                static fn($tag) => is_string($tag) ? trim($tag) : '',
                $tagSlugs
            ),
            static fn($tag) => $tag !== ''
        )));
        
        $tags = $recipe['tags'] ?? $tagSlugs;
        
        if (!is_array($tags)) {
            $tags = $tagSlugs;
        }
        
        $tags = array_values(array_filter(
            array_map(
                static fn($tag) => is_string($tag) ? trim($tag) : '',
                $tags
            ),
            static fn($tag) => $tag !== ''
        ));
        
        $cleanRecipe = [
            'title'       => mb_substr($title, 0, 200),
            'description' => trim((string)($recipe['description'] ?? '')),
            'cuisine'     => trim((string)($recipe['cuisine'] ?? $cuisine)),
            'difficulty' =>  difficulty_normalize($recipe['difficulty'] ?? 'medium'),
            'prep_time'   => max(0, (int)($recipe['prep_time'] ?? 10)),
            'cook_time'   => max(0, (int)($recipe['cook_time'] ?? 20)),
            'servings'    => max(1, (int)($recipe['servings'] ?? $servings)),
            'calories'    => max(0, (int)($recipe['calories'] ?? 0)),
            'proteins'    => max(0, (int)($recipe['proteins'] ?? 0)),
            'fats'        => max(0, (int)($recipe['fats'] ?? 0)),
            'carbs'       => max(0, (int)($recipe['carbs'] ?? 0)),
            'diet_type'   => array_values(array_filter((array)($recipe['diet_type'] ?? []))),
            'tags' => $tags,
            'tag_slugs' => $tagSlugs,
            'category_slug' => trim((string)($recipe['category_slug'] ?? '')),
            'ingredients' => array_values((array)($recipe['ingredients'] ?? [])),
            'steps'       => array_values((array)($recipe['steps'] ?? [])),
            'is_duplicate'=> $isDuplicate,
            'image_url'   => '/uploads/static/static.jpg',
            'is_ai_generated' => true,
        ];
        
        $finalRecipes[] = $cleanRecipe;
        
        if (count($finalRecipes) >= $count) break;
    }
    
    json_response([
        'ok' => true,
        'recipes' => $finalRecipes
    ]);
    
} catch (Throwable $ex) {
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'AI временно недоступен'
    ], 500);
}