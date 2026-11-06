<?php
require_once __DIR__ . '/../config/config.php';
$inlineAdHtml = get_ad_code('feed_inline');

$meta = get_page_meta('/search');
if ($meta) apply_meta($meta);

$pageTitle = $GLOBALS['pageTitle'] ?? 'Поиск рецептов — GigaChef';
$pageH1    = $GLOBALS['pageH1']    ?? 'Поиск рецептов';
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageH1']    = $pageH1;

$initialPage = max(1, (int)($_GET['page'] ?? 1));

// ============================================================
//  ИКОНКИ ДЛЯ JS-ШАБЛОНОВ
//  Прокидываем готовый HTML от icon() — JS вставит через x-html.
// ============================================================
$jsIcons = [
    'search'    => icon('search',   'gc-icon-sm', 1),
    'ai'        => icon('ai',       'gc-icon-sm', 1),
    'heart'     => icon('heart',    'gc-icon-sm', 1),
    'clock'     => icon('clock',    'gc-icon-sm', 1),
    'dinner'    => icon('dinner',   'gc-icon-sm', 1),
    'flame'     => icon('flame',    'gc-icon-sm', 1),
    'hourglass' => icon('hourglass','gc-icon-sm', 1),
    'left'      => icon('left',     'gc-icon-sm', 1),
    'right'     => icon('right',    'gc-icon-sm', 1),
    'star'      => icon('star',     'gc-icon-sm', 1),
    'chef'      => icon('chef',     'gc-icon-sm', 1),
    'search_big'=> icon('search',   'gc-icon-3xl', 1),
    'chef_big'  => icon('chef',     'gc-icon-3xl', 1),
];

// Иконки сложности (для кнопки и для пунктов списка)
$difficultyIcons = [
    ''       => icon('sliders',   'gc-icon-sm', 1),
    'easy'   => icon('diff-easy', 'gc-icon-sm', 1),
    'medium' => icon('diff-mid',  'gc-icon-sm', 1),
    'hard'   => icon('diff-hard', 'gc-icon-sm', 1),
    'master' => icon('diff-hard', 'gc-icon-sm', 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<script>
window.__SEARCH_ICONS__ = <?= json_encode($jsIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__SEARCH_DIFFICULTY_ICONS__ = <?= json_encode($difficultyIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__INLINE_AD_HTML__ = <?= json_encode($inlineAdHtml ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8" x-data="searchPage()" x-init="init()">

    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 inline-flex items-center gap-2">
        <?= icon('search') ?>
        <span><?= e($pageH1) ?></span>
    </h1>

    <!-- ═══ ФИЛЬТРЫ ═══ -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6 relative z-40" data-aos="fade-up">

        <!-- Строка 1: поиск + сортировка -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Поиск</label>
                <input type="text" x-model="q" @input.debounce.400ms="search()"
                       placeholder="Название рецепта, ингредиент..."
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Сортировка</label>
                <select x-model="sort" @change="search()"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none">
                    <option value="popular">Популярные</option>
                    <option value="new">Новые</option>
                    <option value="calories_asc">Калории ↑</option>
                    <option value="calories_desc">Калории ↓</option>
                    <option value="time_asc">Быстрые</option>
                </select>
            </div>
        </div>

        <!-- Строка 2: 4 кастомных dropdown -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            <!-- КАТЕГОРИЯ -->
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Категория</label>
                <button type="button"
                        @click.prevent="openDropdown = openDropdown === 'category' ? null : 'category'"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2 truncate min-w-0">
                        <span class="shrink-0 inline-flex items-center"
                              x-html="currentCategoryIcon()"></span>
                        <span class="truncate text-gray-700"
                              x-text="category ? (categories.find(c => c.slug === category)?.name || 'Категория') : 'Все категории'"></span>
                    </span>
                    <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                </button>
                <div x-show="openDropdown === 'category'"
                     x-transition
                     @click.outside="openDropdown = null"
                     style="display:none"
                     class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                    <div @click="category = ''; openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('list', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Все категории</span>
                    </div>
                    <template x-for="c in categories" :key="c.slug">
                        <div @click="category = c.slug; openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="c.icon_svg_html || ''"></span>
                            <span class="text-gray-700" x-text="c.name"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- КУХНЯ -->
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Кухня</label>
                <button type="button"
                        @click.prevent="openDropdown = openDropdown === 'cuisine' ? null : 'cuisine'"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2 truncate min-w-0">
                        <span class="shrink-0 inline-flex items-center"
                              x-html="currentGroupIcon('cuisine')"></span>
                        <span class="truncate text-gray-700"
                              x-text="filterCuisine ? (getTagName('cuisine', filterCuisine) || 'Кухня') : 'Любая кухня'"></span>
                    </span>
                    <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                </button>
                <div x-show="openDropdown === 'cuisine'"
                     x-transition
                     @click.outside="openDropdown = null"
                     style="display:none"
                     class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                    <div @click="filterCuisine = ''; onGroupSelect('cuisine', ''); openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('cuisine')"></span>
                        <span class="text-gray-700">Любая кухня</span>
                    </div>
                    <template x-for="t in getGroupTags('cuisine')" :key="t.slug">
                        <div @click="filterCuisine = t.slug; onGroupSelect('cuisine', t.slug); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                            <span class="text-gray-700" x-text="t.name"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ТИП ПИТАНИЯ -->
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Тип питания</label>
                <button type="button"
                        @click.prevent="openDropdown = openDropdown === 'diet' ? null : 'diet'"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2 truncate min-w-0">
                        <span class="shrink-0 inline-flex items-center"
                              x-html="currentGroupIcon('diet')"></span>
                        <span class="truncate text-gray-700"
                              x-text="filterDiet ? (getTagName('diet', filterDiet) || 'Тип питания') : 'Любой'"></span>
                    </span>
                    <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                </button>
                <div x-show="openDropdown === 'diet'"
                     x-transition
                     @click.outside="openDropdown = null"
                     style="display:none"
                     class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                    <div @click="filterDiet = ''; onGroupSelect('diet', ''); openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('diet')"></span>
                        <span class="text-gray-700">Любой</span>
                    </div>
                    <template x-for="t in getGroupTags('diet')" :key="t.slug">
                        <div @click="filterDiet = t.slug; onGroupSelect('diet', t.slug); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                            <span class="text-gray-700" x-text="t.name"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- СЛОЖНОСТЬ -->
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Сложность</label>
                <button type="button"
                        @click.prevent="openDropdown = openDropdown === 'difficulty' ? null : 'difficulty'"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2 truncate min-w-0">
                        <span class="shrink-0 inline-flex items-center"
                              x-html="currentDifficultyIcon()"></span>
                        <span class="truncate text-gray-700"
                              x-text="difficulty ? (difficultyLabel(difficulty)) : 'Любая'"></span>
                    </span>
                    <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                </button>
                <div x-show="openDropdown === 'difficulty'"
                     x-transition
                     @click.outside="openDropdown = null"
                     style="display:none"
                     class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                    <div @click="difficulty = ''; openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('sliders', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Любая</span>
                    </div>
                    <div @click="difficulty = 'easy'; openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-easy', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Легко</span>
                    </div>
                    <div @click="difficulty = 'medium'; openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-mid', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Средне</span>
                    </div>
                    <div @click="difficulty = 'hard'; openDropdown = null; search()"
                         class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-hard', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Сложно</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ═══ ДОПОЛНИТЕЛЬНЫЕ ФИЛЬТРЫ ═══ -->
        <details class="mt-3 border-t border-gray-100 pt-3 group relative z-30">
            <summary class="cursor-pointer text-sm font-semibold text-amber-600 hover:text-amber-800 transition select-none list-none [&::-webkit-details-marker]:hidden flex items-center gap-2">
                <span class="inline-flex items-center transition-transform duration-200 group-open:rotate-90">
                    <?= icon('right', 'gc-icon-sm', 1) ?>
                </span>
                <span>Дополнительные фильтры</span>
                <template x-if="activeExtraCount > 0">
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-amber-500 text-white text-[10px] font-bold"
                          x-text="activeExtraCount"></span>
                </template>
            </summary>

            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

                <!-- ПОВОД -->
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">
                        <?= icon('romantics', 'gc-icon-xs', 1) ?>
                        <span>Повод</span>
                    </label>
                    <button type="button"
                            @click.prevent="openDropdown = openDropdown === 'holiday' ? null : 'holiday'"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-2 truncate min-w-0">
                            <span class="shrink-0 inline-flex items-center"
                                  x-html="currentGroupIcon('holiday')"></span>
                            <span class="truncate text-gray-700"
                                  x-text="filterHoliday ? (getTagName('holiday', filterHoliday) || 'Повод') : 'Любой'"></span>
                        </span>
                        <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                    </button>
                    <div x-show="openDropdown === 'holiday'"
                         x-transition
                         @click.outside="openDropdown = null"
                         style="display:none"
                         class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                        <div @click="filterHoliday = ''; onGroupSelect('holiday', ''); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('holiday')"></span>
                            <span class="text-gray-700">Любой</span>
                        </div>
                        <template x-for="t in getGroupTags('holiday')" :key="t.slug">
                            <div @click="filterHoliday = t.slug; onGroupSelect('holiday', t.slug); openDropdown = null; search()"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                <span class="text-gray-700" x-text="t.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- СЕЗОН -->
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">
                        <?= icon('season', 'gc-icon-xs', 1) ?>
                        <span>Сезон</span>
                    </label>
                    <button type="button"
                            @click.prevent="openDropdown = openDropdown === 'season' ? null : 'season'"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-2 truncate min-w-0">
                            <span class="shrink-0 inline-flex items-center"
                                  x-html="currentGroupIcon('season')"></span>
                            <span class="truncate text-gray-700"
                                  x-text="filterSeason ? (getTagName('season', filterSeason) || 'Сезон') : 'Любой'"></span>
                        </span>
                        <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                    </button>
                    <div x-show="openDropdown === 'season'"
                         x-transition
                         @click.outside="openDropdown = null"
                         style="display:none"
                         class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                        <div @click="filterSeason = ''; onGroupSelect('season', ''); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('season')"></span>
                            <span class="text-gray-700">Любой</span>
                        </div>
                        <template x-for="t in getGroupTags('season')" :key="t.slug">
                            <div @click="filterSeason = t.slug; onGroupSelect('season', t.slug); openDropdown = null; search()"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                <span class="text-gray-700" x-text="t.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ВРЕМЯ -->
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">
                        <?= icon('time', 'gc-icon-xs', 1) ?>
                        <span>Время</span>
                    </label>
                    <button type="button"
                            @click.prevent="openDropdown = openDropdown === 'time' ? null : 'time'"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-2 truncate min-w-0">
                            <span class="shrink-0 inline-flex items-center"
                                  x-html="currentGroupIcon('time')"></span>
                            <span class="truncate text-gray-700"
                                  x-text="filterTime ? (getTagName('time', filterTime) || 'Время') : 'Любое'"></span>
                        </span>
                        <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                    </button>
                    <div x-show="openDropdown === 'time'"
                         x-transition
                         @click.outside="openDropdown = null"
                         style="display:none"
                         class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                        <div @click="filterTime = ''; onGroupSelect('time', ''); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('time')"></span>
                            <span class="text-gray-700">Любое</span>
                        </div>
                        <template x-for="t in getGroupTags('time')" :key="t.slug">
                            <div @click="filterTime = t.slug; onGroupSelect('time', t.slug); openDropdown = null; search()"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                <span class="text-gray-700" x-text="t.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ОГРАНИЧЕНИЯ -->
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">
                        <?= icon('restrictions', 'gc-icon-xs', 1) ?>
                        <span>Ограничения</span>
                    </label>
                    <button type="button"
                            @click.prevent="openDropdown = openDropdown === 'restriction' ? null : 'restriction'"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-2 truncate min-w-0">
                            <span class="shrink-0 inline-flex items-center"
                                  x-html="currentGroupIcon('restriction')"></span>
                            <span class="truncate text-gray-700"
                                  x-text="filterRestriction ? (getTagName('restriction', filterRestriction) || 'Ограничения') : 'Любые'"></span>
                        </span>
                        <span class="text-gray-400 shrink-0"><?= icon('chevron_down') ?></span>
                    </button>
                    <div x-show="openDropdown === 'restriction'"
                         x-transition
                         @click.outside="openDropdown = null"
                         style="display:none"
                         class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                        <div @click="filterRestriction = ''; onGroupSelect('restriction', ''); openDropdown = null; search()"
                             class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="groupIconHtml('restriction')"></span>
                            <span class="text-gray-700">Любые</span>
                        </div>
                        <template x-for="t in getGroupTags('restriction')" :key="t.slug">
                            <div @click="filterRestriction = t.slug; onGroupSelect('restriction', t.slug); openDropdown = null; search()"
                                 class="px-4 py-2 hover:bg-amber-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                <span class="text-gray-700" x-text="t.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            <button @click="clearFilters()"
                    class="mt-3 text-xs text-amber-600 hover:text-amber-800 font-semibold">
                Сбросить все фильтры
            </button>
        </details>

    </div>

    <!-- ═══ СЧЁТЧИК ═══ -->
    <div x-show="total > 0 && !loading" class="mb-4 text-sm text-gray-500">
        Найдено рецептов: <span class="font-bold text-gray-700" x-text="total"></span>
        <template x-if="q || category || difficulty || activeExtraCount > 0 || filterCuisine || filterDiet">
            <button @click="clearFilters()" class="ml-3 text-amber-600 hover:underline text-xs">Сбросить фильтры</button>
        </template>
    </div>

    <!-- ═══ ЗАГРУЗКА ═══ -->
    <div x-show="loading" class="text-center py-10 text-gray-400">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-amber-200 border-t-amber-500 rounded-full"></div>
        <p class="mt-2">Поиск...</p>
    </div>

    <!-- ═══ РЕЗУЛЬТАТЫ ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5" 
         x-show="!loading && results.length > 0" 
         style="display: none;">
        <template x-for="(rec, index) in results" :key="rec.id">
            
            <!-- ОБЕРТКА display: contents -->
            <div style="display: contents;">
                
                <!-- Карточка рецепта -->
                <article class="recipe-card bg-white rounded-2xl shadow-md hover:shadow-lg overflow-hidden transition flex flex-col">
                    <!-- Обложка -->
                    <div class="aspect-[4/3] overflow-hidden relative bg-gradient-to-br from-amber-100 to-orange-100">
                        <img :src="rec.image_url || '/uploads/static/static.jpg'"
                             onerror="this.src='/uploads/static/static.jpg'"
                             class="w-full h-full object-cover"
                             :alt="rec.title">

                        <span x-show="rec.is_ai_generated"
                              class="absolute top-3 left-3 px-2 py-1 rounded-lg text-xs font-bold bg-violet-100/90 text-violet-700 inline-flex items-center gap-1">
                            <span x-html="icons.ai"></span>
                            <span>AI</span>
                        </span>
                        <span class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-bold"
                              :class="difficultyColor(rec.difficulty)"
                              x-text="difficultyLabel(rec.difficulty)"></span>
                    </div>

                    <!-- Контент -->
                    <div class="p-4 flex flex-col flex-1">
                        <!-- Категория -->
                        <template x-if="rec.category_slug && rec.category_name">
                            <a :href="'/category/' + rec.category_slug" @click.stop
                               class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-600 transition mb-2 self-start">
                                <template x-if="rec.category_icon_svg">
                                    <span x-html="categoryIconHtml(rec.category_icon_svg)"></span>
                                </template>
                                <span x-text="rec.category_name"></span>
                            </a>
                        </template>
                        <template x-if="!rec.category_slug">
                            <div class="text-xs text-gray-400 mb-2" x-text="rec.cuisine || 'Другая'"></div>
                        </template>

                        <!-- Лайки -->
                        <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
                            <span></span>
                            <span class="flex items-center gap-1">
                                <span x-html="icons.heart"></span>
                                <span x-text="rec.likes_count || 0"></span>
                            </span>
                        </div>

                        <!-- Автор -->
                        <template x-if="rec.author_name">
                            <a :href="'/user/' + encodeURIComponent(rec.author_email)" @click.stop
                               class="flex items-center gap-2 mb-2 group/author">
                                <template x-if="rec.author_avatar_url">
                                    <img :src="rec.author_avatar_url"
                                         class="w-6 h-6 rounded-full object-cover shrink-0"
                                         :alt="rec.author_name">
                                </template>
                                <template x-if="!rec.author_avatar_url">
                                    <span class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center overflow-hidden shrink-0"
                                          x-html="window.getAvatarSvg(rec.author_gender)"></span>
                                </template>
                                <span class="text-xs text-gray-500 truncate group-hover/author:text-amber-600 transition"
                                      x-text="rec.author_name"></span>
                            </a>
                        </template>

                        <!-- Заголовок -->
                        <h2 class="font-bold text-gray-800 clamp-2 leading-snug text-base mb-1">
                            <a
                                :href="rec.slug ? '/recipe/' + rec.slug : '/recipe?id=' + rec.id"
                                class="hover:text-amber-600 transition"
                                x-text="rec.title">
                            </a>
                        </h2>

                        <!-- Описание -->
                        <p class="text-sm text-gray-500 clamp-2 mb-3 flex-1" x-text="rec.description || ''"></p>

                        <!-- Метаданные -->
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
                            <span class="inline-flex items-center gap-1">
                                <span x-html="icons.clock"></span>
                                <span x-text="((rec.prep_time || 0) + (rec.cook_time || 0)) + ' мин'"></span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span x-html="icons.dinner"></span>
                                <span x-text="(rec.servings || 1) + ' порц.'"></span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span x-html="icons.flame"></span>
                                <span x-text="(rec.calories || 0) + ' ккал'"></span>
                            </span>
                        </div>

                        <!-- НИЖНЯЯ ПАНЕЛЬ: ТЕГИ -->
                        <div class="flex flex-wrap gap-1 mt-auto pt-2 border-t border-gray-100">
                            <template x-for="t in (rec.tags_list || []).slice(0, 4)" :key="t.id">
                                <a :href="'/tag/' + t.slug" @click.stop
                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 hover:bg-amber-100 transition shrink-0"
                                   :title="t.tag_group">
                                    <template x-if="t.icon_svg_html">
                                        <span x-html="t.icon_svg_html"></span>
                                    </template>
                                    <span x-text="t.name"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </article>

                <!-- РЕКЛАМА (Вставляется после 6-й карточки) -->
                <template x-if="(index + 1) % 6 === 0 && window.__INLINE_AD_HTML__">
                    <article class="ad-card-wrapper bg-white rounded-2xl shadow-md overflow-hidden h-full flex flex-col relative border border-amber-100">
                        <span class="ad-label absolute top-3 right-3 bg-white/90 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider z-10 border border-gray-200">Реклама</span>
                        <div class="p-4 flex flex-col justify-center h-full w-full">
                            <div x-html="window.__INLINE_AD_HTML__" class="w-full"></div>
                        </div>
                    </article>
                </template>

            </div>
        </template>
    </div>

    <!-- «Показать ещё» -->
    <div x-show="results.length > 0 && hasMore && !loading" 
         style="display: none;" 
         class="flex justify-center mt-8 relative z-50">
        <button type="button" 
                @click.prevent="loadMore()" 
                :disabled="loadingMore"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition disabled:opacity-60 shadow-sm cursor-pointer">
            <span x-show="!loadingMore">Показать ещё</span>
            <span x-show="loadingMore" style="display:none" class="inline-flex items-center gap-1.5">
                <span x-html="icons.hourglass"></span>
                <span>Загрузка…</span>
            </span>
        </button>
    </div>

    <!-- Пагинация -->
    <nav x-show="!loading && results.length > 0 && totalPages > 1"
         style="display: none;"
         class="hidden sm:flex justify-center items-center gap-2 mt-8" aria-label="Пагинация">

        <button type="button" :disabled="page <= 1 || loading" @click="goToPage(page - 1)"
                class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <span x-html="icons.left"></span>
            <span>Назад</span>
        </button>

        <!-- ИЗМЕНЕНО: уникальный ключ для каждого элемента, чтобы Alpine не путался -->
        <template x-for="(p, idx) in pageNumbers" :key="'page-' + idx + '-' + p">
            <span class="inline-flex">
                <span x-show="p === '…'" class="w-10 h-10 flex items-center justify-center text-gray-400 select-none">…</span>
                <button type="button" x-show="p !== '…'" @click="goToPage(p)" :disabled="loading"
                        :class="p === page ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="w-10 h-10 rounded-xl text-sm font-semibold transition disabled:opacity-50"
                        x-text="p"></button>
            </span>
        </template>

        <button type="button" :disabled="page >= totalPages || loading" @click="goToPage(page + 1)"
                class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <span>Вперёд</span>
            <span x-html="icons.right"></span>
        </button>
    </nav>

    <!-- Нет результатов -->
    <div x-show="!loading && results.length === 0 && searched" class="text-center py-16" data-aos="fade-up">
        <div class="mb-4 text-gray-300 flex justify-center" x-html="icons.search_big"></div>
        <h3 class="text-xl font-bold text-gray-700 mb-2">Рецепты не найдены</h3>
        <p class="text-gray-500 mb-6">Попробуйте изменить параметры поиска</p>
        <div class="flex flex-wrap gap-3 justify-center">
            <button @click="clearFilters()"
                    class="px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                Сбросить фильтры
            </button>
            <a href="<?= url('ai-generator') ?>"
               class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-violet-100 text-violet-700 font-bold hover:bg-violet-200 transition">
                <span x-html="icons.ai"></span>
                <span>Создать рецепт с AI</span>
            </a>
        </div>
    </div>

    <!-- Начальное состояние -->
    <div x-show="!loading && !searched" class="text-center py-16" data-aos="fade-up">
        <div class="mb-4 text-gray-300 flex justify-center" x-html="icons.chef_big"></div>
        <h3 class="text-xl font-bold text-gray-700 mb-2">Все рецепты GigaChef</h3>
        <p class="text-gray-500">Используйте поиск и фильтры для точного подбора</p>
    </div>
</section>

<script>
    window.DIFFICULTY_LABELS = {
        easy:   'Легко',
        medium: 'Средне',
        hard:   'Сложно',
        master: 'Мастер-класс',
        unknown:'—'
    };
    window.DIFFICULTY_COLORS = {
        easy:   'bg-emerald-100 text-emerald-700',
        medium: 'bg-amber-100 text-amber-700',
        hard:   'bg-rose-100 text-rose-700',
        master: 'bg-violet-100 text-violet-700',
        unknown:'bg-gray-100 text-gray-500'
    };
    window.difficultyLabel = function (v) {
        const k = String(v || '').toLowerCase().trim();
        return window.DIFFICULTY_LABELS[k] || '—';
    };
    window.difficultyColor = function (v) {
        const k = String(v || '').toLowerCase().trim();
        return window.DIFFICULTY_COLORS[k] || window.DIFFICULTY_COLORS.unknown;
    };

    window.__SEARCH_INITIAL_PAGE__ = <?= (int)$initialPage ?>;
</script>

<script>
    window.DEFAULT_AVATAR_SVG_MALE    = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
    window.DEFAULT_AVATAR_SVG_FEMALE  = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
    window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;

    window.getAvatarSvg = function (gender) {
        if (gender === 'male')   return window.DEFAULT_AVATAR_SVG_MALE;
        if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
        return window.DEFAULT_AVATAR_SVG_NEUTRAL;
    };
</script>

<script src="<?= url('assets/js/search/search.js') ?>?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>