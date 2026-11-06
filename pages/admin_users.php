<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Управление пользователями';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminUsers()" x-init="load()">
    <?php admin_top('users', 'user', 'Пользователи', 'Роли, премиум и удаление аккаунтов'); ?>

    <!-- Поиск -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-3">
            <input type="text" x-model="search" @input.debounce.300ms="load()" placeholder="Поиск по email или имени..."
                   class="<?= admin_cls('input') ?> flex-1 min-w-[200px]">
            <select x-model="roleFilter" @change="load()" class="<?= admin_cls('input') ?> !w-auto">
                <option value="">Все роли</option>
                <option value="user">Пользователи</option>
                <option value="admin">Администраторы</option>
                <option value="premium">Премиум</option>
            </select>
            <button @click="load()" class="<?= admin_cls('btn btn-soft') ?>"><?= icon('search', 'gc-icon-sm') ?>Найти</button>
        </div>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-gray-700" x-text="stats.total || 0"></div>
            <div class="text-xs text-gray-500">Всего</div>
        </div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-blue-600" x-text="stats.users || 0"></div>
            <div class="text-xs text-gray-500">Пользователей</div>
        </div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-red-600" x-text="stats.admins || 0"></div>
            <div class="text-xs text-gray-500">Администраторов</div>
        </div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-violet-600" x-text="stats.premium || 0"></div>
            <div class="text-xs text-gray-500">Премиум</div>
        </div>
    </div>

    <!-- Таблица пользователей -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Аватар</th>
                    <th class="<?= admin_cls('th') ?>">Email / Имя</th>
                    <th class="<?= admin_cls('th') ?>">Роль</th>
                    <th class="<?= admin_cls('th') ?>">Статус</th>
                    <th class="<?= admin_cls('th') ?>">Очки</th>
                    <th class="<?= admin_cls('th') ?>">Рецепты</th>
                    <th class="<?= admin_cls('th') ?>">Дата</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="u in users" :key="u.id">
                    <tr class="hover:bg-gray-50/50" :class="u.role === 'admin' ? 'bg-red-50/30' : ''">
                        <td class="px-4 py-3 text-sm" x-text="u.id"></td>
                        <td class="px-4 py-3">
                            <img x-show="u.avatar_url" :src="u.avatar_url" class="w-9 h-9 rounded-full object-cover">
                            <span x-show="!u.avatar_url" class="w-9 h-9 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-lg">
                                <template x-if="u.avatar_emoji"><span x-text="u.avatar_emoji"></span></template>
                                <template x-if="!u.avatar_emoji"><?= icon('user', 'gc-icon-md') ?></template>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-gray-800" x-text="u.display_name || 'Без имени'"></div>
                            <div class="text-xs text-gray-400" x-text="u.email"></div>
                        </td>
                        <td class="px-4 py-3">
                            <select @change="changeRole(u.id, $event.target.value)" 
                                    class="text-xs border rounded-lg px-2 py-1.5 outline-none bg-white"
                                    :class="u.role === 'admin' ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200'">
                                <option value="user" :selected="u.role==='user'">Пользователь</option>
                                <option value="admin" :selected="u.role==='admin'">Администратор</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span x-show="u.is_premium == 1"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-700"
                                      title="Премиум выдан вручную в админке">
                                    <?= icon('sparkle', 'gc-icon-xs') ?> Премиум
                                </span>
                                <span x-show="u.has_active_subscription == 1"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700"
                                      title="Активная оплаченная подписка">
                                    <?= icon('flame', 'gc-icon-xs') ?> Pro
                                </span>
                                <span x-show="u.is_premium != 1 && u.has_active_subscription != 1"
                                      class="text-xs text-gray-400">
                                    Обычный
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-amber-600" x-text="u.points || 0"></td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="u.recipes_count || 0"></td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="formatDate(u.created_at)"></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button @click="editUser(u)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                                <button @click="togglePremium(u)" class="<?= admin_cls('ibtn') ?>"
                                        :title="u.is_premium == 1 ? 'Убрать Премиум' : 'Сделать Премиум'"
                                        :aria-label="u.is_premium == 1 ? 'Убрать Премиум' : 'Сделать Премиум'">
                                    <span x-show="u.is_premium == 1" class="text-violet-500 hover:text-violet-700 inline-flex"><?= icon('star', 'gc-icon-md') ?></span>
                                    <span x-show="u.is_premium != 1" class="text-gray-300 hover:text-violet-500 inline-flex"><?= icon('star', 'gc-icon-md') ?></span>
                                </button>
                                <button @click="deleteUser(u.id)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно редактирования -->
    <div x-show="editingUser" style="display:none" class="<?= admin_cls('overlay') ?>" @click.self="editingUser=null">
        <div class="<?= admin_cls('modal') ?> max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Редактировать пользователя</h3>
                <button @click="editingUser=null" class="<?= admin_cls('ibtn') ?> text-gray-400 hover:bg-gray-100" aria-label="Закрыть"><?= icon('close', 'gc-icon-md') ?></button>
            </div>
            
            <div class="space-y-4">
                <div><label class="<?= admin_cls('label') ?>">ID</label><input type="text" x-model="editForm.id" disabled class="<?= admin_cls('input') ?> bg-gray-50"></div>
                <div><label class="<?= admin_cls('label') ?>">Email</label><input type="email" x-model="editForm.email" disabled class="<?= admin_cls('input') ?> bg-gray-50"></div>
                <div><label class="<?= admin_cls('label') ?>">Имя</label><input type="text" x-model="editForm.display_name" class="<?= admin_cls('input') ?>"></div>
                <div><label class="<?= admin_cls('label') ?>">Роль</label><select x-model="editForm.role" class="<?= admin_cls('input') ?>"><option value="user">Пользователь</option><option value="admin">Администратор</option></select></div>
                <div><label class="<?= admin_cls('label') ?>">Очки</label><input type="number" x-model="editForm.points" min="0" class="<?= admin_cls('input') ?>"></div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="editForm.is_premium" :checked="editForm.is_premium == 1" class="w-4 h-4 rounded accent-violet-500">
                        <span class="text-sm text-gray-700 inline-flex items-center gap-1.5"><?= icon('sparkle', 'gc-icon-sm') ?> Премиум статус</span>
                    </label>
                </div>
                <div><label class="<?= admin_cls('label') ?>">Биография</label><textarea x-model="editForm.bio" rows="3" class="<?= admin_cls('input') ?> resize-none"></textarea></div>
                
                <div class="flex gap-3 justify-end pt-4 border-t border-gray-100">
                    <button @click="editingUser=null" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveUser()" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('diskette', 'gc-icon-sm') ?>Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminUsers', () => ({
        users: [],
        search: '',
        roleFilter: '',
        editingUser: null,
        editForm: {},
        stats: { total: 0, users: 0, admins: 0, premium: 0 },
        
        async load() {
            const params = new URLSearchParams({ search: this.search, role: this.roleFilter });
            const res = await fetch('/api/admin/users.php?' + params.toString());
            const data = await res.json();
            this.users = data.users || [];
            this.stats = data.stats || { total: 0, users: 0, admins: 0, premium: 0 };
        },
        
        async changeRole(id, role) {
            await fetch('/api/admin/users.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id, role })
            });
            this.load();
        },
        
        editUser(u) {
            this.editForm = { 
                ...u,
                is_premium: u.is_premium == 1 ? true : false
            };
            this.editingUser = true;
        },
        
        async saveUser() {
            const res = await fetch('/api/admin/users.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: this.editForm.id,
                    display_name: this.editForm.display_name,
                    role: this.editForm.role,
                    points: parseInt(this.editForm.points) || 0,
                    is_premium: this.editForm.is_premium ? 1 : 0,
                    bio: this.editForm.bio || ''
                })
            });
            
            const data = await res.json();
            
            if (data.ok) {
                // локально синхронизируем, чтобы бейдж переключился мгновенно
                const idx = this.users.findIndex(u => u.id === this.editForm.id);
                if (idx !== -1) {
                    this.users[idx].is_premium = this.editForm.is_premium ? 1 : 0;
                    this.users[idx].display_name = this.editForm.display_name;
                    this.users[idx].role = this.editForm.role;
                    this.users[idx].points = parseInt(this.editForm.points) || 0;
                }
                this.editingUser = null;
                this.load();
                if (typeof toast === 'function') toast('Пользователь обновлён', 'success');
            } else {
                alert(data.error || 'Ошибка сохранения');
            }
        },
        
        async togglePremium(u) {
            const newStatus = u.is_premium == 1 ? 0 : 1;
            
            const res = await fetch('/api/admin/users.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: u.id, is_premium: newStatus })
            });
            
            const data = await res.json();
            
            if (data.ok) {
                // локально синхронизируем, чтобы бейдж переключился мгновенно
                const idx = this.users.findIndex(u => u.id === this.editForm.id);
                if (idx !== -1) {
                    this.users[idx].is_premium = this.editForm.is_premium ? 1 : 0;
                    this.users[idx].display_name = this.editForm.display_name;
                    this.users[idx].role = this.editForm.role;
                    this.users[idx].points = parseInt(this.editForm.points) || 0;
                }
                this.editingUser = null;
                this.load();
                if (typeof toast === 'function') toast('Пользователь обновлён', 'success');
            } else {
                alert(data.error || 'Ошибка');
            }
        },
        
        async deleteUser(id) {
            if (!confirm('Удалить пользователя #' + id + '? Все данные будут потеряны.')) return;
            await fetch('/api/admin/users.php?id=' + id, { method: 'DELETE' });
            this.load();
        },
        
        formatDate(d) { return new Date(d).toLocaleDateString('ru-RU'); }
    }));
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>