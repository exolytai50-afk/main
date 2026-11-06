<?php
/**
 * GigaChefAI — превышение лимита AI-запросов (429). Автономна.
 */
if (!headers_sent()) {
    http_response_code(429);
    header('Content-Type: text/html; charset=utf-8');
    if (!empty($GLOBALS['__retry_after'])) {
        header('Retry-After: ' . (int) $GLOBALS['__retry_after']);
    }
}
$reset = $GLOBALS['__limit_reset'] ?? 'завтра';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лимит запросов · GigaChefAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Nunito', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-violet-50 flex items-center justify-center p-4">
    <div class="text-center max-w-md">
        <div class="text-8xl mb-4 select-none">⏳</div>
        <div class="text-6xl font-extrabold bg-gradient-to-r from-violet-400 to-purple-400 bg-clip-text text-transparent">429</div>
        <h1 class="text-2xl font-extrabold text-gray-800 mt-4">Лимит AI-запросов исчерпан</h1>
        <p class="text-gray-500 mt-2">
            Вы использовали все бесплатные AI-запросы на сегодня.
            Лимит обновится <span class="font-semibold text-gray-700"><?= htmlspecialchars((string)$reset, ENT_QUOTES, 'UTF-8') ?></span>.
        </p>
        <div class="bg-white rounded-3xl shadow-md p-6 mt-8 text-left">
            <div class="font-extrabold text-violet-700 flex items-center gap-2">✨ GigaChefAI Pro</div>
            <p class="text-sm text-gray-500 mt-1">Оформите подписку и получите:</p>
            <ul class="text-sm text-gray-600 space-y-1 mt-3">
                <li>✓ Безлимитная генерация рецептов</li>
                <li>✓ AI-фото блюд</li>
                <li>✓ Расширенные лимиты сканера калорий</li>
            </ul>
            <a href="/payment" class="block text-center mt-4 py-3 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold hover:shadow-md transition">Разблокировать безлимит</a>
        </div>
        <a href="/" class="inline-block mt-6 text-gray-500 font-semibold hover:text-gray-700">← На главную</a>
    </div>
</body>
</html>