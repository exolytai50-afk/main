<?php
/**
 * GigaChefAI — центральная конфигурация и bootstrap.
 * Подключается ПЕРВЫМ в каждом entry-point (pages/*, api/*, cron/*).
 * 
 * ВАЖНО: Учётные данные загружаются из переменных окружения (.env),
 * а НЕ из исходного кода!
 */
declare(strict_types=1);

// ============================================================
//  ЗАГРУЗКА .ENV ФАЙЛА
// ============================================================
require_once dirname(__DIR__) . '/includes/env-loader.php';

// ============================================================
//  РЕЖИМ И ОКРУЖЕНИЕ
// ============================================================
define('APP_NAME', 'GigaChef');
define('APP_DEBUG', (bool)(getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1'));
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_TIMEZONE', 'Europe/Moscow');

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// ============================================================
//  РЕЖИМ ОБСЛУЖИВАНИЯ (maintenance)
// ============================================================
define('MAINTENANCE_MODE', (bool)(getenv('MAINTENANCE_MODE') === 'true' || getenv('MAINTENANCE_MODE') === '1'));
define('MAINTENANCE_RETRY_AFTER', (int)(getenv('MAINTENANCE_RETRY_AFTER') ?: 3600));
define('MAINTENANCE_ALLOWED_IPS', array_filter(explode(',', getenv('MAINTENANCE_ALLOWED_IPS') ?: '')));
define('MAINTENANCE_MESSAGE', getenv('MAINTENANCE_MESSAGE') ?: 'Мы обновляем кухню и скоро вернёмся с новыми возможностями!');

// --- Плановые техработы (мягкое предупреждение баннером) ---
define('MAINTENANCE_SCHEDULED_AT', getenv('MAINTENANCE_SCHEDULED_AT') ?: null);
define('MAINTENANCE_NOTICE_MINUTES', (int)(getenv('MAINTENANCE_NOTICE_MINUTES') ?: 30));
define('MAINTENANCE_DURATION_MINUTES', (int)(getenv('MAINTENANCE_DURATION_MINUTES') ?: 15));


define('CAPTCHA_SECRET', '^d5sw)0^elB;27oL<*g\&hk!s7tv<<y5');
// ============================================================
//  БАЗА ДАННЫХ (из переменных окружения)
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: '188.127.239.143');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS') ?: '');

if (!DB_NAME || !DB_USER) {
    die("ERROR: Database credentials not configured.\n"
        . "Set DB_NAME, DB_USER, DB_PASS in .env file or environment variables.\n"
        . "DB_HOST={$_ENV['DB_HOST']}, DB_PORT={$_ENV['DB_PORT']}, "
        . "DB_NAME=" . (DB_NAME ?: 'NOT SET') . ", DB_USER=" . (DB_USER ?: 'NOT SET'));
}

// ============================================================
//  БАЗОВЫЙ URL / ПУТИ
// ============================================================
define('SITE_URL', getenv('SITE_URL') ?: 'https://s1647298.smrtp.ru');
define('BASE_PATH', getenv('BASE_PATH') ?: '');
define('BASE_URL', rtrim(SITE_URL, '/') . BASE_PATH);
define('ROOT_DIR', dirname(__DIR__));
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ============================================================
//  СЕССИИ / БЕЗОПАСНОСТЬ
// ============================================================
define('SESSION_NAME', 'cookai_sess');
define('CRON_SECRET', getenv('CRON_SECRET') ?: '');

if (!CRON_SECRET && APP_ENV === 'production') {
    error_log('WARNING: CRON_SECRET not set. Cron endpoints will be vulnerable!');
}

if (session_status() === PHP_SESSION_NONE) {
    $__sessDir = ROOT_DIR . '/logs/sessions';
    if (!is_dir($__sessDir)) @mkdir($__sessDir, 0755, true);
    if (is_dir($__sessDir) && is_writable($__sessDir)) {
        session_save_path($__sessDir);
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => BASE_PATH ?: '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on')
                      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
//  YANDEX AI (из переменных окружения)
// ============================================================
define('YANDEX_API_KEY', getenv('YANDEX_API_KEY') ?: '');
define('YANDEX_FOLDER_ID', getenv('YANDEX_FOLDER_ID') ?: '');
define('YANDEX_COMPLETION_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion');

// ============================================================
//  МОДЕЛИ AI (все через Yandex Cloud AI Studio)
// ============================================================
define('YANDEX_GPT_4_LITE_MODEL', 'yandexgpt-4-lite/latest');
define('QWEN_3_6_MODEL',          'qwen3.6-35b-a3b/latest');
define('DEEPSEEK_MODEL',          'deepseek-v4.1-flash/latest');

// Провайдер для текстовых AI-операций: deepseek | yandex
define('AI_TEXT_PROVIDER', 'yandex');

// Провайдер для vision (анализа изображений): yandex
define('AI_VISION_PROVIDER', 'yandex');

// Лимиты загрузки изображений
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/webp');

if ((!YANDEX_API_KEY || !YANDEX_FOLDER_ID) && APP_ENV === 'production') {
    error_log('WARNING: Yandex AI credentials not configured. Set YANDEX_API_KEY, YANDEX_FOLDER_ID.');
}

// --- AI rate-limit (дневные лимиты по фичам) ---
define('AI_LIMIT_GUEST', 2);
define('AI_LIMIT_PRO', 40);
define('AI_LIMITS', [
    'generate' => [AI_LIMIT_GUEST, AI_LIMIT_PRO],
    'calorie'  => [2, AI_LIMIT_PRO],
    'cookbook' => [1, 20],
    'holiday' => [3, 3],
]);

// ============================================================
//  ЕДИНИЦЫ ИЗМЕРЕНИЯ ИНГРЕДИЕНТОВ
// ============================================================
define('INGREDIENT_UNITS', [
    // Вес
    'г'      => ['label' => 'грамм',        'type' => 'weight',  'base_g' => 1],
    'кг'     => ['label' => 'килограмм',    'type' => 'weight',  'base_g' => 1000],
    // Объём (для воды/молока примерно 1:1, для масла — 0.92)
    'мл'     => ['label' => 'миллилитр',    'type' => 'volume',  'base_g' => 1],
    'л'      => ['label' => 'литр',         'type' => 'volume',  'base_g' => 1000],
    // Штуки
    'шт'     => ['label' => 'штука',        'type' => 'count',   'base_g' => null],
    // Кухонные
    'ч.л.'   => ['label' => 'чайная ложка', 'type' => 'spoon',   'base_g' => 5],
    'ст.л.'  => ['label' => 'столовая ложка','type' => 'spoon',  'base_g' => 15],
    'стакан' => ['label' => 'стакан',       'type' => 'volume',  'base_g' => 200],
    'щепотка'=> ['label' => 'щепотка',      'type' => 'pinch',   'base_g' => 1],
    'пучок'  => ['label' => 'пучок',        'type' => 'count',   'base_g' => 30],
    'зубчик' => ['label' => 'зубчик',       'type' => 'count',   'base_g' => 5],
    // Особый случай — «по вкусу»
    'по вкусу' => ['label' => 'по вкусу',   'type' => 'to_taste','base_g' => 0],
]);

// Значения по умолчанию для «по вкусу» (чтобы КБЖУ не падало в 0)
define('AI_TO_TASTE_DEFAULTS', [
    'соль'      => 0,
    'перец'     => 0,
    'специи'    => 0,
    'зелень'    => 5,
    'масло'     => 10,
    'соус'      => 15,
    'сах'       => 5,
    'сметана'   => 15,
    'майонез'   => 15,
]);

if (!function_exists('ingredient_units_for_js')) {
    function ingredient_units_for_js(): array {
        $out = [];
        foreach (INGREDIENT_UNITS as $slug => $meta) {
            $out[] = [
                'value' => $slug,
                'label' => $meta['label'],
                'type'  => $meta['type'],
            ];
        }
        return $out;
    }
}

// ============================================================
//  2GIS API (из переменных окружения)
// ============================================================
define('GIS_API_KEY', getenv('GIS_API_KEY') ?: '');
define('GIS_CATALOG_API_URL', 'https://catalog.api.2gis.com/3.0/items');
define('GIS_MAPGL_JS_URL', 'https://api.mapgl.2gis.com/api/js/v1');

// Если нужны раздельные ключи для разных сервисов
define('GIS_CATALOG_KEY', getenv('GIS_CATALOG_KEY') ?: GIS_API_KEY);
define('GIS_MAPGL_KEY', getenv('GIS_MAPGL_KEY') ?: GIS_API_KEY);

if (!GIS_API_KEY && APP_ENV === 'production') {
    error_log('WARNING: 2GIS API key not configured. Set GIS_API_KEY.');
}

// ============================================================
//  VK API
// ============================================================
define('VK_COMMUNITY_TOKEN', getenv('VK_COMMUNITY_TOKEN') ?: '');
define('VK_GROUP_ID', getenv('VK_GROUP_ID') ?: '');

if (APP_ENV === 'production' && (!VK_COMMUNITY_TOKEN || !VK_GROUP_ID)) {
    error_log('WARNING: VK API credentials not configured. Video upload to VK will fail.');
}

// ============================================================
//  ПЛАТЕЖИ (ЮKassa) (из переменных окружения)
// ============================================================
define('YOOKASSA_SHOP_ID', getenv('YOOKASSA_SHOP_ID') ?: '');
define('YOOKASSA_SECRET_KEY', getenv('YOOKASSA_SECRET_KEY') ?: '');
define('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3/payments');
define('YOOKASSA_REFUND_URL', 'https://api.yookassa.ru/v3/refunds');
define('PAYMENT_LIVE_MODE', (bool)(getenv('PAYMENT_LIVE_MODE') === 'true' || getenv('PAYMENT_LIVE_MODE') === '1'));

define('PLAN_MONTHLY_PRICE', (float)(getenv('PLAN_MONTHLY_PRICE') ?: 299.00));
define('PLAN_YEARLY_PRICE', (float)(getenv('PLAN_YEARLY_PRICE') ?: 2990.00));

define('MAX_RENEW_ATTEMPTS', 3);
define('RENEW_NOTIFY_DAYS', 3);
define('REFUND_WINDOW_DAYS', 14);

if (PAYMENT_LIVE_MODE && (!YOOKASSA_SHOP_ID || !YOOKASSA_SECRET_KEY)) {
    error_log('ERROR: Payment live mode enabled but credentials not set!');
}

// ============================================================
//  ADMIN EMAILS (из переменных окружения)
// ============================================================
$__admin_emails = getenv('ADMIN_EMAILS') ?: '';
define('ADMIN_EMAILS', array_map('trim', array_filter(explode(',', $__admin_emails))));

// GamePush API
define('GAMEPUSH_API_KEY', getenv('GAMEPUSH_API_KEY') ?: '');
define('GAMEPUSH_PUBLIC_KEY', getenv('GAMEPUSH_PUBLIC_KEY') ?: '');

// ============================================================
//  ПОЧТА
// ============================================================
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'no-reply@cookai.ru');
define('MAIL_FROM_NAME', 'GigaChefAI');

// ============================================================
//  КЭШИРОВАНИЕ
// ============================================================
define('CACHE_ENABLED', (bool)(getenv('CACHE_ENABLED') === 'true' || getenv('CACHE_ENABLED') === '1'));
define('CACHE_TTL_RESTAURANTS', (int)(getenv('CACHE_TTL_RESTAURANTS') ?: 600)); // 10 минут
define('CACHE_DIR', ROOT_DIR . '/cache');

// Создаем директорию для кэша, если её нет
if (CACHE_ENABLED && !is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

// ============================================================
//  АВТОПОДКЛЮЧЕНИЕ СЛОЁВ
// ============================================================
require_once ROOT_DIR . '/includes/db.php';
require_once ROOT_DIR . '/includes/functions.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once __DIR__ . '/../includes/captcha.php';
require_once ROOT_DIR . '/includes/activity.php';
require_once ROOT_DIR . '/includes/achievements.php';
require_once ROOT_DIR . '/includes/cache.php';
require_once ROOT_DIR . '/includes/remember.php';
require_once ROOT_DIR . '/includes/yandex.php';
require_once __DIR__ . '/../includes/ad_manager.php';

// ============================================================
//  СОЗДАНИЕ ТАБЛИЦ (если не существуют)
// ============================================================

// Создаем таблицы для челленджей
try {
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_participants` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challenge_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `emoji` varchar(10) DEFAULT NULL,
        `photo_url` varchar(500) DEFAULT NULL,
        `video_url` varchar(500) DEFAULT NULL,
        `status` varchar(20) DEFAULT 'active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `challenge_user` (`challenge_id`, `user_email`),
        INDEX `idx_challenge` (`challenge_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_submissions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challenge_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `emoji` varchar(10) DEFAULT NULL,
        `photo_url` varchar(500) DEFAULT NULL,
        `video_url` varchar(500) DEFAULT NULL,
        `comment` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_challenge` (`challenge_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating challenge tables: ' . $e->getMessage());
}

// Создаем таблицы для PNG-смайлов
try {
    // Таблица наборов PNG-смайлов
    db_exec("CREATE TABLE IF NOT EXISTS `emoji_packs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `slug` varchar(255) NOT NULL UNIQUE,
        `description` text DEFAULT NULL,
        `sort_order` int(11) DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_sort_order` (`sort_order`),
        INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Таблица PNG-смайлов
    db_exec("CREATE TABLE IF NOT EXISTS `emoji_images` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pack_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(100) NOT NULL,
        `image_url` varchar(500) NOT NULL,
        `sort_order` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_pack_id` (`pack_id`),
        INDEX `idx_sort_order` (`sort_order`),
        FOREIGN KEY (`pack_id`) REFERENCES `emoji_packs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating emoji tables: ' . $e->getMessage());
}

// ============================================================
//  ТАБЛИЦА СОГЛАСИЙ НА COOKIE
// ============================================================
try {
    db_exec("CREATE TABLE IF NOT EXISTS `cookie_consents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_email` varchar(255) DEFAULT NULL,
        `session_id` varchar(128) DEFAULT NULL,
        `ip_address` varchar(45) NOT NULL,
        `user_agent` varchar(500) DEFAULT NULL,
        `policy_version` varchar(20) NOT NULL,
        `accepted` tinyint(1) NOT NULL DEFAULT 1,
        `accepted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_user_email` (`user_email`),
        INDEX `idx_accepted_at` (`accepted_at`),
        INDEX `idx_policy_version` (`policy_version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating cookie_consents table: ' . $e->getMessage());
}

// Версия политики cookie (меняйте при обновлении текста политики)
if (!defined('COOKIE_POLICY_VERSION')) {
    define('COOKIE_POLICY_VERSION', '1.0');
}

// Добавляем колонку для изображений в чат, если её нет
try {
    $chatColumns = db_all("SHOW COLUMNS FROM chat_messages");
    $chatColumnNames = array_column($chatColumns, 'Field');
    
    if (!in_array('image_url', $chatColumnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN image_url varchar(500) DEFAULT NULL AFTER sticker_url");
    }
} catch (Throwable $e) {
    error_log('Error adding image_url column to chat_messages: ' . $e->getMessage());
}



// ============================================================
//  ФУНКЦИИ МЕТА-ТЕГОВ (доступны везде)
// ============================================================
if (!function_exists('get_page_meta')) {
    /**
     * Получение мета-тегов страницы из базы данных
     */
    function get_page_meta(string $pagePath): ?array {
        try {
            $meta = db_one("SELECT * FROM page_meta WHERE page_path = ?", [$pagePath]);
            return $meta ?: null;
        } catch (Throwable $e) {
            error_log('get_page_meta error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('apply_meta')) {
    /**
     * Применение мета-тегов к глобальным переменным
     */
    function apply_meta(?array $meta): void {
        if (!$meta) {
            return;
        }
        
        if (!empty($meta['meta_title'])) {
            $GLOBALS['pageTitle'] = $meta['meta_title'];
        }
        if (!empty($meta['meta_description'])) {
            $GLOBALS['pageDescription'] = $meta['meta_description'];
        }
        if (!empty($meta['meta_keywords'])) {
            $GLOBALS['pageKeywords'] = $meta['meta_keywords'];
        }
        if (!empty($meta['h1'])) {
            $GLOBALS['pageH1'] = $meta['h1'];
        }
    }
}

// ============================================================
//  ФУНКЦИЯ ПРОВЕРКИ АДМИНИСТРАТОРА
// ============================================================
if (!function_exists('is_admin')) {
    /**
     * Проверка, является ли текущий пользователь администратором
     */
    function is_admin(): bool {
        $user = current_user();
        if (!$user) {
            return false;
        }
        
        // Проверяем по email
        if (in_array($user['email'], ADMIN_EMAILS, true)) {
            return true;
        }
        
        // Проверяем по роли
        if (isset($user['role']) && $user['role'] === 'admin') {
            return true;
        }
        
        // Проверяем по флагу
        if (isset($user['is_admin']) && (int)$user['is_admin'] === 1) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('require_admin')) {
    /**
     * Требует права администратора, иначе показывает 403
     */
    function require_admin(): void {
        if (!is_logged_in()) {
            header('Location: /login');
            exit;
        }
        
        if (!is_admin()) {
            http_response_code(403);
            require ROOT_DIR . '/pages/403.php';
            exit;
        }
    }
}

// ============================================================
//  ФУНКЦИЯ ДЛЯ РАБОТЫ С JSON INPUT
// ============================================================
if (!function_exists('json_input')) {
    /**
     * Получение JSON данных из тела запроса
     */
    function json_input(): array {
        static $input = null;
        
        if ($input !== null) {
            return $input;
        }
        
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        
        if (!is_array($data)) {
            $data = [];
        }
        
        $input = $data;
        return $input;
    }
}

// ============================================================
//  ГЛОБАЛЬНЫЙ ОБРАБОТЧИК ОШИБОК → страница 500
// ============================================================
function cookai_render_500(?string $message = null): void
{
    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
          || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($isApi) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok' => false,
            'error' => (APP_DEBUG && $message) ? $message : 'Внутренняя ошибка сервера',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (APP_DEBUG && $message) {
        $GLOBALS['__error_message'] = $message;
    }
    require ROOT_DIR . '/pages/500.php';
    exit;
}

set_exception_handler(function (Throwable $e): void {
    error_log('[GigaChefAI 500] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    cookai_render_500($e->getMessage());
});

// ============================================================
//  AI: КЛАССИФИКАЦИЯ РЕЦЕПТОВ (категории + теги)
// ============================================================
define('AI_CLASSIFY_TEMPERATURE', 0.1);
define('AI_CLASSIFY_MAX_TOKENS', 4000);
define('AI_RECIPE_GENERATE_MAX_TOKENS', 4000);
define('AI_CLASSIFY_BATCH_SLEEP', 1);

define('AI_RECIPE_CLASSIFY_PROMPT',
    'Ты — API-парсер кулинарных рецептов. Твоя задача — вернуть СТРОГО ВАЛИДНЫЙ JSON без пояснений.' . "\n\n" .

    'ФОРМАТ ОТВЕТА:' . "\n" .
    '{' . "\n" .
    '  "category_slug": "...",' . "\n" .
    '  "tags": ["...", "..."]' . "\n" .
    '}' . "\n\n" .

    'ОСНОВНАЯ КАТЕГОРИЯ (ровно ОДНА из списка):' . "\n" .
    'supy-i-bulony, zakuski-i-kanape, salaty-i-holodnye, goryachie-osnovnye, ' .
    'garniry, vypechka-i-hleb, deserty, zavtraki-i-branchi, uzhiny, obedy, ' .
    'fastfud-i-stritfud, gril-i-barbekyu, duhovka-i-zapecanki, na-paru, ' .
    'vo-frityure, konservaciya, sousy-dipy-zapravki, zagotovki-polufabrikaty, ' .
    'napitki-bezalkogolnye, alkogolnye-napitki, koktejli-alkogolnye, ' .
    'koktejli-bezalkogolnye, detskoe-menyu' . "\n\n" .

    'ТЕГИ (все, что подходят под состав):' . "\n" .
    'cuisine (1-2): russkaya, italyanskaya, yaponskaya, kitajskaya, frantsuzskaya, ' .
    'tureckaya, gruzinskaya, indijskaya, meksikanskaya, amerikanskaya, korejskaya, ' .
    'tajskaya, vetnamskaya, grecheskaya, ispanskaya, livanskaya-blizhnevostochnaya, ' .
    'uzbekskaya, azerbajdzhanskaya, armyanskaya, kavkazskaya, skandinavskaya, afrikanskaya, ' .
    'evropejskaya, sredizemnomorskaya' . "\n" .

    'time (РОВНО 1): do-15-minut, do-30-minut, do-60-minut, bolee-chasa' . "\n" .
    'difficulty (РОВНО 1): dlya-novichkov, srednij-uroven, dlya-profi' . "\n" .
    'season (РОВНО 1): letnie, zimnie, vesennie, osennie, kruglogodichnye' . "\n" .

    'diet: bezglyutenovaya (нет пшеницы/макарон), bezlaktoznaya (нет молочки), veganskaya, ' .
    'vegetarianskaya, ketogennaya, paleodieta, nizkouglevodnaya, nizkokalorijnaya, vysokobelkovaya, ' .
    'fodmap, diabet-nizkij-gi, gipertoniya-nizkaya-sol, zhkt-shhadyashhee, sportivnoe-pitanie, ' .
    'detskoe-1-plus, detskoe-3-plus, detskoe-6-plus, lechebnoe-pitanie' . "\n" .

    'holiday: novyj-god (оливье, сельдь под шубой, мандарины, шампанское), ' .
    'rozhdestvo (запеченная индейка, гусь, рождественский пудинг, кутья, сочиво), ' .
    'pasxa (кулич, пасха, творожная пасха), maslenica (блины), ' .
    '8-marta, 23-fevralya, den-rozhdeniya (торты), svadba, yubilej, helloween (тыква), ' .
    'den-blagodareniya (индейка), tatyanin-den, ivan-kupala, den-pobedy, korporativ, ' .
    'detskij-prazdnik, piknik (шашлык), romanticheskij-uzhin, post' . "\n" .

    'restriction: bez-orexov, bez-soi, bez-yaic, bez-moreproduktov, bez-ryby, ' .
    'bez-krasnogo-myasa, bez-svininy, bez-govyadiny, bez-kuricy, bez-molochnyx-produktov' . "\n\n" .
    
    'ПРАВИЛА ВЫБОРА КУХНИ:' . "\n" .
    '1. В первую очередь ставь КОНКРЕТНУЮ национальную кухню (русская для борща/оливье, итальянская для пасты/пиццы, японская для суши).' . "\n" .
    '2. evropejskaya ставь ТОЛЬКО для современных блюд без явной национальной привязки (например, "Куриная грудка с овощами", "Смузи-боул").' . "\n" .
    '3. sredizemnomorskaya ставь ТОЛЬКО для блюд на основе оливкового масла, авокадо, томатов, оливок, морепродуктов (греческий салат, хумус).' . "\n" .
    '4. Если блюдо — салат с авокадо, овощами, но без четкой национальной привязки — ставь sredizemnomorskaya (НЕ evropejskaya).' . "\n" .
    '5. Если блюдо не подходит ни под одну кухню (например, странный фьюжн) — НЕ СТАВЬ ТЕГ КУХНИ ВООБЩЕ.' . "\n\n" .

    'ИНСТРУКЦИЯ ПО ВЫПОЛНЕНИЮ:' . "\n" .
    '1. Смотри на ингредиенты и шаги. Если в шагах есть "залить квасом/кефиром/бульоном" или "варить" — это суп (supy-i-bulony), даже если в составе много овощей.' . "\n" .
    '2. Сезонность определяй по температуре подачи: если блюдо холодное (окрошка, гаспачо) — letnie. Если горячее и наваристое (щи, борщ) — zimnie.' . "\n" .
    '3. Выбери 1 time, 1 difficulty, 1 season, 1-2 cuisine. Они ОБЯЗАТЕЛЬНЫ.' . "\n" .
    '4. Проверь состав на аллергены. Если блюдо без глютена — добавь bezglyutenovaya. Если без курицы — bez-kuricy.' . "\n" .
    '5. Праздничные теги (novyj-god, rozhdestvo) ставь ТОЛЬКО если в составе есть явные атрибуты (оливье, мандарины, индейка, кулич). Не ставь novyj-god для обычных щей!' . "\n" .
    '6. Добавь остальные подходящие теги (диеты).' . "\n" .
    '7. ВАЖНО: Если блюдо содержит индейку или гуся и запекается целиком — это rozhdestvo (Рождество), а НЕ novyj-god.' . "\n" .
    '8. Ответ — ТОЛЬКО валидный JSON.'
);
// ============================================================
//  AI: Справка по категориям и тегам для генерации рецептов
//  Подмешивается к промптам ai_generate, ai_fusion, ai_cookbook, ai_variator
// ============================================================
define('AI_TAXONOMY_APPENDIX',
    "\n\n=== ДОПОЛНИТЕЛЬНО в JSON верни два поля ===\n\n" .
    "category_slug — ровно ОДНА строка из списка категорий:\n" .
    "supy-i-bulony, zakuski-i-kanape, salaty-i-holodnye, goryachie-osnovnye, " .
    "garniry, vypechka-i-hleb, deserty, zavtraki-i-branchi, uzhiny, obedy, " .
    "fastfud-i-stritfud, gril-i-barbekyu, duhovka-i-zapecanki, na-paru, " .
    "vo-frityure, konservaciya, sousy-dipy-zapravki, zagotovki-polufabrikaty, " .
    "napitki-bezalkogolnye, alkogolnye-napitki, koktejli-alkogolnye, " .
    "koktejli-bezalkogolnye, detskoe-menyu\n\n" .

    "tag_slugs — массив СТРОК (не объектов!), 3-7 slug-ов из списков:\n" .
    "cuisine (1-2): russkaya, italyanskaya, yaponskaya, kitajskaya, frantsuzskaya, evropejskaya, " .
    "tureckaya, gruzinskaya, indijskaya, meksikanskaya, amerikanskaya, korejskaya, sredizemnomorskaya, " .
    "tajskaya, vetnamskaya, grecheskaya, ispanskaya, livanskaya-blizhnevostochnaya, " .
    "uzbekskaya, azerbajdzhanskaya, armyanskaya, kavkazskaya, skandinavskaya, afrikanskaya\n" .
    "time (1): do-15-minut, do-30-minut, do-60-minut, bolee-chasa\n" .
    "difficulty (1): dlya-novichkov, srednij-uroven, dlya-profi\n" .
    "season (если явно): letnie, zimnie, vesennie, osennie, kruglogodichnye\n" .
    "diet (если явно): bezglyutenovaya, bezlaktoznaya, veganskaya, vegetarianskaya, " .
    "ketogennaya, paleodieta, nizkouglevodnaya, nizkokalorijnaya, vysokobelkovaya, " .
    "fodmap, diabet-nizkij-gi, gipertoniya-nizkaya-sol, zhkt-shhadyashhee, " .
    "sportivnoe-pitanie, detskoe-1-plus, detskoe-3-plus, detskoe-6-plus, lechebnoe-pitanie\n" .
    "holiday (если явно): novyj-god, rozhdestvo, pasxa, maslenica, 8-marta, " .
    "23-fevralya, den-rozhdeniya, svadba, yubilej, helloween, den-blagodareniya, " .
    "tatyanin-den, ivan-kupala, den-pobedy, korporativ, detskij-prazdnik, piknik, " .
    "romanticheskij-uzhin, post\n" .
    "restriction (ТОЛЬКО если ингредиента НЕТ): bez-orexov, bez-soi, bez-yaic, " .
    "bez-moreproduktov, bez-ryby, bez-krasnogo-myasa, bez-svininy, bez-govyadiny, " .
    "bez-kuricy, bez-molochnyx-produktov\n\n" .

    "КРИТИЧНО:\n" .
    "1. Теги-ограничения bez-* ставь ТОЛЬКО если ингредиента НЕТ.\n" .
    "   Блюдо с курицей → БЕЗ тега bez-kuricy.\n" .
    "   Блюдо с говядиной → БЕЗ тега bez-govyadiny.\n" .
    "2. Если название содержит суп/окрошку/щи/борщ/похлёбку/уху/солянку/шурпу/шулюм — " .
    "категория ОБЯЗАТЕЛЬНО supy-i-bulony.\n" .
    "3. gril-i-barbekyu — только открытый огонь. Запечённое в духовке — duhovka-i-zapecanki " .
    "или goryachie-osnovnye.\n" .
    "4. Ответ — ТОЛЬКО валидный JSON, без markdown и текста вокруг.\n" .
        "\n\n=== ПРИМЕРЫ категорий для типичных блюд ===\n" .
    "ПАСТА, ПИЦЦА, КАРБОНАРА, ЛАЗАНЬЯ, РИЗОТТО, СТЕЙК, ПЛОВ, ЖАРКОЕ → goryachie-osnovnye\n" .
    "БОРЩ, ЩИ, ОКРОШКА, СУП, УХА, СОЛЯНКА, ШУЛЮМ, ШУРПА, БУЛЬОН → supy-i-bulony\n" .
    "ОЛИВЬЕ, ВИНЕГРЕТ, ЦЕЗАРЬ, СВЕЖИЙ САЛАТ, ВИТАМИННЫЙ → salaty-i-holodnye\n" .
    "ШАШЛЫК, БАРБЕКЮ, СТЕЙК НА ГРИЛЕ, ОВОЩИ ГРИЛЬ → gril-i-barbekyu\n" .
    "ЗАПЕКАНКА, ГРАТЕН, БЛЮДО В ГОРШОЧКЕ, УТКА В ДУХОВКЕ → duhovka-i-zapecanki\n" .
    "БУРГЕР, ШАУРМА, ХОТ-ДОГ, КАРТОФЕЛЬ ФРИ → fastfud-i-stritfud\n" .
    "ПИРОГ, ХЛЕБ, БУЛОЧКА, КРУАССАН, БЛИНЫ, ОЛАДЬИ → vypechka-i-hleb\n" .
    "ТОРТ, ПИРОЖНОЕ, МОРОЖЕНОЕ, ПЕЧЕНЬЕ → deserty\n" .
    "ОМЛЕТ, КАША, ЯИЧНИЦА, СЫРНИКИ, ТОСТ → zavtraki-i-branchi\n" .
    "СОУС, МАЙОНЕЗ, КЕТЧУП, ПЕСТО, ДИП → sousy-dipy-zapravki\n" .
    "КОКТЕЙЛЬ С АЛКОГОЛЕМ → koktejli-alkogolnye\n" .
    "КОМПОТ, СМУЗИ, ЛИМОНАД, МОРС, ЧАЙ → napitki-bezalkogolnye\n" .
    "ВИНО, НАСТОЙКА, ЛИКЁР, ПИВО → alkogolnye-napitki\n" .
    "СУШИ, РОЛЛ, САШИМИ → zakuski-i-kanape\n" .
    "ГАРНИР, РИС, ГРЕЧКА, КАРТОФЕЛЬНОЕ ПЮРЕ → garniry\n" .
    "КОНСЕРВАЦИЯ, СОЛЕНЬЯ, МАРИНАД, ВАРЕНЬЕ → konservaciya"
);

// Хелперы для иконок
require_once ROOT_DIR . '/includes/icons.php';

// ============================================================
//  ГЕЙТ РЕЖИМА ОБСЛУЖИВАНИЯ (после обработчиков ошибок)
// ============================================================
require_once ROOT_DIR . '/includes/maintenance.php';
cookai_maintenance_gate();

// ============================================================
//  OAuth / Восстановление пароля
// ============================================================
require_once ROOT_DIR . '/includes/oauth.php';
require_once ROOT_DIR . '/includes/password_reset.php';