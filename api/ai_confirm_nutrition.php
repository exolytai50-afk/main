<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/ai_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
ai_guard('calorie');

$in = json_input();
$title    = trim((string)($in['title'] ?? ''));
$ings     = normalize_recipe_ingredients((array)($in['ingredients'] ?? []));
$servings = max(1, (int)($in['servings'] ?? 1));
$weight   = max(1, (int)($in['total_weight_g'] ?? 0));

if ($title === '' || empty($ings) || $weight <= 0) {
    json_response(['error' => 'Нужны title, ingredients и total_weight_g'], 400);
}

$ingsText = implode("\n", array_map(function ($i) {
    $name = trim((string)($i['name'] ?? ''));
    $amount = trim((string)($i['amount'] ?? ''));
    $unit   = trim((string)($i['unit'] ?? ''));
    if ($unit === 'по вкусу') return "- {$name}: по вкусу";
    return "- {$name}: {$amount} {$unit}";
}, $ings));

$schema = '{"calories":int,"proteins":int,"fats":int,"carbs":int,"note":string}';

$prompt = "Рассчитай КБЖУ на ВСЮ порцию по ингредиентам и весу.\n"
        . "Блюдо: {$title}\n"
        . "Ингредиенты:\n{$ingsText}\n"
        . "Общий вес блюда: {$weight} г, порций: {$servings}.\n\n"
        . "ПРАВИЛА:\n"
        . "1. Если у ингредиента unit = 'по вкусу' — примени стандартную порцию (соль/перец/специи = 0 г, зелень = 5 г, масло = 10 г, соус = 15 г).\n"
        . "2. Всё остальное считай строго по указанному количеству и единице.\n"
        . "3. Верни КБЖУ на ОДНУ порцию (раздели итог на количество порций).\n"
        . "Ответ — только JSON по схеме. Обязательно заполни все поля числами.";

try {
    $folderId = defined('YANDEX_FOLDER_ID') ? YANDEX_FOLDER_ID : getenv('YANDEX_FOLDER_ID');

    // ВАЖНО: используем ключ 'content' вместо 'text' для DeepSeek
    $yandexMessages = [
        ['role' => 'system', 'text' => 'Ты — API, который возвращает ТОЛЬКО валидный JSON. Не пиши никаких слов.'],
        ['role' => 'user',   'text' => $prompt . "\n\nОТВЕТЬ СТРОГО В ТАКОМ ФОРМАТЕ:\n{\"calories\": 450, \"proteins\": 25, \"fats\": 15, \"carbs\": 40, \"note\": \"Сбалансированное блюдо\"}"],
    ];

    $body = [
    'modelUri' => 'gpt://' . $folderId . '/yandexgpt-4-lite/latest',
    'completionOptions' => [
        'stream'      => false,
        'temperature' => 0.1,
        'maxTokens'   => '2000',
    ],
    'messages' => $yandexMessages, // с ключом 'text', как было изначально
];

    // 1. Отправляем запрос
    $text = yandex_gpt_request($body);
    
    if (trim($text) === '') {
        throw new RuntimeException('DeepSeek вернул пустую строку.');
    }
    
    // 2. Вырезаем JSON
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    
    if ($start === false || $end === false) {
        throw new RuntimeException('AI не вернул JSON. Сырой ответ: ' . mb_substr($text, 0, 500));
    }
    
    $jsonString = substr($text, $start, $end - $start + 1);
    $data = json_decode($jsonString, true);
    
    if (!is_array($data)) {
        $jsonString = preg_replace('/,\s*([}\]])/', '$1', $jsonString);
        $data = json_decode($jsonString, true);
    }

    if (!$data || !isset($data['calories'])) {
        throw new RuntimeException('AI вернул некорректный JSON: ' . mb_substr($jsonString, 0, 500));
    }

    // 3. Формируем массив $nutrition (его не хватало!)
    $serv = max(1, $servings);
    $nutrition = [
        'calories' => max(0, (int)round(($data['calories'] ?? 0) / $serv)),
        'proteins' => max(0, (int)round(($data['proteins'] ?? 0) / $serv)),
        'fats'     => max(0, (int)round(($data['fats']     ?? 0) / $serv)),
        'carbs'    => max(0, (int)round(($data['carbs']    ?? 0) / $serv)),
        'note'     => trim((string)($data['note'] ?? '')),
        'locked'   => true,
    ];

    // 4. Обновляем скан
    $scanId = (int)($in['scan_id'] ?? 0);
    if ($scanId > 0 && is_logged_in()) {
        try {
            db_exec(
                'UPDATE calorie_scans
                 SET dish=?, portion=?, calories=?, proteins=?, fats=?, carbs=?
                 WHERE id=? AND user_email=?',
                [
                    $title, '1 порция ' . $weight . ' г',
                    $nutrition['calories'], $nutrition['proteins'],
                    $nutrition['fats'],     $nutrition['carbs'],
                    $scanId, current_user()['email'],
                ]
            );
        } catch (Throwable $e) {}
    }
    
    // 5. Автодобавление в дневник диетолога
    $diaryEntryId = null;
    if (is_logged_in()) {
        try {
            $hasQuestionnaire = db_one(
                'SELECT id FROM diet_questionnaires WHERE user_email = ? LIMIT 1',
                [current_user()['email']]
            );

            if ($hasQuestionnaire) {
                $hour = (int)date('H');
                if ($hour < 11)      $mealType = 'breakfast';
                elseif ($hour < 16)  $mealType = 'lunch';
                elseif ($hour < 21)  $mealType = 'dinner';
                else                 $mealType = 'snack';

                $imageUrl = null;
                if ($scanId > 0) {
                    $scanRow = db_one('SELECT image_url FROM calorie_scans WHERE id = ? AND user_email = ?', 
                        [$scanId, current_user()['email']]
                    );
                    if ($scanRow) {
                        $imageUrl = $scanRow['image_url'];
                    }
                }

                $diaryEntryId = db_insert(
                    'INSERT INTO diet_diary
                     (user_email, entry_date, meal_type, food_name, portion,
                      calories, proteins, fats, carbs, photo_url, created_at)
                     VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        current_user()['email'],
                        $mealType,
                        $title,
                        '1 порция ' . $weight . ' г',
                        (int)$nutrition['calories'],
                        (float)$nutrition['proteins'],
                        (float)$nutrition['fats'],
                        (float)$nutrition['carbs'],
                        $imageUrl,
                    ]
                );
            }
        } catch (Throwable $e) {}
    }

    // 6. Отдаём результат
    json_response([
        'ok' => true, 
        'nutrition' => $nutrition,
        'diary_entry_id' => $diaryEntryId 
    ]);

} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}