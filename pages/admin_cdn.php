<?php
/**
 * Админ-страница: управление CDN.
 * Сейчас модуль выключен, но готов к подключению VK Cloud CDN.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$success = '';
$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Неверный CSRF-токен';
    } else {
        $enabled  = isset($_POST['cdn_enabled']) ? '1' : '0';
        $domain   = trim($_POST['cdn_domain'] ?? '');
        $ttl      = max(60, (int)($_POST['cdn_cache_ttl'] ?? 31536000));
        $apiToken = trim($_POST['cdn_api_token'] ?? '');   // пусто = не менять сохранённый
        $projectId = trim($_POST['cdn_project_id'] ?? '');

        // Валидация домена
        $domain = strtolower(preg_replace('~^https?://~i', '', rtrim($domain, '/')) ?? '');
        if ($domain !== '' && !preg_match('~^(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$~', $domain)) {
            $error = 'Укажите домен вида cdn.gigachef.ru (латинские буквы, цифры, точки и дефисы, без пути и порта)';
        } elseif ($enabled === '1' && $domain === '') {
            $error = 'Чтобы включить CDN, укажите CDN-домен';
        } else {
            cdn_set_setting('cdn_enabled', $enabled);
            cdn_set_setting('cdn_domain', $domain);
            cdn_set_setting('cdn_cache_ttl', (string)$ttl);
            if ($apiToken !== '') {
                cdn_set_setting('cdn_api_token', $apiToken);
            }
            cdn_set_setting('cdn_project_id', $projectId);
            $success = 'Настройки CDN сохранены';

        }
    }
}

$pageTitle = 'CDN — управление доставкой контента';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('cdn', 'globe', 'CDN', 'Доставка файлов через CDN-домен'); ?>
    <?php admin_flash($success, $error); ?>

    <div class="max-w-3xl mx-auto">

    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Статус</h2>
        <div class="flex items-center gap-3 mb-4">
            <span class="inline-block w-3 h-3 rounded-full <?= cdn_is_enabled() ? 'bg-emerald-500' : 'bg-gray-300' ?>"></span>
            <span class="font-semibold <?= cdn_is_enabled() ? 'text-emerald-700' : 'text-gray-500' ?>">
                <?= cdn_is_enabled() ? 'CDN ВКЛЮЧЁН' : 'CDN ВЫКЛЮЧЕН (работает основной домен)' ?>
            </span>
        </div>
        <p class="text-sm text-gray-500">
            Пока CDN выключен, файлы отдаются с основного домена. Через CDN идут только ссылки, построенные функцией <code class="bg-gray-100 px-1 rounded">cdn_url()</code>.
            Когда включите — они пойдут через CDN-домен (например, <code class="bg-gray-100 px-1 rounded">cdn.gigachef.ru</code>).
        </p>
    </div>

    <form method="post" class="bg-white rounded-2xl shadow-md p-6 space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer">
            <input type="checkbox" name="cdn_enabled" value="1" <?= cdn_is_enabled() ? 'checked' : '' ?> class="w-5 h-5 rounded accent-emerald-500">
            <span class="font-semibold text-gray-800">Включить CDN</span>
        </label>

        <div>
            <label class="<?= admin_cls('label') ?>">CDN-домен (например, <code>cdn.gigachef.ru</code>)</label>
            <input type="text" name="cdn_domain" value="<?= e(cdn_get_setting('cdn_domain')) ?>"
                   placeholder="cdn.gigachef.ru"
                   class="<?= admin_cls('input') ?>">
            <p class="text-xs text-gray-400 mt-1">
                Оставьте пустым, если CDN пока не подключён. Домен нужно будет добавить в CNAME-запись у вашего DNS-провайдера.
            </p>
        </div>

        <div>
            <label class="<?= admin_cls('label') ?>">Срок кеша (секунды)</label>
            <input type="number" name="cdn_cache_ttl" value="<?= (int)cdn_get_setting('cdn_cache_ttl', '31536000') ?>"
                   min="60" step="3600"
                   class="<?= admin_cls('input') ?>">
            <p class="text-xs text-gray-400 mt-1">По умолчанию 31536000 (1 год). Это справочное значение: реальный срок хранения задаётся в настройках CDN-ресурса у провайдера. Адреса файлов содержат ?v=&lt;время изменения&gt;, поэтому изменённый файл обновляется сразу.</p>
        </div>

        <div class="pt-4 border-t border-gray-100">
            <h3 class="font-semibold text-gray-800 mb-2">VK Cloud API (для будущего подключения)</h3>
            <p class="text-xs text-gray-400 mb-3">
                Заполните, когда решите подключить VK Cloud CDN. Сейчас можно оставить пустым.
            </p>

            <div class="space-y-3">
                <div>
                    <label class="<?= admin_cls('label') ?>">API-токен VK Cloud</label>
                    <input type="password" name="cdn_api_token" value="" autocomplete="new-password"
                           placeholder="<?= cdn_get_setting('cdn_api_token') !== '' ? 'Сохранён. Оставьте пустым, чтобы не менять' : '...' ?>"
                           class="<?= admin_cls('input') ?> font-mono">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">ID проекта VK Cloud</label>
                    <input type="text" name="cdn_project_id" value="<?= e(cdn_get_setting('cdn_project_id')) ?>"
                           placeholder="..."
                           class="<?= admin_cls('input') ?> font-mono">
                </div>
            </div>
        </div>

        <button type="submit" class="<?= admin_cls('btn btn-primary') ?> w-full">
            <?= icon('diskette', 'gc-icon-sm') ?> Сохранить настройки
        </button>
    </form>

    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mt-6 text-sm text-amber-800">
        <h3 class="font-bold mb-2 flex items-center gap-2"><?= icon('info', 'gc-icon-sm') ?>Как подключить CDN, когда будете готовы</h3>
        <ol class="list-decimal list-inside space-y-1">
            <li>Зарегистрируйтесь в <a href="https://cloud.vk.com" target="_blank" class="underline">VK Cloud</a>.</li>
            <li>Создайте CDN-ресурс, укажите источник — ваш домен <code>gigachef.ru</code>.</li>
            <li>Задайте персональный домен, например <code>cdn.gigachef.ru</code>.</li>
            <li>Добавьте <strong>CNAME-запись</strong> у вашего DNS-провайдера: <code>cdn.gigachef.ru &rarr; &lt;ваш_домен_от_VK&gt;</code>.</li>
            <li>Включите <strong>кеширование</strong> в настройках CDN-ресурса, укажите срок 1 год.</li>
            <li>Вернитесь сюда, впишите домен <code>cdn.gigachef.ru</code>, поставьте галочку «Включить CDN» и сохраните.</li>
        </ol>
    </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>