<?php
/**
 * POST /api/ai_beer_pairing.php
 * Два режима: food-to-beer (пиво к блюду) и beer-to-food (блюда к пиву)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/yandex_vision.php';
require_once __DIR__ . '/../includes/ai_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

ai_guard('calorie');

$in = json_input();
$mode = trim((string)($in['mode'] ?? 'beer-to-food'));
$image = (string)($in['image'] ?? '');

// Схемы
$vision_schema = $mode === 'food-to-beer'
    ? '{"dish_name":"string","ingredients":["string"],"cuisine_type":"string","flavor_profile":"string"}'
    : '{"beer_name":"string","style":"string","abv":"string","brand":"string","notes":"string"}';

$result_schema = $mode === 'food-to-beer'
    ? '{"dish_name":"string","pairing_summary":"string","primary_beer":{"name":"string","style":"string","abv":"string","reason":"string","brands":["string"]},"alternatives":[{"name":"string","style":"string","abv":"string","reason":"string"}],"serving_tips":["string"],"note":"string"}'
    : '{"beer_info":{"name":"string","style":"string","abv":"string","ibu":"string","serving_temp":"string","flavor_notes":"string"},"pairing_summary":"string","primary_dish":{"name":"string","cuisine":"string","difficulty":"string","reason":"string"},"alternatives":[{"name":"string","cuisine":"string","prep_time":0,"cook_time":0,"reason":"string"}],"snacks":["string"],"sommelier_tips":["string"],"note":"string"}';

try {
    $visionData = null;
    
    // ЭТАП 1: Vision (если есть фото)
    if ($image !== '') {
        try {
            $base64 = normalize_image_data_url($image);
            $prompt = $mode === 'food-to-beer'
                ? 'Ты — эксперт по еде. Посмотри на фото блюда. Определи название, ингредиенты, кухню и профиль вкуса. Отвечай на русском языке строго JSON.'
                : 'Ты — эксперт по пиву. Посмотри на фото бутылки/банки. Определи название, стиль, бренд и крепость. Отвечай на русском языке строго JSON.';
            
            $visionData = yandex_vision_json($base64, $prompt, $vision_schema);
        } catch (Throwable $e) {
            error_log('Beer pairing vision error: ' . $e->getMessage());
            $inputText = $mode === 'food-to-beer' ? ($in['dish_name'] ?? '') : ($in['beer_name'] ?? '');
            if ($inputText === '') throw $e;
        }
    }
    
    // ЭТАП 2: Подбор
    if ($mode === 'food-to-beer') {
        $dishName = $visionData['dish_name'] ?? trim((string)($in['dish_name'] ?? ''));
        $ingredients = implode(', ', (array)($visionData['ingredients'] ?? []));
        $cuisine = $visionData['cuisine_type'] ?? trim((string)($in['cuisine'] ?? ''));
        $flavor = $visionData['flavor_profile'] ?? '';
        $preference = trim((string)($in['preference'] ?? ''));
        
        if ($dishName === '') json_response(['error' => 'Не удалось определить блюдо'], 400);
        
        $prompt = "Ты — пивной сомелье. Подбери идеальное пиво к блюду на русском языке.\n\n"
                . "Блюдо: {$dishName}\n"
                . ($ingredients ? "Ингредиенты: {$ingredients}\n" : '')
                . ($cuisine ? "Кухня: {$cuisine}\n" : '')
                . ($flavor ? "Профиль вкуса: {$flavor}\n" : '')
                . ($preference ? "Предпочтения: {$preference}\n" : '')
                . "\nДай ОСНОВНУЮ рекомендацию (1 стиль + 2-3 бренда), 2-3 АЛЬТЕРНАТИВЫ, советы по сервировке. Отвечай JSON.";
    } else {
        $beerName = $visionData['beer_name'] ?? trim((string)($in['beer_name'] ?? ''));
        $style = $visionData['style'] ?? '';
        $abv = $visionData['abv'] ?? '';
        $mealType = trim((string)($in['meal_type'] ?? ''));
        $servings = trim((string)($in['servings'] ?? '1-2'));
        
        if ($beerName === '') json_response(['error' => 'Не удалось определить пиво'], 400);
        
        $prompt = "Ты — пивной сомелье и шеф-повар. Подбери идеальные блюда к пиву на русском языке.\n\n"
                . "Пиво: {$beerName}\n"
                . ($style ? "Стиль: {$style}\n" : '')
                . ($abv ? "Крепость: {$abv}\n" : '')
                . ($mealType ? "Тип блюда: {$mealType}\n" : '')
                . "На {$servings} персон\n"
                . "\nДай характеристику пива, 1 идеальное блюдо, 3-4 альтернативы, 4-5 закусок, советы сомелье. Отвечай JSON.";
    }
    
    $result = yandex_gpt_json($prompt, $result_schema, 2);
    
    json_response(['ok' => true, 'result' => $result]);
    
} catch (Throwable $ex) {
    error_log('Beer pairing error: ' . $ex->getMessage());
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'Не удалось выполнить подбор. Попробуйте другое фото или описание.'
    ], 500);
}