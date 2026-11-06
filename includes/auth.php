<?php
/**
 * Аутентификация, сессии, проверка подписки
 */
declare(strict_types=1);

/**
 * Возвращает данные текущего пользователя.
 * Кеш — по user_id, чтобы после session_regenerate_id данные не «залипали».
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $uid = (int)$_SESSION['user_id'];
    if (!isset($GLOBALS['__current_user_cache'])) {
        $GLOBALS['__current_user_cache'] = [];
    }
    if (array_key_exists($uid, $GLOBALS['__current_user_cache'])) {
        return $GLOBALS['__current_user_cache'][$uid];
    }

    $u = db_one(
        'SELECT id, email, nickname, display_name, avatar_emoji, avatar_url,
                bio, role, points, city, birth_date, gender, phone, website, occupation,
                is_premium,
                created_at
         FROM users WHERE id = ?',
        [$uid]
    );

    if ($u) {
        $u['name'] = $u['display_name'];
        $u['is_premium'] = (int)($u['is_premium'] ?? 0);
    }

    return $GLOBALS['__current_user_cache'][$uid] = $u;
}

function current_user_flush_cache(): void
{
    $GLOBALS['__current_user_cache'] = [];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . url('login'));
        exit;
    }
}

/**
 * Проверяет, заполнен ли профиль пользователя
 */
function is_profile_completed(): bool
{
    $u = current_user();
    if (!$u) return false;

    return !empty($u['city']) && !empty($u['gender']);
}

/**
 * Редирект на шаг 2 регистрации, если профиль не заполнен
 */
function require_completed_profile(): void
{
    require_login();
    if (!is_profile_completed()) {
        header('Location: ' . url('register/step2'));
        exit;
    }
}

function auth_register(string $email, string $password, string $name, string $nickname = ''): array
{
    $email = mb_strtolower(trim($email));
    $nickname = trim($nickname);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный email'];
    }
    if (mb_strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Пароль должен быть не короче 6 символов'];
    }
    
    // Проверка никнейма (только буквы, цифры, подчеркивание, от 3 до 50)
    if (empty($nickname) || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $nickname)) {
        return ['ok' => false, 'error' => 'Никнейм должен быть от 3 до 50 символов (буквы, цифры, _)'];
    }

    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        return ['ok' => false, 'error' => 'Пользователь с таким email уже существует'];
    }
    if (db_one('SELECT id FROM users WHERE nickname = ?', [$nickname])) {
        return ['ok' => false, 'error' => 'Этот никнейм уже занят'];
    }

    $id = db_insert(
        'INSERT INTO users (email, nickname, password_hash, display_name) VALUES (?, ?, ?, ?)',
        [$email, $nickname, password_hash($password, PASSWORD_DEFAULT), $name ?: 'Кулинар']
    );

    db_exec('INSERT INTO user_stats (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id', [$id]);
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$id;
    current_user_flush_cache();

    return ['ok' => true, 'user_id' => (int)$id];
}

function auth_login(string $login, string $password, bool $remember = false): array
{
    $login = trim($login);

    if (empty($login) || empty($password)) {
        return ['ok' => false, 'error' => 'Заполните все поля'];
    }

    // Ищем по email ИЛИ по никнейму
    $user = db_one('SELECT * FROM users WHERE email = ? OR nickname = ?', [$login, $login]);

    if (!$user) {
        return ['ok' => false, 'error' => 'Пользователь не найден'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Неверный пароль'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    current_user_flush_cache();

    return ['ok' => true, 'user_id' => (int)$user['id'], 'email' => $user['email']];
}

function auth_logout(): void
{
    if (function_exists('remember_me_clear')) {
        remember_me_clear();
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function check_subscription(?string $email = null): bool
{
    $email ??= current_user()['email'] ?? null;
    if (!$email) return false;

    $sub = db_one(
        'SELECT status, trial_end_date, subscription_end_date
         FROM subscriptions WHERE user_email = ? ORDER BY id DESC LIMIT 1',
        [$email]
    );
    if (!$sub) return false;

    $today = date('Y-m-d');
    return match ($sub['status']) {
        'active' => $sub['subscription_end_date'] >= $today,
        'trial'  => $sub['trial_end_date'] >= $today,
        default  => false,
    };
}

function is_premium(?string $email = null): bool
{
    $u = current_user();
    if (!$u) return false;

    // 1) Ручная выдача из админки
    if ((int)($u['is_premium'] ?? 0) === 1) {
        return true;
    }

    // 2) Активная подписка
    return check_subscription($email ?? $u['email']);
}

function require_premium(): void
{
    require_login();
    if (!is_premium()) {
        http_response_code(403);
        json_response(['error' => 'Требуется подписка GigaChef Pro'], 403);
    }
}

function is_admin(): bool
{
    $u = current_user();
    if (!$u) return false;

    $admin_emails = [];
    if (defined('ADMIN_EMAILS') && is_array(ADMIN_EMAILS)) {
        $admin_emails = ADMIN_EMAILS;
    } elseif (getenv('ADMIN_EMAILS')) {
        $admin_emails = array_map('trim', explode(',', getenv('ADMIN_EMAILS')));
    }

    return in_array(mb_strtolower($u['email']), array_map('mb_strtolower', $admin_emails), true);
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Доступ запрещён');
    }
}

function require_admin_api(): void
{
    if (!is_logged_in()) {
        json_response(['error' => 'Требуется вход'], 401);
    }
    if (!is_admin()) {
        json_response(['error' => 'Доступ запрещён'], 403);
    }
}