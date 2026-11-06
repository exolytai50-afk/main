<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/cookbook_helpers.php';
require_once __DIR__ . '/../includes/cookbook_generator_helpers.php';
require_once __DIR__ . '/../includes/ads.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$aiAdBundle = ad_bundle_json('ai_action');
$user       = current_user();
$userId     = (int)$user['id'];
$myBooks    = get_user_cookbooks($userId);

// Получаем категории с иконками
$categories = get_all_categories();
$catsOut = [];
foreach ($categories as $c) {
    $catsOut[] = [
        'slug' => $c['slug'],
        'name' => $c['name'],
        'icon' => icon($c['icon_svg'] ?: 'cookbook', 'gc-icon-xs', 1)
    ];
}

// Получаем теги для времени
$timeTags = get_all_tags_by_group()['time'] ?? [];

$pageTitle = 'AI-генератор рецептов для книги | GigaChef';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Красивые кнопки режимов */
.cb-mode-btn {
    background-color: var(--bg-card); border: 2px solid var(--border-main);
    color: var(--text-main); transition: all 0.2s;
}
.cb-mode-btn-active {
    background-color: rgba(139, 92, 246, 0.1); border-color: #8b5cf6; color: #6d28d9;
}
html.theme-dark .cb-mode-btn-active {
    background-color: rgba(139, 92, 246, 0.2); color: #c4b5fd;
}
</style>

<section class="max-w-4xl mx-auto px-4 sm:px-6 py-10" x-data="cookbookWizard()" x-init="init()">

    <div class="text-center mb-8">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 inline-flex items-center gap-2">
            <?= icon('cookbook', 'gc-icon-lg', 1.2) ?> <span>Генератор рецептов для книги</span>
        </h1>
        <p class="mt-2 text-gray-500">Создайте новую книгу или дополните существующую</p>
    </div>

    <!-- Описание -->
    <div class="mb-6 p-4 bg-violet-50 rounded-2xl border border-violet-200 text-sm text-violet-800">
        <p class="font-bold mb-2">Как это работает:</p>
        <ul class="space-y-1 list-disc pl-5 text-violet-700">
            <li>Введите <b>ключевые слова</b> (обязательно): «суп, курица» — через запятую варианты, через пробел — вместе.</li>
            <li>Сначала ищем готовые рецепты в базе. Если их не хватает — AI доберёт недостающие по <b>названию книги</b>, категории, времени и пожеланиям.</li>
            <li><b>Время</b> — это общее время (подготовка + готовка), оно не будет превышено.</li>
        </ul>
    </div>

    <!-- Форма -->
    <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8 space-y-4">

        <!-- Режим -->
        <div class="grid grid-cols-2 gap-2 sm:gap-3 mb-4">
            <button type="button" @click="mode='new'; form.book_id=''" 
                :class="mode==='new' ? 'cb-mode-btn-active' : 'cb-mode-btn'"
                class="cb-mode-btn flex-1 min-w-0 py-3 px-2 rounded-xl font-bold flex items-center justify-center gap-1.5 sm:gap-2 text-xs sm:text-sm leading-tight text-center">
                <?= icon('openbook', 'gc-icon-sm', 1) ?> Новая книга
            </button>
            <button type="button" @click="mode='existing'" 
                :class="mode==='existing' ? 'cb-mode-btn-active' : 'cb-mode-btn'"
                class="cb-mode-btn flex-1 min-w-0 py-3 px-2 rounded-xl font-bold flex items-center justify-center gap-1.5 sm:gap-2 text-xs sm:text-sm leading-tight text-center">
                <?= icon('bookmark', 'gc-icon-sm', 1) ?> Дополнить книгу
            </button>
        </div>

        <!-- Выбор книги -->
        <div x-show="mode==='existing'" style="display:none">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Выберите книгу</label>
            <select x-model="form.book_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none">
                <option value="">— Выберите книгу —</option>
                <?php foreach ($myBooks as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= e($b['title']) ?> (<?= count(json_field($b['recipes_json'])) ?> рец.)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Название -->
        <div x-show="mode==='new'" style="display:none">
            <label class="block text-sm font-semibold text-gray-600 mb-1">Название книги <span class="text-rose-500">*</span></label>
            <input type="text" x-model="form.title" maxlength="200" placeholder="Например: Супы и бульоны"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300">
            <p class="text-xs text-gray-400 mt-1">По названию AI подбирает блюда, если в базе их не хватит.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Категория -->
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Категория</label>
                <select x-model="form.category" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none">
                    <option value="">Все категории</option>
                    <template x-for="c in categories" :key="c.slug">
                        <option :value="c.slug" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <!-- Время -->
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Общее время <span class="font-normal text-gray-400">(не больше)</span></label>
                <select x-model="form.time" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none">
                    <option value="">Любое</option>
                    <?php foreach ($timeTags as $t): ?>
                        <option value="<?= e($t['slug']) ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Ключевые слова -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Ключевые слова <span class="text-rose-500">*</span></label>
            <input type="text" x-model="form.keywords" maxlength="200" placeholder="Например: суп, курица, борщ"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300">
            <p class="text-xs text-gray-400 mt-1">Через запятую — разные варианты, через пробел — слова вместе.</p>
        </div>

        <!-- Количество и Пожелания -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-1">
                <label class="block text-sm font-semibold text-gray-600 mb-1">Сколько</label>
                <select x-model="form.count" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none">
                    <option value="3">+3</option>
                    <option value="5">+5</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-gray-600 mb-1">Пожелания</label>
                <input type="text" x-model="form.notes" maxlength="300" placeholder="Например: без глютена, низкокалорийные"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300">
            </div>
        </div>

        <!-- Кнопка -->
        <button type="button" @click="addRecipes()" :disabled="loading || !canSubmit"
            class="mt-4 w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
            <span x-show="!loading" class="inline-flex items-center gap-2">
                <?= icon('ai', 'gc-icon-sm', 1) ?> <span>Добавить рецепты</span>
            </span>
            <span x-show="loading" style="display:none" class="inline-flex items-center gap-2">
                <?= icon('hourglass', 'gc-icon-sm', 1) ?> <span x-text="stepText || 'Подбираем…'"></span>
            </span>
        </button>

        <!-- Реклама -->
        <div x-show="adVisible" style="display:none" class="mt-4 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center">
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                    <span x-text="stepText || 'Подождите…'"></span>
                </span>
                <span class="text-amber-600 font-bold text-xs"><span x-text="adTimer"></span> сек</span>
            </div>
            <div x-html="adHtml" class="p-4"></div>
            <div x-show="!adHtml" class="p-4 text-sm text-gray-400 text-center">Реклама загружается…</div>
        </div>

        <p x-show="error" style="display:none" class="text-rose-500 text-sm" x-text="error"></p>
        <p x-show="success" style="display:none" class="text-emerald-600 text-sm font-semibold" x-text="success"></p>
    </div>
</section>
<script src="<?= e(url('assets/js/ad-pause.js')) ?>?v=<?= time() ?>"></script>
<script src="<?= e(url('assets/js/cookbook-fill.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/cookbook-fill.js') ?>"></script>
<script>
window.__AI_AD__ = <?= json_encode($aiAdBundle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__CB_CATEGORIES__ = <?= json_encode($catsOut, JSON_UNESCAPED_UNICODE) ?>;

function cookbookWizard() {
    return {
        mode: 'new',
        loading: false,
        error: '',
        success: '',
        stepText: '',
        bookId: 0,
        categories: window.__CB_CATEGORIES__ || [],
        
        ...gcAdPause(10000),

        form: {
            book_id: '',
            title: '',
            category: '',
            time: '',
            keywords: '',
            notes: '',
            count: '3'
        },

        init() {},

        get canSubmit() {
            if (!this.form.keywords.trim()) return false;
            if (this.mode === 'new') return this.form.title.trim().length >= 3;
            return !!this.form.book_id;
        },

        async addRecipes() {
            if (this.loading) return;
            const kw = this.form.keywords.trim();
            if (!kw) { this.error = 'Укажите ключевые слова'; return; }
            if (this.mode === 'new' && this.form.title.trim().length < 3) { this.error = 'Введите название книги (минимум 3 символа)'; return; }
            if (this.mode === 'existing' && !this.form.book_id) { this.error = 'Выберите книгу'; return; }

            this.error = '';
            this.success = '';
            this.loading = true;
            const tags = { category: this.form.category, time: this.form.time };
            const count = parseInt(this.form.count) || 3;
            let createdBook = null;   // книга, созданная в этом запуске (для очистки, если ничего не добавилось)
            let target = null;        // { id, url }

            try {
                const result = await this.adHold((async () => {
                    let preFound;
                    if (this.mode === 'new') {
                        // 1) сначала ищем в базе — книга ещё не создаётся
                        this.stepText = 'Ищем в базе…';
                        const s = await cbFill.api('search', { keywords: kw, tags, limit: count });
                        const ids = (s.candidates || []).map(c => c.recipe_id);
                        // 2) создаём книгу сразу с найденными рецептами
                        this.stepText = 'Создаём книгу…';
                        const c = await cbFill.api('create', { title: this.form.title.trim(), theme: this.form.title.trim(), link_ids: ids });
                        createdBook = c.book.id;
                        target = { id: c.book.id, url: c.url };
                        preFound = c.linked || 0;
                    } else {
                        const b = await cbFill.api('get', { book_id: parseInt(this.form.book_id) });
                        target = { id: b.book.id, url: b.url };
                    }

                    const r = await cbFill.fillBook({
                        bookId: target.id, keywords: kw, tags, notes: this.form.notes, count,
                        onStep: t => { this.stepText = t; }
                    }, preFound);

                    if (r.total === 0 && createdBook) {
                        try { await cbFill.api('discard', { book_id: createdBook }); } catch (e) {}
                        createdBook = null;
                        throw new Error(r.errors[0] || 'Не удалось подобрать рецепты. Измените запрос и попробуйте ещё раз.');
                    }
                    return r;
                })());

                const msg = cbFill.summary(result);
                if (result.total === 0) {
                    this.error = result.errors.length ? ('❌ ' + result.errors[0]) : 'Новых рецептов не найдено: все подходящие уже есть в книге. Измените запрос.';
                } else {
                    this.success = '✅ Готово' + (msg ? ' — ' + msg : '') + '. Открываем книгу…';
                    // редирект ПОСЛЕ рекламной паузы, всегда на slug (а не id — по id страница не находилась)
                    setTimeout(() => { location.href = target.url; }, 900);
                }
            } catch (e) {
                this.error = '❌ ' + (e.message || 'Ошибка');
            } finally {
                this.loading = false;
                this.stepText = '';
            }
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>