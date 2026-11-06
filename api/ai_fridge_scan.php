<?php
/**
 * POST /api/ai_fridge_scan.php
 * Распознаёт продукты на фото холодильника через Qwen Vision.
 * Использует ту же фичу ai_guard, что и calorie_scanner.
 *
 * body: { image: "data:image/jpeg;base64,..." }
 * resp: { ok: true, ingredients: ["яйца", "молоко", ...], ai: {...} }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/yandex_vision.php';
require_once __DIR__ . '/../includes/ai_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

// Используем ту же фичу 'calorie' — это фото-анализ, лимиты общие
ai_guard('calorie');

$in    = json_input();
$image = (string)($in['image'] ?? '');

if ($image === '') {
    json_response(['error' => 'Не передано изображение'], 400);
}

$schema = 'Структура JSON (ВСЕ поля на русском языке): {
  "ingredients": ["продукт 1 на русском", "продукт 2 на русском"],
  "notes": "краткое примечание на русском"
}';

$prompt = 'Ты — эксперт по продуктам питания. '
        . 'Внимательно посмотри на фотографию холодильника или продуктов. '
        . 'Перечисли ВСЕ различимые продукты (овощи, фрукты, мясо, рыба, молочное, соусы, крупы и т.д.) '
        . 'НА РУССКОМ ЯЗЫКЕ, в единственном числе, в нижнем регистре, без брендов и упаковок. '
        . 'Не придумывай того, чего не видно на фото. Если продукт неразборчив — пропусти его. '
        . 'Отвечай СТРОГО в формате JSON без markdown-разметки.';

try {
    $base64 = normalize_image_data_url($image);
    
    // Пытаемся получить ответ от Vision
    $vlm = yandex_vision_json($base64, $prompt, $schema);

    // Если Vision вернул null или пустоту — выбрасываем понятную ошибку
    if (!is_array($vlm) || empty($vlm)) {
        throw new RuntimeException('Vision вернул пустой ответ. Попробуйте другое фото.');
    }

    $ingredients = array_values(array_filter(array_map(
        fn($x) => mb_strtolower(trim((string)$x)),
        (array)($vlm['ingredients'] ?? [])
    )));

    if (empty($ingredients)) {
        // Если продуктов не найдено — возвращаем пустой массив (без ошибки 500)
        json_response([
            'ok' => true,
            'ingredients' => [],
            'notes' => 'На фото не удалось распознать продукты',
            'ai' => ai_usage_status('calorie'),
        ]);
    }

    json_response([
        'ok'          => true,
        'ingredients' => $ingredients,
        'notes'       => (string)($vlm['notes'] ?? ''),
        'ai'          => ai_usage_status('calorie'),
    ]);
} catch (Throwable $e) {
    error_log('Fridge scan error: ' . $e->getMessage());
    json_response([
        'error' => APP_DEBUG ? $e->getMessage() : 'Не удалось распознать продукты'
    ], 500);
}