<?php
/**
 * POST /api/ai_chat.php
 * body: { message: string, context?: string, history?: [{role,text}] }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in      = json_input();
$message = trim((string)($in['message'] ?? ''));
$context = trim((string)($in['context'] ?? ''));
$history = is_array($in['history'] ?? null) ? $in['history'] : [];

if ($message === '') {
    json_response(['error' => 'Пустое сообщение'], 400);
}

$system = 'Ты — дружелюбный кулинарный AI-ассистент GigaChefAI. Помогай с рецептами, советами по '
        . 'готовке, заменой ингредиентов и планированием питания. Отвечай на русском языке, кратко '
        . 'и по существу, с эмодзи там, где уместно.';
if ($context !== '') {
    $system .= ' Контекст текущего рецепта: ' . mb_substr($context, 0, 1500);
}

// ✅ Правильный вызов функции
try {
    $reply = yandex_gpt_chat($system, $message, $history);
    
    if (empty($reply)) {
        $reply = 'Извините, не смог сформулировать ответ. Попробуйте переформулировать вопрос 🙂';
    }
    
    json_response(['ok' => true, 'reply' => $reply]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'AI временно недоступен'], 500);
}