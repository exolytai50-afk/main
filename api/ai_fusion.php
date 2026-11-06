<?php
/**
 * POST /api/ai_fusion.php
 * Генерирует fusion-рецепт с полной структурой
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in = json_input();
$cuisineA = trim((string)($in['cuisine_a'] ?? ''));
$cuisineB = trim((string)($in['cuisine_b'] ?? ''));

if (empty($cuisineA) || empty($cuisineB)) {
    json_response(['error' => 'Выберите обе кухни'], 400);
}

if ($cuisineA === $cuisineB) {
    json_response(['error' => 'Выберите разные кухни'], 400);
}

$schema = 'Структура JSON (ВСЕ поля на русском языке): {
  "title": "название блюда",
  "description": "краткое описание",
  "concept": "концепция fusion (как объединены кухни)",
  "cuisine": "название кухни",
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
    {"name": "название ингредиента на русском", "amount": "количество", "unit": "г/мл/шт/ст.л/ч.л"}
  ],
  "steps": [
    {"order": 1, "instruction": "описание шага на русском", "tip": "совет"}
  ]
}';

$prompt = "Ты — шеф-повар, специализирующийся на fusion-кухне. "
        . "Придумай уникальное блюдо, объединяющее {$cuisineA} и {$cuisineB} кухни. "
        . "Опиши концепцию блюда, полный список ингредиентов с граммовками, "
        . "пошаговые инструкции приготовления, и рассчитай пищевую ценность. "
        . "Всё на русском языке."
        . AI_TAXONOMY_APPENDIX;

try {
    $recipe = yandex_gpt_json($prompt, $schema);
    
    // Валидация
    if (empty($recipe['title'])) {
        throw new RuntimeException('AI не вернул название блюда');
    }
    
    json_response([
        'ok' => true,
        'recipe' => $recipe
    ]);
} catch (Throwable $ex) {
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'Не удалось сгенерировать рецепт'
    ], 500);
}