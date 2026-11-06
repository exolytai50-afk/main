<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Управление челленджами';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminChallenges()" x-init="load()">

    <?php
    ob_start(); ?>
        <select x-model="filter" @change="load()" class="<?= admin_cls('input') ?> !w-auto">
            <option value="all">Все</option>
            <option value="draft">Черновики</option>
            <option value="active">Активные</option>
            <option value="finished">Завершённые</option>
        </select>
        <button @click="openCreate()" class="<?= admin_cls('btn btn-primary') ?>"><?= icon('plus', 'gc-icon-sm') ?> Создать</button>
    <?php
    admin_top('challenges', 'challenges', 'Челленджи', 'Создание, запуск и завершение', ob_get_clean());
    ?>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="<?= admin_cls('th') ?>">ID</th>
                    <th class="<?= admin_cls('th') ?>">Название</th>
                    <th class="<?= admin_cls('th') ?>">Сложность</th>
                    <th class="<?= admin_cls('th') ?>">Очки</th>
                    <th class="<?= admin_cls('th') ?>">Участ.</th>
                    <th class="<?= admin_cls('th') ?>">Работ</th>
                    <th class="<?= admin_cls('th') ?>">Даты</th>
                    <th class="<?= admin_cls('th') ?>">Приурочен к</th>
                    <th class="<?= admin_cls('th') ?>">Статус</th>
                    <th class="<?= admin_cls('th') ?> text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <tr x-show="!challenges.length" style="display:none"><td colspan="10" class="px-4 py-8 text-center text-gray-400">Челленджей нет</td></tr>
                <template x-for="ch in challenges" :key="ch.id">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-3 py-3 text-sm" x-text="ch.id"></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold text-gray-700" x-text="ch.title"></div>
                            <div class="text-xs text-gray-400" x-text="ch.theme || ''"></div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-bold"
                                  :class="{
                                    'bg-emerald-100 text-emerald-700': ch.difficulty === 'easy',
                                    'bg-amber-100 text-amber-700': ch.difficulty === 'medium',
                                    'bg-rose-100 text-rose-700': ch.difficulty === 'hard'
                                  }"
                                  x-text="{'easy':'Легко','medium':'Средне','hard':'Сложно'}[ch.difficulty] || ch.difficulty"></span>
                        </td>
                        <td class="px-3 py-3 text-sm">
                            <span class="text-amber-600 font-bold" x-text="ch.reward_points"></span>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-500" x-text="ch.participants_count || 0"></td>
                        <td class="px-3 py-3 text-sm text-gray-500" x-text="ch.submissions_count || 0"></td>
                        <td class="px-3 py-3 text-xs text-gray-400">
                            <span x-text="ch.starts_at || '—'"></span> — 
                            <span x-text="ch.ends_at || '—'"></span>
                        </td>
                        
                        <td class="px-3 py-3 text-xs">
    <template x-if="ch.is_thematic && ch.thematic_date">
        <div class="flex items-center gap-1">
            <span class="text-amber-600 inline-flex"><?= icon('holiday', 'gc-icon-sm') ?></span>
            <span class="text-gray-700 font-semibold" x-text="ch.thematic_date"></span>
        </div>
    </template>
    <template x-if="!ch.is_thematic || !ch.thematic_date">
        <span class="text-gray-300">—</span>
    </template>
</td>

                        <td class="px-3 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-bold"
                                  :class="{
                                    'bg-gray-100 text-gray-600': ch.status === 'draft',
                                    'bg-emerald-100 text-emerald-700': ch.status === 'active',
                                    'bg-blue-100 text-blue-700': ch.status === 'finished'
                                  }"
                                  x-text="{'draft':'Черновик','active':'Активен','finished':'Завершён'}[ch.status] || ch.status"></span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <template x-if="ch.status === 'draft'">
                                <button @click="startNow(ch.id)" class="<?= admin_cls('ibtn ibtn-ok') ?>" title="Запустить" aria-label="Запустить"><?= icon('play', 'gc-icon-sm') ?></button>
                            </template>
                            <template x-if="ch.status === 'active'">
                                <button @click="finishNow(ch.id)" class="<?= admin_cls('ibtn ibtn-info') ?>" title="Завершить" aria-label="Завершить"><?= icon('stop', 'gc-icon-sm') ?></button>
                            </template>
                            <button @click="editChallenge(ch)" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать"><?= icon('edit', 'gc-icon-sm') ?></button>
                            <button @click="deleteChallenge(ch.id)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Модалка создания / редактирования -->
    <div x-show="showModal" style="display:none"
         class="<?= admin_cls('overlay') ?>"
         @click.self="closeModal()">
        <div class="<?= admin_cls('modal') ?> max-w-lg max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold mb-4" x-text="editing ? 'Редактировать' : 'Создать челлендж'"></h3>

            <div class="space-y-3">
                <div>
                    <label class="<?= admin_cls('label') ?>">Название *</label>
                    <input x-model="form.title" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Описание</label>
                    <textarea x-model="form.description" rows="3" class="<?= admin_cls('input') ?>"></textarea>
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Тема</label>
                    <input x-model="form.theme" placeholder="Например: Соусы, Итальянская кухня" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Сложность / Формат</label>
                    <select x-model="form.category" class="<?= admin_cls('input') ?>">
                        <option value="express">Экспресс · 3 дня · 30 очков</option>
                        <option value="standard">Стандарт · 7 дней · 50 очков</option>
                        <option value="long">Длинный · 14 дней · 100 очков</option>
                        <option value="month">Месяц · 30 дней · 200 очков</option>
                    </select>
                </div>

                <!-- Тематический — отдельным блоком -->
                <div class="flex items-center gap-2 p-3 bg-amber-50 rounded-xl">
                    <input type="checkbox" x-model="form.is_thematic" id="is_thematic" class="w-4 h-4 accent-amber-500">
                    <label for="is_thematic" class="text-sm text-gray-700 cursor-pointer inline-flex items-center gap-1.5">
                        <?= icon('holiday', 'gc-icon-sm') ?> Тематический челлендж (приурочен к празднику)
                    </label>
                </div>

                <!-- Дата привязки — только если тематический -->
                <div x-show="form.is_thematic" style="display:none" x-transition>
                    <label class="<?= admin_cls('label') ?>">К какому празднику / дате?</label>
                    <input type="date" x-model="form.thematic_date" class="<?= admin_cls('input') ?>">
                </div>

                <!-- Автоподсказка -->
                <div class="bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
                    <div class="font-bold mb-1">Будет проставлено автоматически:</div>
                    <div x-text="`Категория: ${autoInfo.category}`"></div>
                    <div x-text="`Длительность: ${autoInfo.duration_days} дн.`"></div>
                    <div x-text="`Очки: +${autoInfo.reward_points}`"></div>
                    <div x-text="`Цель: ${autoInfo.goal}`"></div>
                </div>

                <div class="flex gap-3 justify-end mt-4">
                    <button @click="closeModal()" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                    <button @click="saveChallenge()" :disabled="saving"
                            class="<?= admin_cls('btn btn-primary') ?>">
                        <span x-show="!saving">Сохранить</span>
                        <span x-show="saving" style="display:none">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminChallenges', () => ({
        challenges: [],
        filter: 'all',
        showModal: false,
        editing: null,
        saving: false,
        form: { title: '', description: '', theme: '', category: 'standard', days: [], is_thematic: false, thematic_date: null, },

        get autoInfo() {
    const map = {
        express:  { category: 'express',  duration_days: 3,  reward_points: 30,  goal: 'Справиться за 3 дня' },
        standard: { category: 'standard', duration_days: 7,  reward_points: 50,  goal: '7 дней — 7 блюд' },
        long:     { category: 'long',     duration_days: 14, reward_points: 100, goal: '14 дней — глубокое погружение' },
        month:    { category: 'month',    duration_days: 30, reward_points: 200, goal: '30 дней — полное погружение' },
    };
    return map[this.form.category] || map.standard;
},

        async load() {
            try {
                const res = await fetch('/api/admin/challenges.php');
                const data = await res.json();
                let list = data.challenges || [];
                if (this.filter !== 'all') {
                    list = list.filter(c => c.status === this.filter);
                }
                this.challenges = list;
            } catch (e) {
                console.error('load error:', e);
            }
        },

        openCreate() {
    this.editing = null;
    this.form = { 
        title: '', 
        description: '', 
        theme: '', 
        category: 'standard', 
        days: [],
        is_thematic: false,
        thematic_date: null,
    };
    this.showModal = true;
},

editChallenge(ch) {
    this.editing = ch;
    const meta = typeof ch.meta_json === 'string' 
        ? JSON.parse(ch.meta_json || '{}') 
        : (ch.meta_json || {});
    
    this.form = {
        title: ch.title || '',
        description: ch.description || '',
        theme: ch.theme || '',
        category: ch.category || 'standard',
        days: meta.days || [],
        is_thematic: ch.is_thematic == 1,       // добавлено
        thematic_date: ch.thematic_date || null, // добавлено
    };
    this.showModal = true;
},

closeModal() {
            this.showModal = false;
            this.editing = null;
        },

        async saveChallenge() {
    if (!this.form.title.trim()) {
        alert('Укажите название');
        return;
    }
    this.saving = true;
    try {
        const method = this.editing ? 'PUT' : 'POST';
        const body = this.editing
            ? { ...this.form, id: this.editing.id }
            : this.form;

        const res = await fetch('/api/admin/challenges.php', {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.ok || data.id) {
            this.closeModal();
            this.load();
        } else {
            alert(data.error || 'Ошибка');
        }
    } catch (e) {
        alert('Ошибка соединения');
    } finally {
        this.saving = false;
    }
},

        async startNow(id) {
            if (!confirm('Запустить челлендж сейчас?')) return;
            await fetch('/api/admin/challenges.php?action=start_now&id=' + id, { method: 'POST' });
            this.load();
        },

        async finishNow(id) {
            if (!confirm('Завершить челлендж сейчас?')) return;
            await fetch('/api/admin/challenges.php?action=finish_now&id=' + id, { method: 'POST' });
            this.load();
        },

        async deleteChallenge(id) {
            if (!confirm('Удалить челлендж #' + id + '?')) return;
            await fetch('/api/admin/challenges.php?id=' + id, { method: 'DELETE' });
            this.load();
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>