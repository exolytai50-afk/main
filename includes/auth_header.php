<?php
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="ru" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-path" content="<?= e(BASE_PATH) ?>">
    
    <!-- Базовые мета-теги -->
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'GigaChef - умная кулинарная социальная сеть с AI-генератором рецептов') ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="<?= e($pageUrl ?? url('')) ?>">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#f59e0b">
    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($pageTitle ?? APP_NAME) ?>">
    <meta property="og:description" content="<?= e($pageDescription ?? 'GigaChef - умная кулинарная социальная сеть') ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="GigaChef">
    
    <!-- Фавиконки -->
    <link rel="icon" type="image/x-icon" href="<?= url('assets/icon/chef_16.ico') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/icon/chef_16.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/icon/chef_32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= url('assets/icon/chef_64.png') ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/icon/chef_64.png') ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Nunito','system-ui','sans-serif'] } } }
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/themes.css') ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .auth-gradient {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 30%, #fde68a 60%, #fbbf24 100%);
        }
        /* Поле пароля с кнопкой-глазом */
.password-field {
    position: relative;
}
.password-field input {
    padding-right: 2.75rem; /* место под кнопку */
}
.password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #9ca3af; /* gray-400 */
    cursor: pointer;
    border-radius: 0.5rem;
    transition: color 0.15s ease, background-color 0.15s ease;
}
.password-toggle:hover {
    color: #6b7280; /* gray-500 */
    background: rgba(0, 0, 0, 0.04);
}
.password-toggle:focus-visible {
    outline: 2px solid #fcd34d; /* amber-300 */
    outline-offset: 2px;
}
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased min-h-screen flex flex-col">

<!-- Мини-навбар -->
<header class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="<?= url('') ?>" class="flex items-center gap-2 font-extrabold text-amber-700">
            <img src="<?= url('assets/icon/chef_32.png') ?>" alt="GigaChef" class="w-7 h-7 rounded-lg object-cover">
            <span>GigaChef</span>
        </a>
        <div class="flex items-center gap-2">
            <?php if (!is_logged_in()): ?>
                <a href="<?= url('login') ?>" class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 text-sm font-bold hover:bg-amber-200 transition">Войти</a>
                <a href="<?= url('register') ?>" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 text-sm font-bold hover:shadow-md transition">Регистрация</a>
            <?php else: ?>
                <a href="<?= url('') ?>" class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 text-sm font-bold hover:bg-amber-200 transition">На главную</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Основной контент -->
<main class="flex-1">