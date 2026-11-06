<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user      = current_user();
$userEmail = $user['email'];

/* ─────────── Фильтр ─────────── */
$scope = $_GET['scope'] ?? 'all';
$allowedScopes = ['all', 'friends', 'ai', 'challenges'];
if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'all';
}

/* ─────────── Пагинация ─────────── */
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ─────────── SQL ─────────── */
$params = [];
$where  = '';

switch ($scope) {
    case 'friends':
        $where = "(af.user_email IN (
                    SELECT friend_email FROM friendships
                    WHERE user_email = ? AND status = 'accepted'
                  ) OR af.user_email = ?)
                  AND (af.visibility = 'public' OR af.visibility = 'friends' OR af.user_email = ?)";
        $params = [$userEmail, $userEmail, $userEmail];
        break;

    case 'ai':
        $where = "af.type IN ('ai_recipe', 'fusion_generate', 'fusion_save', 'calorie_scan')
                  AND (af.visibility = 'public' OR af.user_email = ?)";
        $params = [$userEmail];
        break;

    case 'challenges':
        $where = "af.type IN ('challenge_joined', 'challenge_submitted', 'challenge_completed')
                  AND (af.visibility = 'public' OR af.user_email = ?)";
        $params = [$userEmail];
        break;

    case 'all':
    default:
        $where = "(af.visibility = 'public'
                  OR (af.visibility = 'friends' AND af.user_email IN (
                        SELECT friend_email FROM friendships
                        WHERE user_email = ? AND status = 'accepted'
                     ))
                  OR af.user_email = ?)";
        $params = [$userEmail, $userEmail];
        break;
}

$sql = "SELECT
            af.*,
            u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
            r.title       AS recipe_title,
            r.slug        AS recipe_slug,
            r.image_url   AS recipe_image,
            r.description AS recipe_description,
            r.prep_time, r.cook_time, r.servings, r.calories, r.likes_count,
            c.slug     AS category_slug,
            c.name     AS category_name,
            c.icon_svg AS category_icon
        FROM activity_feed af
        JOIN users u ON u.email = af.user_email
        LEFT JOIN recipes r ON af.reference_type = 'recipe' AND r.id = af.reference_id
        LEFT JOIN recipe_categories c ON c.id = r.category_id
        WHERE {$where}
        ORDER BY af.created_at DESC
        LIMIT ? OFFSET ?";

/* ─── Истории за 7 дней ─── */
$storiesForFeed = db_all(
    "SELECT s.*, u.display_name AS user_name, u.avatar_url, u.gender
     FROM stories s
     LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = s.user_email COLLATE utf8mb4_unicode_ci
     WHERE s.created_at > DATE_SUB(NOW(), INTERVAL 168 HOUR)
     ORDER BY s.created_at DESC
     LIMIT 20"
);

/* ─── Последние новости ─── */
$latestNews = db_all(
    "SELECT id, title, content, image_url, created_at, is_pinned
     FROM news
     WHERE is_published = 1
     ORDER BY is_pinned DESC, created_at DESC
     LIMIT 3"
);

/* ─── Свой профиль ─── */
$currentUser = current_user();
$isOwnProfile = true;

/* ─── Загружаем ленту с пагинацией ─── */
$feed = db_all($sql, array_merge($params, [$perPage, $offset]));

// Загружаем теги для всех рецептов ленты
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

$pageTitle = 'Лента — GigaChef';

// Комментарии: схема БД + счётчики для всей страницы одним запросом
require_once __DIR__ . '/../includes/gc_social.php';
gc_schema_ensure();
gc_prefetch_counts('feed', $feed);

// Подсчёт общего количества для пагинации
$totalCount = (int)(db_one("SELECT COUNT(*) AS cnt FROM activity_feed af WHERE {$where}", $params)['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

  <!-- Шапка ленты -->
  <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-3xl shadow-md p-6 sm:p-8 mb-6" data-aos="fade-up">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-amber-200 border-4 border-amber-300 shadow-lg flex items-center justify-center shrink-0 text-amber-700">
        <?= icon('feed', 'gc-icon-xl', 3) ?>
      </div>
      <div class="min-w-0">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-800 truncate">Лента</h1>
        <p class="text-gray-500 text-sm mt-1">Активности сообщества GigaChef</p>
      </div>
    </div>
  </div>

  <!-- Истории -->
  <div class="bg-white rounded-2xl shadow-md p-4 mb-6" data-aos="fade-up">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-bold text-gray-800 text-sm inline-flex items-center gap-2">
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
        
        <!-- Обложка / Медиа -->
        <div class="w-full h-full bg-gray-900 relative">
            <?php if (($story['media_type'] ?? 'image') === 'video'): ?>
                
                <?php if (!empty($story['rutube_id'])): ?>
                    <!-- Если Rutube уже обработал видео, показываем его обложку -->
                    <img src="https://pic.rutube.ru/video/<?= e($story['rutube_id']) ?>.jpg"
                         class="w-full h-full object-cover"
                         alt=""
                         loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <!-- Фоллбек на локальное видео, если обложка Rutube не загрузилась -->
                    <video src="<?= e($story['video_url']) ?>" class="w-full h-full object-cover hidden" preload="metadata" muted playsinline></video>
                <?php else: ?>
                    <!-- Пока Rutube не ответил, показываем локальное видео -->
                    <video src="<?= e($story['video_url']) ?>" 
                           class="w-full h-full object-cover" 
                           preload="metadata" 
                           muted 
                           playsinline></video>
                <?php endif; ?>

                <!-- Иконка Play поверх видео -->
                <div class="absolute inset-0 flex items-center justify-center bg-black/20 pointer-events-none">
                    <div class="w-8 h-8 rounded-full bg-white/80 flex items-center justify-center shadow-md">
                        <?= icon('play', 'gc-icon-sm') ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Если это картинка -->
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
                        'gender'     => $story['gender'] ?? null,
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
      <div class="shrink-0 w-40 h-32 rounded-xl bg-gray-50 flex flex-col items-center justify-center text-center px-3 text-gray-600">
        <?= icon('video', 'gc-icon-xl', 2.5) ?>
        <span class="text-xs text-gray-600 mt-1">Историй пока нет</span>
      </div>
      <?php endif; ?>

      <!-- Блок "Добавить" (оставляем без изменений, уже 24х32) -->
      <a href="<?= url('stories') ?>" 
         class="shrink-0 w-24 h-32 rounded-xl border-2 border-dashed border-amber-300 flex flex-col items-center justify-center text-amber-400 hover:bg-amber-50 transition">
        <?= icon('plus') ?>
        <span class="text-[10px] font-semibold mt-1">Добавить</span>
      </a>
    </div>
  </div>

  <!-- Фильтры -->
  <div class="bg-white rounded-2xl shadow-md p-3 mb-6" data-aos="fade-up">
    <div class="grid grid-cols-4 gap-1.5">
      <?php
      $tabs = [
          'all' => [
              'icon_name' => 'feed',
              'label' => 'Все',
          ],
          'friends' => [
              'icon_name' => 'friends',
              'label' => 'Друзья',
          ],
          'ai' => [
              'icon_name' => 'ai',
              'label' => 'AI',
          ],
          'challenges' => [
              'icon_name' => 'challenges',
              'label' => 'Челленджи',
          ],
      ];
      foreach ($tabs as $key => $tab):
          $isActive = ($scope === $key);
          $cls = $isActive
              ? 'bg-amber-100 text-amber-700'
              : 'bg-gray-50 text-gray-500 hover:bg-gray-100';
      ?>
      <a href="<?= url('feed?scope=' . $key) ?>" 
         class="flex flex-col items-center justify-center py-2 rounded-xl transition text-center <?= $cls ?>">
          <?= icon($tab['icon_name']) ?>
          <span class="text-[10px] font-bold mt-1"><?= e($tab['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Лента -->
  <?php if (empty($feed)): ?>
  <?php
  $emptyStates = [
      'all' => [
          'icon_name' => 'feed',
          'title'     => 'Пока нет активностей',
          'text'      => 'Создайте рецепт или подпишитесь на других пользователей, чтобы видеть их активности.',
          'buttons'   => [
              ['url' => url('search'),        'icon_name' => 'search', 'label' => 'Найти рецепты', 'cls' => 'bg-amber-100 text-amber-700 hover:bg-amber-200'],
              ['url' => url('create-recipe'), 'icon_name' => 'plus',   'label' => 'Создать рецепт', 'cls' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'],
          ],
      ],
      'friends' => [
          'icon_name' => 'friends',
          'title'     => 'Лента друзей пуста',
          'text'      => 'У вас пока нет друзей или они не публикуют активности. Найдите единомышленников!',
          'buttons'   => [
              ['url' => url('friends'),     'icon_name' => 'friends',     'label' => 'Найти друзей', 'cls' => 'bg-blue-100 text-blue-700 hover:bg-blue-200'],
              ['url' => url('communities'), 'icon_name' => 'communities', 'label' => 'Сообщества',   'cls' => 'bg-violet-100 text-violet-700 hover:bg-violet-200'],
          ],
      ],
      'ai' => [
          'icon_name' => 'ai',
          'title'     => 'AI-активностей пока нет',
          'text'      => 'Попробуйте AI-инструменты GigaChef — сгенерируйте рецепт, спарьте блюдо с пивом или просканируйте калории.',
          'buttons'   => [
              ['url' => url('ai-generator'),    'icon_name' => 'aigenerator', 'label' => 'Генератор рецептов', 'cls' => 'bg-violet-100 text-violet-700 hover:bg-violet-200'],
              ['url' => url('beer-pairing'),    'icon_name' => 'beer',        'label' => 'Бирпейринг',         'cls' => 'bg-amber-100 text-amber-700 hover:bg-amber-200'],
              ['url' => url('calorie-scanner'), 'icon_name' => 'scaner',      'label' => 'Сканер калорий',     'cls' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'],
          ],
      ],
      'challenges' => [
          'icon_name' => 'challenges',
          'title'     => 'В челленджах пока тихо',
          'text'      => 'Присоединяйтесь к кулинарному челленджу — соревнуйтесь с другими и получайте достижения.',
          'buttons'   => [
              ['url' => url('challenges'),  'icon_name' => 'challenges', 'label' => 'Все челленджи', 'cls' => 'bg-amber-100 text-amber-700 hover:bg-amber-200'],
              ['url' => url('leaderboard'), 'icon_name' => 'leader',     'label' => 'Лидеры',        'cls' => 'bg-blue-100 text-blue-700 hover:bg-blue-200'],
          ],
      ],
  ];
  $empty = $emptyStates[$scope] ?? $emptyStates['all'];
  ?>

  <div class="bg-white rounded-2xl shadow-md p-12 text-center" data-aos="fade-up">
    <div class="mb-4 flex justify-center text-gray-300">
        <?= icon($empty['icon_name'], 'gc-icon-xl', 3.5) ?>
    </div>
    <p class="text-gray-700 text-lg font-bold"><?= e($empty['title']) ?></p>
    <p class="text-gray-400 text-sm mt-2 max-w-md mx-auto"><?= e($empty['text']) ?></p>

    <div class="flex flex-wrap gap-3 justify-center mt-6">
      <?php foreach ($empty['buttons'] as $btn): ?>
      <a href="<?= $btn['url'] ?>" 
         class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition <?= $btn['cls'] ?>">
        <?= icon($btn['icon_name']) ?>
        <span><?= $btn['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="space-y-6"
       data-infinite-feed
       data-infinite-url="<?= url('api/feed_page.php') ?>"
       data-infinite-scope="<?= e($scope) ?>"
       data-infinite-page="<?= $page ?>"
       data-infinite-hasmore="<?= $page < $totalPages ? '1' : '0' ?>"
       id="feed-list">

          <?php foreach ($feed as $item): ?>
            <?php include __DIR__ . '/../components/feed-item.php'; // карточка + комментарии ?>
          <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div data-infinite-sentinel class="h-8"></div>

</section>
<script src="<?= url('assets/js/pagination.js') ?>?v=<?= time() ?>"></script>
<script src="<?= url('assets/js/infinite-feed.js') ?>?v=<?= time() ?>"></script>
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