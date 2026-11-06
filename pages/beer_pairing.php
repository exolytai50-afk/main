<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/beer-pairing');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем заголовок страницы
$pageTitle = $GLOBALS['pageTitle'] ?? 'Бирпейринг — идеальные сочетания пива и еды | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Бирпейринг';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Подберите пиво к блюду или блюдо к пиву — AI найдёт идеальное сочетание.';

// Получаем код рекламы для пауз AI
$aiActionAdHtml = get_ad_code('ai_action');

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

$logged = is_logged_in();
require_once __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10"
         x-data="beerPairing(<?= $logged ? 'true' : 'false' ?>)" x-init="init()">
    <div class="text-center mb-8" data-aos="fade-up">
        <span class="inline-block px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-bold mb-3"><?= icon('ai') ?> AI Сомелье</span>
        <h1 class="text-3xl font-extrabold text-gray-800"><?= icon('beer') ?><?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500">Подберите пиво к блюду или блюдо к пиву — AI найдёт идеальное сочетание</p>

        <div x-show="ai.limit" x-cloak class="mt-4 inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold"
             :class="ai.remaining > 0 || ai.limit >= 999 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-600'">
            <span x-show="ai.limit >= 999"><?= icon('lightning') ?>Безлимитный доступ</span>
            <span x-show="ai.limit < 999 && !ai.is_pro">Осталось <span class="font-extrabold" x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
            <span x-show="ai.limit < 999 && ai.is_pro" style="display:none">Pro · <span x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8" data-aos="fade-up" data-aos-delay="100">
        
        <!-- ✅ Переключатель режима -->
        <div class="flex bg-gray-100 rounded-2xl p-1.5 mb-6">
            <button @click="mode = 'beer-to-food'" 
                :class="mode === 'beer-to-food' ? 'beer-tab-active' : 'text-gray-400 hover:text-gray-600 hover:bg-black/5 dark:hover:bg-white/5'" 
                class="beer-tab flex-1 py-3 rounded-xl text-sm font-bold transition">
                <?= icon('beer') ?>
                → 
                <?= icon('rest') ?>
                К пиву
            </button>
            <button @click="mode = 'food-to-beer'" 
                :class="mode === 'food-to-beer' ? 'beer-tab-active' : 'text-gray-400 hover:text-gray-600 hover:bg-black/5 dark:hover:bg-white/5'" 
                class="beer-tab flex-1 py-3 rounded-xl text-sm font-bold transition">
                <?= icon('rest') ?>
                → 
                <?= icon('beer') ?>
                К блюду
            </button>
        </div>
        
        <!-- ===== РЕЖИМ: Подбор пива к блюду ===== -->
        <div x-show="mode === 'food-to-beer'">
            <!-- Загрузка фото блюда -->
            <div class="mb-4">
                <label class="hidden sm:flex border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center cursor-pointer hover:border-amber-300 transition group">
                    <input type="file" accept="image/*" class="hidden" @change="preview($event)">
                    <div class="text-gray-600 group-hover:text-amber-500 transition w-full">
                        <div class="text-4xl mb-2"><?= icon('rest') ?></div>
                        <div class="text-sm font-semibold">Добавить фото блюда</div>
                        <div class="text-xs mt-1">JPEG, PNG, WebP · до 5 МБ</div>
                    </div>
                </label>
                
                <button @click="showMobileMenu = true" 
                        class="sm:hidden w-full border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:border-amber-300 transition group">
                    <div class="text-gray-600 group-hover:text-amber-500 transition w-full">
                        <div class="text-4xl mb-2"><?= icon('rest') ?></div>
                        <div class="text-sm font-semibold">Добавить фото блюда</div>
                        <div class="text-xs mt-1">Снять фото или выбрать из галереи</div>
                    </div>
                </button>
            </div>

            <!-- Превью -->
            <div x-show="image" x-transition style="display:none" class="relative mb-4">
                <img :src="image" class="mx-auto max-h-48 sm:max-h-64 rounded-xl object-contain shadow-md" alt="Фото">
                <button @click="clearImage()" 
                        class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition">
                    <?= icon('close') ?>
                </button>
            </div>

            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-xs"><span class="bg-white px-3 text-gray-400">или</span></div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-600 mb-1">Название блюда</label>
                <textarea x-model="dishName" rows="1"
                          placeholder="Например: стейк рибай, роллы Филадельфия, пицца Маргарита..."
                          @keydown.enter.prevent="analyze()"
                          @input="
                              let el = $event.target;
                              el.style.height = 'auto'; 
                              el.style.height = (el.scrollHeight) + 'px';
                          "
                          class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                          style="max-height: 150px; min-height: 46px;"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Предпочтения по пиву</label>
                    <select x-model="foodToBeerPref" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value=""><?= icon('beer') ?> Любое пиво</option>
                        <option value="light">Светлое</option>
                        <option value="dark">Тёмное</option>
                        <option value="craft">Крафтовое</option>
                        <option value="non-alcoholic">Безалкогольное</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Стиль кухни</label>
                    <select x-model="cuisineStyle" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value=""><?= icon('rest') ?> Любая кухня</option>
                        <option value="russian">Русская</option>
                        <option value="italian">Итальянская</option>
                        <option value="japanese">Японская</option>
                        <option value="mexican">Мексиканская</option>
                        <option value="american">Американская</option>
                        <option value="german">Немецкая</option>
                        <option value="thai">Тайская</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- ===== РЕЖИМ: Подбор блюд к пиву ===== -->
        <div x-show="mode === 'beer-to-food'" style="display:none">
            <!-- Загрузка фото пива -->
            <div class="mb-4">
                <label class="hidden sm:flex border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center cursor-pointer hover:border-amber-300 transition group">
                    <input type="file" accept="image/*" class="hidden" @change="preview($event)">
                    <div class="text-gray-600 group-hover:text-amber-400 transition w-full">
                        <div class="text-4xl mb-2"><?= icon('beer') ?></div>
                        <div class="text-sm font-semibold">Добавить фото пива</div>
                        <div class="text-xs mt-1">Сфотографируйте бутылку, банку или бокал</div>
                    </div>
                </label>
                
                <button @click="showMobileMenu = true" 
                        class="sm:hidden w-full border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:border-amber-300 transition group">
                    <div class="text-gray-600 group-hover:text-amber-400 transition w-full">
                        <div class="text-4xl mb-2"><?= icon('beer') ?></div>
                        <div class="text-sm font-semibold">Добавить фото пива</div>
                        <div class="text-xs mt-1">Снять фото или выбрать из галереи</div>
                    </div>
                </button>
            </div>

            <!-- Превью -->
            <div x-show="image" x-transition style="display:none" class="relative mb-4">
                <img :src="image" class="mx-auto max-h-48 sm:max-h-64 rounded-xl object-contain shadow-md" alt="Фото">
                <button @click="clearImage()" 
                        class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition">
                    <?= icon('close') ?>
                </button>
            </div>

            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-xs"><span class="bg-white px-3 text-gray-400">или опишите пиво</span></div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-600 mb-1">Название или стиль пива</label>
                <textarea x-model="beerName" rows="1"
                          placeholder="Например: Guinness, IPA, тёмный эль, пшеничное, крафтовый стаут..."
                          @keydown.enter.prevent="analyze()"
                          @input="
                              let el = $event.target;
                              el.style.height = 'auto'; 
                              el.style.height = (el.scrollHeight) + 'px';
                          "
                          class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                          style="max-height: 150px; min-height: 46px;"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Тип блюда</label>
                    <select x-model="beerToFoodType" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value=""><?= icon('rest') ?> Любое блюдо</option>
                        <option value="snack">Закуска</option>
                        <option value="main">Основное блюдо</option>
                        <option value="grill">Гриль/Мангал</option>
                        <option value="seafood">Морепродукты</option>
                        <option value="cheese">Сырная тарелка</option>
                        <option value="dessert">Десерт</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Количество персон</label>
                    <select x-model="servings" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value="1-2">1-2 персоны</option>
                        <option value="3-4">3-4 персоны</option>
                        <option value="5-8">Компания 5-8</option>
                        <option value="party">Вечеринка 10+</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Кнопка анализа (общая) -->
        <button @click="analyze()" :disabled="loading || (!image && !getInputText().trim())"
        class="beer-btn-main w-full py-3.5 rounded-xl font-bold transition disabled:opacity-60">
            <span x-show="!loading">
                <span x-show="mode === 'food-to-beer'"><?= icon('beer') ?> Подобрать пиво к блюду</span>
                <span x-show="mode === 'beer-to-food'"><?= icon('rest') ?> Подобрать блюда к пиву</span>
            </span>
            <span x-show="loading" style="display:none"><?= icon('hourglass') ?> AI анализирует…</span>
        </button>
        
        <!-- Рекламный блок для анализа -->
        <div x-show="loading" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                    <span>AI анализирует...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
        
        <p x-show="error" style="display:none" class="text-rose-500 text-sm mt-3" x-text="error"></p>
    </div>

    <!-- ===== РЕЗУЛЬТАТ: Пиво к блюду ===== -->
    <div x-show="result && mode === 'food-to-beer'" x-transition style="display:none" class="mt-6 bg-white rounded-3xl shadow-md overflow-hidden" data-aos="fade-up">
        <div class="bg-gradient-to-r from-amber-100 to-orange-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-gray-800" x-text="result?.dish_name || 'Блюдо'"></h2>
                    <p class="text-sm text-gray-500 mt-1" x-text="result?.pairing_summary"></p>
                </div>
                <span class="text-5xl"><?= icon('beer') ?></span>
            </div>
        </div>
        
        <div class="p-6 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('thumbs') ?> Лучшая рекомендация</h3>
            <div class="bg-amber-50 rounded-2xl p-5">
                <div class="flex items-start gap-4">
                    <span class="text-4xl shrink-0"><?= icon('beer') ?></span>
                    <div>
                        <div class="text-lg font-extrabold text-gray-800" x-text="result?.primary_beer?.name"></div>
                        <div class="text-sm text-gray-500 mt-0.5" x-text="result?.primary_beer?.style + ' · ' + (result?.primary_beer?.abv || '')"></div>
                        <div class="text-sm text-amber-700 mt-2 leading-relaxed" x-text="result?.primary_beer?.reason"></div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <template x-for="brand in result?.primary_beer?.brands || []" :key="brand">
                                <span class="px-3 py-1 rounded-full bg-white text-gray-600 text-xs shadow-sm" x-text="brand"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div x-show="result?.alternatives?.length" class="p-6 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('refresh') ?> Альтернативные варианты</h3>
            <div class="space-y-3">
                <template x-for="(alt, i) in result?.alternatives || []" :key="i">
                    <div class="flex items-start gap-3 p-4 rounded-2xl hover:bg-gray-50 transition">
                        <span class="text-2xl shrink-0"><?= icon('beer') ?></span>
                        <div class="min-w-0">
                            <div class="font-bold text-gray-800 text-sm" x-text="alt.name"></div>
                            <div class="text-xs text-gray-400" x-text="alt.style + ' · ' + (alt.abv || '')"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="alt.reason"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <div class="p-6">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('bulb') ?> Советы по сервировке</h3>
            <ul class="space-y-2">
                <template x-for="tip in (result?.serving_tips || [])" :key="tip">
                    <li class="flex items-start gap-2 text-sm text-gray-600"><span class="text-amber-400 mt-0.5"><?= icon('dot') ?></span><span x-text="tip"></span></li>
                </template>
            </ul>
            <p x-show="result?.note" class="text-xs text-gray-400 mt-4 italic"><?= icon('chats') ?><span x-text="result?.note"></span></p>
        </div>
    </div>
    
    <!-- ===== РЕЗУЛЬТАТ: Блюда к пиву ===== -->
    <div x-show="result && mode === 'beer-to-food'" x-transition style="display:none" class="mt-6 bg-white rounded-3xl shadow-md overflow-hidden" data-aos="fade-up">
        <div class="bg-gradient-to-r from-amber-100 to-orange-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-gray-800" x-text="result?.beer_info?.name || 'Пиво'"></h2>
                    <p class="text-sm text-gray-500 mt-1" x-text="result?.pairing_summary"></p>
                </div>
                <span class="text-5xl"><?= icon('rest') ?></span>
            </div>
            <div class="flex flex-wrap gap-2 mt-3" x-show="result?.beer_info">
                <span class="px-2.5 py-1 rounded-full bg-white/70 text-xs font-semibold text-gray-600" x-text="'Стиль: ' + result?.beer_info?.style"></span>
                <span class="px-2.5 py-1 rounded-full bg-white/70 text-xs font-semibold text-gray-600" x-text="'ABV: ' + result?.beer_info?.abv"></span>
                <span class="px-2.5 py-1 rounded-full bg-white/70 text-xs font-semibold text-gray-600" x-text="'IBU: ' + result?.beer_info?.ibu"></span>
                <span class="px-2.5 py-1 rounded-full bg-white/70 text-xs font-semibold text-gray-600" x-text="'t°: ' + result?.beer_info?.serving_temp"></span>
            </div>
        </div>
        
        <div class="p-6 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('thumbs') ?> Лучшая рекомендация</h3>
            <div class="bg-amber-50 rounded-2xl p-5">
                <div class="flex items-start gap-4">
                    <span class="text-4xl shrink-0"><?= icon('steak') ?></span>
                    <div>
                        <div class="text-lg font-extrabold text-gray-800" x-text="result?.primary_dish?.name"></div>
                        <div class="text-sm text-gray-500 mt-0.5" x-text="result?.primary_dish?.cuisine + ' кухня'"></div>
                        <div class="text-sm text-amber-700 mt-2 leading-relaxed" x-text="result?.primary_dish?.reason"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div x-show="result?.alternatives?.length" class="p-6 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('refresh') ?> Другие варианты</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <template x-for="(alt, i) in result?.alternatives || []" :key="i">
                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50">
                        <span class="text-2xl shrink-0"><?= icon('rest') ?></span>
                        <div class="min-w-0">
                            <div class="font-bold text-gray-800 text-sm" x-text="alt.name"></div>
                            <div class="text-xs text-gray-400" x-text="alt.cuisine"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="alt.reason"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <div x-show="result?.snacks?.length" class="p-6 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('cookie') ?> Быстрые закуски</h3>
            <div class="flex flex-wrap gap-2">
                <template x-for="snack in result?.snacks || []" :key="snack">
                    <span class="px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold" x-text="snack"></span>
                </template>
            </div>
        </div>
        
        <div class="p-6">
            <h3 class="font-bold text-gray-800 mb-3"><?= icon('bulb') ?> Советы сомелье</h3>
            <ul class="space-y-2">
                <template x-for="tip in (result?.sommelier_tips || [])" :key="tip">
                    <li class="flex items-start gap-2 text-sm text-gray-600"><span class="text-amber-400 mt-0.5"><?= icon('dot') ?></span><span x-text="tip"></span></li>
                </template>
            </ul>
            <p x-show="result?.note" class="text-xs text-gray-400 mt-4 italic"><?= icon('chats') ?><span x-text="result?.note"></span></p>
        </div>
    </div>
    
    <!-- МОБИЛЬНОЕ МЕНЮ: выбор источника фото -->
    <div x-show="showMobileMenu" x-transition style="display:none" 
         class="sm:hidden fixed inset-0 z-[100] flex items-end justify-center"
         @click.self="showMobileMenu = false">
        <div class="bg-black/40 absolute inset-0"></div>
        <div class="bg-white rounded-t-3xl shadow-2xl w-full max-w-lg p-6 relative z-10"
             style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));">
            
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-gray-800 text-lg"><?= icon('plus') ?> Добавить фото</h3>
                <button @click="showMobileMenu = false" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200">×</button>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-amber-300 transition group">
                    <input type="file" accept="image/*" capture="environment" class="hidden" @change="preview($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-amber-500 transition">
                        <div class="text-3xl mb-1"><?= icon('scaner') ?></div>
                        <div class="text-sm font-semibold">Снять фото</div>
                        <div class="text-xs mt-0.5">Открыть камеру</div>
                    </div>
                </label>
                
                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-amber-300 transition group">
                    <input type="file" accept="image/*" class="hidden" @change="preview($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-amber-500 transition">
                        <div class="text-3xl mb-1"><?= icon('picture') ?></div>
                        <div class="text-sm font-semibold">Из галереи</div>
                        <div class="text-xs mt-0.5">Выбрать файл</div>
                    </div>
                </label>
            </div>
            
            <button @click="showMobileMenu = false" class="mt-4 w-full py-3 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold hover:bg-gray-200 transition">
                Отмена
            </button>
        </div>
    </div>
</section>

<script>
window.__AI_AD_HTML__ = <?= json_encode($aiActionAdHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= url('assets/js/ai/beer-pairing.js') ?>?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>