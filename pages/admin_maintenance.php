<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Техническое обслуживание';
require_once __DIR__ . '/../includes/header.php';

$s = maintenance_get_settings();
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="maintenanceAdmin()" x-init="load()">

    <?php admin_top('maintenance', 'technical', 'Техническое обслуживание', 'Заглушка для пользователей на время работ'); ?>

    <div class="max-w-3xl mx-auto">

    <!-- Статус -->
    <div class="bg-white rounded-3xl shadow-md p-6 mb-6"
         :class="state.enabled ? 'ring-2 ring-rose-300' : ''">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center shrink-0"
                 :class="state.enabled ? 'bg-rose-100' : 'bg-emerald-100'">
                <span x-show="state.enabled" class="text-rose-500 inline-flex"><?= icon('technical', 'gc-icon-xl') ?></span>
                <span x-show="!state.enabled" class="text-emerald-500 inline-flex"><?= icon('check', 'gc-icon-xl') ?></span>
            </div>
            <div class="flex-1">
                <div class="text-xl font-bold text-gray-800">
                    <span x-show="state.enabled">Режим техработ ВКЛЮЧЁН</span>
                    <span x-show="!state.enabled">Сайт работает в обычном режиме</span>
                </div>
                <div class="text-sm text-gray-500 mt-1" x-show="state.enabled && state.until">
                    До: <span class="font-semibold" x-text="state.until"></span>
                </div>
                <div class="text-sm text-gray-500 mt-1" x-show="state.enabled && !state.until">
                    Без автоматического окончания
                </div>
            </div>
        </div>

        <!-- Кнопки -->
        <div class="flex gap-3" x-show="!state.enabled">
            <button @click="openSetup()" 
                    class="<?= admin_cls('btn btn-danger') ?> flex-1 py-3">
                <?= icon('technical', 'gc-icon-sm') ?> Включить техработы
            </button>
        </div>

        <div class="flex gap-3" x-show="state.enabled">
            <button @click="disable()" 
                    class="<?= admin_cls('btn btn-success') ?> flex-1 py-3">
                <?= icon('check', 'gc-icon-sm') ?> Выключить техработы
            </button>
            <button @click="openSetup()" 
                    class="<?= admin_cls('btn btn-ghost') ?> px-5 py-3">
                <?= icon('edit', 'gc-icon-sm') ?> Изменить
            </button>
        </div>

        <div class="mt-4 bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
            <strong>Админам</strong> сайт доступен всегда — вы видите обычный интерфейс с баннером.
            <strong>Остальные</strong> видят заглушку.
        </div>
    </div>

    <!-- Модалка настройки -->
    <div x-show="showSetup" style="display:none"
         class="<?= admin_cls('overlay') ?>"
         @click.self="showSetup = false">
        <div class="<?= admin_cls('modal') ?> max-w-lg">
            <h3 class="text-xl font-bold mb-4">Настройка техработ</h3>

            <div class="space-y-4">
                <div>
                    <label class="<?= admin_cls('label') ?>">Дата и время окончания</label>
                    <input type="datetime-local" x-model="form.until" 
                           class="<?= admin_cls('input') ?>">
                    <p class="text-xs text-gray-400 mt-1">Оставьте пустым — техработы не закончатся автоматически.</p>
                </div>

                <div>
                    <label class="<?= admin_cls('label') ?>">Сообщение для пользователей</label>
                    <textarea x-model="form.message" rows="3" 
                              placeholder="Мы обновляем кухню и скоро вернёмся..."
                              class="<?= admin_cls('input') ?> resize-none"></textarea>
                </div>

                <div class="bg-amber-50 rounded-xl p-3 text-xs text-amber-700">
                    После окончания пользователи увидят баннер «Мы вернулись» на 24 часа.
                </div>

                <div class="flex gap-3 justify-end pt-2">
                    <button @click="showSetup = false" 
                            class="<?= admin_cls('btn btn-ghost') ?>">
                        Отмена
                    </button>
                    <button @click="enable()" :disabled="saving"
                            class="<?= admin_cls('btn btn-danger') ?>">
                        <span x-show="!saving">Включить</span>
                        <span x-show="saving" style="display:none">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('maintenanceAdmin', () => ({
        state: {
            enabled: <?= $s['maintenance_enabled'] === '1' ? 'true' : 'false' ?>,
            until: <?= json_encode($s['maintenance_until']) ?>,
            message: <?= json_encode($s['maintenance_message']) ?>,
        },
        showSetup: false,
        saving: false,
        form: {
            until: '',
            message: '',
        },

        async load() {
            try {
                const res = await fetch('/api/admin/maintenance.php');
                const data = await res.json();
                this.state.enabled = !!data.enabled;
                this.state.until = data.until || '';
                this.state.message = data.message || '';
            } catch (e) {
                console.error('load error:', e);
            }
        },

        openSetup() {
            this.form.until = this.state.until || '';
            this.form.message = this.state.message || 'Мы обновляем кухню и скоро вернёмся с новыми возможностями!';
            this.showSetup = true;
        },

        async enable() {
            this.saving = true;
            try {
                const res = await fetch('/api/admin/maintenance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        enabled: 1,
                        until: this.form.until,
                        message: this.form.message,
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    this.state.enabled = true;
                    this.state.until = data.until || '';
                    this.state.message = this.form.message;
                    this.showSetup = false;
                } else {
                    alert(data.error || 'Ошибка');
                }
            } catch (e) {
                alert('Ошибка соединения');
            } finally {
                this.saving = false;
            }
        },

        async disable() {
            if (!confirm('Выключить режим техработ?')) return;
            try {
                const res = await fetch('/api/admin/maintenance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: 0 })
                });
                const data = await res.json();
                if (data.ok) {
                    this.state.enabled = false;
                    this.state.until = '';
                }
            } catch (e) {
                alert('Ошибка соединения');
            }
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>