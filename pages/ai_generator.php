<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/ai-generator');
if ($meta) {
    apply_meta($meta);
}

$pageTitle = $GLOBALS['pageTitle'] ?? 'AI-генератор рецептов | GigaChef';
$pageH1    = $GLOBALS['pageH1']    ?? 'AI-генератор рецептов';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Бесплатный AI-генератор рецептов. Опишите что хотите приготовить — нейросеть создаст 4 варианта рецептов с БЖУ.';

// Получаем код рекламы для паузы AI
$aiActionAdHtml = get_ad_code('ai_action');

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

// ============================================================
//  ИКОНКИ ДЛЯ JS-ШАБЛОНОВ
//  Прокидываем готовый HTML через window, чтобы Alpine мог
//  вставлять их через x-html в динамических карточках.
// ============================================================
$jsIcons = [
    'ai'         => icon('ai',         'gc-icon-sm', 1),
    'hourglass'  => icon('hourglass',  'gc-icon-sm', 1),
    'diskette'   => icon('diskette',   'gc-icon-sm', 1),
    'info'       => icon('info',       'gc-icon-sm', 1),
    'time'       => icon('time',       'gc-icon-sm', 1),
    'rest'       => icon('rest',       'gc-icon-sm', 1),
    'lightning'  => icon('lightning',  'gc-icon-sm', 1),
    'cart'       => icon('cart',       'gc-icon-sm', 1),
    'chart'      => icon('chart',      'gc-icon-sm', 1),
    'expand'     => icon('expand',     'gc-icon-sm', 1),
    'minimize'   => icon('minimize',   'gc-icon-sm', 1),
    'refresh'    => icon('refresh',    'gc-icon-sm', 1),
    'cookbook'   => icon('cookbook',   'gc-icon-sm', 1),
    'dot'        => icon('dot',        'gc-icon-xs', 1),
    'chevron_down' => icon('chevron_down', 'gc-icon-sm', 1),
    'sliders'    => icon('sliders',    'gc-icon-sm', 1),
    'user'       => icon('user',       'gc-icon-sm', 1),
];

// ============================================================
//  ИКОНКИ СЛОЖНОСТИ
// ============================================================
$difficultyIcons = [
    ''       => icon('sliders',   'gc-icon-sm', 1),
    'easy'   => icon('diff-easy', 'gc-icon-sm', 1),
    'medium' => icon('diff-mid',  'gc-icon-sm', 1),
    'hard'   => icon('diff-hard', 'gc-icon-sm', 1),
    'master' => icon('diff-hard', 'gc-icon-sm', 1),  // отдельной нет — используем hard
];

require_once __DIR__ . '/../includes/header.php';
?>

<script>
window.__AI_ICONS__ = <?= json_encode($jsIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__AI_DIFFICULTY_ICONS__ = <?= json_encode($difficultyIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__AI_AD_HTML__ = <?= json_encode($aiActionAdHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.DIFFICULTY_LABELS = { easy: 'Легко', medium: 'Средне', hard: 'Сложно', master: 'Мастер', unknown: '—' };
</script>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10" x-data="aiGenerator()">

    <div data-aos="fade-up" class="text-center mb-8">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-bold mb-3">
            <?= icon('ai', 'gc-icon-sm', 1) ?>
            <span>AI</span>
        </span>

        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 flex items-center justify-center gap-2">
            <?= icon('aigenerator', 'gc-icon-md', 1) ?>
            <span><?= e($pageH1) ?></span>
        </h1>

        <p class="mt-2 text-gray-500">Опишите, что хотите приготовить — AI создаст 4 варианта рецептов с БЖУ.</p>
    </div>

    <!-- ═══ ФОРМА ═══ -->
    <div data-aos="fade-up" data-aos-delay="100" class="bg-white rounded-3xl shadow-md p-6 sm:p-8">

        <!-- Что приготовить -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Что приготовить?</label>
            <textarea x-model="form.dish" rows="1"
                      placeholder="Например: острый куриный суп с лапшой"
                      @keydown.enter.prevent="generate()"
                      @input="
                          let el = $event.target;
                          el.style.height = 'auto'; 
                          el.style.height = (el.scrollHeight) + 'px';
                      "
                      class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                      style="max-height: 150px; min-height: 46px;"
            ></textarea>
        </div>

        <!-- ═══ ФИЛЬТРЫ ═══ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">

            <!-- Группы тегов (cuisine, holiday, diet, ...) -->
            <template x-for="grp in tagGroups" :key="grp.group">
                <div class="relative">
                    <label class="block text-sm font-semibold text-gray-600 mb-1 inline-flex items-center gap-1.5">
                        <span class="shrink-0 inline-flex items-center" x-html="grp.icon_svg_html || ''"></span>
                        <span x-text="grp.label"></span>
                    </label>

                    <button type="button"
                            @click.prevent="toggleGroupDropdown(grp.group)"
                            class="w-full h-12 px-4 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-2 truncate min-w-0">
                            <span class="shrink-0 inline-flex items-center"
                                  x-html="currentGroupIcon(grp.group)"></span>
                            <span class="truncate text-gray-700"
                                  x-text="selectedTags[grp.group] ? getTagName(grp.group, selectedTags[grp.group]) : 'Не важно'"></span>
                        </span>
                        <span class="text-gray-400 shrink-0" x-html="icons.chevron_down"></span>
                    </button>

                    <div x-show="openDropdown === grp.group"
                         x-transition
                         @click.outside="openDropdown = null"
                         style="display:none"
                         class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">

                        <div @click="onGroupSelect(grp.group, '')"
                             class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                            <span class="shrink-0 inline-flex items-center" x-html="grp.icon_svg_html || ''"></span>
                            <span class="text-gray-700">Не важно</span>
                        </div>

                        <template x-for="t in grp.tags" :key="t.slug">
                            <div @click="onGroupSelect(grp.group, t.slug)"
                                 class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                                <span class="shrink-0 inline-flex items-center" x-html="t.icon_svg_html || ''"></span>
                                <span class="text-gray-700" x-text="t.name"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- СЛОЖНОСТЬ -->
            <div class="relative">
                <label class="block text-sm font-semibold text-gray-600 mb-1 inline-flex items-center gap-1.5">
                    <?= icon('sliders', 'gc-icon-sm', 1) ?>
                    <span>Сложность</span>
                </label>

                <button type="button"
                        @click.prevent="openDropdown = openDropdown === 'difficulty' ? null : 'difficulty'"
                        class="w-full h-12 px-4 rounded-xl border border-gray-200 bg-white text-left flex items-center justify-between gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2 truncate min-w-0">
                        <span class="shrink-0 inline-flex items-center"
                              x-html="currentDifficultyIcon()"></span>
                        <span class="truncate text-gray-700"
                              x-text="form.difficulty === '' ? 'Любая' : ({
                                  easy: 'Легко',
                                  medium: 'Средне',
                                  hard: 'Сложно',
                                  master: 'Мастер-класс'
                              }[form.difficulty] || 'Любая')"></span>
                    </span>
                    <span class="text-gray-400 shrink-0" x-html="icons.chevron_down"></span>
                </button>

                <div x-show="openDropdown === 'difficulty'"
                     x-transition
                     @click.outside="openDropdown = null"
                     style="display:none"
                     class="absolute z-[999] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">

                    <div @click="form.difficulty = ''; openDropdown = null"
                         class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center" x-html="icons.sliders"></span>
                        <span class="text-gray-700">Любая</span>
                    </div>
                    <div @click="form.difficulty = 'easy'; openDropdown = null"
                         class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-easy', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Легко</span>
                    </div>
                    <div @click="form.difficulty = 'medium'; openDropdown = null"
                         class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-mid', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Средне</span>
                    </div>
                    <div @click="form.difficulty = 'hard'; openDropdown = null"
                         class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-hard', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Сложно</span>
                    </div>
                    <div @click="form.difficulty = 'master'; openDropdown = null"
                         class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2 text-sm text-gray-500">
                        <span class="shrink-0 inline-flex items-center"><?= icon('diff-hard', 'gc-icon-sm', 1) ?></span>
                        <span class="text-gray-700">Мастер-класс</span>
                    </div>
                </div>
            </div>

            <!-- ПОРЦИЙ -->
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1 inline-flex items-center gap-1.5">
                    <?= icon('user', 'gc-icon-sm', 1) ?>
                    <span>Порций</span>
                </label>
                <input type="number" x-model="form.servings" min="1" max="20"
                       class="w-full h-12 px-4 rounded-xl border border-gray-200 outline-none focus:border-violet-300 text-sm">
            </div>

        </div>

               <!-- Исключить ингредиенты -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Исключить ингредиенты (необязательно)</label>
            <textarea x-model="form.exclude" rows="1" placeholder="Например: грибы, кинза"
                      @input="
                          let el = $event.target;
                          el.style.height = 'auto'; 
                          el.style.height = (el.scrollHeight) + 'px';
                      "
                      class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                      style="max-height: 150px; min-height: 46px;"
            ></textarea>
        </div>

        <!-- Кнопка -->
        <button @click="generate()" :disabled="loading"
                class="w-full h-12 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
            <span x-show="!loading" class="inline-flex items-center gap-2">
                <?= icon('ai', 'gc-icon-sm', 1) ?>
                <span>Сгенерировать 4 рецепта</span>
            </span>
            <span x-show="loading" style="display:none" class="inline-flex items-center gap-2">
                <?= icon('hourglass', 'gc-icon-sm', 1) ?>
                <span>Готовим рецепты…</span>
            </span>
        </button>
        <!-- Рекламный блок для AI-паузы -->
        <div x-show="loading" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                    <span>Пока AI придумывает рецепты...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
    </div>

    <!-- Ошибка -->
    <div x-show="error" x-transition style="display:none"
         class="mt-6 px-4 py-3 rounded-xl bg-rose-100 text-rose-700 text-sm" x-text="error"></div>

    <!-- ═══ РЕЗУЛЬТАТЫ ═══ -->
    <div x-show="recipes.length > 0" x-transition style="display:none" class="mt-8 space-y-6">

        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <h2 class="text-xl font-extrabold text-gray-800 inline-flex items-center gap-2">
                <?= icon('cookbook', 'gc-icon-md', 1) ?>
                <span>Сгенерированные рецепты</span>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-400">Выбрано: <b x-text="selectedCount"></b> из <b x-text="recipes.length"></b></span>
                <button @click="saveSelected()" :disabled="saving || selectedCount === 0"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center gap-2">
                    <span x-show="!saving" class="inline-flex items-center gap-2">
                        <?= icon('diskette', 'gc-icon-sm', 1) ?>
                        <span>Сохранить выбранное (<span x-text="selectedCount"></span>)</span>
                    </span>
                    <span x-show="saving" style="display:none">Сохраняем…</span>
                </button>
            </div>
        </div>

        <template x-for="(recipe, index) in recipes" :key="index">
            <div class="bg-white rounded-3xl shadow-md overflow-hidden"
                 :class="recipe.selected ? 'ring-2 ring-amber-400' : ''">

                <div class="bg-gradient-to-r from-violet-100 to-purple-100 p-6">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-violet-200 text-violet-800 inline-flex items-center gap-1">
                                <?= icon('ai', 'gc-icon-xs', 1) ?>
                                <span>AI</span>
                            </span>
                            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-white/70 text-gray-600" x-text="recipe.cuisine"></span>
                            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-white/70 text-gray-600" x-text="window.DIFFICULTY_LABELS[recipe.difficulty] || recipe.difficulty"></span>
                            <span x-show="recipe.is_duplicate" class="px-2 py-1 rounded-lg text-xs font-bold bg-amber-200 text-amber-800 inline-flex items-center gap-1">
                                <?= icon('info', 'gc-icon-xs', 1) ?>
                                <span>Похожий</span>
                            </span>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="recipe.selected" @change="updateSelection()"
                                   class="w-5 h-5 rounded accent-amber-500">
                            <span class="text-sm text-gray-600">Выбрать</span>
                        </label>
                    </div>
                    <h2 class="text-xl font-extrabold text-gray-800" x-text="recipe.title"></h2>
                    <p class="text-gray-600 mt-1 text-sm" x-text="recipe.description?.substring(0, 150) + '...'"></p>
                    <div class="flex flex-wrap gap-4 mt-4 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1">
                            <?= icon('time', 'gc-icon-sm', 1) ?>
                            <b x-text="(recipe.prep_time || 0) + (recipe.cook_time || 0)"></b> мин
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <?= icon('rest', 'gc-icon-sm', 1) ?>
                            <b x-text="recipe.servings"></b> порций
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <?= icon('lightning', 'gc-icon-sm', 1) ?>
                            <b x-text="recipe.calories"></b> ккал
                        </span>
                    </div>
                </div>

                <!-- Детали -->
                <div x-show="recipe.expanded" x-transition class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-100">
                    <div>
                        <h3 class="font-bold text-gray-800 mb-3 inline-flex items-center gap-2">
                            <?= icon('cart', 'gc-icon-sm', 1) ?>
                            <span>Ингредиенты</span>
                        </h3>
                        <ul class="space-y-2">
                            <template x-for="(ing, i) in recipe.ingredients" :key="i">
                                <li class="flex items-center gap-2 text-sm text-gray-700 py-1.5 border-b border-gray-50">
                                    <span class="text-violet-400 inline-flex items-center"><?= icon('dot', 'gc-icon-xs', 1) ?></span>
                                    <span x-text="ing.name"></span>
                                    <span class="ml-auto text-gray-500" x-text="`${ing.amount} ${ing.unit || ''}`"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 mb-3 inline-flex items-center gap-2">
                            <?= icon('chart', 'gc-icon-sm', 1) ?>
                            <span>КБЖУ</span>
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-amber-50 rounded-xl p-3 text-center"><div class="text-xl font-extrabold text-amber-600" x-text="recipe.calories"></div><div class="text-xs text-gray-500">ккал</div></div>
                            <div class="bg-emerald-50 rounded-xl p-3 text-center"><div class="text-xl font-extrabold text-emerald-600" x-text="recipe.proteins + ' г'"></div><div class="text-xs text-gray-500">белки</div></div>
                            <div class="bg-rose-50 rounded-xl p-3 text-center"><div class="text-xl font-extrabold text-rose-500" x-text="recipe.fats + ' г'"></div><div class="text-xs text-gray-500">жиры</div></div>
                            <div class="bg-violet-50 rounded-xl p-3 text-center"><div class="text-xl font-extrabold text-violet-600" x-text="recipe.carbs + ' г'"></div><div class="text-xs text-gray-500">углеводы</div></div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <h3 class="font-bold text-gray-800 mb-3 inline-flex items-center gap-2">
                            <?= icon('ai', 'gc-icon-sm', 1) ?>
                            <span>Приготовление</span>
                        </h3>
                        <ol class="space-y-2">
                            <template x-for="(step, i) in recipe.steps" :key="i">
                                <li class="flex gap-2 text-sm text-gray-700">
                                    <span class="shrink-0 w-7 h-7 rounded-full bg-violet-100 text-violet-700 font-bold flex items-center justify-center text-xs" x-text="i + 1"></span>
                                    <span x-text="step.instruction"></span>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>

                <div class="px-6 pb-4">
                    <button @click="recipe.expanded = !recipe.expanded"
                            class="text-sm text-violet-600 hover:text-violet-800 font-semibold inline-flex items-center gap-1.5">
                        <span x-show="!recipe.expanded" class="inline-flex items-center gap-1.5">
                            <?= icon('expand', 'gc-icon-sm', 1) ?>
                            <span>Показать полностью</span>
                        </span>
                        <span x-show="recipe.expanded" style="display:none" class="inline-flex items-center gap-1.5">
                            <?= icon('minimize', 'gc-icon-sm', 1) ?>
                            <span>Свернуть</span>
                        </span>
                    </button>
                </div>
            </div>
        </template>

        <div class="flex flex-wrap gap-3 justify-center pt-4">
            <button @click="saveSelected()" :disabled="saving || selectedCount === 0"
                    class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center gap-2">
                <span x-show="!saving" class="inline-flex items-center gap-2">
                    <?= icon('diskette', 'gc-icon-sm', 1) ?>
                    <span>Сохранить выбранное (<span x-text="selectedCount"></span>)</span>
                </span>
                <span x-show="saving" style="display:none">Сохраняем…</span>
            </button>
            <button @click="generate()"
                    class="px-6 py-3 rounded-xl bg-violet-100 text-violet-700 font-bold hover:bg-violet-200 transition inline-flex items-center gap-2">
                <?= icon('refresh', 'gc-icon-sm', 1) ?>
                <span>Сгенерировать другие</span>
            </button>
        </div>
        <!-- Рекламный блок для сохранения -->
        <div x-show="saving" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('diskette', 'gc-icon-xs', 1) ?>
                    <span>Сохраняем рецепты в вашу книгу...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
    </div>
</section>

<script src="<?= url('assets/js/ai/ai_generator.js') ?>?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>