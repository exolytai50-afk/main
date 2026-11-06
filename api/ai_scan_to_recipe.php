<?php
/**
 * POST /api/ai_scan_to_recipe.php
 * Из результата скана генерирует черновик рецепта (без сохранения).
 * Сохранение — через /api/save_recipe.php.
 *
 * Принимает:
 *   - dish          (string, обязательно)
 *   - portion       (string)
 *   - ingredients   (array)
 *   - calories      (int)
 *   - proteins      (int)
 *   - fats          (int)
 *   - carbs         (int)
 *   - image_url     (string, опционально) — URL фото сканируемого блюда
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/ai_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}
if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход'], 401);
}

ai_guard('recipe');

$in = json_input();

$dish        = trim((string)($in['dish'] ?? ''));
$portion     = trim((string)($in['portion'] ?? ''));
$ingredients = (array)($in['ingredients'] ?? []);
$calories    = (int)($in['calories'] ?? 0);
$proteins    = (int)($in['proteins'] ?? 0);
$fats        = (int)($in['fats'] ?? 0);
$carbs       = (int)($in['carbs'] ?? 0);

// Фото сканируемого блюда (если сканер его сохранил)
$image_url = trim((string)($in['image_url'] ?? ''));

// Валидируем URL: только внутренние /uploads/* или http(s)
if ($image_url !== '') {
    $is_local  = str_starts_with($image_url, '/uploads/');
    $is_remote = preg_match('#^https?://#i', $image_url) === 1;
    if (!$is_local && !$is_remote) {
        $image_url = '';
    }
}

if ($dish === '') {
    json_response(['error' => 'Не указано название блюда'], 400);
}

$ingsList = [];
foreach ($ingredients as $ing) {
    if (is_array($ing)) {
        $name = $ing['name'] ?? '';
        $amount = $ing['amount'] ?? '';
        $unit = $ing['unit'] ?? '';
        $ingsList[] = trim("{$name} {$amount} {$unit}");
    } else {
        $ingsList[] = (string)$ing;
    }
}
$ingredients_str = $ingsList ? implode(', ', $ingsList) : 'по вкусу';

$schema = 'Структура JSON (все поля на русском):
{
  "title": "название рецепта",
  "description": "краткое описание 1-2 предложения",
  "prep_time": число (минуты подготовки),
  "cook_time": число (минуты готовки),
  "servings": число (порций),
  "difficulty": "Легко|Средне|Сложно",
  "ingredients": [{"name":"...","amount":"число","unit":"г|мл|шт|ст.л.|ч.л."}],
  "steps": [{"instruction":"пошаговая инструкция","tip":"подсказка (опционально)"}],
  "tags": ["тег1","тег2"],
  "diet_type": ["вегетарианское"|"диетическое"|"низкокалорийное"],
  "tips": ["совет шефа 1","совет шефа 2"]
}';

$prompt = "Ты — профессиональный шеф-повар. Составь полноценный рецепт на русском языке.\n"
        . "Блюдо: {$dish}\n"
        . "Порция: {$portion}\n"
        . "Известные ингредиенты: {$ingredients_str}\n"
        . "Пищевая ценность на порцию: {$calories} ккал, Б {$proteins} г, Ж {$fats} г, У {$carbs} г\n\n"
        . "Составь пошаговый рецепт из 4-8 шагов.\n"
        . "Укажи время (prep_time — подготовка, cook_time — готовка).\n"
        . "Сложность: одна из 'Легко', 'Средне', 'Сложно'.\n"
        . "Добавь 2-3 совета шефа в поле tips и 3-5 тегов в поле tags.\n"
        . "Если известны не все ингредиенты — дополни типичными для этого блюда.\n"
        . "ВСЕ поля отвечай на русском. Отвечай СТРОГО JSON без markdown.";

try {
    $folderId = defined('YANDEX_FOLDER_ID') ? YANDEX_FOLDER_ID : getenv('YANDEX_FOLDER_ID');
$yandexMessages = [
    ['role' => 'system', 'text' => 'Ты — API, который возвращает ТОЛЬКО валидный JSON.'],
    ['role' => 'user',   'text' => "СТРУКТУРА ОТВЕТА:\n" . $schema . "\n\n" . $prompt],
];
$body = [
    'modelUri' => 'gpt://' . $folderId . '/yandexgpt-4-lite/latest',
    'completionOptions' => ['stream' => false, 'temperature' => 0.3, 'maxTokens' => '2000'],
    'messages' => $yandexMessages,
];
$text = yandex_gpt_request($body);

$start = strpos($text, '{');
$end = strrpos($text, '}');
if ($start === false || $end === false) throw new RuntimeException('AI не вернул JSON');
$jsonString = substr($text, $start, $end - $start + 1);
$recipe = json_decode($jsonString, true);

if (!is_array($recipe)) {
    $jsonString = preg_replace('/,\s*([}\]])/', '$1', $jsonString);
    $recipe = json_decode($jsonString, true);
}
if (!$recipe || !isset($recipe['title'])) throw new RuntimeException('AI вернул некорректный JSON');

    // Финальный image_url: фото скана, иначе заглушка
    $finalImageUrl = $image_url !== '' ? $image_url : '/uploads/static/static.jpg';

    // Нормализуем данные для save_recipe.php
    $normalized = [
        'title'           => (string)($recipe['title'] ?? $dish),
        'description'     => (string)($recipe['description'] ?? ''),
        'prep_time'       => (int)($recipe['prep_time'] ?? 0),
        'cook_time'       => (int)($recipe['cook_time'] ?? 0),
        'servings'        => max(1, (int)($recipe['servings'] ?? 1)),
        'difficulty'      => in_array($recipe['difficulty'] ?? '', ['Легко','Средне','Сложно'], true)
                                ? $recipe['difficulty']
                                : 'Легко',
        'cuisine'         => 'Другая',
        'calories'        => $calories,
        'proteins'        => $proteins,
        'fats'            => $fats,
        'carbs'           => $carbs,
        'ingredients'     => array_values((array)($recipe['ingredients'] ?? [])),
        'steps'           => array_values((array)($recipe['steps'] ?? [])),
        'tags'            => array_values((array)($recipe['tags'] ?? [])),
        'diet_type'       => array_values((array)($recipe['diet_type'] ?? [])),
        'tips'            => array_values((array)($recipe['tips'] ?? [])),
        'image_url'       => $finalImageUrl,
        'is_ai_generated' => 1,
    ];

    json_response([
        'ok'        => true,
        'recipe'    => $normalized,
        'image_url' => $finalImageUrl,
        'ai'        => ai_usage_status('recipe'),
    ]);
} catch (Throwable $e) {
    error_log('scan_to_recipe error: ' . $e->getMessage());
    json_response([
        'error' => APP_DEBUG ? $e->getMessage() : 'Не удалось создать рецепт'
    ], 500);
}