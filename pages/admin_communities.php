<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Управление сообществами';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminCommunities()" x-init="load()">
    <?php
    ob_start(); ?>
        <button @click="showCreate = true" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('plus', 'gc-icon-sm') ?> Создать</button>
    <?php
    admin_top('communities', 'communities', 'Сообщества', 'Управление группами', ob_get_clean());
    ?>

    <!-- Таблица сообществ -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Название</th>
                    <th class="<?= admin_cls('th') ?>">Slug</th>
                    <th class="<?= admin_cls('th') ?>">Участники</th>
                    <th class="<?= admin_cls('th') ?>">Создано</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <tr x-show="!communities.length" style="display:none"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Сообществ пока нет</td></tr>
                <template x-for="c in communities" :key="c.id">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-sm" x-text="c.id"></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600">
                                    <template x-if="c.cover_emoji"><span x-text="c.cover_emoji"></span></template>
                                    <template x-if="!c.cover_emoji"><?= icon('communities', 'gc-icon-sm') ?></template>
                                </span>
                                <span class="font-semibold text-gray-700" x-text="c.name"></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="c.slug"></td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="c.members_count || 0"></td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="formatDate(c.created_at)"></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button @click="editCommunity(c)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                            <button @click="deleteCommunity(c.id)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно создания/редактирования -->
    <div x-show="showCreate || editing" style="display:none" class="<?= admin_cls('overlay') ?>" @click.self="showCreate=false;editing=null">
        <div class="<?= admin_cls('modal') ?> max-w-lg">
            <h3 class="text-xl font-bold mb-4">
                <span x-show="editing">Редактировать сообщество</span>
                <span x-show="showCreate">Создать сообщество</span>
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="<?= admin_cls('label') ?>">Название *</label>
                    <input x-model="form.name" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Slug</label>
                    <input x-model="form.slug" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Описание</label>
                    <textarea x-model="form.description" rows="3" class="<?= admin_cls('input') ?>"></textarea>
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Эмодзи (необязательно)</label>
                    <input x-model="form.cover_emoji" placeholder="Если пусто — иконка по умолчанию" class="<?= admin_cls('input') ?>">
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showCreate=false;editing=null" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveCommunity()" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('diskette', 'gc-icon-sm') ?>Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminCommunities', () => ({
        communities: [],
        showCreate: false,
        editing: null,
        form: {},
        
        async load() {
            const res = await fetch('/api/admin/communities.php');
            const data = await res.json();
            this.communities = data.communities || [];
        },
        
        editCommunity(c) {
            this.form = {...c};
            this.editing = true;
            this.showCreate = false;
        },
        
        async saveCommunity() {
            const method = this.editing ? 'PUT' : 'POST';
            await fetch('/api/admin/communities.php', {
                method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(this.form)
            });
            this.showCreate = false;
            this.editing = null;
            this.form = {};
            this.load();
        },
        
        async deleteCommunity(id) {
            if (!confirm('Удалить сообщество #' + id + '?')) return;
            await fetch('/api/admin/communities.php?id=' + id, {method: 'DELETE'});
            this.load();
        },
        
        formatDate(d) {
            return new Date(d).toLocaleDateString('ru-RU');
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>