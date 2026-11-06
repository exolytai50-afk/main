<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Бэкап базы данных';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="adminBackup()" x-init="init()">
    <?php admin_top('backup', 'diskette', 'Бэкап и восстановление', 'База данных и карта сайта'); ?>

    <div class="max-w-4xl mx-auto">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Создание бэкапа -->
        <div class="bg-white rounded-2xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><?= icon('download', 'gc-icon-sm') ?> Создать бэкап</h3>
            <p class="text-sm text-gray-500 mb-4">Экспорт всей базы данных в SQL-файл</p>
            <button @click="createBackup()" :disabled="loading"
                    class="<?= admin_cls('btn btn-success') ?> w-full py-3">
                <span x-show="!loading" class="inline-flex items-center gap-2"><?= icon('download', 'gc-icon-sm') ?> Создать бэкап</span>
                <span x-show="loading" style="display:none" class="inline-flex items-center gap-2"><?= icon('hourglass', 'gc-icon-sm') ?> Создаётся...</span>
            </button>
            <p x-show="backupUrl" style="display:none" class="mt-3 text-sm">
                <a :href="'<?= url('') ?>download_backup.php?file=' + encodeURIComponent(backupUrl.split('/').pop())"
                   class="text-emerald-600 hover:underline inline-flex items-center gap-1.5" download>
                    <?= icon('download', 'gc-icon-sm') ?> Скачать бэкап
                </a>
            </p>
        </div>

        <!-- Восстановление -->
        <div class="bg-white rounded-2xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><?= icon('upload', 'gc-icon-sm') ?> Восстановить из бэкапа</h3>
            <p class="text-sm text-gray-500 mb-4">Загрузите SQL-файл для восстановления</p>
            <input type="file" @change="handleFile" accept=".sql,.gz" class="w-full text-sm mb-3">
            <button @click="restoreBackup()" :disabled="!file || restoring"
                    class="<?= admin_cls('btn btn-soft') ?> w-full py-3">
                <span x-show="!restoring" class="inline-flex items-center gap-2"><?= icon('upload', 'gc-icon-sm') ?> Восстановить</span>
                <span x-show="restoring" style="display:none" class="inline-flex items-center gap-2"><?= icon('hourglass', 'gc-icon-sm') ?> Восстановление...</span>
            </button>
        </div>
    </div>

    <!-- SITEMAP -->
    <div class="mt-6 bg-white rounded-2xl shadow-md p-6" data-aos="fade-up">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <h3 class="font-bold text-gray-800 flex items-center gap-2"><?= icon('globe', 'gc-icon-sm') ?> Sitemap.xml</h3>
            <div x-show="sitemapInfo.exists" class="text-xs text-gray-400">
                Обновлён: <span x-text="sitemapInfo.generated_at"></span> ·
                URL: <span x-text="sitemapInfo.url_count"></span> ·
                <span x-text="sitemapInfo.size_human"></span>
            </div>
            <div x-show="!sitemapInfo.exists && sitemapInfo.loaded" class="text-xs text-amber-600">
                sitemap.xml ещё не создан
            </div>
        </div>

        <p class="text-sm text-gray-500 mb-4">
            Карта сайта для поисковых систем. <strong>Страницы и категории добавляются всегда</strong> —
            лимит действует только на рецепты, чтобы файл не разбухал.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="<?= admin_cls('label') ?>">Рецептов в sitemap</label>
                <select x-model.number="recipesLimit"
                        class="<?= admin_cls('input') ?>">
                    <option value="100">100</option>
                    <option value="500">500</option>
                    <option value="1000">1 000</option>
                    <option value="5000">5 000</option>
                    <option value="10000">10 000</option>
                    <option value="20000">20 000</option>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-end gap-2">
                <button @click="generateSitemap()" :disabled="sitemapLoading"
                        class="<?= admin_cls('btn btn-soft') ?> flex-1">
                    <span x-show="!sitemapLoading" class="inline-flex items-center gap-2"><?= icon('refresh', 'gc-icon-sm') ?> Обновить sitemap</span>
                    <span x-show="sitemapLoading" style="display:none" class="inline-flex items-center gap-2"><?= icon('hourglass', 'gc-icon-sm') ?> Генерация…</span>
                </button>
                <a :href="'<?= url('sitemap.xml') ?>'" target="_blank"
                   x-show="sitemapInfo.exists"
                   class="<?= admin_cls('btn btn-ghost') ?>">
                    <?= icon('eye', 'gc-icon-sm') ?> Открыть
                </a>
            </div>
        </div>

        <div x-show="sitemapMessage" x-transition
             class="mt-4 px-4 py-3 rounded-xl text-sm"
             :class="sitemapMessageType === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
             x-text="sitemapMessage"></div>
    </div>

    <!-- Список бэкапов -->
    <div class="mt-8 bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('folder', 'gc-icon-sm') ?> Существующие бэкапы</div>
        <template x-for="b in backups" :key="b.name">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <span class="text-sm text-gray-700 inline-flex items-center gap-2 min-w-0 truncate"><?= icon('file', 'gc-icon-sm') ?><span class="truncate" x-text="b.name"></span></span>
                <span class="text-xs text-gray-400" x-text="b.size"></span>
                <div class="flex gap-2">
                    <a :href="b.url" class="<?= admin_cls('ibtn ibtn-ok') ?>" title="Скачать" aria-label="Скачать"><?= icon('download', 'gc-icon-sm') ?></a>
                    <button @click="deleteBackup(b.name)" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                </div>
            </div>
        </template>
        <div x-show="!backups.length" class="px-5 py-6 text-center text-sm text-gray-400">
            Бэкапов пока нет
        </div>
    </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminBackup', () => ({
        // Бэкапы
        loading: false,
        restoring: false,
        file: null,
        backupUrl: null,
        backups: [],

        // Sitemap
        recipesLimit: 1000,
        sitemapLoading: false,
        sitemapMessage: '',
        sitemapMessageType: 'success',
        sitemapInfo: {
            exists: false,
            loaded: false,
            url_count: 0,
            size_human: '',
            generated_at: '',
        },

        async init() {
            await this.loadBackups();
            await this.loadSitemapInfo();
        },

        /* ─── Бэкапы ─── */
        async loadBackups() {
            try {
                const res = await fetch('/api/admin/backup.php?action=list');
                const data = await res.json();
                this.backups = data.backups || [];
            } catch (e) { console.error(e); }
        },

        async createBackup() {
            this.loading = true;
            try {
                const res = await fetch('/api/admin/backup.php?action=create');
                const data = await res.json();
                this.backupUrl = data.url;
                await this.loadBackups();
            } catch (e) {
                alert('Ошибка создания бэкапа');
            } finally {
                this.loading = false;
            }
        },

        handleFile(e) {
            this.file = e.target.files[0];
        },

        async restoreBackup() {
            if (!this.file) return;
            this.restoring = true;
            try {
                const fd = new FormData();
                fd.append('backup', this.file);
                await fetch('/api/admin/backup.php?action=restore', { method: 'POST', body: fd });
                alert('База данных восстановлена!');
            } catch (e) {
                alert('Ошибка восстановления');
            } finally {
                this.restoring = false;
            }
        },

        async deleteBackup(name) {
            if (!confirm('Удалить бэкап ' + name + '?')) return;
            await fetch('/api/admin/backup.php?action=delete&file=' + encodeURIComponent(name));
            await this.loadBackups();
        },

        /* ─── Sitemap ─── */
        async loadSitemapInfo() {
            try {
                const res = await fetch('/api/admin/sitemap.php?action=info');
                const data = await res.json();

                this.sitemapInfo.loaded = true;

                if (data.exists) {
                    this.sitemapInfo.exists       = true;
                    this.sitemapInfo.url_count    = data.url_count || 0;
                    this.sitemapInfo.size_human   = this.humanSize(data.size || 0);
                    this.sitemapInfo.generated_at = this.humanDate(data.generated_at);
                } else {
                    this.sitemapInfo.exists = false;
                }
            } catch (e) {
                console.error(e);
            }
        },

        async generateSitemap() {
            this.sitemapLoading = true;
            this.sitemapMessage = '';
            try {
                const res = await fetch('/api/admin/sitemap.php?action=create&recipes_limit=' + this.recipesLimit);
                const data = await res.json();

                if (!data.ok) {
                    this.sitemapMessage = 'Ошибка: ' + (data.error || 'не удалось');
                    this.sitemapMessageType = 'error';
                    return;
                }

                this.sitemapMessage = 'Готово: рецептов ' + data.recipes +
                                     ', страниц ' + data.pages +
                                     ', категорий ' + data.categories;
                this.sitemapMessageType = 'success';

                await this.loadSitemapInfo();
            } catch (e) {
                this.sitemapMessage = 'Ошибка сети';
                this.sitemapMessageType = 'error';
            } finally {
                this.sitemapLoading = false;

                // Скрываем сообщение через 5 секунд
                setTimeout(() => { this.sitemapMessage = ''; }, 5000);
            }
        },

        humanSize(bytes) {
            if (bytes < 1024) return bytes + ' Б';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
            return (bytes / 1024 / 1024).toFixed(2) + ' МБ';
        },

        humanDate(iso) {
            try {
                return new Date(iso).toLocaleString('ru-RU', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            } catch { return iso; }
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>