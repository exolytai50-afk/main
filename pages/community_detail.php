<?php
require_once __DIR__ . '/../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$slug = trim((string)($_GET['slug'] ?? ''));

if ($id > 0) {
    $c = db_one('SELECT * FROM communities WHERE id = ?', [$id]);
} elseif ($slug !== '') {
    $c = db_one('SELECT * FROM communities WHERE slug = ?', [$slug]);
} else {
    http_response_code(404);
    die('Сообщество не найдено');
}

if (!$c) {
    http_response_code(404);
    die('Сообщество не найдено');
}

require_once __DIR__ . '/../includes/gc_social.php';
gc_schema_ensure();

$c['tags'] = json_field($c['tags'] ?? []);
$currentUserEmail = is_logged_in() ? current_user()['email'] : '';
$isOwner = $currentUserEmail === ($c['author_email'] ?? '');
$isMember = false;

$defaultCommunityCover = '/uploads/static/static-communities.jpg';
$communityCoverSrc = !empty($c['cover_image']) ? $c['cover_image'] : $defaultCommunityCover;

if (is_logged_in()) {
    try {
        db_exec("CREATE TABLE IF NOT EXISTS `community_members` (
            `id` int NOT NULL AUTO_INCREMENT,
            `community_id` int NOT NULL,
            `user_email` varchar(255) NOT NULL,
            `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_member` (`community_id`, `user_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        db_exec("CREATE TABLE IF NOT EXISTS `community_votes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `target_type` varchar(20) NOT NULL,
            `target_id` int NOT NULL,
            `user_email` varchar(255) NOT NULL,
            `vote` tinyint NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vote_unique` (`target_type`, `target_id`, `user_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    $memberCheck = db_one(
        'SELECT id FROM community_members 
         WHERE community_id = ? AND CONVERT(user_email USING utf8mb4) = CONVERT(? USING utf8mb4)',
        [$c['id'], $currentUserEmail]
    );

    $isMember = $isOwner || (bool)$memberCheck;

    if ($isOwner && !$memberCheck) {
        db_insert(
            'INSERT INTO community_members (community_id, user_email) VALUES (?, CONVERT(? USING utf8mb4))',
            [$c['id'], $currentUserEmail]
        );
    }
}

$members = db_all(
    "SELECT u.id, u.email, u.display_name, u.avatar_url, u.gender, cm.joined_at
     FROM community_members cm
     JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(cm.user_email USING utf8mb4)
     WHERE cm.community_id = ?
     ORDER BY cm.joined_at ASC",
    [$c['id']]
);

$emojiPacks = [];
try {
    $emojiPacks = db_all(
        "SELECT ep.*, 
                (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji
         FROM emoji_packs ep 
         WHERE ep.is_active = 1 
         ORDER BY ep.sort_order ASC"
    );
} catch (Throwable $e) { $emojiPacks = []; }

$allEmojis = [];
try {
    $allEmojis = db_all(
        "SELECT ei.* FROM emoji_images ei 
         JOIN emoji_packs ep ON ep.id = ei.pack_id 
         WHERE ep.is_active = 1 
         ORDER BY ei.pack_id, ei.sort_order ASC"
    );
} catch (Throwable $e) { $allEmojis = []; }

$emojisByPack = [];
foreach ($allEmojis as $emoji) {
    $emojisByPack[$emoji['pack_id']][] = $emoji;
}

$pageTitle = $c['name'];

/* ───────── Лента сообщества (первая страница) ───────── */
$gcViewer  = gc_viewer();
$ownerMail = (string)($c['author_email'] ?? '');
$gcCx = [
    'owner_email' => $ownerMail,
    'is_member'   => $isMember,
    'is_owner'    => $isOwner,
    'moderator'   => $isOwner || $gcViewer['is_admin'],
];
$gcRows    = gc_fetch_community_posts((int)$c['id'], GC_POSTS_PER_PAGE + 1, 0, false, $ownerMail);
$gcHasMore = count($gcRows) > GC_POSTS_PER_PAGE;
$gcRows    = array_slice($gcRows, 0, GC_POSTS_PER_PAGE);
$postsTotal = (int)(db_one('SELECT COUNT(*) AS n FROM community_posts WHERE community_id = ?', [$c['id']])['n'] ?? 0);

$ownerRow = $ownerMail !== ''
    ? db_one('SELECT email, display_name, avatar_url, avatar_emoji, gender FROM users WHERE email = ?', [$ownerMail])
    : null;
$membersCount = count($members);
$membersPreview = array_slice($members, 0, 8);


require_once __DIR__ . '/../includes/header.php';
?>

<style>
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl { display: inline-block; vertical-align: -0.125em; flex-shrink: 0; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<section class="gc-cd <?= $isMember ? 'is-member' : '' ?>" :class="{ 'is-member': joined }"
         x-data="communityDetail(<?= (int)$c['id'] ?>, <?= $isMember ? 'true' : 'false' ?>, <?= $isOwner ? 'true' : 'false' ?>, '<?= e($currentUserEmail) ?>')">

    <!-- Обложка -->
    <div class="h-48 sm:h-64 bg-gradient-to-br from-amber-100 to-orange-100 relative overflow-hidden">
        <img src="<?= e($communityCoverSrc) ?>" class="w-full h-full object-cover" alt="<?= e($c['name']) ?>" loading="lazy">
        <div class="absolute inset-0 bg-black/20"></div>

        <a href="<?= url('communities') ?>"
           class="absolute left-4 sm:left-6 top-1/2 -translate-y-1/2 z-10
                  inline-flex items-center gap-2 px-4 py-2.5
                  bg-white/90 backdrop-blur-sm rounded-xl shadow-lg
                  text-gray-700 hover:text-amber-600 hover:bg-white transition
                  text-sm font-semibold">
            <?= icon('back') ?>
            <span>Все сообщества</span>
        </a>

        <?php if ($isOwner): ?>
        <button type="button" @click="openCoverModal()"
                class="absolute right-4 sm:right-6 top-4 sm:top-6 z-10
                       inline-flex items-center gap-2 px-4 py-2.5
                       bg-white/90 backdrop-blur-sm rounded-xl shadow-lg
                       text-gray-700 hover:text-amber-600 hover:bg-white transition
                       text-sm font-semibold">
            <?= icon('image') ?>
            <span>Изменить обложку</span>
        </button>
        <?php endif; ?>
    </div>

    <div class="gc-cd-wrap">

        <!-- Шапка сообщества -->
        <div class="bg-white rounded-3xl shadow-md p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex-1 min-w-0 flex flex-col gap-2">
                    <div class="text-xs text-amber-600 font-semibold"><?= e($c['category'] ?? 'Общее') ?></div>
                    <h1 class="text-2xl font-extrabold text-gray-800"><?= e($c['name']) ?></h1>
                    <?php if (!empty($c['description'])): ?>
                        <p class="text-gray-500 gc-about-text"><?= e($c['description']) ?></p>
                    <?php endif; ?>
                    <div class="gc-cd-stats">
                        <span><?= icon('friends') ?> <b><?= $membersCount ?></b> <?= gc_e(gc_plural($membersCount, ['участник', 'участника', 'участников'])) ?></span>
                        <span>Записей: <b data-gc-posts-count><?= $postsTotal ?></b></span>
                    </div>
                </div>

                <div class="flex gap-2 sm:shrink-0 flex-col sm:flex-row w-full sm:w-auto">
                    <?php if (is_logged_in()): ?>
                        <?php if ($isOwner): ?>
                            <span class="inline-flex items-center justify-center gap-1 px-5 py-2.5 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm w-full sm:w-auto text-center">
                                <?= icon('check') ?> <span>Вы владелец</span>
                            </span>
                            <button @click="showInviteModal = true" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-100 text-blue-700 font-bold text-sm hover:bg-blue-200 transition w-full sm:w-auto">
                                <?= icon('plus') ?> <span>Пригласить</span>
                            </button>
                        <?php else: ?>
                            <button x-show="!joined" @click="join()" :disabled="loading" class="inline-flex items-center justify-center gap-1 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 w-full sm:w-auto">
                                <span x-show="!loading" class="inline-flex items-center gap-1"><?= icon('plus') ?> <span>Вступить</span></span>
                                <span x-show="loading" style="display:none">Вступаем…</span>
                            </button>
                            <button x-show="joined" @click="leaveCommunity()" :disabled="leaving" style="display:none" class="px-5 py-2.5 rounded-xl bg-rose-50 text-rose-600 font-bold text-sm hover:bg-rose-100 transition disabled:opacity-60 w-full sm:w-auto text-center">
                                <span x-show="!leaving">Выйти из сообщества</span>
                                <span x-show="leaving" style="display:none">Выходим…</span>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= url('login') ?>" class="px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200 transition w-full sm:w-auto text-center">Войти</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Вкладки + лента -->
        <div x-data="{ tab: (location.hash === '#members') ? 'members' : 'posts' }">

            <nav class="gc-tabs" aria-label="Разделы сообщества">
                <button type="button" class="gc-tab" :class="{ 'is-active': tab === 'posts' }" @click="tab = 'posts'">
                    <span>Записи</span> <small data-gc-posts-count><?= $postsTotal ?></small>
                </button>
                <button type="button" class="gc-tab" :class="{ 'is-active': tab === 'members' }" @click="tab = 'members'">
                    <span>Участники</span> <small><?= $membersCount ?></small>
                </button>
            </nav>

            <div class="gc-cd-grid">

                <!-- ═════ Основная колонка ═════ -->
                <div class="gc-cd-main">

                    <!-- ── Записи ── -->
                    <div x-show="tab === 'posts'" class="gc-cd-main">

                        <?php if (is_logged_in()): ?>
                        <div class="gc-card gc-pc" data-gc-post-compose>
                            <div class="gc-pc-collapsed">
                                <?= gc_ava($gcViewer['user'], 'md') ?>
                                <button type="button" class="gc-pc-open" data-gc-compose-open>Что у вас нового?</button>
                                <div class="gc-pc-quick">
                                    <button type="button" data-gc-compose-open aria-label="Добавить фото"><?= icon('image') ?></button>
                                    <button type="button" data-gc-compose-open aria-label="Добавить смайл"><?= icon('happy') ?></button>
                                </div>
                            </div>
                            <div class="gc-pc-form">
                                <?= gc_render_editor('Что у вас нового?', ['no_avatar' => true, 'cancel' => true, 'send_text' => 'Опубликовать', 'send_label' => 'Опубликовать']) ?>
                                <div class="gc-pc-hint">Ctrl + Enter — опубликовать</div>
                            </div>
                            <div class="gc-pc-join">Вступите в сообщество, чтобы публиковать записи и комментировать</div>
                        </div>
                        <?php else: ?>
                        <div class="gc-card gc-empty" style="padding:18px">
                            <a href="<?= url('login') ?>" class="text-amber-600 font-bold">Войдите</a>, чтобы публиковать записи и комментировать
                        </div>
                        <?php endif; ?>

                        <div class="gc-filter">
                            <div class="gc-seg" role="tablist" aria-label="Фильтр записей">
                                <button type="button" class="is-active" data-gc-filter="all">Все записи</button>
                                <button type="button" data-gc-filter="owner">От владельца</button>
                            </div>
                        </div>

                        <div class="gc-posts" id="gc-posts" data-gc-posts
                             data-community="<?= (int)$c['id'] ?>"
                             data-offset="<?= count($gcRows) ?>"
                             data-hasmore="<?= $gcHasMore ? '1' : '0' ?>"
                             data-filter="all">
                            <?= gc_render_community_posts($gcRows, $gcCx) ?>
                        </div>

                        <div class="gc-card gc-empty" data-gc-posts-empty <?= $gcRows ? 'hidden' : '' ?>>
                            <b>Записей пока нет</b>
                            Станьте первым — поделитесь рецептом, фото блюда или вопросом.
                        </div>
                        <div class="gc-loadmore" data-gc-posts-loading hidden>Загрузка…</div>
                        <div data-gc-sentinel style="height:1px"></div>
                    </div>

                    <!-- ── Участники ── -->
                    <div x-show="tab === 'members'" style="display:none" id="members">
                        <div class="gc-card">
                            <h3 class="gc-card-title"><span>Участники (<?= $membersCount ?>)</span></h3>
                            <?php if (empty($members)): ?>
                                <p class="text-gray-400 text-center py-6">Пока нет участников</p>
                            <?php else: ?>
                                <div class="gc-members-list">
                                    <?php foreach ($members as $member): $isMemberOwner = $member['email'] === $ownerMail; ?>
                                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition">
                                        <a href="<?= url('user/' . urlencode($member['email'])) ?>" class="shrink-0">
                                            <?= render_avatar($member, 'w-10 h-10 rounded-full object-cover', 'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600', 'text-lg') ?>
                                        </a>
                                        <div class="flex-1 min-w-0">
                                            <a href="<?= url('user/' . urlencode($member['email'])) ?>" class="font-semibold text-gray-800 text-sm hover:text-amber-600 truncate block"><?= e($member['display_name'] ?? $member['email']) ?></a>
                                            <p class="text-xs text-gray-400">Присоединился: <?= date('d.m.Y', strtotime($member['joined_at'])) ?></p>
                                        </div>
                                        <?php if ($isMemberOwner): ?>
                                            <span class="gc-badge">Владелец</span>
                                        <?php elseif ($isOwner): ?>
                                            <button type="button" @click="removeMember('<?= e($member['email']) ?>')" class="text-xs px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 transition">Удалить</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═════ Правая колонка (десктоп) ═════ -->
                <aside class="gc-cd-aside">
                    <div class="gc-card">
                        <h3 class="gc-card-title"><span>О сообществе</span></h3>
                        <p class="gc-about-text"><?= !empty($c['description']) ? e($c['description']) : 'Описание пока не добавлено.' ?></p>
                        <div class="gc-about-meta">
                            <?= e($c['category'] ?? 'Общее') ?>
                            <?php if (!empty($c['created_at'])): ?> · создано <?= date('d.m.Y', strtotime($c['created_at'])) ?><?php endif; ?>
                        </div>
                        <?php if ($ownerRow): ?>
                        <a href="<?= url('user/' . urlencode($ownerRow['email'])) ?>" class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100 no-underline">
                            <?= gc_ava($ownerRow, 'sm') ?>
                            <span class="min-w-0">
                                <span class="block text-xs text-gray-400">Владелец</span>
                                <span class="block text-sm font-bold text-gray-700 truncate"><?= gc_e(gc_user_name($ownerRow)) ?></span>
                            </span>
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="gc-card">
                        <h3 class="gc-card-title">
                            <span>Участники <span class="text-gray-400 font-bold"><?= $membersCount ?></span></span>
                            <?php if ($membersCount > count($membersPreview)): ?>
                                <button type="button" @click="tab = 'members'; window.scrollTo({ top: 0, behavior: 'smooth' })">Все</button>
                            <?php endif; ?>
                        </h3>
                        <?php if ($membersPreview): ?>
                        <div class="gc-members-grid">
                            <?php foreach ($membersPreview as $m): ?>
                            <a class="gc-member-mini" href="<?= url('user/' . urlencode($m['email'])) ?>" title="<?= e($m['display_name'] ?? '') ?>">
                                <?= gc_ava($m, 'md') ?>
                                <span class="n"><?= gc_e(explode(' ', gc_user_name($m))[0]) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="gc-about-text">Пока никого.</p>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                            <button type="button" @click="showInviteModal = true" class="w-full mt-4 py-2.5 rounded-xl bg-blue-50 text-blue-700 font-bold text-sm hover:bg-blue-100 transition">Пригласить друзей</button>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <!-- Модалки -->
    <div x-show="showInviteModal" style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="showInviteModal = false">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-md p-6 max-h-[80vh] overflow-y-auto">
            <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('plus') ?> Пригласить в сообщество</h3>
            <div class="mb-3"><input type="text" x-model="inviteSearch" @input.debounce.300ms="searchUsers()" placeholder="Поиск по имени или email..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm"></div>
            <div class="space-y-2 mb-4">
                <template x-for="u in inviteUsers" :key="u.id">
                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                        <template x-if="u.avatar_url"><img :src="u.avatar_url" class="w-10 h-10 rounded-full object-cover shrink-0"></template>
                        <template x-if="!u.avatar_url"><span class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center overflow-hidden shrink-0" x-html="window.getAvatarSvg(u.gender)"></span></template>
                        <div class="flex-1 min-w-0"><div class="font-semibold text-sm truncate" x-text="u.display_name || u.email"></div><div class="text-xs text-gray-400" x-text="u.email"></div></div>
                        <button @click="inviteUser(u.email)" :disabled="invitedEmails.includes(u.email)" class="px-3 py-1.5 rounded-lg text-xs font-bold inline-flex items-center gap-1" :class="invitedEmails.includes(u.email) ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                            <template x-if="invitedEmails.includes(u.email)"><span class="inline-flex items-center gap-1"><?= icon('check') ?> Приглашён</span></template>
                            <template x-if="!invitedEmails.includes(u.email)"><span class="inline-flex items-center gap-1"><?= icon('plus') ?> Пригласить</span></template>
                        </button>
                    </div>
                </template>
            </div>
            <button @click="showInviteModal = false" class="w-full py-2.5 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Закрыть</button>
        </div>
    </div>

    <div x-show="showCoverModal" style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="closeCoverModal()">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-md p-6 max-h-[85vh] overflow-y-auto">
            <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('image') ?> Обложка сообщества</h3>
            <div x-show="!coverPreview" class="mb-3"><img :src="currentCoverSrc" class="w-full h-40 object-cover rounded-xl border border-gray-200"><p class="text-xs text-gray-400 mt-1">Если хотите заменить — загрузите новое изображение ниже.</p></div>
            <div x-show="coverPreview" style="display:none" class="mb-3 relative">
                <img :src="coverPreview" class="w-full h-40 object-cover rounded-xl border border-gray-200">
                <button type="button" @click="removeCoverPreview()" class="absolute top-2 right-2 bg-white/90 rounded-full p-1.5 text-rose-500 shadow-md transition hover:bg-rose-50 inline-flex items-center justify-center w-8 h-8"><?= icon('close') ?></button>
            </div>
            <div class="mb-3"><label class="block border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-amber-300 transition"><input type="file" accept="image/*" class="hidden" @change="handleCoverFile($event)"><span class="block mb-1 inline-flex items-center justify-center"><?= icon('image') ?></span><span class="text-sm text-gray-500">Загрузить новое изображение</span><span class="block text-xs text-gray-400 mt-0.5">JPG, PNG, WebP · до 5 МБ</span></label></div>
            <div class="flex flex-col gap-2">
                <button type="button" @click="saveCover()" :disabled="savingCover || !coverFile" class="w-full py-2.5 rounded-xl bg-amber-300 text-amber-900 font-bold text-sm hover:bg-amber-400 transition disabled:opacity-60 inline-flex items-center justify-center gap-1"><span x-show="!savingCover" class="inline-flex items-center gap-1"><?= icon('diskette') ?> Сохранить обложку</span><span x-show="savingCover" style="display:none" class="inline-flex items-center gap-1"><?= icon('hourglass') ?> Сохранение…</span></button>
                <button type="button" @click="removeCoverImage()" :disabled="savingCover" class="w-full py-2.5 rounded-xl bg-rose-50 text-rose-600 font-bold text-sm hover:bg-rose-100 transition disabled:opacity-60 inline-flex items-center justify-center gap-1"><?= icon('trash') ?> Удалить обложку (использовать стандартную)</button>
                <button type="button" @click="closeCoverModal()" class="w-full py-2.5 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Отмена</button>
            </div>
            <p x-show="coverError" style="display:none" class="text-rose-500 text-sm mt-3" x-text="coverError"></p>
        </div>
    </div>

</section>

<script>
window.__EMOJI_PACKS__ = <?= json_encode($emojiPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__EMOJIS_BY_PACK__ = <?= json_encode($emojisByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
window.__COMMUNITY_ID__ = <?= (int)$c['id'] ?>;
window.__COMMUNITY_COVER__ = <?= json_encode($communityCoverSrc, JSON_UNESCAPED_UNICODE) ?>;
window.__DEFAULT_COMMUNITY_COVER__ = <?= json_encode($defaultCommunityCover, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
  window.DEFAULT_AVATAR_SVG_MALE    = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
  window.DEFAULT_AVATAR_SVG_FEMALE  = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
  window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;

  window.getAvatarSvg = function(gender) {
    if (gender === 'male')   return window.DEFAULT_AVATAR_SVG_MALE;
    if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
    return window.DEFAULT_AVATAR_SVG_NEUTRAL;
  };
</script>

<?= gc_social_assets() ?>
<script src="<?= url('assets/js/social/community.js') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/js/social/community.js') ?: time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
