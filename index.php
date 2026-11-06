<?php
/**
 * GigaChef — Точка входа (router)
 * 
 * Обрабатывает все входящие запросы и маршрутизирует их к нужным страницам.
 * Включает интеграцию с системой фьюжн-рецептов.
 */
require_once __DIR__.'/config/config.php';

// ============================================================
//  ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================

/**
 * Транслитерация кириллицы в латиницу
 */
function transliterate($string) {
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
    ];
    return strtr($string, $translit);
}

/**
 * Генерация URL-slug из произвольной строки
 */
function generateSlug($string) {
    $string = transliterate($string);
    $string = mb_strtolower($string);
    $string = str_replace(
        [' ','_','/','\\',',','.','!','?','(',')','[',']','{','}','+','=','&','#','@','$','%','^','*',':',';','"',"'",'<','>','|','~','`'],
        '-',
        $string
    );
    $string = preg_replace('/[^a-z0-9\-]/u', '', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Загрузка мета-тегов для статической страницы из базы данных
 */
function load_page_meta($pagePath) {
    try {
        $meta = db_one("SELECT * FROM page_meta WHERE page_path = ?", [$pagePath]);
        if ($meta) {
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
    } catch (Throwable $e) {
        error_log('Error loading page meta: ' . $e->getMessage());
    }
}

// ============================================================
//  РАЗБОР URL
// ============================================================

$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(BASE_PATH, '/');
if ($basePath && strpos($request, $basePath) === 0) {
    $request = trim(substr($request, strlen($basePath)), '/');
}

$parts = explode('/', $request);
$first = $parts[0] ?? '';
$second = $parts[1] ?? '';

// ============================================================
//  ПУБЛИЧНЫЕ СТРАНИЦЫ (доступны без авторизации)
// ============================================================

$publicPages = [
    '', 'login', 'register', 'terms', 'privacy', 'legal', 'about',
    'forgot-password', 'reset-password', 'news', 'download_backup.php', 'payment',
    'fusion', 'shared', 'api',
];

// Если гость и запрашивает НЕ публичную страницу — показываем лендинг
if (!is_logged_in() && !in_array($first, $publicPages)) {
    require __DIR__.'/pages/login.php';
    exit;
}

// ============================================================
//  ГЕЙТ: залогинен, но профиль не заполнен → только /register/step2
// ============================================================
if (is_logged_in()) {
    $__u = current_user();

    // Профиль не заполнен (city или gender пустые)?
    $__profileEmpty = empty($__u['city']) || empty($__u['gender']);

    // Пути, куда МОЖНО ходить с незаполненным профилем
    $__allowedWhenEmpty = [
        'register',   // шаг 1 и шаг 2 регистрации
        'logout',     // выход
        'payment',    // оплата Pro (если выбрали Pro-план)
        'terms', 'privacy', 'legal', 'about',  // правовые
    ];

    if ($__profileEmpty && !in_array($first, $__allowedWhenEmpty, true)) {
        header('Location: ' . url('register/step2'));
        exit;
    }

    // Если профиль заполнен, а он лезет на /register/step2 — на главную
    if (!$__profileEmpty && $first === 'register' && $second === 'step2') {
        header('Location: ' . url(''));
        exit;
    }
}

// Скачивание бэкапа
if ($first === 'download_backup.php' || $first === 'download_backup') {
    require __DIR__.'/download_backup.php';
    exit;
}

// ============================================================
//  ЗАГРУЗКА МЕТА-ТЕГОВ ДЛЯ СТАТИЧЕСКИХ СТРАНИЦ
// ============================================================

$staticPagesWithMeta = [
    // Сообщество
    'friends'         => '/friends',
    'stories'         => '/stories',
    'communities'     => '/communities',
    'challenges'      => '/challenges',
    'leaderboard'     => '/leaderboard',
    'chats'           => '/chats',
    'feed'            => '/',
    
    // AI-инструменты
    'ai-generator'    => '/ai-generator',
    'what-to-cook'    => '/what-to-cook',
    'advisor'         => '/advisor',
    'calorie-scanner' => '/calorie-scanner',
    'scanner'         => '/calorie-scanner',
    'beer-pairing'    => '/beer-pairing',
    'dietolog'        => '/dietolog',
    'ai-cookbook'     => '/ai-cookbook',
    
    // === ФЬЮЖН (новое) ===
    'fusion'          => '/fusion',
    'fusion-lab'      => '/fusion',  // алиас
    'shared'          => '/shared',  // просмотр публичных фьюжн-рецептов
    
    // Контент
    'videos'          => '/videos',
    'achievements'    => '/achievements',
    'games'           => '/games',
    
    // Личное
    'favorites'       => '/favorites',
    'my-cookbooks'    => '/my-cookbooks',
    'notifications'   => '/notifications',
    'search'          => '/search',
    'create-recipe'   => '/create-recipe',
    'inspiration'     => '/inspiration',
    'billing'         => '/billing',
    'quit-smoking'    => '/quit-smoking',
    'my-holidays' => '/my-holidays'
];

if (isset($staticPagesWithMeta[$first])) {
    load_page_meta($staticPagesWithMeta[$first]);
}

// Для главной страницы
if ($first === '' || $first === 'home' || $first === 'feed') {
    load_page_meta('/');
}

// ============================================================
//  МАРШРУТИЗАЦИЯ
// ============================================================

switch ($first) {
    
    // ---------------------------------------------------------
    //  ФЬЮЖН-ЛАБОРАТОРИЯ (новое)
    // ---------------------------------------------------------
    
    /**
     * /fusion                    — главная страница фьюжн-лаборатории
     * /fusion?id=123             — просмотр конкретного рецепта
     * /fusion/page/2             — пагинация
     */
    case 'fusion':
    case 'fusion-lab':
        load_page_meta('/fusion');
        
        // Если запрошен конкретный рецепт
        if (!empty($_GET['id'])) {
            $_GET['fusion_id'] = (int)$_GET['id'];
        }
        
        // Если указан номер страницы
        if (isset($parts[1]) && ctype_digit($parts[1])) {
            $_GET['page'] = (int)$parts[1];
        }
        
        require __DIR__.'/pages/fusion.php';
        exit;
    
    /**
     * /shared/<token>            — публичный просмотр фьюжн-рецепта по токену
     */
    case 'shared':
        if (isset($parts[1]) && !empty($parts[1])) {
            $_GET['token'] = preg_replace('/[^a-f0-9]/i', '', $parts[1]);
            require __DIR__.'/pages/fusion_shared.php';
            exit;
        }
        // Если токена нет — редирект на фьюжн-страницу
        header('Location: ' . url('fusion'));
        exit;
    
    /**
     * /my-fusion                 — личные фьюжн-рецепты пользователя
     */
    case 'my-fusion':
        if (!is_logged_in()) {
            header('Location: ' . url('login'));
            exit;
        }
        $_GET['my'] = 1;
        require __DIR__.'/pages/fusion.php';
        exit;
    
    /**
     * /fusion-cookbook           — кулинарная книга с фьюжн-рецептами
     */
    case 'fusion-cookbook':
        if (!is_logged_in()) {
            header('Location: ' . url('login'));
            exit;
        }
        load_page_meta('/my-cookbooks');
        $_GET['filter'] = 'fusion';
        require __DIR__.'/pages/my_cookbooks.php';
        exit;
    
    // ---------------------------------------------------------
    //  ДИЕТОЛОГ
    // ---------------------------------------------------------
    
    case 'dietolog':
        load_page_meta('/dietolog');
        require __DIR__.'/pages/dietolog.php';
        exit;

    // ---------------------------------------------------------
    //  АДМИНКА
    // ---------------------------------------------------------
    
    case 'admin':
        switch ($second) {
            case '':
            case 'panel':
                require __DIR__.'/pages/admin_panel.php';
                exit;
            case 'icon-packs':
                require __DIR__.'/pages/admin_icon-packs.php';
                exit;
            case 'recipes':
                require __DIR__.'/pages/admin_recipes.php';
                exit;
            case 'cdn':
                require __DIR__.'/pages/admin_cdn.php';
                exit;
            case 'news':
                require __DIR__.'/pages/admin_news.php';
                exit;
            case 'games':
                require __DIR__.'/pages/admin_games.php';
                exit;
            case 'maintenance':
                require __DIR__.'/pages/admin_maintenance.php';
                exit;
            case 'users':
                require __DIR__.'/pages/admin_users.php';
                exit;
            case 'comments':
                require __DIR__.'/pages/admin_comments.php';
                exit;
            case 'reviews':
                require __DIR__ . '/pages/admin_reviews.php';
                exit;
            case 'communities':
                require __DIR__.'/pages/admin_communities.php';
                exit;
            case 'challenges':
                require __DIR__.'/pages/admin_challenges.php';
                exit;
            case 'backup':
                require __DIR__.'/pages/admin_backup.php';
                exit;
            case 'meta':
                require __DIR__.'/pages/admin_meta.php';
                exit;
            case 'stickers':
                require __DIR__.'/pages/admin_stickers.php';
                exit;
            case 'emoji':
                require __DIR__.'/pages/admin_emoji.php';
                exit;
            case 'messages':
                require __DIR__ . '/pages/admin_messages.php';
                exit;
            case 'ads':
                require __DIR__ . '/pages/admin_ads.php';
                exit;
            // === Фьюжн: админка ===
            case 'fusion':
                require __DIR__.'/pages/admin_fusion.php';
                exit;
            default:
                http_response_code(404);
                require __DIR__.'/pages/404.php';
                exit;
        }
        break;

    // ---------------------------------------------------------
    //  НОВОСТИ
    // ---------------------------------------------------------
    
    case 'news':
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $_GET['id'] = (int)$parts[1];
            require __DIR__.'/pages/news_detail.php';
        } else {
            require __DIR__.'/pages/news.php';
        }
        exit;
        
    

        // ---------------------------------------------------------
    //  Метод Переключателя
    // ---------------------------------------------------------
    case 'metod-perekluchatelya':
        // Статьи: /metod-perekluchatelya/statya-N
        if ($second !== '' && preg_match('/^statya-(\d)$/', $second, $m)) {
            $statyaFile = __DIR__ . '/pages/metod-perekluchatelya/statya-' . $m[1] . '.php';
            if (file_exists($statyaFile)) {
                require $statyaFile;
                exit;
            }
        }
        // Лендинг метода
        require __DIR__ . '/pages/metod-perekluchatelya.php';
        exit;
    

    // ---------------------------------------------------------
    //  СТРАНИЦЫ ОШИБОК
    // ---------------------------------------------------------
    
    case '403':
        http_response_code(403);
        require __DIR__.'/pages/403.php';
        exit;
        
    case '404':
        http_response_code(404);
        require __DIR__.'/pages/404.php';
        exit;

    // ---------------------------------------------------------
    //  КАТЕГОРИИ
    // ---------------------------------------------------------
    
    case 'category':
        if (isset($parts[1]) && !empty($parts[1])) {
            $_GET['slug'] = urldecode($parts[1]);
            if (preg_match('/[а-яА-ЯёЁ]/u', $_GET['slug'])) {
                $_GET['slug'] = generateSlug($_GET['slug']);
            }
            require __DIR__.'/pages/category.php';
            exit;
        }
        break;
    case 'tag':
    if (isset($parts[1]) && !empty($parts[1])) {
        $_GET['slug'] = urldecode($parts[1]);
        if (preg_match('/[а-яА-ЯёЁ]/u', $_GET['slug'])) {
            $_GET['slug'] = generateSlug($_GET['slug']);
        }
        require __DIR__ . '/pages/tag.php';
        exit;                                    // ← ОБЯЗАТЕЛЬНО
    }
    break;                                       // ← если slug пустой

    // ---------------------------------------------------------
    //  РЕЦЕПТЫ
    // ---------------------------------------------------------
    
    case 'recipe':
        if (isset($parts[1]) && !empty($parts[1])) {
            if (is_numeric($parts[1])) {
                $_GET['id'] = (int)$parts[1];
            } else {
                $_GET['slug'] = urldecode($parts[1]);
                if (preg_match('/[а-яА-ЯёЁ]/u', $_GET['slug'])) {
                    $_GET['slug'] = generateSlug($_GET['slug']);
                }
            }
            require __DIR__.'/pages/recipe_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  СООБЩЕСТВА
    // ---------------------------------------------------------
    
    case 'community':
        if (isset($parts[1]) && !empty($parts[1])) {
            $_GET['slug'] = urldecode($parts[1]);
            if (preg_match('/[а-яА-ЯёЁ]/u', $_GET['slug'])) {
                $_GET['slug'] = generateSlug($_GET['slug']);
            }
            require __DIR__.'/pages/community_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  ЧЕЛЛЕНДЖИ
    // ---------------------------------------------------------
    
    case 'challenge':
        if (isset($parts[1]) && !empty($parts[1])) {
            $challengeSlug = urldecode($parts[1]);
            $challenge = db_one('SELECT id FROM challenges WHERE slug = ?', [$challengeSlug]);
            if ($challenge) {
                $_GET['id'] = (int)$challenge['id'];
            } elseif (is_numeric($challengeSlug)) {
                $_GET['id'] = (int)$challengeSlug;
            }
            require __DIR__.'/pages/challenge_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  ПРОФИЛЬ И ПОЛЬЗОВАТЕЛИ
    // ---------------------------------------------------------
    
    case 'user':
        if (isset($parts[1]) && !empty($parts[1])) {
            $requestedUser = urldecode($parts[1]);
            $currentUserEmail = current_user()['email'];
            if ($requestedUser === $currentUserEmail) {
                require __DIR__.'/pages/profile.php';
                exit;
            }
            $_GET['user'] = $requestedUser;
            require __DIR__.'/pages/home.php';
            exit;
        }
        require __DIR__.'/pages/profile.php';
        exit;

    case 'profile':
        require __DIR__.'/pages/profile.php';
        exit;

    // ---------------------------------------------------------
    //  ЧАТЫ
    // ---------------------------------------------------------
    
    case 'chat':
        if (isset($parts[1]) && $parts[1] === 'group' && isset($parts[2]) && ctype_digit($parts[2])) {
            $_GET['group_id'] = (int)$parts[2];
            require __DIR__ . '/pages/chat_group.php';
            exit;
        }
        if (isset($parts[1]) && !empty($parts[1])) {
            $_GET['email'] = urldecode($parts[1]);
            require __DIR__ . '/pages/chat_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  КУЛИНАРНЫЕ КНИГИ
    // ---------------------------------------------------------
    
    case 'cookbook':
        if (isset($parts[1]) && !empty($parts[1])) {
            $_GET['slug'] = urldecode($parts[1]);
            if (preg_match('/[а-яА-ЯёЁ]/u', $_GET['slug'])) {
                $_GET['slug'] = generateSlug($_GET['slug']);
            }
            require __DIR__.'/pages/cookbook_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  ГЛАВНАЯ / HOME / FEED
    // ---------------------------------------------------------
    
    case '':
case 'home':
    load_page_meta('/');
    if (!is_logged_in()) {
        require __DIR__.'/pages/login.php';
    } else {
        require __DIR__.'/pages/home.php';
    }
    exit;

case 'feed':
    load_page_meta('/feed');
    if (!is_logged_in()) {
        require __DIR__.'/pages/login.php';
    } else {
        require __DIR__.'/pages/feed.php';
    }
    exit;

    // ---------------------------------------------------------
    //  БРОСАНИЕ КУРИТЬ
    // ---------------------------------------------------------
    
    case 'quit-smoking':
        load_page_meta('/quit-smoking');
        require __DIR__.'/pages/quit_smoking.php';
        exit;
        
    // ---------------------------------------------------------
    //  Праздники
    // ---------------------------------------------------------
    
    case 'ai-holiday':
    load_page_meta('/ai-holiday');
    if ($second === 'table' || $second === 'decor') {
        $holidayStage = ($second === 'table') ? 2 : 3;
        require __DIR__.'/pages/ai_holiday_tips.php';
    } else {
        require __DIR__.'/pages/ai_holiday.php';
    }
    exit;
    case 'my-holidays':
        load_page_meta('/my-holidays');
        require __DIR__.'/pages/my_holiday.php';
        exit;
    
    case 'holiday':
        if ($second !== '') {
            $_GET['slug'] = urldecode($second);
            require __DIR__.'/pages/holiday_detail.php';
            exit;
        }
        break;

    // ---------------------------------------------------------
    //  АВТОРИЗАЦИЯ
    // ---------------------------------------------------------
    
    case 'login':
        require __DIR__.'/pages/login.php';
        exit;

    case 'register':
        // /register/step2 — второй шаг регистрации
        if ($second === 'step2') {
            require __DIR__.'/pages/register_step2.php';
            exit;
        }
        require __DIR__.'/pages/register.php';
        exit;
        // ---------------------------------------------------------
    //  API
    // ---------------------------------------------------------

    case 'api':
        switch ($second) {
            case 'captcha':
                if (($parts[2] ?? '') === 'solve') {
                    require __DIR__ . '/api/captcha_solve.php';
                    exit;
                }
                break;
        }
        http_response_code(404);
        require __DIR__ . '/pages/404.php';
        exit;

    case 'logout':
        require __DIR__.'/pages/logout.php';
        exit;
    
    // ---------------------------------------------------------
    //  AI-ИНСТРУМЕНТЫ
    // ---------------------------------------------------------
    
    case 'ai-generator':
        load_page_meta('/ai-generator');
        require __DIR__.'/pages/ai_generator.php';
        exit;
        
    case 'what-to-cook':
        load_page_meta('/what-to-cook');
        require __DIR__.'/pages/what_to_cook.php';
        exit;
        
    case 'advisor':
        load_page_meta('/advisor');
        require __DIR__.'/pages/culinary_advisor.php';
        exit;
        
    case 'ai-cookbook':
        load_page_meta('/ai-cookbook');
        require __DIR__.'/pages/ai_cookbook_generator.php';
        exit;
        
    case 'calorie-scanner':
    case 'scanner':
        load_page_meta('/calorie-scanner');
        require __DIR__.'/pages/calorie_scanner.php';
        exit;
        
    case 'beer-pairing':
        load_page_meta('/beer-pairing');
        require __DIR__.'/pages/beer_pairing.php';
        exit;
        
    // ---------------------------------------------------------
    //  СОЦИАЛЬНЫЕ ФУНКЦИИ
    // ---------------------------------------------------------
    
    case 'stories':
        load_page_meta('/stories');
        require __DIR__.'/pages/stories.php';
        exit;
        
    case 'notifications':
        load_page_meta('/notifications');
        require __DIR__.'/pages/notifications.php';
        exit;
        
    case 'achievements':
        load_page_meta('/achievements');
        require __DIR__.'/pages/achievements.php';
        exit;

    // ---------------------------------------------------------
    //  ПРАВОВЫЕ СТРАНИЦЫ
    // ---------------------------------------------------------
    
    case 'terms':
        require __DIR__.'/pages/terms.php';
        exit;

    case 'privacy':
        require __DIR__.'/pages/privacy.php';
        exit;

    case 'legal':
        require __DIR__.'/pages/legal.php';
        exit;

    case 'about':
        require __DIR__.'/pages/about.php';
        exit;
    
    // ---------------------------------------------------------
    //  СОЗДАНИЕ И ПОИСК
    // ---------------------------------------------------------
    
    case 'create-recipe':
        load_page_meta('/create-recipe');
        require __DIR__.'/pages/create_recipe.php';
        exit;

    case 'search':
        load_page_meta('/search');
        require __DIR__.'/pages/search.php';
        exit;

    case 'inspiration':
        load_page_meta('/inspiration');
        require __DIR__.'/pages/inspiration.php';
        exit;

    // ---------------------------------------------------------
    //  ЛИЧНОЕ
    // ---------------------------------------------------------
    
    case 'favorites':
        load_page_meta('/favorites');
        require __DIR__.'/pages/saved_recipes.php';
        exit;

    case 'my-cookbooks':
        load_page_meta('/my-cookbooks');
        require __DIR__.'/pages/my_cookbooks.php';
        exit;

    // ---------------------------------------------------------
    //  СООБЩЕСТВО
    // ---------------------------------------------------------
    
    case 'communities':
        load_page_meta('/communities');
        require __DIR__.'/pages/communities.php';
        exit;

    case 'challenges':
        load_page_meta('/challenges');
        require __DIR__.'/pages/challenges.php';
        exit;

    case 'leaderboard':
        load_page_meta('/leaderboard');
        require __DIR__.'/pages/leaderboard.php';
        exit;

    case 'friends':
        load_page_meta('/friends');
        require __DIR__.'/pages/friends.php';
        exit;

    case 'chats':
        load_page_meta('/chats');
        require __DIR__.'/pages/chats.php';
        exit;

    // ---------------------------------------------------------
    //  МЕДИА И РАЗВЛЕЧЕНИЯ
    // ---------------------------------------------------------
    
    case 'videos':
        load_page_meta('/videos');
        require __DIR__.'/pages/videos.php';
        exit;

    case 'games':
        load_page_meta('/games');
        require __DIR__.'/pages/games.php';
        exit;

    // ---------------------------------------------------------
    //  ПЛАТЕЖИ
    // ---------------------------------------------------------
    
    case 'payment':
        require __DIR__.'/pages/payment.php';
        exit;

    case 'billing':
        load_page_meta('/billing');
        require __DIR__.'/pages/billing_history.php';
        exit;
}

// ============================================================
//  404 — если ни один маршрут не совпал
// ============================================================

http_response_code(404);
require __DIR__.'/pages/404.php';
exit;