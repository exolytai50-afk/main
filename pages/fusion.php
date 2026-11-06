<?php
/**
 * GigaChef — Страница «Фьюжн-кухня»
 *
 * Функционал:
 *  1. AI Fusion Lab — генерация фьюжн-блюда из двух кухонь
 *  2. Вариатор рецептов — адаптация блюда под нужный стиль
 *  3. Сохранение в кулинарную книгу
 *
 * Все иконки — через icon('name') из includes/icons.php.
 * Кухни, сложности, время — из recipe_tags (tag_group).
 * Тип блюда — из recipe_categories.
 */

require_once dirname(__DIR__) . '/config/config.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user   = current_user();
$userId = (int)($user['id'] ?? 0);

// ============================================================
//  МЕТА-ТЕГИ
// ============================================================
$meta = function_exists('get_page_meta') ? get_page_meta('/fusion') : null;
if ($meta && function_exists('apply_meta')) {
    apply_meta($meta);
}

$pageTitle = $GLOBALS['pageTitle'] ?? 'Фьюжн-кухня — GigaChef';
$pageH1    = $GLOBALS['pageH1']    ?? 'Фьюжн-кухня';
$GLOBALS['pageH1'] = $pageH1;

// ============================================================
//  СТАТИСТИКА ПОЛЬЗОВАТЕЛЯ
// ============================================================
$fusionHistory = [];
$stats = [
    'total_generated' => 0,
    'total_saved'     => 0,
    'avg_rating'      => 0,
];

try {
    $db = db();

    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_generated,
            SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) as total_saved,
            AVG(rating) as avg_rating
        FROM fusion_recipes
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $statsRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($statsRow) {
        $stats['total_generated'] = (int)$statsRow['total_generated'];
        $stats['total_saved']     = (int)$statsRow['total_saved'];
        $stats['avg_rating']      = round((float)($statsRow['avg_rating'] ?? 0), 1);
    }

    $stmt = $db->prepare("
        SELECT id, title, cuisine_a, cuisine_b,
               cuisine_a_name, cuisine_b_name,
               rating, is_saved, image_url, created_at
        FROM fusion_recipes
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([':user_id' => $userId]);
    $fusionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Fusion page error: ' . $e->getMessage());
}

// ============================================================
//  КУХНИ
//
//  $cuisines       — уникальный список кухонь (slug → entry).
//                    Используется для итерации в модалке.
//  $cuisinesByKey  — «расширенный» индекс по ДВУМ ключам
//                    (slug и icon_svg), нужен только для поиска
//                    записи по fusion_recipes.cuisine_a/b.
// ============================================================
$cuisines      = [];   // уникальный список, для вывода
$cuisinesByKey = [];   // алиасы slug+icon_svg, для поиска

try {
    $rows = db_all(
        "SELECT id, slug, name, icon_svg
         FROM recipe_tags
         WHERE tag_group = 'cuisine' AND is_active = 1
         ORDER BY sort_order, name"
    );
    foreach ($rows as $r) {
        $entry = [
            'name' => $r['name'],
            'id'   => (int)$r['id'],
            'icon' => $r['icon_svg'] ?: $r['slug'],
        ];

        // Для итерации — только по slug
        $cuisines[$r['slug']] = $entry;

        // Для поиска — по трём ключам: slug, icon_svg, name.
        // Так находится запись независимо от того, что лежит
        // в fusion_recipes.cuisine_a/b.
        $cuisinesByKey[$r['slug']] = $entry;

        if (!empty($r['icon_svg']) && $r['icon_svg'] !== $r['slug']) {
            $cuisinesByKey[$r['icon_svg']] = $entry;
        }

        if (!empty($r['name']) && $r['name'] !== $r['slug']) {
            $cuisinesByKey[$r['name']] = $entry;
        }
    }
} catch (Throwable $e) {
    error_log('Fusion cuisines load error: ' . $e->getMessage());
}

// ============================================================
//  СЛОЖНОСТЬ — из recipe_tags (tag_group='difficulty')
// ============================================================
$difficultyTags = [];
try {
    $rows = db_all(
        "SELECT slug, name, icon_svg
         FROM recipe_tags
         WHERE tag_group = 'difficulty' AND is_active = 1
         ORDER BY sort_order"
    );
    if (is_array($rows)) {
        $difficultyTags = $rows;
    }
} catch (Throwable $e) {
    error_log('Fusion difficulty load error: ' . $e->getMessage());
}

// ============================================================
//  ТИП БЛЮДА — из recipe_categories
// ============================================================
$dishTypes = [];
try {
    $rows = db_all(
        "SELECT slug, name, icon_svg
         FROM recipe_categories
         WHERE is_active = 1
         ORDER BY sort_order"
    );
    if (is_array($rows)) {
        $dishTypes = $rows;
    }
} catch (Throwable $e) {
    error_log('Fusion dish types load error: ' . $e->getMessage());
}

// ============================================================
//  ИКОНКИ ДЛЯ JS-ШАБЛОНОВ
// ============================================================
$jsIcons = [
    'bludo'   => icon('rest'),
    'user'    => icon('user'),
    'clock'   => icon('clock'),
    'flame'   => icon('flame'),
    'save'    => icon('save'),
    'refresh' => icon('refresh'),
    'share'   => icon('share'),
    'check'   => icon('check'),
    'sparkle' => icon('sparkle'),
    'flask'   => icon('flask'),
    'warning' => icon('warning'),
    'info'    => icon('info'),
    'close'   => icon('close'),
    'cart'    => icon('cart'),
    'chef'    => icon('chef'),
    'chef_svg'=> icon('chef'),
    'bulb'    => icon('bulb'),
    'book'    => icon('book'),
    'trash'   => icon('trash'),
    'spell'   => icon('spell'),
];

// Иконки кухонь для JS-шаблона displayRecipe.
$cuisineIcons = [];
foreach ($cuisinesByKey as $key => $data) {
    // Используем стандартный размер 1.5em (как в HTML-слотах)
    $cuisineIcons[$key] = icon($data['icon'], 'gc-icon-xl');  
}

// ============================================================
//  ДАННЫЕ ДЛЯ ALPINE-КОМПОНЕНТОВ
//
//  Каждый список: slug → { name, icon }
//  Иконки БЕЗ цветовых классов — цвет наследуется от родителя
//  (text-gray-500 у контейнера → currentColor).
//
//  Для пунктов «Любой / Любая / Любое» используем иконку группы:
//  dishType → 'main', difficulty → 'diff-mid', cookTime → 'time'.
// ============================================================
$dishTypeLabels = [];
foreach ($dishTypes as $dt) {
    $dishTypeLabels[$dt['slug']] = [
        'name' => $dt['name'],
        'icon' => icon($dt['icon_svg'] ?: 'main', 'gc-icon-sm', 1),
    ];
}

$difficultyLabels = [];
foreach ($difficultyTags as $t) {
    $difficultyLabels[$t['slug']] = [
        'name' => $t['name'],
        'icon' => icon($t['icon_svg'] ?: 'diff-mid', 'gc-icon-sm', 1),
    ];
}
// Иконки для пустых значений «Любой / Любая / Любое»
$dishTypeAnyIcon   = icon('receipt',     'gc-icon-sm', 1);
$difficultyAnyIcon = icon('muscle', 'gc-icon-sm', 1);

// ============================================================
//  ИДЕИ ДЛЯ ВДОХНОВЕНИЯ
// ============================================================
$inspirations = [
    ['cuisine' => 'italian', 'title' => 'Итальянская + Японская',    'desc' => 'Пицца с васаби и лососем'],
    ['cuisine' => 'mexican', 'title' => 'Мексиканская + Корейская',  'desc' => 'Тако с кимчи и говядиной бульгоги'],
    ['cuisine' => 'thai',    'title' => 'Тайская + Французская',     'desc' => 'Том-ям с трюфельным маслом'],
    ['cuisine' => 'russian', 'title' => 'Русская + Китайская',       'desc' => 'Пельмени с соусом хойсин'],
    ['cuisine' => 'indian',  'title' => 'Индийская + Итальянская',   'desc' => 'Ризотто с карри и паниром'],
    ['cuisine' => 'french',  'title' => 'Французская + Мексиканская','desc' => 'Круассан с гуакамоле'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Кастомные стили -->
<style>
.fusion-gradient {
    background: linear-gradient(135deg,
        var(--color-primary, #fbbf24) 0%,
        var(--color-primary-dark, #f59e0b) 100%);
}
.mixing-animation { animation: mixing 1.5s ease-in-out infinite; }
@keyframes mixing {
    0%, 100% { transform: rotate(0deg); }
    25%      { transform: rotate(-10deg); }
    75%      { transform: rotate(10deg); }
}
.loading-spinner { display: none; vertical-align: middle; }
.loading-spinner.active { display: inline-flex; align-items: center; justify-content: center; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Тёмная тема: иконка белая */
html.theme-dark .cuisine-slot-icon svg {
    color: #f1f5f9 !important;
}

/* В выбранном состоянии (фон желтый) иконка должна быть контрастной */
.cuisine-selector.selected .cuisine-slot-icon svg {
    color: #b45309; /* Тёмно-коричневый на желтом фоне (светлая тема) */
}

html.theme-dark .cuisine-selector.selected .cuisine-slot-icon svg {
    color: #ffffff; /* Белая на желтом фоне (тёмная тема) */
}
/* ===== ЛОКАЛЬНЫЕ ИСПРАВЛЕНИЯ ДЛЯ СТРАНИЦЫ FUSION ===== */

/* Светлая тема: принудительно белый фон для карточек */
html:not(.theme-dark) #cuisineASlot,
html:not(.theme-dark) #cuisineBSlot {
    background: #fef3c7 !important; /* Светло-жёлтый для А */
    border-color: #fcd34d !important;
}
html:not(.theme-dark) #cuisineBSlot {
    background: #ffe4e6 !important; /* Светло-розовый для B */
    border-color: #fda4af !important;
}
html:not(.theme-dark) .cuisine-selector.selected {
    background: linear-gradient(135deg, #fcd34d, #fbbf24) !important;
    color: #78350f !important;
}

/* Тёмная тема: принудительно тёмный фон */
html.theme-dark #cuisineASlot,
html.theme-dark #cuisineBSlot {
    background: #1e293b !important;
    border-color: #334155 !important;
}
html.theme-dark .cuisine-selector.selected {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(249, 115, 22, 0.3)) !important;
    border-color: rgba(245, 158, 11, 0.5) !important;
    color: #fbbf24 !important;
}

/* Убираем тёмный фон у градиентов в светлой теме */
html:not(.theme-dark) .bg-gradient-to-br,
html:not(.theme-dark) .bg-gradient-to-r {
    background-image: linear-gradient(to bottom right, #fef3c7, #ffe4e6) !important;
    background-color: transparent !important;
}

html.theme-dark .cuisine-slot-icon svg {
    color: #f1f5f9 !important;
}

.cuisine-selector.selected .cuisine-slot-icon svg {
    color: #78350f !important;
}

html.theme-dark .cuisine-selector.selected .cuisine-slot-icon svg {
    color: #ffffff !important;
}
</style>

<?php
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<script>
window.__DISH_TYPE_LABELS__   = <?= json_encode($dishTypeLabels,  $jsonFlags) ?>;
window.__DIFFICULTY_LABELS__  = <?= json_encode($difficultyLabels, $jsonFlags) ?>;

window.__DISH_TYPE_ANY_ICON__   = <?= json_encode($dishTypeAnyIcon,   $jsonFlags) ?>;
window.__DIFFICULTY_ANY_ICON__  = <?= json_encode($difficultyAnyIcon, $jsonFlags) ?>;
</script>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8" x-data="fusionPage()">

    <!-- ============================================================
         HERO
         ============================================================ -->
    <div class="text-center mb-8" data-aos="fade-up">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-100 text-amber-800 shadow-sm mb-4">
            <span class="text-xl mixing-animation"><?= icon('flask') ?></span>
            <span class="text-xs font-bold tracking-wide">AI FUSION LAB</span>
        </div>

        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800"><?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500 max-w-2xl mx-auto">
            Смешивайте кухни мира и создавайте уникальные блюда с помощью искусственного интеллекта
        </p>

        <div class="grid grid-cols-3 gap-3 max-w-xl mx-auto mt-6">
            <div class="bg-white rounded-2xl p-4 shadow-md">
                <div class="text-2xl font-bold text-amber-600"><?= (int)$stats['total_generated'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Создано</div>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-md">
                <div class="text-2xl font-bold text-emerald-600"><?= (int)$stats['total_saved'] ?></div>
                <div class="text-xs text-gray-500 mt-1">В книгах</div>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-md">
                <div class="text-2xl font-bold text-yellow-600"><?= $stats['avg_rating'] ?: '—' ?></div>
                <div class="text-xs text-gray-500 mt-1">Оценка</div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         ПЕРЕКЛЮЧАТЕЛЬ РЕЖИМОВ
         ============================================================ -->
    <div class="bg-white rounded-2xl shadow-md p-2 mb-6 flex flex-col sm:flex-row gap-2" data-aos="fade-up">
        <!-- Вкладка 1 -->
        <button @click="tab = 'fusion'"
                :class="tab === 'fusion' ? 'bg-amber-100 text-amber-700 ring-2 ring-amber-300' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'"
                class="flex-1 py-3 px-4 rounded-xl font-bold text-sm transition inline-flex items-center justify-center gap-2">
            <?= icon('flask', 'gc-icon-sm') ?> Fusion-генератор
        </button>
        
        <!-- Вкладка 2 -->
        <button @click="tab = 'variator'"
                :class="tab === 'variator' ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-300' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'"
                class="flex-1 py-3 px-4 rounded-xl font-bold text-sm transition inline-flex items-center justify-center gap-2">
            <?= icon('refresh', 'gc-icon-sm') ?> Вариатор рецептов
        </button>
    </div>

    <!-- ============================================================
         РЕЖИМ 1: FUSION-ГЕНЕРАТОР
         ============================================================ -->
    <div x-show="tab === 'fusion'" x-transition>

        <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8 mb-8" data-aos="fade-up">
            <div class="flex items-center gap-2 mb-6">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <h2 class="text-xl font-extrabold text-gray-800">Лаборатория вкусов</h2>
            </div>

            <!-- ШАГ 1: КУХНИ -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                    <label class="text-sm font-semibold text-gray-600 inline-flex items-center gap-2">
                        <?= icon('globe') ?>
                        Шаг 1: Выберите две кухни
                    </label>
                    <button id="randomCuisines"
                            class="text-xs text-amber-600 hover:text-amber-800 font-semibold inline-flex items-center justify-center gap-1 py-1.5 px-3 rounded-lg bg-amber-50 hover:bg-amber-100 transition w-full sm:w-auto">
                        <?= icon('random', 'gc-icon-sm') ?> Случайно
                    </button>
                </div>

                <!-- Сетка кухонь -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center mb-4">
                    
                    <!-- Кухня А -->
                    <div id="cuisineASlot"
                         class="cuisine-selector bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-dashed border-amber-300 rounded-2xl p-4 text-center min-h-[120px] flex flex-col items-center justify-center cursor-pointer hover:shadow-md transition w-full">
                        <div class="cuisine-slot-icon mb-2">
                            <?= icon('chef', 'gc-icon-xl') ?>
                        </div>
                        <div class="cuisine-slot-label text-xs font-medium">Кухня A</div>
                    </div>

                    <!-- Плюс -->
                    <div class="hidden sm:flex items-center justify-center">
                        <div class="text-3xl font-bold text-amber-400 mixing-animation">+</div>
                    </div>

                    <!-- Кухня B -->
                    <div id="cuisineBSlot"
                         class="cuisine-selector bg-gradient-to-br from-rose-50 to-pink-50 border-2 border-dashed border-rose-300 rounded-2xl p-4 text-center min-h-[120px] flex flex-col items-center justify-center cursor-pointer hover:shadow-md transition w-full">
                        <div class="cuisine-slot-icon mb-2">
                            <?= icon('chef', 'gc-icon-xl') ?>
                        </div>
                        <div class="cuisine-slot-label text-xs font-medium">Кухня B</div>
                    </div>
                </div>

                <!-- Кнопка открытия модалки (возвращена на место) -->
                <button id="openCuisinePicker"
                        class="w-full py-3 border-2 border-dashed border-amber-300 rounded-2xl text-amber-700 hover:bg-amber-50 transition font-medium inline-flex items-center justify-center gap-2">
                    <?= icon('openbook') ?> Открыть выбор кухонь
                </button>
            </div>

            <!-- ШАГ 2: ПАРАМЕТРЫ -->
            <div class="mb-6">
                <label class="text-sm font-semibold text-gray-600 mb-3 block inline-flex items-center gap-2">
                    <?= icon('settings') ?>
                    Шаг 2: Параметры блюда
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- ТИП БЛЮДА -->
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-600 mb-2 inline-flex items-center gap-1">
                            <?= icon('receipt', 'gc-icon-xs', 1) ?>
                            <span>Тип блюда</span>
                        </label>

                        <button type="button"
                                @click.prevent="openDropdown = openDropdown === 'dishType' ? null : 'dishType'"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                            <span class="inline-flex items-center gap-2 truncate min-w-0">
                                <span class="shrink-0 inline-flex items-center"
                                      x-html="currentDishTypeIcon()"></span>
                                <span class="truncate text-gray-700"
                                      x-text="dishType === 'any' ? 'Любой' : (dishTypeLabels[dishType]?.name || 'Любой')"></span>
                            </span>
                            <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                        </button>

                        <div x-show="openDropdown === 'dishType'"
                             x-transition
                             @click.outside="openDropdown = null"
                             style="display:none"
                             class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                            <div @click="dishType = 'any'; openDropdown = null"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="dishTypeAnyIcon"></span>
                                <span class="text-gray-700">Любой</span>
                            </div>
                            <template x-for="(label, slug) in dishTypeLabels" :key="slug">
                                <div @click="dishType = slug; openDropdown = null"
                                     class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                    <span class="shrink-0 inline-flex items-center" x-html="label.icon"></span>
                                    <span class="text-gray-700" x-text="label.name"></span>
                                </div>
                            </template>
                        </div>
                        <input type="hidden" id="dishType" :value="dishType">
                    </div>

                    <!-- СЛОЖНОСТЬ -->
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-600 mb-2 inline-flex items-center gap-1">
                            <?= icon('muscle', 'gc-icon-xs', 1) ?>
                            <span>Сложность</span>
                        </label>
                        <button type="button"
                                @click.prevent="openDropdown = openDropdown === 'difficulty' ? null : 'difficulty'"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                            <span class="inline-flex items-center gap-2 truncate min-w-0">
                                <span class="shrink-0 inline-flex items-center"
                                      x-html="currentDifficultyIcon()"></span>
                                <span class="truncate text-gray-700"
                                      x-text="difficulty === 'any' ? 'Любая' : (difficultyLabels[difficulty]?.name || 'Любая')"></span>
                            </span>
                            <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                        </button>
                        <div x-show="openDropdown === 'difficulty'"
                             x-transition
                             @click.outside="openDropdown = null"
                             style="display:none"
                             class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                            <div @click="difficulty = 'any'; openDropdown = null"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="difficultyAnyIcon"></span>
                                <span class="text-gray-700">Любая</span>
                            </div>
                            <template x-for="(label, slug) in difficultyLabels" :key="slug">
                                <div @click="difficulty = slug; openDropdown = null"
                                     class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                    <span class="shrink-0 inline-flex items-center" x-html="label.icon"></span>
                                    <span class="text-gray-700" x-text="label.name"></span>
                                </div>
                            </template>
                        </div>
                        <input type="hidden" id="difficulty" :value="difficulty">
                    </div>
                </div>
            </div>

            <!-- ШАГ 2.5: СТИЛЬ БЛЮДА -->
            <div class="mb-6 mt-6" x-data="fusionTags()" x-init="init()">
                <label class="text-sm font-semibold text-gray-600 mb-3 block inline-flex items-center gap-2">
                    <?= icon('settings') ?>
                    Шаг 2.5: Стиль блюда (необязательно)
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <template x-for="grp in tagGroups" :key="grp.group">
                        <div class="relative">
                            <label class="block text-xs font-medium text-gray-600 mb-2 inline-flex items-center gap-1">
                                <span class="shrink-0 inline-flex items-center" x-html="grp.icon_svg_html || ''"></span>
                                <span x-text="grp.label"></span>
                            </label>
                            <button type="button"
                                    @click.prevent="toggleTagDropdown(grp.group)"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                                <span class="inline-flex items-center gap-2 truncate min-w-0">
                                    <span class="shrink-0 inline-flex items-center" x-html="currentIconHtml(grp.group)"></span>
                                    <span class="truncate text-gray-700"
                                          x-text="selectedTags[grp.group] ? selectedName(grp.group) : 'Не важно'"></span>
                                </span>
                                <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                            </button>
                            <div x-show="openTagDropdown === grp.group"
                                 x-transition
                                 @click.outside="openTagDropdown = null"
                                 style="display:none"
                                 class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                                <div @click="onGroupSelect(grp.group, '')"
                                     class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                    <span class="shrink-0 inline-flex items-center" x-html="grp.icon_svg_html || ''"></span>
                                    <span class="text-gray-700">Не важно</span>
                                </div>
                                <template x-for="t in grp.tags" :key="t.slug">
                                    <div @click="onGroupSelect(grp.group, t.slug)"
                                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                        <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                        <span class="text-gray-700" x-text="t.name"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ШАГ 3: ОПИСАНИЕ -->
            <div class="mb-6 mt-6">
                <label class="text-sm font-semibold text-gray-600 mb-3 block inline-flex items-center gap-2">
                    <?= icon('bulb') ?>
                    Шаг 3: Опишите вашу идею (необязательно)
                </label>
                <textarea id="fusionPrompt" rows="3"
                          placeholder="Например: что-то сытное, с морепродуктами, под белое вино..."
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-400 focus:border-transparent bg-gray-50 resize-none"></textarea>

                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="text-xs text-gray-500 font-medium py-1.5">Быстрые идеи:</span>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-amber-50 text-amber-700 rounded-full hover:bg-amber-100 transition inline-flex items-center gap-1" data-text="для романтического ужина"><?= icon('romantics') ?>Романтика</button>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-rose-50 text-rose-700 rounded-full hover:bg-rose-100 transition inline-flex items-center gap-1" data-text="для большой компании"><?= icon('company') ?>Компания</button>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-full hover:bg-emerald-100 transition inline-flex items-center gap-1" data-text="полезное и лёгкое"><?= icon('healthy') ?>ПП</button>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-orange-50 text-orange-700 rounded-full hover:bg-orange-100 transition inline-flex items-center gap-1" data-text="острое и пряное"><?= icon('spicy') ?>Острое</button>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full hover:bg-blue-100 transition inline-flex items-center gap-1" data-text="с морепродуктами"><?= icon('seafood') ?>Морепродукты</button>
                    <button class="quick-idea text-xs px-3 py-1.5 bg-red-50 text-red-700 rounded-full hover:bg-red-100 transition inline-flex items-center gap-1" data-text="вегетарианское"><?= icon('vegetarian') ?>Вегетарианское</button>
                </div>
            </div>

            <!-- Кнопка генерации -->
            <button id="generateBtn"
                    class="w-full py-4 bg-purple-300 text-amber-900 rounded-2xl font-bold text-lg shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <span class="loading-spinner"><?= icon('flask') ?></span>
                <span class="btn-text inline-flex items-center gap-2">
                    <span class="btn-label">Создать фьюжн-блюдо</span>
                    <?= icon('sparkle') ?>
                </span>
            </button>

            <div id="statusMessage" class="mt-4 text-center text-sm text-gray-600"></div>
        </div>

        <section id="resultSection" class="hidden mb-8"></section>

        <!-- ИДЕИ ДЛЯ ВДОХНОВЕНИЯ -->
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-extrabold text-gray-800 inline-flex items-center gap-2">
                    <?= icon('bulb') ?>Идеи для вдохновения
                </h2>
                <button id="refreshInspirations"
                        class="text-sm text-amber-600 hover:text-amber-800 font-medium inline-flex items-center gap-1">
                    <?= icon('refresh') ?>Обновить
                </button>
            </div>

            <div id="inspirationGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($inspirations as $idea): ?>
                    <div class="inspiration-card bg-white rounded-2xl p-5 shadow-md hover:shadow-lg transition cursor-pointer border border-gray-100"
                         data-title="<?= e($idea['title']) ?>">
                        <div class="mb-3 text-amber-500">
                            <?= icon($cuisinesByKey[$idea['cuisine']]['icon'] ?? 'globe', '', 2.5) ?>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-1"><?= e($idea['title']) ?></h3>
                        <p class="text-sm text-gray-500"><?= e($idea['desc']) ?></p>
                        <div class="mt-3 text-xs text-amber-600 font-medium inline-flex items-center gap-1">
                            Попробовать <?= icon('right', '', 1) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <!-- ============================================================
         РЕЖИМ 2: ВАРИАТОР РЕЦЕПТОВ
         ============================================================ -->
    <div x-show="tab === 'variator'" x-transition x-data="recipeVariator()">
        <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8 mb-8" data-aos="fade-up">
            <div class="flex items-center gap-2 mb-6">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <h2 class="text-xl font-extrabold text-gray-800">Адаптация блюда</h2>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                Введите название любимого блюда и выберите стиль — AI предложит адаптированный рецепт.
            </p>

            <div class="flex flex-col gap-3 mb-4">
                <!-- Поле ввода (растягивается) -->
                <textarea x-model="dish"
                          placeholder="Название блюда, например: Борщ"
                          rows="1"
                          @keydown.enter.prevent="generate()"
                          @input="
                              let el = $event.target;
                              el.style.height = 'auto'; 
                              el.style.height = (el.scrollHeight) + 'px';
                          "
                          class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300 focus:ring-2 focus:ring-emerald-200 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                          style="max-height: 150px; min-height: 46px;"
                ></textarea>

                <!-- Селектор и кнопка в одну строку -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <select x-model="mode"
                            class="px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <template x-for="m in modes" :key="m">
                            <option x-text="m" :value="m"></option>
                        </template>
                    </select>
                    <button @click="generate()" :disabled="loading"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="!loading" class="inline-flex items-center gap-2"><?= icon('sparkle') ?>Создать</span>
                        <span x-show="loading" class="inline-flex items-center gap-2"><?= icon('sparkle') ?>Создаю...</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <span class="text-xs text-gray-500 font-medium py-1.5">Попробуйте:</span>
                <button type="button" class="quick-dish text-xs px-3 py-1.5 bg-purple-50 text-purple-700 rounded-full hover:bg-purple-100 transition inline-flex items-center gap-1" data-dish="Паста Карбонара"><?= icon('pasta') ?>Карбонара</button>
                <button type="button" class="quick-dish text-xs px-3 py-1.5 bg-purple-50 text-purple-700 rounded-full hover:bg-purple-100 transition inline-flex items-center gap-1" data-dish="Борщ"><?= icon('soup') ?>Борщ</button>
                <button type="button" class="quick-dish text-xs px-3 py-1.5 bg-purple-50 text-purple-700 rounded-full hover:bg-purple-100 transition inline-flex items-center gap-1" data-dish="Пицца Маргарита"><?= icon('pizza') ?>Пицца</button>
                <button type="button" class="quick-dish text-xs px-3 py-1.5 bg-purple-50 text-purple-700 rounded-full hover:bg-purple-100 transition inline-flex items-center gap-1" data-dish="Тирамису"><?= icon('dessert') ?>Тирамису</button>
            </div>

            <p x-show="error" class="text-rose-500 text-sm mt-3" x-text="error"></p>

            <div x-show="recipe" x-transition class="mt-5 bg-white rounded-2xl border border-emerald-100 overflow-hidden fade-in">
                <div class="bg-emerald-50 p-5">
                    <h3 class="font-bold text-gray-800 text-lg" x-text="recipe?.title"></h3>
                    <p class="text-sm text-gray-600 mt-1" x-text="recipe?.description"></p>
                    <div class="flex flex-wrap gap-3 mt-3 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1"><?= icon('clock') ?><span x-text="(recipe?.prep_time || 0) + ' + ' + (recipe?.cook_time || 0) + ' мин'"></span></span>
                        <span class="inline-flex items-center gap-1"><?= icon('user') ?><span x-text="(recipe?.servings || 2) + ' порц.'"></span></span>
                        <span class="inline-flex items-center gap-1"><?= icon('flame') ?><span x-text="(recipe?.calories || 0) + ' ккал'"></span></span>
                        <span x-text="recipe?.difficulty || 'Средне'"></span>
                    </div>
                </div>

                <template x-if="recipe?.changes?.length">
                    <div class="p-5 border-b border-gray-50">
                        <h4 class="font-bold text-gray-700 mb-2 text-sm inline-flex items-center gap-2"><?= icon('sparkle') ?> Ключевые изменения</h4>
                        <ul class="space-y-1">
                            <template x-for="ch in recipe.changes" :key="ch">
                                <li class="text-sm text-emerald-700 flex gap-2">
                                    <span><?= icon('check', '', 1) ?></span>
                                    <span x-text="ch"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="p-5 border-b border-gray-50">
                    <h4 class="font-bold text-gray-700 mb-2 text-sm inline-flex items-center gap-2"><?= icon('cart') ?> Ингредиенты</h4>
                    <ul class="space-y-1">
                        <template x-for="(ing, i) in recipe?.ingredients || []" :key="i">
                            <li class="flex justify-between text-sm text-gray-600">
                                <span x-text="ing.name"></span>
                                <span class="text-gray-400" x-text="((ing.amount || '') + ' ' + (ing.unit || '')).trim()"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="p-5 border-b border-gray-50">
                    <h4 class="font-bold text-gray-700 mb-2 text-sm inline-flex items-center gap-2"><?= icon('chef') ?> Приготовление</h4>
                    <ol class="space-y-2">
                        <template x-for="(step, i) in recipe?.steps || []" :key="i">
                            <li class="flex gap-2 text-sm text-gray-600">
                                <span class="font-bold text-emerald-500" x-text="(i + 1) + '.'"></span>
                                <span x-text="step.instruction || step"></span>
                            </li>
                        </template>
                    </ol>
                </div>

                <div class="p-5 bg-gray-50">
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div><span class="font-bold text-amber-600" x-text="recipe?.calories || 0"></span> ккал</div>
                        <div><span class="font-bold text-emerald-600" x-text="recipe?.proteins || 0"></span> г белки</div>
                        <div><span class="font-bold text-rose-500" x-text="recipe?.fats || 0"></span> г жиры</div>
                        <div><span class="font-bold text-violet-600" x-text="recipe?.carbs || 0"></span> г угл.</div>
                    </div>
                </div>

                <div class="p-5 flex flex-wrap gap-3">
                    <button @click="saveVariatorToBook()" :disabled="saving"
                            class="flex-1 min-w-[200px] py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold text-sm disabled:opacity-60">
                        <span x-show="!saving" class="inline-flex items-center gap-2"><?= icon('save') ?> Сохранить в книгу</span>
                        <span x-show="saving">Сохраняем...</span>
                    </button>
                    <button @click="generate()"
                            class="px-4 py-2.5 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm inline-flex items-center gap-2"><?= icon('refresh') ?> Другой</button>
                </div>

                <p x-show="saved" class="px-5 pb-3 text-emerald-600 text-sm font-semibold text-center">
                    Сохранено! <a :href="savedUrl" class="underline">Открыть рецепт</a>
                </p>
                <p x-show="bookSaved" class="px-5 pb-3 text-emerald-600 text-sm font-semibold text-center"
                   x-text="bookSavedMessage"></p>
            </div>

            <!-- МОДАЛКА ВЫБОРА КНИГИ -->
            <div x-show="showBookModal" x-transition
                 class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                 @click.self="showBookModal = false">
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
                    <h3 class="text-lg font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('book') ?> В какую книгу?</h3>

                    <div class="space-y-2 max-h-[400px] overflow-y-auto mb-4">
                        <template x-if="books.length === 0">
                            <p class="text-gray-500 text-sm text-center py-4">
                                У вас ещё нет книг. Создайте первую ниже.
                            </p>
                        </template>
                        <template x-for="b in books" :key="b.id">
                            <button @click="saveToBook(b.id)"
                                    class="w-full text-left p-3 rounded-xl bg-gray-50 hover:bg-amber-50 transition border-2 border-transparent hover:border-amber-200">
                                <div class="font-semibold text-gray-800" x-text="b.title"></div>
                                <div class="text-xs text-gray-500 mt-1" x-text="(b.recipe_count || 0) + ' рецептов'"></div>
                            </button>
                        </template>
                    </div>

                    <div class="border-t border-gray-200 pt-4 space-y-2">
                        <input type="text" x-model="newBookTitle"
                               placeholder="Название новой книги"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm">
                        <button @click="createBookAndSave()" :disabled="creatingBook"
                                class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold text-sm disabled:opacity-60">
                            <span x-show="!creatingBook" class="inline-flex items-center gap-2"><?= icon('sparkle') ?> Создать книгу и сохранить</span>
                            <span x-show="creatingBook">Создаём...</span>
                        </button>
                    </div>

                    <button @click="showBookModal = false"
                            class="w-full mt-3 py-2 text-gray-500 text-sm hover:text-gray-700">
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         ИСТОРИЯ ГЕНЕРАЦИЙ
         ============================================================ -->
    <?php if (!empty($fusionHistory)): ?>
    <section class="mb-8" x-show="tab === 'fusion'" x-transition>
        <h2 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2">
            <?= icon('sparkle') ?> Ваши последние творения
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($fusionHistory as $item): ?>
                <a href="<?= url('fusion?id=' . (int)$item['id']) ?>"
                   class="history-card bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition cursor-pointer border border-gray-100 block">
                    <?php
                    // Один раз вычисляем всё для этой карточки.
                    // $cuisinesByKey — расширенный индекс по трём ключам
                    // (slug, icon_svg, name), поэтому находится и 'korean',
                    // и 'korejskaya', и 'Корейская'.
                    $keyA = $item['cuisine_a'] ?? '';
                    $keyB = $item['cuisine_b'] ?? '';

                    $entryA = $cuisinesByKey[$keyA] ?? null;
                    $entryB = $cuisinesByKey[$keyB] ?? null;

                    $iconA = $entryA['icon'] ?? 'globe';
                    $iconB = $entryB['icon'] ?? 'globe';

                    $nameA = $item['cuisine_a_name'] ?? ($entryA['name'] ?? $keyA);
                    $nameB = $item['cuisine_b_name'] ?? ($entryB['name'] ?? $keyB);
                    ?>
                    <?php if (!empty($item['image_url'])): ?>
                        <div class="h-40 bg-cover bg-center" style="background-image: url('<?= e($item['image_url']) ?>')"></div>
                    <?php else: ?>
                        <div class="h-40 fusion-gradient flex items-center justify-center text-white">
                            <span class="inline-flex items-center gap-2">
                                <?= icon($iconA, '', 2.5) ?>
                                <span class="text-2xl font-bold opacity-80">+</span>
                                <?= icon($iconB, '', 2.5) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="p-4">
                        <h3 class="font-semibold text-gray-800 mb-2 line-clamp-2">
                            <?= e($item['title']) ?>
                        </h3>

                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            <span class="inline-flex items-center gap-1">
                                <?= icon($iconA, '', 1) ?>
                                <span><?= e($nameA) ?></span>
                            </span>
                            <span>+</span>
                            <span class="inline-flex items-center gap-1">
                                <?= icon($iconB, '', 1) ?>
                                <span><?= e($nameB) ?></span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <?php if ($item['rating']): ?>
                                    <span class="text-yellow-500"><?= icon('star') ?></span>
                                    <span class="text-sm font-medium"><?= number_format((float)$item['rating'], 1) ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">Без оценки</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['is_saved']): ?>
                                <span class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full inline-flex items-center gap-1">
                                    <?= icon('check', '', 1) ?> Сохранено
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="text-xs text-gray-400 mt-2">
                            <?= date('d.m.Y H:i', strtotime($item['created_at'])) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</section>

<!-- ============================================================
     МОДАЛКА ВЫБОРА КУХНИ
     ============================================================ -->
<div id="cuisinePickerModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[80vh] overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-extrabold text-gray-800">Выберите кухню</h3>
            <button onclick="closeCuisinePicker()"
                    class="w-9 h-9 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-500">
                <?= icon('close') ?>
            </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php foreach ($cuisines as $key => $c): ?>
                    <button class="cuisine-option bg-white border-2 border-gray-200 rounded-2xl p-4 text-center hover:border-amber-400 hover:shadow-md transition"
                            data-cuisine="<?= e($key) ?>"
                            data-name="<?= e($c['name']) ?>">
                        <div class="mb-2 text-amber-500"><?= icon($c['icon'], '', 2.5) ?></div>
                        <div class="text-sm font-semibold text-gray-800"><?= e($c['name']) ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================
//  ДАННЫЕ ИЗ PHP
// ============================================================
const ICONS = <?= json_encode($jsIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const CUISINE_ICONS = <?= json_encode($cuisineIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

document.addEventListener('alpine:init', () => {

    Alpine.data('fusionPage', () => ({
        tab: 'fusion',

        // Выбранные значения (slug-и из БД)
        dishType: 'any',
        difficulty: 'any',

        // Какой dropdown сейчас открыт: null | 'dishType' | 'difficulty'
        openDropdown: null,

        // HTML-иконки, прокинутые из PHP через <script>
        get dishTypeLabels()   { return window.__DISH_TYPE_LABELS__   || {}; },
        get difficultyLabels() { return window.__DIFFICULTY_LABELS__  || {}; },

        // SVG для пустых значений «Любой / Любая»
        get dishTypeAnyIcon()   { return window.__DISH_TYPE_ANY_ICON__   || ''; },
        get difficultyAnyIcon() { return window.__DIFFICULTY_ANY_ICON__  || ''; },

        // Возвращает SVG иконки текущего выбранного значения
        // (для «any» — соответствующую иконку-заглушку)
        currentDishTypeIcon() {
            if (this.dishType === 'any') return this.dishTypeAnyIcon;
            return (this.dishTypeLabels[this.dishType] || {}).icon || this.dishTypeAnyIcon;
        },
        currentDifficultyIcon() {
            if (this.difficulty === 'any') return this.difficultyAnyIcon;
            return (this.difficultyLabels[this.difficulty] || {}).icon || this.difficultyAnyIcon;
        }
    }));

    Alpine.data('recipeVariator', () => ({
        icons: ICONS,
        modes: [
            'Веганская версия',
            'Кето версия',
            'Низкокалорийная версия',
            'Быстрая версия (до 30 мин)',
            'Праздничная версия',
            'Детская версия',
            'Острая версия',
            'Средиземноморский стиль',
            'Азиатский стиль'
        ],
        dish: '',
        mode: 'Веганская версия',
        recipe: null,
        loading: false,
        saving: false,
        saved: false,
        savedUrl: '',
        error: '',

        showBookModal: false,
        books: [],
        newBookTitle: '',
        creatingBook: false,
        bookSaved: false,
        bookSavedMessage: '',

        async loadBooks() {
            try {
                const res = await fetch('/api/cookbook_get.php?list=1');
                const data = await res.json();
                this.books = (data.ok && data.books) ? data.books : [];
            } catch (e) {
                console.error('loadBooks error:', e);
                this.books = [];
            }
        },

        async generate() {
            if (!this.dish.trim()) {
                this.error = 'Введите название блюда';
                return;
            }
            this.loading = true;
            this.error = '';
            this.recipe = null;
            this.saved = false;
            this.bookSaved = false;

            try {
                const response = await fetch('/api/ai_variator.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ dish: this.dish.trim(), mode: this.mode })
                });
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                }
                const result = await response.json();
                if (result.ok && result.recipe) {
                    this.recipe = result.recipe;
                } else if (result.recipe) {
                    this.recipe = result.recipe;
                } else {
                    throw new Error(result.error || 'Не удалось адаптировать рецепт');
                }
            } catch (e) {
                console.error('Variator error:', e);
                this.error = e.message || 'Ошибка при адаптации';
            } finally {
                this.loading = false;
            }
        },

        async saveVariatorToBook() {
            if (!this.recipe || this.saving) return;
            if (this._savedInProgress) return;
            this._savedInProgress = true;
            this.saving = true;
            this.error = '';
            try {
                await this.loadBooks();
                this.showBookModal = true;
            } catch (e) {
                this.error = e.message || 'Ошибка загрузки книг';
            } finally {
                this.saving = false;
                this._savedInProgress = false;
            }
        },

        async saveToBook(bookId) {
            try {
                const checkRes = await fetch('/api/recipe_check_duplicate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: this.recipe.title,
                        ingredients: this.recipe.ingredients || []
                    })
                });
                const checkData = await checkRes.json();
                if (checkData.duplicate) {
                    if (!confirm(
                        'Найден похожий рецепт: «' + checkData.duplicate.title + '»\n' +
                        'Совпадение ' + checkData.duplicate.similarity + '%.\n\n' +
                        'Всё равно добавить в книгу?'
                    )) {
                        return;
                    }
                }
                const res = await fetch('/api/cookbook_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_recipe',
                        book_id: bookId,
                        recipe: this.recipe
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    this.showBookModal = false;
                    this.bookSaved = true;
                    this.bookSavedMessage = data.existing_recipe
                        ? 'Рецепт добавлен! Найден похожий: ' + data.existing_recipe.title
                        : 'Рецепт добавлен в книгу!';
                } else {
                    this.error = data.error || 'Ошибка добавления';
                }
            } catch (e) {
                console.error('saveToBook error:', e);
                this.error = 'Ошибка соединения';
            }
        },

        async createBookAndSave() {
            const title = this.newBookTitle.trim();
            if (!title) return;
            this.creatingBook = true;
            try {
                const createRes = await fetch('/api/cookbook_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        title: title,
                        description: 'Создано из вариатора рецептов',
                        recipes: []
                    })
                });
                const createData = await createRes.json();
                if (!createData.ok) {
                    throw new Error(createData.error || 'Не удалось создать книгу');
                }
                this.newBookTitle = '';
                await this.saveToBook(createData.book_id);
            } catch (e) {
                console.error('createBookAndSave error:', e);
                this.error = e.message || 'Ошибка создания книги';
            } finally {
                this.creatingBook = false;
            }
        }
    }));

    Alpine.data('fusionTags', () => ({
        tagGroups: [],
        selectedTags: {},

        // Ключ открытого дропдауна: null | '<group>' | '<group>:<slug>'
        // Храним строкой вида `"cuisine:italian"`, чтобы одновременно
        // был открыт только один список во всей секции.
        openTagDropdown: null,

        async init() {
            try {
                const res  = await fetch('/api/tag_groups.php');
                const data = await res.json();
                this.tagGroups = data.groups || [];
            } catch (e) {
                console.error('fusionTags loadTagGroups error:', e);
                this.tagGroups = [];
            }
            window.fusionSelectedTags = () => ({ ...this.selectedTags });
        },

        // ─── Возвращает HTML иконки группы (для кнопки, когда «Не важно») ───
        groupIconHtml(group) {
            const g = this.tagGroups.find(x => x.group === group);
            return g && g.icon_svg_html ? g.icon_svg_html : '';
        },

        // ─── Возвращает HTML иконки выбранного тега группы ───
        selectedIconHtml(group) {
            const slug = this.selectedTags[group];
            if (!slug) return '';
            const g = this.tagGroups.find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t && t.icon_svg_html ? t.icon_svg_html : '';
        },

        // ─── Возвращает название выбранного тега группы ───
        selectedName(group) {
            const slug = this.selectedTags[group];
            if (!slug) return '';
            const g = this.tagGroups.find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t ? t.name : '';
        },

        // ─── Текущая иконка для кнопки: выбранного тега или самой группы ───
        currentIconHtml(group) {
            return this.selectedIconHtml(group) || this.groupIconHtml(group);
        },

        // ─── Открыть/закрыть дропдаун ───
        toggleTagDropdown(group) {
            this.openTagDropdown = (this.openTagDropdown === group) ? null : group;
        },

        // ─── Выбор тега ───
        onGroupSelect(group, slug) {
            if (!slug) {
                delete this.selectedTags[group];
            } else {
                this.selectedTags[group] = slug;
            }
            this.openTagDropdown = null;
        }
    }));
});

// ============================================================
//  FUSION-ГЕНЕРАТОР (vanilla JS)
// ============================================================
const state = {
    cuisineA: null,
    cuisineB: null,
    currentRecipe: null,
    isGenerating: false,
    activeSlot: null,
    // Для поиска по обоим ключам — используем расширенный индекс
    cuisines: <?= json_encode($cuisinesByKey, JSON_UNESCAPED_UNICODE) ?>
};

// ---------- КУХНИ ----------
function openCuisinePicker(slot) {
    state.activeSlot = slot;
    document.getElementById('cuisinePickerModal').classList.remove('hidden');
}
function closeCuisinePicker() {
    document.getElementById('cuisinePickerModal').classList.add('hidden');
    state.activeSlot = null;
}

document.getElementById('cuisineASlot').addEventListener('click', () => openCuisinePicker('A'));
document.getElementById('cuisineBSlot').addEventListener('click', () => openCuisinePicker('B'));
document.getElementById('openCuisinePicker').addEventListener('click', () => {
    if (!state.cuisineA) openCuisinePicker('A');
    else if (!state.cuisineB) openCuisinePicker('B');
    else openCuisinePicker('A');
});

document.querySelectorAll('.cuisine-option').forEach(btn => {
    btn.addEventListener('click', () => {
        const cuisine = btn.dataset.cuisine;
        const name    = btn.dataset.name;

        if (state.activeSlot === 'A') {
            state.cuisineA = { key: cuisine, name };
            updateCuisineSlot('A');
            if (!state.cuisineB) {
                setTimeout(() => { closeCuisinePicker(); setTimeout(() => openCuisinePicker('B'), 250); }, 200);
            } else {
                closeCuisinePicker();
            }
        } else if (state.activeSlot === 'B') {
            state.cuisineB = { key: cuisine, name };
            updateCuisineSlot('B');
            closeCuisinePicker();
        }
    });
});

// Запоминаем исходный HTML слотов при загрузке страницы
const initialSlotA = document.getElementById('cuisineASlot').innerHTML;
const initialSlotB = document.getElementById('cuisineBSlot').innerHTML;

function updateCuisineSlot(slot) {
    const cuisine = slot === 'A' ? state.cuisineA : state.cuisineB;
    const slotEl = document.getElementById(`cuisine${slot}Slot`);
    const initialHTML = slot === 'A' ? initialSlotA : initialSlotB;

    if (!cuisine) {
        // Возвращаем исходный HTML, который отрендерил PHP
        slotEl.innerHTML = initialHTML;
        slotEl.className = 'cuisine-selector bg-gradient-to-br ' +
            (slot === 'A' ? 'from-amber-50 to-orange-50 border-amber-300' : 'from-rose-50 to-pink-50 border-rose-300') +
            ' border-2 border-dashed rounded-2xl p-4 text-center min-h-[120px] flex flex-col items-center justify-center cursor-pointer hover:shadow-md transition w-full';
        return;
    }

    // Когда кухня выбрана:
    slotEl.innerHTML = `
        <div class="cuisine-slot-icon mb-2">
            ${CUISINE_ICONS[cuisine.key] || ''}
        </div>
        <div class="text-sm font-bold text-amber-900 dark:text-white">${cuisine.name}</div>
        <button class="mt-2 text-xs text-white/80 hover:text-white inline-flex items-center gap-1" onclick="event.stopPropagation(); clearCuisine('${slot}')">${ICONS.close || ''} Убрать</button>
    `;
    slotEl.className = 'cuisine-selector selected rounded-2xl p-4 text-center min-h-[120px] flex flex-col items-center justify-center bg-gradient-to-br from-amber-300 to-orange-300 text-amber-900 shadow-md w-full';

    // Принудительно убираем инлайн-стили у контейнера и SVG
    const iconWrapper = slotEl.querySelector('.gc-icon');
    if (iconWrapper) {
        iconWrapper.removeAttribute('style');
        iconWrapper.style.width = '70px';
        iconWrapper.style.height = '70px';
        iconWrapper.style.display = 'flex';
        iconWrapper.style.alignItems = 'center';
        iconWrapper.style.justifyContent = 'center';
    }

    const svg = slotEl.querySelector('svg');
    if (svg) {
        svg.removeAttribute('style');
        svg.setAttribute('width', '70');
        svg.setAttribute('height', '70');
        svg.style.width = '70px';
        svg.style.height = '70px';
        svg.style.display = 'block';
        svg.style.fill = 'currentColor';
    }
}

function clearCuisine(slot) {
    if (slot === 'A') state.cuisineA = null;
    if (slot === 'B') state.cuisineB = null;
    updateCuisineSlot(slot);
}

// Уникальный список кухонь для «Случайно» (без дублей)
const UNIQUE_CUISINES = <?= json_encode($cuisines, JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('randomCuisines').addEventListener('click', () => {
    const keys = Object.keys(UNIQUE_CUISINES);
    const shuffled = keys.sort(() => 0.5 - Math.random());
    state.cuisineA = { key: shuffled[0], name: UNIQUE_CUISINES[shuffled[0]].name };
    state.cuisineB = { key: shuffled[1], name: UNIQUE_CUISINES[shuffled[1]].name };
    updateCuisineSlot('A');
    updateCuisineSlot('B');
});

document.querySelectorAll('.quick-idea').forEach(btn => {
    btn.addEventListener('click', () => {
        const textarea = document.getElementById('fusionPrompt');
        const current = textarea.value.trim();
        const add = btn.dataset.text;
        textarea.value = current ? `${current}, ${add}` : add;
        textarea.focus();
    });
});

document.querySelectorAll('.inspiration-card').forEach(card => {
    card.addEventListener('click', () => {
        const title = card.dataset.title;
        const parts = title.split(' + ');
        if (parts.length === 2) {
            const cuisineA = findCuisineByName(parts[0].trim());
            const cuisineB = findCuisineByName(parts[1].trim());
            if (cuisineA) { state.cuisineA = cuisineA; updateCuisineSlot('A'); }
            if (cuisineB) { state.cuisineB = cuisineB; updateCuisineSlot('B'); }
            window.scrollTo({ top: 200, behavior: 'smooth' });
        }
    });
});

function findCuisineByName(name) {
    for (const [key, value] of Object.entries(state.cuisines)) {
        if (value.name === name) return { key, name: value.name };
    }
    return null;
}

// ---------- ГЕНЕРАЦИЯ ----------
async function generateFusion() {
    if (state.isGenerating) return;
    if (!state.cuisineA || !state.cuisineB) {
        showStatus(ICONS.warning + ' Выберите две кухни', 'warning');
        return;
    }

    state.isGenerating = true;
    const btn = document.getElementById('generateBtn');
    btn.disabled = true;
    btn.querySelector('.loading-spinner').classList.add('active');
    btn.querySelector('.btn-label').textContent = 'Создаю...';
    showStatus(ICONS.sparkle + ' Анализирую сочетание...', 'info');

    try {
        const payload = {
            cuisine_a:      state.cuisineA.key,
            cuisine_b:      state.cuisineB.key,
            cuisine_a_name: state.cuisineA.name,
            cuisine_b_name: state.cuisineB.name,
            dish_type:      document.getElementById('dishType').value,
            difficulty:     document.getElementById('difficulty').value,
            // Время готовки теперь выбирается в Шаге 2.5 (группа тегов `time`).
            // Забираем выбранный slug из общего объекта tags, если он есть.
            cook_time: (function () {
                const t = (typeof window.fusionSelectedTags === 'function')
                    ? window.fusionSelectedTags()
                    : {};
                return t.time || 'any';
            })(),
            prompt:         document.getElementById('fusionPrompt').value.trim(),
            tags: (typeof window.fusionSelectedTags === 'function')
                ? window.fusionSelectedTags()
                : {}
        };

        showStatus(ICONS.flask + ' Смешиваю вкусы...', 'info');

        const response = await fetch('/api/fusion_generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка генерации');

        state.currentRecipe = data.recipe;
        showStatus(ICONS.check + ' Блюдо готово!', 'success');
        displayRecipe(data.recipe);
        document.getElementById('resultSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        console.error(error);
        showStatus(ICONS.close + ' ' + error.message, 'error');
    } finally {
        state.isGenerating = false;
        btn.disabled = false;
        btn.querySelector('.loading-spinner').classList.remove('active');
        btn.querySelector('.btn-label').textContent = 'Создать фьюжн-блюдо';
    }
}

function showStatus(message, type = 'info') {
    const status = document.getElementById('statusMessage');
    const colors = {
        info: 'text-blue-600',
        success: 'text-emerald-600',
        warning: 'text-amber-600',
        error: 'text-rose-600'
    };
    status.className = `mt-4 flex items-center justify-center gap-2 text-sm font-medium ${colors[type] || colors.info}`;
    status.innerHTML = message;
}

// ---------- ОТОБРАЖЕНИЕ FUSION-РЕЦЕПТА ----------
function displayRecipe(recipe) {
    const section = document.getElementById('resultSection');

    const iconA = CUISINE_ICONS[recipe.cuisine_a] || '';
    const iconB = CUISINE_ICONS[recipe.cuisine_b] || '';

    section.innerHTML = `
        <div class="bg-white rounded-3xl shadow-md overflow-hidden fade-in">
            <div class="bg-gradient-to-r from-amber-300 to-orange-300 p-8 text-amber-900 relative">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="px-3 py-1 bg-white/40 rounded-full text-xs font-semibold inline-flex items-center gap-2">
                        ${iconA} ${escapeHtml(recipe.cuisine_a_name)}
                        +
                        ${iconB} ${escapeHtml(recipe.cuisine_b_name)}
                    </span>
                    <span class="px-3 py-1 bg-white/40 rounded-full text-xs font-semibold">
                        ${getDishTypeLabel(recipe.dish_type)}
                    </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-extrabold mb-2">${escapeHtml(recipe.title)}</h2>
                <p class="text-amber-900/90 leading-relaxed">${escapeHtml(recipe.description)}</p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
                    <div class="bg-white/40 rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                        <div class="mb-1 flex items-center justify-center leading-none">${ICONS.clock}</div>
                        <div class="text-xs opacity-70">Время</div>
                        <div class="font-bold">${recipe.cook_time || '—'} мин</div>
                    </div>
                    <div class="bg-white/40 rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                        <div class="mb-1 flex items-center justify-center leading-none">${ICONS.user}</div>
                        <div class="text-xs opacity-70">Порции</div>
                        <div class="font-bold">${recipe.servings || 2}</div>
                    </div>
                    <div class="bg-white/40 rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                        <div class="mb-1 flex items-center justify-center leading-none">${getDifficultyIcon(recipe.difficulty)}</div>
                        <div class="text-xs opacity-70">Сложность</div>
                        <div class="font-bold">${getDifficultyLabel(recipe.difficulty)}</div>
                    </div>
                    <div class="bg-white/40 rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                        <div class="mb-1 flex items-center justify-center leading-none">${ICONS.flame}</div>
                        <div class="text-xs opacity-70">Калории</div>
                        <div class="font-bold">${recipe.calories || '—'}</div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-b border-gray-100 flex flex-wrap gap-3">
                <button onclick="saveFusionToBook()" class="flex-1 min-w-[150px] py-3 bg-emerald-500 text-white rounded-xl font-semibold hover:bg-emerald-600 transition inline-flex items-center justify-center gap-2">
                    ${ICONS.save} Сохранить в книгу
                </button>
                <button onclick="regenerate()" class="py-3 px-4 bg-amber-100 text-amber-700 rounded-xl font-semibold hover:bg-amber-200 transition inline-flex items-center gap-2">
                    ${ICONS.refresh} Другой
                </button>
                <button onclick="shareRecipe()" class="py-3 px-4 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition inline-flex items-center gap-2">
                    ${ICONS.share} Поделиться
                </button>
            </div>

            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2">${ICONS.cart} Ингредиенты</h3>
                    <ul class="space-y-2">
                        ${(recipe.ingredients || []).map(ing => `
                            <li class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl">
                                <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold flex-shrink-0">${ICONS.check}</span>
                                <div class="flex-1 text-sm text-gray-800">
                                    <span class="font-semibold">${escapeHtml(ing.name || ing)}</span>
                                    ${ing.amount ? `<span class="text-gray-500 ml-2">— ${escapeHtml(ing.amount)}</span>` : ''}
                                </div>
                            </li>
                        `).join('')}
                    </ul>
                </div>

                <div>
                    <h3 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2">${ICONS.chef} Приготовление</h3>
                    <ol class="space-y-3">
                        ${(recipe.steps || []).map((step, i) => `
                            <li class="flex gap-3 p-3 bg-gray-50 rounded-xl">
                                <span class="w-7 h-7 rounded-full bg-gradient-to-br from-amber-300 to-orange-300 text-amber-900 flex items-center justify-center text-sm font-bold flex-shrink-0">${i + 1}</span>
                                <p class="text-sm text-gray-700 leading-relaxed pt-1">${escapeHtml(step.instruction || step)}</p>
                            </li>
                        `).join('')}
                    </ol>
                </div>

                ${recipe.tips && recipe.tips.length ? `
                <div class="lg:col-span-2">
                    <h3 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2">${ICONS.bulb} Советы шефа</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        ${recipe.tips.map(tip => `
                            <div class="p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                                <p class="text-sm text-gray-700">${escapeHtml(tip)}</p>
                            </div>
                        `).join('')}
                    </div>
                </div>` : ''}

                ${recipe.serving_suggestion ? `
                <div class="lg:col-span-2">
                    <div class="p-5 bg-amber-50 rounded-2xl border border-amber-200">
                        <div class="flex items-start gap-3">
                            <span>${ICONS.bludo}</span>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Подача</h4>
                                <p class="text-sm text-gray-700">${escapeHtml(recipe.serving_suggestion)}</p>
                            </div>
                        </div>
                    </div>
                </div>` : ''}
            </div>
        </div>
    `;
    section.classList.remove('hidden');
}

// ---------- СОХРАНЕНИЕ FUSION-РЕЦЕПТА ----------
async function saveFusionToBook() {
    if (!state.currentRecipe) return;
    try {
        const saveRes = await fetch('/api/fusion_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: state.currentRecipe.id })
        });
        await saveRes.json();
    } catch (e) {}

    let books = [];
    try {
        const res = await fetch('/api/cookbook_get.php?list=1');
        const data = await res.json();
        if (data.ok && data.books) books = data.books;
    } catch (e) {}

    showSaveToBookModal(books);
}

function showSaveToBookModal(books) {
    const modalHtml = `
        <div id="saveToBookModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
                <h3 class="text-lg font-extrabold text-gray-800 mb-4 inline-flex items-center gap-2">${ICONS.book} В какую книгу?</h3>
                <div class="space-y-2 max-h-[400px] overflow-y-auto mb-4">
                    ${books.length === 0 ? '<p class="text-gray-500 text-sm text-center py-4">У вас ещё нет книг</p>' :
                        books.map(b => `
                            <button onclick="saveToBook(${b.id})" class="w-full text-left p-3 rounded-xl bg-gray-50 hover:bg-amber-50 transition border-2 border-transparent hover:border-amber-200">
                                <div class="font-semibold text-gray-800">${escapeHtml(b.title)}</div>
                                <div class="text-xs text-gray-500 mt-1">${b.recipe_count || 0} рецептов</div>
                            </button>
                        `).join('')}
                </div>
                <div class="border-t border-gray-200 pt-4 space-y-2">
                    <input type="text" id="newBookTitle" placeholder="Название новой книги" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm">
                    <button onclick="createNewBookAndSave()" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold text-sm inline-flex items-center justify-center gap-2">
                        ${ICONS.sparkle} Создать книгу и сохранить
                    </button>
                </div>
                <button onclick="document.getElementById('saveToBookModal').remove()" class="w-full mt-3 py-2 text-gray-500 text-sm hover:text-gray-700">Отмена</button>
            </div>
        </div>
    `;
    const old = document.getElementById('saveToBookModal');
    if (old) old.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveToBook(bookId) {
    try {
        const res = await fetch('/api/cookbook_manage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_recipe',
                book_id: bookId,
                recipe: state.currentRecipe
            })
        });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('saveToBookModal')?.remove();
            showStatus(
                ICONS.check + ' ' + (data.existing_recipe
                    ? 'Сохранено! Найден похожий: ' + data.existing_recipe.title
                    : 'Рецепт добавлен в книгу!'),
                'success'
            );
        } else {
            showStatus(ICONS.close + ' ' + data.error, 'error');
        }
    } catch (e) {
        showStatus(ICONS.close + ' Ошибка сохранения', 'error');
    }
}

async function createNewBookAndSave() {
    const titleInput = document.getElementById('newBookTitle');
    const title = titleInput?.value.trim();
    if (!title) { titleInput.focus(); return; }
    try {
        const createRes = await fetch('/api/cookbook_manage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', title, description: 'Создано из фьюжн-лаборатории', recipes: [] })
        });
        const createData = await createRes.json();
        if (!createData.ok) { showStatus(ICONS.close + ' ' + createData.error, 'error'); return; }
        await saveToBook(createData.book_id);
    } catch (e) { showStatus(ICONS.close + ' Ошибка создания книги', 'error'); }
}

function regenerate() {
    document.getElementById('resultSection').classList.add('hidden');
    generateFusion();
}

function shareRecipe() {
    if (!state.currentRecipe) return;
    const text = `${state.currentRecipe.title}\n\n${state.currentRecipe.description}`;
    if (navigator.share) {
        navigator.share({ title: state.currentRecipe.title, text, url: window.location.href });
    } else {
        navigator.clipboard.writeText(text);
        showStatus(ICONS.check + ' Скопировано', 'success');
    }
}

// ---------- УТИЛИТЫ ----------
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
function getDishTypeLabel(type) {
    const labels = {
        'appetizer': 'Закуска', 'soup': 'Суп', 'main': 'Основное',
        'dessert': 'Десерт', 'drink': 'Напиток', 'snack': 'Снек'
    };
    return labels[type] || 'Блюдо';
}
function getDifficultyIcon(d) {
    const color = {
        'easy': 'text-emerald-500',
        'medium': 'text-amber-500',
        'hard': 'text-rose-500'
    }[d] || 'text-amber-500';
    return `<span class="${color}">${ICONS.flame}</span>`;
}
function getDifficultyLabel(d) {
    return { 'easy': 'Просто', 'medium': 'Средне', 'hard': 'Сложно' }[d] || 'Средне';
}

// ---------- ИНИЦИАЛИЗАЦИЯ (Безопасная) ----------

// 1. Кнопка "Обновить идеи"
const btnRefresh = document.getElementById('refreshInspirations');
if (btnRefresh) {
    btnRefresh.addEventListener('click', () => {
        const grid = document.getElementById('inspirationGrid');
        if (!grid) return;
        grid.style.opacity = '0.5';
        setTimeout(() => {
            grid.style.opacity = '1';
            const cards = Array.from(grid.children);
            cards.sort(() => 0.5 - Math.random());
            cards.forEach(card => grid.appendChild(card));
        }, 300);
    });
}

// 2. Кнопка "Создать фьюжн-блюдо"
const btnGenerate = document.getElementById('generateBtn');
if (btnGenerate) {
    btnGenerate.addEventListener('click', generateFusion);
}

// 3. Ctrl+Enter в поле ввода
const promptArea = document.getElementById('fusionPrompt');
if (promptArea) {
    promptArea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.ctrlKey) generateFusion();
    });
}

// 4. Закрытие модалки выбора кухни по клику на фон
const modalCuisine = document.getElementById('cuisinePickerModal');
if (modalCuisine) {
    modalCuisine.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCuisinePicker();
    });
}

// 5. Быстрые блюда (в режиме "Вариатор")
document.querySelectorAll('.quick-dish').forEach(btn => {
    btn.addEventListener('click', () => {
        const dishInput = document.querySelector('[x-data="recipeVariator()"] input[x-model="dish"]');
        if (dishInput) {
            dishInput.value = btn.dataset.dish;
            dishInput.dispatchEvent(new Event('input'));
            dishInput.focus();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>