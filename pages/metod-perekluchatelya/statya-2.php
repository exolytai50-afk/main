<?php // @page: Статья 2 — Анатомия желания
require_once __DIR__ . '/../../config/config.php';
require_login();

$pageTitle = 'Анатомия желания — Метод Переключателя';
$pageH1 = '🧪 Анатомия желания: как работает дофаминовый обман';
$GLOBALS['pageH1'] = $pageH1;

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <a href="<?= url('metod-perekluchatelya') ?>" class="text-sm text-violet-600 hover:text-violet-700 mb-6 inline-block">
        ← К методу Переключателя
    </a>

    <article class="prose prose-gray max-w-none">

        <h1 class="text-3xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <?= icon('flask', 'gc-icon-md') ?> Почему сигарета не даёт удовольствия (и что даёт)
        </h1>

        <p class="text-gray-600 leading-relaxed mb-4">
            Главная ошибка курильщика — вера в то, что сигарета расслабляет.
            Это иллюзия. Никотин — стимулятор. Он не расслабляет — он снимает
            <i>абстинентный синдром</i>, который сам же и вызвал.
        </p>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('recycle', 'gc-icon-sm') ?> Петля гомеостаза
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Когда вы курите, организм пытается восстановить баланс. Он выделяет
            вещества, подавляющие никотин. Вам становится «нормально».
            Когда уровень никотина падает — вы чувствуете дискомфорт. Вы снова курите.
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            Вы не получаете «плюс». Вы возвращаетесь к «нулю». Но мозг запоминает:
            «Сигарета = возвращение к нулю = выживание».
        </p>

        <div class="bg-blue-50 rounded-xl p-5 my-6">
            <p class="font-bold text-blue-800 mb-2 flex items-center gap-2">
                <?= icon('bulb', 'gc-icon-sm') ?> Ключевая мысль
            </p>
            <p class="text-sm text-blue-700">
                Зависимость — это не дыра в душе. Это ошибка в системе вознаграждения.
                Дофамин выделяется не на «удовольствие», а на <b>предвкушение</b> облегчения.
            </p>
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('shuffle', 'gc-icon-sm') ?> Техника «Замена ритуала»
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Мы не ждём, пока пройдёт тяга. Мы наблюдаем её. Когда возникает желание,
            скажите себе:
        </p>

        <blockquote class="border-l-4 border-violet-400 pl-4 my-6 italic text-gray-700">
            «Это не я хочу курить. Это моя дофаминовая система требует возврата к базовой линии.
            Сейчас я дам ей это — но другим способом».
        </blockquote>

        <ul class="list-disc list-inside space-y-2 text-gray-600 mb-6">
            <li>Курите, чтобы сделать паузу в работе → купите эспандер для кисти.</li>
            <li>Кофе с сигаретой → 2 минуты квадратного дыхания.</li>
            <li>Перекур в компании → позвоните другу или коллеге.</li>
        </ul>

        <p class="text-gray-600 leading-relaxed mb-4">
            Вы обманываете мозг, давая ему ритуал, но убирая яд.
        </p>

        <div class="bg-violet-50 rounded-xl p-5 my-8">
            <p class="font-bold text-violet-800 mb-2 flex items-center gap-2">
                <?= icon('rocket', 'gc-icon-sm') ?> Что дальше
            </p>
            <p class="text-sm text-violet-700 mb-3">Теперь вы знаете, что происходит в мозге. Дальше — как перестать считать дни.</p>
            <a href="<?= url('metod-perekluchatelya/statya-3') ?>" class="text-sm font-bold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
                Читать: Точка отсчёта <?= icon('right', 'gc-icon-xs') ?>
            </a>
        </div>

        <div class="border-t border-gray-200 pt-6 mt-8">
            <p class="text-xs font-bold text-gray-500 mb-2">Источники:</p>
            <ul class="text-xs text-gray-400 space-y-1">
                <li>• Volkow N.D. et al. (2016). Neurobiologic Advances from the Brain Disease Model of Addiction. <i>NEJM</i>.</li>
                <li>• Koob G.F., Volkow N.D. (2016). Neurobiology of addiction. <i>Lancet Psychiatry</i>.</li>
            </ul>
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mt-6 text-xs text-amber-700 flex items-start gap-2">
            <span class="text-amber-600 shrink-0"><?= icon('warning', 'gc-icon-sm') ?></span>
            <span>Материал носит информационный характер и не является медицинской рекомендацией.</span>
        </div>

    </article>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>