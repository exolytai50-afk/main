<?php
/**
 * GigaChef — Страница профиля
 * Включает блок «Фьюжн-лаборатория» и интеграцию с достижениями
 */
require_once __DIR__ . '/../config/config.php';
require_login();

// Определяем, чью ленту смотрим
$profileUser = $_GET['user'] ?? null;
$isOwnProfile = !$profileUser || $profileUser === current_user()['email'];

if ($isOwnProfile) {
    $u = current_user();
    $user_id = $u['id'];
    $user_email = $u['email'];
} else {
    $decodedUser = urldecode($profileUser);
    $u = db_one('SELECT * FROM users WHERE email = ?', [$decodedUser]);
    if (!$u) {
        $u = db_one('SELECT * FROM users WHERE display_name = ?', [$decodedUser]);
    }
    if (!$u) {
        http_response_code(404);
        $pageTitle = 'Пользователь не найден';
        require_once __DIR__ . '/../includes/header.php';
        echo '<div class="text-center py-20"><div class="text-6xl mb-4">'.icon('search', 'gc-icon-xl').'</div><h1 class="text-2xl font-bold text-gray-800">Пользователь не найден</h1><p class="text-gray-500 mt-2">'.e($decodedUser).'</p><a href="'.url('friends').'" class="inline-block mt-4 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold">Найти друзей</a></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
    $user_id = $u['id'];
    $user_email = $u['email'];
}

// Добавляем поля профиля
$profileFields = ['city', 'birth_date', 'gender', 'phone', 'website', 'occupation', 'theme', 'notification_settings'];
foreach ($profileFields as $field) {
    try {
        $cols = db_all("SHOW COLUMNS FROM users LIKE '{$field}'");
        if (empty($cols)) {
            $type = match($field) {
                'birth_date' => 'date DEFAULT NULL',
                'notification_settings' => 'TEXT DEFAULT NULL',
                default => 'varchar(255) DEFAULT NULL'
            };
            db_exec("ALTER TABLE users ADD COLUMN `{$field}` {$type}");
        }
    } catch (Throwable $e) {}
}

// Статус дружбы
$friendshipStatus = null;
if (!$isOwnProfile) {
    $myEmail = current_user()['email'];
    $rel = db_one(
        "SELECT user_email, status
         FROM friendships
         WHERE (user_email = ? AND friend_email = ?)
            OR (user_email = ? AND friend_email = ?)
         ORDER BY (status = 'accepted') DESC, id DESC
         LIMIT 1",
        [$myEmail, $user_email, $user_email, $myEmail]
    );
    if ($rel) {
        if ($rel['status'] === 'accepted')        $friendshipStatus = 'accepted';
        elseif ($rel['user_email'] === $myEmail)  $friendshipStatus = 'pending';
        else                                      $friendshipStatus = 'incoming';
    }
}

// Статистика
$stats = db_one('SELECT * FROM user_stats WHERE user_id=?', [$user_id]) ?? [];

// ============================================================
//  СТАТИСТИКА ФЬЮЖН
// ============================================================
$fusionStats = [
    'total' => 0,
    'saved' => 0,
    'avg_rating' => 0,
    'top_cuisines' => []
];

try {
    $row = db_one(
        'SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) as saved,
            AVG(rating) as avg_rating
         FROM fusion_recipes WHERE user_id = ?',
        [$user_id]
    );
    if ($row) {
        $fusionStats['total'] = (int)$row['total'];
        $fusionStats['saved'] = (int)($row['saved'] ?? 0);
        $fusionStats['avg_rating'] = round((float)($row['avg_rating'] ?? 0), 1);
    }

    // Топ-3 кухни
    $cuisines = db_all(
        'SELECT cuisine_a as cuisine, COUNT(*) as cnt FROM fusion_recipes WHERE user_id = ? GROUP BY cuisine_a
         UNION ALL
         SELECT cuisine_b as cuisine, COUNT(*) as cnt FROM fusion_recipes WHERE user_id = ? GROUP BY cuisine_b',
        [$user_id, $user_id]
    );

    $cuisineCounts = [];
    foreach ($cuisines as $c) {
        $cuisineCounts[$c['cuisine']] = ($cuisineCounts[$c['cuisine']] ?? 0) + (int)$c['cnt'];
    }
    arsort($cuisineCounts);
    $fusionStats['top_cuisines'] = array_slice($cuisineCounts, 0, 3, true);

} catch (Throwable $e) {
    // Таблица может отсутствовать
}

// Последние фьюжн-рецепты пользователя
$recentFusion = [];
try {
    $recentFusion = db_all(
        'SELECT id, title, cuisine_a, cuisine_b, cuisine_a_name, cuisine_b_name, 
                difficulty, cook_time, rating, is_saved, image_url, created_at
         FROM fusion_recipes 
         WHERE user_id = ?
         ORDER BY created_at DESC 
         LIMIT 4',
        [$user_id]
    );
} catch (Throwable $e) {}

// Достижения пользователя
$achievements = [];
if (function_exists('get_user_achievements')) {
    try {
        $allAchievements = get_user_achievements($user_email);
        $achievements = array_slice(array_filter($allAchievements, fn($a) => $a['unlocked']), 0, 30);
    } catch (Throwable $e) {}
}

// Рецепты пользователя
$my_recipes = db_all(
    'SELECT id, slug, title, image_url, likes_count, views_count, created_at, description, cuisine, difficulty, prep_time, cook_time, servings, calories
     FROM recipes WHERE author_id=? OR author_email=? ORDER BY created_at DESC LIMIT 20',
    [$user_id, $user_email]
);

// Комментарии
$my_comments = db_all(
    'SELECT c.id, c.text, c.rating, c.created_at, c.recipe_id, r.title as recipe_title, r.slug as recipe_slug
     FROM comments c LEFT JOIN recipes r ON c.recipe_id = r.id
     WHERE c.user_email=? ORDER BY c.created_at DESC LIMIT 10',
    [$user_email]
);

// Друзья
$friends = db_all(
    "SELECT u.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
            (SELECT COUNT(*) FROM recipes WHERE author_id = u.id OR author_email = u.email) AS recipe_count
     FROM friendships f
     JOIN users u ON u.email = CASE WHEN f.user_email = ? THEN f.friend_email ELSE f.user_email END
     WHERE (f.user_email = ? OR f.friend_email = ?)
       AND f.status = 'accepted'
       AND u.email IS NOT NULL
     ORDER BY u.display_name",
    [$user_email, $user_email, $user_email]
);

// Входящие заявки
$incomingRequests = [];
if ($isOwnProfile) {
    $incomingRequests = db_all(
        "SELECT f.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender, f.created_at
         FROM friendships f JOIN users u ON u.email = f.user_email
         WHERE f.friend_email = ? AND f.status = 'pending'
         ORDER BY f.created_at DESC LIMIT 10",
        [$user_email]
    );
}

// Активность (включая фьюжн)
$activity = [];
foreach ($my_recipes as $r) $activity[] = ['type'=>'recipe','title'=>$r['title'],'date'=>$r['created_at']];
foreach ($my_comments as $c) $activity[] = ['type'=>'comment','title'=>'Комментарий к '.($c['recipe_title']??'рецепту'),'date'=>$c['created_at']];
foreach ($recentFusion as $f) $activity[] = ['type'=>'fusion','title'=>'Фьюжн: '.$f['title'],'date'=>$f['created_at']];
usort($activity, fn($a,$b) => strtotime($b['date'])-strtotime($a['date']));
$activity = array_slice($activity, 0, 20);

// Настройки уведомлений
$notificationSettings = [
    'email_notifications' => true,
    'push_notifications' => true,
    'new_friend_requests' => true,
    'new_comments' => true,
    'new_likes' => true,
    'new_messages' => true,
    'recipe_approved' => true,
    'weekly_digest' => false,
];

try {
    $settingsFromDb = db_one('SELECT notification_settings FROM users WHERE id = ?', [$user_id]);
    if ($settingsFromDb && !empty($settingsFromDb['notification_settings'])) {
        $savedSettings = json_decode($settingsFromDb['notification_settings'], true);
        if (is_array($savedSettings)) {
            $notificationSettings = array_merge($notificationSettings, $savedSettings);
        }
    }
} catch (Throwable $e) {}

// Обработка форм
$profile_error = $profile_success = $password_error = $password_success = $avatar_error = '';
$notification_success = '';
$avatar_url = $u['avatar_url'] ?? null;
$avatar_emoji = $u['avatar_emoji'] ?? '';

if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Обновление профиля
    if (isset($_POST['update_profile']) && csrf_check($_POST['csrf'] ?? '')) {
        $new_name = trim($_POST['display_name'] ?? '');
        $new_nickname = trim($_POST['nickname'] ?? '');
        
        if (mb_strlen($new_name) < 2) {
            $profile_error = 'Имя должно быть не короче 2 символов';
        } elseif (empty($new_nickname) || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $new_nickname)) {
            $profile_error = 'Никнейм должен быть от 3 до 50 символов (буквы, цифры, _)';
        } else {
            // Проверяем, не занят ли никнейм другим пользователем
            $exists = db_one('SELECT id FROM users WHERE nickname = ? AND id != ?', [$new_nickname, $user_id]);
            if ($exists) {
                $profile_error = 'Этот никнейм уже занят другим пользователем';
            } else {
                $birth_date = trim($_POST['birth_date'] ?? '');
                $birth_date = ($birth_date === '') ? null : $birth_date;

                db_exec(
                    'UPDATE users SET display_name=?, nickname=?, bio=?, city=?, birth_date=?, gender=?, phone=?, website=?, occupation=? WHERE id=?',
                    [
                        $new_name, 
                        $new_nickname, 
                        trim($_POST['bio'] ?? ''), 
                        trim($_POST['city'] ?? ''), 
                        $birth_date, 
                        trim($_POST['gender'] ?? ''), 
                        trim($_POST['phone'] ?? ''), 
                        trim($_POST['website'] ?? ''), 
                        trim($_POST['occupation'] ?? ''), 
                        $user_id
                    ]
                );
                $profile_success = 'Профиль обновлён';
                
                // Обновляем локальную переменную $u, чтобы изменения сразу отобразились в форме
                $u['display_name'] = $new_name;
                $u['nickname'] = $new_nickname;
                $u['bio'] = trim($_POST['bio'] ?? '');
                $u['city'] = trim($_POST['city'] ?? '');
                $u['birth_date'] = $birth_date;
                $u['gender'] = trim($_POST['gender'] ?? '');
                $u['phone'] = trim($_POST['phone'] ?? '');
                $u['website'] = trim($_POST['website'] ?? '');
                $u['occupation'] = trim($_POST['occupation'] ?? '');
            }
        }
    }

    // Смена пароля
    if (isset($_POST['change_password']) && csrf_check($_POST['csrf2'] ?? '')) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $pwd = db_one('SELECT password_hash FROM users WHERE id=?', [$user_id]);

        if (!password_verify($old, $pwd['password_hash'])) $password_error = 'Неверный текущий пароль';
        elseif (strlen($new) < 6) $password_error = 'Минимум 6 символов';
        elseif ($new !== $confirm) $password_error = 'Пароли не совпадают';
        else {
            db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $password_success = 'Пароль изменён';
        }
    }

    // Настройки уведомлений
    if (isset($_POST['save_notifications']) && csrf_check($_POST['csrf3'] ?? '')) {
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']),
            'push_notifications' => isset($_POST['push_notifications']),
            'new_friend_requests' => isset($_POST['new_friend_requests']),
            'new_comments' => isset($_POST['new_comments']),
            'new_likes' => isset($_POST['new_likes']),
            'new_messages' => isset($_POST['new_messages']),
            'recipe_approved' => isset($_POST['recipe_approved']),
            'weekly_digest' => isset($_POST['weekly_digest']),
        ];

        try {
            db_exec('UPDATE users SET notification_settings = ? WHERE id = ?', [json_encode($settings), $user_id]);
            $notificationSettings = $settings;
            $notification_success = 'Настройки уведомлений сохранены';
        } catch (Throwable $e) {
            $profile_error = 'Ошибка сохранения настроек: ' . $e->getMessage();
        }
    }

    // Загрузка аватара
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        if (!in_array($file['type'], ['image/jpeg','image/png','image/gif','image/webp'])) {
            $avatar_error = 'Только JPEG, PNG, GIF, WebP';
        } elseif ($file['size'] > 5*1024*1024) {
            $avatar_error = 'Файл слишком большой (макс. 5MB)';
        } else {
            $dir = ROOT_DIR . '/uploads/avatar/user';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $path = '/uploads/avatar/user/avatar_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], ROOT_DIR . $path)) {
                db_exec('UPDATE users SET avatar_url=?, avatar_emoji=NULL WHERE id=?', [$path, $user_id]);
                $avatar_url = $path;
                $avatar_emoji = null;
            }
        }
    }

    // Выбор аватара из галереи
    if (isset($_POST['select_avatar']) && str_starts_with($_POST['select_avatar'], '/uploads/avatar/')) {
        db_exec('UPDATE users SET avatar_url=?, avatar_emoji=NULL WHERE id=?', [$_POST['select_avatar'], $user_id]);
        $avatar_url = $_POST['select_avatar'];
        $avatar_emoji = null;
    }
}

// Функция сканирования аватаров
function scan_avatars(string $dir): array {
    $avatars = [];
    $full = ROOT_DIR . $dir;
    if (!is_dir($full)) {
        mkdir($full, 0755, true);
        return $avatars;
    }
    foreach (scandir($full) as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $full.'/'.$item; $r = $dir.'/'.$item;
        if (is_dir($p)) {
            $avatars = array_merge($avatars, scan_avatars($r));
        } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $item)) {
            $avatars[] = $r;
        }
    }
    return $avatars;
}
$gallery_avatars = scan_avatars('/uploads/avatar/static');

function recipe_url(array $r): string {
    return !empty($r['slug']) ? url('recipe/'.$r['slug']) : url('recipe?id='.(int)$r['id']);
}

$pageTitle = ($u['display_name'] ?? 'Профиль') . ' — GigaChef';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.avatar-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 99999;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.avatar-modal-overlay.show {
    display: flex !important;
}
.avatar-modal-content {
    background: white;
    border-radius: 1.5rem;
    width: 100%;
    max-width: 28rem;
    max-height: 85vh;
    overflow-y: auto;
    padding: 1.5rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}
.fusion-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}
</style>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="{ tab: 'profile' }">

    <!-- Профиль -->
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-3xl shadow-md p-8 mb-8" data-aos="fade-up">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

            <!-- Аватар -->
            <div class="flex flex-col items-center">
                <div class="relative mb-4">
                    <?= render_avatar(
    [
        'avatar_url'   => $avatar_url,
        'avatar_emoji' => null,
        'gender'       => $u['gender'] ?? null,
    ],
    'w-32 h-32 rounded-full object-cover border-4 border-amber-300 shadow-lg',
    'w-32 h-32 rounded-full bg-amber-200 flex items-center justify-center border-4 border-amber-300 shadow-lg text-amber-600 text-6xl',
    'text-6xl'
) ?>

                    <?php if ($isOwnProfile): ?>
                    <button onclick="openAvatarModal()" class="absolute bottom-0 right-0 bg-amber-400 w-10 h-10 p-2 rounded-full cursor-pointer hover:bg-amber-500 transition shadow-md z-10" title="Сменить аватар">
                        <?= icon('plus', 'gc-icon-sm') ?>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (!$isOwnProfile): ?>
                <div class="flex flex-wrap gap-2 justify-center">
                    <?php if ($friendshipStatus === 'accepted'): ?>
                        <span class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold"><?= icon('check', 'gc-icon-xs') ?> Друзья</span>
                    <?php elseif ($friendshipStatus === 'pending'): ?>
                        <span class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 text-sm font-bold"><?= icon('hourglass', 'gc-icon-xs') ?> Заявка отправлена</span>
                    <?php elseif ($friendshipStatus === 'incoming'): ?>
                        <form method="post" action="/api/friends.php" onsubmit="return gcFriendSubmit(event, this);">
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="friend_email" value="<?= e($user_email) ?>">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold hover:bg-emerald-200">
                                <?= icon('check', 'gc-icon-xs') ?> Принять
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/api/friends.php" onsubmit="return gcFriendSubmit(event, this);">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="friend_email" value="<?= e($user_email) ?>">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200">
                                <?= icon('plus', 'gc-icon-xs') ?> В друзья
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= url('chat/' . urlencode($user_email)) ?>" class="px-4 py-2 rounded-xl bg-blue-100 text-blue-700 text-sm font-bold hover:bg-blue-200 transition"><?= icon('chats', 'gc-icon-xs') ?> Написать</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Данные -->
            <div class="md:col-span-3">
                <?php if ($profile_error): ?><div class="mb-4 px-4 py-2 rounded-xl bg-rose-100 text-rose-700 text-sm"><?= e($profile_error) ?></div><?php endif; ?>
                <?php if ($profile_success): ?><div class="mb-4 px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm"><?= e($profile_success) ?></div><?php endif; ?>

                <?php if ($isOwnProfile): ?>
                <div class="flex gap-2 mb-4 border-b border-gray-200 overflow-x-auto">
                    <button @click="tab='profile'" :class="tab==='profile'?'border-amber-400 text-amber-700':'border-transparent text-gray-400'" class="px-3 py-2 border-b-2 text-sm font-semibold whitespace-nowrap"><?= icon('profile', 'gc-icon-xs') ?> Профиль</button>
                    <button @click="tab='recipes'" :class="tab==='recipes'?'border-amber-400 text-amber-700':'border-transparent text-gray-400'" class="px-3 py-2 border-b-2 text-sm font-semibold whitespace-nowrap"><?= icon('openbook', 'gc-icon-xs') ?> Рецепты</button>
                    <button @click="tab='fusion'" :class="tab==='fusion'?'border-purple-400 text-purple-700':'border-transparent text-gray-400'" class="px-3 py-2 border-b-2 text-sm font-semibold whitespace-nowrap"><?= icon('fusion', 'gc-icon-xs') ?> Фьюжн</button>
                    <button @click="tab='password'" :class="tab==='password'?'border-amber-400 text-amber-700':'border-transparent text-gray-400'" class="px-3 py-2 border-b-2 text-sm font-semibold whitespace-nowrap"><?= icon('lock', 'gc-icon-xs') ?> Пароль</button>
                    <button @click="tab='notifications'" :class="tab==='notifications'?'border-amber-400 text-amber-700':'border-transparent text-gray-400'" class="px-3 py-2 border-b-2 text-sm font-semibold whitespace-nowrap"><?= icon('notifications', 'gc-icon-xs') ?> Уведомления</button>
                </div>

                <!-- Профиль -->
                <div x-show="tab==='profile'">
                    <form method="post" class="space-y-3">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="text-xs text-gray-600 mb-1 block">Имя *</label><input type="text" name="display_name" value="<?= e($u['display_name']??'') ?>" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                            <div>
                                <label class="text-xs text-gray-600 mb-1 block">Никнейм *</label>
                                <input type="text" name="nickname" value="<?= e($u['nickname']??'') ?>" required pattern="[a-zA-Z0-9_]{3,50}" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                                <p class="text-[10px] text-gray-400 mt-0.5">Для ссылки на профиль и входа</p>
                            </div>                            
                            <div><label class="text-xs text-gray-600 mb-1 block">Email</label><input type="email" value="<?= e($user_email) ?>" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-sm"></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Город</label><input type="text" name="city" value="<?= e($u['city']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Дата рождения</label><input type="date" name="birth_date" value="<?= e($u['birth_date']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Пол</label><select name="gender" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"><option value="">Не указан</option><option value="male" <?=($u['gender']??'')==='male'?'selected':''?>>Мужской</option><option value="female" <?=($u['gender']??'')==='female'?'selected':''?>>Женский</option></select></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Телефон</label><input type="text" name="phone" value="<?= e($u['phone']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Сайт</label><input type="text" name="website" value="<?= e($u['website']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                            <div><label class="text-xs text-gray-600 mb-1 block">Занятие</label><input type="text" name="occupation" value="<?= e($u['occupation']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></div>
                        </div>
                        <div><label class="text-xs text-gray-600 mb-1 block">Биография</label><textarea name="bio" rows="3" maxlength="500" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm resize-none"><?= e($u['bio']??'') ?></textarea></div>
                        <button type="submit" class="w-full py-2 rounded-lg bg-gradient-to-r from-amber-400 to-orange-400 text-amber-900 font-semibold text-sm">Сохранить</button>
                    </form>
                </div>

                <!-- Мои рецепты -->
                <div x-show="tab==='recipes'" style="display:none">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-gray-800"><?= icon('openbook', 'gc-icon-sm') ?> Мои рецепты (<?= count($my_recipes) ?>)</h3>
                            <a href="<?= url('create-recipe') ?>" class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-700 text-xs font-bold hover:bg-amber-200 transition"><?= icon('plus', 'gc-icon-xs') ?> Новый рецепт</a>
                        </div>

                        <?php if ($my_recipes): ?>
                            <div class="space-y-3">
                                <?php foreach ($my_recipes as $recipe): ?>
                                <div class="bg-white rounded-xl shadow-sm p-4" x-data="{ editMode: false }">
                                    <div class="flex items-start gap-3">
                                        <div class="w-16 h-16 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                                            <?php if (!empty($recipe['image_url'])): ?>
                                                <img src="<?= e($recipe['image_url']) ?>" class="w-full h-full object-cover" alt="<?= e($recipe['title']) ?>">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-2xl"><?= icon('chef', 'gc-icon-lg') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="<?= recipe_url($recipe) ?>" class="font-semibold text-gray-800 hover:text-amber-600 text-sm"><?= e($recipe['title']) ?></a>
                                            <div class="flex gap-3 mt-1 text-xs text-gray-400">
                                                <span><?= icon('eye', 'gc-icon-xs') ?> <?= (int)$recipe['views_count'] ?></span>
                                                <span><?= icon('heart', 'gc-icon-xs') ?> <?= (int)$recipe['likes_count'] ?></span>
                                                <span><?= icon('calend', 'gc-icon-xs') ?> <?= date('d.m.Y', strtotime($recipe['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            <button @click="editMode = !editMode" class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold hover:bg-amber-100 transition"><?= icon('pen', 'gc-icon-xs') ?></button>
                                            <a href="<?= recipe_url($recipe) ?>" class="px-2 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100 transition"><?= icon('eye', 'gc-icon-xs') ?></a>
                                        </div>
                                    </div>

                                    <form x-show="editMode" style="display:none" method="post" action="/api/admin/recipes.php" class="mt-3 p-3 bg-gray-50 rounded-lg space-y-2">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$recipe['id'] ?>">
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Название</label>
                                            <input type="text" name="title" value="<?= e($recipe['title']) ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Описание</label>
                                            <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm"><?= e($recipe['description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-xs text-gray-600 mb-1 block">Кухня</label>
                                                <input type="text" name="cuisine" value="<?= e($recipe['cuisine'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                                            </div>
                                            <div>
                                                <label class="text-xs text-gray-600 mb-1 block">Сложность</label>
                                                <select name="difficulty" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm">
                                                    <option value="Легко" <?= ($recipe['difficulty'] ?? '') === 'Легко' ? 'selected' : '' ?>>Легко</option>
                                                    <option value="Средне" <?= ($recipe['difficulty'] ?? '') === 'Средне' ? 'selected' : '' ?>>Средне</option>
                                                    <option value="Сложно" <?= ($recipe['difficulty'] ?? '') === 'Сложно' ? 'selected' : '' ?>>Сложно</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 justify-end">
                                            <button type="button" @click="editMode = false" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">Отмена</button>
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-bold hover:bg-emerald-600 transition"><?= icon('diskette', 'gc-icon-xs') ?> Сохранить</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <div class="text-4xl mb-2"><?= icon('openbook', 'gc-icon-xl') ?></div>
                                <p>У вас пока нет рецептов</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ФЬЮЖН-ЛАБОРАТОРИЯ                             -->
                <!-- ============================================ -->
                <div x-show="tab==='fusion'" style="display:none">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <span class="text-xl"><?= icon('fusion', 'gc-icon-md') ?></span>
                                <span>Фьюжн-лаборатория</span>
                            </h3>
                            <a href="<?= url('fusion') ?>"
                               class="px-3 py-1.5 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold hover:bg-purple-200 transition">
                                <?= icon('plus', 'gc-icon-xs') ?> Создать фьюжн
                            </a>
                        </div>

                        <!-- Статистика фьюжн -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-3 text-center border border-purple-200">
                                <div class="text-2xl font-bold text-purple-600"><?= $fusionStats['total'] ?></div>
                                <div class="text-xs text-purple-700 mt-1">Создано</div>
                            </div>
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-3 text-center border border-green-200">
                                <div class="text-2xl font-bold text-green-600"><?= $fusionStats['saved'] ?></div>
                                <div class="text-xs text-green-700 mt-1">Сохранено</div>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-3 text-center border border-yellow-200">
                                <div class="text-2xl font-bold text-yellow-600">
                                    <?= $fusionStats['avg_rating'] ?: '—' ?>
                                </div>
                                <div class="text-xs text-yellow-700 mt-1">Ср. оценка</div>
                            </div>
                        </div>

                        <!-- Топ-кухни -->
                        <?php if (!empty($fusionStats['top_cuisines'])): ?>
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <h4 class="text-xs font-bold text-gray-600 mb-2"><?= icon('globe', 'gc-icon-xs') ?> Любимые кухни</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($fusionStats['top_cuisines'] as $cuisine => $count): ?>
                                    <span class="px-3 py-1 rounded-full bg-purple-50 text-purple-700 text-xs font-medium">
                                        <?= icon($cuisine, 'gc-icon-xs', 1) ?> <?= e(ucfirst($cuisine)) ?> · <?= $count ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Последние фьюжн-рецепты -->
                        <?php if (!empty($recentFusion)): ?>
                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-2">Последние творения</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <?php foreach ($recentFusion as $f): ?>
                                    <a href="<?= url('fusion?id=' . (int)$f['id']) ?>"
                                       class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition-all group">
                                        <div class="aspect-video fusion-gradient flex items-center justify-center text-3xl">
                                            <?= icon($f['cuisine_a'], '', 2) ?><?= icon('plus', 'gc-icon-xs') ?><?= icon($f['cuisine_b'], '', 2) ?>
                                        </div>
                                        <div class="p-2">
                                            <div class="text-xs font-semibold text-gray-800 line-clamp-2 group-hover:text-purple-600 transition">
                                                <?= e($f['title']) ?>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                                                <?php if ($f['rating']): ?>
                                                    <span><?= icon('star', 'gc-icon-xs') ?> <?= number_format((float)$f['rating'], 1) ?></span>
                                                <?php endif; ?>
                                                <?php if ($f['cook_time']): ?>
                                                    <span><?= icon('time', 'gc-icon-xs') ?> <?= (int)$f['cook_time'] ?>м</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <div class="text-4xl mb-2"><?= icon('fusion', 'gc-icon-xl') ?></div>
                                <p>Вы ещё не создавали фьюжн-рецепты</p>
                                <a href="<?= url('fusion') ?>"
                                   class="inline-block mt-3 px-4 py-2 rounded-lg bg-purple-100 text-purple-700 text-sm font-semibold hover:bg-purple-200 transition">
                                    Попробовать
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Пароль -->
                <div x-show="tab==='password'" style="display:none">
                    <?php if ($password_error): ?>
                        <div class="mb-4 px-4 py-2 rounded-xl bg-rose-100 text-rose-700 text-sm"><?= e($password_error) ?></div>
                    <?php endif; ?>
                    <?php if ($password_success): ?>
                        <div class="mb-4 px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm"><?= e($password_success) ?></div>
                    <?php endif; ?>

                    <form method="post" class="space-y-3" autocomplete="on">
                        <input type="hidden" name="csrf2" value="<?= csrf_token() ?>">
                        <input type="hidden" name="change_password" value="1">

                        <!-- Username для менеджера паролей: видим для Chrome, скрыт для пользователя -->
                        <input type="text"
                               name="username"
                               autocomplete="username"
                               value="<?= e(current_user()['email']) ?>"
                               readonly tabindex="-1" aria-hidden="true"
                               style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;">

                        <div>
                            <label class="text-xs text-gray-600 mb-1 block">Текущий пароль</label>
                            <input type="password" name="old_password"
                                   autocomplete="current-password" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="text-xs text-gray-600 mb-1 block">Новый пароль</label>
                            <input type="password" name="new_password"
                                   autocomplete="new-password" required minlength="6"
                                   class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="text-xs text-gray-600 mb-1 block">Подтвердите</label>
                            <input type="password" name="confirm_password"
                                   autocomplete="new-password" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        </div>

                        <button type="submit"
                                class="w-full py-2 rounded-lg bg-amber-100 text-amber-700 font-semibold text-sm">
                            Изменить пароль
                        </button>
                    </form>
                </div>

                <!-- Уведомления -->
                <div x-show="tab==='notifications'" style="display:none">
                    <div class="space-y-6">
                        <?php if ($notification_success): ?><div class="mb-4 px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm"><?= e($notification_success) ?></div><?php endif; ?>

                        <div>
                            <h3 class="font-bold text-gray-800 mb-3"><?= icon('friends', 'gc-icon-sm') ?> Заявки в друзья</h3>
                            <?php if (empty($incomingRequests)): ?>
                                <p class="text-sm text-gray-400">Нет входящих заявок</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($incomingRequests as $req): ?>
                                    <div class="flex items-center gap-3 p-3 bg-amber-50 rounded-xl">
                                        <?= render_avatar(
                                            $req,
                                            'w-12 h-12 rounded-full object-cover',
                                            'w-12 h-12 rounded-full bg-amber-200 flex items-center justify-center',
                                            'text-xl leading-none'
                                        ) ?>
                                        <div class="flex-1"><a href="<?= url('profile?user='.urlencode($req['email'])) ?>" class="font-semibold text-gray-800 text-sm hover:text-amber-600"><?= e($req['display_name']) ?></a></div>
                                        <div class="flex gap-2">
                                            <form method="post" action="/api/friends.php" onsubmit="return gcFriendSubmit(event, this);">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="friend_email" value="<?= e($req['email']) ?>">
                                                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold hover:bg-emerald-200">
                                                    <?= icon('check', 'gc-icon-xs') ?> Принять
                                                </button>
                                            </form>
                                            <form method="post" action="/api/friends.php" onsubmit="return gcFriendSubmit(event, this);">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="friend_email" value="<?= e($req['email']) ?>">
                                                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-100 text-rose-600 text-sm font-bold hover:bg-rose-200">
                                                    <?= icon('close', 'gc-icon-xs') ?> Отклонить
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-800 mb-3"><?= icon('settings', 'gc-icon-sm') ?> Настройки уведомлений</h3>
                            <form method="post" class="space-y-3">
                                <input type="hidden" name="csrf3" value="<?= csrf_token() ?>">
                                <input type="hidden" name="save_notifications" value="1">

                                <div class="space-y-2">
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('mail', 'gc-icon-xs') ?> Email-уведомления</span><input type="checkbox" name="email_notifications" <?= $notificationSettings['email_notifications'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('notifications', 'gc-icon-xs') ?> Push-уведомления</span><input type="checkbox" name="push_notifications" <?= $notificationSettings['push_notifications'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('friends', 'gc-icon-xs') ?> Новые заявки в друзья</span><input type="checkbox" name="new_friend_requests" <?= $notificationSettings['new_friend_requests'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('chats', 'gc-icon-xs') ?> Новые комментарии</span><input type="checkbox" name="new_comments" <?= $notificationSettings['new_comments'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('heart', 'gc-icon-xs') ?> Новые лайки</span><input type="checkbox" name="new_likes" <?= $notificationSettings['new_likes'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('send', 'gc-icon-xs') ?> Новые сообщения</span><input type="checkbox" name="new_messages" <?= $notificationSettings['new_messages'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                    <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer"><span class="text-sm"><?= icon('calend', 'gc-icon-xs') ?> Еженедельный дайджест</span><input type="checkbox" name="weekly_digest" <?= $notificationSettings['weekly_digest'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-amber-500"></label>
                                </div>

                                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-100 text-amber-700 font-semibold text-sm hover:bg-amber-200 transition"><?= icon('diskette', 'gc-icon-xs') ?> Сохранить настройки</button>
                            </form>
                        </div>
                    </div>
                </div>

    <?php else: ?>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 inline-flex items-center gap-2 flex-wrap">
                        <span><?= e($u['display_name']??'Пользователь') ?></span>
                        <?php
                        // ✅ Бейджи премиума и про (Вариант Б: показываем оба, если есть оба)
                        $profileIsManualPremium = (int)($u['is_premium'] ?? 0) === 1;
                        $profileHasActiveSub    = check_subscription($u['email'] ?? null);
                        ?>
                        <?php if ($profileIsManualPremium): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-700"
                                  title="Премиум выдан вручную">
                                <?= icon('sparkle', 'gc-icon-sm') ?>Премиум
                            </span>
                        <?php endif; ?>
                        <?php if ($profileHasActiveSub): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700"
                                  title="Активная оплаченная подписка">
                                <?= icon('flame', 'gc-icon-sm') ?> Pro
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-gray-500 mt-1"><?= e($u['bio']??'') ?></p>
                    <div class="flex flex-wrap gap-3 mt-3 text-sm text-gray-500">
                        <?php if(!empty($u['city'])):?><span><?= icon('location', 'gc-icon-xs') ?> <?= e($u['city']) ?></span><?php endif;?>
                        <?php if(!empty($u['birth_date'])):?><span><?= icon('calend', 'gc-icon-xs') ?> <?= date('d.m.Y', strtotime($u['birth_date'])) ?></span><?php endif;?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-8" data-aos="fade-up">
        <div class="bg-white rounded-2xl shadow-md p-4 text-center"><div class="text-2xl font-bold text-amber-600"><?=(int)($stats['total_points']??0)?></div><div class="text-xs text-gray-500">очков</div></div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center"><div class="text-2xl font-bold text-emerald-600"><?=count($my_recipes)?></div><div class="text-xs text-gray-500">рецептов</div></div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center"><div class="text-2xl font-bold text-purple-600"><?=$fusionStats['total']?></div><div class="text-xs text-gray-500">фьюжн</div></div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center"><div class="text-2xl font-bold text-violet-600"><?=(int)($stats['level']??1)?></div><div class="text-xs text-gray-500">уровень</div></div>
        <div class="bg-white rounded-2xl shadow-md p-4 text-center"><div class="text-2xl font-bold text-blue-600"><?=count($friends)?></div><div class="text-xs text-gray-500">друзей</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <!-- Активность -->
            <div data-aos="fade-up">
                <h2 class="text-xl font-extrabold text-gray-800 mb-4"><?= icon('activity', 'gc-icon-sm') ?> Активность</h2>
                <?php if($activity):?>
                    <div class="space-y-3">
                        <?php foreach($activity as $a):?>
                        <div class="bg-white rounded-2xl shadow-md p-4 flex gap-3">
                            <div class="text-2xl">
                                <?php
                                echo match($a['type']) {
                                    'recipe'  => icon('openbook', 'gc-icon-sm'),
                                    'comment' => icon('chats', 'gc-icon-sm'),
                                    'fusion'  => icon('fusion', 'gc-icon-sm'),
                                    default   => icon('star', 'gc-icon-sm'),
                                };
                                ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm"><?=e($a['title'])?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php
                                    if (function_exists('format_activity_time')) {
                                        echo format_activity_time($a['date']);
                                    } else {
                                        $d=new DateTime($a['date']);$n=new DateTime();$df=$n->diff($d);
                                        echo match(true){$df->days==0=>'сегодня',$df->days==1=>'вчера',default=>$df->days.' дн. назад'};
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach;?>
                    </div>
                <?php else:?>
                    <div class="bg-white rounded-2xl shadow-md p-6 text-center text-gray-400">Нет активности</div>
                <?php endif;?>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Достижения -->
            <?php if (!empty($achievements)): ?>
            <div class="bg-white rounded-2xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800"><?= icon('achievements', 'gc-icon-sm') ?> Достижения</h3>
                    <span class="text-xs text-gray-400"><?= count($achievements) ?> шт.</span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($achievements as $ach): ?>
                        <div class="text-center p-2 rounded-xl bg-amber-50 border border-amber-100"
                             title="<?= e($ach['description']) ?>">
                            <div class="text-2xl inline-block"><?= achievement_icon($ach) ?></div>
                            <div class="text-[10px] text-gray-600 mt-1 line-clamp-2"><?= e($ach['title']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="font-bold text-gray-800 mb-3"><?= icon('info', 'gc-icon-sm') ?> Информация</h3>
                <div class="space-y-2 text-sm text-gray-600">
                    <?php if(!empty($u['city'])):?><p><?= icon('location', 'gc-icon-xs') ?> <?=e($u['city'])?></p><?php endif;?>
                    <?php if(!empty($u['birth_date'])):?><p><?= icon('calend', 'gc-icon-xs') ?> <?=date('d.m.Y',strtotime($u['birth_date']))?></p><?php endif;?>
                    <p><?= icon('calend', 'gc-icon-xs') ?> На сайте с <?=date('d.m.Y',strtotime($u['created_at']))?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800"><?= icon('friends', 'gc-icon-sm') ?> Друзья</h3>
                    <a href="<?=url('friends')?>" class="text-sm text-amber-600">Поиск <?= icon('right', 'gc-icon-xs') ?></a>
                </div>
                <?php if($friends):?>
                    <div class="space-y-3">
                        <?php foreach(array_slice($friends,0,5) as $f):?>
                        <a href="<?=url('profile?user='.urlencode($f['email']))?>" class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition">
                            <?= render_avatar(
                                $f,
                                'w-10 h-10 rounded-full object-cover',
                                'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center',
                                'text-lg leading-none'
                            ) ?>
                            <div><p class="text-sm font-semibold"><?=e($f['display_name'])?></p><p class="text-xs text-gray-400"><?=(int)$f['recipe_count']?> рецептов</p></div>
                        </a>
                        <?php endforeach;?>
                    </div>
                <?php else:?>
                    <p class="text-sm text-gray-400 text-center py-3">Нет друзей</p>
                <?php endif;?>
            </div>

            <?php if($isOwnProfile):?>
            <div class="bg-white rounded-2xl shadow-md p-6 space-y-2">
                <h3 class="font-bold text-gray-800 mb-4"><?= icon('menu', 'gc-icon-sm') ?> Меню</h3>
                <a href="<?= url('fusion') ?>" class="block px-3 py-2 rounded-lg hover:bg-purple-50 text-gray-700 text-sm font-semibold transition"><?= icon('fusion', 'gc-icon-xs') ?> Фьюжн-лаборатория</a>
                <a href="<?= url('dietolog') ?>" class="block px-3 py-2 rounded-lg hover:bg-amber-50 text-gray-700 text-sm font-semibold transition"><?= icon('diet', 'gc-icon-xs') ?> Дневник питания</a>
                <a href="<?= url('favorites') ?>" class="block px-3 py-2 rounded-lg hover:bg-amber-50 text-gray-700 text-sm font-semibold transition"><?= icon('bookmark', 'gc-icon-xs') ?> Сохраненные рецепты</a>
                <a href="<?= url('my-cookbooks') ?>" class="block px-3 py-2 rounded-lg hover:bg-amber-50 text-gray-700 text-sm font-semibold transition"><?= icon('cookbook', 'gc-icon-xs') ?> Мои кулинарные книги</a>
                <a href="<?= url('billing') ?>" class="block px-3 py-2 rounded-lg hover:bg-amber-50 text-gray-700 text-sm font-semibold transition"><?= icon('receipt', 'gc-icon-xs') ?> История платежей</a>
                <hr class="my-2">
                <a href="<?= url('logout') ?>" class="block px-3 py-2 rounded-lg hover:bg-rose-50 text-rose-700 text-sm font-semibold transition"><?= icon('logout', 'gc-icon-xs') ?> Выход</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Модальное окно аватаров -->
<?php if ($isOwnProfile): ?>
<div id="avatarModal" class="avatar-modal-overlay" onclick="if(event.target===this)closeAvatarModal()">
    <div class="avatar-modal-content" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800 text-lg">Выбрать аватар</h3>
            <button onclick="closeAvatarModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none"><?= icon('close', 'gc-icon-sm') ?></button>
        </div>

        <form method="post" enctype="multipart/form-data" class="mb-4">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <label class="block border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-amber-300 transition">
                <input type="file" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()">
                <span class="text-sm text-gray-500"><?= icon('upload', 'gc-icon-sm') ?> Загрузить с компьютера</span>
                <span class="block text-xs text-gray-400 mt-1">JPG, PNG, GIF, WebP до 5MB</span>
            </label>
        </form>

        <div class="grid grid-cols-4 gap-3">
            <?php foreach ($gallery_avatars as $av): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="select_avatar" value="<?= e($av) ?>">
                <button type="submit" class="w-full aspect-square rounded-xl overflow-hidden border-2 hover:border-amber-400 transition <?= $avatar_url===$av?'border-amber-500 ring-2 ring-amber-300':'border-transparent' ?>">
                    <img src="<?= e($av) ?>" alt="avatar" class="w-full h-full object-cover">
                </button>
            </form>
            <?php endforeach; ?>
            <?php if (empty($gallery_avatars)): ?><p class="col-span-4 text-center text-gray-400 text-sm py-8">Галерея пуста</p><?php endif; ?>
        </div>
        <button onclick="closeAvatarModal()" class="mt-4 w-full py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition">Закрыть</button>
    </div>
</div>
<script>
function openAvatarModal(){document.getElementById('avatarModal').classList.add('show');document.body.style.overflow='hidden';}
function closeAvatarModal(){document.getElementById('avatarModal').classList.remove('show');document.body.style.overflow='';}

// Вспомогательные функции для фьюжн (если не определены)
if (typeof window.saveFusionRecipe === 'undefined') {
    window.saveFusionRecipe = async function(recipeId, btn) {
        try {
            const res = await fetch('/api/recipes.php?type=fusion&id=' + recipeId);
            // Просто заглушка — реальная логика в fusion.php
            if (confirm('Сохранить рецепт в кулинарную книгу?')) {
                location.href = '/fusion?id=' + recipeId;
            }
        } catch(e) {}
    };
}
if (typeof window.shareFusionRecipe === 'undefined') {
    window.shareFusionRecipe = function(recipeId) {
        const url = location.origin + '/fusion?id=' + recipeId;
        if (navigator.share) {
            navigator.share({url: url, title: 'Фьюжн-рецепт GigaChef'});
        } else {
            navigator.clipboard.writeText(url);
            alert('Ссылка скопирована!');
        }
    };
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>