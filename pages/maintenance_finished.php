<?php
/**
 * Страница «Мы вернулись».
 * Показывается после окончания техработ.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$settings = maintenance_get_settings();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мы вернулись! — GigaChef</title>
    <link rel="icon" type="image/png" href="/assets/icon/chef_32.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .animate-bounce-in { animation: bounceIn 0.8s ease-out; }
        @keyframes bounceIn {
            0%   { transform: scale(0.5); opacity: 0; }
            60%  { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 50%, #fef3c7 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 sm:p-12 max-w-xl w-full text-center border border-white/60">
        <div class="w-24 h-24 mx-auto mb-6 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center animate-bounce-in"><svg class="w-14 h-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1 class="text-3xl sm:text-4xl font-black text-gray-800 mb-3">Мы вернулись!</h1>
        <p class="text-gray-600 text-base sm:text-lg mb-8">
            Технические работы завершены. Все сервисы снова работают.
            Спасибо, что были с нами!
        </p>
        <a href="<?= url('') ?>"
           class="inline-block px-8 py-3.5 rounded-2xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-black text-lg hover:shadow-lg transition">
            <span class="inline-flex items-center gap-2">На главную <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
    </div>
</body>
</html>