<?php
/**
 * POST /api/ai_variator.php
 * Адаптирует блюдо с полной структурой рецепта
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in = json_input();
$dish = trim((string)($in['dish'] ?? ''));
$mode = trim((string)($in['mode'] ?? ''));

if (empty($dish)) {
    json_response(['error' => 'Введите название блюда'], 400);
}
if (empty($mode)) {
    json_response(['error' => 'Выберите стиль'], 400);
}

$schema = 'Структура JSON (ВСЕ поля на русском языке): {
  "title": "название адаптированного блюда",
  "description": "описание изменений",
  "changes": ["список", "ключевых", "изменений"],
  "cuisine": "кухня",
  "difficulty": "Легко|Средне|Сложно",
  "prep_time": число,
  "cook_time": число,
  "servings": число,
  "calories": число,
  "proteins": число,
  "fats": число,
  "carbs": число,
  "category_slug": "slug категории из списка",
  "tag_slugs": ["slug", "slug", "slug"],
  "ingredients": [
    {"name": "название на русском", "amount": "количество", "unit": "г/мл/шт/ст.л/ч.л"}
  ],
  "steps": [
    {"order": 1, "instruction": "описание шага на русском", "tip": "совет"}
  ]
}';

$prompt = "Ты — профессиональный шеф-повар. Адаптируй блюдо «{$dish}» под стиль: «{$mode}». "
        . "Опиши что изменилось в рецепте, какие ингредиенты заменены, "
        . "перечисли ключевые изменения списком. "
        . "Предоставь полный список ингредиентов с граммовками, "
        . "пошаговые инструкции и пищевую ценность. "
        . "Всё на русском языке."
        . AI_TAXONOMY_APPENDIX;

try {
    $recipe = yandex_gpt_json($prompt, $schema);
    
    if (empty($recipe['title'])) {
        throw new RuntimeException('AI не вернул название блюда');
    }
    
    json_response([
        'ok' => true,
        'recipe' => $recipe
    ]);
} catch (Throwable $ex) {
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'Не удалось адаптировать рецепт'
    ], 500);
}