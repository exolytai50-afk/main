<?php
require_once __DIR__ . '/../config/config.php';

// Должен быть авторизован
if (!is_logged_in()) {
    header('Location: ' . url('register'));
    exit;
}

$user  = current_user();
error_log('STEP2 DEBUG: user_id=' . var_export($user['id'] ?? null, true)
    . ' email=' . var_export($user['email'] ?? null, true)
    . ' city=' . var_export($user['city'] ?? null, true)
    . ' gender=' . var_export($user['gender'] ?? null, true));
$error = '';

// Если профиль уже заполнен — на главную
if (!empty($user['city']) && !empty($user['gender'])) {
    session_write_close();
    header('Location: ' . url(''));
    exit;
}

// Обработка завершения регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Сессия истекла';
    } else {
        $city       = trim($_POST['city']       ?? '');
        $gender     = trim($_POST['gender']     ?? '');
        $birthDate  = trim($_POST['birth_date'] ?? '');
        $phone      = trim($_POST['phone']      ?? '');
        $website    = trim($_POST['website']    ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $bio        = trim($_POST['bio']        ?? '');

        if (mb_strlen($city) < 2) {
            $error = 'Укажите город';
        } elseif (!in_array($gender, ['male', 'female'], true)) {
            $error = 'Укажите пол';
        } else {
            $birthDate = $birthDate === '' ? null : $birthDate;

            db_exec(
                'UPDATE users SET city = ?, gender = ?, birth_date = ?, phone = ?, website = ?, occupation = ?, bio = ? WHERE id = ?',
                [$city, $gender, $birthDate, $phone, $website, $occupation, $bio, (int)$user['id']]
            );

            // Upsert: если строки нет — создаём; если есть — добавляем очки
            db_exec(
                'INSERT INTO user_stats (user_id, total_points) VALUES (?, 15)
                 ON DUPLICATE KEY UPDATE total_points = total_points + 15',
                [(int)$user['id']]
            );
            db_exec('UPDATE users SET points = points + 15 WHERE id = ?', [(int)$user['id']]);

            add_activity($user['email'], 'profile_completed', null, null, 'Заполнил профиль');

            // Сбрасываем кеш current_user, чтобы последующие проверки видели новые данные
            if (function_exists('current_user_flush_cache')) {
                current_user_flush_cache();
            }

            $plan = $_SESSION['registration_plan'] ?? 'free';
            unset($_SESSION['registration_step'], $_SESSION['registration_plan']);

            if ($plan === 'pro') {
                header('Location: ' . url('payment'));
                exit;
            }
            session_write_close();
            header('Location: ' . url(''));
            exit;
        }
    }
}

$pageTitle       = 'Регистрация — Шаг 2 из 2 — GigaChef';
$pageDescription = 'Заполните дополнительную информацию';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<section class="auth-gradient min-h-[80vh] flex items-center justify-center px-4 py-10">
    <div data-aos="fade-up" class="w-full max-w-lg bg-white rounded-3xl shadow-lg p-8">
        <div class="text-center mb-6">
            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="w-8 h-8 rounded-full bg-emerald-400 text-white font-bold flex items-center justify-center text-sm">✓</span>
                <span class="w-12 h-0.5 bg-amber-400"></span>
                <span class="w-8 h-8 rounded-full bg-amber-400 text-white font-bold flex items-center justify-center text-sm">2</span>
            </div>
            <span class="text-4xl">👤</span>
            <h1 class="text-2xl font-extrabold text-amber-700 mt-2">Расширенный профиль</h1>
            <p class="text-sm text-gray-500">Шаг 2 из 2 — дополнительная информация</p>
            <p class="text-xs text-gray-400 mt-1">Поля отмеченные * обязательны</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 rounded-xl bg-rose-100 text-rose-700 text-sm"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Город *</label>
                    <input type="text" name="city" required value="<?= e($user['city'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="Например: Москва">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Пол *</label>
                    <select name="gender" required class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300">
                        <option value="">Выберите...</option>
                        <option value="male"   <?= ($user['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Женский</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= e($user['birth_date'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Телефон</label>
                    <input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="+7 (999) 000-00-00">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Веб-сайт</label>
                    <input type="text" name="website" value="<?= e($user['website'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="https://...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Род занятий</label>
                    <input type="text" name="occupation" value="<?= e($user['occupation'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300"
                           placeholder="Например: Шеф-повар">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">О себе</label>
                <textarea name="bio" rows="3" maxlength="500"
                          class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 resize-none"
                          placeholder="Расскажите о себе..."><?= e($user['bio'] ?? '') ?></textarea>
            </div>

            <button class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                ✅ Завершить регистрацию
            </button>

            <a href="<?= url('') ?>" class="block text-center text-sm text-gray-400 hover:text-gray-600">
                Пропустить и заполнить позже
            </a>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>