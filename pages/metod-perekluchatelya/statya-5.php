<?php // @page: Статья 5 — Свобода вместо борьбы
require_once __DIR__ . '/../../config/config.php';
require_login();

$pageTitle = 'Свобода вместо борьбы — Метод Переключателя';
$pageH1 = ' Конец войны с самим собой';
$GLOBALS['pageH1'] = $pageH1;

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <a href="<?= url('metod-perekluchatelya') ?>" class="text-sm text-violet-600 hover:text-violet-700 mb-6 inline-block">
        ← К методу Переключателя
    </a>

    <article class="prose prose-gray max-w-none">

        <h1 class="text-3xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <?= icon('heart', 'gc-icon-md') ?> Конец войны с самим собой
        </h1>

        <p class="text-gray-600 leading-relaxed mb-4">
            Классические методы предлагают «бороться» или «просто не думать».
            Оба подхода требуют энергии и внимания.
            Мы предлагаем третий путь: <b>переключение</b>.
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            Вам не нужно ничего бросать. Вам нужно просто перестать
            нажимать на кнопку, которая запускает ненужную программу.
        </p>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('info', 'gc-icon-sm') ?> Зачем вам сигарета…
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            …если вы можете получить тот же эффект (перерыв, расслабление, дофамин) без яда?
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            …если вы можете быть раскованным трезвым?<br>
            …если вы можете удовлетворить голод вкусной и полезной едой?
        </p>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('refresh', 'gc-icon-sm') ?> Обновление прошивки
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Зависимость — это не дыра в душе, которую нужно заполнить.
            Это просто ошибка в программном обеспечении.
            Метод Переключателя — это обновление прошивки.
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            Вы не теряете «друга» (сигарету). Вы избавляетесь от паразита,
            который притворялся другом.
        </p>

        <div class="bg-emerald-50 rounded-xl p-5 my-6">
            <p class="font-bold text-emerald-800 mb-2 flex items-center gap-2">
                <?= icon('check', 'gc-icon-sm') ?> Попробуйте один день
            </p>
            <p class="text-sm text-emerald-700">
                Проживите день без мысли «я бросаю». Просто скажите:
                «Я не делаю этого». Почувствуйте разницу.
                Это и есть Переключатель.
            </p>
        </div>

        <div class="bg-violet-50 rounded-xl p-5 my-8">
            <p class="font-bold text-violet-800 mb-2 flex items-center gap-2">
                <?= icon('rocket', 'gc-icon-sm') ?> Готовы?
            </p>
            <p class="text-sm text-violet-700 mb-3">Откройте страницу метода и выберите «Метод Переключателя» в анкете.</p>
            <a href="<?= url('quit-smoking') ?>" class="text-sm font-bold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
                Применить метод <?= icon('right', 'gc-icon-xs') ?>
            </a>
        </div>

        <div class="border-t border-gray-200 pt-6 mt-8">
            <p class="text-xs font-bold text-gray-500 mb-2">Источники:</p>
            <ul class="text-xs text-gray-400 space-y-1">
                <li>• Azrin N.H., Nunn R.G. (1973). Habit-reversal. <i>Behaviour Research and Therapy</i>.</li>
                <li>• Duhigg C. (2012). <i>The Power of Habit</i>.</li>
            </ul>
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mt-6 text-xs text-amber-700 flex items-start gap-2">
            <span class="text-amber-600 shrink-0"><?= icon('warning', 'gc-icon-sm') ?></span>
            <span>Материал носит информационный характер и не является медицинской рекомендацией.</span>
        </div>

    </article>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>