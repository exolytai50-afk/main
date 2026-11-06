<?php
require_once __DIR__ . '/../config/config.php';

/* ─── Валидация ─── */
$newsId = (int)($_GET['id'] ?? 0);
if ($newsId <= 0) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/* ─── Получаем новость ─── */
$news = db_one(
    "SELECT n.*, u.display_name, u.avatar_url, u.avatar_emoji
     FROM news n
     LEFT JOIN users u ON u.email = n.author_email
     WHERE n.id = ? AND n.is_published = 1",
    [$newsId]
);

if (!$news) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/* ─── Увеличиваем просмотры ─── */
db_exec('UPDATE news SET views_count = views_count + 1 WHERE id = ?', [$newsId]);

/* ─── Рейтинг ─── */
$avgRating = (($news['rating_count'] ?? 0) > 0)
    ? round($news['rating_sum'] / $news['rating_count'], 1)
    : 0;

$userRating = 0;
if (is_logged_in()) {
    $ratingCheck = db_one(
        'SELECT rating FROM news_ratings WHERE news_id = ? AND user_email = ?',
        [$newsId, current_user()['email']]
    );
    $userRating = (int)($ratingCheck['rating'] ?? 0);
}

/* ─── Мета ─── */
$pageTitle       = $news['title'] . ' — Новости GigaChef';
$pageDescription = mb_substr(strip_tags($news['content'] ?? ''), 0, 160);

/* ─── Данные для блока «Поделиться» ─── */
$scheme  = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/news/' . $newsId;

$shareData = [
    'title'       => $news['title'],
    'url'         => $pageUrl,
    'description' => mb_substr(strip_tags($news['content'] ?? ''), 0, 200),
    'image'       => !empty($news['image_url'])
                        ? $news['image_url']
                        : url('assets/icon/chef_256.png'),
];

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex items-center justify-between mb-4">
        <a href="<?= url('news') ?>" class="text-sm text-gray-400 hover:text-amber-600 transition">
            ← Все новости
        </a>

        <button type="button"
        onclick="window.dispatchEvent(new CustomEvent('open-share-modal'))"
        class="p-2 rounded-lg hover:bg-amber-50 transition"
        title="Поделиться"
        aria-label="Поделиться рецептом">
    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
    </svg>
</button>
    </div>

    <article class="bg-white rounded-2xl shadow-md overflow-hidden mb-8">
        <?php if (!empty($news['image_url'])): ?>
            <div class="relative aspect-[16/9] overflow-hidden">
                <img src="<?= e($news['image_url']) ?>"
                     onerror="this.src='/uploads/static/static.jpg'"
                     class="w-full h-full object-cover"
                     alt="<?= e($news['title']) ?>">
            </div>
        <?php endif; ?>

        <div class="p-6">
            <?php if (!empty($news['is_pinned'])): ?>
                <span class="inline-block px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-100 text-amber-700 mb-3">
                    📌 Закреплено
                </span>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400 mb-3">
                <span>📅 <?= date('d.m.Y H:i', strtotime($news['created_at'])) ?></span>
                <span>👁️ <?= (int)$news['views_count'] ?> просмотров</span>
                <?php if (!empty($news['display_name'])): ?>
                    <span>✍️ <?= e($news['display_name']) ?></span>
                <?php endif; ?>
                <?php if ($avgRating > 0): ?>
                    <span class="text-amber-500">⭐ <?= $avgRating ?></span>
                <?php endif; ?>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-800 mb-4">
                <?= e($news['title']) ?>
            </h1>

            <div class="prose max-w-none text-gray-700 leading-relaxed mb-6">
                <?= nl2br(e($news['content'])) ?>
            </div>

            <!-- Рейтинг -->
            <div class="border-t border-gray-100 pt-4 flex flex-wrap items-center gap-4"
                 x-data="{ rating: <?= $userRating ?>, submitting: false }">
                <span class="text-sm font-semibold text-gray-600">Оцените новость:</span>
                <div class="flex gap-1 text-2xl">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button"
                                @click="
                                    if (submitting) return;
                                    submitting = true;
                                    fetch('/api/news_rate.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({news_id: <?= $newsId ?>, rating: <?= $i ?>})
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        submitting = false;
                                        if (data.ok) {
                                            rating = <?= $i ?>;
                                            if (typeof toast === 'function') toast('Спасибо за оценку! ⭐', 'success');
                                        }
                                    })
                                    .catch(() => { submitting = false; });
                                "
                                :class="<?= $i ?> <= (rating || 0) ? 'text-amber-400' : 'text-gray-200'"
                                class="transition hover:scale-110"
                                aria-label="<?= $i ?> звёзд">★</button>
                    <?php endfor; ?>
                </div>
                <span class="text-sm text-gray-400"
                      x-text="rating > 0 ? rating + ' из 5' : 'Не оценено'"></span>
            </div>
        </div>
    </article>

    <!-- Комментарии -->
    <div class="bg-white rounded-2xl shadow-md p-6"
         x-data="newsComments(<?= $newsId ?>)" x-init="load()">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            💬 Комментарии (<span x-text="comments.length"></span>)
        </h2>

        <?php if (is_logged_in()): ?>
            <form @submit.prevent="submit()" class="mb-6">
                <textarea x-model="newComment" rows="3" placeholder="Напишите комментарий..."
                          class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm resize-none"></textarea>
                <button type="submit" :disabled="!newComment.trim() || sending"
                        class="mt-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold text-sm hover:shadow-md transition disabled:opacity-60">
                    Отправить
                </button>
            </form>
        <?php else: ?>
            <div class="bg-gray-50 rounded-xl p-4 mb-6 text-center">
                <p class="text-sm text-gray-500">
                    <a href="<?= url('login') ?>" class="text-amber-600 font-semibold hover:underline">Войдите</a>,
                    чтобы оставить комментарий
                </p>
            </div>
        <?php endif; ?>

        <div class="space-y-3">
            <template x-for="comment in comments" :key="comment.id">
                <div class="bg-gray-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-gray-700 text-sm"
                              x-text="comment.display_name || comment.user_email"></span>
                        <span class="text-xs text-gray-400"
                              x-text="formatDate(comment.created_at)"></span>
                    </div>
                    <p class="text-gray-600 text-sm" x-text="comment.text"></p>
                </div>
            </template>
            <p x-show="!comments.length" class="text-gray-400 text-center py-4 text-sm">
                Пока нет комментариев
            </p>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('newsComments', (newsId) => ({
        comments: [],
        newComment: '',
        sending: false,

        async load() {
            try {
                const res = await fetch('/api/news_comments.php?news_id=' + newsId);
                const data = await res.json();
                this.comments = data.comments || [];
            } catch (e) { this.comments = []; }
        },

        async submit() {
            if (!this.newComment.trim()) return;
            this.sending = true;
            try {
                const res = await fetch('/api/news_comments.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({news_id: newsId, text: this.newComment})
                });
                const data = await res.json();
                if (data.ok) {
                    this.newComment = '';
                    this.load();
                    if (typeof toast === 'function') toast('Комментарий добавлен!', 'success');
                }
            } catch (e) {}
            this.sending = false;
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString('ru-RU');
        }
    }));
});
</script>

<?php require __DIR__ . '/../components/share-block.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>