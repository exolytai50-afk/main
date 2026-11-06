<?php
/**
 * GigaChefAI — единый файл с общими вспомогательными функциями и хелперами.
 * Все функции для работы с JSON, URL, CSRF, HTML и т.д.
 */
declare(strict_types=1);

// ==========================================================
//  ВЫВОД / ВВОД
// ==========================================================

/** Отдаёт JSON-ответ и завершает выполнение */
function json_response($data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Читает и декодирует JSON-тело запроса */
function json_input(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/**
 * Безопасное декодирование JSON-поля из БД.
 */
function json_field($value, $default = [])
{
    if (is_array($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : $default;
}

// ==========================================================
//  HTML / URL
// ==========================================================

/** Экранирование для HTML-вывода */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Абсолютный URL внутри сайта с учётом BASE_PATH */
function url(string $path = ''): string
{
    $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
    return $base . '/' . ltrim($path, '/');
}

/** URL к статике (assets/) */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Редирект внутри сайта или на внешний URL */
function redirect(string $path): void
{
    if (!headers_sent()) {
        $target = preg_match('~^https?://~', $path) ? $path : url($path);
        header('Location: ' . $target);
    }
    exit;
}

// ==========================================================
//  I18N
// ==========================================================

/** Перевод строки */
function t(string $key, array $params = []): string
{
    static $dict = [
        'app_name'         => 'GigaChef',
        'search'           => 'Поиск',
        'trending'         => 'В тренде',
        'seasonal'         => 'Сезонные рецепты',
        'ai_tools'         => 'AI-инструменты',
        'community'        => 'Сообщество',
        'personal'         => 'Личное',
        'login'            => 'Войти',
        'logout'           => 'Выйти',
        'register'         => 'Регистрация',
        'profile'          => 'Профиль',
        'save'             => 'Сохранить',
        'saved'            => 'Сохранено',
        'min'              => 'мин',
        'servings'         => 'порций',
        'view_recipe'      => 'Открыть рецепт',
        'no_recipes'       => 'Рецепты не найдены',
        'ai_generate'      => 'Сгенерировать рецепт',
        'what_to_cook'     => 'Что приготовить?',
        'advisor'          => 'Кулинарный советник',
        'diabetes_adapted' => 'Адаптировано для диабетиков',
        'fusion'           => 'Фьюжн',
        'fusion_lab'       => 'Фьюжн-лаборатория',
        'fusion_create'    => 'Создать фьюжн-блюдо',
        'fusion_save'      => 'Сохранить в книгу',
        'fusion_mix'       => 'Смешать кухни',
    ];
    $text = $dict[$key] ?? $key;
    return $params ? strtr($text, $params) : $text;
}

// ==========================================================
//  ФОРМЫ / ФЛЕШИ
// ==========================================================

/** Возвращает старое значение поля формы */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

/** Флеш-сообщение */
function flash(string $key, ?string $msg = null): ?string
{
    if ($msg === null) {
        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
    $_SESSION['_flash'][$key] = $msg;
    return null;
}

// ==========================================================
//  CSRF
// ==========================================================

/** Возвращает CSRF-токен текущей сессии */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Скрытое поле с CSRF-токеном */
function csrf_field(): string
{
    $t = e(csrf_token());
    return '<input type="hidden" name="_csrf" value="' . $t . '">'
         . '<input type="hidden" name="csrf" value="' . $t . '">';
}

/** Проверка CSRF-токена */
function csrf_check(?string $token = null): bool
{
    if ($token === null || $token === '') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (!$token) {
        $token = $_POST['_csrf'] ?? ($_POST['csrf'] ?? '');
    }

    if (!$token) {
        $data  = json_input();
        $token = $data['_csrf'] ?? ($data['csrf'] ?? '');
    }

    return !empty($_SESSION['_csrf'])
        && is_string($token) && $token !== ''
        && hash_equals($_SESSION['_csrf'], $token);
}

// ==========================================================
//  ФОРМАТИРОВАНИЕ
// ==========================================================

/** Человекочитаемое время «X мин» */
function format_time(int $minutes): string
{
    if ($minutes <= 0) return '—';
    if ($minutes < 60) return $minutes . ' мин';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m ? "{$h} ч {$m} мин" : "{$h} ч";
}

/** Цвет-акцент для типа сложности */
function difficulty_color(string $difficulty): string
{
    return match ($difficulty) {
        'Легко'         => 'bg-emerald-100 text-emerald-700',
        'Средне'        => 'bg-amber-100 text-amber-700',
        'Сложно'        => 'bg-rose-100 text-rose-700',
        'Мастер-класс'  => 'bg-violet-100 text-violet-700',
        'easy'          => 'bg-emerald-100 text-emerald-700',
        'medium'        => 'bg-amber-100 text-amber-700',
        'hard'          => 'bg-rose-100 text-rose-700',
        default       => 'bg-gray-100 text-gray-600',
    };
}

/** Человекочитаемая сложность */
function difficulty_label(string $difficulty): string
{
    return match ($difficulty) {
        'easy'   => 'Легко',
        'medium' => 'Средне',
        'hard'   => 'Сложно',
        default  => $difficulty,
    };
}

/** Название для типа блюда */
function dish_type_label(string $type): string
{
    return match ($type) {
        'appetizer' => 'Закуска',
        'soup'      => 'Суп',
        'main'      => 'Основное',
        'dessert'   => 'Десерт',
        'drink'     => 'Напиток',
        'snack'     => 'Снек',
        default     => 'Блюдо',
    };
}

// ==========================================================
//  ИЗОБРАЖЕНИЯ
// ==========================================================

/**
 * Получить URL изображения рецепта с fallback'ом
 */
function recipe_image_url(?string $imageUrl = null): string
{
    $default = '/uploads/static/static.jpg';
    
    if (empty($imageUrl)) {
        return $default;
    }
    
    if (str_starts_with($imageUrl, '/uploads/')) {
        $localPath = ROOT_DIR . $imageUrl;
        if (!file_exists($localPath)) {
            return $default;
        }
    }
    
    if (str_starts_with($imageUrl, 'http')) {
        return $imageUrl;
    }
    
    return $default;
}

/**
 * Получить URL изображения фьюжн-рецепта
 */
function fusion_image_url(?string $imageUrl = null): string
{
    return recipe_image_url($imageUrl);
}

// ==========================================================
//  БАЛЛЫ / ОЧКИ (с защитой от дублей)
// ==========================================================

/**
 * Начисляет пользователю баллы с логированием
 * Защита от двойного начисления через points_log
 */
function add_user_points(int $userId, int $points, string $reason, string $description = ''): bool
{
    if ($points === 0) return true;
    
    try {
        $db = db();
        $db->beginTransaction();
        
        // Обновляем users.points
        db_exec(
            'UPDATE users SET points = points + ? WHERE id = ?',
            [$points, $userId]
        );
        
        // Обновляем user_stats.total_points
        try {
            db_exec(
                'INSERT INTO user_stats (user_id, total_points, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                     total_points = total_points + VALUES(total_points),
                     updated_at = NOW()',
                [$userId, $points]
            );
        } catch (Throwable $e) {
            // Fallback без updated_at
            db_exec(
                'INSERT INTO user_stats (user_id, total_points)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE 
                     total_points = total_points + VALUES(total_points)',
                [$userId, $points]
            );
        }
        
        // Логируем операцию
        db_insert(
    'INSERT INTO points_log (user_id, points, reason, description, created_at)
     VALUES (?, ?, ?, ?, NOW())',
    [$userId, $points, $reason, $description]
);
        
        $db->commit();
        
        // Проверяем достижения
        $user = db_one('SELECT email FROM users WHERE id = ?', [$userId]);
        if ($user && function_exists('check_achievements')) {
            try {
                check_achievements($user['email']);
            } catch (Throwable $e) {
                error_log('check_achievements error: ' . $e->getMessage());
            }
        }
        
        return true;
        
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('add_user_points error: ' . $e->getMessage());
        return false;
    }
}

// ==========================================================
//  ДИАБЕТИЧЕСКИЙ КАЛЬКУЛЯТОР
// ==========================================================

/** Расчет хлебных единиц */
function calculate_bread_units(float $carbsGrams): float
{
    return $carbsGrams / 12.0;
}

/** Расчет гликемической нагрузки */
function calculate_glycemic_load(int $glycemicIndex, float $carbsGrams): float
{
    return ($glycemicIndex * $carbsGrams) / 100.0;
}

/** Классификация гликемической нагрузки */
function classify_glycemic_load(float $glycemicLoad): string
{
    if ($glycemicLoad < 10) {
        return 'Низкая ГН';
    } elseif ($glycemicLoad < 20) {
        return 'Средняя ГН';
    } else {
        return 'Высокая ГН';
    }
}

/** Получение гликемического индекса продукта */
function get_glycemic_index(string $productName): int
{
    static $cache = [];
    $key = mb_strtolower(trim($productName));
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        $product = db_one(
            'SELECT glycemic_index FROM gi_products WHERE LOWER(product_name) = ? OR LOWER(product_name) LIKE ?',
            [$key, '%' . $key . '%']
        );
        
        $gi = $product ? (int)$product['glycemic_index'] : 50;
        $cache[$key] = $gi;
        return $gi;
    } catch (\Exception $e) {
        return 50;
    }
}

/** Получение углеводов на 100 г продукта */
function get_carbs_per_100g(string $productName): float
{
    static $cache = [];
    $key = mb_strtolower(trim($productName));
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        $product = db_one(
            'SELECT carbs_per_100g FROM gi_products WHERE LOWER(product_name) = ? OR LOWER(product_name) LIKE ?',
            [$key, '%' . $key . '%']
        );
        
        $carbs = $product ? (float)$product['carbs_per_100g'] : 20.0;
        $cache[$key] = $carbs;
        return $carbs;
    } catch (\Exception $e) {
        return 20.0;
    }
}

// ==========================================================
//  ВСПОМОГАТЕЛЬНЫЕ ДЛЯ ФЬЮЖН
// ==========================================================

/**
 * Форматирует список ингредиентов фьюжн-рецепта
 */
function format_fusion_ingredients(array $ingredients): array
{
    $result = [];
    foreach ($ingredients as $ing) {
        if (is_string($ing)) {
            $result[] = ['name' => $ing, 'amount' => ''];
        } elseif (is_array($ing)) {
            $result[] = [
                'name'   => (string)($ing['name'] ?? ''),
                'amount' => (string)($ing['amount'] ?? ''),
            ];
        }
    }
    return $result;
}

/* ============================================================
 *  СЛОЖНОСТЬ РЕЦЕПТА: нормализация, подпись, цвет
 * ============================================================ */

if (!function_exists('difficulty_normalize')) {
    /**
     * Приводит любое значение сложности (латиница/кириллица, разный род)
     * к одному из ключей: 'easy' | 'medium' | 'hard' | 'unknown'.
     */
    function difficulty_normalize(?string $value): string {
        $value = trim((string)$value);
        if ($value === '') return 'medium';

        $key = mb_strtolower($value, 'UTF-8');

        $easy = [
            'easy', 'beginner', 'novice',
            'лёгкий', 'легкий', 'лёгкая', 'легкая', 'лёгкое', 'легкое',
            'легко', 'просто', 'простой', 'простая',
        ];
        $medium = [
            'medium', 'middle', 'normal',
            'средне', 'средний', 'средняя', 'среднее', 'нормально',
        ];
        $hard = [
            'hard', 'expert', 'advanced', 'pro',
            'сложно', 'сложный', 'сложная', 'сложное', 'сложен',
        ];

        if (in_array($key, $easy, true))   return 'easy';
        if (in_array($key, $medium, true)) return 'medium';
        if (in_array($key, $hard, true))   return 'hard';

        return 'unknown';
    }
}

if (!function_exists('difficulty_label')) {
    /**
     * Русская подпись сложности.
     */
    function difficulty_label(?string $value): string {
        $level = difficulty_normalize($value);
        return [
            'easy'    => 'Легко',
            'medium'  => 'Средне',
            'hard'    => 'Сложно',
            'unknown' => '—',
        ][$level];
    }
}

if (!function_exists('difficulty_color')) {
    /**
     * CSS-классы (фон + текст) для лейбла сложности.
     */
    function difficulty_color(?string $value): string {
        $level = difficulty_normalize($value);
        return [
            'easy'    => 'bg-emerald-100 text-emerald-700',
            'medium'  => 'bg-amber-100 text-amber-700',
            'hard'    => 'bg-rose-100 text-rose-700',
            'unknown' => 'bg-gray-100 text-gray-500',
        ][$level];
    }
}
/**
 * Возвращает SVG-заглушку аватара (силуэт пользователя).
 * Используется, когда у пользователя нет avatar_url.
 *
 * Единая точка правды — не тянуть из БД, не дублировать в шаблонах.
 */
if (!function_exists('avatar_normalize_gender')) {
    /**
     * Приводит значение пола из БД/формы/OAuth к 'male' | 'female' | null.
     * Понимает male/female, m/f, man/woman, м/ж, мужской/женский, мужчина/женщина, 1/2.
     */
    function avatar_normalize_gender($gender): ?string
    {
        if ($gender === null || $gender === false) return null;
        $g = mb_strtolower(trim((string)$gender), 'UTF-8');
        if ($g === '') return null;
 
        static $male   = ['male', 'm', 'man', 'boy', 'м', 'муж', 'мужской', 'мужчина', 'парень', 'мальчик', '1'];
        static $female = ['female', 'f', 'woman', 'w', 'girl', 'ж', 'жен', 'женский', 'женщина', 'девушка', 'девочка', '2'];
 
        if (in_array($g, $male, true))   return 'male';
        if (in_array($g, $female, true)) return 'female';
        return null;
    }
}
 
if (!function_exists('default_avatar_svg')) {
    /**
     * SVG-заглушка аватара.
     *
     * @param mixed $gender 'male' | 'female' | синонимы (м/ж, мужской/женский, 1/2) | null
     */
    function default_avatar_svg($gender = null): string
    {
        $gender = avatar_normalize_gender($gender);
 
        // ─── ЖЕНЩИНА ───
        if ($gender === 'female') {
            return '<svg width="100%" height="100%" viewBox="0 0 512 512" version="1.1" xmlns="http://www.w3.org/2000/svg" desc="Created with imagetracer.js version 1.2.6" ><path fill="rgb(248,240,215)" stroke="rgb(248,240,215)" stroke-width="1" opacity="0.99" d="M 408.5 424 L 409.5 426 L 408.5 424 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 149.5 389 L 150.5 391 L 149.5 389 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 178.5 395 L 179.5 397 L 178.5 395 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 156.5 397 L 162.5 404 L 156.5 397 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 183.5 399 L 184.5 401 L 183.5 399 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 138.5 400 L 139.5 402 L 138.5 400 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 145.5 408 L 147.5 411 L 145.5 408 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 168.5 408 L 170.5 411 L 168.5 408 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 92.5 412 L 89.5 416 L 92.5 412 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 149.5 412 L 151.5 415 L 149.5 412 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 173.5 412 L 174.5 414 L 173.5 412 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 419.5 412 L 421.5 415 L 419.5 412 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 158.5 420 L 159.5 422 L 158.5 420 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 244.5 423 L 247 423.5 L 244.5 424 L 244.5 423 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 79.5 438 L 80 441.5 L 79 441.5 L 79.5 438 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 432.5 439 L 433 504.5 L 432 504.5 L 432.5 439 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 254.5 440 L 259 440.5 L 254.5 441 L 254.5 440 Z " /><path fill="rgb(239,203,151)" stroke="rgb(239,203,151)" stroke-width="1" opacity="1" d="M 266.5 455 L 269 455.5 L 266.5 456 L 266.5 455 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 0 0 L 244 0.5 L 220.5 4 L 197.5 12 Q 168.07 25.57 148 48.5 Q 124.45 73.95 115 113.5 L 114 124.5 L 113 125.5 L 113 134.5 L 112 135.5 L 112 216.5 L 113 217.5 L 113 229.5 L 114 230.5 L 114 238.5 L 115 239.5 L 117 256.5 L 123 279.5 L 132 303.5 Q 147.53 338.47 172 364.5 L 97.5 391 Q 84.49 396.99 76 407.5 L 67 423.5 L 64 436.5 L 64 506.5 L 67.5 511 L 69 511.5 L 0 512 L 0 0 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 268.5 0 L 512 0 L 512 512 L 443 511.5 L 447 508.5 L 448 506.5 L 448 436.5 Q 443.69 411.81 427.5 399 L 414.5 391 L 400 385.5 L 400 135.5 L 399 134.5 L 399 125.5 L 395 105.5 L 384 77.5 Q 371.13 53.37 351.5 36 Q 335.51 21.49 314.5 12 L 291.5 4 L 276.5 1 L 268.5 1 L 268.5 0 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 79 505 L 352.5 505 L 353 507.5 L 358 511.5 L 74.5 512 L 74.5 511 L 78 508.5 L 79 505 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 368.5 505 L 384.5 505 L 385 507.5 L 390 511.5 L 363.5 512 L 363.5 511 L 367 508.5 L 368.5 505 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 400.5 505 L 432.5 505 L 433 506.5 L 436.5 511 L 438 511.5 L 395.5 512 L 395.5 511 L 399 508.5 L 400.5 505 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 244.5 0 L 267.5 0 L 268.5 1 L 282.5 2 Q 300.03 5.47 314.5 12 Q 344.06 25.44 364 48.5 Q 377.02 62.98 386 81.5 L 395 105.5 L 399 125.5 L 399 134.5 L 400 135.5 L 400 385.5 L 401.5 387 Q 424.15 391.85 436 407.5 L 445 423.5 L 448 436.5 L 448 506.5 L 444.5 511 L 438.5 512 L 434 508.5 L 433 506.5 L 433 435.5 Q 429.13 418.87 417.5 410 Q 410.38 403.73 400 402 L 400 505.5 L 396.5 511 L 390.5 512 L 385 507.5 L 385 221.5 L 382.5 221 Q 377.83 224.83 367.5 223 L 366 224.5 L 366 229.5 L 360 249.5 Q 353.89 264.89 344 276.5 L 344 362.5 L 350 396.5 L 355 412.5 L 363 447.5 L 365 466.5 L 366 467.5 L 366 476.5 L 367 477.5 L 368 505.5 L 364.5 511 L 358.5 512 L 353 507.5 L 353 495.5 L 348 472.5 L 338 450.5 L 320 421.5 L 314 409 Q 298.1 417.8 276.5 422 Q 271.75 420.75 270.5 423 L 241.5 423 L 225.5 420 Q 209.51 415.49 196.5 408 Q 178.95 398.05 166.5 383 L 164.5 383 L 152.5 387 L 149 389.5 L 166.5 408 Q 185.37 424.63 211.5 434 L 236.5 440 L 266.5 441 L 267.5 440 L 285.5 438 L 298.5 434 L 303.5 434 Q 307.92 436.08 308 442.5 L 306 447 L 289.5 452 L 272.5 455 L 239.5 455 L 214.5 450 Q 198.07 445.43 184.5 438 Q 161.06 425.44 144 406.5 L 133.5 394 L 98.5 407 L 87 417.5 Q 81.3 424.8 79 435.5 L 79 506.5 L 75.5 511 L 69.5 512 L 65 508.5 L 64 506.5 L 64 436.5 Q 68.31 411.81 84.5 399 Q 92.04 392.54 102.5 389 L 172 364.5 Q 137.14 327.86 121 272.5 L 115 245.5 L 114 230.5 L 113 229.5 L 113 217.5 L 112 216.5 L 112 135.5 L 113 134.5 L 113 125.5 L 117 105.5 L 126 81.5 Q 139.26 54.76 160.5 36 Q 176.49 21.49 197.5 12 L 220.5 4 L 235.5 1 L 243.5 1 L 244.5 0 Z M 247 15 L 246 16 L 232 17 L 219 20 Q 224 34 234 44 Q 236 52 230 54 L 223 53 Q 211 41 205 26 Q 189 32 177 43 Q 181 53 189 61 Q 211 86 241 103 Q 261 115 286 122 L 312 128 L 327 129 L 328 130 L 369 130 L 370 129 L 383 128 L 384 127 L 381 111 L 369 112 L 368 113 L 335 113 L 334 112 L 316 110 L 301 106 Q 265 95 241 72 L 242 65 L 247 61 L 253 62 Q 269 78 293 87 L 318 95 L 336 97 L 337 98 L 367 98 L 368 97 L 375 97 L 374 92 L 369 82 L 351 57 Q 331 35 302 23 L 275 16 L 247 15 Z M 165 53 Q 147 70 137 94 L 129 120 L 128 135 L 128 136 L 142 135 L 157 132 L 173 127 Q 193 119 210 107 L 213 103 L 187 81 L 165 53 Z M 227 112 Q 205 132 175 141 L 175 158 L 170 170 Q 164 178 154 182 L 139 183 L 134 185 Q 128 187 127 194 L 127 199 L 130 205 L 139 209 L 144 209 Q 146 208 145 205 L 149 201 L 157 202 L 159 206 L 160 224 L 165 243 Q 171 257 180 269 Q 191 282 206 291 L 228 301 L 250 305 L 263 305 L 264 304 L 272 304 L 281 302 Q 302 296 318 283 Q 336 268 346 245 L 352 224 L 353 206 L 354 204 Q 356 200 364 201 L 367 205 Q 366 208 369 209 Q 378 209 382 205 L 385 199 L 384 191 L 377 184 Q 371 182 363 183 L 351 178 Q 342 172 338 162 L 337 145 L 313 143 L 281 136 Q 251 127 227 112 Z M 379 143 L 369 145 L 352 145 L 352 157 L 354 161 L 364 168 L 373 168 L 385 171 L 385 144 Q 385 142 379 143 Z M 159 146 L 149 149 L 134 150 L 133 151 Q 127 150 127 152 L 127 171 L 140 168 L 149 168 L 153 166 L 160 157 L 160 147 L 159 146 Z M 128 221 L 129 238 L 133 260 L 145 296 Q 161 333 189 359 L 193 356 Q 198 351 200 344 L 200 304 L 185 294 Q 168 280 157 261 L 149 242 L 146 230 L 146 225 L 145 223 Q 133 224 128 221 Z M 329 293 L 312 304 L 312 359 L 313 360 L 314 371 L 321 392 L 342 430 L 343 430 L 334 394 L 330 372 L 330 363 L 329 362 L 329 293 Z M 215 312 L 215 345 Q 212 357 205 365 Q 197 373 184 377 Q 181 376 182 379 Q 194 391 211 399 L 227 405 L 248 409 L 264 409 L 265 408 L 278 407 Q 295 403 307 396 L 302 383 L 297 358 L 297 312 L 289 315 L 270 319 L 243 319 L 224 315 L 215 312 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 215.5 150 Q 223.4 149.1 225 154.5 Q 226.5 162.5 221.5 164 L 212.5 166 L 197.5 167 L 194 164.5 L 193 156.5 L 196.5 153 L 214.5 151 L 215.5 150 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 292.5 150 L 312.5 152 L 319 156.5 Q 320.25 163.75 316.5 166 L 307.5 167 L 306.5 166 L 292.5 165 L 287 160.5 Q 285.5 152.5 290.5 151 L 292.5 150 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 204.5 177 Q 211.75 175.75 214 179.5 L 215 181.5 L 215 194.5 L 212.5 198 L 205.5 199 L 202 196.5 L 201 194.5 L 201 180.5 L 204.5 177 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 301.5 177 Q 309.5 175.5 311 180.5 L 311 194.5 L 308.5 198 L 301.5 199 L 298 196.5 L 297 194.5 L 297 181.5 L 299.5 178 L 301.5 177 Z " /><path fill="rgb(1,1,0)" stroke="rgb(1,1,0)" stroke-width="1" opacity="0.99" d="M 229.5 233 L 234.5 233 L 246.5 240 L 259.5 241 L 268.5 239 L 277.5 233 L 282.5 233 L 286 235.5 L 287 242.5 L 283.5 247 L 269.5 254 L 264.5 255 L 247.5 255 Q 233.77 252.73 226 244.5 L 225 237.5 L 227.5 234 L 229.5 233 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 226.5 112 Q 247.16 125.34 272.5 134 L 303.5 142 L 322.5 144 L 323.5 145 L 336 145 Q 335.05 167.64 348.5 177 Q 357.4 185.1 375.5 184 L 383 189.5 Q 385.5 193 384 200.5 L 379.5 206 Q 375.65 209.33 368 208 L 365.5 202 L 358.5 200 L 353 204.5 L 351 226.5 L 346 243.5 Q 336.96 265.46 320.5 280 Q 305.04 294.04 282.5 301 L 267.5 304 L 244.5 304 L 241 302.5 Q 274.85 295.85 294 274.5 Q 310.89 257.39 318 230.5 L 320 219.5 L 320 169.5 L 317 165.5 Q 320.86 163.86 320 157.5 L 316.5 153 L 300.5 150 L 291.5 150 L 287.5 153 L 283.5 153 L 249.5 141 L 225.5 129 L 215 121.5 L 226.5 112 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 175.5 153 L 176 155.5 L 175 155.5 L 175.5 153 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 193.5 164 L 194.5 166 L 193.5 164 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 212.5 177 L 214.5 180 L 212.5 177 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 299.5 177 L 297.5 180 L 299.5 177 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 201.5 196 L 202.5 198 L 201.5 196 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 310.5 196 L 309.5 198 L 310.5 196 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 227.5 233 L 225.5 236 L 227.5 233 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 236.5 233 L 237.5 235 L 236.5 233 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 275.5 233 L 274.5 235 L 275.5 233 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 284.5 233 L 286.5 236 L 284.5 233 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 225.5 244 L 227.5 247 L 225.5 244 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 286.5 244 L 284.5 247 L 286.5 244 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 184.5 272 L 191.5 280 L 184.5 272 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 196.5 283 L 197.5 285 L 196.5 283 Z " /><path fill="rgb(249,186,126)" stroke="rgb(249,186,126)" stroke-width="1" opacity="1" d="M 216 313 L 238.5 319 L 265.5 320 L 266.5 319 L 273.5 319 L 283.5 317 L 296 313 L 296 351.5 L 297 352.5 L 297 361.5 L 300 377.5 L 306 396 L 290.5 403 L 275.5 407 Q 270.75 405.75 269.5 408 L 243.5 408 L 224.5 404 L 209.5 398 Q 193.42 390.08 182 377.5 Q 199.25 373.75 208 361.5 L 215 346.5 Q 213.75 341.75 216 340.5 L 216 313 Z " /><path fill="rgb(251,244,219)" stroke="rgb(251,244,219)" stroke-width="1" opacity="1" d="M 132.5 395 L 136 397.5 Q 144.04 409.96 155.5 419 Q 178.36 439.64 211.5 450 L 241.5 456 L 269.5 456 L 297.5 451 L 307 446.5 Q 309.83 443.53 309 438 L 324.5 431 Q 337.33 448.17 346 469.5 L 351 487.5 L 352 505 L 80.5 505 L 80 504.5 L 80 434.5 Q 83.98 419.48 94.5 411 L 104.5 405 L 132.5 395 Z " /><path fill="rgb(251,244,219)" stroke="rgb(251,244,219)" stroke-width="1" opacity="1" d="M 400 420 L 403.5 421 L 412 428.5 L 416 438.5 L 416 505 L 400.5 505 L 400 504.5 L 400 420 Z " /><path fill="rgb(255,209,140)" stroke="rgb(255,209,140)" stroke-width="1" opacity="1" d="M 214.5 122 Q 245.45 143.32 287 153 L 286 159.5 L 290.5 165 L 313.5 168 L 317.5 166 L 320 169.5 L 320 219.5 L 317 234.5 L 313 245.5 Q 304.98 263.98 291.5 277 Q 272.33 297.33 238.5 303 Q 200.9 294.6 181 268.5 Q 171 256.5 165 240.5 L 160 219.5 L 160 206.5 L 156.5 201 L 149.5 200 L 145 203.5 L 144 208 Q 135.13 209.05 131 205 L 128 199.5 L 128 192.5 L 130 188 L 137.5 184 L 147.5 184 L 155.5 182 Q 164.52 178.02 170 170.5 L 175 160.5 L 176 142.5 L 177.5 141 Q 198.91 134.41 214.5 122 Z M 211 150 L 196 153 L 192 158 L 193 165 L 199 168 L 222 165 L 226 160 L 225 154 L 222 150 L 211 150 Z M 206 176 L 204 177 L 200 182 L 200 195 L 201 197 L 207 200 L 214 198 L 216 194 Q 217 185 215 180 Q 213 175 206 176 Z M 302 176 L 300 177 L 296 183 Q 294 193 298 198 L 303 200 L 309 199 L 312 195 L 312 182 L 311 180 Q 309 175 302 176 Z M 230 232 L 228 233 L 224 238 L 225 245 L 238 253 L 250 256 Q 272 257 284 248 L 288 243 L 287 236 L 283 232 L 278 232 L 267 239 L 262 240 L 246 239 L 235 232 L 230 232 Z " /><path fill="rgb(247,234,203)" stroke="rgb(247,234,203)" stroke-width="1" opacity="1" d="M 163.5 384 L 166.5 384 L 184.5 401 Q 196.49 410.01 211.5 416 L 236.5 423 Q 242.75 421.25 244.5 424 L 266.5 424 L 288.5 420 L 314 410 L 325 431 L 308.5 438 Q 306.33 433.17 299.5 433 L 287.5 437 L 277.5 439 L 270.5 439 L 269.5 440 L 241.5 440 L 210.5 433 L 190.5 424 Q 167.52 410.98 151 391.5 L 150 389.5 L 163.5 384 Z " /><path fill="rgb(247,234,203)" stroke="rgb(247,234,203)" stroke-width="1" opacity="1" d="M 400 403 Q 416.35 406.36 425 418.5 Q 430.05 424.95 432 434.5 L 432 505 L 417.5 505 L 416 503.5 L 416 436.5 Q 413.75 428.75 408.5 424 L 402.5 420 L 400 420 L 400 403 Z " /><path fill="rgb(247,234,203)" stroke="rgb(247,234,203)" stroke-width="1" opacity="1" d="M 143.5 406 L 144.5 408 L 143.5 406 Z " /><path fill="rgb(247,234,203)" stroke="rgb(247,234,203)" stroke-width="1" opacity="1" d="M 152.5 415 L 153.5 417 L 152.5 415 Z " /><path fill="rgb(247,234,203)" stroke="rgb(247,234,203)" stroke-width="1" opacity="1" d="M 79.5 442 L 80 503.5 L 79 503.5 L 79.5 442 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 246.5 15 L 274.5 16 L 301.5 23 Q 331.12 34.88 351 56.5 L 369 81.5 L 374 91.5 L 375 97 L 367.5 97 L 366.5 98 L 336.5 98 L 335.5 97 L 317.5 95 L 292.5 87 Q 269.4 77.6 252.5 62 L 246.5 61 L 242 64.5 L 241 71.5 Q 264.77 94.73 300.5 106 L 315.5 110 L 333.5 112 L 334.5 113 L 367.5 113 L 368.5 112 L 381 111 L 384 126.5 L 382.5 128 L 369.5 129 L 368.5 130 L 327.5 130 L 326.5 129 L 311.5 128 L 285.5 122 Q 260.8 114.7 240.5 103 Q 210.6 85.9 189 60.5 Q 181.33 53.17 177 42.5 Q 188.86 31.55 205 26 Q 211.08 41.46 222.5 53 L 229.5 54 Q 235.5 52.5 234 43.5 Q 224.12 33.53 219 20 L 231.5 17 L 245.5 16 L 246.5 15 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 164.5 53 L 187 81 L 213 102.5 L 209.5 107 Q 193.26 119.26 172.5 127 L 156.5 132 L 141.5 135 L 127.5 136 L 128 134.5 L 129 119.5 L 137 93.5 Q 147.25 69.75 164.5 53 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 224.5 113 L 222.5 116 L 224.5 113 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 231.5 114 L 232.5 116 L 231.5 114 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 219.5 117 L 217.5 120 L 219.5 117 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 206.5 126 L 205.5 128 L 206.5 126 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 175.5 141 L 176 152.5 L 175 152.5 L 175.5 141 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 303.5 141 L 306 141.5 L 303.5 142 L 303.5 141 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 309.5 142 L 312 142.5 L 309.5 143 L 309.5 142 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 315.5 143 L 319 143.5 L 315.5 144 L 315.5 143 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 378.5 143 Q 384.75 142.25 385 143.5 L 385 171 L 372.5 168 L 363.5 168 L 354 160.5 L 352 156.5 L 352 145 L 368.5 145 L 378.5 143 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 323.5 144 L 329 144.5 L 323.5 145 L 323.5 144 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 336.5 145 L 337 153.5 L 336 153.5 L 336.5 145 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 158.5 146 L 160 146.5 L 160 156.5 L 152.5 166 L 148.5 168 L 139.5 168 L 127 171 L 127 151.5 Q 127.12 150.38 132.5 151 L 133.5 150 L 148.5 149 L 158.5 146 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 212.5 150 L 215 150.5 L 212.5 151 L 212.5 150 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 297.5 150 L 301 150.5 L 297.5 151 L 297.5 150 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 204.5 151 L 207 151.5 L 204.5 152 L 204.5 151 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 222.5 151 L 223.5 153 L 222.5 151 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 289.5 151 L 287.5 154 L 289.5 151 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 305.5 151 L 309 151.5 L 305.5 152 L 305.5 151 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 197.5 152 L 200 152.5 L 197.5 153 L 197.5 152 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 195.5 153 L 194.5 155 L 195.5 153 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 316.5 153 L 318.5 156 L 316.5 153 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 225.5 156 L 226 158.5 L 225 158.5 L 225.5 156 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 286.5 156 L 287 158.5 L 286 158.5 L 286.5 156 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 319.5 157 L 320 161.5 L 319 161.5 L 319.5 157 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 192.5 158 L 193 161.5 L 192 161.5 L 192.5 158 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 223.5 162 L 222.5 164 L 223.5 162 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 288.5 162 L 289.5 164 L 288.5 162 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 213.5 165 L 216 165.5 L 213.5 166 L 213.5 165 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 296.5 165 L 299 165.5 L 296.5 166 L 296.5 165 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 205.5 166 L 209 166.5 L 205.5 167 L 205.5 166 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 303.5 166 L 307 166.5 L 303.5 167 L 303.5 166 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 168.5 171 L 163.5 177 L 168.5 171 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 343.5 171 L 348.5 177 L 343.5 171 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 206.5 176 L 210 176.5 L 206.5 177 L 206.5 176 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 302.5 176 L 306 176.5 L 302.5 177 L 302.5 176 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 200.5 182 L 201 193.5 L 200 193.5 L 200.5 182 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 215.5 182 L 216 192.5 L 215 192.5 L 215.5 182 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 296.5 182 L 297 192.5 L 296 192.5 L 296.5 182 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 311.5 182 L 312 193.5 L 311 193.5 L 311.5 182 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 138.5 183 L 146 183.5 L 138.5 184 L 138.5 183 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 365.5 183 L 374 183.5 L 365.5 184 L 365.5 183 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 127.5 193 L 128 198.5 L 127 198.5 L 127.5 193 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 384.5 193 L 385 198.5 L 384 198.5 L 384.5 193 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 150.5 200 L 154 200.5 L 150.5 201 L 150.5 200 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 358.5 200 L 362 200.5 L 358.5 201 L 358.5 200 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 146.5 202 L 145.5 204 L 146.5 202 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 365.5 202 L 366.5 204 L 365.5 202 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 144.5 205 L 145 207.5 Q 143.75 210.25 138.5 209 L 138.5 208 Q 145.46 210.25 144.5 205 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 367.5 205 L 368 208 L 373 208.5 L 368.5 209 Q 366 208 367.5 205 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 352.5 207 L 353 215.5 L 352 215.5 L 352.5 207 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 159.5 208 L 160 216.5 L 159 216.5 L 159.5 208 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 128 221 Q 133.28 224.38 144.5 223 L 146 224.5 L 146 229.5 L 149 241.5 L 157 260.5 Q 167.89 280.11 184.5 294 L 200 304 L 200 343.5 Q 197.75 351.25 192.5 356 L 188.5 359 Q 160.71 333.29 145 295.5 L 133 259.5 L 129 237.5 L 128 221 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 160.5 221 L 161 223.5 L 160 223.5 L 160.5 221 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 351.5 221 L 352 223.5 L 351 223.5 L 351.5 221 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 382.5 221 L 385 221.5 L 385 505 L 368 505 L 368 494.5 L 367 493.5 L 367 477.5 L 366 476.5 L 366 467.5 L 365 466.5 L 363 447.5 L 355 412.5 L 350 396.5 L 344 362.5 L 344 276.5 Q 353.89 264.89 360 249.5 L 366 229.5 L 366 224.5 L 367.5 223 Q 377.83 224.83 382.5 221 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 230.5 232 L 234 232.5 L 230.5 233 L 230.5 232 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 278.5 232 L 282 232.5 L 278.5 233 L 278.5 232 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 238.5 235 L 239.5 237 L 238.5 235 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 273.5 235 L 272.5 237 L 273.5 235 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 224.5 238 L 225 241.5 L 224 241.5 L 224.5 238 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 287.5 238 L 288 241.5 L 287 241.5 L 287.5 238 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 246.5 239 L 249 239.5 L 246.5 240 L 246.5 239 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 263.5 239 L 266 239.5 L 263.5 240 L 263.5 239 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 252.5 240 L 260 240.5 L 252.5 241 L 252.5 240 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 229.5 247 L 230.5 249 L 229.5 247 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 282.5 247 L 281.5 249 L 282.5 247 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 251.5 255 L 261 255.5 L 251.5 256 L 251.5 255 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 173.5 258 L 174.5 260 L 173.5 258 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 338.5 258 L 337.5 260 L 338.5 258 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 175.5 261 L 176.5 263 L 175.5 261 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 336.5 261 L 335.5 263 L 336.5 261 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 177.5 264 L 178.5 266 L 177.5 264 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 334.5 264 L 333.5 266 L 334.5 264 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 180.5 268 L 183.5 272 L 180.5 268 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 330.5 269 L 328.5 272 L 330.5 269 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 192.5 280 L 194.5 283 L 192.5 280 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 319.5 280 L 317.5 283 L 319.5 280 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 198.5 285 L 199.5 287 L 198.5 285 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 201.5 287 L 202.5 289 L 201.5 287 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 310.5 287 L 309.5 289 L 310.5 287 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 204.5 289 L 205.5 291 L 204.5 289 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 307.5 289 L 306.5 291 L 307.5 289 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 328.5 293 L 329 361.5 L 330 362.5 L 330 371.5 L 334 393.5 L 344 430.5 L 342 429.5 L 321 391.5 L 314 370.5 L 313 359.5 L 312 358.5 L 312 304 L 328.5 293 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 240.5 303 L 244 303.5 L 240.5 304 L 240.5 303 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 268.5 303 L 272 303.5 L 268.5 304 L 268.5 303 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 249.5 304 L 263 304.5 L 249.5 305 L 249.5 304 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 215 312 L 217 312.5 L 216 313.5 L 215.5 341 L 215 312 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 295.5 312 L 297 312.5 L 297 351.5 L 296 351.5 L 296 313.5 L 295.5 312 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 228.5 316 L 231 316.5 L 228.5 317 L 228.5 316 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 281.5 316 L 284 316.5 L 281.5 317 L 281.5 316 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 238.5 318 L 242 318.5 L 238.5 319 L 238.5 318 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 270.5 318 L 274 318.5 L 270.5 319 L 270.5 318 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 246.5 319 L 266 319.5 L 246.5 320 L 246.5 319 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 297.5 358 L 298 361.5 L 297 361.5 L 297.5 358 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 207.5 361 L 205.5 364 L 207.5 361 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 298.5 366 L 299 368.5 L 298 368.5 L 298.5 366 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 201.5 367 L 199.5 370 L 201.5 367 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 197.5 370 L 196.5 372 L 197.5 370 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 300.5 375 L 301 377.5 L 300 377.5 L 300.5 375 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 182.5 378 L 185.5 382 L 182.5 378 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 166.5 383 L 177.5 395 L 166.5 383 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 190.5 385 L 191.5 387 L 190.5 385 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 194.5 388 L 195.5 390 L 194.5 388 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 200.5 392 L 201.5 394 L 200.5 392 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 152.5 393 L 155.5 397 L 152.5 393 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 136.5 397 L 137.5 399 L 136.5 397 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 181.5 397 L 182.5 399 L 181.5 397 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 185.5 400 L 186.5 402 L 185.5 400 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 140.5 402 L 143.5 406 L 140.5 402 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 163.5 404 L 166.5 408 L 163.5 404 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 98.5 407 L 97.5 409 L 98.5 407 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 239.5 407 L 243 407.5 L 239.5 408 L 239.5 407 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 270.5 407 L 273 407.5 L 270.5 408 L 270.5 407 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 247.5 408 L 264 408.5 L 247.5 409 L 247.5 408 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 94.5 410 L 93.5 412 L 94.5 410 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 417.5 410 L 418.5 412 L 417.5 410 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 422.5 415 L 424.5 418 L 422.5 415 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 88.5 416 L 87.5 418 L 88.5 416 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 154.5 416 L 157.5 420 L 154.5 416 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 178.5 416 L 179.5 418 L 178.5 416 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 181.5 418 L 182.5 420 L 181.5 418 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 232.5 421 L 235 421.5 L 232.5 422 L 232.5 421 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 277.5 421 L 280 421.5 L 277.5 422 L 277.5 421 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 161.5 422 L 162.5 424 L 161.5 422 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 238.5 422 L 241 422.5 L 238.5 423 L 238.5 422 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 271.5 422 L 274 422.5 L 271.5 423 L 271.5 422 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 247.5 423 L 265 423.5 L 247.5 424 L 247.5 423 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 79.5 435 L 80 437.5 L 79 437.5 L 79.5 435 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 305.5 435 L 306.5 437 L 305.5 435 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 432.5 435 L 433 438.5 L 432 438.5 L 432.5 435 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 236.5 439 L 239 439.5 L 236.5 440 L 236.5 439 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 272.5 439 L 275 439.5 L 272.5 440 L 272.5 439 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 245.5 440 L 254 440.5 L 245.5 441 L 245.5 440 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 259.5 440 L 267 440.5 L 259.5 441 L 259.5 440 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 229.5 453 L 232 453.5 L 229.5 454 L 229.5 453 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 236.5 454 L 239 454.5 L 236.5 455 L 236.5 454 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 273.5 454 L 276 454.5 L 273.5 455 L 273.5 454 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 245.5 455 L 266 455.5 L 245.5 456 L 245.5 455 Z " /><path fill="rgb(159,103,87)" stroke="rgb(159,103,87)" stroke-width="1" opacity="1" d="M 352.5 495 L 353 502.5 L 352 502.5 L 352.5 495 Z " /></svg>';
        }
 
        // ─── МУЖЧИНА ───
        if ($gender === 'male') {
            return '<svg width="100%" height="100%" viewBox="0 0 512 512" version="1.1" xmlns="http://www.w3.org/2000/svg" desc="Created with imagetracer.js version 1.2.6" ><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0" d="M 0 0 L 512 0 L 512 512 L 0 512 L 0 0 Z M 152 18 L 147 20 L 146 23 L 146 34 L 150 47 L 163 65 L 157 69 Q 141 81 136 103 Q 137 107 135 109 L 135 171 L 129 174 Q 121 179 119 190 L 119 206 L 125 220 Q 131 228 141 232 Q 148 236 159 234 Q 167 261 184 280 L 205 300 L 202 335 L 109 357 Q 95 361 86 369 L 73 384 L 67 398 L 67 403 L 72 406 L 70 419 L 70 427 L 70 429 L 65 430 L 62 434 L 59 469 L 58 470 L 58 479 L 57 480 L 57 490 L 63 494 L 68 493 Q 71 493 70 489 L 72 487 L 201 487 L 202 491 L 207 494 L 212 493 Q 216 492 215 487 L 297 487 L 297 490 L 303 494 L 308 493 Q 311 493 310 489 L 312 487 L 441 487 L 442 491 L 447 494 Q 453 494 455 490 L 455 480 L 453 470 L 453 461 L 451 451 L 451 442 L 449 432 L 449 424 L 448 423 L 447 406 Q 444 387 433 376 Q 422 363 404 357 L 310 335 L 307 300 L 313 295 Q 341 272 353 234 L 366 234 L 379 228 Q 389 220 393 206 Q 395 187 388 179 Q 384 173 377 171 L 377 119 L 376 118 L 376 110 L 374 99 Q 368 73 354 57 L 348 52 L 343 54 Q 336 46 326 42 Q 328 40 327 36 L 324 32 L 308 25 Q 299 23 298 29 L 281 25 Q 276 27 277 23 L 272 18 L 152 18 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 151.5 18 L 271.5 18 L 277 22.5 Q 278.04 29.04 274.5 31 L 272.5 32 L 160 32 Q 163.39 45.37 172.5 54 L 183 61.5 L 184 67.5 L 181.5 71 Q 165.75 76.25 157 88.5 Q 151.29 96.29 149 107.5 L 149 170 L 155.5 171 Q 170.04 160.54 178 143.5 L 181 131.5 L 183 108.5 L 186.5 103 L 191.5 102 L 207.5 106 L 231.5 108 L 232.5 109 L 279.5 109 L 280.5 108 L 304.5 106 L 322.5 102 L 328 105.5 L 330 123.5 L 331 124.5 L 331 131.5 L 334 143.5 Q 341.96 160.54 356.5 171 L 363 170 L 363 116.5 L 362 115.5 L 361 103.5 L 355 84.5 Q 350.34 72.66 342 64.5 L 340 57.5 L 343.5 53 L 347.5 52 L 354 56.5 Q 368.29 73.21 374 98.5 L 376 109.5 L 376 117.5 L 377 118.5 L 377 171 Q 383.93 172.85 388 178.5 Q 395.21 187.29 393 205.5 Q 389.45 220.45 378.5 228 L 365.5 234 L 353 234 Q 340.89 272.01 312.5 295 L 307 299.5 L 310 335 L 403.5 357 Q 421.99 362.51 433 375.5 Q 443.57 386.93 447 405.5 L 448 422.5 L 449 423.5 L 449 431.5 L 451 441.5 L 451 450.5 L 453 460.5 L 453 469.5 L 455 479.5 L 455 489.5 Q 452.92 493.92 446.5 494 L 442 490.5 L 433 405.5 Q 428.6 387.4 415.5 378 L 402.5 371 L 345.5 358 L 346 359.5 L 362 439.5 L 357.5 445 L 352.5 445 L 310 430 L 310 486.5 L 311 487.5 L 307.5 493 L 302.5 494 L 297 489.5 L 296.5 377 Q 288.45 386.95 275.5 392 L 263.5 395 L 247.5 395 Q 226.77 390.73 215.5 377 L 215 489.5 L 211.5 493 L 206.5 494 L 202 490.5 L 201 487.5 L 202 486.5 L 202 430 L 159.5 445 L 154.5 445 Q 150.45 443.05 150 437.5 L 167 358.5 L 163.5 358 L 109.5 371 Q 95.33 376.33 87 387.5 L 79 405 L 72.5 407 L 67 402.5 L 67 397.5 L 73 383.5 L 85.5 369 Q 95.03 361.03 108.5 357 L 202 335 L 205 299.5 L 184 279.5 Q 166.92 260.78 159 234 Q 147.53 235.63 140.5 232 Q 130.84 227.66 125 219.5 L 119 205.5 L 119 189.5 Q 121.36 179.36 128.5 174 L 135 171 L 135 108.5 Q 137.25 107.25 136 102.5 Q 141.38 80.88 156.5 69 L 163 64.5 L 150 46.5 L 146 33.5 L 146 22.5 L 147 20 L 151.5 18 Z M 196 117 L 193 141 L 189 153 Q 179 170 164 182 L 164 196 L 165 197 L 166 210 L 173 235 Q 183 259 200 277 L 223 295 L 237 303 L 251 307 L 262 307 L 276 303 Q 296 293 311 278 Q 327 262 337 240 L 345 215 L 347 197 L 348 196 L 348 182 Q 332 170 323 153 L 319 141 L 316 117 L 295 121 L 270 122 L 269 123 L 228 122 L 227 121 L 218 121 L 210 119 L 204 119 L 196 117 Z M 142 183 L 139 184 L 134 189 L 132 196 Q 132 207 137 213 Q 143 221 155 221 L 152 208 L 150 185 Q 148 181 142 183 Z M 364 183 L 361 189 L 361 201 L 357 221 Q 365 221 371 217 Q 378 212 380 201 L 380 196 L 378 189 Q 375 182 364 183 Z M 219 308 L 217 313 L 217 324 L 216 325 L 216 335 L 215 336 Q 214 356 224 367 L 237 377 L 253 382 L 266 381 L 282 373 Q 293 364 297 347 L 295 313 L 294 308 Q 282 317 266 320 L 247 320 L 242 319 Q 228 315 219 308 Z M 201 349 L 181 354 L 166 428 L 194 419 L 202 415 L 202 351 L 201 349 Z M 310 350 L 310 415 L 314 417 L 346 428 L 331 354 L 316 350 L 310 350 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 301.5 25 Q 318.63 25.87 327 35.5 Q 328.04 42.04 324.5 44 L 317.5 45 Q 311.05 39.95 301.5 38 L 298 34.5 Q 296.5 26.5 301.5 25 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 211.5 157 Q 230.88 155.63 239 165.5 L 240 169.5 L 237.5 174 L 229.5 175 Q 224.5 169 211.5 171 L 204.5 175 L 198.5 175 L 196 174 L 194 168.5 L 198.5 162 L 211.5 157 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 291.5 157 Q 309.01 156.49 317 165.5 L 318 169.5 L 314.5 175 L 311.5 176 L 300.5 171 L 289.5 171 L 282.5 175 L 276.5 175 L 274 174 Q 272.25 171.63 273 165.5 L 280.5 160 L 291.5 157 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 213.5 184 Q 222.53 182.47 225 187.5 L 226 196.5 L 224 200 Q 221.07 202.86 213.5 202 L 210 200 L 208 196.5 L 208 189.5 L 211.5 185 L 213.5 184 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 291.5 184 Q 300.53 182.47 303 187.5 L 304 196.5 L 302 200 L 298.5 202 L 292.5 202 L 288 200 L 286 196.5 L 286 189.5 L 289.5 185 L 291.5 184 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 228.5 245 L 231.5 245 L 244.5 250 L 261.5 251 L 283.5 245 L 288 248.5 Q 289.25 255.75 285.5 258 L 265.5 264 L 246.5 264 L 233.5 261 L 225 256.5 L 224 248.5 L 228.5 245 Z " /><path fill="rgb(0,0,0)" stroke="rgb(0,0,0)" stroke-width="1" opacity="0.99" d="M 66.5 429 Q 71.94 428.38 74 430 L 76 433.5 L 76 438.5 L 75 439.5 L 75 448.5 L 74 449.5 L 74 457.5 L 72 467.5 L 71 487.5 L 67.5 493 L 62.5 494 L 57 489.5 L 57 479.5 L 58 478.5 L 58 469.5 L 59 468.5 L 62 433.5 L 64.5 430 L 66.5 429 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 278.5 25 L 291.5 27 L 297 29.5 Q 296.12 36.37 300.5 38 L 320.5 46 L 327.5 42 L 342 53.5 Q 338.8 55.8 340 62.5 Q 349.43 72.07 355 85.5 L 361 105.5 L 362 121.5 L 363 122.5 L 363 168.5 L 357.5 171 L 340 153.5 L 334 142.5 L 331 127.5 L 329 106.5 L 324.5 102 L 318.5 102 L 307.5 105 L 286.5 107 L 285.5 108 L 241.5 109 L 240.5 108 L 218.5 107 L 193.5 102 L 187.5 102 L 183 106.5 L 181 127.5 L 178 142.5 L 172 153.5 L 154.5 171 L 149 168.5 L 149 111.5 Q 151.25 110.25 150 105.5 Q 154.09 89.09 165.5 80 L 180.5 72 L 184 68.5 Q 185.5 59.5 179.5 58 L 169 49.5 Q 162.07 42.75 161 32 L 273.5 32 L 277 28.5 L 278.5 25 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 212.5 120 L 215 120.5 L 212.5 121 L 212.5 120 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 297.5 120 L 300 120.5 L 297.5 121 L 297.5 120 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 220.5 121 L 223 121.5 L 220.5 122 L 220.5 121 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 289.5 121 L 292 121.5 L 289.5 122 L 289.5 121 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 231.5 122 L 235 122.5 L 231.5 123 L 231.5 122 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 277.5 122 L 281 122.5 L 277.5 123 L 277.5 122 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 195.5 123 L 196 125.5 L 195 125.5 L 195.5 123 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 316.5 123 L 317 125.5 L 316 125.5 L 316.5 123 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 184.5 160 L 183.5 162 L 184.5 160 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 327.5 160 L 328.5 162 L 327.5 160 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 236.5 162 L 238.5 165 L 236.5 162 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 314.5 162 L 316.5 165 L 314.5 162 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 274.5 163 L 273.5 165 L 274.5 163 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 181.5 164 L 174.5 172 L 181.5 164 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 330.5 164 L 338.5 173 L 330.5 164 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 238.5 173 L 237.5 175 L 238.5 173 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 316.5 173 L 315.5 175 L 316.5 173 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 143.5 183 L 146 183.5 L 143.5 184 L 143.5 183 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 366.5 183 L 369 183.5 L 366.5 184 L 366.5 183 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 211.5 184 L 209.5 187 L 211.5 184 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 300.5 184 L 302.5 187 L 300.5 184 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 137.5 185 L 135.5 188 L 137.5 185 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 223.5 185 L 224.5 187 L 223.5 185 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 288.5 185 L 287.5 187 L 288.5 185 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 374.5 185 L 376.5 188 L 374.5 185 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 164.5 186 L 165 191.5 L 164 191.5 L 164.5 186 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 347.5 186 L 348 191.5 L 347 191.5 L 347.5 186 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 361.5 195 L 362 197.5 L 361 197.5 L 361.5 195 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 165.5 199 L 166 201.5 L 165 201.5 L 165.5 199 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 346.5 199 L 347 201.5 L 346 201.5 L 346.5 199 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 225.5 246 L 224.5 248 L 225.5 246 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 286.5 246 L 287.5 248 L 286.5 246 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 224.5 256 L 225.5 258 L 224.5 256 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 287.5 256 L 286.5 258 L 287.5 256 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 190.5 264 L 191.5 266 L 190.5 264 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 249.5 264 L 263 264.5 L 249.5 265 L 249.5 264 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 195.5 270 L 198.5 274 L 195.5 270 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 316.5 270 L 314.5 273 L 316.5 270 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 206.5 281 L 208.5 284 L 206.5 281 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 305.5 281 L 303.5 284 L 305.5 281 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 213.5 287 L 214.5 289 L 213.5 287 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 291.5 292 L 290.5 294 L 291.5 292 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 253.5 306 L 259 306.5 L 253.5 307 L 253.5 306 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 217.5 317 L 218 319.5 L 217 319.5 L 217.5 317 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 294.5 317 L 295 319.5 L 294 319.5 L 294.5 317 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 248.5 320 L 251 320.5 L 248.5 321 L 248.5 320 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 261.5 320 L 264 320.5 L 261.5 321 L 261.5 320 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 216.5 328 L 217 331.5 L 216 331.5 L 216.5 328 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 295.5 328 L 296 331.5 L 295 331.5 L 295.5 328 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 222.5 363 L 223.5 365 L 222.5 363 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 289.5 363 L 288.5 365 L 289.5 363 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 226.5 368 L 228.5 371 L 226.5 368 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 285.5 368 L 283.5 371 L 285.5 368 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 95.5 379 L 93.5 382 L 95.5 379 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 416.5 379 L 418.5 382 L 416.5 379 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 90.5 384 L 89.5 386 L 90.5 384 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 421.5 384 L 422.5 386 L 421.5 384 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 435.5 433 L 436 435.5 L 435 435.5 L 435.5 433 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 155.5 445 L 159 445.5 L 155.5 446 L 155.5 445 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 353.5 445 L 357 445.5 L 353.5 446 L 353.5 445 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 74.5 452 L 75 454.5 L 74 454.5 L 74.5 452 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 437.5 452 L 438 454.5 L 437 454.5 L 437.5 452 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 71.5 480 L 72 482.5 L 71 482.5 L 71.5 480 Z " /><path fill="rgb(203,144,109)" stroke="rgb(203,144,109)" stroke-width="1" opacity="1" d="M 440.5 480 L 441 482.5 L 440 482.5 L 440.5 480 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 214.5 156 L 220 156.5 L 214.5 157 L 214.5 156 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 139.5 184 L 150 184 L 150 196.5 L 154 220 Q 142.17 219.62 137 211.5 L 133 202.5 L 133 193.5 Q 134.54 187.04 139.5 184 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 362 184 L 372.5 184 L 377 188.5 Q 380.29 193.21 379 202.5 Q 376.87 211.37 370.5 216 Q 366.23 220.83 358 220 L 362 197.5 L 362 184 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 218 309 L 239.5 319 L 248.5 321 L 263.5 321 Q 281.84 317.64 294 309 L 295 331.5 L 296 332.5 L 296 348.5 Q 292.12 364.62 280.5 373 L 266.5 380 L 250.5 381 Q 233 377.5 224 365.5 Q 218.32 358.68 216 348.5 L 216 332.5 L 217 331.5 L 218 309 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 199.5 350 L 201 350.5 L 201 414.5 L 194.5 418 L 167 427 L 182 354 L 186.5 354 L 199.5 350 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 311 350 L 330 354 L 345 427 L 311 414.5 L 311 350 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 92.5 382 L 91.5 384 L 92.5 382 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 419.5 382 L 420.5 384 L 419.5 382 Z " /><path fill="rgb(254,194,118)" stroke="rgb(254,194,118)" stroke-width="1" opacity="1" d="M 79.5 404 L 78.5 406 L 79.5 404 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 297.5 29 L 298 33.5 L 297 33.5 L 297.5 29 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 298.5 35 L 300.5 38 L 298.5 35 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 167.5 47 L 168.5 49 L 167.5 47 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 170.5 51 L 172.5 54 L 170.5 51 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 176.5 56 L 177.5 58 L 176.5 56 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 179.5 58 L 180.5 60 L 179.5 58 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 340.5 61 L 342 63.5 L 340 62.5 L 340.5 61 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 343.5 66 L 344.5 68 L 343.5 66 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 346.5 70 L 347.5 72 L 346.5 70 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 167.5 78 L 166.5 80 L 167.5 78 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 159.5 86 L 157.5 89 L 159.5 86 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 156.5 90 L 155.5 92 L 156.5 90 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 202.5 104 L 205 104.5 L 202.5 105 L 202.5 104 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 207.5 105 L 211 105.5 L 207.5 106 L 207.5 105 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 301.5 105 L 305 105.5 L 301.5 106 L 301.5 105 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 214.5 106 L 218 106.5 L 214.5 107 L 214.5 106 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 295.5 106 L 298 106.5 L 295.5 107 L 295.5 106 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 149.5 107 L 150 110.5 L 149 110.5 L 149.5 107 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 222.5 107 L 227 107.5 L 222.5 108 L 222.5 107 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 286.5 107 L 290 107.5 L 286.5 108 L 286.5 107 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 232.5 108 L 241 108.5 L 232.5 109 L 232.5 108 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 272.5 108 L 280 108.5 L 272.5 109 L 272.5 108 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 361.5 108 L 362 110.5 L 361 110.5 L 361.5 108 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 182.5 112 L 183 115.5 L 182 115.5 L 182.5 112 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 329.5 112 L 330 115.5 L 329 115.5 L 329.5 112 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 362.5 116 L 363 121.5 L 362 121.5 L 362.5 116 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 207.5 119 L 210 119.5 L 207.5 120 L 207.5 119 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 302.5 119 L 305 119.5 L 302.5 120 L 302.5 119 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 181.5 120 L 182 123.5 L 181 123.5 L 181.5 120 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 330.5 120 L 331 123.5 L 330 123.5 L 330.5 120 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 223.5 121 L 227 121.5 L 223.5 122 L 223.5 121 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 285.5 121 L 289 121.5 L 285.5 122 L 285.5 121 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 235.5 122 L 243 122.5 L 235.5 123 L 235.5 122 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 269.5 122 L 277 122.5 L 269.5 123 L 269.5 122 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 180.5 128 L 181 131.5 L 180 131.5 L 180.5 128 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 194.5 128 L 195 130.5 L 194 130.5 L 194.5 128 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 317.5 128 L 318 130.5 L 317 130.5 L 317.5 128 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 331.5 128 L 332 131.5 L 331 131.5 L 331.5 128 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 179.5 135 L 180 137.5 L 179 137.5 L 179.5 135 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 332.5 135 L 333 137.5 L 332 137.5 L 332.5 135 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 173.5 151 L 172.5 153 L 173.5 151 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 338.5 151 L 339.5 153 L 338.5 151 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 170.5 155 L 155.5 171 L 170.5 155 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 341.5 155 L 356.5 171 L 341.5 155 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 288.5 157 L 291 157.5 L 288.5 158 L 288.5 157 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 326.5 158 L 327.5 160 L 326.5 158 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 272.5 167 L 273 170.5 L 272 170.5 L 272.5 167 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 173.5 172 L 164.5 182 L 173.5 172 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 339.5 173 L 347.5 182 L 339.5 173 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 199.5 175 L 203 175.5 L 199.5 176 L 199.5 175 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 231.5 175 L 235 175.5 L 231.5 176 L 231.5 175 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 277.5 175 L 281 175.5 L 277.5 176 L 277.5 175 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 146.5 183 L 149 183.5 L 146.5 184 L 146.5 183 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 214.5 183 L 220 183.5 L 214.5 184 L 214.5 183 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 292.5 183 L 298 183.5 L 292.5 184 L 292.5 183 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 363.5 183 L 366 183.5 L 363.5 184 L 363.5 183 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 150.5 187 L 151 194.5 L 150 194.5 L 150.5 187 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 361.5 188 L 362 194.5 L 361 194.5 L 361.5 188 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 207.5 191 L 208 194.5 L 207 194.5 L 207.5 191 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 226.5 191 L 227 193.5 L 226 193.5 L 226.5 191 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 285.5 191 L 286 194.5 L 285 194.5 L 285.5 191 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 304.5 191 L 305 194.5 L 304 194.5 L 304.5 191 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 164.5 192 L 165 195.5 L 164 195.5 L 164.5 192 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 347.5 192 L 348 195.5 L 347 195.5 L 347.5 192 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 132.5 195 L 133 200.5 L 132 200.5 L 132.5 195 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 379.5 195 L 380 200.5 L 379 200.5 L 379.5 195 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 208.5 197 L 209.5 199 L 208.5 197 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 303.5 197 L 302.5 199 L 303.5 197 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 151.5 201 L 152 203.5 L 151 203.5 L 151.5 201 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 360.5 201 L 361 203.5 L 360 203.5 L 360.5 201 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 359.5 208 L 360 210.5 L 359 210.5 L 359.5 208 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 137.5 212 L 141.5 217 L 137.5 212 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 374.5 212 L 370.5 217 L 374.5 212 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 154.5 218 L 155 221 L 151 220.5 L 154 220 L 154.5 218 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 357.5 219 L 361 220.5 L 357 221 L 357.5 219 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 223.5 250 L 224 253.5 L 223 253.5 L 223.5 250 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 250.5 250 L 262 250.5 L 250.5 251 L 250.5 250 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 288.5 250 L 289 253.5 L 288 253.5 L 288.5 250 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 322.5 263 L 321.5 265 L 322.5 263 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 192.5 267 L 194.5 270 L 192.5 267 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 319.5 267 L 317.5 270 L 319.5 267 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 200.5 276 L 202.5 279 L 200.5 276 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 311.5 276 L 310.5 278 L 311.5 276 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 209.5 284 L 210.5 286 L 209.5 284 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 302.5 284 L 300.5 287 L 302.5 284 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 297.5 288 L 296.5 290 L 297.5 288 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 218.5 291 L 219.5 293 L 218.5 291 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 293.5 291 L 292.5 293 L 293.5 291 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 221.5 293 L 222.5 295 L 221.5 293 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 250.5 306 L 253 306.5 L 250.5 307 L 250.5 306 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 259.5 306 L 262 306.5 L 259.5 307 L 259.5 306 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 217.5 312 L 218 316.5 L 217 316.5 L 217.5 312 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 294.5 312 L 295 316.5 L 294 316.5 L 294.5 312 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 251.5 320 L 261 320.5 L 251.5 321 L 251.5 320 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 216.5 324 L 217 327.5 L 216 327.5 L 216.5 324 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 295.5 324 L 296 327.5 L 295 327.5 L 295.5 324 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 215.5 335 L 216 339.5 L 215 339.5 L 215.5 335 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 296.5 335 L 297 339.5 L 296 339.5 L 296.5 335 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 215.5 342 L 216 346.5 L 215 346.5 L 215.5 342 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 296.5 342 L 297 346.5 L 296 346.5 L 296.5 342 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 200.5 349 L 202 350.5 L 202 414.5 L 201 414.5 L 201 350.5 L 200.5 349 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 310.5 350 L 311 414.5 L 310 414.5 L 310.5 350 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 288.5 365 L 286.5 368 L 288.5 365 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 224.5 366 L 225.5 368 L 224.5 366 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 229.5 371 L 230.5 373 L 229.5 371 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 282.5 371 L 281.5 373 L 282.5 371 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 215.5 377 L 220.5 383 L 216 379.5 L 215.5 487 L 215.5 377 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 296.5 377 L 297 486.5 L 296 486.5 L 295.5 379 L 292 381.5 L 296.5 377 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 252.5 381 L 260 381.5 L 252.5 382 L 252.5 381 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 223.5 384 L 224.5 386 L 223.5 384 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 288.5 384 L 287.5 386 L 288.5 384 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 88.5 386 L 87.5 388 L 88.5 386 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 226.5 386 L 227.5 388 L 226.5 386 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 285.5 386 L 284.5 388 L 285.5 386 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 423.5 386 L 424.5 388 L 423.5 386 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 264.5 394 L 267 394.5 L 264.5 395 L 264.5 394 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 252.5 395 L 260 395.5 L 252.5 396 L 252.5 395 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 433.5 411 L 434 414.5 L 433 414.5 L 433.5 411 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 434.5 420 L 435 423.5 L 434 423.5 L 434.5 420 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 166.5 426 L 167.5 428 Q 164.71 428.67 166.5 426 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 345.5 426 L 346 428 L 344 427.5 L 345.5 426 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 200.5 430 L 202 430.5 L 202 486.5 L 201 486.5 L 201 431.5 L 200.5 430 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 310 430 L 312 430.5 L 311 431.5 L 310.5 487 L 310 430 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 435.5 430 L 436 432.5 L 435 432.5 L 435.5 430 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 75.5 439 L 76 442.5 L 75 442.5 L 75.5 439 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 436.5 439 L 437 442.5 L 436 442.5 L 436.5 439 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 151.5 442 L 153.5 445 L 151.5 442 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 360.5 442 L 358.5 445 L 360.5 442 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 74.5 449 L 75 451.5 L 74 451.5 L 74.5 449 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 437.5 449 L 438 451.5 L 437 451.5 L 437.5 449 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 73.5 458 L 74 461.5 L 73 461.5 L 73.5 458 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 438.5 458 L 439 461.5 L 438 461.5 L 438.5 458 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 72.5 467 L 73 470.5 L 72 470.5 L 72.5 467 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 439.5 467 L 440 470.5 L 439 470.5 L 439.5 467 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 71.5 477 L 72 479.5 L 71 479.5 L 71.5 477 Z " /><path fill="rgb(106,76,55)" stroke="rgb(106,76,55)" stroke-width="1" opacity="1" d="M 440.5 477 L 441 479.5 L 440 479.5 L 440.5 477 Z " /><path fill="rgb(254,225,124)" stroke="rgb(254,225,124)" stroke-width="1" opacity="1" d="M 151.5 443 L 152.5 445 L 151.5 443 Z " /><path fill="rgb(254,225,124)" stroke="rgb(254,225,124)" stroke-width="1" opacity="1" d="M 360.5 443 L 359.5 445 L 360.5 443 Z " /><path fill="rgb(251,207,162)" stroke="rgb(251,207,162)" stroke-width="1" opacity="1" d="M 196 118 L 220.5 122 L 230.5 122 L 231.5 123 L 280.5 123 L 281.5 122 L 291.5 122 L 316 118 L 317 133.5 L 321 149.5 L 330 164.5 L 348 182.5 L 348 184.5 L 347 185.5 L 347 198.5 L 346 199.5 L 345 211.5 L 337 237.5 Q 326.24 262.74 307.5 280 Q 292.08 294.58 271.5 304 L 263.5 306 L 248.5 306 Q 218.62 296.38 200 275.5 Q 182.53 257.97 173 232.5 L 167 211.5 L 164 182.5 Q 181.25 169.75 191 149.5 L 195 133.5 L 196 118 Z M 215 156 L 203 159 L 194 166 Q 193 172 195 174 L 199 176 L 204 176 L 215 171 Q 225 171 231 176 L 238 175 L 240 172 L 239 165 Q 231 156 215 156 Z M 288 157 L 276 162 L 272 167 L 272 172 L 277 176 L 282 176 L 293 171 Q 303 171 309 176 L 316 175 Q 319 173 318 166 Q 309 155 288 157 Z M 214 183 L 212 184 L 207 190 Q 206 197 209 200 L 215 203 L 222 202 L 227 196 Q 228 189 225 187 Q 223 181 214 183 Z M 292 183 L 290 184 L 285 191 L 286 198 L 293 203 L 298 203 L 303 200 L 305 196 L 304 188 Q 302 181 292 183 Z M 228 245 L 226 246 L 223 250 L 224 257 L 235 262 L 250 265 L 270 264 L 287 258 L 289 255 L 288 248 L 285 245 L 279 245 L 273 248 L 264 250 L 249 250 L 237 247 L 234 245 L 228 245 Z " /><path fill="rgb(255,225,125)" stroke="rgb(255,225,125)" stroke-width="1" opacity="1" d="M 162.5 359 L 166 359.5 L 150 433.5 L 150 441.5 L 152.5 445 L 159.5 446 L 200.5 431 L 201 487 L 71.5 487 L 71 485.5 L 72 484.5 L 72 475.5 L 73 474.5 L 73 466.5 L 74 465.5 L 74 456.5 L 75 455.5 L 77 434.5 L 73.5 429 L 69 427.5 L 71 422.5 L 70 419.5 L 72 411.5 L 72 409.5 L 73.5 407 Q 79 408 80 404.5 Q 84.53 388.03 96.5 379 L 112.5 371 L 145.5 364 L 162.5 359 Z " /><path fill="rgb(255,225,125)" stroke="rgb(255,225,125)" stroke-width="1" opacity="1" d="M 346.5 359 L 405.5 373 Q 416.81 378.19 424 387.5 L 432 404.5 L 436 446.5 L 437 447.5 L 437 455.5 L 438 456.5 L 439 474.5 L 440 475.5 L 440 484.5 L 441 487 L 311 487 L 311 431.5 L 312.5 432 L 352.5 446 L 357.5 446 L 361 443.5 Q 363.5 440 362 432.5 L 360 427.5 L 357 408.5 L 355 403.5 L 351 379.5 L 346.5 359 Z " /><path fill="rgb(229,100,109)" stroke="rgb(229,100,109)" stroke-width="1" opacity="1" d="M 216.5 379 Q 230.1 395.4 259.5 396 L 276.5 392 Q 287.82 387.32 295.5 379 L 296 487 L 216 487 L 216.5 379 Z " /></svg>';
        }
 
        // ─── НЕЙТРАЛЬНЫЙ (текущий) ───
        return '<svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" '
             . 'style="display:inline-block;vertical-align:middle;flex-shrink:0" aria-hidden="true">'
             . '<path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/>'
             . '<path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0'
             . 'C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52'
             . 's70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56'
             . 'A56.06,56.06,0,0,1,72,96Z"/></svg>';
    }
}

/**
 * Универсальный рендер аватара пользователя.
 *
 * Правила:
 *   1. Если есть avatar_url — показываем <img>.
 *   2. Если avatar_emoji содержит <svg — выводим как SVG.
 *   3. Если avatar_emoji содержит эмодзи — выводим как текст.
 *   4. Если пусто — используем default_avatar_svg().
 *
 * @param array  $user        Массив с ключами avatar_url, avatar_emoji
 * @param string $imgClass    CSS-класс для <img>
 * @param string $wrapClass   CSS-класс для обёртки-заглушки
 * @param string $emojiClass  CSS-класс для эмодзи
 * @return string HTML
 */
if (!function_exists('render_avatar')) {
    function render_avatar(
        array $user,
        string $imgClass = 'w-10 h-10 rounded-full object-cover',
        string $wrapClass = 'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center',
        string $emojiClass = 'text-xl leading-none'
    ): string {
        $url    = trim((string)($user['avatar_url']   ?? ''));
        $gender = isset($user['gender']) ? (string)$user['gender'] : null;

        // 1. Картинка из uploads
        if ($url !== '') {
            return '<img src="' . e($url) . '" class="' . e($imgClass) . '" alt="Аватар">';
        }

        // 2. SVG-заглушка по полу
        return '<span class="' . e($wrapClass) . '">' . default_avatar_svg($gender) . '</span>';
    }
}
// ==========================================================
//  CDN НАСТРОЙКИ (пока модуль выключен)
// ==========================================================

/**
 * Получить значение настройки CDN из БД.
 * Кешируется в статической переменной на время запроса.
 */
function cdn_get_setting(string $key, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $rows = db_all("SELECT setting_key, setting_value FROM cdn_settings");
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            // Таблица может отсутствовать — вернём дефолты
            $cache = [];
        }
    }

    return $cache[$key] ?? $default;
}

/**
 * Сохранить значение настройки CDN.
 */
function cdn_set_setting(string $key, string $value): bool
{
    try {
        db_exec(
            "INSERT INTO cdn_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
        return true;
    } catch (Throwable $e) {
        error_log('cdn_set_setting error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Включён ли CDN.
 */
function cdn_is_enabled(): bool
{
    return cdn_get_setting('cdn_enabled', '0') === '1';
}

/**
 * Получить CDN-домен (без схемы).
 */
function cdn_domain(): string
{
    return cdn_get_setting('cdn_domain', '');
}

/**
 * Преобразовать локальный URL в CDN-URL, если CDN включён.
 * Используется для ассетов, картинок из /uploads и т.п.
 */
function cdn_url(string $path): string
{
    if (!cdn_is_enabled()) {
        return $path;
    }

    $domain = cdn_domain();
    if ($domain === '') {
        return $path;
    }

    // Внешние ссылки не трогаем
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    // Только относительные пути к ассетам/загрузкам
    if (!preg_match('~^/(uploads|assets)/~', $path)) {
        return $path;
    }

    return 'https://' . $domain . $path;
}
if (!function_exists('tag_group_label')) {
    /**
     * Человекочитаемое название группы тегов.
     *
     * @param string|null $group  Значение tag_group из recipe_tags
     * @return string             Название на русском
     */
    function tag_group_label(?string $group): string {
        $map = [
            'cuisine'    => 'Кухня',
            'time'       => 'Время',
            'difficulty' => 'Сложность',
            'diet'       => 'Диета',
            'dietary'    => 'Питание',
            'meal'       => 'Приём пищи',
            'meal_type'  => 'Приём пищи',
            'occasion'   => 'Повод',
            'holiday'    => 'Праздник',
            'season'     => 'Сезон',
            'ingredient' => 'Ингредиент',
            'method'     => 'Способ приготовления',
            'equipment'  => 'Техника',
            'taste'      => 'Вкус',
            'spice'      => 'Специи',
            'feature'    => 'Особенность',
            'tag'        => 'Тег',
            'restriction'=> 'Ограничения',
        ];

        $key = trim((string)$group);
        if ($key === '') return '';

        return $map[mb_strtolower($key)] ?? $key;
    }
}
/* ============================================================
 *  ТЕГИ И КАТЕГОРИИ — ХЕЛПЕРЫ
 * ============================================================ */

if (!function_exists('get_tag_group_label')) {
    function get_tag_group_label(string $group): string {
        return [
            'cuisine'     => 'Кухня',
            'holiday'     => 'Повод',
            'diet'        => 'Тип питания',
            'restriction' => 'Ограничения',
            'season'      => 'Сезон',
            'time'        => 'Время готовки',
            'difficulty'  => 'Сложность',
        ][$group] ?? $group;
    }
}

if (!function_exists('get_tag_group_icon_svg')) {
    /**
     * Имя SVG-иконки для группы тегов.
     * Возвращает имя из icon-names.php или null, если иконки нет.
     *
     * ВАЖНО: все имена ниже ДОЛЖНЫ присутствовать в icon-names.php.
     * Если имени нет — вернём null, и group останется без иконки.
     */
    function get_tag_group_icon_svg(string $group): ?string {
        $map = [
            'cuisine'     => 'globe',
            'holiday'     => 'romantics',
            'diet'        => 'healthy',
            'restriction' => 'restrictions',
            'season'      => 'season',
            'time'        => 'time',
            'difficulty'  => 'diff-mid',
        ];

        $name = $map[$group] ?? null;
        if ($name === null) return null;

        // Проверим, что имя есть в icon-names.php
        static $available = null;
        if ($available === null) {
            $available = [];
            $file = ROOT_DIR . '/includes/icon-names.php';
            if (is_file($file)) {
                $fromFile = require $file;
                if (is_array($fromFile)) $available = $fromFile;
            }
        }

        return in_array($name, $available, true) ? $name : null;
    }
}

if (!function_exists('get_filter_tag_groups')) {
    /** Группы тегов, показываемые как фильтры (difficulty исключён). */
    function get_filter_tag_groups(): array {
        return ['cuisine', 'holiday', 'diet', 'restriction', 'season', 'time'];
    }
}

if (!function_exists('get_all_tags_by_group')) {
    /**
     * Все активные теги, сгруппированные по tag_group.
     * @return array<string, array<int,array>>
     */
    function get_all_tags_by_group(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $rows = db_all(
                "SELECT id, slug, name, icon_svg, tag_group, sort_order
                 FROM recipe_tags
                 WHERE is_active = 1
                 ORDER BY tag_group, sort_order, name"
            ) ?: [];
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['tag_group']][] = $r;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('get_all_categories')) {
    /** Все активные категории. */
    function get_all_categories(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $cache = db_all(
                "SELECT id, slug, name, icon_svg
                 FROM recipe_categories
                 WHERE is_active = 1
                 ORDER BY sort_order, name"
            ) ?: [];
        } catch (Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('get_recipe_tag_slugs')) {
    /** Slug'и тегов конкретного рецепта. */
    function get_recipe_tag_slugs(int $recipeId): array {
        try {
            $rows = db_all(
                "SELECT t.slug FROM recipe_tag_links l
                 JOIN recipe_tags t ON t.id = l.tag_id
                 WHERE l.recipe_id = ? AND t.is_active = 1",
                [$recipeId]
            );
            return array_column($rows ?: [], 'slug');
        } catch (Throwable $e) {
            return [];
        }
    }
}
if (!function_exists('normalize_recipe_ingredients')) {
    function normalize_recipe_ingredients(array $list): array
    {
        $knownUnits = ['г','кг','мл','л','шт','ч.л.','ст.л.','стакан','щепотка','пучок','зубчик','по вкусу'];
        $out = [];

        foreach ($list as $it) {
            if (is_string($it)) {
                $raw = trim($it);
                if ($raw === '') continue;
                if (preg_match('/^(.+?)\s+по\s+вкусу$/ui', $raw, $m)) {
                    $out[] = ['name' => trim($m[1]), 'amount' => '', 'unit' => 'по вкусу', 'is_optional' => true];
                    continue;
                }
                if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*([а-яa-z\.]+)?$/ui', $raw, $m)) {
                    $unit = trim($m[3] ?? '');
                    if ($unit !== '' && !in_array($unit, $knownUnits, true)) $unit = '';
                    $out[] = [
                        'name'   => trim($m[1]),
                        'amount' => str_replace(',', '.', $m[2]),
                        'unit'   => $unit ?: '',
                        'is_optional' => false,
                    ];
                    continue;
                }
                $out[] = ['name' => $raw, 'amount' => '', 'unit' => '', 'is_optional' => false];
                continue;
            }
            if (is_array($it)) {
                $name = trim((string)($it['name'] ?? ''));
                if ($name === '') continue;
                $amount = $it['amount'] ?? '';
                $unit   = trim((string)($it['unit'] ?? ''));
                
                if (is_string($amount) && preg_match('/^(\d+(?:[.,]\d+)?)\s*([а-яa-z\.]+)?$/ui', trim($amount), $m)) {
                    $amount = str_replace(',', '.', $m[1]);
                    if ($unit === '' && !empty($m[2])) $unit = trim($m[2]);
                }
                if (is_string($amount) && preg_match('/^по\s+вкусу$/ui', trim($amount))) {
                    $amount = '';
                    $unit   = 'по вкусу';
                }
                $out[] = [
                    'name'   => $name,
                    'amount' => (string)$amount,
                    'unit'   => $unit,
                    'is_optional' => !empty($it['is_optional']),
                ];
            }
        }
        return $out;
    }
}