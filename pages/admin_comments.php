<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Управление комментариями';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminComments()" x-init="load()">
    <?php admin_top('comments', 'quote', 'Комментарии', 'Модерация комментариев'); ?>

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-3">
            <input type="text" x-model="search" @keydown.enter="load()" placeholder="Поиск по тексту..."
                   class="<?= admin_cls('input') ?> flex-1 min-w-[200px]">
            <select x-model="rating" @change="load()" class="<?= admin_cls('input') ?> !w-auto">
                <option value="0">Все оценки</option>
                <option value="5">Оценка 5</option>
                <option value="4">Оценка 4</option>
                <option value="3">Оценка 3</option>
                <option value="2">Оценка 2</option>
                <option value="1">Оценка 1</option>
            </select>
            <button @click="load()" class="<?= admin_cls('btn btn-soft') ?>"><?= icon('search', 'gc-icon-sm') ?>Найти</button>
        </div>
    </div>
    <div x-show="reviewId" style="display:none" class="mb-4 bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-center gap-3">
        <span class="text-sm text-amber-800">
            Показаны комментарии только к отзыву <strong>#<span x-text="reviewId"></span></strong>
        </span>
        <a href="<?= url('admin/comments') ?>" class="ml-auto text-xs text-amber-600 hover:underline inline-flex items-center gap-1">Показать все <?= icon('right', 'gc-icon-xs') ?></a>
    </div>

    <!-- Таблица комментариев -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Рецепт</th>
                    <th class="<?= admin_cls('th') ?>">Автор</th>
                    <th class="<?= admin_cls('th') ?>">Текст</th>
                    <th class="<?= admin_cls('th') ?>">Оценка</th>
                    <th class="<?= admin_cls('th') ?>">Дата</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="c in comments" :key="c.id">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-sm" x-text="c.id"></td>
                        <td class="px-4 py-3">
                            <a :href="'/recipe/' + (c.recipe_slug || ('?id=' + c.recipe_id))" 
                               class="text-sm text-amber-600 hover:underline" 
                               x-text="c.recipe_title || '#' + c.recipe_id"></a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="c.user_email || c.author_name || 'Гость'"></td>
                        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" x-text="c.text"></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex gap-0.5"><template x-for="s in 5" :key="s"><span :class="s <= (c.rating || 0) ? 'text-amber-400' : 'text-gray-200'"><?= icon('star', 'gc-icon-xs') ?></span></template></span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="formatDate(c.created_at)"></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button @click="editComment(c)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                            <button @click="deleteComment(c.id)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!comments.length">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">Комментарии не найдены</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно редактирования -->
    <div x-show="editing" style="display:none" class="<?= admin_cls('overlay') ?>" @click.self="editing=null">
        <div class="<?= admin_cls('modal') ?> max-w-lg">
            <h3 class="text-xl font-bold mb-4">Редактировать комментарий #<span x-text="editForm.id"></span></h3>
            <div class="space-y-3">
                <div>
                    <label class="<?= admin_cls('label') ?>">Текст</label>
                    <textarea x-model="editForm.text" rows="3" class="<?= admin_cls('input') ?>"></textarea>
                </div>
                <div>
    <label class="<?= admin_cls('label') ?>">Оценка отзыва (не редактируется)</label>
    <div class="flex gap-1 text-2xl">
        <template x-for="s in 5" :key="s">
            <span :class="s <= (editForm.rating || 0) ? 'text-amber-400' : 'text-gray-200'"><?= icon('star', 'gc-icon-lg') ?></span>
        </template>
    </div>
</div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="editing=null" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveComment()" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('diskette', 'gc-icon-sm') ?>Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminComments', () => ({
    comments: [],
    search: '',
    rating: '0',
    reviewId: '',
    editing: null,
    editForm: {},

    init() {
        const params = new URLSearchParams(window.location.search);
        this.reviewId = params.get('review_id') || '';
        this.load();
    },

    async load() {
        const params = new URLSearchParams({
            search: this.search,
            rating: this.rating
        });
        if (this.reviewId) params.set('review_id', this.reviewId);
        const res = await fetch('/api/admin/comments.php?' + params.toString());
        const data = await res.json();
        this.comments = data.comments || [];
    },
        
        editComment(c) {
            this.editForm = {...c};
            this.editing = true;
        },
        
        async saveComment() {
            await fetch('/api/admin/comments.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
    id: this.editForm.id,
    text: this.editForm.text
    // rating НЕ передаём
})
            });
            this.editing = null;
            this.load();
        },
        
        async deleteComment(id) {
            if (!confirm('Удалить комментарий #' + id + '?')) return;
            await fetch('/api/admin/comments.php?id=' + id, {method: 'DELETE'});
            this.load();
        },
        
        formatDate(d) {
            return new Date(d).toLocaleDateString('ru-RU');
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>