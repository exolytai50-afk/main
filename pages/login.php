<?php
require_once __DIR__ . '/../config/config.php';
if (is_logged_in()) { header('Location: ' . url('')); exit; }

$error = '';
$success = '';

// Ошибка OAuth, переданная из /oauth_callback
if (!empty($_SESSION['oauth_error'])) {
    $error = (string)$_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

// ---------- Обычный вход ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Сессия истекла, попробуйте ещё раз.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $res = auth_login($email, $password, $remember);

        if ($res['ok']) {
            if ($remember && !empty($res['user_id'])) {
                remember_me_issue((int)$res['user_id']);
            } else {
                remember_me_clear();
            }

            // Если профиль не заполнен — на шаг 2
            $u = current_user();
            if (empty($u['city']) || empty($u['gender'])) {
                header('Location: ' . url('register/step2'));
            } else {
                header('Location: ' . url(''));
            }
            exit;
        }
        $error = $res['error'];
    }
}

// ---------- OAuth колбэк ----------
$oauthProvider = oauth_detect_provider($_GET);
if ($oauthProvider !== null) {
    try {
        $oauthResult = oauth_handle_callback($oauthProvider, (string)$_GET['code'], $_GET);
        if (!empty($oauthResult['ok'])) {
            $u = current_user();
            $oauthPlan = $_SESSION['oauth_plan'] ?? 'free';
            unset($_SESSION['oauth_plan']);
            if ($u && (empty($u['city']) || empty($u['gender']))) {
                $_SESSION['registration_step'] = 2;
                $_SESSION['registration_plan'] = $oauthPlan;
                header('Location: ' . url('register/step2'));
            } elseif ($oauthPlan === 'pro') {
                header('Location: ' . url('payment'));
            } else {
                header('Location: ' . url(''));
            }
            exit;
        }
        $error = $oauthResult['error'] ?? 'Ошибка авторизации';
    } catch (Throwable $e) {
        $error = 'Ошибка OAuth: ' . $e->getMessage();
    }
}

// ---------- Показ формы сброса пароля ----------
$showReset = isset($_GET['token']);

// ---------- Сброс пароля ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов';
    } else {
        $res = reset_password_with_token($token, $password);
        if ($res['ok']) {
            $success = 'Пароль успешно изменён! Теперь вы можете войти.';
            $showReset = false;
        } else {
            $error = $res['error'] ?? 'Ошибка сброса пароля';
        }
    }
}

// ---------- Отправка ссылки для восстановления ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $res = send_password_reset($email);
        if ($res['ok']) {
            $success = 'Ссылка для восстановления пароля отправлена на ваш email.';
        } else {
            $error = $res['error'] ?? 'Ошибка отправки';
        }
    } else {
        $error = 'Введите корректный email';
    }
}

$pageTitle       = 'Вход — GigaChef';
$pageDescription = 'Войдите в свой аккаунт GigaChef';
require_once __DIR__ . '/../includes/auth_header.php';

$oauthUrls = get_oauth_urls();
?>

<section class="auth-gradient min-h-[80vh] flex items-center justify-center px-4 py-10">
    <div data-aos="fade-up" class="w-full max-w-md bg-white rounded-3xl shadow-lg p-8">
        <div class="text-center mb-6">
            <span class="text-4xl"><svg xmlns="http://www.w3.org/2000/svg" id="Icons" viewBox="0 0 48 48" class="w-16 h-16 inline-block"><defs><style>.cls-11,.cls-5{stroke:#45413c;stroke-linecap:round;stroke-linejoin:round}.cls-11,.cls-9{fill:#45413c}.cls-3{fill:#fff}.cls-4{fill:#f0f0f0}.cls-5{fill:none}.cls-14{fill:#ffb59e}</style></defs><title>144-woman-cook-2</title><path id="_Path_" d="M24 6.32a10.9 10.9 0 0 1 10.88 10.87v14.19a1.2 1.2 0 0 1-.77 1.14h0a27 27 0 0 1-20.22 0h0a1.2 1.2 0 0 1-.77-1.14V17.19A10.9 10.9 0 0 1 24 6.32" data-name="&lt;Path&gt;" style="fill:#a86c4d;stroke:#45413c;stroke-linecap:round;stroke-linejoin:round"/><ellipse id="_Ellipse_" cx="24" cy="45.51" data-name="&lt;Ellipse&gt;" rx="13.5" ry="1.5" style="fill:#45413c;opacity:.15"/><path id="_Rectangle_" d="M24 31.22A11.9 11.9 0 0 1 35.88 43.1V45H12.12v-1.9A11.9 11.9 0 0 1 24 31.22" class="cls-3" data-name="&lt;Rectangle&gt;"/><path id="_Path_2" d="M24 31.22A11.9 11.9 0 0 0 12.12 43.1V45h.1A11.9 11.9 0 0 1 24 34.62 11.9 11.9 0 0 1 35.78 45h.1v-1.9A11.9 11.9 0 0 0 24 31.22" class="cls-4" data-name="&lt;Path&gt;"/><path id="_Rectangle_2" d="M24 31.22A11.9 11.9 0 0 1 35.88 43.1V45h0-23.76 0v-1.9A11.9 11.9 0 0 1 24 31.22" class="cls-5" data-name="&lt;Rectangle&gt;"/><path d="M24 27.18a2.54 2.54 0 0 0-2.54 2.54v2.85a2.54 2.54 0 1 0 5.08 0v-2.85A2.54 2.54 0 0 0 24 27.18" style="stroke:#45413c;stroke-linecap:round;stroke-linejoin:round;fill:#ffb59e"/><path id="_Path_3" d="M17.06 45v-2.41" class="cls-5" data-name="&lt;Path&gt;"/><path id="_Path_4" d="M30.94 45v-2.41" class="cls-5" data-name="&lt;Path&gt;"/><path id="_Path_5" d="M21.46 31.22c0 1.1.73 2 2.54 2s2.54-.89 2.54-2a2.8 2.8 0 0 1 .74 1.91c0 1.27-1.47 2-3.28 2s-3.28-.77-3.28-2a2.7 2.7 0 0 1 .74-1.91" data-name="&lt;Path&gt;" style="stroke:#45413c;stroke-linecap:round;stroke-linejoin:round;fill:#00dfeb"/><path id="_Path_6" d="M24 35.12h2.78a1.5 1.5 0 0 1 1.51 1.5V45" data-name="&lt;Path&gt;" style="stroke:#45413c;stroke-linecap:round;stroke-linejoin:round;fill:#fff"/><path id="_Path_7" d="M24 35.12v-1.91" class="cls-5" data-name="&lt;Path&gt;"/><path id="_Group_" d="M21.84 37.4a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5" class="cls-9" data-name="&lt;Group&gt;"/><path id="_Group_2" d="M26.15 37.4a.75.75 0 1 0 .75.75.75.75 0 0 0-.75-.75" class="cls-9" data-name="&lt;Group&gt;"/><path id="_Group_3" d="M21.84 40.81a.75.75 0 1 0 .75.75.75.75 0 0 0-.75-.75" class="cls-9" data-name="&lt;Group&gt;"/><path id="_Group_4" d="M26.15 40.81a.75.75 0 0 0 0 1.5.75.75 0 1 0 0-1.5" class="cls-9" data-name="&lt;Group&gt;"/><g id="_Group_5" data-name="&lt;Group&gt;"><path id="_Path_8" d="M33.07 19.47a8.8 8.8 0 0 1-5.68-5L26.77 13a16 16 0 0 1-9.95 6.18l-1.88.34a1.81 1.81 0 1 0 0 3.62h.11a9 9 0 0 0 17.9 0h.12a1.81 1.81 0 1 0 0-3.62Z" data-name="&lt;Path&gt;" style="stroke:#45413c;stroke-linecap:round;stroke-linejoin:round;fill:#ffcebf"/></g><path id="_Path_9" d="M18.57 20.91a.77.77 0 1 0 .77-.77.76.76 0 0 0-.77.77" class="cls-11" data-name="&lt;Path&gt;"/><path id="_Path_10" d="M29.43 20.91a.77.77 0 1 1-.77-.77.76.76 0 0 1 .77.77" class="cls-11" data-name="&lt;Path&gt;"/><g id="_Group_6" data-name="&lt;Group&gt;"><g id="_Group_7" data-name="&lt;Group&gt;"><g id="_Group_8" data-name="&lt;Group&gt;"><g id="_Group_9" data-name="&lt;Group&gt;"><g id="_Group_10" data-name="&lt;Group&gt;"><g id="_Group_11" data-name="&lt;Group&gt;"><g id="_Group_12" data-name="&lt;Group&gt;"><path id="_Path_11" d="M21.16 25.6a.4.4 0 0 0-.33.16.4.4 0 0 0-.1.35 3.32 3.32 0 0 0 6.54 0 .4.4 0 0 0-.1-.35.4.4 0 0 0-.33-.16Z" data-name="&lt;Path&gt;" style="fill:#ff6242"/><path id="_Path_12" d="M24 27a4 4 0 0 0-2.52.77 3.36 3.36 0 0 0 5 0A4 4 0 0 0 24 27" data-name="&lt;Path&gt;" style="fill:#ffa694"/></g></g></g></g></g></g></g><g id="_Group_13" data-name="&lt;Group&gt;"><path id="_Path_13" d="M21.16 25.6a.4.4 0 0 0-.33.16.4.4 0 0 0-.1.35 3.32 3.32 0 0 0 6.54 0 .4.4 0 0 0-.1-.35.4.4 0 0 0-.33-.16Z" class="cls-5" data-name="&lt;Path&gt;"/></g><ellipse id="_Ellipse_2" cx="18.06" cy="24.19" class="cls-14" data-name="&lt;Ellipse&gt;" rx="1" ry=".6"/><ellipse id="_Ellipse_3" cx="29.94" cy="24.19" class="cls-14" data-name="&lt;Ellipse&gt;" rx="1" ry=".6"/><path id="_Path_14" d="M29.94 5.21A7.5 7.5 0 0 0 24 2.58a7.5 7.5 0 0 0-5.94 2.63c-2.62-1.27-7.18.3-7.18 4.54 0 3.95 2.79 4.83 2.79 4.83h20.66s2.79-.88 2.79-4.83c0-4.24-4.56-5.81-7.18-4.54" class="cls-4" data-name="&lt;Path&gt;"/><path id="_Path_15" d="M18.06 9.92A7.5 7.5 0 0 1 24 7.29a7.5 7.5 0 0 1 5.94 2.63 5.3 5.3 0 0 1 6.71 2.35 6 6 0 0 0 .47-2.52c0-4.24-4.56-5.81-7.18-4.54A7.5 7.5 0 0 0 24 2.58a7.5 7.5 0 0 0-5.94 2.63c-2.62-1.27-7.18.3-7.18 4.54a6 6 0 0 0 .47 2.52 5.3 5.3 0 0 1 6.71-2.35" class="cls-3" data-name="&lt;Path&gt;"/><path id="_Path_16" d="M29.94 5.21A7.5 7.5 0 0 0 24 2.58a7.5 7.5 0 0 0-5.94 2.63c-2.62-1.27-7.18.3-7.18 4.54 0 3.95 2.79 4.83 2.79 4.83h20.66s2.79-.88 2.79-4.83c0-4.24-4.56-5.81-7.18-4.54" class="cls-5" data-name="&lt;Path&gt;"/><path id="_Path_17" d="M30 9.08c0 2.67-2.69 4.83-6 4.83s-6-2.16-6-4.83" class="cls-5" data-name="&lt;Path&gt;"/><path d="M24 12.83c-5.71 0-10.88.78-10.88 1.75v2.79c0-1 5.17-1.75 10.88-1.75s10.88.79 10.88 1.75v-2.79c0-.97-5.17-1.75-10.88-1.75" style="stroke:#45413c;stroke-linecap:round;stroke-linejoin:round;fill:#f0f0f0"/></svg></span>
            <h1 class="text-2xl font-extrabold text-amber-700 mt-2">Вход в GigaChef</h1>
            <p class="text-sm text-gray-500">Рады видеть вас снова!</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 rounded-xl bg-rose-100 text-rose-700 text-sm"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-100 text-emerald-700 text-sm"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($showReset): ?>
            <!-- Форма сброса пароля -->
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token"  value="<?= e($_GET['token'] ?? '') ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Новый пароль</label>
                    <div class="password-field" x-data="passwordField()">
                        <input type="password"
                               name="password"
                               required
                               minlength="6"
                               autocomplete="new-password"
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                               placeholder="Минимум 6 символов"
                               :type="shown ? 'text' : 'password'">
                        <button type="button"
                                class="password-toggle"
                                @click="toggle()"
                                :aria-label="label"
                                :title="label"
                                tabindex="-1">
                            <svg x-show="!shown" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg x-show="shown" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                <line x1="2" x2="22" y1="2" y2="22"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                    Установить новый пароль
                </button>
            </form>
        <?php else: ?>
            <!-- Обычная форма входа -->
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Email или логин</label>
                    <input type="text" 
                           name="email"
                           required
                           autocomplete="username"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="demo@gigachef.ru или admin">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Пароль</label>
                    <div class="password-field" x-data="passwordField()">
                        <input type="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                               placeholder="••••••"
                               :type="shown ? 'text' : 'password'">
                        <button type="button"
                                class="password-toggle"
                                @click="toggle()"
                                :aria-label="label"
                                :title="label"
                                tabindex="-1">
                            <svg x-show="!shown" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg x-show="shown" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                <line x1="2" x2="22" y1="2" y2="22"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox"
                               name="remember"
                               value="1"
                               class="w-4 h-4 rounded border-gray-300 accent-amber-500 cursor-pointer"
                               id="rememberMe"
                               <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-800 transition">Запомнить меня</span>
                    </label>

                    <button type="button"
                            onclick="showForgotForm()"
                            class="text-xs text-amber-600 hover:underline">
                        Забыли пароль?
                    </button>
                </div>

                <button class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                    Войти
                </button>
            </form>

            <!-- Форма восстановления пароля (скрыта) -->
            <form id="forgotForm" method="post" class="space-y-4 mt-4" style="display:none">
                <input type="hidden" name="action" value="forgot_password">

                <div class="border-t border-gray-100 pt-4">
                    <p class="text-sm text-gray-500 mb-3">Введите email для восстановления пароля</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Email для восстановления</label>
                    <input type="email" 
                           name="email"
                           required
                           autocomplete="email"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="ваш email">
                </div>

                <button class="w-full py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                    Отправить ссылку
                </button>

                <button type="button"
                        onclick="hideForgotForm()"
                        class="w-full py-2 text-sm text-gray-400 hover:text-gray-600">
                    ← Назад ко входу
                </button>
            </form>

            <!-- OAuth -->
            <div class="mt-6">
                <div class="relative mb-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="bg-white px-3 text-gray-400">или войти через</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <?php if (!empty($oauthUrls['yandex'])): ?>
                    <a href="<?= $oauthUrls['yandex'] ?>"
                       class="flex items-center justify-center gap-2 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition text-sm text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 512 512"><path fill="#e34241" d="M48.59 119.59a48 48 0 0 1 74.82-46.99l1.86 1.31c5.02 2.41 8.55 6.54 12.5 10.15l97.54 92.48c7.15 6.74 13.56 13.26 20.71 20 31.91-30.26 63.14-60.24 95.05-90.5 10.26-9.74 20.56-19.44 30.78-29.22 1.31-1.33 2.63-2.66 4.89-2.87 13.62-10.18 30.14-12.68 46.19-6.88a48.1 48.1 0 0 1 30.8 49.78l-.32 2.74c-1.76 9.9-5.71 18.06-12.58 25.43-6.77 8.48-14.97 15.63-22.77 23.08l-105.31 99.83c-6 5.59-11.72 11.2-17.79 16.68-.12 4.99.04 9.57.08 14.56l-.04 3.89v142.95c0 1.6.3 3.23-1.02 4.79a48.4 48.4 0 0 1-6.96 22.19c.32 1.89-.42 2.84-2.19 3.58-3.65 4.08-6.8 7.72-11.43 10.84a48 48 0 0 1-29.17 8.56 48 48 0 0 1-34.09-16.1c-1-1.14-1.92-2.3-3-3.37-1.68-.51-2.18-1.24-1.81-2.9a49 49 0 0 1-7.29-21.81c-1.33-1.87-1.04-3.84-1.04-5.77V303.06l-.04-3.9c.04-4.99.19-9.58.08-14.57-6.42-5.85-12.48-11.75-18.85-17.69-35.44-33.58-70.86-67.2-106.31-100.77-7.07-6.82-14.66-13.32-20.7-21.13-6.86-7.33-10.83-15.53-12.59-25.41"/><path fill="#e4e4e4" d="M456 154.71c2.44 4.75 4.61 9.66 6.73 14.56-1.8 2.37-3.07 2.32-4.89 4.29 2.61 4.61 4.42 8.84 6.4 13.74 7.08 17.28 12.3 36.3 13.71 54.94.37 3.6.85 6.38 1.98 9.87.13 3.21.04 6.37-.06 9.57a223 223 0 0 1-11.75 66.5c-16.82 50.01-51.13 92.43-96.23 119.73a222 222 0 0 1-56.76 24.2c-3.94 1.2-7.93 1.99-11.94 2.94-1.67-1.96-1.18-3.87.33-5.73.84-6.13.14-12.36.41-18.52q.08-2.4.07-4.79V303.06l.04-3.9c3.32-3.98 7.12-7.93 10.89-11.26l84.71-80.3 46.72-44.27a50 50 0 0 0 5.65-6.09c1.03-1.33 1.79-2.7 3.99-2.53m-400.06.08c3.89.81 5.69 5.15 8.69 7.54l45.19 42.81q45.66 43.34 91.38 86.63a42 42 0 0 1 6.76 7.39l.04 3.9v142.96q-.02 2.88.12 5.76c.19 6.64-.53 12.83.69 19.44 1.43 1.2 1.51 2.54.05 3.82-8.24-1.9-16.22-3.84-24.21-6.68a189 189 0 0 1-21.6-8.46 223 223 0 0 1-89.39-73.56 224 224 0 0 1-28.03-52.96 226 226 0 0 1-13.51-71.69c-.08-2.87-.18-5.7-.08-8.57a62 62 0 0 0 1.99-10.88c2.38-25.78 10.72-51.98 22.96-74.82 1.04-1.88 1.89-2.58 2.45-4.66-2.59-.28-3.62.33-6.35.13.35-1.29.27-2.74.82-4.03.65-1.37 1.37-2.7 2.03-4.07m69.33-80.88a222 222 0 0 1 63.06-31.6c20.22-6.25 41.47-9.9 62.65-10.22a226 226 0 0 1 72.76 10.19 239 239 0 0 1 30.72 12.26c11.12 5.55 22.33 11.97 32.28 19.41-1.4 1.23-2.89 2.27-4.2 3.6-10.22 9.78-20.52 19.48-30.78 29.21l-95.74 90.78c-7.14-6.74-14.25-13.53-21.39-20.28l-97.55-92.47c-3.95-3.62-7.67-7.46-11.81-10.88"/><path fill-opacity=".2" d="M32.12 261.69a226 226 0 0 0 13.51 71.69 224 224 0 0 0 28.03 52.96 223 223 0 0 0 89.39 73.56c7.1 3.27 14.16 6.08 21.6 8.46 7.99 2.84 15.97 4.78 24.21 6.68l1.49.36c-.44 4.73 3.12 9.96 5.29 13.98 2.61 4.86 5.61 3.72 10.36 4.59a232 232 0 0 0 31.77 2c9.22-.16 18.12-.64 27.27-1.89 3.67-.83 8.23.22 10.49-3.45 2.4-3.97 6.62-10.45 6.14-15.24l1.52-.34c4.01-.95 8-1.74 11.94-2.94a222 222 0 0 0 56.76-24.2c45.1-27.3 79.41-69.72 96.23-119.73a223 223 0 0 0 11.75-66.5c.05 4.12-.25 8.21-.02 12.33a97 97 0 0 1-.56 15.03 224 224 0 0 1-13.21 61.04 224 224 0 0 1-88.66 110.34 222 222 0 0 1-76.01 30.98c-4.35 1.11-6.93.11-9.89 4.09-13.9 15.73-37.22 20.25-56.12 11.14a46 46 0 0 1-14.9-11.13c-3-3.96-4.68-2.99-9.08-3.94a225 225 0 0 1-38.52-11.46c-55.44-21.81-100.72-66.39-123.59-121.39a223 223 0 0 1-16.61-69.67c-.49-4.76-.8-9.23-.57-14.03.26-4.46-.05-8.86-.01-13.32"/><path fill="#b6b6b6" d="M450.83 145.02q2.52 4.46 4.84 9.03l.33.66a12 12 0 0 0-3.21 3.15 51 51 0 0 1-5.74 6.19l-46.72 44.27-84.71 80.31c-3.77 3.33-7.53 7.66-11.58 10.53-.04-4.98-.1-10 .02-14.99 6.07-5.48 12-11.37 18.01-16.96l105.3-99.84c7.8-7.45 15.75-14.82 23.46-22.35M61.18 145c6.99 6.88 14.32 13.59 21.39 20.4q53.16 50.38 106.31 100.78c6.36 5.93 12.64 12.13 19.06 17.98.12 4.99.06 10.01.02 15-2.8-1.84-4.98-4.43-7.45-6.67q-45.71-43.29-91.38-86.62l-45.19-42.81c-3-2.4-4.81-6.32-8-8.27l.63-1.28q2.24-4.3 4.61-8.51"/><path fill="#e9e9e9" d="M53.91 158.86a21 21 0 0 1-.59 3.8c2.51-.68 4.36-.74 6.95-.46a25 25 0 0 1-2.4 5.7c-12.24 22.83-20.23 48.72-23.58 74.37-.43 3.85-.83 7.2-2.25 10.85.12-4.98.16-9.86.67-14.82a216 216 0 0 1 5.35-34.38 224 224 0 0 1 15.85-45.06m408.82 10.41c3.72 9.13 6.89 18.1 9.43 27.64a223 223 0 0 1 7.77 55.2c-1.48-3.34-1.89-6.23-2.26-9.84a218 218 0 0 0-14.36-54.59c-1.98-4.9-3.85-9.65-6.46-14.26 1.82-1.97 3.47-2.96 5.88-4.15"/><path fill-opacity=".36" d="M210.35 475.4c2.25.39 4.51.81 6.79 1.1 1.08 1.07 2 2.23 3 3.37a48 48 0 0 0 34.09 16.1c-9.56-.16-18.74-.69-28.23-2-4.75-.87-7.75.27-10.36-4.59-2.17-4.02-5.73-9.25-5.29-13.98m84.48 1.17c2.29-.27 4.56-.8 6.84-1.18.48 4.79-3.74 11.27-6.14 15.24-2.26 3.67-6.82 2.62-10.49 3.45a228 228 0 0 1-27.27 1.89 48 48 0 0 0 25.63-8.56c4.63-3.12 7.78-6.76 11.43-10.84"/><path fill-opacity=".2" d="M463.41 119.59c1.82 11.92-.64 24.59-7.74 34.46a230 230 0 0 0-4.84-9.03c6.87-7.37 10.82-15.53 12.58-25.43m-414.82 0c1.76 9.88 5.73 18.08 12.59 25.41a303 303 0 0 0-4.61 8.51 10 10 0 0 1-3.17-3.99 49 49 0 0 1-4.81-29.93"/><path fill="#b6b6b6" d="M303.93 450.8c-.19 6.16.48 12.4-.36 18.53-.26 2.03-.72 3.62-.38 5.72l-1.52.34c-2.28.38-4.55.91-6.84 1.18.58-1.33 1.39-2.39 2.15-3.61a48 48 0 0 0 6.95-22.16m-95.81.98a48.6 48.6 0 0 0 7.28 21.78c.65.98 1.26 1.85 1.74 2.94-2.28-.29-4.54-.71-6.79-1.1l-1.49-.36a13 13 0 0 0-.15-3.8c-1.21-6.62-.52-12.82-.59-19.46"/></svg>
                        Яндекс
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($oauthUrls['vk'])): ?>
                    <a href="<?= $oauthUrls['vk'] ?>"
                       class="flex items-center justify-center gap-2 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition text-sm text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 512 512"><path fill="#507299" d="M31.99 67.1c2.24-6.39 4.24-11.14 10.09-15.13C49.18 45.9 57.39 47.09 66 47h378c9.07.24 18.45-1.46 25.91 4.97 5.85 4.01 7.86 8.73 10.1 15.13L480 442c-.09 5.56.19 11.21-.18 16.75a24.3 24.3 0 0 1-6.87 14.2c-6.78 6.6-13.86 7.38-22.91 7.05H57c-13.07-.01-22.91-8.28-24.83-21.24-.28-3.25-.14-6.48-.17-9.73z"/><path fill="#fff" d="M196.8 174.82c-.08-5.87 8.81-10.93 13.92-12.12 11.94-3.16 25.02-2.55 37.28-2.64 8.66.14 26.99.08 33.5 6.44 6.02 5.94 4.77 18.76 4.7 26.5-.64 14.97-.91 30.04-1.08 45.02.03 6.68.58 19.37 7.4 22.96 9.21 4.71 27.14-25.76 31.2-32.26a287 287 0 0 0 21.84-44.16c3.02-6.78 4.04-14.92 13.35-14.75 17.62.02 35.26-.2 52.88-.28 2.4-.04 4.77-.33 7.19-.2 15.21.52 14.78 8.56 10 20.34-3.2 11.16-10.42 21.59-16.84 31.19-9.07 13.18-18.84 25.86-28.22 38.8-1.37 1.98-2.72 3.89-3.99 5.95-3.85 6.47-6.57 10.67-3.87 18.63 4.82 7.66 12.3 13.67 18.59 20.11 11.14 10.97 22.58 22.75 31.14 35.88 3.34 5.9 8.38 12.52 5.04 19.51-5.93 9.21-19.46 7.21-28.84 7.62-11.33 1.5-22.68 1.18-34.01 1.54-19.92.67-33.67-17.17-45.31-30.74-5.17-5.71-15.42-20.93-24.35-17.4-10.34 4.14-11.43 22.81-11.56 32.27-.08 10.39-6.82 15.02-16.68 15.12-8.9-.17-18.3.33-27.14-.4-27.21-2.79-53.75-16.45-73.18-35.48-6.42-6.12-11.93-12.81-17.49-19.65a395 395 0 0 1-16.64-23.55 582 582 0 0 1-52.45-101.42c-4.46-9.58-6.01-16.85 7.24-18.03 5.09-.26 10.45-.07 15.58-.21q16.51-.07 33.02-.23c3.88-.26 8.1 1.29 11.15 3.65 4.03 3.32 5.86 11.15 8.18 15.81a316 316 0 0 0 19.77 39.23c4.66 8 9.45 15.65 14.99 23.07 3.94 4.67 12.25 15.13 19.02 9.31 5.1-4.82 6.06-20.21 6.44-26.94.3-10.47 0-20.87-.55-31.32-1.33-1.5-1.19-3.1-1.41-4.68-1.26-7.36-2.13-13.16-8.18-18.54-3.78-2.39-7.27-3.21-11.63-3.95"/><path fill-opacity=".2" d="M32.17 458.76C34.09 471.72 43.93 479.99 57 480h393.04c9.05.33 16.13-.45 22.91-7.05a24.3 24.3 0 0 0 6.87-14.2c.04 11.21 2.06 20.69-6.35 29.73-7.15 7.4-14.74 7.86-24.47 7.52H67c-10.69.02-19.75 1.23-27.97-7.03-8.97-9.07-6.88-18.71-6.86-30.21"/><path fill="#6180a3" d="M31.99 67.1c.05-10.77-1.33-19.8 7.03-28.08C47.24 30.73 56.31 32 67 32h383.04c9.42-.38 16.57.44 23.45 7.51 7.82 8.24 6.46 17.14 6.52 27.59-2.69-6.18-4.46-10.81-10.31-14.81-7.91-5.75-16.63-4.05-25.7-4.29H66c-8.61.09-16.16-1.09-23.71 4.28-5.85 3.99-7.61 8.65-10.3 14.82"/><path fill="#405b7a" d="M83.18 187.65c15.36 34.81 32.5 68.96 53.29 100.88A396 396 0 0 0 153.06 312c5.56 6.84 10.98 13.43 17.39 19.55 19.44 19.03 45.38 32.42 72.58 35.21 8.84.72 18.16.22 27.05.39 9.86-.1 15.6-3.77 15.68-14.16.13-9.46 1.86-29.03 12.21-33.17 8.92-3.53 20.29 11.98 25.46 17.69 11.64 13.57 24.58 31.06 44.5 30.39 11.34-.35 22.72-.36 34.06-.57 9.38-.42 22.87 1.59 28.84-7.59l-1.35 2.35c1.76 4.76 4.02 9.48.92 14.29-4.02 5.79-12.9 7.01-19.4 6.81-14.32.24-28.71.32-43.04.7-26.09.68-39.57-28.22-56.55-43.29-8.54-7.77-16.03-7.22-21.19 3.6-3.59 8.12-4.23 16.99-4.51 25.73-.76 15.53-14.37 13.06-25.71 13.2-8.75.07-16.93.15-25.57-1.61-28.47-5.4-53.3-21.01-72.27-42.67-13.88-15.71-25.65-33.62-36.48-51.54-16.08-27.13-30.09-55.36-42.75-84.23-.81-1.87-1.72-3.77-2.29-5.73-.9-4.27-1.45-7.09 2.54-9.7m345.8 2.02c2.1.22 2.23.44 2.76 2.55 1.29 7.48-5.55 19.77-9.19 26.31-11.54 19.92-26 37.93-39.45 56.55-2.47 2.95-3.73 7.08-7.04 9.16-3.89-7.37-.83-12.69 3.02-19.15 1.26-2.06 2.65-4.02 4.02-6.01 9.38-12.94 19.15-25.61 28.21-38.79 6.42-9.6 13.23-19.95 17.67-30.62m-220.55-10.9c6.79 4.27 7.92 11.04 9.17 18.4q.33 2.4.51 4.81c.55 10.46.88 20.86.46 31.33-.44-5.45-.24-10.92-.34-16.32-.57-4.15-1.58-10.16-3.13-14.09-2.12-5.64-6.67-8.97-12.29-10.72-4.6-1.49-8.64-.31-3.99-6.31 2.61-3.33 6.33-4.67 9.61-7.1"/></svg>
                        ВКонтакте
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-center text-sm text-gray-500 mt-5">
            Нет аккаунта?
            <a href="<?= url('register') ?>" class="text-amber-600 font-semibold hover:underline">Зарегистрироваться</a>
        </p>
    </div>
</section>

<script>
function showForgotForm() { document.getElementById('forgotForm').style.display = 'block'; }
function hideForgotForm() { document.getElementById('forgotForm').style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>