<?php
/**
 * POST /api/ai_calorie_scan.php
 * Анализ фото еды через Yandex Vision (только черновик, без КБЖУ)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/yandex_vision.php';
require_once __DIR__ . '/../includes/ai_guard.php';
require_once __DIR__ . '/../includes/image_storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

ai_guard('calorie');

$in    = json_input();
$image = (string)($in['image'] ?? '');
$desc  = trim((string)($in['description'] ?? ''));

if ($image === '' && $desc === '') {
    json_response(['error' => 'Загрузите фото или опишите блюдо'], 400);
}

// ✅ Схема для VLM (Yandex Vision) — на русском
$vlm_schema = 'Структура JSON (ВСЕ поля на русском языке): {
  "dish_name": "название блюда на русском",
  "ingredients": [
        {"name":"название на русском","amount":"число или пустая строка","unit":"г|кг|мл|л|шт|ч.л.|ст.л.|стакан|щепотка|пучок|зубчик|по вкусу"}
    ],
  "estimated_weight_g": число (вес в граммах),
  "notes": "краткие примечания на русском"
}';

try {
    $source = 'text';
    $vlm_data = null;

    // ЭТАП 1: Анализ фото через Vision
    if ($image !== '') {
        try {
            $base64 = normalize_image_data_url($image);
            
            $vlm_prompt = 'Ты — профессиональный диетолог и эксперт по питанию. '
                        . 'Внимательно посмотри на фотографию еды. '
                        . 'Опиши блюдо НА РУССКОМ ЯЗЫКЕ. '
                        . 'Название блюда должно быть на русском (например: "Борщ со сметаной", "Куриная грудка с гречкой", "Салат Цезарь"). '
                        . 'Ингредиенты перечисли на русском языке. '
                        . 'Не придумывай ингредиенты, которых нет на фото. '
                        . 'Если не уверен в названии — выбери наиболее вероятный вариант на русском. '
                        . "ПРАВИЛА ПО ИНГРЕДИЕНТАМ: "
                        . "в поле 'amount' пиши ЧИСЛО (например '200', '0.5', '2') или пустую строку, если количество неизвестно. "
                        . "В поле 'unit' — ОДИН из: г, кг, мл, л, шт, ч.л., ст.л., стакан, щепотка, пучок, зубчик, по вкусу. "
                        . "Для соли, перца, специй, зелени, масла для жарки — amount='', unit='по вкусу'. "
                        . "НЕ придумывай граммы для того, что нельзя взвесить на фото. "
                        . 'Отвечай СТРОГО в формате JSON без markdown-разметки.'
                        . ($desc !== '' ? " Дополнительный контекст от пользователя: «{$desc}»." : '');

            $vlm_data = yandex_vision_json($base64, $vlm_prompt, $vlm_schema);
            $source = 'photo';
            
            error_log('Vision result: ' . json_encode($vlm_data, JSON_UNESCAPED_UNICODE));
            
        } catch (Throwable $e) {
            error_log('Vision API error: ' . $e->getMessage());
            
            if ($desc === '') {
                throw $e;
            }
            $source = 'text';
        }
    }

    // ── Сборка черновика (БЕЗ КБЖУ, БЕЗ ДНЕВНИКА) ──
    $result = [
        'dish'           => trim((string)($vlm_data['dish_name'] ?? $desc ?? 'Неизвестное блюдо')),
        'ingredients'    => normalize_recipe_ingredients((array)($vlm_data['ingredients'] ?? [])),
        'total_weight_g' => max(1, (int)($vlm_data['estimated_weight_g'] ?? 350)),
        'confidence'     => $source === 'text' ? 'низкая' : 'средняя',
        'note'           => '',
    ];

    // ============================================================
    //  Сохранение черновика в БД (с нулевыми КБЖУ)
    // ============================================================
    $scanId   = null;
    $imageUrl = null;

    // Сохраняем фото на диск (если оно есть)
    if ($image !== '') {
        $imageUrl = save_data_url_image($image, 'scan');
    }

    // Сохраняем скан в calorie_scans (КБЖУ = 0, обновятся после подтверждения)
    if (is_logged_in()) {
        $scanId = db_insert(
            'INSERT INTO calorie_scans
             (user_email, dish, portion, calories, proteins, fats, carbs, source, confidence, image_url, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?, NOW())',
            [
                current_user()['email'],
                $result['dish'],
                '1 порция ' . $result['total_weight_g'] . ' г',
                0, 0, 0, 0,
                $source,
                $result['confidence'],
                $imageUrl,
            ]
        );
    }

    // ============================================================
    //  Финальный ответ
    // ============================================================
    json_response([
        'ok'        => true,
        'source'    => $source,
        'result'    => $result,
        'saved'     => $scanId !== null,
        'scan_id'   => $scanId,
        'image_url' => $imageUrl,
        'ai'        => ai_usage_status('calorie'),
    ]);
    
} catch (Throwable $ex) {
    error_log('Calorie scan error: ' . $ex->getMessage());
    json_response([
        'error' => APP_DEBUG 
            ? $ex->getMessage() 
            : 'Не удалось проанализировать. Попробуйте другое фото или описание.'
    ], 500);
}