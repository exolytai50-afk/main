<?php // @page: Статья 3 — Точка отсчёта
require_once __DIR__ . '/../../config/config.php';
require_login();

$pageTitle = 'Точка отсчёта — Метод Переключателя';
$pageH1 = 'Точка отсчёта: как перестать считать дни';
$GLOBALS['pageH1'] = $pageH1;

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <a href="<?= url('metod-perekluchatelya') ?>" class="text-sm text-violet-600 hover:text-violet-700 mb-6 inline-block">
        ← К методу Переключателя
    </a>

    <article class="prose prose-gray max-w-none">

        <h1 class="text-3xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <?= icon('pin', 'gc-icon-md') ?> Синдром «Я бросаю» против «Я не нуждаюсь в этом»
        </h1>

        <p class="text-gray-600 leading-relaxed mb-4">
            Самая опасная фраза в жизни зависимого: «Я бросил курить 3 дня назад».
            Почему? Потому что вы продолжаете идентифицировать себя с курильщиком.
            Вы просто «в завязке». Это создаёт напряжение ожидания.
        </p>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('mask', 'gc-icon-sm') ?> Самоисполняющееся пророчество
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Если вы считаете, что вы «боретесь», — вы будете страдать.
            Если вы считаете, что вы «человек, которому не нужно», — тяга теряет смысл.
            Зачем некурящему хотеть сигарету? Это абсурд.
        </p>

        <div class="bg-emerald-50 rounded-xl p-5 my-6">
            <p class="font-bold text-emerald-800 mb-2 flex items-center gap-2">
                <?= icon('bulb', 'gc-icon-sm') ?> Исследования подтверждают
            </p>
            <p class="text-sm text-emerald-700">
                Verplanken & Wood (2006): изменение <b>самоидентификации</b> — самый эффективный
                способ сломать привычку. Не сила воли, а новая роль.
            </p>
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('switch', 'gc-icon-sm') ?> Три шага «Переключателя»
        </h2>

        <ol class="list-decimal list-inside space-y-3 text-gray-600 mb-6">
            <li>
                <b>Отказ от ярлыка «бросающий».</b>
                Вы не бросаете. Вы просто перестали делать то, что вам не нужно.
            </li>
            <li>
                <b>Практика «Пустой стул».</b>
                Представьте, что зависимость — отдельный персонаж (надоедливый сосед).
                Когда он приходит с предложением, вы говорите: «Нет, спасибо, я занят».
            </li>
            <li>
                <b>Фокус на выгоде.</b>
                Вместо «я не могу курить» — «я могу дышать свободно».
            </li>
        </ol>

        <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-3 flex items-center gap-2">
            <?= icon('pin', 'gc-icon-sm') ?> Почему «Точка отсчёта», а не «Точка невозврата»
        </h2>

        <p class="text-gray-600 leading-relaxed mb-4">
            Слово «невозврата» обманчиво. Срыв возможен, даже через год.
            И если человек верит, что «вернуться уже нельзя», то первый срыв
            обрушит всю мотивацию.
        </p>

        <p class="text-gray-600 leading-relaxed mb-4">
            Мы говорим «точка отсчёта»: каждый день — новый старт.
            Сорвались — не «всё пропало», а «продолжаем с этой точки».
        </p>

        <div class="bg-violet-50 rounded-xl p-5 my-8">
            <p class="font-bold text-violet-800 mb-2 flex items-center gap-2">
                <?= icon('rocket', 'gc-icon-sm') ?> Что дальше
            </p>
            <p class="text-sm text-violet-700 mb-3">Теперь — практика. 7-дневный план: от инвентаризации до точки отсчёта.</p>
            <a href="<?= url('metod-perekluchatelya/statya-4') ?>" class="text-sm font-bold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
                Читать: 7-дневный план <?= icon('right', 'gc-icon-xs') ?>
            </a>
        </div>

        <div class="border-t border-gray-200 pt-6 mt-8">
            <p class="text-xs font-bold text-gray-500 mb-2">Источники:</p>
            <ul class="text-xs text-gray-400 space-y-1">
                <li>• Verplanken B., Wood W. (2006). Interventions to Break and Create Consumer Habits. <i>Journal of Public Policy & Marketing</i>.</li>
                <li>• Marlatt G.A., Gordon J.R. (1985). <i>Relapse Prevention</i>.</li>
            </ul>
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mt-6 text-xs text-amber-700 flex items-start gap-2">
            <span class="text-amber-600 shrink-0"><?= icon('warning', 'gc-icon-sm') ?></span>
            <span>Материал носит информационный характер и не является медицинской рекомендацией.</span>
        </div>

    </article>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>