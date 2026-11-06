<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/ad_manager.php';
// По умолчанию нижнее меню показываем
$__showMobileNav = $__showMobileNav ?? true;

// Определяем путь и скрываем меню на страницах чата/мессенджера
$__path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$__basePath = trim(BASE_PATH, '/');
if ($__basePath && strpos($__path, $__basePath) === 0) {
    $__path = trim(substr($__path, strlen($__basePath)), '/');
}
if (preg_match('~^(chat|chats|admin/messages)(/|$)~', $__path)) {
    $__showMobileNav = false;
}
$__user = current_user();

// ─── Проверка согласия на cookie ───
$__cookieConsent = $_COOKIE['cookie_consent'] ?? null;
$__cookieConsentValid = false;

if ($__cookieConsent) {
    // Формат cookie: "version|accepted_at"
    $__parts = explode('|', $__cookieConsent);
    if (count($__parts) === 2 && $__parts[0] === COOKIE_POLICY_VERSION) {
        $__cookieConsentValid = true;
    }
}

$__showCookieBanner = !$__cookieConsentValid;

// ─── Счётчик непрочитанных сообщений админу ───
$__unreadAdmin = 0;
if (is_logged_in() && is_admin()) {
    try {
        $__row = db_one(
            "SELECT COUNT(*) AS cnt FROM admin_chat_messages
             WHERE to_email = ? AND is_read = 0",
            [$__user['email']]
        );
        $__unreadAdmin = (int)($__row['cnt'] ?? 0);
    } catch (Throwable $e) {
        $__unreadAdmin = 0;
    }
}

$__currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$__basePath = trim(BASE_PATH, '/');
if ($__basePath && strpos($__currentPath, $__basePath) === 0) {
    $__currentPath = trim(substr($__currentPath, strlen($__basePath)), '/');
}

// Нормализуем главную страницу
if ($__currentPath === '' || $__currentPath === 'index.php' || $__currentPath === 'home') {
    $__currentPath = '';
}

// Функция получения мета-тегов из базы
if (!function_exists('get_page_meta')) {
    function get_page_meta($pagePath) {
        try {
            return db_one("SELECT * FROM page_meta WHERE page_path = ?", [$pagePath]);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('apply_meta')) {
    function apply_meta($meta) {
        global $pageTitle, $pageDescription, $pageKeywords, $pageH1;
        
        if ($meta) {
            if (!empty($meta['meta_title'])) {
                $pageTitle = $meta['meta_title'];
            }
            if (!empty($meta['meta_description'])) {
                $pageDescription = $meta['meta_description'];
            }
            if (!empty($meta['meta_keywords'])) {
                $pageKeywords = $meta['meta_keywords'];
            }
            if (!empty($meta['h1'])) {
                $pageH1 = $meta['h1'];
            }
        }
    }
}

// Устанавливаем значения по умолчанию для мета-тегов
$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'GigaChef - умная кулинарная социальная сеть с AI-генератором рецептов';
$pageKeywords = $pageKeywords ?? 'рецепты, кулинария, готовка, ИИ, GigaChef';
$pageImage = $pageImage ?? url('assets/icon/chef_256.png');
$pageUrl = $pageUrl ?? url('');
$pageType = $pageType ?? 'website';
$pageH1 = $pageH1 ?? $pageTitle;

// Пытаемся найти мета-теги для текущей страницы в базе данных
try {
    $currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $basePath = trim(BASE_PATH, '/');
    if ($basePath && strpos($currentPath, $basePath) === 0) {
        $currentPath = trim(substr($currentPath, strlen($basePath)), '/');
    }
    
    // Определяем путь для поиска мета-тегов
    $metaPath = '/' . $currentPath;
    
    // Для главной страницы
    if ($currentPath === '' || $currentPath === 'index.php' || $currentPath === 'home' || $currentPath === 'feed') {
        $metaPath = '/';
    }
    
    // Получаем мета-теги из базы
    $metaFromDb = db_one('SELECT * FROM page_meta WHERE page_path = ?', [$metaPath]);
    
    if ($metaFromDb) {
        apply_meta($metaFromDb);
    }
} catch (Throwable $e) {
    error_log('Meta tags lookup failed: ' . $e->getMessage());
}

// Сохраняем H1 в глобальную переменную для использования в шаблонах
$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

// Счётчики с кешем — 1 раз в 20 сек на пользователя, а не на каждый запрос
$unreadNotifications = 0;
$friendRequests = 0;
$unreadMessages = 0;

if ($__user) {
    $email = $__user['email'];
    $cacheKey = 'user_counters_' . md5($email);
    $cached = function_exists('cache_get') ? cache_get($cacheKey) : null;

    if (is_array($cached)) {
        $unreadNotifications = (int)($cached['notifications'] ?? 0);
        $friendRequests      = (int)($cached['friends'] ?? 0);
        $unreadMessages      = (int)($cached['chats'] ?? 0);
    } else {
        // Собрать все три счётчика за один проход
        try {
            $row = db_one(
                "SELECT
                    (SELECT COUNT(*) FROM notifications WHERE user_email = ? AND is_read = 0) AS notifications,
                    (SELECT COUNT(*) FROM friendships WHERE friend_email = ? AND status = 'pending') AS friends,
                    (SELECT COUNT(*) FROM chat_messages WHERE receiver_email = ? AND is_read = 0) AS chats",
                [$email, $email, $email]
            );
            $unreadNotifications = (int)($row['notifications'] ?? 0);
            $friendRequests      = (int)($row['friends'] ?? 0);
            $unreadMessages      = (int)($row['chats'] ?? 0);
        } catch (Throwable $e) {
            // игнорируем, оставляем нули
        }

        // Кешируем на 20 секунд — короче, чем polling, чтобы данные были свежими
        if (function_exists('cache_set')) {
            cache_set($cacheKey, [
                'notifications' => $unreadNotifications,
                'friends'       => $friendRequests,
                'chats'         => $unreadMessages,
            ], 20);
        }
    }
}

// Админ-счётчик (отдельно, т.к. только для админов)
$__unreadAdmin = 0;
if (is_logged_in() && is_admin()) {
    $admCacheKey = 'user_admin_counter_' . md5($__user['email']);
    $cachedAdmin = function_exists('cache_get') ? cache_get($admCacheKey) : null;
    if ($cachedAdmin !== null) {
        $__unreadAdmin = (int)$cachedAdmin;
    } else {
        try {
            $row = db_one(
                "SELECT COUNT(*) AS cnt FROM admin_chat_messages
                 WHERE to_email = ? AND is_read = 0",
                [$__user['email']]
            );
            $__unreadAdmin = (int)($row['cnt'] ?? 0);
        } catch (Throwable $e) {}
        if (function_exists('cache_set')) {
            cache_set($admCacheKey, $__unreadAdmin, 20);
        }
    }
}
?>
<?php
// Тема пользователя: 'default' (светлая) или 'dark'
$__u = function_exists('current_user') ? current_user() : null;
$__theme = (is_array($__u) && !empty($__u['theme']) && $__u['theme'] === 'dark') ? 'dark' : 'default';
?>
<!doctype html>
<html lang="ru" class="scroll-smooth <?= $__theme === 'dark' ? 'theme-dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="base-path" content="<?= e(BASE_PATH) ?>">
    
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <meta name="robots" content="index, follow">
    <meta name="author" content="GigaChef">
    <link rel="canonical" href="<?= e($pageUrl) ?>">
    
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="<?= e($pageType) ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta property="og:site_name" content="GigaChef">
    <meta property="og:locale" content="ru_RU">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($pageImage) ?>">
    <meta name="cookie-policy-version" content="<?= e(COOKIE_POLICY_VERSION) ?>">
    
    <link rel="icon" type="image/x-icon" href="<?= url('assets/icon/chef_16.ico') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/icon/chef_16.png') ?>">
    <link rel="icon" type="image/png" sizes="24x24" href="<?= url('assets/icon/chef_24.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/icon/chef_32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= url('assets/icon/chef_64.png') ?>">
    <link rel="icon" type="image/png" sizes="128x128" href="<?= url('assets/icon/chef_128.png') ?>">
    <link rel="icon" type="image/png" sizes="256x256" href="<?= url('assets/icon/chef_256.png') ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= url('assets/icon/chef_512.png') ?>">
    
    <link rel="apple-touch-icon" sizes="57x57" href="<?= url('assets/icon/chef_64.png') ?>">
    <link rel="apple-touch-icon" sizes="72x72" href="<?= url('assets/icon/chef_64.png') ?>">
    <link rel="apple-touch-icon" sizes="76x76" href="<?= url('assets/icon/chef_64.png') ?>">
    <link rel="apple-touch-icon" sizes="114x114" href="<?= url('assets/icon/chef_128.png') ?>">
    <link rel="apple-touch-icon" sizes="120x120" href="<?= url('assets/icon/chef_128.png') ?>">
    <link rel="apple-touch-icon" sizes="144x144" href="<?= url('assets/icon/chef_128.png') ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= url('assets/icon/chef_256.png') ?>">
    <link rel="apple-touch-icon" sizes="167x167" href="<?= url('assets/icon/chef_256.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/icon/chef_256.png') ?>">
    
    <meta name="msapplication-TileImage" content="<?= url('assets/icon/chef_128.png') ?>">
    <meta name="msapplication-TileColor" content="#f59e0b">
    
    <link rel="manifest" href="<?= url('manifest.json') ?>">
    
    <?php if (function_exists('csrf_token')): ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php endif; ?>
    <?php if (!empty($extraCss)): ?>
    <link rel="stylesheet" href="<?= e($extraCss) ?>">
    <?php endif; ?>
    
    <script>
      (function() {
        // Если пользователь авторизован, PHP уже поставил класс на <html>.
        // Скрипт нужен для гостей или если тема меняется без перезагрузки.
        // В нашем случае PHP уже знает тему, поэтому этот скрипт — дубль.
        // Но если вы хотите мгновенную смену без перезагрузки:
        if (localStorage.getItem('gc_theme') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
      })();
    </script>


    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Nunito','system-ui','sans-serif'] } } }
        };
    </script>
    <link rel="stylesheet" href="<?= url('assets/css/ads.css') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/css/ads.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= url('assets/css/themes.css') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/css/themes.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= url('assets/css/icons.css') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/css/icons.css') ?: time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <script defer src="<?= url('assets/js/vendor/alpine.min.js') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/js/vendor/alpine.min.js') ?>"></script>
<style>

    /* ===== Компактный сайдбар ===== */
    .sidebar-nav .nav-link {
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        margin-bottom: 1px !important;
    }
    .sidebar-nav .nav-group {
        padding-top: 0.5rem !important;
        padding-bottom: 0.25rem !important;
    }
    .sidebar-nav {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    .sidebar-nav .space-y-1 > * + * {
        margin-top: 0.125rem !important;
    
}
</style>
<?php if (function_exists('csrf_token')): ?>
<script>window.__CSRF_TOKEN__ = <?= json_encode(csrf_token()) ?>;</script>
<?php endif; ?>
<script>
window.DEFAULT_AVATAR_SVG_MALE    = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
window.DEFAULT_AVATAR_SVG_FEMALE  = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;

window.getAvatarSvg = function(gender) {
    if (gender === 'male')   return window.DEFAULT_AVATAR_SVG_MALE;
    if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
    return window.DEFAULT_AVATAR_SVG_NEUTRAL;
};
</script>
</head>
<body class="font-sans antialiased flex flex-col min-h-screen">

<?php if ($__showCookieBanner): ?>
<!-- ===== COOKIE BANNER ===== -->
<div id="cookie-banner"
     role="dialog"
     aria-modal="false"
     aria-labelledby="cookie-banner-title"
     aria-describedby="cookie-banner-text"
     class="fixed bottom-0 inset-x-0 z-[300] bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]"
     style="padding-bottom: env(safe-area-inset-bottom, 0px);">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
        
        <!-- Иконка -->
        <div class="shrink-0 hidden md:flex w-10 h-10 rounded-full bg-amber-100 items-center justify-center text-amber-600">
            <?= icon('cookie') ?>
        </div>

        <!-- Текст -->
        <div class="flex-1 min-w-0">
            <h2 id="cookie-banner-title" class="font-extrabold text-gray-600 text-sm mb-0.5">
                Мы используем файлы cookie
            </h2>
            <p id="cookie-banner-text" class="text-xs text-gray-500 leading-snug">
                Для корректной работы сайта, анализа посещаемости и улучшения сервиса мы используем файлы cookie. 
                Продолжая пользоваться сайтом, вы соглашаетесь с 
                <a href="<?= url('privacy') ?>" class="text-amber-600 hover:text-amber-700 underline font-semibold">политикой конфиденциальности</a>.
            </p>
        </div>

        <!-- Кнопки -->
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= url('privacy') ?>"
               class="hidden sm:inline-flex px-3 py-2 rounded-xl text-xs font-bold text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition">
                Подробнее
            </a>
            <button type="button"
                    id="cookie-accept-btn"
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-orange-400 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-xs shadow-sm hover:shadow-md transition">
                Принять
            </button>
            <button type="button"
                    id="cookie-close-btn"
                    class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition"
                    aria-label="Закрыть">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// ─── Баннер админу о текущих техработах ───
$maintBanner = function_exists('cookai_maintenance_notice') ? cookai_maintenance_notice() : null;
?>
<?php if ($maintBanner): ?>
<div x-data="maintenanceAdminBanner(<?= (int)($maintBanner['until_ts'] ?? 0) ?>, '<?= e($maintBanner['until']) ?>')"
     x-init="init()" x-show="visible" x-cloak
     class="sticky top-0 z-50 bg-gradient-to-r from-amber-400 to-orange-400 text-amber-950 shadow-md">
    <div class="max-w-6xl mx-auto px-4 py-2.5 flex items-center gap-3 text-sm font-semibold">
        <span class="text-lg shrink-0"><?= icon('technical') ?>️</span>
        <div class="flex-1 min-w-0">
            <strong>Режим техработ включён.</strong>
            <?php if (!empty($maintBanner['until'])): ?>
                До окончания: <span x-text="label"></span>.
            <?php else: ?>
                Автоматическое окончание не задано.
            <?php endif; ?>
            Пользователи видят заглушку.
        </div>
        <a href="<?= url('admin/maintenance') ?>"
           class="shrink-0 px-3 py-1 rounded-lg bg-amber-900/10 hover:bg-amber-900/20 transition text-xs font-bold">
            Управление
        </a>
        <button @click="dismiss()"
                class="shrink-0 w-7 h-7 rounded-full hover:bg-amber-500/30 transition flex items-center justify-center"
                aria-label="Скрыть"><?= icon('close') ?></button>
    </div>
</div>
<script>
function maintenanceAdminBanner(untilTs, untilFull) {
    return {
        visible: true,
        seconds: 0,
        label: '',
        untilFull: untilFull,
        timer: null,
        _initialized: false,
        _endTime: 0,

        init() {
            // Защита от двойного вызова (Alpine может вызвать init дважды)
            if (this._initialized) return;
            this._initialized = true;

            // Защита от утечки старого таймера
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }

            if (sessionStorage.getItem('maint_admin_dismissed') === this.untilFull) {
                this.visible = false;
                return;
            }

            var now = Math.floor(Date.now() / 1000);
            this.seconds = Math.max(0, untilTs - now);

            if (this.seconds <= 0) {
                this.visible = false;
                return;
            }

            // Запоминаем абсолютный момент окончания
            // Это защита от двойного тика: даже если setInterval сработает дважды,
            // мы каждый раз пересчитываем остаток от _endTime, а не декрементим
            this._endTime = Date.now() + this.seconds * 1000;

            this.render();

            var self = this;
            this.timer = setInterval(function () {
                var remaining = Math.max(0, Math.floor((self._endTime - Date.now()) / 1000));
                self.seconds = remaining;

                if (remaining <= 0) {
                    self.visible = false;
                    clearInterval(self.timer);
                    self.timer = null;
                    return;
                }

                self.render();
            }, 1000);
        },

        render() {
            var m = Math.floor(this.seconds / 60);
            var s = this.seconds % 60;
            this.label = m > 0
                ? (m + ' мин ' + String(s).padStart(2, '0') + ' с')
                : (s + ' с');
        },

        dismiss() {
            this.visible = false;
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            sessionStorage.setItem('maint_admin_dismissed', this.untilFull);
        }
    };
}
</script>
<?php endif; ?>

<?php
// ─── Баннер «Мы вернулись» для всех ───
if (function_exists('maintenance_should_show_finished') && maintenance_should_show_finished()):
    $__maintHash = maintenance_get_settings()['maintenance_finished_notice_until'] ?? '';
?>
<div id="gc-returned-banner"
     data-hash="<?= e($__maintHash) ?>"
     class="sticky top-0 z-50 bg-gradient-to-r from-emerald-400 to-teal-400 text-white shadow-md">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3 text-sm font-bold">
        <span class="text-xl"><?= icon('balloons') ?></span>
        <span class="flex-1">
            Мы вернулись! Технические работы завершены.
        </span>
        <a href="<?= url('') ?>"
           class="px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 transition">
            На главную
        </a>
        <button type="button"
                onclick="
                    var b = document.getElementById('gc-returned-banner');
                    if (!b) return;
                    var h = b.getAttribute('data-hash') || '';
                    localStorage.setItem('gc_returned_dismissed_hash', h);
                    b.remove();
                "
                class="w-7 h-7 rounded-full hover:bg-white/20 transition flex items-center justify-center"
                aria-label="Скрыть"><?= icon('close') ?></button>
    </div>
</div>
<script>
(function() {
    var banner = document.getElementById('gc-returned-banner');
    if (!banner) return;
    var hash = banner.getAttribute('data-hash') || '';
    var dismissed = localStorage.getItem('gc_returned_dismissed_hash');
    if (dismissed === hash && hash !== '') {
        banner.remove();
    }
})();
</script>
<?php endif; ?>
<!-- ===== МОБИЛЬНАЯ ШАПКА (только для lg:hidden) ===== -->
<header class="lg:hidden sticky top-0 z-40 mobile-header backdrop-blur-md border-b"
        x-data>
    <div class="flex items-center gap-2 px-3 h-14">

        <!-- Бургер -->
        <button type="button"
                @click="$dispatch('open-burger')"
                class="w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center shrink-0 text-gray-700"
                aria-label="Открыть меню">
            <?= icon('menu') ?>
        </button>

        <!-- Логотип -->
        <a href="<?= url('') ?>" class="flex items-center gap-2 min-w-0">
            <img src="<?= url('assets/icon/chef_32.png') ?>" alt="GigaChef" class="w-8 h-8 rounded-lg shrink-0">
            <span class="font-extrabold text-amber-700 truncate">GigaChef ß</span>
        </a>

        <div class="flex-1"></div>

        <!-- Поиск -->
        <a href="<?= url('search') ?>"
           class="w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center shrink-0 text-gray-600"
           aria-label="Поиск">
            <?= icon('search') ?>
        </a>

        <!-- Чаты с бейджем -->
        <a href="<?= url('chats') ?>"
           class="relative w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center shrink-0 text-gray-600"
           aria-label="Чаты">
            <?= icon('chats') ?>
            <span data-badge="chats"
                  class="absolute top-1 right-1 min-w-[16px] h-[16px] px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center"
                  style="<?= ($unreadMessages ?? 0) > 0 ? '' : 'display:none' ?>">
                <?= (int)($unreadMessages ?? 0) ?>
            </span>
        </a>

        <!-- Профиль (аватар) -->
        <?php if (!empty($__user)): ?>
            <a href="<?= url('profile') ?>"
               class="w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center shrink-0"
               aria-label="Профиль">
                <?= render_avatar(
    $__user,
    'w-7 h-7 rounded-full object-cover ring-2 ring-amber-200',
    'w-7 h-7 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
    'text-base'
) ?>
            </a>
        <?php else: ?>
            <a href="<?= url('login') ?>"
               class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-700 font-bold text-xs shrink-0">
                Войти
            </a>
        <?php endif; ?>
    </div>
</header>
<!-- ===== САЙДБАР ===== -->
<aside class="hidden lg:flex fixed top-0 left-0 bottom-0 w-64 bg-white border-r border-gray-200 z-40 flex-col">
    
    <!-- Логотип -->
    <a href="<?= url('') ?>" class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 shrink-0">
        <img src="<?= url('assets/icon/chef_64.png') ?>" alt="GigaChef" class="w-9 h-9 rounded-xl object-cover">
        <span class="font-extrabold text-xl text-amber-700">GigaChef ß</span>
    </a>
    
    <!-- Навигация -->
    <nav class="sidebar-nav flex-1 overflow-y-auto py-3 px-3 space-y-0.5">
        
<!-- Основные -->
        <a href="<?= url('') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === '' ? 'bg-amber-50 text-amber-700' : 'hover:bg-amber-50 hover:text-amber-700' ?> transition">
            <?= icon('home') ?>
            Моя лента
        </a>
        
        <a href="<?= url('feed') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'feed' ? 'bg-amber-50 text-amber-700' : 'hover:bg-amber-50 hover:text-amber-700' ?> transition">
            <?= icon('feed') ?>
            Все активности
        </a>
        
        <a href="<?= url('stories') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'stories' ? 'bg-blue-50 text-blue-600' : 'hover:bg-blue-50 hover:text-blue-600' ?> transition">
            <?= icon('stories') ?>
            Истории
        </a>
        
        <a href="<?= url('search') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'search' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('search') ?>
            Поиск
        </a>
        
        <div class="nav-group pt-2 pb-1">
            
        <hr class="my-1 border-t border-gray-300">
        
        <div class="px-4 flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-wider transition">
            <?= icon('ai') ?> 
            AI-инструменты
        </div>
            
        <hr class="my-1 border-t border-gray-300">

        <a href="<?= url('ai-generator') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'ai-generator' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('aigenerator') ?>
            Генератор рецептов
        </a>
        
        <a href="<?= url('what-to-cook') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'what-to-cook' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('whattocook') ?>
            Что приготовить?
        </a>
        
        <a href="<?= url('fusion') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'fusion' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('fusion') ?>
            Фьюжн-кухня
        </a>
        
        <a href="<?= url('calorie-scanner') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'calorie-scanner' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('scaner') ?>
            Сканер калорий
        </a>
        
        <a href="<?= url('beer-pairing') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'beer-pairing' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('beer') ?>
            Бирпейринг
        </a>
        
        <a href="<?= url('dietolog') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'dietolog' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('diet') ?>
            Диетолог
        </a>
        
        <a href="<?= url('quit-smoking') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'quit-smoking' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('smoking') ?>
            Бросаем курить
        </a>
        
        <a href="<?= url('ai-cookbook') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'ai-cookbook' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('cookbook') ?>
            AI-книга
        </a>
        
        <a href="<?= url('ai-holiday') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'ai-holiday' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('balloons') ?>
            Праздники
        </a>
        
        <!-- Сообщества -->
        <div class="nav-group pt-2 pb-1">
            
        <hr class="my-1 border-t border-gray-300">
            <div class="px-4 flex items-center text-xs font-bold text-gray-400 uppercase tracking-wider transition">
                <?= icon('team') ?>
                &ensp;Сообщества</div>
            
        <hr class="my-1 border-t border-gray-300">
        </div>
        
        <a href="<?= url('communities') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'communities' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('communities') ?>
            Сообщества
        </a>
        
        <a href="<?= url('challenges') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'challenges' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('challenges') ?>
            Челленджи
        </a>
        
        <a href="<?= url('leaderboard') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'leaderboard' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('leader') ?>
            Лидеры
        </a>
        
        <a href="<?= url('friends') ?>" class="nav-link relative flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'friends' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('friends') ?>
            <span class="flex-0">Друзья</span>
            <span data-badge="friends"
          class="min-w-[18px] h-[18px] px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center"
          style="display:none" hidden></span>
        </a>
        
        <a href="<?= url('videos') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'videos' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('video') ?>
            Видео
        </a>
        
        <a href="<?= url('achievements') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'achievements' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('achievements') ?>
            Достижения
        </a>
        
        <a href="<?= url('games') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'games' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('games') ?>
            Игры
        </a>
        
        <!-- Личное -->
        <div class="nav-group pt-2 pb-1">
            
        <hr class="my-1 border-t border-gray-300">
            <div class="px-4 flex items-center text-xs font-bold text-gray-400 uppercase tracking-wider transition">
                <?= icon('admin') ?>
                &ensp;Личное</div>
            
        <hr class="my-1 border-t border-gray-300">
        </div>
        
        <a href="<?= url('my-cookbooks') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'my-cookbooks' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('cookbook') ?>
            Мои книги
        </a>
        
        <a href="<?= url('my-holidays') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'my-holidays' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
            <?= icon('balloons') ?>
            Мои праздники
        </a>
        

        
        <?php if (is_admin()): ?>
        <div class="nav-group pt-2 pb-1">
            <hr class="my-1 border-t border-gray-300">
            <div class="px-4 flex gap-2 items-center text-xs font-bold text-gray-400 uppercase tracking-wider transition">
            <?= icon('settings') ?> 
            Администрирование</div>
            <hr class="my-1 border-t border-gray-300">
        </div>
        
        <a href="<?= url('admin') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'admin' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
    <?= icon('admin') ?>
    Админ-панель
</a>

<a href="<?= url('admin/messages') ?>" class="nav-link flex items-center gap-3 px-4 py-1.5 rounded-lg text-sm font-semibold text-gray-700 <?= $__currentPath === 'admin/messages' ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-emerald-50 hover:text-emerald-700' ?> transition">
    <?= icon('chats') ?>
    <span class="flex-1">Сообщения</span>
    <span data-badge="admin"
          class="min-w-[18px] h-[18px] px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center"
          style="<?= ($__unreadAdmin ?? 0) > 0 ? '' : 'display:none' ?>">
        <?= (int)($__unreadAdmin ?? 0) ?>
    </span>
</a>
        <?php endif; ?>
    </nav>
    
    <!-- Переключатель темы -->
<div class="border-t border-gray-100 px-3 py-3 shrink-0">
    <button type="button"
            id="gc-theme-toggle-btn"
            class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition"
            aria-label="Переключить тему">
        <span class="gc-theme-icon-light text-xl leading-none"><?= icon('day') ?></span>
        <span class="gc-theme-icon-dark text-xl leading-none" style="display:none"><?= icon('night') ?></span>
        <span class="gc-theme-label flex-1 text-left">Тёмная тема</span>
    </button>
</div>

<script>
(function() {
    function getInitialTheme() {
        var saved = localStorage.getItem('gc_theme');
        if (saved === 'dark' || saved === 'light') return saved;
        if (document.documentElement.classList.contains('theme-dark')) return 'dark';
        return 'light';
    }

    function applyTheme(theme) {
        var isDark = theme === 'dark';
        var root = document.documentElement;

        if (isDark) {
            root.classList.add('theme-dark');
        } else {
            root.classList.remove('theme-dark');
        }
        localStorage.setItem('gc_theme', theme);

        // UI кнопки
        var lightIcon = document.querySelector('.gc-theme-icon-light');
        var darkIcon  = document.querySelector('.gc-theme-icon-dark');
        var label     = document.querySelector('.gc-theme-label');

                if (isDark) {
            root.classList.add('theme-dark');
            // ПРИНУДИТЕЛЬНО переопределяем переменные
            root.style.setProperty('--bg-app', '#0f172a', 'important');
            root.style.setProperty('--bg-body', '#020617', 'important');
            root.style.setProperty('--bg-card', '#1e293b', 'important');
            root.style.setProperty('--bg-hover', '#334155', 'important');
            root.style.setProperty('--text-main', '#f1f5f9', 'important');
            root.style.setProperty('--text-muted', '#94a3b8', 'important');
            root.style.setProperty('--border-main', '#334155', 'important');
        } else {
            root.classList.remove('theme-dark');
            // Возвращаем светлые переменные
            root.style.setProperty('--bg-app', '#f9fafb', 'important');
            root.style.setProperty('--bg-body', '#e5e7eb', 'important');
            root.style.setProperty('--bg-card', '#ffffff', 'important');
            root.style.setProperty('--bg-hover', '#f3f4f6', 'important');
            root.style.setProperty('--text-main', '#374151', 'important');
            root.style.setProperty('--text-muted', '#6b7280', 'important');
            root.style.setProperty('--border-main', '#e5e7eb', 'important');
        }

        // Перекрашиваем цветные плашки (сайдбар, меню)
        var colorMap = {
            'bg-amber-50':   { dark: 'rgba(245, 158, 11, 0.15)', light: '#fef3c7',  textDark: '#fbbf24', textLight: '#b45309' },
            'bg-blue-50':    { dark: 'rgba(59, 130, 246, 0.15)', light: '#dbeafe',  textDark: '#93c5fd', textLight: '#1d4ed8' },
            'bg-emerald-50': { dark: 'rgba(16, 185, 129, 0.15)', light: '#d1fae5',  textDark: '#6ee7b7', textLight: '#047857' },
            'bg-violet-50':  { dark: 'rgba(139, 92, 246, 0.15)', light: '#ede9fe',  textDark: '#c4b5fd', textLight: '#6d28d9' },
            'bg-rose-50':    { dark: 'rgba(244, 63, 94, 0.15)',  light: '#ffe4e6',  textDark: '#fda4af', textLight: '#be123c' }
        };

        Object.keys(colorMap).forEach(function(cls) {
            var cfg = colorMap[cls];
            document.querySelectorAll('.' + cls).forEach(function(el) {
                el.style.setProperty('background-color', isDark ? cfg.dark : cfg.light, 'important');
                el.style.setProperty('color', isDark ? cfg.textDark : cfg.textLight, 'important');
                
                el.querySelectorAll('svg, .gc-icon').forEach(function(icon) {
                    icon.style.setProperty('color', isDark ? cfg.textDark : cfg.textLight, 'important');
                    icon.style.setProperty('fill', 'currentColor', 'important');
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var theme = getInitialTheme();
        applyTheme(theme);

        document.querySelectorAll('[id^="gc-theme-toggle-btn"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var current = localStorage.getItem('gc_theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        });
    });
})();
</script>
    
    <!-- Пользователь -->
    <div class="border-t border-gray-100 p-3 shrink-0">
        <?php if ($__user): ?>
            <div class="flex items-center gap-2 mb-2">
                <a href="<?= url('profile') ?>" class="shrink-0">
                    <?= render_avatar(
    $__user,
    'w-7 h-7 rounded-full object-cover ring-2 ring-amber-200',
    'w-7 h-7 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
    'text-base'
) ?>
                </a>
                <div class="min-w-0 flex-1">
                    <a href="<?= url('profile') ?>" class="block font-bold text-gray-600 text-sm hover:text-amber-600 truncate">
                        <?= e($__user['display_name'] ?? 'Профиль') ?>
                    </a>
                    <a href="<?= url('profile') ?>" class="block text-xs text-gray-400 hover:text-amber-600 truncate">
                        Открыть профиль
                    </a>
                </div>
            </div>
            
            <div class="flex gap-1.5">
                <!-- Чаты с бейджем непрочитанных сообщений -->
<a href="<?= url('chats') ?>"
   class="relative flex-1 py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 transition text-center text-gray-600 text-sm"
   aria-label="Чаты">
    <!-- Иконка чата -->
    <?= icon('chats') ?>

    <!-- Бейдж непрочитанных сообщений -->
    <span data-badge="chats"
          class="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center shadow-sm"
          style="<?= ($unreadMessages ?? 0) > 0 ? '' : 'display:none' ?>"
          <?= ($unreadMessages ?? 0) > 0 ? '' : 'hidden' ?>>
        <?= (int)($unreadMessages ?? 0) ?>
    </span>
</a>

                <!-- Уведомления: единый бейдж (уведомления + заявки) -->
<a href="<?= url('notifications') ?>"
   class="relative flex-1 py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 transition text-center text-gray-600 text-sm"
   aria-label="Уведомления">
    <?= icon('notifications') ?>

    <?php
    // Единый бейдж: уведомления + входящие заявки в друзья
    $__totalNotifications = (int)($unreadNotifications ?? 0) + (int)($friendRequests ?? 0);
    ?>
    <span data-badge="notifications-total"
          class="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center"
          style="<?= $__totalNotifications > 0 ? '' : 'display:none' ?>"
          <?= $__totalNotifications > 0 ? '' : 'hidden' ?>>
        <?= $__totalNotifications ?>
    </span>
</a>

                <a href="<?= url('create-recipe') ?>" class="flex-1 py-1.5 rounded-lg bg-gradient-to-r from-amber-200 to-orange-200 hover:shadow-md transition text-center text-amber-800 font-bold text-sm">
  <?= icon('plus') ?>
</a>

                <a href="<?= url('logout') ?>" class="relative flex-1 py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 transition text-center text-gray-600 text-sm">
  <!-- Иконка выхода -->
  <?= icon('exit') ?>
</a>

            </div>
        <?php else: ?>
            <div class="space-y-1.5">
                <a href="<?= url('login') ?>" class="block w-full py-2 rounded-lg bg-amber-100 text-amber-700 font-bold text-sm text-center hover:bg-amber-200 transition">Войти</a>
                <a href="<?= url('register') ?>" class="block w-full py-2 rounded-lg bg-gradient-to-r from-amber-200 to-orange-200 text-amber-800 font-bold text-sm text-center hover:shadow-md transition">Регистрация</a>
                <a href="<?= url('payment') ?>" class="block w-full py-2 rounded-lg bg-gray-50 text-gray-600 font-semibold text-sm text-center hover:bg-gray-100 transition"><?= icon('payment') ?> Тарифы</a>
            </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Основной контент с отступом под сайдбар -->
<main class="lg:pl-64 flex-1 <?= !empty($__showMobileNav) ? 'pb-20' : 'pb-0' ?> lg:pb-0">