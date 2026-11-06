<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$pageTitle = 'Управление рецептами';
require_once __DIR__ . '/../includes/header.php';

// Иконки типов питания (рендерятся на сервере, подставляются в Alpine через x-html)
$dietIconNames = [
    'vegan' => 'diet-vegan', 'vegetarian' => 'diet-vegetarian', 'pescatarian' => 'fish', 'keto' => 'diet-keto',
    'glutenfree' => 'diet-glutenfree', 'lactosefree' => 'diet-lactosefree', 'lowcarb' => 'diet-lowcarb',
    'lowcal' => 'diet-lowcal', 'highprotein' => 'diet-highprotein', 'omnivore' => 'steak',
];
$dietIcons = [];
foreach ($dietIconNames as $k => $n) $dietIcons[$k] = icon($n, 'gc-icon-sm');
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminRecipes()" x-init="load()">
    <?php admin_top('recipes', 'cookbook', 'Рецепты', 'Редактирование, удаление, модерация'); ?>

    <!-- Поиск и фильтры -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-3">
            <input type="text" x-model="search" @keydown.enter="load()" placeholder="Поиск по названию..."
                   class="<?= admin_cls('input') ?> flex-1 min-w-[200px]">
            <select x-model="filter" @change="load()" class="<?= admin_cls('input') ?> !w-auto">
                <option value="all">Все</option>
                <option value="ai">AI-рецепты</option>
                <option value="user">От пользователей</option>
            </select>
            <button @click="load()" class="<?= admin_cls('btn btn-soft') ?>"><?= icon('search', 'gc-icon-sm') ?>Найти</button>
        </div>
    </div>

    <!-- Таблица рецептов -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Изобр.</th>
                    <th class="<?= admin_cls('th') ?>">Название</th>
                    <th class="<?= admin_cls('th') ?>">Автор</th>
                    <th class="<?= admin_cls('th') ?>">Кухня</th>
                    <th class="<?= admin_cls('th') ?>">Видео</th>
                    <th class="<?= admin_cls('th') ?>">Дата</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="r in recipes" :key="r.id">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-sm" x-text="r.id"></td>
                        <td class="px-4 py-3">
                            <img :src="r.image_url || '/uploads/static/static.jpg'" 
                                 onerror="this.src='/uploads/static/static.jpg'"
                                 class="w-10 h-10 rounded object-cover"
                                 :alt="r.title">
                        </td>
                        <td class="px-4 py-3">
                            <a :href="'/recipe/' + (r.slug || r.id)" class="text-sm font-semibold text-gray-700 hover:text-amber-600" x-text="r.title"></a>
                            <span x-show="r.is_ai_generated" class="ml-1 text-xs text-violet-500">AI</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="r.author_email || '—'"></td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="r.cuisine || '—'"></td>
                        <td class="px-4 py-3">
                            <span x-show="r.video_url" class="text-emerald-500 inline-flex"><?= icon('video', 'gc-icon-md') ?></span>
                            <span x-show="!r.video_url" class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="formatDate(r.created_at)"></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button @click="editRecipe(r)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                            <button @click="deleteRecipe(r.id)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно: ПОЛНОЕ редактирование рецепта -->
    <div x-show="editing" style="display:none" class="<?= admin_cls('overlay') ?>" @click.self="editing=null">
        <div class="<?= admin_cls('modal') ?> max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">
                    Редактировать рецепт #<span x-text="editForm.id"></span>
                </h3>
                <button @click="editing=null" class="<?= admin_cls('ibtn') ?> text-gray-400 hover:bg-gray-100" aria-label="Закрыть"><?= icon('close', 'gc-icon-md') ?></button>
            </div>
            
            <div class="space-y-5">
                <!-- Название -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Название *</label>
                    <input type="text" x-model="editForm.title" class="<?= admin_cls('input') ?>">
                </div>
                
                <!-- Описание -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Описание</label>
                    <textarea x-model="editForm.description" rows="3" class="<?= admin_cls('input') ?> resize-none"></textarea>
                </div>
                
                <!-- Изображение: URL или загрузка -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Изображение</label>
                    
                    <!-- Превью -->
                    <div x-show="editForm.image_url" class="relative mb-3">
                        <img :src="editForm.image_url" class="w-full max-h-48 object-cover rounded-xl shadow-md" alt="Превью">
                        <button @click="editForm.image_url = ''" aria-label="Убрать изображение"
                                class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition text-rose-500 inline-flex">
                            <?= icon('close', 'gc-icon-sm') ?>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- URL -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Ссылка на изображение</label>
                            <input type="url" x-model="editForm.image_url" 
                                   placeholder="https://example.com/photo.jpg"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        
                        <!-- Загрузка с устройства -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Загрузить с устройства</label>
                            <label class="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-amber-300 transition text-sm text-gray-500">
                                <input type="file" accept="image/*" class="hidden" @change="uploadEditImage($event)">
                                <?= icon('picture', 'gc-icon-md') ?>
                                <span x-text="editForm.image_url ? 'Заменить фото' : 'Выбрать файл'"></span>
                            </label>
                        </div>
                    </div>
                    
                    <p class="text-xs text-gray-400 mt-2">По умолчанию используется стандартное изображение. JPEG, PNG, WebP до 5MB.</p>
                </div>
                
                <!-- ВИДЕО: Загрузка на Rutube -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2 inline-flex items-center gap-1.5">
                        <?= icon('video', 'gc-icon-sm') ?> Видео рецепта
                        <span class="text-gray-400 font-normal">— необязательно</span>
                    </label>
                    
                    <!-- Превью видео -->
                    <div x-show="editForm.video_url" class="relative mb-3 bg-gray-50 rounded-xl p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><?= icon('video', 'gc-icon-lg') ?></div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-gray-700">Видео загружено на Rutube</p>
                                <a :href="editForm.video_url" target="_blank" class="text-xs text-emerald-600 hover:underline">Открыть видео</a>
                            </div>
                            <button @click="editForm.video_url = ''" aria-label="Убрать видео"
                                    class="text-rose-400 hover:text-rose-600 transition inline-flex">
                                <?= icon('close', 'gc-icon-md') ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Загрузка видео -->
                    <div x-show="!editForm.video_url">
                        <label class="flex items-center justify-center gap-2 w-full px-4 py-4 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-emerald-300 transition text-sm text-gray-500"
                               :class="{ 'opacity-50 cursor-not-allowed': uploadingVideo }">
                            <input type="file" accept="video/*" class="hidden" @change="uploadEditVideo($event)" :disabled="uploadingVideo">
                            <template x-if="!uploadingVideo">
                                <span class="flex items-center gap-2">
                                    <?= icon('video', 'gc-icon-md') ?>
                                    Выбрать видео для загрузки
                                </span>
                            </template>
                            <template x-if="uploadingVideo">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Загрузка видео... <span x-text="uploadProgress + '%'"></span>
                                </span>
                            </template>
                        </label>
                        <p class="text-xs text-gray-400 mt-2">
                            Видео будет автоматически загружено на Rutube и встроено в рецепт. Поддерживаются MP4, MOV до 500MB.
                        </p>
                    </div>
                </div>
                
                <!-- Кухня, Сложность, Время -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Кухня</label>
                        <select x-model="editForm.cuisine" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                            <option value="">Выбрать...</option>
                            <option>Русская</option><option>Итальянская</option><option>Японская</option>
                            <option>Мексиканская</option><option>Индийская</option><option>Тайская</option>
                            <option>Французская</option><option>Грузинская</option><option>Средиземноморская</option>
                            <option>Американская</option><option>Китайская</option><option>Корейская</option>
                            <option>Другая</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Сложность</label>
                        <select x-model="editForm.difficulty" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                            <option>Легко</option><option>Средне</option><option>Сложно</option><option>Мастер-класс</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Подготовка (мин)</label>
                        <input type="number" x-model="editForm.prep_time" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Готовка (мин)</label>
                        <input type="number" x-model="editForm.cook_time" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                    </div>
                </div>
                
                <!-- Порции и КБЖУ -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div><label class="text-xs text-gray-600 mb-1 block">Порций</label><input type="number" x-model="editForm.servings" min="1" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
                    <div><label class="text-xs text-gray-600 mb-1 block">Ккал</label><input type="number" x-model="editForm.calories" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
                    <div><label class="text-xs text-gray-600 mb-1 block">Белки (г)</label><input type="number" x-model="editForm.proteins" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
                    <div><label class="text-xs text-gray-600 mb-1 block">Жиры (г)</label><input type="number" x-model="editForm.fats" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
                    <div><label class="text-xs text-gray-600 mb-1 block">Углеводы (г)</label><input type="number" x-model="editForm.carbs" min="0" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
                </div>
                
                <!-- Тип питания -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Тип питания</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="diet in dietTypes" :key="diet.value">
                            <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer hover:bg-amber-50 transition select-none"
                                   :class="(editForm.diet_type || []).includes(diet.value) ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
                                <input type="checkbox" :value="diet.value" x-model="editForm.diet_type" class="hidden">
                                <span x-html="dietIcons[diet.value] || ''" class="inline-flex"></span>
                                <span x-text="diet.label" class="text-sm"></span>
                            </label>
                        </template>
                    </div>
                </div>
                
                <!-- Теги -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Теги (через запятую)</label>
                    <input type="text" x-model="tagsInput" @keydown.enter.prevent="addTagToEdit()" 
                           placeholder="Например: быстро, завтрак, веганское"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm">
                    <div class="flex flex-wrap gap-1 mt-2">
                        <template x-for="(tag, i) in (editForm.tags || [])" :key="i">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                                <span x-text="tag"></span>
                                <button type="button" @click="editForm.tags.splice(i,1)" class="hover:text-rose-500 inline-flex" aria-label="Убрать тег"><?= icon('close', 'gc-icon-xs') ?></button>
                            </span>
                        </template>
                    </div>
                </div>
                
                <!-- Ингредиенты -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Ингредиенты</label>
                    <template x-for="(ing, i) in (editForm.ingredients || [])" :key="i">
                        <div class="flex gap-2 mb-2">
                            <input x-model="ing.name" placeholder="Название" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                            <input x-model="ing.amount" placeholder="Кол-во" class="w-24 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                            <input x-model="ing.unit" placeholder="Ед." class="w-20 px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                            <label class="flex items-center gap-1 text-xs text-gray-400">
                                <input type="checkbox" x-model="ing.is_optional"> опц.
                            </label>
                            <button type="button" @click="editForm.ingredients.splice(i,1)" class="px-2 text-rose-300 hover:text-rose-500 inline-flex items-center" aria-label="Убрать ингредиент"><?= icon('close', 'gc-icon-sm') ?></button>
                        </div>
                    </template>
                    <button type="button" @click="addIngredient()" class="text-sm text-amber-600 font-semibold inline-flex items-center gap-1.5"><?= icon('plus', 'gc-icon-sm') ?> Добавить ингредиент</button>
                </div>
                
                <!-- Шаги -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Шаги приготовления</label>
                    <template x-for="(st, i) in (editForm.steps || [])" :key="i">
                        <div class="flex gap-2 mb-2 items-start">
                            <span class="mt-2 text-gray-400 font-bold text-sm" x-text="(i+1)+'.'"></span>
                            <div class="flex-1 space-y-1">
                                <textarea x-model="st.instruction" rows="2" placeholder="Что делать" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm resize-none"></textarea>
                                <input x-model="st.tip" placeholder="Совет" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-xs">
                            </div>
                            <div class="flex flex-col items-center gap-1">
                                <input type="number" x-model="st.timer_minutes" placeholder="мин" title="Таймер, минут" class="w-16 px-2 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                                <button type="button" @click="editForm.steps.splice(i,1)" class="text-rose-300 hover:text-rose-500 inline-flex" aria-label="Убрать шаг"><?= icon('close', 'gc-icon-sm') ?></button>
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="addStep()" class="text-sm text-amber-600 font-semibold inline-flex items-center gap-1.5"><?= icon('plus', 'gc-icon-sm') ?> Добавить шаг</button>
                </div>
                
                <!-- Кнопки -->
                <div class="flex gap-3 justify-end pt-4 border-t border-gray-100">
                    <button @click="editing=null" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveRecipe()" :disabled="uploadingVideo" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('diskette', 'gc-icon-sm') ?>Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminRecipes', () => ({
        recipes: [],
        search: '',
        filter: 'all',
        editing: null,
        editForm: {},
        tagsInput: '',
        uploadingVideo: false,
        uploadProgress: 0,
        dietIcons: <?= json_encode($dietIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        dietTypes: [
            { value: 'vegan', label: 'Веганская' },
            { value: 'vegetarian', label: 'Вегетарианская' },
            { value: 'pescatarian', label: 'Пескетарианская' },
            { value: 'keto', label: 'Кето' },
            { value: 'glutenfree', label: 'Без глютена' },
            { value: 'lactosefree', label: 'Без лактозы' },
            { value: 'lowcarb', label: 'Низкоуглеводная' },
            { value: 'lowcal', label: 'Низкокалорийная' },
            { value: 'highprotein', label: 'Высокобелковая' },
            { value: 'omnivore', label: 'Обычное' }
        ],
        
        async load() {
            const params = new URLSearchParams({search: this.search, filter: this.filter});
            const res = await fetch('/api/admin/recipes.php?' + params);
            const data = await res.json();
            this.recipes = data.recipes || [];
        },
        
        editRecipe(r) {
            this.editForm = {
                ...r,
                ingredients: Array.isArray(r.ingredients) ? [...r.ingredients] : (typeof r.ingredients === 'string' ? JSON.parse(r.ingredients) : []),
                steps: Array.isArray(r.steps) ? [...r.steps] : (typeof r.steps === 'string' ? JSON.parse(r.steps) : []),
                tags: Array.isArray(r.tags) ? [...r.tags] : (typeof r.tags === 'string' ? JSON.parse(r.tags) : []),
                diet_type: Array.isArray(r.diet_type) ? [...r.diet_type] : (typeof r.diet_type === 'string' ? JSON.parse(r.diet_type) : [])
            };
            this.tagsInput = '';
            this.uploadingVideo = false;
            this.uploadProgress = 0;
            this.editing = true;
        },
        
        // Загрузка изображения в редакторе
        uploadEditImage(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл больше 5 МБ');
                return;
            }
            
            if (!file.type.startsWith('image/')) {
                alert('Выберите изображение');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (ev) => {
                this.compressAndSetEdit(ev.target.result, file.type);
            };
            reader.onerror = () => {
                alert('Ошибка чтения файла');
            };
            reader.readAsDataURL(file);
            e.target.value = '';
        },
        
        compressAndSetEdit(dataUrl, mimeType) {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width;
                let h = img.height;
                
                const max = 800;
                if (w > max || h > max) {
                    if (w > h) { h = Math.round(h * max / w); w = max; }
                    else { w = Math.round(w * max / h); h = max; }
                }
                
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                
                this.editForm.image_url = canvas.toDataURL('image/jpeg', 0.75);
            };
            img.onerror = () => {
                this.editForm.image_url = dataUrl;
            };
            img.src = dataUrl;
        },
        
        // Загрузка видео на Rutube
        async uploadEditVideo(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Проверка размера
            if (file.size > 500 * 1024 * 1024) {
                alert('Файл больше 500 МБ');
                return;
            }
            
            // Проверка типа
            if (!file.type.startsWith('video/')) {
                alert('Выберите видеофайл');
                return;
            }
            
            this.uploadingVideo = true;
            this.uploadProgress = 0;
            
            try {
                // Создаем FormData для загрузки
                const formData = new FormData();
                formData.append('video', file);
                formData.append('title', this.editForm.title || 'Видео рецепта');
                formData.append('description', this.editForm.description || '');
                
                // Загружаем видео через API
                const res = await fetch('/api/upload_video_rutube.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.ok && data.video_url) {
                    this.editForm.video_url = data.video_url;
                    if (typeof toast === 'function') toast('Видео загружено на Rutube', 'success');
                } else {
                    alert(data.error || 'Ошибка загрузки видео');
                }
            } catch (err) {
                console.error('Video upload error:', err);
                alert('Ошибка загрузки видео');
            } finally {
                this.uploadingVideo = false;
                this.uploadProgress = 0;
                e.target.value = '';
            }
        },
        
        addTagToEdit() {
            const tag = this.tagsInput.trim();
            if (tag && !(this.editForm.tags || []).includes(tag)) {
                if (!this.editForm.tags) this.editForm.tags = [];
                this.editForm.tags.push(tag);
                this.tagsInput = '';
            }
        },
        
        addIngredient() {
            if (!this.editForm.ingredients) this.editForm.ingredients = [];
            this.editForm.ingredients.push({name: '', amount: '', unit: '', is_optional: false});
        },
        
        addStep() {
            if (!this.editForm.steps) this.editForm.steps = [];
            this.editForm.steps.push({order: this.editForm.steps.length + 1, instruction: '', timer_minutes: 0, tip: ''});
        },
        
        async saveRecipe() {
            const payload = {
                ...this.editForm,
                ingredients: JSON.stringify(this.editForm.ingredients || []),
                steps: JSON.stringify(this.editForm.steps || []),
                tags: JSON.stringify(this.editForm.tags || []),
                diet_type: JSON.stringify(this.editForm.diet_type || [])
            };
            
            const res = await fetch('/api/admin/recipes.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            const data = await res.json();
            
            if (data.ok) {
                this.editing = null;
                this.load();
                if (typeof toast === 'function') toast('Рецепт обновлён', 'success');
            } else {
                alert(data.error || 'Ошибка сохранения');
            }
        },
        
        async deleteRecipe(id) {
            if (!confirm('Удалить рецепт #' + id + '? Это действие нельзя отменить.')) return;
            await fetch('/api/admin/recipes.php?id=' + id, {method: 'DELETE'});
            this.load();
        },
        
        formatDate(d) {
            return new Date(d).toLocaleDateString('ru-RU');
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>