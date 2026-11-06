<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$meta = get_page_meta('/dietolog');
if ($meta) { apply_meta($meta); }

$pageTitle = $GLOBALS['pageTitle'] ?? 'Диетолог — GigaChef';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Персональный AI-диетолог: план питания, дневник, анализ и рекомендации';
$pageH1 = $GLOBALS['pageH1'] ?? 'Диетолог';
$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

$csrfToken = bin2hex(random_bytes(32));
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['csrf_token'] = $csrfToken;
}

$questionnaire = db_one(
    'SELECT * FROM diet_questionnaires WHERE user_email = ? ORDER BY created_at DESC LIMIT 1',
    [current_user()['email']]
);

$weightEntries = [];
if ($questionnaire) {
    $weightEntries = db_all(
        'SELECT * FROM diet_weight WHERE user_email = ? ORDER BY entry_date DESC LIMIT 30',
        [current_user()['email']]
    );
}

$diaryEntries = [];
if ($questionnaire) {
    $diaryEntries = db_all(
        'SELECT * FROM diet_diary WHERE user_email = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY entry_date DESC',
        [current_user()['email']]
    );
}

$todaySteps = 0;
if ($questionnaire) {
    $stepsData = db_one(
        'SELECT * FROM diet_steps WHERE user_email = ? AND entry_date = CURDATE()',
        [current_user()['email']]
    );
    $todaySteps = (int)($stepsData['steps_count'] ?? 0);
}

$kbjuTargets = ['calories' => 0, 'proteins' => 0, 'fats' => 0, 'carbs' => 0];
$bmi = 0;
$bmiCategory = '';
$bmiColor = '';
$bmiBg = '';
$idealWeightMin = 0;
$idealWeightMax = 0;
$waterGoalMl = 2000;

if ($questionnaire) {
    $weight = floatval($questionnaire['weight'] ?? 70);
    $height = intval($questionnaire['height'] ?? 170);
    $age    = intval($questionnaire['age'] ?? 30);
    $gender = $questionnaire['gender'] ?? 'male';
    $actKey = $questionnaire['activity_level'] ?? 'moderate';
    $goal   = $questionnaire['goal'] ?? 'maintenance';

    $bmr = ($gender === 'male')
        ? 10 * $weight + 6.25 * $height - 5 * $age + 5
        : 10 * $weight + 6.25 * $height - 5 * $age - 161;

    $actFactors = [
        'sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55,
        'active' => 1.725, 'very_active' => 1.9,
    ];
    $tdee = $bmr * ($actFactors[$actKey] ?? 1.55);

    $goalMult = [
        'weight_loss' => 0.85, 'muscle_gain' => 1.15, 'maintenance' => 1.0,
        'diabetes' => 0.95, 'low_carb' => 0.9, 'vegetarian' => 1.0,
        'gluten_free' => 1.0, 'lactose_free' => 1.0,
    ];
    $calTarget = $tdee * ($goalMult[$goal] ?? 1.0);

    $proteinPerKg = ($goal === 'muscle_gain') ? 2.0 : 1.6;
    $protein = $weight * $proteinPerKg;
    $fat     = ($calTarget * 0.25) / 9;
    $carbs   = max(0, ($calTarget - $protein * 4 - $fat * 9) / 4);

    $kbjuTargets = [
        'calories' => round($calTarget),
        'proteins' => round($protein),
        'fats'     => round($fat),
        'carbs'    => round($carbs),
    ];

    $bmi = round($weight / pow($height / 100, 2), 1);
    if ($bmi < 18.5)      { $bmiCategory = 'Недостаток веса'; $bmiColor = 'text-blue-600'; $bmiBg = 'bg-blue-50'; }
    elseif ($bmi < 25)   { $bmiCategory = 'Норма';           $bmiColor = 'text-emerald-600'; $bmiBg = 'bg-emerald-50'; }
    elseif ($bmi < 30)   { $bmiCategory = 'Избыточный вес';  $bmiColor = 'text-amber-600'; $bmiBg = 'bg-amber-50'; }
    else                 { $bmiCategory = 'Ожирение';         $bmiColor = 'text-rose-600'; $bmiBg = 'bg-rose-50'; }

    $idealWeightMin = round(18.5 * pow($height / 100, 2), 1);
    $idealWeightMax = round(24.9 * pow($height / 100, 2), 1);
    $waterGoalMl = intval($weight * 30);
}

$productsByGoal = [
    'weight_loss'   => ['Куриная грудка', 'Гречка', 'Овощи', 'Яйца', 'Творог 5%', 'Зелень', 'Рыба', 'Огурцы', 'Помидоры', 'Оливковое масло'],
    'muscle_gain'   => ['Курица', 'Говядина', 'Рис', 'Гречка', 'Яйца', 'Творог', 'Овсянка', 'Бананы', 'Орехи', 'Батат'],
    'maintenance'   => ['Курица', 'Рыба', 'Гречка', 'Рис', 'Овощи', 'Фрукты', 'Оливковое масло', 'Яйца', 'Творог', 'Зелень'],
    'diabetes'      => ['Курица', 'Рыба', 'Овощи', 'Яйца', 'Творог', 'Гречка', 'Брокколи', 'Кабачки', 'Зелень', 'Оливковое масло'],
    'low_carb'      => ['Курица', 'Говядина', 'Рыба', 'Яйца', 'Творог', 'Сыр', 'Авокадо', 'Орехи', 'Брокколи', 'Оливковое масло'],
    'vegetarian'    => ['Тофу', 'Чечевица', 'Фасоль', 'Овощи', 'Фрукты', 'Орехи', 'Киноа', 'Гречка', 'Творог', 'Яйца'],
    'gluten_free'   => ['Рис', 'Гречка', 'Киноа', 'Курица', 'Рыба', 'Овощи', 'Фрукты', 'Яйца', 'Творог', 'Оливковое масло'],
    'lactose_free'  => ['Курица', 'Рыба', 'Гречка', 'Рис', 'Овощи', 'Фрукты', 'Орехи', 'Яйца', 'Оливковое масло', 'Зелень'],
];
$recommendedProducts = $productsByGoal[$questionnaire['goal'] ?? 'maintenance'] ?? $productsByGoal['maintenance'];

$goalsRu = [
    'weight_loss' => 'Похудение', 'muscle_gain' => 'Набор массы',
    'maintenance' => 'Поддержка веса', 'diabetes' => 'Диабет/низкий сахар',
    'low_carb' => 'Низкоуглеводное', 'vegetarian' => 'Вегетарианство',
    'gluten_free' => 'Без глютена', 'lactose_free' => 'Без лактозы',
];

require_once __DIR__ . '/../includes/header.php';
?>
<style>
    [x-cloak] { display: none !important; }
    .step-enter-active, .step-leave-active { transition: all 0.25s ease; }
    .step-enter-from { opacity: 0; transform: translateX(20px); }
    .step-leave-to   { opacity: 0; transform: translateX(-20px); }
    .skeleton {
        background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: 0.5rem;
    }
    @keyframes skeleton-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    .drop-zone.dragover { border-color: #10b981; background: #ecfdf5; }
    .ring-progress { transform: rotate(-90deg); }

    /* Выравнивание SVG-иконок */
    .gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
        display: inline-block;
        vertical-align: -0.125em;
        flex-shrink: 0;
    }
</style>

<!-- Данные для JS -->
<script>
window.__DIETOLOG__ = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    dietologUrl: <?= json_encode(url('dietolog')) ?>,
    kbjuTargets: <?= json_encode($kbjuTargets, JSON_UNESCAPED_UNICODE) ?>,
    waterGoalMl: <?= (int)$waterGoalMl ?>,
    todaySteps: <?= (int)$todaySteps ?>,
    diaryEntries: <?= json_encode($diaryEntries, JSON_UNESCAPED_UNICODE) ?>,
    weightEntries: <?= json_encode($weightEntries, JSON_UNESCAPED_UNICODE) ?>,
    questionnaire: <?= json_encode($questionnaire, JSON_UNESCAPED_UNICODE) ?>,

    // Иконки для мобильного dropdown табов
    tabIcons: {
        plan:     `<?= str_replace('`', '\\`', icon('dinner')) ?>`,
        diary:    `<?= str_replace('`', '\\`', icon('openbook')) ?>`,
        analysis: `<?= str_replace('`', '\\`', icon('chart')) ?>`,
        checks:   `<?= str_replace('`', '\\`', icon('receipt')) ?>`,
        steps:    `<?= str_replace('`', '\\`', icon('steps')) ?>`,
    }
};
</script>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

<?php if (!$questionnaire): ?>
    <!-- ============ ОНБОРДИНГ ============ -->
    <div x-data="dietForm()" x-cloak
         @open-questionnaire.window="openQuestionnaire($event.detail)">
        <div class="text-center mb-8" data-aos="fade-up">
            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold mb-3 inline-flex items-center gap-1">
    <?= icon('diet') ?>
    <span>Диетолог</span>
</span>
            <h1 class="text-3xl font-extrabold text-gray-800"><?= e($pageH1) ?></h1>
            <p class="mt-2 text-gray-500">Выберите цель — AI составит персональный план питания</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-12">
            <?php
            $goals = [
                ['id' => 'weight_loss', 'icon_name' => 'diet',       'title' => 'Похудение',          'desc' => 'Снижение веса с дефицитом калорий'],
                ['id' => 'muscle_gain', 'icon_name' => 'lightning',  'title' => 'Набор массы',        'desc' => 'Увеличение мышечной массы'],
                ['id' => 'maintenance', 'icon_name' => 'check',      'title' => 'Поддержка веса',     'desc' => 'Сохранение текущей формы'],
                ['id' => 'diabetes',    'icon_name' => 'water',      'title' => 'Диабет',             'desc' => 'Контроль уровня сахара'],
                ['id' => 'low_carb',    'icon_name' => 'diet',       'title' => 'Низкоуглеводное',    'desc' => 'Кето / LCHF питание'],
                ['id' => 'vegetarian',  'icon_name' => 'leaf',       'title' => 'Вегетарианство',     'desc' => 'Рацион без мяса'],
                ['id' => 'gluten_free', 'icon_name' => 'wheat',      'title' => 'Без глютена',        'desc' => 'При целиакии / непереносимости'],
                ['id' => 'lactose_free','icon_name' => 'milk',       'title' => 'Без лактозы',        'desc' => 'Без молочных продуктов'],
            ];

            foreach ($goals as $goal): ?>
            <button @click="openQuestionnaire({ goal: '<?= $goal['id'] ?>' })"
                    class="bg-white rounded-2xl shadow-md p-6 text-center hover:shadow-lg transition group cursor-pointer">
                <div class="text-4xl mb-3 group-hover:scale-110 transition inline-flex items-center justify-center" style="width:1.5em;height:1.5em;">
                    <?= icon($goal['icon_name']) ?>
                </div>
                <h3 class="font-bold text-gray-800 mb-1"><?= $goal['title'] ?></h3>
                <p class="text-xs text-gray-400"><?= $goal['desc'] ?></p>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="mb-12">
            <h2 class="text-2xl font-extrabold text-gray-800 text-center mb-8">Как это работает</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="text-center p-4">
        <div class="text-4xl mb-2 inline-flex items-center justify-center"><?= icon('openbook') ?></div>
        <h3 class="font-bold text-gray-800 mb-1">Анкета</h3>
        <p class="text-xs text-gray-500">Заполните данные о себе</p>
    </div>
    <div class="text-center p-4">
        <div class="text-4xl mb-2 inline-flex items-center justify-center"><?= icon('advisor') ?></div>
        <h3 class="font-bold text-gray-800 mb-1">AI-анализ</h3>
        <p class="text-xs text-gray-500">Нейросеть рассчитает план</p>
    </div>
    <div class="text-center p-4">
        <div class="text-4xl mb-2 inline-flex items-center justify-center"><?= icon('dinner') ?></div>
        <h3 class="font-bold text-gray-800 mb-1">План питания</h3>
        <p class="text-xs text-gray-500">Получите меню на день</p>
    </div>
    <div class="text-center p-4">
        <div class="text-4xl mb-2 inline-flex items-center justify-center"><?= icon('time') ?></div>
        <h3 class="font-bold text-gray-800 mb-1">Дневник и анализ</h3>
        <p class="text-xs text-gray-500">Отслеживайте прогресс</p>
    </div>
</div>
</div>
            

        <div class="bg-amber-50 rounded-2xl p-6 text-center max-w-3xl mx-auto">
    <h3 class="font-bold text-amber-700 mb-2 inline-flex items-center gap-1">
        <?= icon('warning') ?>
        <span>Важно</span>
    </h3>
    <p class="text-sm text-amber-600">Рекомендации не являются медицинской консультацией. Перед изменением рациона проконсультируйтесь с врачом.</p>
    <p class="text-xs text-amber-500 mt-2">Данные не передаются третьим лицам.</p>
</div>

        <!-- Модальное окно анкеты (4 шага) -->
        <div x-show="showQuestionnaire" x-transition style="display:none"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4"
             @click.self="closeQuestionnaire()"
             @keydown.escape.window="closeQuestionnaire()"
             role="dialog" aria-modal="true">

            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-2xl font-extrabold text-gray-800 inline-flex items-center gap-2">
    <template x-if="!isEditing">
        <span class="inline-flex items-center"><?= icon('openbook') ?></span>
    </template>
    <template x-if="isEditing">
        <span class="inline-flex items-center" style="display:none"><?= icon('pen') ?></span>
    </template>
    <span x-text="isEditing ? 'Редактирование анкеты' : 'Анкета диетолога'"></span>
</h3>
                    <button @click="closeQuestionnaire()" 
        class="text-gray-400 hover:text-gray-600 inline-flex items-center justify-center w-8 h-8"
        aria-label="Закрыть">
    <?= icon('close') ?>
</button>
                </div>

                <div class="flex items-center gap-2 mb-2">
                    <template x-for="i in 4" :key="i">
                        <div class="flex-1 h-2 rounded-full transition-all"
                             :class="step >= i ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                    </template>
                </div>
                <p class="text-xs text-gray-400 text-center mb-6">Шаг <span x-text="step"></span> из 4</p>

                <form @submit.prevent="submitQuestionnaire">
                    <input type="hidden" name="csrf_token" :value="window.__DIETOLOG__.csrfToken">

                    <!-- Шаг 1 -->
                    <div x-show="step === 1" class="space-y-4">
                        <h4 class="font-bold text-gray-700">1. Базовые данные</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Пол *</label>
                                <select x-model="formData.gender" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                                    <option value="">Выберите…</option>
                                    <option value="male">Мужской</option>
                                    <option value="female">Женский</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Возраст *</label>
                                <input type="number" x-model.number="formData.age" min="14" max="100" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Рост (см) *</label>
                                <input type="number" x-model.number="formData.height" min="100" max="250" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Вес (кг) *</label>
                                <input type="number" x-model.number="formData.weight" min="30" max="300" step="0.1" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Целевой вес (кг)</label>
                                <input type="number" x-model.number="formData.target_weight" min="30" max="300" step="0.1" placeholder="Необязательно" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs text-gray-500 block mb-1">Уровень активности *</label>
                                <select x-model="formData.activity_level" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                                    <option value="sedentary">Сидячая работа, мало движения</option>
                                    <option value="light">Лёгкая активность (1–2 тренировки/нед)</option>
                                    <option value="moderate" selected>Умеренная (2–3 тренировки/нед)</option>
                                    <option value="active">Активная (4–5 тренировки/нед)</option>
                                    <option value="very_active">Очень активная (6–7 тренировки/нед)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Шаг 2 -->
                    <div x-show="step === 2" style="display:none" class="space-y-4">
                        <h4 class="font-bold text-gray-700">2. Здоровье и ограничения</h4>
                        <div>
                            <label class="text-xs text-gray-500 block mb-2">Аллергии и непереносимости</label>
                            <div class="flex flex-wrap gap-3">
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="gluten" class="rounded"> Глютен</label>
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="lactose" class="rounded"> Лактоза</label>
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="nuts" class="rounded"> Орехи</label>
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="seafood" class="rounded"> Морепродукты</label>
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="eggs" class="rounded"> Яйца</label>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Заболевания</label>
                            <input type="text" x-model="formData.medical_conditions" placeholder="Диабет, гипертония…" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        </div>
                    </div>

                    <!-- Шаг 3: Результаты анализов (новый) -->
                    <div x-show="step === 3" style="display:none" class="space-y-4">
                        <h4 class="font-bold text-gray-700">3. Результаты анализов <span class="text-xs font-normal text-gray-400">(необязательно)</span></h4>
                        <div class="bg-blue-50 rounded-xl p-3 mb-2">
                            <p class="text-xs text-blue-600 inline-flex items-start gap-1">
    <?= icon('info') ?>
    <span>Если у вас есть свежие результаты анализов, укажите их — AI учтёт их в рекомендациях. Все поля необязательны.</span>
</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Глюкоза (ммоль/л)</label>
                                <input type="number" x-model="formData.blood_sugar" step="0.1" placeholder="напр. 5.4" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Холестерин (ммоль/л)</label>
                                <input type="number" x-model="formData.cholesterol" step="0.1" placeholder="напр. 4.8" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Гемоглобин (г/л)</label>
                                <input type="number" x-model="formData.hemoglobin" placeholder="напр. 140" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Давление (систол.)</label>
                                <input type="number" x-model="formData.systolic_pressure" placeholder="напр. 120" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Давление (диастол.)</label>
                                <input type="number" x-model="formData.diastolic_pressure" placeholder="напр. 80" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Шаг 4: Подтверждение -->
                    <div x-show="step === 4" style="display:none" class="space-y-4">
                        <h4 class="font-bold text-gray-700">4. Предпочтения и подтверждение</h4>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Бюджет</label>
                            <select x-model="formData.budget" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                                <option value="economy">Эконом</option>
                                <option value="medium" selected>Средний</option>
                                <option value="premium">Премиум</option>
                            </select>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4 text-sm text-emerald-700 space-y-1">
                            <p><b>Цель:</b> <span x-text="goalTitle"></span></p>
                            <p><b>Пол:</b> <span x-text="formData.gender === 'male' ? 'Мужской' : 'Женский'"></span></p>
                            <p><b>Возраст:</b> <span x-text="formData.age"></span> лет</p>
                            <p><b>Вес:</b> <span x-text="formData.weight"></span> кг · <b>Рост:</b> <span x-text="formData.height"></span> см</p>
                            <p x-show="formData.target_weight"><b>Целевой вес:</b> <span x-text="formData.target_weight"></span> кг</p>
                            <p x-show="formData.allergies.length"><b>Аллергии:</b> <span x-text="formData.allergies.join(', ')"></span></p>
                            <p x-show="formData.blood_sugar"><b>Глюкоза:</b> <span x-text="formData.blood_sugar"></span> ммоль/л</p>
                            <p x-show="formData.cholesterol"><b>Холестерин:</b> <span x-text="formData.cholesterol"></span> ммоль/л</p>
                            <p x-show="formData.hemoglobin"><b>Гемоглобин:</b> <span x-text="formData.hemoglobin"></span> г/л</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-3">
                            <p class="text-xs text-amber-600 inline-flex items-start gap-1">
    <?= icon('warning') ?>
    <span>Рекомендации не являются медицинской консультацией.</span>
</p>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="button" x-show="step > 1" @click="prevStep()"
                                class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm hover:bg-gray-200 transition">← Назад</button>
                        <button type="button" x-show="step < 4" @click="nextStep()"
                                :disabled="!stepValid"
                                class="flex-1 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-50 disabled:cursor-not-allowed">Далее →</button>
                        <button type="submit" x-show="step === 4" :disabled="submitting"
        class="flex-1 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
    <span x-show="!submitting" class="inline-flex items-center gap-2">
        <?= icon('diskette') ?>
        <span>Сохранить план питания</span>
    </span>
    <span x-show="submitting" class="inline-flex items-center gap-2">
        <?= icon('hourglass') ?>
        <span>Сохранение…</span>
    </span>
</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ============ ДАШБОРД ============ -->
    <div x-data="dietologApp()" x-cloak
         @open-questionnaire.window="$dispatch('open-questionnaire', $event.detail)">
        <!-- Шапка -->
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold mb-3 inline-flex items-center gap-1">
    <?= icon('diet') ?>
    <span>Диетолог</span>
</span>
            <h1 class="text-3xl font-extrabold text-gray-800"><?= e($pageH1) ?></h1>
            <p class="text-gray-500 mt-2 flex items-center justify-center gap-2 flex-wrap">
                <span>Цель: <span class="font-bold text-emerald-600"><?= e($goalsRu[$questionnaire['goal']] ?? $questionnaire['goal']) ?></span></span>
                <button @click="$dispatch('open-questionnaire', { goal: '<?= e($questionnaire['goal']) ?>', edit: true })" 
        class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-600 text-xs font-bold hover:bg-blue-200 transition">
    <?= icon('pen') ?>
    <span>Редактировать анкету</span>
</button>
                <span x-show="streak > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-100 text-orange-600 text-xs font-bold">
    <?= icon('flame') ?>
    <span x-text="streak"></span>
    <span>дн. подряд</span>
</span>
            </p>
        </div>

        <!-- Сводка: КБЖУ + BMI + вода -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" data-aos="fade-up">
            <!-- КБЖУ -->
            <div class="bg-white rounded-2xl shadow-md p-5">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('lightning') ?>
    <span>Суточная норма</span>
</h3>
                <div class="grid grid-cols-4 gap-2">
                    <div class="text-center">
                        <p class="text-xl font-extrabold text-emerald-600"><?= $kbjuTargets['calories'] ?></p>
                        <p class="text-[10px] text-gray-400">ккал</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-extrabold text-blue-600"><?= $kbjuTargets['proteins'] ?><span class="text-xs">г</span></p>
                        <p class="text-[10px] text-gray-400">белки</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-extrabold text-amber-600"><?= $kbjuTargets['fats'] ?><span class="text-xs">г</span></p>
                        <p class="text-[10px] text-gray-400">жиры</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-extrabold text-violet-600"><?= $kbjuTargets['carbs'] ?><span class="text-xs">г</span></p>
                        <p class="text-[10px] text-gray-400">углев.</p>
                    </div>
                </div>
            </div>

            <!-- BMI -->
            <div class="bg-white rounded-2xl shadow-md p-5">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('diet') ?>
    <span>Индекс массы тела</span>
</h3>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <p class="text-3xl font-extrabold <?= $bmiColor ?>"><?= $bmi ?></p>
                        <p class="text-xs <?= $bmiColor ?>"><?= $bmiCategory ?></p>
                    </div>
                    <div class="flex-1 text-xs text-gray-500 space-y-0.5">
                        <p>Идеальный вес для вашего роста:</p>
                        <p class="font-bold text-gray-700"><?= $idealWeightMin ?>–<?= $idealWeightMax ?> кг</p>
                        <?php if (!empty($questionnaire['target_weight'])): ?>
                        <p>Ваша цель: <b class="text-emerald-600"><?= e($questionnaire['target_weight']) ?> кг</b></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Вода -->
            <div class="bg-white rounded-2xl shadow-md p-5">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('water') ?>
    <span>Норма воды</span>
</h3>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <p class="text-3xl font-extrabold text-blue-600"><?= round($waterGoalMl / 1000, 1) ?><span class="text-base">л</span></p>
                        <p class="text-xs text-gray-400"><?= $waterGoalMl ?> мл/день</p>
                    </div>
                    <div class="flex-1 text-xs text-gray-500">
                        <p>Расчёт: 30 мл на 1 кг веса</p>
                        <p class="mt-1">Отметьте стаканы во вкладке «Дневник»</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Результаты анализов (если есть) -->
        <?php
        $hasMedical = !empty($questionnaire['blood_sugar']) || !empty($questionnaire['cholesterol'])
            || !empty($questionnaire['hemoglobin']) || !empty($questionnaire['systolic_pressure']);
        ?>
        <?php if ($hasMedical): ?>
        <div class="bg-white rounded-2xl shadow-md p-5 mb-6" data-aos="fade-up">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-gray-800 text-sm inline-flex items-center gap-1">
    <?= icon('diet') ?>
    <span>Результаты анализов</span>
</h3>
                <button @click="$dispatch('open-questionnaire', { goal: '<?= e($questionnaire['goal']) ?>', edit: true })"
                        class="text-xs text-blue-500 hover:underline">обновить</button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php if (!empty($questionnaire['blood_sugar'])): ?>
                <div class="text-center bg-rose-50 rounded-xl p-3">
                    <p class="text-xl font-bold text-rose-600"><?= e($questionnaire['blood_sugar']) ?></p>
                    <p class="text-[10px] text-gray-400">глюкоза, ммоль/л</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($questionnaire['cholesterol'])): ?>
                <div class="text-center bg-amber-50 rounded-xl p-3">
                    <p class="text-xl font-bold text-amber-600"><?= e($questionnaire['cholesterol']) ?></p>
                    <p class="text-[10px] text-gray-400">холестерин, ммоль/л</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($questionnaire['hemoglobin'])): ?>
                <div class="text-center bg-violet-50 rounded-xl p-3">
                    <p class="text-xl font-bold text-violet-600"><?= e($questionnaire['hemoglobin']) ?></p>
                    <p class="text-[10px] text-gray-400">гемоглобин, г/л</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($questionnaire['systolic_pressure'])): ?>
                <div class="text-center bg-blue-50 rounded-xl p-3">
                    <p class="text-xl font-bold text-blue-600"><?= e($questionnaire['systolic_pressure']) ?>/<?= e($questionnaire['diastolic_pressure'] ?? '') ?></p>
                    <p class="text-[10px] text-gray-400">давление, мм рт.ст.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Табы: десктоп -->
        <div class="hidden sm:flex gap-2 mb-6 border-b border-gray-200 overflow-x-auto">
    <button @click="switchTab('plan')" :class="tab==='plan' ? 'border-emerald-400 text-emerald-700' : 'border-transparent text-gray-400'" 
            class="px-4 py-2 border-b-2 font-semibold text-sm whitespace-nowrap inline-flex items-center gap-1">
        <?= icon('dinner') ?>
        <span>План</span>
    </button>
    <button @click="switchTab('diary')" :class="tab==='diary' ? 'border-emerald-400 text-emerald-700' : 'border-transparent text-gray-400'" 
            class="px-4 py-2 border-b-2 font-semibold text-sm whitespace-nowrap inline-flex items-center gap-1">
        <?= icon('openbook') ?>
        <span>Дневник</span>
    </button>
    <button @click="switchTab('analysis')" :class="tab==='analysis' ? 'border-emerald-400 text-emerald-700' : 'border-transparent text-gray-400'" 
            class="px-4 py-2 border-b-2 font-semibold text-sm whitespace-nowrap inline-flex items-center gap-1">
        <?= icon('chart') ?>
        <span>Анализ</span>
    </button>
    <button @click="switchTab('checks')" :class="tab==='checks' ? 'border-emerald-400 text-emerald-700' : 'border-transparent text-gray-400'" 
            class="px-4 py-2 border-b-2 font-semibold text-sm whitespace-nowrap inline-flex items-center gap-1">
        <?= icon('receipt') ?>
        <span>Чеки</span>
    </button>
    <button @click="switchTab('steps')" :class="tab==='steps' ? 'border-emerald-400 text-emerald-700' : 'border-transparent text-gray-400'" 
            class="px-4 py-2 border-b-2 font-semibold text-sm whitespace-nowrap inline-flex items-center gap-1 sm:hidden">
        <?= icon('steps') ?>
        <span>Шаги</span>
    </button>
</div>

<!-- Табы: мобильный dropdown с SVG -->
<div x-data="{ open: false }" class="sm:hidden relative mb-4">
    <button type="button"
            @click="open = !open"
            @keydown.escape="open = false"
            :aria-expanded="open ? 'true' : 'false'"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm flex items-center gap-2 bg-white text-left">
        <span class="shrink-0 text-emerald-600" x-html="tabIcon(tab)"></span>
        <span class="font-semibold text-gray-700" x-text="tabLabel(tab)"></span>
        <svg class="w-4 h-4 ml-auto text-gray-400 transition-transform"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open"
         @click.outside="open = false"
         x-transition.opacity.duration.150ms
         style="display:none"
         class="absolute z-30 mt-1 w-full bg-white rounded-xl shadow-xl border border-gray-100 py-1">
        <template x-for="item in tabItems" :key="item.v">
            <button type="button"
                    @click="tab = item.v; switchTab(item.v); open = false"
                    :class="tab === item.v ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700 hover:bg-gray-50'"
                    class="w-full px-4 py-2.5 text-sm flex items-center gap-2 text-left transition">
                <span class="shrink-0" x-html="item.i"></span>
                <span x-text="item.t"></span>
                <span x-show="tab === item.v" class="ml-auto text-emerald-500" aria-hidden="true">
    <?= icon('dot') ?>
</span>
            </button>
        </template>
    </div>
</div>

        <!-- ─── План питания ─── -->
        <div x-show="tab==='plan'">
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <h3 class="font-bold text-gray-800 mb-4" style="display:flex;align-items:center;gap:0.25em;"><?= icon('cart') ?>Рекомендованные продукты</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($recommendedProducts as $product): ?>
                    <span class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-sm"><?= e($product) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
$meals = [
    ['icon_name' => 'breakfast', 'title' => 'Завтрак', 'items' => [
        ['name' => 'Овсянка с ягодами',  'kbju' => '350 ккал | Б: 15г | Ж: 8г | У: 55г'],
        ['name' => 'Омлет с овощами',   'kbju' => '280 ккал | Б: 20г | Ж: 15г | У: 10г'],
        ['name' => 'Творог с фруктами', 'kbju' => '220 ккал | Б: 25г | Ж: 5г | У: 20г'],
    ]],
    ['icon_name' => 'dinner', 'title' => 'Обед', 'items' => [
        ['name' => 'Курица с гречкой', 'kbju' => '450 ккал | Б: 35г | Ж: 12г | У: 50г'],
        ['name' => 'Рыба с рисом',    'kbju' => '420 ккал | Б: 30г | Ж: 10г | У: 48г'],
    ]],
    ['icon_name' => 'dinner', 'title' => 'Ужин', 'items' => [
        ['name' => 'Рыба с овощами',  'kbju' => '300 ккал | Б: 30г | Ж: 10г | У: 25г'],
        ['name' => 'Тушёные овощи',   'kbju' => '180 ккал | Б: 5г | Ж: 7г | У: 25г'],
    ]],
    ['icon_name' => 'cookie', 'title' => 'Перекус', 'items' => [
        ['name' => 'Творог с яблоком', 'kbju' => '150 ккал | Б: 15г | Ж: 3г | У: 15г'],
        ['name' => 'Орехи (30г)',      'kbju' => '180 ккал | Б: 5г | Ж: 12г | У: 15г'],
    ]],
];
foreach ($meals as $meal): ?>
<div class="bg-white rounded-2xl shadow-md p-6">
    <h3 class="font-bold text-gray-800 mb-3 inline-flex items-center gap-2">
        <span class="shrink-0 text-emerald-600"><?= icon($meal['icon_name']) ?></span>
        <span><?= e($meal['title']) ?></span>
    </h3>
                    <div class="space-y-3">
                        <?php foreach ($meal['items'] as $item): ?>
                        <div class="bg-gray-50 rounded-xl p-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-700"><?= e($item['name']) ?></p>
                                <p class="text-xs text-gray-400 mt-0.5"><?= e($item['kbju']) ?></p>
                            </div>
                            <button @click="$dispatch('add-to-diary', { name: '<?= e($item['name']) ?>', kbju: '<?= e($item['kbju']) ?>' })"
                                class="shrink-0 px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 transition inline-flex items-center justify-center"
                                aria-label="Добавить в дневник">
                            <?= icon('plus') ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-blue-50 rounded-2xl p-4 mt-6 text-center">
                <p class="text-sm text-blue-600" style="display:flex;align-items:flex-start;gap:0.25em;"><?= icon('bulb') ?><span>Выберите по одному блюду из каждой категории — это даст примерно <b><?= $kbjuTargets['calories'] ?> ккал</b></span></p>
            </div>

            <!-- РЕКЛАМНЫЙ БЛОК В КОНЦЕ ВКЛАДКИ -->
            <?php 
                $adPlacement = 'dietolog_bottom'; 
                include __DIR__ . '/../components/ad-banner.php'; 
            ?>
        </div>

        <!-- ─── Дневник ─── -->
        <div x-show="tab==='diary'" style="display:none" x-data="dietDiary()" x-init="init()"
             @add-to-diary.window="handleQuickAdd($event.detail)">
            <!-- Навигация по дням -->
            <div class="flex items-center justify-between mb-4">
                <button @click="changeDay(-1)" class="px-3 py-2 rounded-xl bg-white shadow-sm text-gray-500 hover:bg-gray-50 transition text-sm">←</button>
                <div class="text-center">
                    <p class="font-bold text-gray-800 text-sm" x-text="dayLabel"></p>
                    <p class="text-xs text-gray-400" x-text="dayDateStr"></p>
                </div>
                <button @click="changeDay(1)" :disabled="isToday" class="px-3 py-2 rounded-xl bg-white shadow-sm text-gray-500 hover:bg-gray-50 transition text-sm disabled:opacity-40">→</button>
            </div>

            <!-- Сводка за день -->
            <div class="bg-white rounded-2xl shadow-md p-5 mb-4">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('openbook') ?>
    <span>Съедено за день</span>
</h3>
                <div class="grid grid-cols-4 gap-3">
                    <div class="text-center">
                        <p class="text-xl font-bold text-emerald-600" x-text="dayTotals.calories"></p>
                        <p class="text-[10px] text-gray-400">из <?= $kbjuTargets['calories'] ?></p>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-emerald-500 transition-all" :style="'width:' + Math.min(dayTotals.calories / <?= $kbjuTargets['calories'] ?> * 100, 100) + '%'"></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-blue-600" x-text="dayTotals.proteins + 'г'"></p>
                        <p class="text-[10px] text-gray-400">из <?= $kbjuTargets['proteins'] ?>г</p>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-blue-500 transition-all" :style="'width:' + Math.min(dayTotals.proteins / <?= $kbjuTargets['proteins'] ?> * 100, 100) + '%'"></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-amber-600" x-text="dayTotals.fats + 'г'"></p>
                        <p class="text-[10px] text-gray-400">из <?= $kbjuTargets['fats'] ?>г</p>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-amber-500 transition-all" :style="'width:' + Math.min(dayTotals.fats / <?= $kbjuTargets['fats'] ?> * 100, 100) + '%'"></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-violet-600" x-text="dayTotals.carbs + 'г'"></p>
                        <p class="text-[10px] text-gray-400">из <?= $kbjuTargets['carbs'] ?>г</p>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-violet-500 transition-all" :style="'width:' + Math.min(dayTotals.carbs / <?= $kbjuTargets['carbs'] ?> * 100, 100) + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Вода -->
            <div class="bg-white rounded-2xl shadow-md p-5 mb-4" x-show="isToday">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('water') ?>
    <span>Вода (норма: <?= round($waterGoalMl / 1000, 1) ?> л)</span>
</h3>
                <div class="flex gap-1.5 flex-wrap">
    <template x-for="i in waterGoalCount" :key="i">
        <button @click="setWater(i)"
                :class="i <= waterCount ? 'bg-blue-100 text-blue-600 scale-105' : 'bg-gray-100 text-gray-300'"
                class="text-blue-400 w-9 h-9 rounded-xl text-lg transition hover:scale-110 inline-flex items-center justify-center">
            <?= icon('water') ?>
        </button>
    </template>
</div>
                <p class="text-sm text-gray-500 mt-2"><span x-text="waterCount * 250"></span> / <?= $waterGoalMl ?> мл</p>
            </div>

            <!-- Быстрое добавление из недавних -->
            <div x-show="isToday && recentFoods.length > 0" class="bg-white rounded-2xl shadow-md p-5 mb-4" style="display:none">
                <h3 class="font-bold text-gray-800 mb-3 text-sm inline-flex items-center gap-1">
    <?= icon('lightning') ?>
    <span>Быстрое добавление</span>
</h3>
                <div class="flex flex-wrap gap-2">
                    <template x-for="food in recentFoods" :key="food.name">
                        <button @click="quickAdd(food)"
                                class="px-3 py-1.5 rounded-lg bg-gray-50 text-gray-600 text-xs font-semibold hover:bg-emerald-50 hover:text-emerald-700 transition">
                            + <span x-text="food.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Форма добавления -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-4" x-show="isToday">
                <div class="flex gap-2 mb-4">
                    <button @click="mealType='breakfast'" :class="mealType==='breakfast' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-50 text-gray-500'" 
        class="flex-1 py-2 rounded-xl text-sm font-semibold inline-flex items-center justify-center">
    <?= icon('breakfast') ?>
</button>
<button @click="mealType='lunch'" :class="mealType==='lunch' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-50 text-gray-500'" 
        class="flex-1 py-2 rounded-xl text-sm font-semibold inline-flex items-center justify-center">
    <?= icon('dinner') ?>
</button>
<button @click="mealType='dinner'" :class="mealType==='dinner' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-50 text-gray-500'" 
        class="flex-1 py-2 rounded-xl text-sm font-semibold inline-flex items-center justify-center">
    <?= icon('dinner') ?>
</button>
<button @click="mealType='snack'" :class="mealType==='snack' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-50 text-gray-500'" 
        class="flex-1 py-2 rounded-xl text-sm font-semibold inline-flex items-center justify-center">
    <?= icon('cookie') ?>
</button>
                </div>

                <form @submit.prevent="addEntry()" class="space-y-3">
                    <input type="text" x-model="foodName" placeholder="Название блюда (AI рассчитает КБЖУ)"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm" :readonly="!isToday">
                    <input type="text" x-model="portion" placeholder="Порция (например: 200г, 1 шт)"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    <button type="submit" :disabled="aiCalculating || !isToday"
        class="w-full py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
    <span x-show="!aiCalculating" class="inline-flex items-center gap-2">
        <?= icon('plus') ?>
        <span>Добавить</span>
    </span>
    <span x-show="aiCalculating" class="inline-flex items-center gap-2">
        <?= icon('hourglass') ?>
        <span>AI анализирует…</span>
    </span>
</button>
                </form>
            </div>

            <!-- Скелетоны -->
            <div x-show="entriesLoading" class="space-y-3" style="display:none">
                <div class="skeleton h-16 w-full"></div>
                <div class="skeleton h-16 w-full"></div>
                <div class="skeleton h-16 w-full"></div>
            </div>

            <!-- Пустое состояние -->
            <div x-show="!entriesLoading && dayEntries.length === 0" class="text-center py-10" style="display:none">
                <div class="text-5xl mb-3 inline-flex items-center justify-center"><?= icon('openbook') ?></div>
                <p class="text-gray-400 font-semibold" x-text="isToday ? 'Дневник пуст' : 'Нет записей за этот день'"></p>
                <p class="text-sm text-gray-400 mt-1" x-show="isToday">Добавьте первое блюдо в форме выше</p>
            </div>

            <!-- Записи -->
            <div x-show="!entriesLoading && dayEntries.length > 0" class="space-y-3 mb-4" style="display:none">
                <template x-for="entry in dayEntries" :key="entry.id">
                    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800 text-sm truncate" x-text="entry.food_name"></p>
                            <p class="text-xs text-gray-400" x-text="entry.portion"></p>
                        </div>
                        <div class="text-right ml-3 shrink-0">
                            <p class="text-sm font-bold text-emerald-600" x-text="entry.calories + ' ккал'"></p>
                            <p class="text-xs text-gray-400" x-text="'Б:' + entry.proteins + ' Ж:' + entry.fats + ' У:' + entry.carbs + 'г'"></p>
                        </div>
                        <button x-show="isToday" @click="removeEntry(entry.id)" 
        class="text-rose-400 hover:text-rose-600 ml-3 shrink-0 inline-flex items-center justify-center" 
        aria-label="Удалить">
    <?= icon('close') ?>
</button>
                    </div>
                </template>
            </div>

            <button x-show="isToday && dayEntries.length > 0" @click="finishDay()" 
        class="w-full py-3 rounded-xl bg-emerald-500 text-white font-bold hover:bg-emerald-600 transition sticky bottom-4 shadow-lg inline-flex items-center justify-center gap-1">
    <?= icon('check') ?>
    <span>Завершить день</span>
</button>
            
            <!-- РЕКЛАМНЫЙ БЛОК В КОНЦЕ ВКЛАДКИ -->
            <?php 
                $adPlacement = 'dietolog_bottom'; 
                include __DIR__ . '/../components/ad-banner.php'; 
            ?>
        </div>

        <!-- ─── Анализ ─── -->
        <div x-show="tab==='analysis'" style="display:none" x-data="dietAnalysis()" x-init="init()">
            <!-- Добавить вес -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <h3 class="font-bold text-gray-800 inline-flex items-center gap-1">
    <?= icon('diet') ?>
    <span>Добавить вес</span>
</h3>
                <form @submit.prevent="addWeight()" class="flex gap-3 flex-wrap">
                    <input type="date" x-model="weightDate" :max="today" class="flex-1 min-w-[150px] px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    <input type="number" x-model="weightValue" step="0.1" placeholder="Вес (кг)" class="w-32 px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition">Добавить</button>
                </form>
            </div>

            <!-- График веса -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800 inline-flex items-center gap-1">
    <?= icon('chart') ?>
    <span>График веса</span>
</h3>
<button @click="exportCSV()" x-show="weightEntries.length > 0" 
        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition inline-flex items-center gap-1">
    <?= icon('diskette') ?>
    <span>CSV</span>
</button>
                </div>
                <div x-show="weightEntries.length === 0" class="text-center py-8">
                    <div class="text-4xl mb-2" style="display:inline-flex;align-items:center;justify-content:center;line-height:1;"><?= icon('document') ?></div>
                    <p class="text-gray-400 text-sm">Нет данных. Добавьте первую запись выше.</p>
                </div>
                <canvas x-show="weightEntries.length > 0" id="weightChart" height="200" style="display:none"></canvas>
            </div>

            <!-- Круговая диаграмма БЖУ -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6" x-show="analysisDays.length > 0" style="display:none">
    <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-1">
    <?= icon('chart') ?>
    <span>Распределение БЖУ (среднее за неделю)</span>
</h3>
    <div class="flex flex-col sm:flex-row items-center gap-6">
    <div class="relative shrink-0" style="width:180px;height:180px;">
        <canvas id="macroChart"></canvas>
    </div>

    <!-- Легенда: фиксированная ширина, три колонки -->
    <div class="w-full sm:w-72 space-y-2 text-sm">
        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3">
            <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-blue-500 shrink-0"></span>
                Белки
            </span>
            <span class="font-bold text-right" x-text="macroAvg.proteins + 'г'"></span>
            <span class="text-gray-400 text-xs text-right w-10" x-text="macroPercent.proteins + '%'"></span>
        </div>
        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3">
            <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 shrink-0"></span>
                Жиры
            </span>
            <span class="font-bold text-right" x-text="macroAvg.fats + 'г'"></span>
            <span class="text-gray-400 text-xs text-right w-10" x-text="macroPercent.fats + '%'"></span>
        </div>
        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3">
            <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-violet-500 shrink-0"></span>
                Углеводы
            </span>
            <span class="font-bold text-right" x-text="macroAvg.carbs + 'г'"></span>
            <span class="text-gray-400 text-xs text-right w-10" x-text="macroPercent.carbs + '%'"></span>
        </div>
    </div>
</div>
            </div>

            <!-- Анализ по дням -->
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-1">
    <?= icon('chart') ?>
    <span>Анализ по дням</span>
</h3>
                <div x-show="analysisDays.length === 0" class="text-center py-8">
                    <div class="text-4xl mb-2" style="display:inline-flex;align-items:center;justify-content:center;line-height:1;"><?= icon('rest') ?></div>
                    <p class="text-gray-400 text-sm">Нет данных. Заполняйте дневник питания.</p>
                </div>
                <div x-show="analysisDays.length > 0" class="space-y-3" style="display:none">
                    <template x-for="day in analysisDays" :key="day.date">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-bold text-gray-700 text-sm" x-text="formatDate(day.date)"></p>
                                <span class="text-xs px-2 py-0.5 rounded-lg" :class="day.calories <= <?= $kbjuTargets['calories'] ?> * 1.1 ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'">
                                    <span x-text="day.calories > <?= $kbjuTargets['calories'] ?> ? 'превышение' : 'в норме'"></span>
                                </span>
                            </div>
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div class="bg-white rounded-lg p-2">
                                    <p class="text-lg font-bold text-emerald-600" x-text="day.calories"></p>
                                    <p class="text-[10px] text-gray-400">ккал</p>
                                </div>
                                <div class="bg-white rounded-lg p-2">
                                    <p class="text-lg font-bold text-blue-600" x-text="day.proteins + 'г'"></p>
                                    <p class="text-[10px] text-gray-400">белки</p>
                                </div>
                                <div class="bg-white rounded-lg p-2">
                                    <p class="text-lg font-bold text-amber-600" x-text="day.fats + 'г'"></p>
                                    <p class="text-[10px] text-gray-400">жиры</p>
                                </div>
                                <div class="bg-white rounded-lg p-2">
                                    <p class="text-lg font-bold text-violet-600" x-text="day.carbs + 'г'"></p>
                                    <p class="text-[10px] text-gray-400">углев.</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- AI-рекомендации -->
            <div class="bg-blue-50 rounded-2xl p-6">
                <h3 class="font-bold text-blue-700 mb-3 inline-flex items-center gap-1">
    <?= icon('advisor') ?>
    <span>Рекомендации AI</span>
</h3>
                <ul class="space-y-2 text-sm text-blue-600">
                    <template x-for="rec in aiRecommendations" :key="rec">
                        <li>• <span x-text="rec"></span></li>
                    </template>
                </ul>
            </div>
                
            <!-- РЕКЛАМНЫЙ БЛОК В КОНЦЕ ВКЛАДКИ -->
            <?php 
                $adPlacement = 'dietolog_bottom'; 
                include __DIR__ . '/../components/ad-banner.php'; 
            ?>
        </div>

        <!-- ─── Чеки ─── -->
        <div x-show="tab==='checks'" style="display:none" x-data="checkUploader()">
            <div class="bg-white rounded-2xl shadow-md p-6 mb-4">
                <h3 class="font-bold text-gray-800 mb-2 inline-flex items-center gap-1">
    <?= icon('receipt') ?>
    <span>Сканер чеков</span>
</h3>
                <p class="text-sm text-gray-500 mb-4">Загрузите фото чека — AI распознает продукты и предложит добавить в дневник</p>

                <form @submit.prevent="uploadCheck()" class="space-y-3">
                    <label class="drop-zone block border-2 border-dashed border-gray-200 rounded-xl p-8 text-center cursor-pointer hover:border-emerald-300 transition"
                           @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop($event)"
                           :class="dragOver ? 'dragover' : ''">
                        <input type="file" accept="image/*,.pdf" class="hidden" @change="handleFile($event)">
                        <span class="flex items-center justify-center mb-2 text-emerald-500">
    <?= icon('receipt', 'gc-icon-xl', 2) ?>
</span>
                        <span class="text-sm text-gray-500" x-text="fileName || 'Перетащите файл или нажмите для выбора'"></span>
                        <span class="block text-xs text-gray-400 mt-1">JPG, PNG, PDF · до 10 МБ</span>
                    </label>

                    <div x-show="previewUrl" style="display:none" class="relative">
                        <img :src="previewUrl" class="w-full max-h-64 object-contain rounded-xl" alt="Чек">
                        <button type="button" @click="clearFile()" 
        class="absolute top-2 right-2 bg-white/90 rounded-full p-1.5 shadow-md text-gray-500 hover:text-rose-500 transition inline-flex items-center justify-center w-8 h-8"
        aria-label="Удалить файл">
    <?= icon('close') ?>
</button>
                    </div>

                    <button type="submit" :disabled="!fileSelected || processing"
        class="w-full py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
    <span x-show="!processing" class="inline-flex items-center gap-2">
        <?= icon('advisor') ?>
        <span>Распознать чек</span>
    </span>
    <span x-show="processing" class="inline-flex items-center gap-2">
        <?= icon('hourglass') ?>
        <span>AI анализирует чек…</span>
    </span>
</button>
                </form>
            </div>

            <!-- Результаты -->
            <div x-show="recognizedProducts.length > 0" style="display:none" class="bg-white rounded-2xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800 inline-flex items-center gap-1">
    <?= icon('receipt') ?>
    <span>Распознанные продукты</span>
</h3>
                    <button @click="addAllToDiary()" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-bold hover:bg-emerald-600 transition">Добавить все</button>
                </div>
                <div class="space-y-2">
                    <template x-for="(product, idx) in recognizedProducts" :key="idx">
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg p-3 gap-3">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm text-gray-700 block truncate" x-text="product.name"></span>
                                <span class="text-xs text-gray-400" x-text="product.quantity + (product.calories ? ' · ' + product.calories + ' ккал' : '')"></span>
                            </div>
                            <button @click="addToDiary(product)" class="shrink-0 px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 transition">+ В дневник</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- РЕКЛАМНЫЙ БЛОК В КОНЦЕ ВКЛАДКИ -->
            <?php 
                $adPlacement = 'dietolog_bottom'; 
                include __DIR__ . '/../components/ad-banner.php'; 
            ?>
        </div>

        <!-- ─── Шагомер (только мобильные) ─── -->
        <div x-show="tab==='steps'" style="display:none" x-data="stepCounter()" x-init="init()">
            <div class="bg-white rounded-2xl shadow-md p-6 mb-4">
                <h3 class="font-bold text-gray-800 mb-4 text-center inline-flex items-center justify-center gap-1">
    <?= icon('steps') ?>
    <span>Шагомер</span>
</h3>

                <div class="text-center mb-6">
                    <div class="text-6xl font-extrabold text-emerald-600" x-text="steps.toLocaleString()"></div>
                    <p class="text-gray-500 mt-2">шагов сегодня</p>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-bold text-emerald-600" x-text="distance.toFixed(2)">0</div>
                        <div class="text-xs text-gray-500">км</div>
                    </div>
                    <div class="bg-orange-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-bold text-orange-600" x-text="caloriesBurned">0</div>
                        <div class="text-xs text-gray-500">ккал</div>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-bold text-blue-600" x-text="activeMinutes">0</div>
                        <div class="text-xs text-gray-500">мин</div>
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Прогресс к цели</span>
                        <span x-text="Math.round((steps / 10000) * 100) + '%'"></span>
                    </div>
                    <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all"
                             :style="'width: ' + Math.min((steps / 10000) * 100, 100) + '%'"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 text-center">Цель: 10 000 шагов</p>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <div class="flex items-center gap-2">
                        <span x-show="isTracking" class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span x-show="!isTracking" class="w-3 h-3 rounded-full bg-gray-300"></span>
                        <span class="text-sm font-semibold" x-text="statusText">Шагомер не запущен</span>
                    </div>
                </div>

                <button @click="toggleTracking()"
        :class="isTracking ? 'bg-rose-500 hover:bg-rose-600' : 'bg-emerald-500 hover:bg-emerald-600'"
        class="w-full py-3 rounded-xl text-white font-bold transition inline-flex items-center justify-center gap-2">
    <span x-show="!isTracking" class="inline-flex items-center gap-2">
        <?= icon('right') ?>
        <span>Начать</span>
    </span>
    <span x-show="isTracking" class="inline-flex items-center gap-2">
        <?= icon('close') ?>
        <span>Остановить</span>
    </span>
</button>

                <div class="mt-4 bg-blue-50 rounded-xl p-4">
                    <h4 class="font-bold text-blue-700 text-sm mb-2 inline-flex items-center gap-1">
    <?= icon('info') ?>
    <span>Как это работает</span>
</h4>
                    <ul class="space-y-1 text-xs text-blue-600">
                        <li>• Использует датчики смартфона (акселерометр)</li>
                        <li>• Работает в фоновом режиме</li>
                        <li>• Данные сохраняются автоматически каждые 30 сек</li>
                        <li>• Для работы нужен доступ к датчикам движения</li>
                    </ul>
                </div>
            </div>

            <!-- РЕКЛАМНЫЙ БЛОК В КОНЦЕ ВКЛАДКИ -->
            <?php 
                $adPlacement = 'dietolog_bottom'; 
                include __DIR__ . '/../components/ad-banner.php'; 
            ?>
        </div>
    </div>
<?php endif; ?>
</section>

<!-- Тост -->
<div x-data="{ toast: { show: false, message: '', type: 'info' } }"
     @toast.window="toast = { ...$event.detail, show: true }; clearTimeout(toast._t); toast._t = setTimeout(() => toast.show = false, 3500)"
     x-show="toast.show" x-transition x-cloak
     class="fixed bottom-4 right-4 z-[300] max-w-sm">
    <div :class="toast.type === 'success' ? 'bg-emerald-600' : toast.type === 'error' ? 'bg-rose-600' : 'bg-gray-800'"
         class="rounded-xl shadow-lg px-5 py-3 text-white text-sm font-semibold">
        <span x-text="toast.message"></span>
    </div>
</div>

<!-- Модальное окно анкеты (для режима редактирования из дашборда) -->
<?php if ($questionnaire): ?>
<div x-data="dietForm()" x-show="showQuestionnaire" x-transition x-cloak style="display:none"
     @open-questionnaire.window="openQuestionnaire($event.detail)"
     class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4"
     @click.self="closeQuestionnaire()"
     @keydown.escape.window="closeQuestionnaire()"
     role="dialog" aria-modal="true">

    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-2xl font-extrabold text-gray-800 inline-flex items-center gap-2">
    <template x-if="!isEditing">
        <span class="inline-flex items-center"><?= icon('openbook') ?></span>
    </template>
    <template x-if="isEditing">
        <span class="inline-flex items-center" style="display:none"><?= icon('pen') ?></span>
    </template>
    <span x-text="isEditing ? 'Редактирование анкеты' : 'Анкета диетолога'"></span>
</h3>
            <button @click="closeQuestionnaire()" 
        class="text-gray-400 hover:text-gray-600 inline-flex items-center justify-center w-8 h-8"
        aria-label="Закрыть">
    <?= icon('close') ?>
</button>
        </div>

        <div class="flex items-center gap-2 mb-2">
            <template x-for="i in 4" :key="i">
                <div class="flex-1 h-2 rounded-full transition-all"
                     :class="step >= i ? 'bg-emerald-500' : 'bg-gray-200'"></div>
            </template>
        </div>
        <p class="text-xs text-gray-400 text-center mb-6">Шаг <span x-text="step"></span> из 4</p>

        <form @submit.prevent="submitQuestionnaire">
            <input type="hidden" name="csrf_token" :value="window.__DIETOLOG__.csrfToken">

            <!-- Шаг 1 -->
            <div x-show="step === 1" class="space-y-4">
                <h4 class="font-bold text-gray-700">1. Базовые данные</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Пол *</label>
                        <select x-model="formData.gender" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            <option value="">Выберите…</option>
                            <option value="male">Мужской</option>
                            <option value="female">Женский</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Возраст *</label>
                        <input type="number" x-model.number="formData.age" min="14" max="100" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Рост (см) *</label>
                        <input type="number" x-model.number="formData.height" min="100" max="250" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Вес (кг) *</label>
                        <input type="number" x-model.number="formData.weight" min="30" max="300" step="0.1" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Целевой вес (кг)</label>
                        <input type="number" x-model.number="formData.target_weight" min="30" max="300" step="0.1" placeholder="Необязательно" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-gray-500 block mb-1">Уровень активности *</label>
                        <select x-model="formData.activity_level" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            <option value="sedentary">Сидячая работа, мало движения</option>
                            <option value="light">Лёгкая активность (1–2 тренировки/нед)</option>
                            <option value="moderate" selected>Умеренная (2–3 тренировки/нед)</option>
                            <option value="active">Активная (4–5 тренировки/нед)</option>
                            <option value="very_active">Очень активная (6–7 тренировки/нед)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Шаг 2 -->
            <div x-show="step === 2" style="display:none" class="space-y-4">
                <h4 class="font-bold text-gray-700">2. Здоровье и ограничения</h4>
                <div>
                    <label class="text-xs text-gray-500 block mb-2">Аллергии и непереносимости</label>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="gluten" class="rounded"> Глютен</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="lactose" class="rounded"> Лактоза</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="nuts" class="rounded"> Орехи</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="seafood" class="rounded"> Морепродукты</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="formData.allergies" value="eggs" class="rounded"> Яйца</label>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Заболевания</label>
                    <input type="text" x-model="formData.medical_conditions" placeholder="Диабет, гипертония…" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                </div>
            </div>

            <!-- Шаг 3: Результаты анализов -->
            <div x-show="step === 3" style="display:none" class="space-y-4">
                <h4 class="font-bold text-gray-700">3. Результаты анализов <span class="text-xs font-normal text-gray-400">(необязательно)</span></h4>
                <div class="bg-blue-50 rounded-xl p-3 mb-2">
                    <p class="text-xs text-blue-600 inline-flex items-start gap-1">
    <?= icon('info') ?>
    <span>Если у вас есть свежие результаты анализов, укажите их — AI учтёт их в рекомендациях. Все поля необязательны.</span>
</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Глюкоза (ммоль/л)</label>
                        <input type="number" x-model="formData.blood_sugar" step="0.1" placeholder="напр. 5.4" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Холестерин (ммоль/л)</label>
                        <input type="number" x-model="formData.cholesterol" step="0.1" placeholder="напр. 4.8" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Гемоглобин (г/л)</label>
                        <input type="number" x-model="formData.hemoglobin" placeholder="напр. 140" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Давление (систол.)</label>
                        <input type="number" x-model="formData.systolic_pressure" placeholder="напр. 120" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Давление (диастол.)</label>
                        <input type="number" x-model="formData.diastolic_pressure" placeholder="напр. 80" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    </div>
                </div>
            </div>

            <!-- Шаг 4 -->
            <div x-show="step === 4" style="display:none" class="space-y-4">
                <h4 class="font-bold text-gray-700">4. Предпочтения и подтверждение</h4>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Бюджет</label>
                    <select x-model="formData.budget" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        <option value="economy">Эконом</option>
                        <option value="medium" selected>Средний</option>
                        <option value="premium">Премиум</option>
                    </select>
                </div>
                <div class="bg-emerald-50 rounded-xl p-4 text-sm text-emerald-700 space-y-1">
                    <p><b>Цель:</b> <span x-text="goalTitle"></span></p>
                    <p><b>Пол:</b> <span x-text="formData.gender === 'male' ? 'Мужской' : 'Женский'"></span></p>
                    <p><b>Возраст:</b> <span x-text="formData.age"></span> лет</p>
                    <p><b>Вес:</b> <span x-text="formData.weight"></span> кг · <b>Рост:</b> <span x-text="formData.height"></span> см</p>
                    <p x-show="formData.target_weight"><b>Целевой вес:</b> <span x-text="formData.target_weight"></span> кг</p>
                    <p x-show="formData.allergies.length"><b>Аллергии:</b> <span x-text="formData.allergies.join(', ')"></span></p>
                    <p x-show="formData.blood_sugar"><b>Глюкоза:</b> <span x-text="formData.blood_sugar"></span> ммоль/л</p>
                    <p x-show="formData.cholesterol"><b>Холестерин:</b> <span x-text="formData.cholesterol"></span> ммоль/л</p>
                    <p x-show="formData.hemoglobin"><b>Гемоглобин:</b> <span x-text="formData.hemoglobin"></span> г/л</p>
                </div>
                <div class="bg-amber-50 rounded-xl p-3">
                    <p class="text-xs text-amber-600 inline-flex items-start gap-1">
    <?= icon('warning') ?>
    <span>Рекомендации не являются медицинской консультацией.</span>
</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" x-show="step > 1" @click="prevStep()"
                        class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm hover:bg-gray-200 transition">← Назад</button>
                <button type="button" x-show="step < 4" @click="nextStep()"
                        :disabled="!stepValid"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-50 disabled:cursor-not-allowed">Далее →</button>
                <button type="submit" x-show="step === 4" :disabled="submitting"
        class="flex-1 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
    <span x-show="!submitting" class="inline-flex items-center gap-2">
        <?= icon('diskette') ?>
        <span>Сохранить план питания</span>
    </span>
    <span x-show="submitting" class="inline-flex items-center gap-2">
        <?= icon('hourglass') ?>
        <span>Сохранение…</span>
    </span>
</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<script src="<?= url('assets/js/dietolog.js') ?>?v=<?= time() ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>