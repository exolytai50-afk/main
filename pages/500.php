<?php
/**
 * GigaChefAI — страница внутренней ошибки сервера.
 * Автономна: не зависит от БД, сессий и шаблонов (может отображаться при сбое конфига).
 */
if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Ошибка сервера · GigaChefAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Nunito', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-violet-50 flex items-center justify-center p-4">
    <div class="text-center max-w-md">
        <div class="text-8xl mb-4 select-none">🥘</div>
        <div class="text-7xl font-extrabold bg-gradient-to-r from-orange-400 to-rose-400 bg-clip-text text-transparent">500</div>
        <h1 class="text-2xl font-extrabold text-gray-800 mt-4">Что-то пригорело на кухне</h1>
        <p class="text-gray-500 mt-2">
            На сервере произошла ошибка. Мы уже разбираемся. Попробуйте обновить страницу через минуту.
        </p>

        <?php if (defined('APP_DEBUG') && APP_DEBUG && !empty($GLOBALS['__error_message'])): ?>
            <pre class="text-left text-xs bg-white/70 border border-rose-200 rounded-xl p-4 mt-6 overflow-auto text-rose-600"><?php
                echo htmlspecialchars((string)$GLOBALS['__error_message'], ENT_QUOTES, 'UTF-8');
            ?></pre>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            <a href="javascript:location.reload()" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                🔄 Обновить
            </a>
            <a href="/" class="px-6 py-3 rounded-xl bg-white border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition">
                🏠 На главную
            </a>
        </div>
    </div>
</body>
</html>