<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/calorie-scanner');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем заголовок страницы
$pageTitle = $GLOBALS['pageTitle'] ?? 'Сканер калорий — определение КБЖУ по фото | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Сканер калорий';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Загрузите фото блюда — AI распознает его и оценит КБЖУ. Бесплатный сканер калорийности продуктов.';

// Получаем код рекламы для пауз AI
$aiActionAdHtml = get_ad_code('ai_action');

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

$logged = is_logged_in();
require_once __DIR__ . '/../includes/header.php';
?>
<script>
window.__INGREDIENT_UNITS__ = <?= json_encode(ingredient_units_for_js(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10"
         x-data="calorieScanner(<?= $logged ? 'true' : 'false' ?>)" x-init="init()">
    <div class="text-center mb-8" data-aos="fade-up">
        <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold mb-3"><?= icon('star') ?> AI Vision</span>
        <h1 class="text-3xl font-extrabold text-gray-800"><?= icon('scaner') ?> <?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500">Загрузите фото блюда — AI распознает его и оценит КБЖУ.</p>

        <div x-show="ai.limit" x-cloak class="mt-4 inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold"
             :class="ai.remaining > 0 || ai.limit >= 999 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
            <span x-show="ai.limit >= 999"><?= icon('lightning') ?> Безлимитный доступ</span>
            <span x-show="ai.limit < 999 && !ai.is_pro">Осталось <span class="font-extrabold" x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
            <span x-show="ai.limit < 999 && ai.is_pro" style="display:none">Pro · <span x-text="ai.remaining"></span> из <span x-text="ai.limit"></span></span>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8" data-aos="fade-up" data-aos-delay="100">
        
        <!-- ОДНА кнопка "Добавить изображение" -->
        <div class="mb-4">
            <label class="hidden sm:flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center cursor-pointer hover:border-emerald-300 transition group">
                <input type="file" accept="image/*" class="hidden" @change="preview($event)">
                <div class="text-gray-400 group-hover:text-emerald-500 transition flex flex-col items-center">
                    <div class="mb-2 flex justify-center"><?= icon('picture') ?></div>
                    <div class="text-sm font-semibold">Добавить изображение</div>
                    <div class="text-xs mt-1">JPEG, PNG, WebP · до 5 МБ</div>
                </div>
            </label>
            <button @click="showMobileMenu = true" class="sm:hidden w-full border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:border-emerald-300 transition group">
                <div class="text-gray-400 group-hover:text-emerald-500 transition flex flex-col items-center">
                    <div class="mb-2 flex justify-center"><?= icon('picture') ?></div>
                    <div class="text-sm font-semibold">Добавить изображение</div>
                    <div class="text-xs mt-1">Снять фото или выбрать из галереи</div>
                </div>
            </button>
        </div>

        <!-- Превью -->
        <div x-show="image" x-transition style="display:none" class="relative mb-4">
            <img :src="image" class="mx-auto max-h-48 sm:max-h-64 rounded-xl object-contain shadow-md" alt="Фото">
            <button @click="clearImage()" 
                    class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition"
                    aria-label="Удалить изображение">
                <?= icon('close') ?>
            </button>
        </div>

        <div class="mb-4">
            <textarea x-model="description" rows="1"
                      placeholder="Уточните блюдо (необязательно)"
                      @input="
                          let el = $event.target;
                          el.style.height = 'auto'; 
                          el.style.height = (el.scrollHeight) + 'px';
                      "
                      class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-emerald-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 text-sm"
                      style="max-height: 150px; min-height: 46px;"
            ></textarea>
        </div>

        <button @click="scan()" :disabled="loading || (!image && !description.trim())"
            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold hover:shadow-md transition disabled:opacity-60">
                <span x-show="!loading"><?= icon('search') ?> Анализировать по описанию</span>
                <span x-show="loading" style="display:none"><?= icon('hourglass') ?> AI изучает блюдо…</span>
        </button>
        
        <!-- Рекламный блок для анализа -->
        <div x-show="loading" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                    <span>AI изучает блюдо...</span>
                </span>
                <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                    <span x-text="adTimer"></span> сек
                </span>
            </div>
            <div x-html="aiAdHtml" class="p-4"></div>
        </div>
        
        <p x-show="error" style="display:none" class="text-rose-500 text-sm mt-3" x-text="error"></p>
    </div>

    <!-- ═══ РЕЗУЛЬТАТ: ЧЕРНОВИК (фаза B) ═══ -->
<div x-show="draft.dish" x-transition style="display:none"
     class="mt-6 bg-white rounded-3xl shadow-md p-6" data-aos="fade-up">

    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-800">Проверьте распознанное блюдо</h2>
            <p class="text-sm text-gray-400">Отредактируйте название, ингредиенты и вес — потом рассчитаем точные КБЖУ</p>
        </div>
    </div>

    <!-- Название блюда -->
    <label class="block text-sm font-semibold text-gray-600 mb-1">Название блюда</label>
    <input type="text" x-model="draft.dish"
           class="w-full h-11 px-3 rounded-xl border border-gray-200 text-sm mb-4 bg-white">

    <!-- Ингредиенты -->
    <label class="block text-sm font-semibold text-gray-600 mb-1">Ингредиенты</label>
<template x-for="(ing, i) in draft.ingredients" :key="i">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3 items-center">
        
        <!-- Название: на мобильном на всю ширину, на ПК 1-я колонка -->
        <input type="text" x-model="ing.name" placeholder="Название"
               class="col-span-2 sm:col-span-1 h-10 px-3 rounded-xl border border-gray-200 text-sm bg-white w-full">

        <!-- Количество: на мобильном под названием (1-я колонка), на ПК (2-я) -->
        <input type="number" x-model="ing.amount" placeholder="Кол-во" step="any"
               :disabled="ing.unit === 'по вкусу'"
               :class="ing.unit === 'по вкусу' ? 'opacity-40' : ''"
               class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm bg-white">

        <!-- Единица измерения: на мобильном (2-я колонка), на ПК (3-я) -->
        <select x-model="ing.unit" @change="onUnitChange(ing)"
                class="w-full h-10 px-2 rounded-xl border border-gray-200 text-sm bg-white">
            <template x-for="u in units" :key="u.value">
                <option :value="u.value" x-text="u.label"></option>
            </template>
        </select>
        <button type="button" @click="draft.ingredients.splice(i, 1)"
                class="hidden sm:flex w-10 h-10 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 items-center justify-center shrink-0">
            ×
        </button>
    </div>
    
    <!-- Мобильная кнопка удаления (появляется только на мобильных) -->
    <div class="sm:hidden flex justify-end mb-3 -mt-2">
         <button type="button" @click="draft.ingredients.splice(i, 1)"
                class="text-xs text-rose-500 hover:text-rose-700 underline">
            Удалить ингредиент
        </button>
    </div>
</template>
<button type="button" @click="draft.ingredients.push({name:'', amount:'', unit:'г'})"
        class="mt-1 text-sm text-emerald-700 hover:text-emerald-900 font-semibold">
    + Добавить ингредиент
</button>

    <!-- Общий вес -->
    <div class="mt-3 mb-4">
        <label class="block text-sm font-semibold text-gray-600 mb-1">Общий вес порции, г</label>
        <input type="number" x-model.number="draft.total_weight_g" min="1"
               class="w-40 h-11 px-3 rounded-xl border border-gray-200 text-sm bg-white">
        <span class="ml-2 text-xs text-gray-500">нужен для точного расчёта КБЖУ</span>
    </div>

    <!-- Кнопка подтверждения -->
    <button @click="confirmNutrition()" :disabled="confirmingNutrition"
            class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold hover:shadow-md transition disabled:opacity-60">
        <span x-show="!confirmingNutrition" class="inline-flex items-center gap-2">
            <?= icon('check') ?> Подтвердить и рассчитать КБЖУ
        </span>
        <span x-show="confirmingNutrition" style="display:none" class="inline-flex items-center gap-2">
            <?= icon('hourglass') ?> Считаем…
        </span>
    </button>

    <p x-show="confirmError" style="display:none"
       class="text-rose-500 text-sm mt-3" x-text="confirmError"></p>
</div>

<!-- ═══ РЕЗУЛЬТАТ: ПОДТВЕРЖДЕНО (фаза C/D) ═══ -->
<div x-show="result && result.locked" x-transition style="display:none"
     class="mt-6 bg-white rounded-3xl shadow-md p-6" data-aos="fade-up">

    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold text-gray-800" x-text="result?.dish"></h2>
            <p class="text-sm text-gray-400" x-text="result?.portion"></p>
        </div>
        <span class="shrink-0 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-700 inline-flex items-center gap-1">
            <?= icon('check') ?> КБЖУ подтверждены
        </span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
        <div class="bg-amber-50 rounded-xl p-3 text-center">
            <div class="text-2xl font-extrabold text-amber-600" x-text="result?.calories"></div>
            <div class="text-xs text-gray-500">ккал</div>
        </div>
        <div class="bg-emerald-50 rounded-xl p-3 text-center">
            <div class="text-2xl font-extrabold text-emerald-600" x-text="result?.proteins+' г'"></div>
            <div class="text-xs text-gray-500">белки</div>
        </div>
        <div class="bg-rose-50 rounded-xl p-3 text-center">
            <div class="text-2xl font-extrabold text-rose-500" x-text="result?.fats+' г'"></div>
            <div class="text-xs text-gray-500">жиры</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3 text-center">
            <div class="text-2xl font-extrabold text-violet-600" x-text="result?.carbs+' г'"></div>
            <div class="text-xs text-gray-500">углеводы</div>
        </div>
    </div>

    <p x-show="result?.note" style="display:none" class="text-sm text-gray-500 mt-4">
        <?= icon('bulb') ?> <span x-text="result?.note"></span>
    </p>

    <button @click="editDraft()"
            class="mt-4 text-xs text-emerald-700 underline hover:text-emerald-900">
        Изменить распознанное и пересчитать
    </button>

    <div class="mt-5 pt-4 border-t border-gray-100">
        <template x-if="!logged">
            <p class="text-sm text-gray-400"><a href="<?= url('login') ?>" class="text-emerald-600 font-semibold hover:underline">Войдите</a>, чтобы сохранять в дневник и сгенерировать рецепт.</p>
        </template>

        <template x-if="logged && result">
            <div class="mt-3">
                <button @click="generateRecipe()" :disabled="generatingRecipe || recipeSaved"
                        class="w-full py-3 rounded-xl bg-emerald-100 text-emerald-800 font-bold hover:bg-emerald-200 transition disabled:opacity-60">
                    <span x-show="!generatingRecipe && !recipeSaved"><?= icon('whattocook') ?> Сгенерировать рецепт</span>
                    <span x-show="generatingRecipe" style="display:none"><?= icon('hourglass') ?> Создаём и сохраняем…</span>
                    <span x-show="recipeSaved" style="display:none"><?= icon('check') ?> Готово, открываем…</span>
                </button>
                
                <!-- Рекламный блок для генерации рецепта -->
                <div x-show="generatingRecipe" x-transition style="display: none;" class="mt-6 ai-ad-pause-container">
                    <div class="ai-ad-pause-header flex justify-between items-center">
                        <span class="inline-flex items-center gap-1.5">
                            <?= icon('aigenerator', 'gc-icon-xs', 1) ?>
                            <span>Генерируем рецепт из сканирования...</span>
                        </span>
                        <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                            <span x-text="adTimer"></span> сек
                        </span>
                    </div>
                    <div x-html="aiAdHtml" class="p-4"></div>
                </div>

                <p x-show="recipeError" style="display:none"
                   class="text-rose-500 text-sm mt-2 text-center"
                   x-text="recipeError"></p>

                <a x-show="recipeUrl && !recipeSaved" :href="recipeUrl"
                   class="mt-2 block w-full text-center py-3 rounded-xl bg-gradient-to-r from-emerald-300 to-teal-300 text-emerald-900 font-bold hover:shadow-md transition">
                    <?= icon('check') ?> Открыть рецепт
                </a>
            </div>
        </template>
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
                <h3 class="font-extrabold text-gray-800 text-lg"><?= icon('scaner') ?> Добавить изображение</h3>
                <button @click="showMobileMenu = false" 
                        class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 transition"
                        aria-label="Закрыть">
                    <?= icon('close') ?>
                </button>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <!-- Снять фото -->
                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-emerald-300 transition group">
                    <input type="file" accept="image/*" capture="environment" class="hidden" @change="preview($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-emerald-500 transition">
                        <div class="mb-1 flex justify-center"><?= icon('scaner') ?></div>
                        <div class="text-sm font-semibold">Снять фото</div>
                        <div class="text-xs mt-0.5">Открыть камеру</div>
                    </div>
                </label>
                
                <!-- Из галереи -->
                <label class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-emerald-300 transition group">
                    <input type="file" accept="image/*" class="hidden" @change="preview($event); showMobileMenu = false">
                    <div class="text-gray-400 group-hover:text-emerald-500 transition">
                        <div class="mb-1 flex justify-center"><?= icon('image') ?></div>
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
function calorieScanner(isLogged) {
    return {
        logged: isLogged,

        draft: { dish: '', ingredients: [], total_weight_g: 350, confidence: '', image_url: null, scan_id: null },
        units: window.__INGREDIENT_UNITS__ || [],

        onUnitChange(ing) {
            if (ing.unit === 'по вкусу') {
                ing.amount = '';
            } else if (!ing.amount) {
                if (['г', 'мл'].includes(ing.unit)) ing.amount = '100';
                else ing.amount = '1';
            }
        },

        result: null,
        description: '',
        image: null,
        source: '',
        loading: false,
        error: '',

        confirmingNutrition: false,
        confirmError: '',

        generatingRecipe: false,
        recipeSaved: false,
        recipeUrl: '',
        recipeError: '',

        ai: {},
        showMobileMenu: false,

        // ─── Реклама ───
        aiAdHtml: window.__AI_AD_HTML__ || '',
        adTimer: 0,

        init() {
            fetch('/api/ai_status.php?feature=calorie')
                .then(r => r.json())
                .then(r => { this.ai = r.ai || {}; })
                .catch(() => {});
        },

        preview(e) {
            var file = e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { this.error = 'Файл больше 5 МБ'; return; }
            if (!file.type.startsWith('image/')) { this.error = 'Выберите изображение'; return; }

            this.error = '';
            var reader = new FileReader();
            reader.onload = (ev) => this.compressAndSet(ev.target.result, file.type);
            reader.onerror = () => { this.error = 'Ошибка чтения файла'; };
            reader.readAsDataURL(file);
            e.target.value = '';
        },

        compressAndSet(dataUrl, mimeType) {
            var img = new Image();
            img.onload = () => {
                var canvas = document.createElement('canvas');
                var w = img.width, h = img.height;
                var max = 800;
                if (w > max || h > max) {
                    if (w > h) { h = Math.round(h * max / w); w = max; }
                    else { w = Math.round(w * max / h); h = max; }
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                this._resetState();
                this.image = canvas.toDataURL('image/jpeg', 0.75);
            };
            img.onerror = () => {
                this._resetState();
                this.image = dataUrl;
            };
            img.src = dataUrl;
        },

        clearImage() {
            this.image = null;
            this._resetState();
        },

        _resetState() {
            this.draft = { dish: '', ingredients: [], total_weight_g: 350, confidence: '', image_url: null, scan_id: null };
            this.result = null;
            this.error = '';
            this.confirmError = '';
            this.recipeSaved = false;
            this.recipeUrl = '';
            this.recipeError = '';
        },

        // ─── Распознавание ───
        async scan() {
            if (this.ai.limit && this.ai.remaining === 0) {
                toast('Лимит исчерпан', 'error');
                return;
            }

            this._resetState();
            this.loading = true;
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let fetchedDraft = null;

            try {
                var payload = {};
                if (this.image) payload.image = this.image;
                if (this.description.trim()) payload.description = this.description.trim();

                // --- ВАЖНО: Используем fetch, как в what-to-cook ---
                const res = await fetch('/api/ai_calorie_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.error || 'HTTP ' + res.status);
                }

                const data = await res.json();
                if (data.ai) this.ai = data.ai;

                var r = data.result || {};
                var weight = Math.max(1, parseInt(r.total_weight_g || r.estimated_weight_g || 0, 10) || 350);

                fetchedDraft = {
                    dish: r.dish || 'Блюдо',
                    ingredients: this._normalizeIngredients(r.ingredients || []),
                    total_weight_g: weight,
                    confidence: r.confidence || 'средняя',
                    image_url: r.image_url || data.image_url || null,
                    scan_id: data.scan_id || null,
                };
                this.source = data.source || 'text';
            } catch (e) {
                this.error = e.message || 'Не удалось проанализировать';
            }

            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.loading = false;
            this.adTimer = 0;

            if (fetchedDraft) {
                this.draft = fetchedDraft;
            }
        },

        _normalizeIngredients(list) {
            const KNOWN = this.units.map(u => u.value);
            const out = [];
            for (const it of (list || [])) {
                if (typeof it === 'string') {
                    out.push({ name: it, amount: '', unit: 'г' });
                } else if (it && it.name) {
                    let amount = it.amount != null ? String(it.amount) : '';
                    let unit = it.unit != null ? String(it.unit) : '';
                    if (/по\s+вкусу/i.test(amount)) { amount = ''; unit = 'по вкусу'; }
                    if (unit && !KNOWN.includes(unit)) unit = 'г';
                    if (!unit) unit = 'г';
                    out.push({ name: String(it.name), amount, unit });
                }
            }
            return out;
        },

        // ─── Расчёт КБЖУ ───
        async confirmNutrition() {
            if (!this.draft) return;
            this.confirmingNutrition = true;
            this.confirmError = '';

            try {
                const res = await fetch('/api/ai_confirm_nutrition.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: this.draft.dish,
                        ingredients: this.draft.ingredients,
                        servings: 1,
                        total_weight_g: this.draft.total_weight_g,
                        scan_id: this.draft.scan_id,
                    })
                });

                const data = await res.json();

                if (!data.ok || !data.nutrition) {
                    throw new Error(data.error || 'Не удалось рассчитать КБЖУ');
                }

                var n = data.nutrition;
                this.result = {
                    dish:        this.draft.dish,
                    portion:     '1 порция ' + this.draft.total_weight_g + ' г',
                    ingredients: this.draft.ingredients,
                    total_weight_g: this.draft.total_weight_g,
                    calories:    n.calories,
                    proteins:    n.proteins,
                    fats:        n.fats,
                    carbs:       n.carbs,
                    note:        n.note || '',
                    confidence:  this.draft.confidence,
                    image_url:   this.draft.image_url,
                    scan_id:     this.draft.scan_id,
                    locked:      true,
                };
                this.draft = { dish: '', ingredients: [], total_weight_g: 350, confidence: '', image_url: null, scan_id: null };

                toast('КБЖУ рассчитаны', 'success');
            } catch (e) {
                this.confirmError = e.message || 'Ошибка расчёта';
                toast(this.confirmError, 'error');
            } finally {
                this.confirmingNutrition = false;
            }
        },

        editDraft() {
            if (!this.result) return;
            this.draft = {
                dish:           this.result.dish,
                ingredients:    this.result.ingredients || [],
                total_weight_g: this.result.total_weight_g || 350,
                confidence:     this.result.confidence,
                image_url:      this.result.image_url,
                scan_id:        this.result.scan_id,
            };
            this.result = null;
            this.recipeSaved = false;
            this.recipeUrl = '';
            this.recipeError = '';
        },

        // ─── Генерация рецепта ───
        async generateRecipe() {
            if (!this.result || this.generatingRecipe || this.recipeSaved) return;

            this.generatingRecipe = true;
            this.recipeError = '';
            this.recipeUrl = '';
            this.recipeSaved = false;
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let savedUrl = '';
            let isSaved = false;
            let isDuplicate = false;
            let errorMsg = '';

            try {
                const genRes = await fetch('/api/ai_scan_to_recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dish:        this.result.dish,
                        portion:     this.result.portion,
                        ingredients: this.result.ingredients || [],
                        calories:    this.result.calories,
                        proteins:    this.result.proteins,
                        fats:        this.result.fats,
                        carbs:       this.result.carbs,
                        image_url:   this.result.image_url || null,
                    })
                });

                const gen = await genRes.json();
                if (!gen || !gen.ok || !gen.recipe) {
                    throw new Error(gen.error || 'Не удалось сгенерировать рецепт');
                }

                const saveRes = await fetch('/api/save_recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(gen.recipe)
                });

                const save = await saveRes.json();

                if (save && save.ok && save.redirect_url) {
                    savedUrl = save.redirect_url;
                    isSaved = true;
                } else if (save && save.duplicate && save.redirect_url) {
                    savedUrl = save.redirect_url;
                    isDuplicate = true;
                } else {
                    throw new Error((save && save.error) || 'Не удалось сохранить рецепт');
                }
            } catch (e) {
                errorMsg = e.message || 'Ошибка генерации рецепта';
            }

            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.generatingRecipe = false;
            this.adTimer = 0;

            if (errorMsg) {
                this.recipeError = errorMsg;
                toast(this.recipeError, 'error');
            } else if (isSaved || isDuplicate) {
                this.recipeUrl = savedUrl;
                this.recipeSaved = true;
                toast(isDuplicate ? '✓ Найден похожий рецепт' : '✓ Рецепт сохранён', 'success');
                setTimeout(function () { window.location.href = savedUrl; }, 800);
            }
        },
    };
}
</script>
<script>
window.__AI_AD_HTML__ = <?= json_encode($aiActionAdHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>