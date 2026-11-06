<?php // @page: Метод Переключателя
require_once __DIR__ . '/../config/config.php';
require_login();

$pageTitle = 'Метод Переключателя — научный подход к отказу от привычек';
$pageDescription = 'Авторский метод на основе нейробиологии привычек: дофамин, аллостаз, идентичность. Без силы воли. Без страха потери.';
$pageH1 = 'Метод Переключателя';
$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <!-- Hero -->
    <div class="text-center mb-12" data-aos="fade-up">
        <span class="inline-block px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-bold mb-3">
            Авторский метод GigaChef
        </span>
        <h1 class="text-4xl font-extrabold text-gray-800 mb-3 flex items-center justify-center gap-2">
            <?= icon('switch', 'gc-icon-sm') ?> Метод Переключателя
        </h1>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Не «бросаем» и не «боремся». Переключаем нейронную цепочку —
            через дофамин, привычку и идентичность.
        </p>
        <div class="flex gap-3 justify-center mt-6">
            <a href="<?= url('metod-perekluchatelya/statya-4') ?>"
               class="px-6 py-3 rounded-xl bg-violet-500 text-white font-bold hover:bg-violet-600 transition shadow-lg inline-flex items-center gap-2">
                <?= icon('rocket', 'gc-icon-sm') ?> Начать 7-дневный план
            </a>
            <a href="<?= url('quit-smoking') ?>"
               class="px-6 py-3 rounded-xl bg-gray-100 text-gray-700 font-bold hover:bg-gray-200 transition">
                Применить метод
            </a>
        </div>
    </div>

    <!-- Почему это не Карр -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-8" data-aos="fade-up">
        <h2 class="text-2xl font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <?= icon('flask', 'gc-icon-md') ?> Чем это отличается от классических методов
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 font-bold text-gray-700">Аспект</th>
                        <th class="text-left py-2 font-bold text-gray-700">Классические методы</th>
                        <th class="text-left py-2 font-bold text-violet-600">Метод Переключателя</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-semibold">Основа</td>
                        <td class="py-2">Внушение, установки</td>
                        <td class="py-2">Нейробиология привычек</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-semibold">Роль силы воли</td>
                        <td class="py-2">Не нужна</td>
                        <td class="py-2">Не нужна</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-semibold">Отношение к тяге</td>
                        <td class="py-2">«Просто не думай»</td>
                        <td class="py-2">Наблюдение и замена</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-semibold">Работа с триггерами</td>
                        <td class="py-2">Общая рекомендация</td>
                        <td class="py-2">Карта триггеров на 7 дней</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-semibold">Что делать при срыве</td>
                        <td class="py-2">Не описано</td>
                        <td class="py-2">Протокол срыва</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">Идентичность</td>
                        <td class="py-2">«Я бросающий»</td>
                        <td class="py-2">«Я не нуждаюсь в этом»</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3 столпа -->
    <h2 class="text-2xl font-extrabold text-gray-800 mb-6 text-center" data-aos="fade-up">Три столпа метода</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
        <div class="bg-white rounded-2xl shadow-md p-6" data-aos="fade-up">
            <div class="mb-3 text-violet-600"><?= icon('fusion', 'gc-icon-lg') ?></div>
            <h3 class="font-bold text-gray-800 mb-2">1. Деконструкция ритуала</h3>
            <p class="text-sm text-gray-600">Разбираем привычку на атомы: что именно вы любите? Кофе с сигаретой? Паузу? Социальный контакт?</p>
        </div>
        <div class="bg-white rounded-2xl shadow-md p-6" data-aos="fade-up">
            <div class="mb-3 text-violet-600"><?= icon('lightning-shield', 'gc-icon-lg') ?></div>
            <h3 class="font-bold text-gray-800 mb-2">2. Замена дофаминового пика</h3>
            <p class="text-sm text-gray-600">Находим здоровый источник дофамина с похожим эффектом. Спорт, холодный душ, дыхание.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-md p-6" data-aos="fade-up">
            <div class="mb-3 text-violet-600"><?= icon('mask', 'gc-icon-lg') ?></div>
            <h3 class="font-bold text-gray-800 mb-2">3. Новая идентичность</h3>
            <p class="text-sm text-gray-600">Не «я бросающий», а «я человек, который не нуждается в этом». Замена ярлыка меняет поведение.</p>
        </div>
    </div>

    <!-- Доказательная база -->
    <div class="bg-blue-50 rounded-2xl p-6 mb-12">
        <h2 class="text-xl font-extrabold text-blue-800 mb-3 flex items-center gap-2">
            <?= icon('openbook', 'gc-icon-sm') ?> Доказательная база
        </h2>
        <p class="text-sm text-blue-700 mb-4">Метод опирается на исследования, а не на «внушение»:</p>
        <ul class="space-y-2 text-sm text-blue-700">
            <li>• <b>Volkow N.D. et al. (2016).</b> «Neurobiologic Advances from the Brain Disease Model of Addiction». New England Journal of Medicine.</li>
            <li>• <b>Koob G.F., Volkow N.D. (2016).</b> «Neurobiology of addiction: a neurocircuitry analysis». Lancet Psychiatry.</li>
            <li>• <b>Verplanken B., Wood W. (2006).</b> «Interventions to Break and Create Consumer Habits». Journal of Public Policy & Marketing.</li>
            <li>• <b>Wegner D.M. (1987).</b> «Ironic processes of mental control». Psychological Review.</li>
            <li>• <b>Azrin N.H., Nunn R.G. (1973).</b> «Habit-reversal: A method of eliminating nervous habits and tics». Behaviour Research and Therapy.</li>
        </ul>
    </div>

    <!-- 5 статей -->
    <h2 class="text-2xl font-extrabold text-gray-800 mb-6 text-center">Серия статей</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-12">
        <?php
        $articles = [
            ['slug' => 'statya-1', 'icon' => 'stress', 'title' => 'Почему сила воли — худший инструмент', 'desc' => 'Эффект «белого медведя» и почему борьба усиливает желание'],
            ['slug' => 'statya-2', 'icon' => 'flask', 'title' => 'Анатомия желания', 'desc' => 'Как работает дофаминовый обман и что даёт сигарета на самом деле'],
            ['slug' => 'statya-3', 'icon' => 'pin', 'title' => 'Точка отсчёта', 'desc' => 'Разница между «я бросаю» и «я не нуждаюсь в этом»'],
            ['slug' => 'statya-4', 'icon' => 'calend', 'title' => '7-дневный план', 'desc' => 'Практический протокол по дням: от инвентаризации до точки отсчёта'],
            ['slug' => 'statya-5', 'icon' => 'heart', 'title' => 'Свобода вместо борьбы', 'desc' => 'Философия метода: конец войны с самим собой'],
        ];
        foreach ($articles as $a):
        ?>
        <a href="<?= url('metod-perekluchatelya/' . $a['slug']) ?>"
           class="bg-white rounded-2xl shadow-md p-6 hover:shadow-lg transition group">
            <div class="mb-3 text-violet-500 group-hover:text-violet-600 transition"><?= icon($a['icon'], 'gc-icon-lg') ?></div>
            <h3 class="font-bold text-gray-800 mb-1 group-hover:text-violet-600 transition"><?= e($a['title']) ?></h3>
            <p class="text-xs text-gray-500"><?= e($a['desc']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Дисклеймер -->
    <div class="bg-amber-50 rounded-2xl p-6 text-sm text-amber-700">
        <p class="mb-2 flex items-center gap-2"><span class="text-amber-600"><?= icon('warning', 'gc-icon-sm') ?></span><b>Важно:</b></p>
        <p class="mb-2">Метод Переключателя — авторская разработка GigaChef. Она <b>не связана</b> с книгами Аллена Карра и другими коммерческими методами.</p>
        <p>Метод не является медицинской рекомендацией. При тяжёлых формах зависимости обратитесь к врачу.</p>
    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>