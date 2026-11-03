<?php
/**
 * «Запомнить меня» — долгоживущая cookie с подписанным токеном.
 * Формат cookie: <user_id>.<expires>.<hmac>
 */
declare(strict_types=1);

const REMEMBER_COOKIE = 'cookai_remember';
const REMEMBER_DAYS   = 30;

/**
 * Выдать новую remember-cookie после успешного логина.
 */
function remember_me_issue(int $userId): void
{
    $expires = time() + REMEMBER_DAYS * 86400;
    $payload = $userId . '.' . $expires;
    $sig     = hash_hmac('sha256', $payload, remember_secret());
    $value   = $payload . '.' . $sig;

    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => BASE_PATH ?: '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Проверить remember-cookie и, если она валидна, залогинить пользователя.
 * Вызывается из config.php после session_start().
 */
function remember_me_try_login(): void
{
    // Уже залогинен — ничего не делаем
    if (!empty($_SESSION['user_id'])) {
        return;
    }
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $value = $_COOKIE[REMEMBER_COOKIE];
    $parts = explode('.', $value);
    if (count($parts) !== 3) {
        remember_me_clear();
        return;
    }
    [$uid, $expires, $sig] = $parts;

    if (!ctype_digit((string)$uid) || !ctype_digit((string)$expires)) {
        remember_me_clear();
        return;
    }
    if ((int)$expires < time()) {
        remember_me_clear();
        return;
    }

    $expected = hash_hmac('sha256', $uid . '.' . $expires, remember_secret());
    if (!hash_equals($expected, $sig)) {
        remember_me_clear();
        return;
    }

    // Проверим, что пользователь существует
    $user = db_one('SELECT id FROM users WHERE id = ?', [(int)$uid]);
    if (!$user) {
        remember_me_clear();
        return;
    }

    // Логиним
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$uid;
    if (function_exists('current_user_flush_cache')) {
        current_user_flush_cache();
    }

    // Продлеваем cookie ещё на 30 дней (скользящее окно)
    remember_me_issue((int)$uid);
}

/**
 * Удалить remember-cookie (logout, смена пароля, подозрительный токен).
 */
function remember_me_clear(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        setcookie(REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => BASE_PATH ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE]);
    }
}

function remember_secret(): string
{
    if (defined('REMEMBER_SECRET')) return REMEMBER_SECRET;
    if (defined('APP_KEY'))         return APP_KEY;
    if (defined('CAPTCHA_SECRET'))  return CAPTCHA_SECRET;

    // Фолбэк (не для прода)
    return 'change-me-remember-secret-' . __DIR__;
}