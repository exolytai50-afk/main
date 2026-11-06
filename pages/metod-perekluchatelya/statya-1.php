<?php // @page: Статья 1 — Почему сила воли — плохой инструмент
require_once __DIR__ . '/../../config/config.php';
require_login();

$pageTitle = 'Почему сила воли — плохой инструмент — Метод Переключателя';
$pageDescription = 'Эффект «белого медведя» и почему борьба с желанием усиливает желание.';
$pageH1 = 'Почему сила воли — плохой инструмент';
$GLOBALS['pageH1'] = $pageH1;

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <a href="<?= url('metod-perekluchatelya') ?>" class="text-sm text-violet-600 hover:text-violet-700 mb-6 inline-block">
        ← К методу Переключателя
    </a>

    <article class="prose prose-gray max-w-none">

        <h1 class="text-3xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <?= icon('bulb', 'gc-icon-md') ?> Вы не слабовольный. Вы просто используете не тот отдел мозга.
        </h1>

        <p class="text-gray-600 leading-relaxed mb-4">
            Мы привыкли думать, что отказ от вредной привычки — это борьба.
            Стиснуть зубы, терпеть, считать дни. Это стратегия «белого медведя»:
            чем больше вы стараетесь не думать о сигарете, тем чаще она всплывает в голове.
        </p>

        <blockquote class="border-l-4 border-violet-400 pl-4 my-6 italic text-gray-700">
            <p>«Попробуйте 5 минут не думать о белом медведе. Вы поймаете себя на том, что думаете именно о нём».</p>
            <footer class="text-xs text-gray-400 mt-1 not-italic">— Дэниел Вегнер, Harvard, 1987</footer>
        </blockquote>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('settings', 'gc-icon-sm') ?> Инженерный подход
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Ваш мозг — биологическая машина, запрограммированная на выживание.
            Никотин взламывает систему вознаграждения. Мозг говорит:
            «Это важно для выживания, запомни это».
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            Вы не слабы. Вы — заложник старого кода. И код можно обновить.
        </p>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('chart', 'gc-icon-sm') ?> Упражнение: карта зависимости
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Возьмите лист бумаги. Нарисуйте круг. Разделите на 3 сектора:
        </p>

        <ol class="list-decimal list-inside space-y-2 text-gray-600 mb-6">
            <li><b>Физиология</b> — что чувствует тело (сердцебиение, вкус, ритм).</li>
            <li><b>Ритуал</b> — что делают руки, рот, какие паузы вы делаете.</li>
            <li><b>Социальный контекст</b> — с кем и где вы курите.</li>
        </ol>

        <p class="text-gray-600 leading-relaxed mb-4">
            Это ваша карта зависимости. Мы будем ломать её по частям, а не целиком.
        </p>

        <div class="bg-violet-50 rounded-xl p-5 my-8">
            <p class="font-bold text-violet-800 mb-2 flex items-center gap-2">
                <?= icon('rocket', 'gc-icon-sm') ?> Что дальше
            </p>
            <p class="text-sm text-violet-700 mb-3">Разберитесь, что именно даёт вам сигарета. Спойлер: ничего. А вот что — узнаете в следующей статье.</p>
            <a href="<?= url('metod-perekluchatelya/statya-2') ?>" class="text-sm font-bold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
                Читать: Анатомия желания <?= icon('right', 'gc-icon-xs') ?>
            </a>
        </div>

        <!-- Библиография -->
        <div class="border-t border-gray-200 pt-6 mt-8">
            <p class="text-xs font-bold text-gray-500 mb-2">Источники:</p>
            <ul class="text-xs text-gray-400 space-y-1">
                <li>• Wegner D.M. (1987). Ironic processes of mental control. <i>Psychological Review</i>, 96(1), 34–52.</li>
                <li>• Baumeister R.F., Tierney J. (2011). <i>Willpower: Rediscovering the Greatest Human Strength</i>.</li>
            </ul>
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mt-6 text-xs text-amber-700 flex items-start gap-2">
            <span class="text-amber-600 shrink-0"><?= icon('warning', 'gc-icon-sm') ?></span>
            <span>Материал носит информационный характер и не является медицинской рекомендацией.</span>
        </div>

    </article>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>