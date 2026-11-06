<?php
require_once __DIR__ . '/../config/config.php';
http_response_code(404);
$pageTitle = '404 — Страница не найдена';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="min-h-[60vh] flex items-center justify-center px-4 py-16">
    <div class="text-center max-w-md" data-aos="fade-up">
        <div class="text-8xl mb-4 select-none">🍳</div>
        <div class="text-7xl font-extrabold bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent">404</div>
        <h1 class="text-2xl font-extrabold text-gray-800 mt-4">Рецепт не найден</h1>
        <p class="text-gray-500 mt-2">
            Похоже, эта страница подгорела или её никогда не было в меню.
            Проверьте адрес или вернитесь к готовке.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            <a href="<?= url('') ?>" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                🏠 На главную
            </a>
            <a href="<?= url('generate') ?>" class="px-6 py-3 rounded-xl bg-white border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition">
                ✨ Сгенерировать рецепт
            </a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>