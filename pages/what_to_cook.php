<?php
require_once __DIR__ . '/../config/config.php';

// ============================================================
//  МЕТА-ТЕГИ (из admin/meta)
// ============================================================
$meta = function_exists('get_page_meta') ? get_page_meta('/what-to-cook') : null;
if ($meta && function_exists('apply_meta')) {
    apply_meta($meta);
}

$pageTitle       = $GLOBALS['pageTitle']       ?? 'Что приготовить? — GigaChef';
$pageH1          = $GLOBALS['pageH1']          ?? 'Что приготовить?';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Добавьте продукты из холодильника — AI предложит блюда.';

// Получаем код рекламы для пауз AI
$aiActionAdHtml = get_ad_code('ai_action');

$GLOBALS['pageTitle']       = $pageTitle;
$GLOBALS['pageH1']          = $pageH1;
$GLOBALS['pageDescription'] = $pageDescription;

$logged = is_logged_in();
require_once __DIR__ . '/../includes/header.php';
?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 py-10" x-data="whatToCook()" x-init="init()">
    <div data-aos="fade-up" class="text-center mb-8">
        <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold mb-3"><?= icon('ai') ?> Умный подбор</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 flex items-center justify-center gap-2"><?= icon('whattocook', 'gc-icon-sm') ?><?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500">Добавьте продукты из холодильника — AI предложит блюда.</p>
    </div>

    <!-- ============================================================
         ВВОД ПРОДУКТОВ + ФОТО ХОЛОДИЛЬНИКА
         ============================================================ -->
    <div data-aos="fade-up" data-aos-delay="100" class="bg-white rounded-3xl shadow-md p-6 sm:p-8">

        <!-- ─── ФОТО ХОЛОДИЛЬНИКА ─── -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-600 mb-2"><?= icon('camera', '', 1.5) ?> Сфотографировать холодильник</label>

            <!-- Десктоп -->
            <label class="hidden sm:flex border-2 border-dashed border-gray-200 rounded-2xl p-5 text-center cursor-pointer hover:border-emerald-300 transition group">
                <input type="file" accept="image/*" class="hidden" @change="previewFridge($event)">
                <div class="text-gray-400 group-hover:text-emerald-500 transition w-full">
                    <div class="text-3xl mb-1"><?= icon('picture', '', 2) ?></div>
                    <div class="text-sm font-semibold">Загрузить фото холодильника</div>
                    <div class="text-xs mt-1">AI распознает продукты · JPEG, PNG · до 5 МБ</div>
                </div>
            </label>

            <!-- Мобилка: открывает меню -->
            <button type="button" @click="showMobileMenu = true"
                    class="sm:hidden w-full border-2 border-dashed border-gray-200 rounded-2xl p-5 text-center hover:border-emerald-300 transition group">
                <div class="text-gray-400 group-hover:text-emerald-500 transition">
                    <div class="text-3xl mb-1"><?= icon('picture', '', 2) ?></div>
                    <div class="text-sm font-semibold">Загрузить фото холодильника</div>
                    <div class="text-xs mt-1">Снять или выбрать из галереи</div>
                </div>
            </button>

            <!-- Превью -->
            <div x-show="fridgeImage" x-transition style="display:none" class="relative mt-3">
                <img :src="fridgeImage" class="mx-auto max-h-48 rounded-xl object-contain shadow-md" alt="Фото холодильника">
                <button type="button" @click="clearFridge()"
                        class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition"
                        aria-label="Удалить фото">
                    <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <button type="button" @click="scanFridge()" :disabled="scanningFridge"
                        class="mt-3 w-full py-3 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold hover:shadow-md transition disabled:opacity-60">
                    <span x-show="!scanningFridge"><?= icon('search', '', 1.5) ?> Распознать продукты</span>
                    <span x-show="scanningFridge" style="display:none"><?= icon('hourglass', '', 1.5) ?>AI смотрит в холодильник…</span>
                </button>
                
                <!-- Рекламный блок для распознавания фото -->
                <div x-show="scanningFridge" x-transition style="display: none;" class="mt-3 ai-ad-pause-container">
                    <div class="ai-ad-pause-header flex justify-between items-center">
                        <span class="inline-flex items-center gap-1.5">
                            <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                            <span>AI смотрит в холодильник...</span>
                        </span>
                        <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                            <span x-text="adTimer"></span> сек
                        </span>
                    </div>
                    <div x-html="aiAdHtml" class="p-4"></div>
                </div>

                <p x-show="fridgeError" style="display:none" class="text-rose-500 text-sm mt-2" x-text="fridgeError"></p>
            </div>

            <!-- Информация о лимитах AI -->
            <div x-show="ai.limit" x-cloak class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold"
                 :class="(ai.remaining > 0 || ai.limit >= 999) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
                <span x-show="ai.limit >= 999"><?= icon('flame', '', 1.5) ?> Безлимитный доступ</span>
                <span x-show="ai.limit < 999 && !ai.is_pro">Распознавание: <span class="font-extrabold" x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
                <span x-show="ai.limit < 999 && ai.is_pro" style="display:none">Pro · <span x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
            </div>
        </div>

        <!-- ─── РУЧНОЙ ВВОД ПРОДУКТОВ ─── -->
        <label class="block text-sm font-semibold text-gray-600 mb-2">Ваши продукты</label>
        <div class="flex gap-2 mb-3 items-end">
            <textarea x-model="ingredientInput" 
                      @keydown.enter.prevent="addIngredient()"
                      @blur="if(ingredientInput.trim()) addIngredient()"
                      placeholder="Введите продукт"
                      rows="1"
                      @input="
                          let el = $event.target;
                          el.style.height = 'auto'; 
                          el.style.height = (el.scrollHeight) + 'px';
                      "
                      class="flex-1 px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                      style="max-height: 150px; min-height: 46px;"
            ></textarea>
            
            <!-- Кнопка стала шире и с текстом для мобильных -->
            <button type="button" @click="addIngredient()" 
                    class="px-5 py-3 rounded-xl bg-emerald-100 text-emerald-700 font-bold hover:bg-emerald-200 transition shrink-0 flex items-center justify-center gap-1">
                <span class="text-xl leading-none">+</span>
            </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4" x-show="ingredients.length" style="display:none">
            <template x-for="(ing, i) in ingredients" :key="i">
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-sm">
                    <span x-text="ing"></span>
                    <button type="button" @click="ingredients.splice(i,1)" class="text-emerald-400 hover:text-emerald-600">×</button>
                </span>
            </template>
        </div>

        <!-- Быстрое добавление -->
        <div class="flex flex-wrap gap-2 mb-5">
            <template x-for="p in popular" :key="p">
                <button type="button" @click="quickAdd(p)" class="px-3 py-1.5 rounded-full bg-gray-50 text-gray-600 text-xs hover:bg-emerald-50 hover:text-emerald-700 transition" x-text="'+ ' + p"></button>
            </template>
        </div>

        <!-- Параметры -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Тип питания</label>
                <select x-model="diet" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300">
                    <option value="">Любое</option>
                    <option>Вегетарианская</option>
                    <option>Веганская</option>
                    <option>Кето</option>
                    <option>Низкокалорийная</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Есть время (мин)</label>
                <input type="number" x-model="time" min="5" max="240" placeholder="30"
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300">
            </div>
        </div>

        <!-- Кнопка подбора -->
        <button type="button" @click="find()" :disabled="loading || !ingredients.length"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold hover:shadow-md transition disabled:opacity-60">
            <span x-show="!loading"><?= icon('search', '', 1.5) ?> Подобрать блюда</span>
            <span x-show="loading" style="display:none"><?= icon('hourglass', '', 1.5) ?> Думаем…</span>
        </button>
        <!-- Рекламный блок для подбора -->
        <div x-show="loading" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                    <span>Пока AI подбирает блюда...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
    </div>

    <!-- Ошибки -->
    <div x-show="error" x-transition style="display:none" class="mt-6 px-4 py-3 rounded-xl bg-rose-100 text-rose-700 text-sm" x-text="error"></div>

    <!-- ============================================================
         РЕЗУЛЬТАТЫ ПОДБОРА
         ============================================================ -->
    <div x-show="dishes.length" x-transition style="display:none" class="mt-8 space-y-4">
        <h2 class="text-xl font-extrabold text-gray-800">Вам подойдёт:</h2>
        
        <template x-for="(d, i) in dishes" :key="i">
            <div class="bg-white rounded-2xl shadow-md p-5 transition" :class="d.selected ? 'ring-2 ring-emerald-400' : ''">
                <div class="flex items-start justify-between gap-3">
                    <label class="flex items-start gap-3 cursor-pointer flex-1">
                        <input type="checkbox" x-model="d.selected" class="w-5 h-5 rounded accent-emerald-500 mt-1 shrink-0">
                        <div>
                            <h3 class="font-bold text-gray-800 text-lg leading-tight" x-text="d.title"></h3>
                            <p class="text-sm text-gray-500 mt-1" x-text="d.description"></p>
                        </div>
                    </label>
                    <span class="shrink-0 px-2 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-600" x-text="'⏱ ' + d.time + ' мин'"></span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <template x-if="d.have && d.have.length">
                        <template x-for="(ing, idx) in d.have" :key="'have-' + idx">
                            <span class="px-2 py-0.5 rounded-md text-xs bg-emerald-50 text-emerald-700" x-text="ing"></span>
                        </template>
                    </template>
                    <template x-if="d.need && d.need.length">
                        <template x-for="(ing, idx) in d.need" :key="'need-' + idx">
                            <span class="px-2 py-0.5 rounded-md text-xs bg-rose-50 text-rose-500" x-text="'+ ' + ing"></span>
                        </template>
                    </template>
                </div>
            </div>
        </template>

        <!-- Кнопка пакетной генерации -->
        <div class="sticky bottom-4 flex flex-col items-center gap-2 pt-4 z-50">
            <div x-show="generating" class="text-sm text-emerald-700 bg-emerald-50 px-4 py-2 rounded-xl font-semibold">
                Генерируем <span x-text="generatingProgress.current"></span> из <span x-text="generatingProgress.total"></span>...
            </div>

            <button type="button" @click="generateSelected()" :disabled="selectedCount === 0 || generating"
                    class="px-8 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-400 to-teal-400 text-white font-bold shadow-lg hover:shadow-xl transition disabled:opacity-50 inline-flex items-center gap-2">
                <span x-show="!generating" class="inline-flex items-center gap-2">
                    <?= icon('aigenerator', 'gc-icon-sm', 1) ?>
                    <span>Сгенерировать выбранные (<span x-text="selectedCount"></span>)</span>
                </span>
                <span x-show="generating" style="display:none" class="inline-flex items-center gap-2">
                    <?= icon('hourglass', 'gc-icon-sm', 1) ?>
                    <span>Генерируем...</span>
                </span>
            </button>
        </div>
        
        <!-- Рекламный блок для генерации (ВЫНЕСЕН ИЗ STICKY-КОНТЕЙНЕРА) -->
        <div x-show="generating" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('aigenerator', 'gc-icon-xs', 1) ?>
                    <span>Генерируем выбранные блюда...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
    </div>

    <!-- ============================================================
         СГЕНЕРИРОВАННЫЕ РЕЦЕПТЫ
         ============================================================ -->
    <div x-show="generatedRecipes.length > 0" x-transition class="mt-8 space-y-4">
        <h2 class="text-xl font-extrabold text-gray-800">Сгенерированные рецепты:</h2>
        
        <template x-for="(r, i) in generatedRecipes" :key="'gen-' + i">
            <div class="bg-white rounded-2xl shadow-md p-5 border-l-4 border-emerald-400">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold text-gray-800 text-lg" x-text="r.title"></h3>
                    <span class="text-xs font-bold px-2 py-1 bg-amber-50 text-amber-600 rounded-lg" x-text="r.calories + ' ккал'"></span>
                </div>
                <p class="text-sm text-gray-500 mb-3" x-text="r.description"></p>
                
                <div class="flex gap-2">
                    <a :href="'/recipe/' + r.slug" x-show="r.slug"
                       class="px-4 py-2 bg-emerald-100 text-emerald-700 font-bold rounded-lg text-sm hover:bg-emerald-200 transition">
                        Открыть рецепт
                    </a>
                    <button type="button" @click="saveGenerated(r)" :disabled="r.saving || r.saved"
                            class="px-4 py-2 bg-violet-100 text-violet-700 font-bold rounded-lg text-sm hover:bg-violet-200 transition disabled:opacity-50">
                        <span x-show="!r.saved">Сохранить в профиль</span>
                        <span x-show="r.saved" style="display:none">Сохранено</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- ============================================================
         МОБИЛЬНОЕ МЕНЮ: выбор источника фото
         ============================================================ -->
    <div x-show="showMobileMenu" x-transition style="display:none"
         class="sm:hidden fixed inset-0 z-[100] flex items-end justify-center"
         @click.self="showMobileMenu = false">
        <div class="bg-black/40 absolute inset-0"></div>
        <div class="bg-white rounded-t-3xl shadow-2xl w-full max-w-lg p-6 relative z-10"
             style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));">

            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-gray-800 text-lg"><?= icon('camera', '', 1.5) ?> Фото холодильника</h3>
                <button type="button" @click="showMobileMenu = false" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200"><?= icon('close', '', 1.5) ?></button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-emerald-300 transition group">
                    <input type="file" accept="image/*" capture="environment" class="hidden" @change="previewFridge($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-emerald-500 transition">
                        <div class="text-3xl mb-1"><?= icon('camera', '', 1.5) ?></div>
                        <div class="text-sm font-semibold">Снять фото</div>
                        <div class="text-xs mt-0.5">Открыть камеру</div>
                    </div>
                </label>

                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-emerald-300 transition group">
                    <input type="file" accept="image/*" class="hidden" @change="previewFridge($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-emerald-500 transition">
                        <div class="text-3xl mb-1"><?= icon('image', '', 1.5) ?>️</div>
                        <div class="text-sm font-semibold">Из галереи</div>
                        <div class="text-xs mt-0.5">Выбрать файл</div>
                    </div>
                </label>
            </div>

            <button type="button" @click="showMobileMenu = false" class="mt-4 w-full py-3 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold hover:bg-gray-200 transition">
                Отмена
            </button>
        </div>
    </div>
</section>
<script>
window.__AI_AD_HTML__ = <?= json_encode($aiActionAdHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= url('assets/js/ai/what-to-cook.js') ?>?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>