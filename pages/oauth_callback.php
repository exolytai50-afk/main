<?php
/**
 * OAuth callback (VK ID, Яндекс). Адрес: https://gigachef.ru/oauth_callback
 * Его нужно добавить в «Доверенный Redirect URL» VK ID и в «Callback URI» приложения Яндекс OAuth.
 *
 *  - профиль не заполнен (нет города/пола)  → register/step2
 *  - регистрация по тарифу Pro              → payment
 *  - иначе                                  → главная
 *  - ошибка                                 → login (с сообщением)
 */
require_once __DIR__ . '/../config/config.php';

/**
 * Переход на страницу НЕ через HTTP-редирект, а через маленькую HTML-страницу.
 * Редирект после перехода с другого сайта (vk.ru) браузер считает cross-site и
 * не отправляет cookie сессии с SameSite=Strict — пользователь оказывался на /login.
 * Навигация, начатая самой страницей нашего сайта, считается same-site.
 */
function oauth_cb_go(string $dest): void
{
    session_write_close();
    $h = htmlspecialchars($dest, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<meta http-equiv="refresh" content="0;url=' . $h . '">'
       . '<title>Вход…</title></head><body style="font-family:sans-serif;text-align:center;padding:3rem 1rem">'
       . '<p>Вход выполнен, перенаправляем…</p>'
       . '<p><a href="' . $h . '">Продолжить</a></p>'
       . '<script>location.replace(' . json_encode($dest, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>'
       . '</body></html>';
    exit;
}

function oauth_cb_fail(string $msg): void
{
    $_SESSION['oauth_error'] = $msg;
    oauth_cb_go(url('login'));
}

// Пользователь отказал в доступе или VK вернул ошибку
if (!empty($_GET['error'])) {
    oauth_cb_fail('Вход отменён или недоступен. Попробуйте ещё раз.');
}

$provider = oauth_detect_provider($_GET);
if ($provider === null) {
    oauth_cb_go(url('login'));
}

try {
    $res = oauth_handle_callback($provider, (string)$_GET['code'], $_GET);
} catch (Throwable $e) {
    error_log('OAuth callback exception: ' . $e->getMessage());
    oauth_cb_fail('Ошибка OAuth. Попробуйте ещё раз.');
}

if (empty($res['ok'])) {
    oauth_cb_fail($res['error'] ?? 'Ошибка авторизации');
}

$u    = current_user();
$plan = $_SESSION['oauth_plan'] ?? 'free';
unset($_SESSION['oauth_plan']);

if (!$u) {
    oauth_cb_fail('Не удалось получить профиль пользователя.');
}

// «Запомнить меня» — как при обычном входе (важно для webview-приложения)
if (function_exists('remember_me_issue') && !empty($u['id'])) {
    remember_me_issue((int)$u['id']);
}

if (empty($u['city']) || empty($u['gender'])) {
    $_SESSION['registration_step'] = 2;
    $_SESSION['registration_plan'] = $plan;
    $dest = url('register/step2');
} elseif ($plan === 'pro') {
    $dest = url('payment');
} else {
    $dest = url('');
}

oauth_cb_go($dest);
