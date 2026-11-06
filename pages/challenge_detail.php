<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 && isset($_GET['slug'])) {
    $ch = db_one('SELECT * FROM challenges WHERE slug = ?', [$_GET['slug']]);
} else {
    $ch = db_one('SELECT * FROM challenges WHERE id = ?', [$id]);
}

if (!$ch) {
    http_response_code(404);
    $pageTitle = 'Челлендж не найден';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="text-center py-20">
        <div class="mb-4 flex justify-center"><?= icon('search') ?></div>
        <h1 class="text-2xl font-bold text-gray-800">Челлендж не найден</h1>
        <a href="<?= url('challenges') ?>" class="inline-block mt-4 px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">Все челленджи</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$id = (int)$ch['id'];

try {
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_participants` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challenge_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `challenge_user` (`challenge_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db_exec("CREATE TABLE IF NOT EXISTS `challenge_submissions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `challenge_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `photo_url` varchar(500) DEFAULT NULL,
        `comment` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db_exec("CREATE TABLE IF NOT EXISTS `challenge_comments` (
        `id` int NOT NULL AUTO_INCREMENT,
        `submission_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `comment` text NOT NULL,
        `sticker_url` varchar(500) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_submission` (`submission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = db_all("SHOW COLUMNS FROM challenge_comments");
    $colNames = array_column($cols, 'Field');
    if (!in_array('sticker_url', $colNames)) {
        db_exec("ALTER TABLE challenge_comments ADD COLUMN sticker_url varchar(500) DEFAULT NULL AFTER comment");
    }

    db_exec("CREATE TABLE IF NOT EXISTS `challenge_votes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `target_type` varchar(20) NOT NULL,
        `target_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `vote` tinyint NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `vote_unique` (`target_type`, `target_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log('Error creating tables: ' . $e->getMessage());
}

if (!function_exists('challenge_difficulty_normalize')) {
    function challenge_difficulty_normalize(?string $value): string {
        $value = trim((string)$value);
        if ($value === '') return 'medium';
        $key = mb_strtolower($value, 'UTF-8');
        $easy = ['easy', 'лёгкий', 'легкий', 'легко', 'beginner', 'novice'];
        $medium = ['medium', 'middle', 'средне', 'средний', 'normal'];
        $hard = ['hard', 'сложно', 'сложный', 'expert', 'advanced', 'pro'];
        if (in_array($key, $easy, true))   return 'easy';
        if (in_array($key, $medium, true)) return 'medium';
        if (in_array($key, $hard, true))   return 'hard';
        return 'unknown';
    }
}
if (!function_exists('challenge_difficulty_label')) {
    function challenge_difficulty_label(?string $value): string {
        return ['easy'=>'Легко','medium'=>'Средне','hard'=>'Сложно','unknown'=>'Средне'][challenge_difficulty_normalize($value)];
    }
}
if (!function_exists('challenge_difficulty_color')) {
    function challenge_difficulty_color(?string $value): string {
        return [
            'easy'=>'bg-emerald-100 text-emerald-700',
            'medium'=>'bg-amber-100 text-amber-700',
            'hard'=>'bg-rose-100 text-rose-700',
            'unknown'=>'bg-gray-100 text-gray-600',
        ][challenge_difficulty_normalize($value)];
    }
}

$difficultyLabel = challenge_difficulty_label($ch['difficulty'] ?? null);
$difficultyColor = challenge_difficulty_color($ch['difficulty'] ?? null);

$emojiPacks = [];
try {
    $emojiPacks = db_all("SELECT ep.*, (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji FROM emoji_packs ep WHERE ep.is_active = 1 ORDER BY ep.sort_order ASC");
} catch (Throwable $e) { $emojiPacks = []; }

$allEmojis = [];
try {
    $allEmojis = db_all("SELECT ei.* FROM emoji_images ei JOIN emoji_packs ep ON ep.id = ei.pack_id WHERE ep.is_active = 1 ORDER BY ei.pack_id, ei.sort_order ASC");
} catch (Throwable $e) { $allEmojis = []; }

$emojisByPack = [];
foreach ($allEmojis as $emoji) { $emojisByPack[$emoji['pack_id']][] = $emoji; }

$pageTitle = $ch['title'] . ' — челлендж | GigaChef';

$participants = db_all(
    "SELECT u.id, u.email, u.display_name, u.avatar_url, u.gender, cp.joined_at
     FROM challenge_participants cp
     JOIN users u ON u.email = cp.user_email COLLATE utf8mb4_unicode_ci
     WHERE cp.challenge_id = ? ORDER BY cp.joined_at DESC",
    [$id]
);

$submissions = db_all(
    "SELECT cs.*, u.display_name, u.avatar_url, u.gender,
            (SELECT COUNT(*) FROM challenge_comments WHERE submission_id = cs.id) as comment_count
     FROM challenge_submissions cs
     JOIN users u ON u.email = cs.user_email COLLATE utf8mb4_unicode_ci
     WHERE cs.challenge_id = ? ORDER BY cs.created_at DESC",
    [$id]
);

$allComments = [];
if (!empty($submissions)) {
    $submissionIds = array_column($submissions, 'id');
    $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
    $allComments = db_all(
        "SELECT cc.*, u.display_name, u.avatar_url, u.gender
         FROM challenge_comments cc
         JOIN users u ON u.email = cc.user_email COLLATE utf8mb4_unicode_ci
         WHERE cc.submission_id IN ({$placeholders}) ORDER BY cc.created_at ASC",
        $submissionIds
    );
}

$commentsBySubmission = [];
foreach ($allComments as $comment) { $commentsBySubmission[$comment['submission_id']][] = $comment; }

$currentUserEmail = current_user()['email'];

$subLikes = []; $subDislikes = []; $subMyVotes = [];
if (!empty($submissions)) {
    $subIds = array_column($submissions, 'id');
    $ph = implode(',', array_fill(0, count($subIds), '?'));
    $subVoteRows = db_all("SELECT target_id, vote, user_email FROM challenge_votes WHERE target_type = 'submission' AND target_id IN ({$ph})", $subIds);
    foreach ($subVoteRows as $vr) {
        $tid = (int)$vr['target_id'];
        if ((int)$vr['vote'] === 1)  $subLikes[$tid]    = ($subLikes[$tid]    ?? 0) + 1;
        if ((int)$vr['vote'] === -1) $subDislikes[$tid] = ($subDislikes[$tid] ?? 0) + 1;
        if ($vr['user_email'] === $currentUserEmail) $subMyVotes[$tid] = (int)$vr['vote'];
    }
}

$comLikes = []; $comDislikes = []; $comMyVotes = [];
if (!empty($allComments)) {
    $comIds = array_column($allComments, 'id');
    $ph = implode(',', array_fill(0, count($comIds), '?'));
    $comVoteRows = db_all("SELECT target_id, vote, user_email FROM challenge_votes WHERE target_type = 'comment' AND target_id IN ({$ph})", $comIds);
    foreach ($comVoteRows as $vr) {
        $tid = (int)$vr['target_id'];
        if ((int)$vr['vote'] === 1)  $comLikes[$tid]    = ($comLikes[$tid]    ?? 0) + 1;
        if ((int)$vr['vote'] === -1) $comDislikes[$tid] = ($comDislikes[$tid] ?? 0) + 1;
        if ($vr['user_email'] === $currentUserEmail) $comMyVotes[$tid] = (int)$vr['vote'];
    }
}

$submissionsData = [];
foreach ($submissions as $sub) {
    $subComments = $commentsBySubmission[$sub['id']] ?? [];
    $formattedComments = [];
    foreach ($subComments as $c) {
        $cid = (int)$c['id'];
        $formattedComments[] = [
            'id' => $cid,
            'user_email' => $c['user_email'],
            'display_name' => $c['display_name'] ?? 'Пользователь',
            'avatar_emoji' => $c['avatar_emoji'] ?? null,
            'avatar_url' => !empty($c['avatar_url']) ? url($c['avatar_url']) : null,
            'gender' => $c['gender'] ?? null,
            'comment' => $c['comment'] ?? '',
            'sticker_url' => $c['sticker_url'] ?? null,
            'created_at' => $c['created_at'],
            'like_count' => $comLikes[$cid] ?? 0,
            'dislike_count' => $comDislikes[$cid] ?? 0,
            'my_vote' => $comMyVotes[$cid] ?? 0,
        ];
    }
    $sid = (int)$sub['id'];
    $submissionsData[] = [
        'id' => $sid,
        'user_email' => $sub['user_email'],
        'display_name' => $sub['display_name'] ?? 'Пользователь',
        'avatar_emoji' => $sub['avatar_emoji'] ?? null,
        'avatar_url' => !empty($sub['avatar_url']) ? url($sub['avatar_url']) : null,
        'gender' => $sub['gender'] ?? null,
        'photo_url' => $sub['photo_url'] ?? null,
        'comment' => $sub['comment'] ?? '',
        'created_at' => $sub['created_at'],
        'comment_count' => count($formattedComments),
        'comments' => $formattedComments,
        'like_count' => $subLikes[$sid] ?? 0,
        'dislike_count' => $subDislikes[$sid] ?? 0,
        'my_vote' => $subMyVotes[$sid] ?? 0,
    ];
}

$myParticipation = db_one(
    "SELECT id FROM challenge_participants WHERE challenge_id = ? AND user_email = ? COLLATE utf8mb4_unicode_ci",
    [$id, $currentUserEmail]
);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
[contenteditable]:empty::before,
[contenteditable]:has(> br:only-child)::before,
[contenteditable]:has(> div:only-child:empty)::before {
    content: attr(data-placeholder);
    color: #9ca3af;
    pointer-events: none;
}
[contenteditable]:focus { outline: none; }
.inline-emoji { height: 1.4em; vertical-align: middle; display: inline-block; line-height: 1; }

.post-image { max-width: 100%; max-height: 480px; border-radius: 12px; cursor: pointer; transition: transform 0.2s, opacity 0.2s; object-fit: contain; }
.post-image:hover { transform: scale(1.01); opacity: 0.97; }

.vote-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 12px; font-size: 13px; transition: all 0.15s ease; cursor: pointer; border: none; background: transparent; }
.vote-btn:hover { background: #f3f4f6; }
.vote-icon-wrapper { display: flex; align-items: center; justify-content: center; transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.vote-btn.anim-click .vote-icon-wrapper { animation: thumb-pop 0.35s ease; }
@keyframes thumb-pop { 0% { transform: scale(1); } 40% { transform: scale(1.3); } 70% { transform: scale(0.95); } 100% { transform: scale(1); } }
.vote-btn-active-like { color: #16a34a; background: #dcfce7; }
.vote-btn-active-like:hover { background: #bbf7d0; }
.vote-btn-active-dislike { color: #dc2626; background: #fee2e2; }
.vote-btn-active-dislike:hover { background: #fecaca; }
</style>

<script>
window.__CHALLENGE_DATA__ = <?= json_encode([
    'id' => $id,
    'isJoined' => (bool)$myParticipation,
    'participantsCount' => count($participants),
    'myEmail' => $currentUserEmail,
    'submissions' => $submissionsData,
], JSON_UNESCAPED_UNICODE) ?>;
window.__EMOJI_PACKS__ = <?= json_encode($emojiPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__EMOJIS_BY_PACK__ = <?= json_encode($emojisByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
</script>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="challengeDetail()">

    <?php $hasCover = !empty($ch['cover_image']); ?>
    <div class="relative rounded-2xl overflow-hidden mb-6 bg-gradient-to-br from-violet-100 to-purple-100">
        <?php if ($hasCover): ?>
            <img src="<?= e($ch['cover_image']) ?>" alt="<?= e($ch['title']) ?>" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-black/20"></div>
        <?php endif; ?>
        <div class="relative flex items-center gap-3 px-4 py-3 sm:px-5 sm:py-4">
            <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9" aria-hidden="true"><?= icon('challenges') ?></span>
            <h1 class="text-base sm:text-xl font-extrabold leading-tight truncate <?= $hasCover ? 'text-white drop-shadow-md' : 'text-gray-800' ?>"><?= e($ch['title']) ?></h1>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-md p-6 sm:p-8 mb-6">
        <div class="flex flex-wrap gap-2 mb-3">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold <?= e($difficultyColor) ?>"><?= icon('leader') ?><span><?= e($difficultyLabel) ?></span></span>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><?= icon('achievements') ?><span>+<?= (int)($ch['reward_points'] ?? 0) ?> очков</span></span>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700"><?= icon('friends') ?><span><span x-text="participantsCount"></span> участников</span></span>
        </div>

        <h2 class="text-lg font-bold text-gray-800 mb-2">О челлендже</h2>
        <p class="text-gray-600 mt-2 emoji-container"><?= e($ch['description'] ?? '') ?></p>

        <?php if (!$myParticipation): ?>
            <button @click="joinChallenge()" :disabled="joining" class="mt-6 w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
                <span x-show="!joining" class="inline-flex items-center gap-2"><?= icon('plus') ?><span>Участвовать</span></span>
                <span x-show="joining" style="display:none" class="inline-flex items-center gap-2"><?= icon('hourglass') ?><span>...</span></span>
            </button>
        <?php else: ?>
            <div class="mt-6 bg-emerald-50 rounded-xl p-4 text-center">
                <span class="inline-flex items-center gap-2 text-emerald-700 font-bold"><?= icon('check') ?><span>Вы участвуете в челлендже</span></span>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-6">
                <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('scaner') ?><span>Добавить работу</span></h3>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Фото блюда</label>
                    <label class="block border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-violet-300 transition">
                        <input type="file" accept="image/*" class="hidden" @change="handlePhoto($event)">
                        <div class="text-gray-400 flex flex-col items-center">
                            <div class="mb-1 flex justify-center"><?= icon('image') ?></div>
                            <div class="text-sm">Загрузить фото</div>
                        </div>
                    </label>
                    <div x-show="photoPreview" style="display:none" class="mt-2 relative">
                        <img :src="photoPreview" class="w-full max-h-64 object-contain rounded-xl">
                        <button @click="removePhoto()" class="absolute top-2 right-2 bg-white/90 rounded-full p-1.5 text-rose-500 hover:bg-rose-50 transition" aria-label="Удалить фото"><?= icon('close') ?></button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Описание блюда</label>

                    <!-- relative-обёртка: поле + кнопки + панель -->
                    <div class="relative w-full">
                        <!-- ПОЛЕ -->
                        <div id="submit-comment-input"
                             contenteditable="true"
                             data-placeholder="Расскажите о вашем блюде…"
                             @input="comment = $event.target.innerHTML"
                             @keydown="onDescriptionKeydown($event)"
                             @paste.prevent="onPastePlain($event)"
                             class="w-full min-h-[48px] max-h-[160px] px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 text-sm overflow-y-auto">
                        </div>

                        <!-- КНОПКИ -->
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" @mousedown.prevent @click="toggleSubmitEmoji()"
                                    class="sm:hidden w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center shrink-0"
                                    :aria-label="showSubmitEmoji ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'">
                                <template x-if="!showSubmitEmoji"><?= icon('happy') ?></template>
                                <template x-if="showSubmitEmoji"><?= icon('keyboard') ?></template>
                            </button>
                            <button type="button" @mousedown.prevent @click="toggleSubmitEmoji()"
                                    class="hidden sm:flex w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 transition items-center justify-center shrink-0"
                                    :class="showSubmitEmoji ? 'text-violet-500' : 'text-gray-500'">
                                <?= icon('happy') ?>
                            </button>
                            <button @click="submitWork()" :disabled="submitting"
                                    class="ml-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold text-sm hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-1.5">
                                <span x-show="!submitting">Отправить</span>
                                <span x-show="submitting" style="display:none"><?= icon('hourglass') ?></span>
                            </button>
                        </div>

                        <!-- ПАНЕЛЬ ЭМОДЗИ (после кнопок, в потоке на мобилке) -->
                        <div x-show="showSubmitEmoji" x-transition style="display:none"
                             class="w-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-200 flex flex-col overflow-hidden
                                    h-[45vh] max-h-[380px]
                                    sm:absolute sm:bottom-full sm:left-0 sm:right-0 sm:mt-0 sm:mb-2 sm:h-auto sm:max-h-[300px]">
                            <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
                                <template x-for="pack in emojiPacks" :key="pack.id">
                                    <button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button"
                                            :class="activeEmojiPack === pack.id ? 'bg-violet-100 ring-2 ring-violet-400' : 'bg-gray-100'"
                                            class="shrink-0 w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
                                        <img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false">
                                    </button>
                                </template>
                            </div>
                            <div class="flex-1 overflow-y-auto p-2 min-h-0">
                                <div class="grid grid-cols-7 sm:grid-cols-10 gap-1.5">
                                    <template x-for="emoji in getActiveEmojis()" :key="emoji.id">
                                        <button @mousedown.prevent @click="addEmojiToSubmit(emoji)" type="button"
                                                class="aspect-square rounded-lg hover:bg-violet-50 transition flex items-center justify-center overflow-hidden p-0.5">
                                            <img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p x-show="submitError" style="display:none" class="text-rose-500 text-sm mt-3" x-text="submitError"></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-3xl shadow-md p-6">
        <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2">
            <?= icon('cookbook') ?>
            <span>Работы участников (<span x-text="submissions.length"></span>)</span>
        </h3>

        <div x-show="!submissions.length" style="display:none" class="text-gray-400 text-center py-8">Пока нет работ</div>

        <div class="space-y-6">
            <template x-for="s in submissions" :key="s.id">
                <div class="border border-gray-100 rounded-2xl p-4 relative group">
                    <div x-show="s.user_email === myEmail" class="absolute top-3 right-3 flex gap-1 z-10 opacity-0 group-hover:opacity-100 transition">
                        <button @click="editSubmission(s)" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center text-sm transition" aria-label="Редактировать"><?= icon('pen') ?></button>
                        <button @click="deleteSubmission(s.id)" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 flex items-center justify-center text-sm transition" aria-label="Удалить"><?= icon('trash') ?></button>
                    </div>

                    <div class="flex items-center gap-3 mb-3">
                        <template x-if="s.avatar_url"><img :src="s.avatar_url" :alt="s.display_name || 'Пользователь'" class="w-8 h-8 rounded-full object-cover shrink-0"></template>
                        <template x-if="!s.avatar_url"><span class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center overflow-hidden shrink-0" x-html="window.getAvatarSvg(s.gender)"></span></template>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm" x-text="s.display_name || 'Пользователь'"></p>
                            <p class="text-xs text-gray-400" x-text="formatDate(s.created_at)"></p>
                        </div>
                    </div>

                    <img x-show="s.photo_url" :src="s.photo_url" @click="openImageModal(s.photo_url)" class="post-image w-full mb-3" alt="">

                    <div x-show="s.comment" class="text-sm text-gray-600 mb-3 emoji-container" x-html="s.comment"></div>

                    <div class="flex items-center gap-2 mb-3">
                        <button @click="vote(s, 'submission', s.id, 1, $event)" type="button" :class="s.my_vote === 1 ? 'vote-btn-active-like' : ''" class="vote-btn text-gray-500">
                            <span class="vote-icon-wrapper"><?= icon('like') ?></span><span x-text="s.like_count || ''"></span>
                        </button>
                        <button @click="vote(s, 'submission', s.id, -1, $event)" type="button" :class="s.my_vote === -1 ? 'vote-btn-active-dislike' : ''" class="vote-btn text-gray-500">
                            <span class="vote-icon-wrapper"><?= icon('dislike') ?></span><span x-text="s.dislike_count || ''"></span>
                        </button>
                    </div>

                    <div class="border-t border-gray-100 pt-3">
                        <button @click="toggleComments(s.id)" type="button" class="text-xs text-gray-400 hover:text-violet-600 font-semibold transition inline-flex items-center gap-1.5">
                            <?= icon('chats') ?>
                            <span x-text="showComments[s.id] ? (s.comments ? 'Скрыть (' + s.comments.length + ')' : 'Скрыть') : ('Комментарии' + (s.comment_count ? ' (' + s.comment_count + ')' : ''))"></span>
                        </button>
                    </div>

                    <div x-show="showComments[s.id]" style="display:none" class="mt-3 space-y-2">
                        <template x-if="s.comments">
                            <template x-for="c in s.comments" :key="c.id">
                                <div class="flex gap-2.5 items-start group/comment">
                                    <template x-if="c.avatar_url"><img :src="c.avatar_url" :alt="c.display_name || 'Пользователь'" class="w-8 h-8 rounded-full object-cover shrink-0"></template>
                                    <template x-if="!c.avatar_url"><span class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center overflow-hidden shrink-0" x-html="window.getAvatarSvg(c.gender)"></span></template>
                                    <div class="flex-1 min-w-0">
                                        <div class="bg-gray-50 rounded-xl px-3 py-2 relative">
                                            <div x-show="c.user_email === myEmail" class="absolute top-1 right-1 flex gap-0.5 opacity-0 group-hover/comment:opacity-100 transition">
                                                <button @click="editComment(c)" class="w-6 h-6 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center text-xs transition" aria-label="Редактировать"><?= icon('pen') ?></button>
                                                <button @click="deleteComment(c.id, s.id)" class="w-6 h-6 rounded bg-rose-50 text-rose-500 hover:bg-rose-100 flex items-center justify-center text-xs transition" aria-label="Удалить"><?= icon('trash') ?></button>
                                            </div>
                                            <img x-show="c.sticker_url" :src="c.sticker_url" class="w-16 h-16 object-contain my-1">
                                            <div x-show="editingCommentId !== c.id">
                                                <div class="font-semibold text-xs text-gray-700" x-text="c.display_name || 'Пользователь'"></div>
                                                <div class="text-sm text-gray-600 emoji-container" x-html="c.comment"></div>
                                            </div>

                                            <div x-show="editingCommentId === c.id" style="display:none" class="mt-2 relative w-full">
                                                <!-- ПОЛЕ РЕДАКТИРОВАНИЯ -->
                                                <div :id="'edit-comment-input-' + c.id" contenteditable="true"
                                                     @input="editCommentContent[c.id] = $event.target.innerHTML"
                                                     @paste.prevent="onPastePlain($event)"
                                                     @keydown="onEditCommentKeydown($event, c.id, s.id)"
                                                     class="w-full min-h-[48px] max-h-[160px] px-3 py-2 rounded-lg border border-gray-200 outline-none focus:border-violet-300 text-sm overflow-y-auto"></div>

                                                <!-- КНОПКИ РЕДАКТИРОВАНИЯ -->
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <button type="button" @mousedown.prevent @click="toggleEditCommentEmoji(c.id)"
                                                            class="sm:hidden w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center"
                                                            :aria-label="showEditCommentEmoji[c.id] ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'">
                                                        <template x-if="!showEditCommentEmoji[c.id]"><?= icon('happy') ?></template>
                                                        <template x-if="showEditCommentEmoji[c.id]"><?= icon('keyboard') ?></template>
                                                    </button>
                                                    <button type="button" @mousedown.prevent @click="toggleEditCommentEmoji(c.id)"
                                                            class="hidden sm:flex w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition items-center justify-center"
                                                            :class="showEditCommentEmoji[c.id] ? 'text-violet-500' : 'text-gray-400'">
                                                        <?= icon('happy') ?>
                                                    </button>
                                                    <button @click="saveEditComment(c.id, s.id)" :disabled="savingCommentEdit[c.id]"
                                                            class="ml-auto px-3 py-1 rounded-lg bg-violet-500 text-white text-xs font-bold hover:bg-violet-600 disabled:opacity-50 inline-flex items-center gap-1">
                                                        <span x-show="!savingCommentEdit[c.id]">Сохранить</span>
                                                        <span x-show="savingCommentEdit[c.id]" style="display:none"><?= icon('hourglass') ?></span>
                                                    </button>
                                                    <button @click="cancelEditComment()" class="px-3 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-bold hover:bg-gray-200">Отмена</button>
                                                </div>

                                                <!-- ПАНЕЛЬ РЕДАКТИРОВАНИЯ -->
                                                <div x-show="showEditCommentEmoji[c.id]" x-transition style="display:none"
                                                     class="w-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-200 flex flex-col overflow-hidden
                                                            h-[45vh] max-h-[380px]
                                                            sm:absolute sm:bottom-full sm:left-0 sm:right-0 sm:mt-0 sm:mb-2 sm:h-auto sm:max-h-[300px]">
                                                    <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
                                                        <template x-for="pack in emojiPacks" :key="pack.id">
                                                            <button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" :class="activeEmojiPack === pack.id ? 'bg-violet-100 ring-2 ring-violet-400' : 'bg-gray-100'" class="shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
                                                                <img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false">
                                                            </button>
                                                        </template>
                                                    </div>
                                                    <div class="flex-1 overflow-y-auto p-2 min-h-0">
                                                        <div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
                                                            <template x-for="emoji in getActiveEmojis()" :key="emoji.id">
                                                                <button @mousedown.prevent @click="addEmojiToEditComment(c.id, emoji)" type="button" class="aspect-square rounded-lg hover:bg-violet-50 transition flex items-center justify-center overflow-hidden p-0.5">
                                                                    <img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-1.5 mt-1.5">
                                                <button @click="vote(c, 'comment', c.id, 1, $event)" type="button" :class="c.my_vote === 1 ? 'vote-btn-active-like' : ''" class="vote-btn text-gray-400" style="font-size:12px; padding:4px 8px;">
                                                    <span class="vote-icon-wrapper"><?= icon('like') ?></span><span x-text="c.like_count || ''"></span>
                                                </button>
                                                <button @click="vote(c, 'comment', c.id, -1, $event)" type="button" :class="c.my_vote === -1 ? 'vote-btn-active-dislike' : ''" class="vote-btn text-gray-400" style="font-size:12px; padding:4px 8px;">
                                                    <span class="vote-icon-wrapper"><?= icon('dislike') ?></span><span x-text="c.dislike_count || ''"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5" x-text="formatDate(c.created_at)"></div>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <p x-show="s.comments && s.comments.length === 0" style="display:none" class="text-xs text-gray-400 text-center py-2">Пока нет комментариев</p>

                        <!-- ФОРМА НОВОГО КОММЕНТАРИЯ -->
                        <div class="flex flex-col sm:flex-row sm:items-start gap-2 mt-2">
                            <div class="w-full sm:flex-1 min-w-0 relative">
                                <!-- ПОЛЕ -->
                                <div :id="'comment-input-' + s.id" contenteditable="true" data-placeholder="Написать комментарий…"
                                     @input="commentText[s.id] = $event.target.innerHTML"
                                     @paste.prevent="onPastePlain($event)"
                                     @keydown.enter="onCommentKeydown($event, s.id)"
                                     class="w-full min-h-[48px] max-h-[160px] px-3 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-violet-300 text-sm overflow-y-auto">
                                </div>

                                <!-- КНОПКИ -->
                                <div class="flex items-center gap-2 mt-2">
                                    <button type="button" @mousedown.prevent @click="toggleCommentEmoji(s.id)"
                                            class="sm:hidden w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center"
                                            :aria-label="showCommentEmoji[s.id] ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'">
                                        <template x-if="!showCommentEmoji[s.id]"><?= icon('happy') ?></template>
                                        <template x-if="showCommentEmoji[s.id]"><?= icon('keyboard') ?></template>
                                    </button>
                                    <button type="button" @mousedown.prevent @click="toggleCommentEmoji(s.id)"
                                            class="hidden sm:flex w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition items-center justify-center"
                                            :class="showCommentEmoji[s.id] ? 'text-violet-500' : 'text-gray-400'">
                                        <?= icon('happy') ?>
                                    </button>
                                    <button @click="submitComment(s.id)" :disabled="commentPosting[s.id] || !(commentText[s.id] || '').trim()"
                                            class="ml-auto px-4 py-1.5 rounded-lg bg-violet-100 text-violet-700 text-sm font-bold hover:bg-violet-200 transition disabled:opacity-50">
                                        Отправить
                                    </button>
                                </div>

                                <!-- ПАНЕЛЬ -->
                                <div x-show="showCommentEmoji[s.id]" x-transition style="display:none"
                                     class="w-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-200 flex flex-col overflow-hidden
                                            h-[45vh] max-h-[380px]
                                            sm:absolute sm:bottom-full sm:left-0 sm:right-0 sm:mt-0 sm:mb-2 sm:h-auto sm:max-h-[300px]">
                                    <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
                                        <template x-for="pack in emojiPacks" :key="pack.id">
                                            <button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" :class="activeEmojiPack === pack.id ? 'bg-violet-100 ring-2 ring-violet-400' : 'bg-gray-100'" class="shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
                                                <img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false">
                                            </button>
                                        </template>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-2 min-h-0">
                                        <div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
                                            <template x-for="emoji in getActiveEmojis()" :key="emoji.id">
                                                <button @mousedown.prevent @click="addEmojiToComment(s.id, emoji)" type="button" class="aspect-square rounded-lg hover:bg-violet-50 transition flex items-center justify-center overflow-hidden p-0.5">
                                                    <img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-md p-6 mt-6">
        <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2">
            <?= icon('friends') ?>
            <span>Участники (<?= count($participants) ?>)</span>
        </h3>
        <?php if (empty($participants)): ?>
            <p class="text-gray-400 text-center py-8">Пока нет участников</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($participants as $p): ?>
                <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition">
                    <a href="<?= url('user/' . urlencode($p['email'])) ?>">
                        <?= render_avatar($p, 'w-10 h-10 rounded-full object-cover', 'w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600', 'text-lg') ?>
                    </a>
                    <div class="flex-1 min-w-0">
                        <a href="<?= url('user/' . urlencode($p['email'])) ?>" class="font-semibold text-gray-800 text-sm hover:text-amber-600 truncate block"><?= e($p['display_name']) ?></a>
                        <p class="text-xs text-gray-400">Присоединился: <?= date('d.m.Y', strtotime($p['joined_at'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div x-show="editingSubmission !== null" style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="editingSubmission = null">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-md p-6 max-h-[85vh] overflow-y-auto">
            <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('pen') ?><span>Редактировать работу</span></h3>
            <template x-if="editingSubmission && editingSubmission.photo_url && !editSubPhotoRemoved && !editSubPhotoPreview">
                <div class="mb-3">
                    <img :src="editingSubmission.photo_url" class="w-full max-h-48 object-contain rounded-xl border border-gray-200 mb-2">
                    <button @click="removeEditSubPhoto()" class="text-xs text-rose-500 hover:text-rose-600 inline-flex items-center gap-1"><?= icon('trash') ?><span>Удалить текущее фото</span></button>
                </div>
            </template>
            <div x-show="editSubPhotoPreview" class="mb-3">
                <img :src="editSubPhotoPreview" class="w-full max-h-48 object-contain rounded-xl border border-gray-200 mb-2">
                <button @click="removeNewEditSubPhoto()" class="text-xs text-rose-500 hover:text-rose-600 inline-flex items-center gap-1"><?= icon('close') ?><span>Убрать новое фото</span></button>
            </div>
            <div class="mb-3">
                <label class="block border-2 border-dashed border-gray-200 rounded-xl p-3 text-center cursor-pointer hover:border-violet-300 transition">
                    <input type="file" accept="image/*" class="hidden" @change="handleEditSubPhoto($event)">
                    <span class="text-sm text-gray-400 inline-flex items-center gap-1"><?= icon('scaner') ?><span>Загрузить новое фото</span></span>
                </label>
            </div>
            <textarea x-model="editSubContent" rows="3" placeholder="Опишите ваше блюдо..." class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 text-sm resize-none mb-4"></textarea>
            <div class="flex gap-2">
                <button @click="editingSubmission = null" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Отмена</button>
                <button @click="saveEditSubmission()" :disabled="savingSubEdit" class="flex-1 py-2.5 rounded-xl bg-violet-500 text-white font-bold text-sm hover:bg-violet-600 transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
                    <span x-show="!savingSubEdit" class="inline-flex items-center gap-2"><?= icon('diskette') ?><span>Сохранить</span></span>
                    <span x-show="savingSubEdit" style="display:none" class="inline-flex items-center gap-2"><?= icon('hourglass') ?><span>...</span></span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="showImageModal" x-transition.opacity style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4" @click.self="closeImageModal()" @keydown.escape.window="closeImageModal()">
        <div class="relative max-w-5xl max-h-[90vh] w-full">
            <button @click="closeImageModal()" class="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300 transition" aria-label="Закрыть"><?= icon('close') ?></button>
            <img :src="modalImageUrl" class="w-full h-auto max-h-[85vh] object-contain rounded-xl shadow-2xl" alt="Просмотр изображения">
            <a :href="modalImageUrl" target="_blank" class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/90 text-gray-700 rounded-lg text-sm font-semibold hover:bg-white">Открыть в новой вкладке</a>
        </div>
    </div>

</section>
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

<script src="<?= url('assets/js/social/challenge.js') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/js/social/challenge.js') ?: time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>