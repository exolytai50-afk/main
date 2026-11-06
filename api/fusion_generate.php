<?php
/**
 * GigaChef — API генерации фьюжн-рецептов через AI
 * 
 * ВАЖНО: Используются функции GigaChefAI/GigaChef:
 *   - is_logged_in()
 *   - current_user()  ← НЕ get_current_user()
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
//  АВТОРИЗАЦИЯ (правильные функции проекта)
// ============================================================
if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}

// Используем current_user(), а не get_current_user()
if (!function_exists('current_user')) {
    send_json(['ok' => false, 'error' => 'Системная ошибка: current_user недоступна'], 500);
}

$user = current_user();

if (!$user || empty($user['id'])) {
    send_json(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}

$userId    = (int)$user['id'];
$userEmail = $user['email'] ?? '';

// ============================================================
//  ПРОВЕРКА МЕТОДА
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'Метод не поддерживается'], 400);
}

// ============================================================
//  ВХОДНЫЕ ДАННЫЕ
// ============================================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_json(['ok' => false, 'error' => 'Невалидный JSON'], 400);
}

$cuisineA     = trim($input['cuisine_a'] ?? '');
$cuisineB     = trim($input['cuisine_b'] ?? '');
$cuisineAName = trim($input['cuisine_a_name'] ?? '');
$cuisineBName = trim($input['cuisine_b_name'] ?? '');
$dishType     = trim($input['dish_type'] ?? 'any');
$difficultyIn = trim($input['difficulty'] ?? 'any');   // slug из recipe_tags
$cookTimeIn   = trim($input['cook_time'] ?? 'any');    // slug из recipe_tags
$prompt       = trim($input['prompt'] ?? '');

// ============================================================
//  МАППИНГ SLUG → КЛАССИЧЕСКОЕ ЗНАЧЕНИЕ
//  Фронт присылает slug из recipe_tags ('dlya-novichkov'),
//  а промпт AI ожидает 'easy'|'medium'|'hard' и число минут.
// ============================================================

// Сложность: slug → easy/medium/hard
$difficultyMap = [
    'dlya-novichkov' => 'easy',
    'srednij-uroven' => 'medium',
    'dlya-profi'     => 'hard',
    'easy'           => 'easy',
    'medium'         => 'medium',
    'hard'           => 'hard',
    'any'            => 'any',
];
$difficulty = $difficultyMap[$difficultyIn] ?? 'any';

// Время: slug → число минут (или null для 'any')
$timeMap = [
    'do-15-minut'  => 15,
    'do-30-minut'  => 30,
    'do-60-minut'  => 60,
    'bolee-chasa'  => 120,
    '15'           => 15,
    '30'           => 30,
    '60'           => 60,
    '120'          => 120,
    'any'          => 'any',
];
$cookTime = $timeMap[$cookTimeIn] ?? 'any';

// Валидация
if (empty($cuisineA) || empty($cuisineB)) {
    send_json(['ok' => false, 'error' => 'Выберите две кухни'], 400);
}

if ($cuisineA === $cuisineB) {
    send_json(['ok' => false, 'error' => 'Кухни должны различаться'], 400);
}

if (mb_strlen($prompt) > 500) {
    $prompt = mb_substr($prompt, 0, 500);
}

// ============================================================
//  ДНЕВНОЙ ЛИМИТ
// ============================================================
$dailyLimit = 20;
try {
    $stmt = db()->prepare("
        SELECT COUNT(*) as cnt 
        FROM fusion_recipes 
        WHERE user_id = :uid 
          AND created_at >= CURDATE()
    ");
    $stmt->execute([':uid' => $userId]);
    $todayCount = (int)$stmt->fetchColumn();
    
    if ($todayCount >= $dailyLimit) {
        send_json([
            'ok' => false,
            'error' => "Достигнут дневной лимит ({$dailyLimit} рецептов). Попробуйте завтра."
        ], 429);
    }
} catch (Throwable $e) {
    error_log('Fusion limit check error: ' . $e->getMessage());
    // Не блокируем при ошибке БД
}

// ============================================================
//  ПРОВЕРКА AI
// ============================================================
if (empty(YANDEX_API_KEY) || empty(YANDEX_FOLDER_ID)) {
    send_json(['ok' => false, 'error' => 'AI не настроен. Проверьте YANDEX_API_KEY и YANDEX_FOLDER_ID'], 500);
}

// ============================================================
//  ФОРМИРОВАНИЕ ПРОМПТА
// ============================================================
$dishTypeMap = [
    'appetizer' => 'закуска',
    'soup'      => 'суп',
    'main'      => 'основное блюдо',
    'dessert'   => 'десерт',
    'drink'     => 'напиток',
    'snack'     => 'снек',
    'any'       => 'блюдо'
];
$dishTypeRu = $dishTypeMap[$dishType] ?? 'блюдо';

$difficultyMap = [
    'easy'   => 'простой (до 30 минут, минимум ингредиентов)',
    'medium' => 'средней сложности',
    'hard'   => 'сложный (ресторанный уровень)',
    'any'    => 'любой сложности'
];
$difficultyRu = $difficultyMap[$difficulty] ?? 'любой сложности';

$cookTimeRu = $cookTime !== 'any' ? "Время приготовления: до {$cookTime} минут." : '';

$userPrompt = "Создай уникальный фьюжн-рецепт, объединяющий {$cuisineAName} и {$cuisineBName} кухни.

Параметры:
- Тип блюда: {$dishTypeRu}
- Сложность: {$difficultyRu}
- {$cookTimeRu}
" . ($prompt ? "- Пожелания: {$prompt}" : "");

$systemPrompt = <<<EOT
Ты — креативный шеф-повар GigaChef, специализирующийся на фьюжн-кухне. Твоя задача — создавать УНИКАЛЬНЫЕ рецепты, объединяющие кулинарные традиции разных стран.

ПРАВИЛА:
1. Рецепт должен быть реалистичным и готовым к воплощению.
2. Ингредиенты должны быть доступны в обычных магазинах.
3. Сочетание кухонь должно быть органичным, а не абсурдным.
4. Описывай техники приготовления чётко и понятно.
5. Добавляй профессиональные советы.

ФОРМАТ ОТВЕТА — ТОЛЬКО валидный JSON:
{
  "title": "Название блюда (креативное, но понятное)",
  "description": "Краткое описание (2-3 предложения)",
  "dish_type": "appetizer|soup|main|dessert|drink|snack",
  "difficulty": "easy|medium|hard",
  "cook_time": число_минут,
  "servings": число_порций,
  "calories": число_калорий_на_порцию,
  "ingredients": [
    {"name": "Название ингредиента", "amount": "количество"}
  ],
  "steps": [
    "Шаг 1 - подробное описание",
    "Шаг 2 - подробное описание"
  ],
  "tips": [
    "Совет 1",
    "Совет 2"
  ],
  "serving_suggestion": "Как подавать блюдо"
}

Никакого текста вне JSON. Только JSON.
EOT;

// ============================================================
//  ЗАПРОС К YANDEX AI
// ============================================================
$aiPayload = [
    'modelUri' => 'gpt://' . YANDEX_FOLDER_ID . '/' . YANDEX_GPT_4_LITE_MODEL,
    'completionOptions' => [
        'stream'      => false,
        'temperature' => 0.8,
        'maxTokens'   => 2000
    ],
    'messages' => [
        ['role' => 'system', 'text' => $systemPrompt],
        ['role' => 'user',   'text' => $userPrompt]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => YANDEX_COMPLETION_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Api-Key ' . YANDEX_API_KEY
    ],
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => json_encode($aiPayload, JSON_UNESCAPED_UNICODE)
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    send_json(['ok' => false, 'error' => 'AI недоступен: ' . $curlError], 502);
}

if ($httpCode === 429) {
    send_json(['ok' => false, 'error' => 'Превышен лимит AI. Попробуйте позже.'], 429);
}

if ($httpCode !== 200) {
    error_log('Yandex AI response: ' . substr($response, 0, 500));
    send_json(['ok' => false, 'error' => 'AI вернул ошибку: ' . $httpCode], 502);
}

$aiResponse = json_decode($response, true);
$aiText = $aiResponse['result']['alternatives'][0]['message']['text'] ?? '';

if (empty($aiText)) {
    send_json(['ok' => false, 'error' => 'Пустой ответ от AI'], 502);
}

// ============================================================
//  ПАРСИНГ ОТВЕТА AI (улучшенный, многоуровневый)
// ============================================================
$aiText = trim($aiText);

// Уровень 1: Убираем markdown-обёртку
$aiText = preg_replace('/^```(?:json|javascript|js)?\s*\n?/i', '', $aiText);
$aiText = preg_replace('/\n?```\s*$/i', '', $aiText);
$aiText = str_replace('```', '', $aiText);
$aiText = trim($aiText);

// Уровень 2: Пытаемся распарсить весь текст
$recipe = json_decode($aiText, true);

// Уровень 3: Ищем JSON-объект в тексте
if (!$recipe) {
    $start = strpos($aiText, '{');
    $end = strrpos($aiText, '}');
    
    if ($start !== false && $end !== false && $end > $start) {
        $jsonPart = substr($aiText, $start, $end - $start + 1);
        $recipe = json_decode($jsonPart, true);
    }
}

// Уровень 4: Исправляем типичные ошибки AI
if (!$recipe) {
    $fixed = $aiText;
    
    // Trailing commas
    $fixed = preg_replace('/,\s*([}\]])/s', '$1', $fixed);
    
    // Однострочные комментарии
    $fixed = preg_replace('/\/\/[^\n]*/s', '', $fixed);
    
    // Многострочные комментарии
    $fixed = preg_replace('/\/\*[\s\S]*?\*\//s', '', $fixed);
    
    $start = strpos($fixed, '{');
    $end = strrpos($fixed, '}');
    
    if ($start !== false && $end !== false && $end > $start) {
        $recipe = json_decode(substr($fixed, $start, $end - $start + 1), true);
    }
}

// Уровень 5: Если совсем не выходит — логируем и отдаём ошибку
if (!$recipe || empty($recipe['title'])) {
    $jsonError = json_last_error_msg();
    error_log(sprintf(
        'AI response unparsable. Error: %s. Length: %d. First 2000 chars: %s',
        $jsonError,
        strlen($aiText),
        substr($aiText, 0, 2000)
    ));
    
    send_json([
        'ok' => false,
        'error' => 'AI вернул некорректный рецепт (json: ' . $jsonError . ')',
        'debug' => APP_DEBUG ? substr($aiText, 0, 500) : null
    ], 502);
}

// ============================================================
//  НОРМАЛИЗАЦИЯ
// ============================================================
$recipe['title']       = mb_substr((string)$recipe['title'], 0, 200);
$recipe['description'] = mb_substr((string)($recipe['description'] ?? ''), 0, 500);
$recipe['dish_type']   = in_array($recipe['dish_type'] ?? '', ['appetizer', 'soup', 'main', 'dessert', 'drink', 'snack']) 
    ? $recipe['dish_type'] : 'main';
$recipe['difficulty']  = in_array($recipe['difficulty'] ?? '', ['easy', 'medium', 'hard']) 
    ? $recipe['difficulty'] : 'medium';
$recipe['cook_time']   = max(5, min(600, (int)($recipe['cook_time'] ?? 30)));
$recipe['servings']    = max(1, min(20, (int)($recipe['servings'] ?? 2)));
$recipe['calories']    = max(0, min(5000, (int)($recipe['calories'] ?? 0)));

$recipe['ingredients'] = array_slice(array_map(function($ing) {
    if (is_string($ing)) {
        return ['name' => mb_substr($ing, 0, 100), 'amount' => ''];
    }
    return [
        'name'   => mb_substr((string)($ing['name'] ?? ''), 0, 100),
        'amount' => mb_substr((string)($ing['amount'] ?? ''), 0, 50)
    ];
}, (array)($recipe['ingredients'] ?? [])), 0, 30);

$recipe['steps'] = array_slice(array_map(function($s) {
    return mb_substr((string)$s, 0, 800);
}, (array)($recipe['steps'] ?? [])), 0, 20);

$recipe['tips'] = array_slice(array_map(function($t) {
    return mb_substr((string)$t, 0, 300);
}, (array)($recipe['tips'] ?? [])), 0, 5);

$recipe['serving_suggestion'] = mb_substr((string)($recipe['serving_suggestion'] ?? ''), 0, 300);

// Эмодзи кухонь
$recipe['cuisine_a']      = $cuisineA;
$recipe['cuisine_b']      = $cuisineB;
$recipe['cuisine_a_name'] = $cuisineAName;
$recipe['cuisine_b_name'] = $cuisineBName;

// ============================================================
//  СОХРАНЕНИЕ В БД
// ============================================================
$recipeId = 0;
try {
    $stmt = db()->prepare("
        INSERT INTO fusion_recipes 
        (user_id, title, description, cuisine_a, cuisine_b, cuisine_a_name, cuisine_b_name,
         dish_type, difficulty, cook_time, servings, calories, ingredients, steps, tips,
         serving_suggestion, recipe_json, created_at)
        VALUES 
        (:user_id, :title, :description, :cuisine_a, :cuisine_b, :cuisine_a_name, :cuisine_b_name,
         :dish_type, :difficulty, :cook_time, :servings, :calories, :ingredients, :steps, :tips,
         :serving_suggestion, :recipe_json, NOW())
    ");
    
    $stmt->execute([
        ':user_id'            => $userId,
        ':title'              => $recipe['title'],
        ':description'        => $recipe['description'],
        ':cuisine_a'          => $cuisineA,
        ':cuisine_b'          => $cuisineB,
        ':cuisine_a_name'     => $cuisineAName,
        ':cuisine_b_name'     => $cuisineBName,
        ':dish_type'          => $recipe['dish_type'],
        ':difficulty'         => $recipe['difficulty'],
        ':cook_time'          => $recipe['cook_time'],
        ':servings'           => $recipe['servings'],
        ':calories'           => $recipe['calories'],
        ':ingredients'        => json_encode($recipe['ingredients'], JSON_UNESCAPED_UNICODE),
        ':steps'              => json_encode($recipe['steps'], JSON_UNESCAPED_UNICODE),
        ':tips'               => json_encode($recipe['tips'], JSON_UNESCAPED_UNICODE),
        ':serving_suggestion' => $recipe['serving_suggestion'],
        ':recipe_json'        => json_encode($recipe, JSON_UNESCAPED_UNICODE)
    ]);
    
    $recipeId = (int)db()->lastInsertId();
    $recipe['id'] = $recipeId;
    
    // Начисляем баллы через существующую функцию
    add_user_points($userId, 5, 'fusion_generate', 'Создание фьюжн-рецепта: ' . $recipe['title']);
    
    // Регистрируем активность
    if (function_exists('add_activity') && $userEmail) {
        add_activity(
            $userEmail,
            'fusion_generate',
            $recipeId,
            'fusion',
            'Создал фьюжн-рецепт: ' . $recipe['title']
        );
    }
    
    // Проверяем достижения
    if (function_exists('check_achievements') && $userEmail) {
        try {
            check_achievements($userEmail);
        } catch (Throwable $e) {
            error_log('check_achievements error: ' . $e->getMessage());
        }
    }
    
} catch (Throwable $e) {
    error_log('Fusion save error: ' . $e->getMessage());
    // Отдаём рецепт даже если не сохранился
    $recipe['id'] = 0;
}

// ============================================================
//  ОТВЕТ
// ============================================================
send_json([
    'ok'     => true,
    'recipe' => $recipe,
    'saved'  => $recipeId > 0
]);