<?php
require_once __DIR__ . '/../config/config.php';
$bookId = (int)($_GET['book_id'] ?? 0);
require_login();
$pageTitle = 'Создать рецепт';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
</style>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10" x-data="createRecipe()">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6" data-aos="fade-up">Создать рецепт</h1>

    <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8 space-y-5" data-aos="fade-up" data-aos-delay="100">
        <!-- Название -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Название *</label>
            <input type="text" x-model="r.title" 
                   class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300" required>
        </div>
        
        <!-- Описание -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-1">Описание</label>
            <textarea x-model="r.description" rows="2" 
                      class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 resize-none"></textarea>
        </div>
        
        <!-- ✅ Фото: URL или загрузка -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">
                Фото
                <span class="text-gray-400 font-normal">— необязательно</span>
            </label>
            
            <!-- Превью -->
            <div x-show="r.image_url" class="relative mb-3">
                <img :src="r.image_url" class="w-full max-h-48 object-cover rounded-xl shadow-md" alt="Превью">
                <button @click="r.image_url = ''" 
                        class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition inline-flex items-center justify-center"
                        aria-label="Убрать фото">
                    <?= icon('close') ?>
                </button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- URL -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ссылка на изображение</label>
                    <input type="url" x-model="r.image_url" 
                           placeholder="https://example.com/photo.jpg"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm">
                </div>
                
                <!-- Загрузка -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Или загрузите с устройства</label>
                    <label class="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-amber-300 transition text-sm text-gray-500">
                        <input type="file" accept="image/*" class="hidden" @change="uploadImage($event)">
                        <?= icon('image') ?>
                        <span>Выбрать файл</span>
                    </label>
                </div>
            </div>
            
            <p class="text-xs text-gray-400 mt-2">
                По умолчанию используется стандартное изображение. Поддерживаются JPEG, PNG, WebP до 5MB.
            </p>
        </div>

        <!-- ✅ ВИДЕО: Загрузка на Rutube -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2 inline-flex items-center gap-1">
                <?= icon('video') ?>
                <span>Видео рецепта</span>
                <span class="text-gray-400 font-normal">— необязательно</span>
            </label>
            
            <!-- Превью видео -->
            <div x-show="r.video_url" class="relative mb-3 bg-gray-50 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                        <?= icon('video') ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-700">Видео загружено на Rutube</p>
                        <a :href="r.video_url" target="_blank" class="text-xs text-emerald-600 hover:underline">Открыть видео</a>
                    </div>
                    <button @click="removeVideo()" 
                            class="text-rose-400 hover:text-rose-600 transition inline-flex items-center justify-center"
                            aria-label="Убрать видео">
                        <?= icon('close') ?>
                    </button>
                </div>
            </div>
            
            <!-- Загрузка видео -->
            <div x-show="!r.video_url">
                <label class="flex items-center justify-center gap-2 w-full px-4 py-4 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-emerald-300 transition text-sm text-gray-500"
                       :class="{ 'opacity-50 cursor-not-allowed': uploadingVideo }">
                    <input type="file" accept="video/*" class="hidden" @change="uploadVideo($event)" :disabled="uploadingVideo">
                    <template x-if="!uploadingVideo">
                        <span class="inline-flex items-center gap-2">
                            <?= icon('video') ?>
                            <span>Выбрать видео для загрузки</span>
                        </span>
                    </template>
                    <template x-if="uploadingVideo">
                        <span class="inline-flex items-center gap-2">
                            <?= icon('hourglass') ?>
                            <span>Загрузка видео...</span>
                            <span x-text="uploadProgress + '%'"></span>
                        </span>
                    </template>
                </label>
                <p class="text-xs text-gray-400 mt-2">
                    Видео будет автоматически загружено на Rutube и встроено в рецепт. Поддерживаются MP4, MOV до 500MB.
                </p>
            </div>
            <!-- ИЛИ вставка прямой ссылки -->
            <div class="mt-3">
                <label class="block text-xs text-gray-500 mb-1">Или вставьте прямую ссылку (YouTube, Rutube, Vimeo)</label>
                <input type="url" x-model="r.video_url" placeholder="https://rutube.ru/video/..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
        </div>

        <!-- Категория + Сложность + Время + Порции -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <label class="text-sm text-gray-600">Категория</label>
                <select x-model="r.category_slug"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    <option value="">Выбрать...</option>
                    <template x-for="c in allCategories" :key="c.slug">
                        <option :value="c.slug" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600">Сложность</label>
                <select x-model="r.difficulty"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    <option value="easy">Легко</option>
                    <option value="medium">Средне</option>
                    <option value="hard">Сложно</option>
                    <option value="master">Мастер-класс</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600">Порций</label>
                <input type="number" x-model="r.servings" min="1"
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">Подготовка (мин)</label>
                <input type="number" x-model="r.prep_time" min="0"
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">Готовка (мин)</label>
                <input type="number" x-model="r.cook_time" min="0"
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
        </div>

        <!-- КБЖУ -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="text-sm text-gray-600">Ккал</label>
                <input type="number" x-model="r.calories" min="0" 
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">Белки (г)</label>
                <input type="number" x-model="r.proteins" min="0" 
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">Жиры (г)</label>
                <input type="number" x-model="r.fats" min="0" 
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">Углеводы (г)</label>
                <input type="number" x-model="r.carbs" min="0" 
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
            </div>
        </div>

        <!-- Теги по группам -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Теги</label>

            <!-- Выбранные теги -->
            <div class="flex flex-wrap gap-1 mb-3 min-h-[28px]">
                <template x-for="slug in r.tag_slugs" :key="slug">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                        <template x-if="getTagIconHtml(slug)">
                            <span x-html="getTagIconHtml(slug)"></span>
                        </template>
                        <span x-text="getTagNameBySlug(slug)"></span>
                        <button type="button" @click="toggleTagSlug(slug)" 
                                class="hover:text-rose-500 transition inline-flex items-center justify-center"
                                aria-label="Убрать тег">
                            <?= icon('close') ?>
                        </button>
                    </span>
                </template>
                <template x-if="r.tag_slugs.length === 0">
                    <span class="text-xs text-gray-400 py-1">Выберите теги ниже или создайте свои</span>
                </template>
            </div>

            <!-- Селекты по группам -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="grp in tagGroups" :key="grp.group">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">
                            <span x-html="grp.icon_svg_html || grp.icon"></span>
                            <span x-text="grp.label"></span>
                        </label>
                        <select @change="onGroupSelect(grp.group, $event.target.value)"
                                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                            <option value="">Не выбрано</option>
                            <template x-for="t in grp.tags" :key="t.slug">
                                <option :value="t.slug"
                                        :selected="isGroupSelected(grp.group, t.slug)"
                                        x-text="t.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>

            <!-- Создание нового тега -->
            <details class="mt-3 bg-gray-50 rounded-xl p-3">
                <summary class="cursor-pointer text-sm font-medium text-gray-700 select-none inline-flex items-center gap-1">
                    <?= icon('plus') ?>
                    <span>Создать новый тег</span>
                </summary>
                <div class="mt-2 flex gap-2">
                    <input type="text" x-model="newTagName"
                           @keydown.enter.prevent="createTag()"
                           placeholder="Например: без сахара"
                           class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm">
                    <button type="button" @click="createTag()"
                            class="px-4 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200 transition">
                        Создать
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Тег создаётся в общем списке с группой «other»</p>
            </details>
        </div>

        <!-- Ингредиенты -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Ингредиенты</label>
            <template x-for="(ing, i) in r.ingredients" :key="i">
                <div class="flex gap-2 mb-2">
                    <input x-model="ing.name" placeholder="Название" 
                           class="flex-1 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                    <input x-model="ing.amount" placeholder="Кол-во" 
                           class="w-24 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                    <input x-model="ing.unit" placeholder="Ед." 
                           class="w-20 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                    <label class="flex items-center gap-1 text-xs text-gray-400">
                        <input type="checkbox" x-model="ing.is_optional"> опционально
                    </label>
                    <button @click="r.ingredients.splice(i,1)" 
                            class="px-2 text-rose-300 hover:text-rose-500 transition inline-flex items-center justify-center"
                            aria-label="Удалить ингредиент">
                        <?= icon('close') ?>
                    </button>
                </div>
            </template>
            <button @click="r.ingredients.push({name:'',amount:'',unit:'',is_optional:false})" 
                    class="text-sm text-amber-600 font-semibold inline-flex items-center gap-1">
                <?= icon('plus') ?>
                <span>Добавить ингредиент</span>
            </button>
        </div>

        <!-- Шаги -->
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Шаги приготовления</label>
            <template x-for="(st, i) in r.steps" :key="i">
                <div class="flex gap-2 mb-2 items-start">
                    <span class="mt-2 text-gray-400 font-bold" x-text="(i+1)+'.'"></span>
                    <div class="flex-1 space-y-1">
                        <textarea x-model="st.instruction" rows="2" placeholder="Что делать" 
                                  class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm resize-none"></textarea>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none inline-flex items-center">
                                <?= icon('info') ?>
                            </span>
                            <input x-model="st.tip" placeholder="Совет (необязательно)" 
                                   class="w-full pl-10 pr-3 py-2 rounded-lg border border-gray-200 outline-none text-xs">
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <input type="number" x-model="st.timer_minutes" placeholder="мин" 
                               class="w-20 px-2 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                        <button @click="r.steps.splice(i,1)" 
                                class="text-rose-300 hover:text-rose-500 transition inline-flex items-center justify-center"
                                aria-label="Удалить шаг">
                            <?= icon('close') ?>
                        </button>
                    </div>
                </div>
            </template>
            <button @click="r.steps.push({order:r.steps.length+1,instruction:'',timer_minutes:0,tip:''})" 
                    class="text-sm text-amber-600 font-semibold inline-flex items-center gap-1">
                <?= icon('plus') ?>
                <span>Добавить шаг</span>
            </button>
        </div>

        <button @click="save()" :disabled="saving || uploadingVideo"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
            <span x-show="!saving" class="inline-flex items-center gap-2">
                <?= icon('diskette') ?>
                <span>Опубликовать рецепт</span>
            </span>
            <span x-show="saving" style="display:none" class="inline-flex items-center gap-2">
                <?= icon('hourglass') ?>
                <span>Сохраняем…</span>
            </span>
        </button>
        <p x-show="error" style="display:none" class="text-rose-500 text-sm text-center" x-text="error"></p>
    </div>
</section>

<script>
    // ✅ Передаем ID книги в JavaScript
    window.__CURRENT_BOOK_ID__ = <?= (int)$bookId ?>;
</script>
<script src="<?= url('assets/js/recipe/create-recipe.js') ?>?v=6"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>