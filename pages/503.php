<?php
/**
 * GigaChefAI — страница технического обслуживания (503). Автономна.
 */
if (!headers_sent()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: ' . (defined('MAINTENANCE_RETRY_AFTER') ? (int) MAINTENANCE_RETRY_AFTER : 3600));
}
$msg = defined('MAINTENANCE_MESSAGE') ? MAINTENANCE_MESSAGE : 'Мы скоро вернёмся!';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Технические работы · GigaChefAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .float { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-violet-50 flex items-center justify-center p-4">
    <div class="text-center max-w-md">
        <div class="text-8xl mb-4 select-none float">🧑‍🍳</div>
        <div class="text-6xl font-extrabold bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent">503</div>
        <h1 class="text-2xl font-extrabold text-gray-800 mt-4">Технические работы</h1>
        <p class="text-gray-500 mt-2"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="mt-8 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/70 text-amber-700 text-sm font-semibold">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Обновляем сервис…
        </div>
        <div class="mt-8">
            <a href="javascript:location.reload()" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition inline-block">🔄 Проверить снова</a>
        </div>
        <p class="text-xs text-gray-400 mt-6">По срочным вопросам: <a href="mailto:support@cookai.ru" class="underline">support@cookai.ru</a></p>
    </div>
    <script>setTimeout(() => location.reload(), 30000);</script>
</body>
</html>