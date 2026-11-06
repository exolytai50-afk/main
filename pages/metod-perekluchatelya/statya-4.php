<?php // @page: Статья 4 — 7-дневный план
require_once __DIR__ . '/../../config/config.php';
require_login();

$pageTitle = '7-дневный план — Метод Переключателя';
$pageH1 = '7-дневный план переключения';
$GLOBALS['pageH1'] = $pageH1;

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <a href="<?= url('metod-perekluchatelya') ?>" class="text-sm text-violet-600 hover:text-violet-700 mb-6 inline-block">
        ← К методу Переключателя
    </a>

    <h1 class="text-3xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
        <?= icon('calend', 'gc-icon-md') ?> Ваш план на неделю
    </h1>
    <p class="text-gray-600 mb-8">Метод не работает без действий. Вот пошаговый протокол на 7 дней.</p>

    <?php
    $plan = [
        ['day' => 1, 'title' => 'Инвентаризация', 'icon' => 'pen', 'tasks' => [
            'Курите/пьёте/едите как обычно',
            'Каждый раз записывайте: «Что я чувствовал за 5 секунд до?»',
            'Отмечайте: скука, стресс, голод, желание отдохнуть',
            'Это сбор данных, а не попытка что-то изменить',
        ]],
        ['day' => 2, 'title' => 'Разрыв шаблона', 'icon' => 'shuffle', 'tasks' => [
            'Измените одну деталь ритуала',
            'Курите в машине — переставьте пепельницу',
            'Пьёте кофе на диване — пересядьте на стул',
            'Мозг заметит несостыковку',
        ]],
        ['day' => 3, 'title' => 'Поиск замены', 'icon' => 'lightning', 'tasks' => [
            'Найдите «дофаминового донора»',
            'Спорт, холодный душ, видеоигра, новый сериал',
            'Что-то, что даёт быстрый выброс дофамина без вреда',
            'Запишите 3 варианта',
        ]],
        ['day' => 4, 'title' => 'Деконструкция «друга»', 'icon' => 'search', 'tasks' => [
            'Напишите список всех «плюсов» привычки',
            'Рядом напишите правду',
            '«Сигарета помогает сосредоточиться» → «Я отвлекаюсь на 10 минут и теряю концентрацию»',
            'Так вы обнажаете иллюзию',
        ]],
        ['day' => 5, 'title' => 'Тест-драйв', 'icon' => 'flask', 'tasks' => [
            'Пропустите один ритуал (например, утреннюю сигарету)',
            'Замените его на 10 приседаний или 2 минуты дыхания',
            'Почувствуйте: мир не рухнул',
            'Это доказательство, что вы можете',
        ]],
        ['day' => 6, 'title' => 'Социальный эксперимент', 'icon' => 'friends', 'tasks' => [
            'Скажите другу: «Я не курю»',
            'Не «бросаю», а «не курю»',
            'Посмотрите на реакцию',
            'Это закрепит новую идентичность',
        ]],
        ['day' => 7, 'title' => 'Точка отсчёта', 'icon' => 'pin', 'tasks' => [
            'Вы больше не считаете дни',
            'Вы просто живёте',
            'Если мысль о привычке приходит — «Переключатель»',
            'Вспоминаете: это дофаминовая петля, и переключаетесь на действие',
        ]],
    ];
    ?>

    <div class="space-y-4">
        <?php foreach ($plan as $p): ?>
        <div class="bg-white rounded-2xl shadow-md p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center text-2xl font-bold">
                    <?= $p['day'] ?>
                </div>
                <div>
                    <p class="text-xs text-violet-600 font-bold uppercase tracking-wider">День <?= $p['day'] ?></p>
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-violet-500"><?= icon($p['icon'], 'gc-icon-sm') ?></span>
                        <?= e($p['title']) ?>
                    </h3>
                </div>
            </div>
            <ul class="space-y-2 text-sm text-gray-600 pl-3">
                <?php foreach ($p['tasks'] as $task): ?>
                <li class="flex items-start gap-2">
                    <span class="text-violet-400 mt-1">•</span>
                    <span><?= e($task) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-violet-50 rounded-2xl p-6 mt-8 text-center">
        <p class="font-bold text-violet-800 mb-2 flex items-center justify-center gap-2">
            <?= icon('rocket', 'gc-icon-sm') ?> Готовы начать?
        </p>
        <p class="text-sm text-violet-700 mb-4">Откройте страницу метода и отметьте «Метод Переключателя» в анкете.</p>
        <a href="<?= url('quit-smoking') ?>" class="inline-block px-6 py-3 rounded-xl bg-violet-500 text-white font-bold hover:bg-violet-600 transition shadow-lg">
            Применить метод
        </a>
    </div>

    <div class="bg-violet-50 rounded-xl p-5 my-8">
        <p class="font-bold text-violet-800 mb-2 flex items-center gap-2">
            <?= icon('right', 'gc-icon-sm') ?> Что дальше
        </p>
        <p class="text-sm text-violet-700 mb-3">Философия метода: почему свобода важнее борьбы.</p>
        <a href="<?= url('metod-perekluchatelya/statya-5') ?>" class="text-sm font-bold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
            Читать: Свобода вместо борьбы <?= icon('right', 'gc-icon-xs') ?>
        </a>
    </div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>