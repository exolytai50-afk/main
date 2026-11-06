<?php
require_once __DIR__.'/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];
$userId = $user['id'];
require_once __DIR__ . '/../includes/notifications.php';
$userName = $user['display_name'] ?? $userEmail;

// AJAX для поиска пользователей
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    
    $search = trim($_GET['search'] ?? '');
    $sort = $_GET['sort'] ?? 'name';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 30;
    $offset = ($page - 1) * $perPage;
    
    // Показываем ВСЕХ пользователей, кроме себя
    $where = "u.id != ?";
    $params = [$userId];

    // Исключаем тех, кого я заблокировал
    $where .= " AND u.email NOT IN (SELECT blocked_email FROM blacklist WHERE user_email = ?)";
    $params[] = $userEmail;

    // Исключаем тех, кто заблокировал меня
    $where .= " AND u.email NOT IN (SELECT user_email FROM blacklist WHERE blocked_email = ?)";
    $params[] = $userEmail;
    
    if ($search) {
        $where .= " AND (u.display_name LIKE ? OR u.email LIKE ? OR u.city LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $orderBy = match($sort) {
        'recipes' => '(SELECT COUNT(*) FROM recipes WHERE author_id = u.id OR author_email = u.email) DESC',
        'city' => 'u.city ASC',
        'date' => 'u.created_at DESC',
        default => 'u.display_name ASC'
    };
    
    $total = db_one("SELECT COUNT(*) as cnt FROM users u WHERE {$where}", $params);
    
    // Получаем пользователей
    $users = db_all(
        "SELECT u.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender, u.city, u.occupation, u.created_at,
                (SELECT COUNT(*) FROM recipes WHERE author_id = u.id OR author_email = u.email) as recipe_count
         FROM users u
         WHERE {$where}
         ORDER BY {$orderBy}
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    
    // Получаем статусы дружбы для всех найденных пользователей
    if (!empty($users)) {
        $userEmails   = array_column($users, 'email');
        $placeholders = implode(',', array_fill(0, count($userEmails), '?'));
    
        // Все связи между мной и найденными пользователями, в обе стороны
        $pairs = db_all(
            "SELECT user_email, friend_email, status
             FROM friendships
             WHERE (user_email = ? AND friend_email IN ({$placeholders}))
                OR (friend_email = ? AND user_email IN ({$placeholders}))",
            array_merge([$userEmail], $userEmails, [$userEmail], $userEmails)
        );
    
        $friendshipMap = [];
        foreach ($pairs as $p) {
            $iSent = ($p['user_email'] === $userEmail);
            $peer  = $iSent ? $p['friend_email'] : $p['user_email'];
    
            if ($p['status'] === 'accepted') {
                $s = 'accepted';
            } elseif ($p['status'] === 'pending') {
                $s = $iSent ? 'pending' : 'incoming';
            } else {
                continue;
            }
    
            // «Друзья» имеет приоритет над остальными статусами
            if (($friendshipMap[$peer] ?? null) !== 'accepted') {
                $friendshipMap[$peer] = $s;
            }
        }
    
        foreach ($users as &$userData) {
            $userData['friendship_status'] = $friendshipMap[$userData['email']] ?? null;
        }
        unset($userData);
    }
    
    echo json_encode([
        'users' => $users,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $page,
        'pages' => max(1, ceil(($total['cnt'] ?? 0) / $perPage))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Обработка действий с друзьями (запасной путь: срабатывает, если JS отключён)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = trim((string)($_POST['action'] ?? ''));
    $friendEmail = mb_strtolower(trim((string)($_POST['friend_email'] ?? '')));

    if ($action && $friendEmail && $friendEmail !== $userEmail
        && filter_var($friendEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            // Второй пользователь должен существовать
            $peerExists = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$friendEmail]);
            if (!$peerExists) {
                $action = '';
            }

            if ($action === 'add') {
                $blocked = db_one(
                    'SELECT id FROM blacklist
                     WHERE (user_email = ? AND blocked_email = ?)
                        OR (user_email = ? AND blocked_email = ?)
                     LIMIT 1',
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );
                $exists = db_one(
                    'SELECT id FROM friendships
                     WHERE (user_email = ? AND friend_email = ?)
                        OR (user_email = ? AND friend_email = ?)',
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );
                if (!$blocked && !$exists) {
                    db_insert(
                        'INSERT INTO friendships (user_email, friend_email, status) VALUES (?, ?, "pending")',
                        [$userEmail, $friendEmail]
                    );

                    // Убираем старые уведомления о дружбе у получателя,
                    // иначе антиспам notify_user (10 минут) проглотит новое
                    db_exec(
                        "DELETE FROM notifications
                         WHERE user_email = ? AND related_email = ?
                           AND type IN ('friend_request','friend_accepted','friend_removed')",
                        [$friendEmail, $userEmail]
                    );

                    notify_user(
                        $friendEmail,
                        'friend_request',
                        'Новая заявка в друзья',
                        "{$userName} хочет добавить вас в друзья",
                        $userEmail,
                        url('profile?user=' . urlencode($userEmail))
                    );
                }

            } elseif ($action === 'accept') {
                // Принять можно только входящую pending-заявку
                $incoming = db_one(
                    "SELECT id FROM friendships
                     WHERE user_email = ? AND friend_email = ? AND status = 'pending'
                     ORDER BY id DESC LIMIT 1",
                    [$friendEmail, $userEmail]
                );
                if ($incoming) {
                    db_exec(
                        "UPDATE friendships SET status = 'accepted' WHERE id = ?",
                        [$incoming['id']]
                    );

                    // У меня заявка больше не актуальна
                    db_exec(
                        "DELETE FROM notifications
                         WHERE user_email = ? AND related_email = ? AND type = 'friend_request'",
                        [$userEmail, $friendEmail]
                    );

                    // Автору заявки: «принята»
                    db_exec(
                        "DELETE FROM notifications
                         WHERE user_email = ? AND related_email = ?
                           AND type IN ('friend_accepted','friend_removed')",
                        [$friendEmail, $userEmail]
                    );
                    notify_user(
                        $friendEmail,
                        'friend_accepted',
                        'Заявка принята',
                        "{$userName} принял(а) вашу заявку в друзья",
                        $userEmail,
                        url('profile?user=' . urlencode($userEmail))
                    );
                }

            } elseif ($action === 'reject') {
                // Только pending: принятую дружбу этой веткой не удаляем
                db_exec(
                    "DELETE FROM friendships
                     WHERE status = 'pending'
                       AND ((user_email = ? AND friend_email = ?)
                         OR (user_email = ? AND friend_email = ?))",
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );
                db_exec(
                    "DELETE FROM notifications
                     WHERE user_email = ? AND related_email = ? AND type = 'friend_request'",
                    [$userEmail, $friendEmail]
                );

            } elseif ($action === 'remove') {
                db_exec(
                    'DELETE FROM friendships
                     WHERE (user_email = ? AND friend_email = ?)
                        OR (user_email = ? AND friend_email = ?)',
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );

                // Сначала чистим, потом уведомляем (в api было наоборот)
                db_exec(
                    "DELETE FROM notifications
                     WHERE type IN ('friend_request','friend_accepted','friend_removed')
                       AND ((user_email = ? AND related_email = ?)
                         OR (user_email = ? AND related_email = ?))",
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );
                notify_user(
                    $friendEmail,
                    'friend_removed',
                    'Друг удалён',
                    "{$userName} удалил(а) вас из друзей",
                    $userEmail,
                    null
                );

            } elseif ($action === 'block') {
                $alreadyBlocked = db_one(
                    'SELECT id FROM blacklist WHERE user_email = ? AND blocked_email = ?',
                    [$userEmail, $friendEmail]
                );
                if (!$alreadyBlocked) {
                    db_insert(
                        'INSERT INTO blacklist (user_email, blocked_email) VALUES (?, ?)',
                        [$userEmail, $friendEmail]
                    );
                }
                db_exec(
                    'DELETE FROM friendships
                     WHERE (user_email = ? AND friend_email = ?)
                        OR (user_email = ? AND friend_email = ?)',
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );
                db_exec(
                    "DELETE FROM notifications
                     WHERE type IN ('friend_request','friend_accepted','friend_removed')
                       AND ((user_email = ? AND related_email = ?)
                         OR (user_email = ? AND related_email = ?))",
                    [$userEmail, $friendEmail, $friendEmail, $userEmail]
                );

            } elseif ($action === 'unblock') {
                db_exec(
                    'DELETE FROM blacklist WHERE user_email = ? AND blocked_email = ?',
                    [$userEmail, $friendEmail]
                );
            }
        } catch (Throwable $e) {
            error_log('friends action error: ' . $e->getMessage());
        }
    }

    // Единый сброс кэшей для обоих участников
    if ($friendEmail !== '') {
        flush_friends_cache($userEmail, $friendEmail);
    }

    header('Location: '.url('friends'));
    exit;
}

// Список друзей
$myFriends = db_all(
    "SELECT u.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender, u.bio, u.city, u.birth_date, u.occupation,
            (SELECT COUNT(*) FROM recipes WHERE author_id = u.id OR author_email = u.email) as recipe_count
     FROM friendships f 
     JOIN users u ON u.email = CASE 
         WHEN f.user_email = ? THEN f.friend_email 
         ELSE f.user_email 
     END
     WHERE (f.user_email = ? OR f.friend_email = ?) 
       AND f.status = 'accepted'
     ORDER BY u.display_name",
    [$userEmail, $userEmail, $userEmail]
);

// Входящие заявки
$incomingRequests = db_all(
    "SELECT f.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, f.created_at
     FROM friendships f 
     JOIN users u ON u.email = f.user_email
     WHERE f.friend_email = ? AND f.status = 'pending'
     ORDER BY f.created_at DESC",
    [$userEmail]
);

// Заблокированные пользователи (чёрный список)
$blockedUsers = db_all(
    "SELECT u.email, u.display_name, u.avatar_emoji, u.avatar_url, b.created_at as blocked_at
     FROM blacklist b
     JOIN users u ON u.email = b.blocked_email
     WHERE b.user_email = ?
     ORDER BY b.created_at DESC",
    [$userEmail]
);

// Мета-теги
$meta = get_page_meta('/friends');
if ($meta) {
    apply_meta($meta);
}

$pageTitle = $GLOBALS['pageTitle'] ?? 'Друзья — поиск и общение | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Друзья';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Поиск друзей в GigaChef. Находите единомышленников, обменивайтесь рецептами и опытом.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__.'/../includes/header.php';
?>

<style>
/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
</style>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="friendsPage()">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 flex items-center gap-2" data-aos="fade-up">
        <span class="text-amber-500 shrink-0 inline-flex items-center justify-center">
            <?= icon('friends', 'gc-icon-xl', 1.5) ?>
        </span>
        <span><?= e($pageH1) ?></span>
    </h1>
    
    <div class="flex gap-2 border-b border-gray-200 mb-6" data-aos="fade-up">
        <button class="px-4 py-2.5 border-b-2 text-sm font-semibold transition inline-flex items-center gap-1.5"
                @click="activeTab = 'friends'"
                :class="activeTab === 'friends' ? 'border-amber-400 text-amber-700' : 'border-transparent text-gray-400'">
            <?= icon('friends') ?>
            <span>Друзья (<?= count($myFriends) ?>)</span>
        </button>
        <button class="px-4 py-2.5 border-b-2 text-sm font-semibold transition inline-flex items-center gap-1.5"
                @click="activeTab = 'add'; loadUsers()"
                :class="activeTab === 'add' ? 'border-amber-400 text-amber-700' : 'border-transparent text-gray-400'">
            <?= icon('plus') ?>
            <span>Найти людей</span>
        </button>
        <?php if ($incomingRequests): ?>
        <button class="px-4 py-2.5 border-b-2 text-sm font-semibold transition relative inline-flex items-center gap-1.5"
                @click="activeTab = 'requests'"
                :class="activeTab === 'requests' ? 'border-amber-400 text-amber-700' : 'border-transparent text-gray-400'">
            <?= icon('notifications') ?>
            <span>Заявки</span>
            <span class="absolute -top-1 -right-2 bg-rose-500 text-white text-[10px] font-bold rounded-full h-[18px] min-w-[18px] px-1 flex items-center justify-center">
                <?= count($incomingRequests) ?>
            </span>
        </button>
        <?php endif; ?>
        <?php if (!empty($blockedUsers)): ?>
        <button class="px-4 py-2.5 border-b-2 text-sm font-semibold transition inline-flex items-center gap-1.5"
                @click="activeTab = 'blocked'"
                :class="activeTab === 'blocked' ? 'border-rose-400 text-rose-700' : 'border-transparent text-gray-400'">
            <?= icon('lock') ?>
            <span>Чёрный список</span>
            <span class="ml-1 bg-rose-100 text-rose-600 text-[10px] font-bold rounded-full px-1.5">
                <?= count($blockedUsers) ?>
            </span>
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Вкладка: Друзья -->
    <div x-show="activeTab === 'friends'" data-aos="fade-up">
        <?php if (empty($myFriends)): ?>
            <div class="text-center py-16">
                <div class="mb-4 flex justify-center text-amber-400">
                    <?= icon('friends', 'gc-icon-xl', 4) ?>
                </div>
                <p class="text-gray-400">У вас пока нет друзей</p>
                <button class="mt-4 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200 transition"
                        @click="activeTab = 'add'; loadUsers()">
                    Найти людей
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($myFriends as $friend): ?>
                <div class="bg-white rounded-2xl shadow-md p-4">
                    <div class="flex items-center gap-3">
                        <a href="<?= url('user/'.urlencode($friend['email'])) ?>">
                            <?= render_avatar(
                                $friend,
                                'w-10 h-10 rounded-full object-cover',
                                'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600',
                                'text-lg'
                            ) ?>
                        </a>
                        <div class="flex-1 min-w-0">
                            <a href="<?= url('user/'.urlencode($friend['email'])) ?>" class="block font-bold text-sm text-gray-800 hover:text-amber-600 truncate">
                                <?= e($friend['display_name'] ?? $friend['email']) ?>
                            </a>
                            <div class="text-xs text-gray-400 mt-0.5"><?= (int)$friend['recipe_count'] ?> рецептов</div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2 mt-3">
                        <a href="<?= url('chat/'.urlencode($friend['email'])) ?>"
                           class="flex-1 py-2 rounded-xl text-center bg-amber-100 text-amber-700 text-xs font-bold hover:bg-amber-200 transition inline-flex items-center justify-center gap-1.5">
                            <?= icon('chats') ?>
                            <span>Написать</span>
                        </a>
                        <form method="post">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="friend_email" value="<?= e($friend['email']) ?>">
                            <button class="px-3 py-2 rounded-xl bg-rose-50 text-rose-500 text-xs hover:bg-rose-100 transition inline-flex items-center justify-center"
                                    title="Удалить" aria-label="Удалить">
                                <?= icon('close') ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Заблокировать пользователя? Он будет удалён из друзей и не сможет вам писать.');">
                            <input type="hidden" name="action" value="block">
                            <input type="hidden" name="friend_email" value="<?= e($friend['email']) ?>">
                            <button class="px-3 py-2 rounded-xl bg-gray-100 text-gray-500 text-xs hover:bg-gray-200 transition inline-flex items-center justify-center"
                                    title="Заблокировать" aria-label="Заблокировать">
                                <?= icon('lock') ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Вкладка: Найти людей -->
    <div x-show="activeTab === 'add'" style="display:none">
        <div class="bg-white rounded-2xl shadow-md p-4 mb-6">
            <div class="flex gap-3 mb-4">
                <input type="text" x-model="search" @input.debounce.300ms="loadUsers()" 
                       placeholder="Поиск по имени, email или городу..."
                       class="flex-1 px-4 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-amber-300">
                <button @click="loadUsers()" 
                        class="px-5 py-2 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm inline-flex items-center gap-1.5 hover:bg-amber-200 transition">
                    <?= icon('search') ?>
                    <span>Найти</span>
                </button>
            </div>
            
            <!-- Фильтры сортировки -->
            <div class="flex gap-2 flex-wrap">
                <button @click="sort = 'name'; loadUsers()" 
                        :class="sort === 'name' ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                    По имени
                </button>
                <button @click="sort = 'recipes'; loadUsers()" 
                        :class="sort === 'recipes' ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                    По рецептам
                </button>
                <button @click="sort = 'city'; loadUsers()" 
                        :class="sort === 'city' ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                    По городу
                </button>
                <button @click="sort = 'date'; loadUsers()" 
                        :class="sort === 'date' ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                    По дате
                </button>
            </div>
        </div>
        
        <div x-show="loading" class="text-center py-10 text-gray-400">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-amber-500"></div>
            <p class="mt-2">Загрузка...</p>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="!loading && users.length > 0">
            <template x-for="u in users" :key="u.id">
                <div class="bg-white rounded-2xl shadow-md p-4 flex items-center gap-3">
                    <a :href="'/user/' + encodeURIComponent(u.email)" class="shrink-0">
                        <img :src="u.avatar_url" x-show="u.avatar_url" class="w-12 h-12 rounded-full object-cover">
                        <div x-show="!u.avatar_url" class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-amber-600"
                             x-html="window.getAvatarSvg(u.gender)"></div>
                    </a>
                    <div class="flex-1 min-w-0">
                        <a :href="'/user/' + encodeURIComponent(u.email)" 
                           class="block text-sm font-semibold text-gray-800 hover:text-amber-600 truncate" 
                           x-text="u.display_name || u.email"></a>
                        <div class="text-xs text-gray-400" x-text="u.recipe_count + ' рецептов' + (u.city ? ' · ' + u.city : '')"></div>
                    </div>
                    <div class="shrink-0">
                        <!-- 1. Заявки нет — кнопка «Друг» -->
                        <template x-if="!u.friendship_status">
                            <form method="post" @submit.prevent="friendAction($event, u)">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="friend_email" :value="u.email">
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-700 text-xs font-bold hover:bg-amber-200 inline-flex items-center gap-1 transition">
                                    <?= icon('plus') ?>
                                    <span>Друг</span>
                                </button>
                            </form>
                        </template>

                        <!-- 2. Я подал заявку, ждём ответа -->
                        <template x-if="u.friendship_status === 'pending'">
                            <span class="px-3 py-1.5 rounded-lg bg-amber-50 text-amber-600 text-xs font-bold inline-flex items-center gap-1">
                                <?= icon('hourglass') ?>
                                <span>Заявка отправлена</span>
                            </span>
                        </template>

                        <!-- 3. Мне подали заявку — можно принять -->
                        <template x-if="u.friendship_status === 'incoming'">
                            <form method="post" @submit.prevent="friendAction($event, u)">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="friend_email" :value="u.email">
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 inline-flex items-center gap-1 transition">
                                    <?= icon('check') ?>
                                    <span>Принять</span>
                                </button>
                            </form>
                        </template>

                        <!-- 4. Уже друзья -->
                        <template x-if="u.friendship_status === 'accepted'">
                            <span class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-bold inline-flex items-center gap-1">
                                <?= icon('check') ?>
                                <span>Друзья</span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        
        <div class="flex justify-center gap-2 mt-6" x-show="pages > 1">
            <button @click="page--; loadUsers()" :disabled="page <= 1" 
                    class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm disabled:opacity-50 inline-flex items-center">
                <?= icon('left') ?>
            </button>
            <span class="px-4 py-2 text-sm text-gray-500" x-text="'Стр. ' + page + ' из ' + pages + ' (' + total + ' чел.)'"></span>
            <button @click="page++; loadUsers()" :disabled="page >= pages" 
                    class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm disabled:opacity-50 inline-flex items-center">
                <?= icon('right') ?>
            </button>
        </div>
        
        <p x-show="!loading && users.length === 0" class="text-center py-10 text-gray-400">
            Пользователи не найдены
        </p>
    </div>
    
    <!-- Вкладка: Заявки -->
    <div x-show="activeTab === 'requests'" style="display:none">
        <?php if (empty($incomingRequests)): ?>
            <div class="text-center py-16">
                <p class="text-gray-400">Нет входящих заявок</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($incomingRequests as $req): ?>
                <div class="bg-white rounded-2xl shadow-md p-4 flex items-center gap-4">
                    <?= render_avatar(
                        $req,
                        'w-12 h-12 rounded-full object-cover shrink-0',
                        'w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center shrink-0 text-amber-600',
                        'text-xl'
                    ) ?>
                    
                    <div class="flex-1">
                        <a href="<?= url('user/'.urlencode($req['email'])) ?>" class="text-sm font-semibold text-gray-800 hover:text-amber-600">
                            <?= e($req['display_name']) ?>
                        </a>
                    </div>
                    
                    <div class="flex gap-2 shrink-0">
                        <form method="post">
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="friend_email" value="<?= e($req['email']) ?>">
                            <button class="px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 transition inline-flex items-center gap-1">
                                <?= icon('check') ?>
                                <span>Принять</span>
                            </button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="friend_email" value="<?= e($req['email']) ?>">
                            <button class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-600 text-xs font-bold hover:bg-rose-200 transition inline-flex items-center gap-1">
                                <?= icon('close') ?>
                                <span>Отклонить</span>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Вкладка: Чёрный список -->
    <div x-show="activeTab === 'blocked'" style="display:none">
        <?php if (empty($blockedUsers)): ?>
            <div class="text-center py-16">
                <div class="mb-4 flex justify-center text-gray-300">
                    <?= icon('lock', 'gc-icon-xl', 4) ?>
                </div>
                <p class="text-gray-400">Чёрный список пуст</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($blockedUsers as $b): ?>
                    <div class="bg-white rounded-2xl shadow-md p-4 flex items-center gap-4">
                        <?= render_avatar(
                            $b,
                            'w-12 h-12 rounded-full object-cover shrink-0',
                            'w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center shrink-0 text-gray-500',
                            'text-xl'
                        ) ?>

                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-800 truncate">
                                <?= e($b['display_name'] ?: $b['email']) ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                Заблокирован <?= date('d.m.Y', strtotime($b['blocked_at'])) ?>
                            </div>
                        </div>

                        <form method="post" class="shrink-0" onsubmit="return confirm('Разблокировать пользователя?');">
                            <input type="hidden" name="action" value="unblock">
                            <input type="hidden" name="friend_email" value="<?= e($b['email']) ?>">
                            <button class="px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 transition">
                                Разблокировать
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('friendsPage', () => ({
        activeTab: 'friends',
        users: [], loading: false, search: '', sort: 'name',
        page: 1, pages: 1, total: 0,

        init() {
            document.addEventListener('gigachef:counters', () => this.refreshLists());
        },

        async loadUsers(silent = false) {
            if (!silent) { this.loading = true; this.users = []; }
            try {
                const params = new URLSearchParams({
                    ajax: '1', search: this.search, sort: this.sort, page: this.page
                });
                const response = await fetch('/friends?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                this.users = data.users || [];
                this.pages = data.pages || 1;
                this.total = data.total || 0;
            } catch (e) {
                console.error('Error:', e);
                if (!silent) { this.users = []; this.total = 0; this.pages = 1; }
            } finally {
                this.loading = false;
            }
        },

        async friendAction(ev, u = null) {
            const form = ev.target;
            const msg = form.dataset.confirm;
            if (msg && !confirm(msg)) return;
            const action = new FormData(form).get('action');
            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;
            try {
                const r = await fetch('/api/friends.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    body: new FormData(form)
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || d.error) throw new Error(d.error || ('HTTP ' + r.status));
                if (u) {
                    if (action === 'add') {
                        u.friendship_status = d.status === 'accepted' ? 'accepted'
                            : (d.direction === 'incoming' ? 'incoming' : 'pending');
                    } else if (action === 'accept') {
                        u.friendship_status = 'accepted';
                    }
                }
                await this.refreshLists();
            } catch (e) {
                alert(e.message || 'Ошибка');
                if (btn) btn.disabled = false;
            }
        },

        async refreshLists() {
            try {
                const r = await fetch('/friends', {credentials: 'same-origin'});
                if (!r.ok) return;
                const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                ['gc-tabbar', 'gc-panel-friends', 'gc-panel-requests', 'gc-panel-blocked'].forEach(id => {
                    const fresh = doc.getElementById(id), old = document.getElementById(id);
                    if (!fresh || !old) return;
                    const node = document.importNode(fresh, true);
                    node.removeAttribute('data-aos');
                    old.replaceWith(node);
                });
                if (this.activeTab === 'add' && !this.loading) this.loadUsers(true);
            } catch (e) {}
        }
    }));
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>