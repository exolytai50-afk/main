<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Управление отзывами';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminReviews()" x-init="load()">

    <?php admin_top('reviews', 'star', 'Отзывы', 'Модерация отзывов к рецептам'); ?>

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-3">
            <input type="text" x-model="search" @keydown.enter="load()" placeholder="Поиск по тексту, рецепту, автору..."
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

    <!-- Таблица отзывов -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Рецепт</th>
                    <th class="<?= admin_cls('th') ?>">Автор</th>
                    <th class="<?= admin_cls('th') ?>">Текст отзыва</th>
                    <th class="<?= admin_cls('th') ?>">Оценка</th>
                    <th class="<?= admin_cls('th') ?>">Комментариев</th>
                    <th class="<?= admin_cls('th') ?>">Дата</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="r in reviews" :key="r.id">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-sm" x-text="r.id"></td>
                        <td class="px-4 py-3">
                            <a :href="'/recipe/' + (r.recipe_slug || ('?id=' + r.recipe_id))"
                               class="text-sm text-amber-600 hover:underline"
                               x-text="r.recipe_title || '#' + r.recipe_id"></a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="r.display_name || r.user_email"></td>
                        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" x-text="r.text"></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex gap-0.5"><template x-for="s in 5" :key="s"><span :class="s <= (r.rating || 0) ? 'text-amber-400' : 'text-gray-200'"><?= icon('star', 'gc-icon-xs') ?></span></template></span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a :href="'<?= url('admin/comments') ?>?review_id=' + r.id"
                               class="inline-flex items-center gap-1 text-amber-600 hover:underline">
                                <?= icon('chats', 'gc-icon-sm') ?> <span x-text="r.comments_count"></span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="formatDate(r.created_at)"></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button @click="editReview(r)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                            <button @click="deleteReview(r.id, r.comments_count)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!reviews.length">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">Отзывы не найдены</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно -->
    <div x-show="editing" style="display:none" class="<?= admin_cls('overlay') ?>" @click.self="editing=null">
        <div class="<?= admin_cls('modal') ?> max-w-lg">
            <h3 class="text-xl font-bold mb-4">Отзыв #<span x-text="editForm.id"></span></h3>

            <div class="space-y-4">
                <div>
                    <label class="text-sm text-gray-600 block mb-1">Текст</label>
                    <textarea x-model="editForm.text" rows="4" class="<?= admin_cls('input') ?>"></textarea>
                </div>

                <div>
                    <label class="text-sm text-gray-600 block mb-1">Оценка</label>
                    <div class="flex gap-1 text-2xl">
                        <template x-for="s in 5" :key="s">
                            <button type="button" @click="editForm.rating = s" :aria-label="'Оценка ' + s"
                                    :class="s <= editForm.rating ? 'text-amber-400' : 'text-gray-200'"><?= icon('star', 'gc-icon-lg') ?></button>
                        </template>
                    </div>
                </div>

                <div class="flex gap-3 justify-end mt-4">
                    <button @click="editing=null" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveReview()" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('diskette', 'gc-icon-sm') ?>Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminReviews', () => ({
        reviews: [],
        search: '',
        rating: '0',
        editing: null,
        editForm: {},

        async load() {
            const params = new URLSearchParams({ search: this.search, rating: this.rating });
            const res = await fetch('/api/admin/reviews.php?' + params.toString());
            const data = await res.json();
            this.reviews = data.reviews || [];
        },

        editReview(r) {
            this.editForm = { ...r };
            this.editing = true;
        },

        async saveReview() {
            await fetch('/api/admin/reviews.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id:     this.editForm.id,
                    text:   this.editForm.text,
                    rating: this.editForm.rating
                })
            });
            this.editing = null;
            this.load();
        },

        async deleteReview(id, commentsCount) {
            let msg = 'Удалить отзыв #' + id + '?';
            if (commentsCount > 0) {
                msg = 'Удалить отзыв #' + id + ' ВМЕСТЕ с ' + commentsCount + ' комментарием(ями)?';
            }
            if (!confirm(msg)) return;
            await fetch('/api/admin/reviews.php?id=' + id, { method: 'DELETE' });
            this.load();
        },

        formatDate(d) {
            return new Date(d.replace(' ', 'T')).toLocaleDateString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>