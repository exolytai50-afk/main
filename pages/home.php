<?php
require_once __DIR__ . '/../config/config.php';

/* ═══════════════════════════════════════════════════════════════
   1. ОПРЕДЕЛЯЕМ, ЧЬЮ ЛЕНТУ СМОТРИМ
   ═══════════════════════════════════════════════════════════════ */
$profileUser    = $_GET['user'] ?? null;
$isOwnProfile   = !$profileUser || $profileUser === current_user()['email'];

if ($isOwnProfile) {
    $currentUser      = current_user();
    $currentUserEmail = $currentUser['email'];
    $homeMeta         = get_page_meta('/');
    if ($homeMeta) apply_meta($homeMeta);
} else {
    $decodedUser = urldecode($profileUser);
    $currentUser = db_one('SELECT * FROM users WHERE email = ?', [$decodedUser]);
    if (!$currentUser) {
        $currentUser = db_one('SELECT * FROM users WHERE display_name = ?', [$decodedUser]);
    }
    if (!$currentUser) {
        http_response_code(404);
        $pageTitle = 'Пользователь не найден';
        require_once __DIR__ . '/../includes/header.php';
        echo '<div class="text-center py-20">'
            . '<h1 class="text-2xl font-bold text-gray-600">Пользователь не найден</h1>'
            . '<p class="text-gray-500 mt-2">' . e($decodedUser) . '</p>'
            . '<a href="' . url('friends') . '" class="inline-block mt-4 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold">Найти друзей</a>'
            . '</div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
    $currentUserEmail = $currentUser['email'];
}

/* ═══════════════════════════════════════════════════════════════
   2. СПИСОК ДРУЗЕЙ
   ═══════════════════════════════════════════════════════════════ */
$friendEmails = db_all(
    "SELECT friend_email FROM friendships WHERE user_email = ? AND status = 'accepted'
     UNION
     SELECT user_email FROM friendships WHERE friend_email = ? AND status = 'accepted'",
    [$currentUserEmail, $currentUserEmail]
);
$friendsList   = array_map(fn($f) => $f['friend_email'], $friendEmails);
$friendsList[] = $currentUserEmail;

/* ═══════════════════════════════════════════════════════════════
   3. ОПРЕДЕЛЯЕМ КОЛОНКУ ПОЛЬЗОВАТЕЛЯ В activity_feed
   ═══════════════════════════════════════════════════════════════ */
$columns     = db_all("SHOW COLUMNS FROM activity_feed");
$columnNames = array_column($columns, 'Field');
$userColumn  = 'user_email'; // дефолт
foreach (['user_email', 'author_email', 'email', 'user_id'] as $candidate) {
    if (in_array($candidate, $columnNames, true)) {
        $userColumn = $candidate;
        break;
    }
}

/* ═══════════════════════════════════════════════════════════════
   4. ПАГИНАЦИЯ: WHERE собираем ОДИН раз
   ═══════════════════════════════════════════════════════════════ */
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$feedWhere  = '1 = 0';
$feedParams = [];

if (!empty($friendsList) && $userColumn !== 'user_id') {
    $placeholders = implode(',', array_fill(0, count($friendsList), '?'));
    $feedWhere    = "af.{$userColumn} IN ({$placeholders})";
    $feedParams   = $friendsList;
} elseif (!empty($friendsList) && $userColumn === 'user_id') {
    $ph    = implode(',', array_fill(0, count($friendsList), '?'));
    $ids   = db_all("SELECT id FROM users WHERE email IN ({$ph})", $friendsList);
    $idList = array_column($ids, 'id');
    if (!empty($idList)) {
        $ip          = implode(',', array_fill(0, count($idList), '?'));
        $feedWhere   = "af.user_id IN ({$ip})";
        $feedParams  = $idList;
    }
}

/* ═══════════════════════════════════════════════════════════════
   5. ЛЕНТА: страница N
   ═══════════════════════════════════════════════════════════════ */
$feed = [];
if ($feedWhere !== '1 = 0') {
    $join = $userColumn === 'user_id'
        ? "JOIN users u ON u.id = af.user_id"
        : "JOIN users u ON u.email = af.{$userColumn}";

    $sql = "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url, u.gender, u.email AS user_email,
               r.title AS recipe_title, r.slug AS recipe_slug, r.image_url AS recipe_image,
               r.description AS recipe_description, r.prep_time, r.cook_time, r.servings,
               r.calories, r.likes_count, r.views_count,
               c.slug     AS category_slug,
               c.name     AS category_name,
               c.icon_svg AS category_icon
        FROM activity_feed af
        {$join}
        LEFT JOIN recipes r ON af.reference_type = 'recipe' AND r.id = af.reference_id
        LEFT JOIN recipe_categories c ON c.id = r.category_id
        WHERE {$feedWhere}
        ORDER BY af.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";
$feed = db_all($sql, $feedParams);

// Загружаем теги для всех рецептов ленты одним запросом
$feedRecipeIds = [];
foreach ($feed as $item) {
    if (!empty($item['recipe_slug']) && !empty($item['reference_id']) && $item['reference_type'] === 'recipe') {
        $feedRecipeIds[] = (int)$item['reference_id'];
    }
}
if (!empty($feedRecipeIds)) {
    $ph = implode(',', array_fill(0, count($feedRecipeIds), '?'));
    $rows = db_all(
        "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l
         JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
         ORDER BY t.tag_group, t.sort_order, t.slug",
        $feedRecipeIds
    );
    $byRecipe = [];
    foreach ($rows as $row) {
        $byRecipe[$row['recipe_id']][] = [
            'id'        => (int)$row['id'],
            'slug'      => $row['slug'],
            'name'      => $row['name'],
            'icon_svg'  => $row['icon_svg'],
            'tag_group' => $row['tag_group'],
        ];
    }
    foreach ($feed as &$item) {
        $rid = (int)($item['reference_id'] ?? 0);
        $item['tags_list'] = $byRecipe[$rid] ?? [];
    }
    unset($item);
}
}

/* ═══════════════════════════════════════════════════════════════
   6. ВСЕГО ЗАПИСЕЙ — для пагинации
   ═══════════════════════════════════════════════════════════════ */
$totalCount = 0;
if ($feedWhere !== '1 = 0') {
    $join = $userColumn === 'user_id'
        ? "JOIN users u ON u.id = af.user_id"
        : "JOIN users u ON u.email = af.{$userColumn}";

    $row = db_one(
        "SELECT COUNT(*) AS cnt
         FROM activity_feed af
         {$join}
         WHERE {$feedWhere}",
        $feedParams
    );
    $totalCount = (int)($row['cnt'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalCount / $perPage));

/* ═══════════════════════════════════════════════════════════════
   7. НОВОСТИ
   ═══════════════════════════════════════════════════════════════ */
$latestNews = db_all(
    "SELECT id, title, content, image_url, created_at, is_pinned
     FROM news
     WHERE is_published = 1
     ORDER BY is_pinned DESC, created_at DESC
     LIMIT 3"
);

/* ═══════════════════════════════════════════════════════════════
   8. ИСТОРИИ
   ═══════════════════════════════════════════════════════════════ */
$storiesForFeed = db_all(
    "SELECT s.*, u.display_name AS user_name, u.avatar_url, u.gender
     FROM stories s
     LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = s.user_email COLLATE utf8mb4_unicode_ci
     WHERE s.created_at > DATE_SUB(NOW(), INTERVAL 168 HOUR)
     ORDER BY s.created_at DESC
     LIMIT 20"
);

/* ═══════════════════════════════════════════════════════════════
   9. ДРУЗЬЯ ДЛЯ БЛОКА «Друзья»
   ═══════════════════════════════════════════════════════════════ */
$userFriends = db_all(
    "SELECT u.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
            (SELECT COUNT(*) FROM recipes WHERE author_id = u.id OR author_email = u.email) AS recipe_count
     FROM friendships f
     JOIN users u ON u.email = CASE WHEN f.user_email = ? THEN f.friend_email ELSE f.user_email END
     WHERE (f.user_email = ? OR f.friend_email = ?)
       AND f.status = 'accepted'
     ORDER BY u.display_name",
    [$currentUserEmail, $currentUserEmail, $currentUserEmail]
);

/* ═══════════════════════════════════════════════════════════════
   10. СОХРАНЁННЫЕ РЕЦЕПТЫ
   ═══════════════════════════════════════════════════════════════ */
$userSavedRecipes = [];
try {
    $userSavedRecipes = db_all(
        "SELECT r.id, r.slug, r.title, r.image_url, r.likes_count, r.views_count, r.created_at,
                r.description, r.cuisine, r.difficulty, r.prep_time, r.cook_time, r.servings, r.calories,
                c.slug     AS category_slug,
                c.name     AS category_name,
                c.icon_svg AS category_icon
         FROM favorites f
         INNER JOIN recipes r ON r.id = f.recipe_id
         LEFT JOIN recipe_categories c ON c.id = r.category_id
         WHERE f.user_email = ?
         ORDER BY f.created_at DESC
         LIMIT 12",
        [$currentUserEmail]
    );

    // Загружаем теги одним запросом
    if (!empty($userSavedRecipes)) {
        $ids = array_column($userSavedRecipes, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all(
            "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
             FROM recipe_tag_links l
             JOIN recipe_tags t ON t.id = l.tag_id
             WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
             ORDER BY t.tag_group, t.sort_order, t.slug",
            $ids
        );
        $byRecipe = [];
        foreach ($rows as $row) {
            $byRecipe[$row['recipe_id']][] = [
                'id'        => (int)$row['id'],
                'slug'      => $row['slug'],
                'name'      => $row['name'],
                'icon_svg'  => $row['icon_svg'],
                'tag_group' => $row['tag_group'],
            ];
        }
        foreach ($userSavedRecipes as &$r) {
            $r['tags_list'] = $byRecipe[$r['id']] ?? [];
        }
        unset($r);
    }
} catch (Throwable $e) {
    error_log('Error fetching saved recipes: ' . $e->getMessage());
}

/* ═══════════════════════════════════════════════════════════════
   11. КУЛИНАРНЫЕ КНИГИ
   ═══════════════════════════════════════════════════════════════ */
$userCookbooks = [];
try {
    $userCookbooks = db_all(
        "SELECT id, slug, title, description, author_email, created_at
         FROM cookbooks
         WHERE author_email = ?
         ORDER BY created_at DESC
         LIMIT 6",
        [$currentUserEmail]
    );
} catch (Throwable $e) {
    error_log('Error fetching cookbooks: ' . $e->getMessage());
}

/* ═══════════════════════════════════════════════════════════════
   12. ЗАГОЛОВКИ
   ═══════════════════════════════════════════════════════════════ */
if ($isOwnProfile) {
    $pageTitle   = $GLOBALS['pageTitle']  ?? 'Лента — GigaChef';
    $pageH1      = $GLOBALS['pageH1']     ?? 'Лента';
    $displayName = $currentUser['display_name'] ?? 'Моя лента';
} else {
    $pageTitle   = ($currentUser['display_name'] ?? 'Пользователь') . ' — лента | GigaChef';
    $pageH1      = $currentUser['display_name'] ?? 'Пользователь';
    $displayName = $currentUser['display_name'] ?? 'Пользователь';
}
$GLOBALS['pageH1']    = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;

/* ═══════════════════════════════════════════════════════════════
   12.1. КОММЕНТАРИИ: схема БД + счётчики для всей страницы одним запросом
   ═══════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/gc_social.php';
gc_schema_ensure();
gc_prefetch_counts('feed', $feed);

/* ═══════════════════════════════════════════════════════════════
   13. ВЫВОД
   ═══════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/header.php';
?>
<script>
    // Передаем статус премиума в JS (работает для infinite-feed.js)
    window.__IS_PREMIUM_USER__ = <?= (function_exists('is_premium') && is_premium()) ? 'true' : 'false' ?>;
</script>
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ bottomTab: null, showProfileInfo: false }">

  <h1 class="sr-only"><?= e($pageH1) ?></h1>

  <!-- Профиль пользователя -->
  <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-3xl shadow-md p-8 mb-6" data-aos="fade-up">
    <div class="flex items-center gap-4 flex-wrap">
        <?= render_avatar(
    [
        'avatar_url'   => $currentUser['avatar_url'] ?? null,
        'avatar_emoji' => $currentUser['avatar_emoji'] ?? null,
        'gender'       => $currentUser['gender'] ?? null, 
    ],
    'w-12 h-12 rounded-full object-cover ring-2 ring-amber-200',
    'w-12 h-12 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
    'text-2xl'
) ?>
      <div class="min-w-0">
        <span class="text-2xl font-bold text-gray-600 block truncate"><?= e($displayName) ?></span>
        <?php if (!empty($currentUser['city'])): ?>
        <p class="text-gray-500 inline-flex items-center gap-1">
          <?= icon('location') ?>
          <?= e($currentUser['city']) ?>
        </p>
        <?php endif; ?>
      </div>
    </div>

    
<?php if (!$isOwnProfile): ?>
<div class="flex gap-2 mt-4 flex-wrap">
    <?php
        $myEmail = current_user()['email'];
    
        // Одна связь в любую сторону; принятая приоритетнее
        $rel = db_one(
            "SELECT user_email, status
             FROM friendships
             WHERE (user_email = ? AND friend_email = ?)
                OR (user_email = ? AND friend_email = ?)
             ORDER BY (status = 'accepted') DESC, id DESC
             LIMIT 1",
            [$myEmail, $currentUserEmail, $currentUserEmail, $myEmail]
        );
    
        $friendshipStatus = null;
        if ($rel) {
            if ($rel['status'] === 'accepted') {
                $friendshipStatus = 'accepted';           // друзья
            } elseif ($rel['user_email'] === $myEmail) {
                $friendshipStatus = 'pending';            // я отправил заявку
            } else {
                $friendshipStatus = 'incoming';           // мне прислали заявку
            }
        }
    ?>

    <?php if ($friendshipStatus === 'accepted'): ?>

        <span class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold inline-flex items-center gap-1.5">
            <?= icon('check') ?>
            Друзья
        </span>

    <?php elseif ($friendshipStatus === 'pending'): ?>

        <span class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 text-sm font-bold inline-flex items-center gap-1.5">
            <?= icon('time') ?>
            Заявка отправлена
        </span>

    <?php elseif ($friendshipStatus === 'incoming'): ?>

        <form method="post"
              action="/api/friends.php"
              onsubmit="return gcFriendSubmit(event, this);">

            <input type="hidden" name="action" value="accept">

            <input type="hidden"
                   name="friend_email"
                   value="<?= e($currentUserEmail) ?>">

            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold hover:bg-emerald-200">
                <?= icon('check', 'gc-icon-xs') ?> Принять
            </button>
        </form>

    <?php else: ?>

        <form method="post"
              action="/api/friends.php"
              onsubmit="return gcFriendSubmit(event, this);">

            <input type="hidden" name="action" value="add">

            <input type="hidden"
                   name="friend_email"
                   value="<?= e($currentUserEmail) ?>">

            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200">
                <?= icon('plus', 'gc-icon-xs') ?> В друзья
            </button>
        </form>

    <?php endif; ?>

    <a href="<?= url('chat/' . urlencode($currentUserEmail)) ?>"
       class="px-4 py-2 rounded-xl bg-blue-100 text-blue-700 text-sm font-bold hover:bg-blue-200 transition inline-flex items-center gap-1.5">
        <?= icon('chats') ?>
        Написать
    </a>
</div>
<?php endif; ?>
  </div>

  <!-- ВКЛАДКА: ЛЕНТА -->
  <div>
    <div class="space-y-4">

      <!-- Истории -->
      <div class="bg-white rounded-2xl shadow-md p-4" data-aos="fade-up">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-bold text-gray-600 text-sm flex items-center gap-2">
            <?= icon('stories') ?>
            <span>Истории</span>
          </h2>
          <a href="<?= url('stories') ?>" class="text-xs text-amber-600 hover:underline">Все →</a>
        </div>
        <div class="flex gap-3 overflow-x-auto no-scrollbar pb-2">
          <?php if (!empty($storiesForFeed)): ?>
            <?php foreach ($storiesForFeed as $story): ?>
            <button type="button"
        onclick="openFeedStory(<?= (int)$story['id'] ?>)"
        class="shrink-0 relative w-24 h-32 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group">
        
    <div class="w-full h-full bg-gray-900 relative">
        <?php if (($story['media_type'] ?? 'image') === 'video'): ?>
            
            <?php if (!empty($story['rutube_id'])): ?>
                <img src="https://pic.rutube.ru/video/<?= e($story['rutube_id']) ?>.jpg"
                     class="w-full h-full object-cover"
                     alt=""
                     loading="lazy"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <video src="<?= e($story['video_url']) ?>" class="w-full h-full object-cover hidden" preload="metadata" muted playsinline></video>
            <?php else: ?>
                <video src="<?= e($story['video_url']) ?>" 
                       class="w-full h-full object-cover" 
                       preload="metadata" 
                       muted 
                       playsinline></video>
            <?php endif; ?>

            <div class="absolute inset-0 flex items-center justify-center bg-black/20 pointer-events-none">
                <div class="w-8 h-8 rounded-full bg-white/80 flex items-center justify-center shadow-md">
                    <?= icon('play', 'gc-icon-sm') ?>
                </div>
            </div>

        <?php else: ?>
            <img src="<?= e($story['image_url'] ?? '/uploads/static/static.jpg') ?>"
                 class="w-full h-full object-cover"
                 alt="<?= e($story['user_name'] ?? 'История') ?>"
                 loading="lazy"
                 onerror="this.src='/uploads/static/static.jpg'">
        <?php endif; ?>
        
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent pointer-events-none"></div>
        
        <div class="absolute bottom-1 left-1.5 right-1.5 flex items-center gap-1">
            <?= render_avatar(
                [
                    'avatar_url' => $story['avatar_url'] ?? null,
                    'gender'     => $story['gender'] ?? null
                ],
                'w-5 h-5 rounded-full object-cover ring-1 ring-white shrink-0',
                'w-5 h-5 rounded-full bg-amber-100 ring-1 ring-white flex items-center justify-center shrink-0 text-amber-600',
                'text-[8px]'
            ) ?>
            <span class="text-white text-[10px] font-semibold truncate"><?= e($story['user_name'] ?? '') ?></span>
        </div>
    </div>
</button>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="shrink-0 w-40 h-32 rounded-xl bg-gray-50 text-gray-600 flex flex-col items-center justify-center text-center px-3">
              <?= icon('video', 'gc-icon-xl') ?>
              <span class="text-xs text-gray-600">Историй пока нет</span>
            </div>
          <?php endif; ?>
          <?php if ($isOwnProfile): ?>
          <a href="<?= url('stories') ?>" class="shrink-0 w-24 h-32 rounded-xl border-2 border-dashed border-amber-300 flex flex-col items-center justify-center text-amber-400 hover:bg-amber-50 transition">
            <?= icon('plus') ?>
            <span class="text-[10px] font-semibold mt-1">Добавить</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Создание поста + вкладки -->
      <div class="bg-white rounded-2xl shadow-md p-4" data-aos="fade-up">
        <?php if ($isOwnProfile): ?>
        <div class="flex items-center gap-3 mb-3">
          <?= render_avatar(
    [
        'avatar_url'   => $currentUser['avatar_url'] ?? null,
        'avatar_emoji' => $currentUser['avatar_emoji'] ?? null,
        'gender'       => $currentUser['gender'] ?? null, 
    ],
    'w-12 h-12 rounded-full object-cover ring-2 ring-amber-200',
    'w-12 h-12 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
    'text-2xl'
) ?>
          <a href="<?= url('search') ?>" class="flex-1 text-left px-4 py-3 rounded-xl bg-gray-50 text-gray-400 text-sm hover:bg-gray-100 transition truncate">
            Найти рецепт...
          </a>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-4 gap-1.5">
          <button @click="bottomTab = bottomTab === 'friends' ? null : 'friends'" :class="bottomTab === 'friends' ? 'bg-blue-100 text-blue-700' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'" class="flex flex-col items-center justify-center py-2 rounded-xl transition text-center">
            <span class="leading-none"><?= icon('friends') ?></span>
            <span class="text-[10px] font-bold mt-1">Друзья</span>
          </button>
          <button @click="bottomTab = bottomTab === 'saved' ? null : 'saved'" :class="bottomTab === 'saved' ? 'bg-rose-100 text-rose-700' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'" class="flex flex-col items-center justify-center py-2 rounded-xl transition text-center">
            <span class="leading-none"><?= icon('bookmark') ?></span>
            <span class="text-[10px] font-bold mt-1">Сохранённые</span>
          </button>
          <button @click="bottomTab = bottomTab === 'books' ? null : 'books'" :class="bottomTab === 'books' ? 'bg-violet-100 text-violet-700' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'" class="flex flex-col items-center justify-center py-2 rounded-xl transition text-center">
            <span class="leading-none"><?= icon('cookbook') ?></span>
            <span class="text-[10px] font-bold mt-1">Книги</span>
          </button>
          <button @click="showProfileInfo = !showProfileInfo" :class="showProfileInfo ? 'bg-amber-100 text-amber-700' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'" class="flex flex-col items-center justify-center py-2 rounded-xl transition text-center">
            <span class="leading-none"><?= icon('info') ?></span>
            <span class="text-[10px] font-bold mt-1">Подробнее</span>
          </button>
        </div>

        <!-- Подробнее -->
        <div x-show="showProfileInfo" x-transition style="display:none" class="mt-3 bg-gray-50 rounded-xl p-4 border border-gray-100" @click.away="showProfileInfo = false">
          <h3 class="font-bold text-gray-600 mb-3">Подробная информация</h3>
          <div class="space-y-2 text-sm">
            <?php if (!empty($currentUser['display_name'])): ?>
              <div><span class="text-xs text-gray-400"><?= icon('user') ?> Имя</span><p class="text-gray-700 font-semibold"><?= e($currentUser['display_name']) ?></p></div>
            <?php endif; ?>
            <?php if (!empty($currentUser['city'])): ?>
              <div><span class="text-xs text-gray-400"><?= icon('location') ?> Город</span><p class="text-gray-700"><?= e($currentUser['city']) ?></p></div>
            <?php endif; ?>
            <?php if (!empty($currentUser['birth_date'])): ?>
              <div><span class="text-xs text-gray-400"><?= icon('balloons') ?>Дата рождения</span><p class="text-gray-700"><?= date('d.m.Y', strtotime($currentUser['birth_date'])) ?></p></div>
            <?php endif; ?>
            <?php if (!empty($currentUser['occupation'])): ?>
              <div><span class="text-xs text-gray-400"><?= icon('work') ?> Занятие</span><p class="text-gray-700"><?= e($currentUser['occupation']) ?></p></div>
            <?php endif; ?>
            <?php if (!empty($currentUser['bio'])): ?>
              <div><span class="text-xs text-gray-400"><?= icon('document') ?> Биография</span><p class="text-gray-700 leading-relaxed"><?= e($currentUser['bio']) ?></p></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Друзья -->
      <div x-show="bottomTab === 'friends'" x-transition style="display:none">
        <div class="bg-white rounded-2xl shadow-md p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-600 inline-flex items-center gap-2"><?= icon('friends') ?> Друзья (<?= count($userFriends) ?>)</h3>
            <?php if ($isOwnProfile): ?>
              <a href="<?= url('friends') ?>" class="text-sm text-amber-600">Поиск →</a>
            <?php endif; ?>
          </div>
          <?php if ($userFriends): ?>
            <div class="space-y-3">
              <?php foreach ($userFriends as $f): ?>
                <a href="<?= url('user/' . urlencode($f['email'])) ?>" class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition">
                  <?= render_avatar(
    $f,                          // ← массив друга из цикла
    'w-10 h-10 rounded-full object-cover',
    'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600',
    'text-lg'
) ?>
                  <div>
                    <p class="text-sm font-semibold"><?= e($f['display_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= (int)$f['recipe_count'] ?> рецептов</p>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-400 text-center py-3">Нет друзей</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Сохранённые -->
      <div x-show="bottomTab === 'saved'" x-transition style="display:none">
        <div class="bg-white rounded-2xl shadow-md p-4">
          <h3 class="font-bold text-gray-600 mb-3 inline-flex items-center gap-2"><?= icon('bookmark') ?> Сохранённые рецепты (<?= count($userSavedRecipes) ?>)</h3>
          <?php if (!empty($userSavedRecipes)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($userSavedRecipes as $recipe): ?>
                <a href="<?= url('recipe/' . ($recipe['slug'] ?? $recipe['id'])) ?>" class="block bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition">
                  <div class="w-full h-32 overflow-hidden bg-gray-100">
                    <img src="<?= e($recipe['image_url'] ?: '/uploads/static/static.jpg') ?>" class="w-full h-full object-cover" loading="lazy" onerror="this.src='/uploads/static/static.jpg'" alt="">
                  </div>
                  <div class="p-3">
                    <h4 class="font-semibold text-gray-600 text-sm clamp-2"><?= e($recipe['title']) ?></h4>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-400 text-center py-3">Нет сохранённых рецептов</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Книги -->
      <div x-show="bottomTab === 'books'" x-transition style="display:none">
        <div class="bg-white rounded-2xl shadow-md p-4">
          <h3 class="font-bold text-gray-600 mb-3 inline-flex items-center gap-2"><?= icon('cookbook') ?> Кулинарные книги (<?= count($userCookbooks) ?>)</h3>
          <?php if (!empty($userCookbooks)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($userCookbooks as $book): ?>
                <a href="<?= url('cookbook/' . ($book['slug'] ?? $book['id'])) ?>" class="block bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-4 hover:shadow-md transition">
                  <h4 class="font-semibold text-gray-600"><?= e($book['title'] ?? 'Кулинарная книга') ?></h4>
                  <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= e($book['description'] ?? '') ?></p>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-400 text-center py-3">Нет кулинарных книг</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Новости -->
      <?php if (!empty($latestNews)): ?>
      <div class="bg-white rounded-2xl shadow-md p-5" data-aos="fade-up">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-600 text-sm inline-flex items-center gap-2">
            <?= icon('news') ?>
            Новости
          </h2>
          <a href="<?= url('news') ?>" class="text-sm text-amber-600 hover:underline">Все →</a>
        </div>
        <div class="space-y-3">
          <?php foreach ($latestNews as $newsItem): ?>
            <a href="<?= url('news/' . $newsItem['id']) ?>" class="block p-3 rounded-xl hover:bg-gray-50 transition">
              <div class="flex items-start gap-3">
                <?php if (!empty($newsItem['image_url'])): ?>
                  <img src="<?= e($newsItem['image_url']) ?>" class="w-16 h-16 rounded-lg object-cover" alt="" loading="lazy">
                <?php endif; ?>
                <div>
                  <?php if ($newsItem['is_pinned']): ?>
                    <span class="text-xs text-gray-500 inline-flex items-center gap-1"><?= icon('pin') ?> Закреплено</span>
                  <?php endif; ?>
                  <h3 class="font-semibold text-gray-600 text-sm"><?= e($newsItem['title']) ?></h3>
                  <p class="text-xs text-gray-400 mt-1"><?= date('d.m.Y', strtotime($newsItem['created_at'])) ?></p>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Лента -->
      <?php if (empty($feed)): ?>
        <div class="bg-white rounded-2xl shadow-md p-12 text-center" data-aos="fade-up">
          <div class="mb-4 flex justify-center">
            <?= icon('friends') ?>
          </div>
          <p class="text-gray-400 text-lg">Лента пуста</p>
          <div class="flex gap-3 justify-center mt-4 flex-wrap">
            <a href="<?= url('friends') ?>" class="px-6 py-3 rounded-xl bg-blue-100 text-blue-700 font-bold hover:bg-blue-200 transition">Найти друзей</a>
            <?php if ($isOwnProfile): ?>
              <a href="<?= url('create-recipe') ?>" class="px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">Создать рецепт</a>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="space-y-6"
             id="feed-list"
             data-infinite-feed
             data-infinite-url="<?= url('api/feed_page.php') ?>"
             data-infinite-scope="home"
             data-infinite-page="<?= $page ?>"
             data-infinite-hasmore="<?= $page < $totalPages ? '1' : '0' ?>">
          <?php foreach ($feed as $item): ?>
            <?php include __DIR__ . '/../components/feed-item.php'; // карточка + комментарии ?>
          <?php endforeach; ?>
        </div>

        <div data-infinite-sentinel class="h-8"></div>
      <?php endif; ?>

    </div>
  </div>
</section>

<script>
window.__FEED_STORIES__ = <?= json_encode(array_map(function($s) {
    return [
        'id' => (int)$s['id'],
        'image_url' => $s['image_url'],
        'media_type' => $s['media_type'] ?? 'image',
        'video_url' => $s['video_url'],
        'rutube_id' => $s['rutube_id'],
        'user_name' => $s['user_name'] ?? '',
        'avatar_url' => $s['avatar_url'] ?? '',
        'user_gender' => $s['gender'] ?? null,
        'created_at' => $s['created_at'],
    ];
}, $storiesForFeed), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function openFeedStory(storyId) {
    const stories = window.__FEED_STORIES__ || [];
    const idx = stories.findIndex(s => s.id === storyId);
    if (idx < 0) return;
    const viewer = Alpine.$data(document.querySelector('[x-data="storyViewer()"]'));
    if (viewer) viewer.openAt(stories, idx);
}
</script>
<?php require __DIR__ . '/../components/story-viewer.php'; ?>
<?= gc_social_assets() ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>