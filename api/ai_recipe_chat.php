<?php
/**
 * POST /api/ai_recipe_chat.php
 * body: { recipe_id, message, history?:[{role,text}] }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in      = json_input();
$rid     = (int)($in['recipe_id'] ?? 0);
$message = trim((string)($in['message'] ?? ''));
$history = is_array($in['history'] ?? null) ? $in['history'] : [];

if ($message === '' || $rid <= 0) {
    json_response(['error' => 'Некорректный запрос'], 400);
}

// Получаем рецепт
$recipe = db_one('SELECT title, description, ingredients, steps FROM recipes WHERE id = ?', [$rid]);
if (!$recipe) {
    json_response(['error' => 'Рецепт не найден'], 404);
}

$ings  = json_field($recipe['ingredients']);
$steps = json_field($recipe['steps']);

// Формируем контекст рецепта
$ctx  = 'Рецепт: ' . $recipe['title'] . '. ' . $recipe['description'] . "\n";
$ctx .= 'Ингредиенты: ';
$ctx .= implode(', ', array_map(function($i) {
    return ($i['name'] ?? '') . ' ' . ($i['amount'] ?? '') . ' ' . ($i['unit'] ?? '');
}, $ings));
$ctx .= "\nШаги: " . implode(' | ', array_map(function($s) {
    return $s['instruction'] ?? '';
}, $steps));

// System prompt с контекстом рецепта
$system = 'Ты — AI-помощник по конкретному рецепту в GigaChefAI. '
        . 'Твоя задача — помогать пользователю с вопросами об этом рецепте. '
        . 'Ты можешь советовать по технике приготовления, замене ингредиентов, '
        . 'времени готовки, размеру порций, сервировке и подаче. '
        . 'Отвечай кратко, на русском языке, с эмодзи где уместно. '
        . 'НЕ придумывай информацию, которой нет в рецепте. '
        . 'Если вопрос не по теме рецепта — вежливо напомни, что ты помощник по этому конкретному рецепту.'
        . "\n\nКонтекст рецепта:\n" . mb_substr($ctx, 0, 2500);

try {
    // ✅ Используем новую функцию для чата
    $reply = yandex_gpt_chat($system, $message, $history, 0.6);
    
    if (empty($reply)) {
        $reply = 'Извините, не смог сформулировать ответ. Попробуйте переформулировать вопрос 🙂';
    }
    
    json_response(['ok' => true, 'reply' => $reply]);
    
} catch (Throwable $ex) {
    error_log('Recipe chat error: ' . $ex->getMessage());
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'AI временно недоступен. Попробуйте позже.'
    ], 500);
}