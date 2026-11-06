<?php
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Новости — GigaChef';
$pageDescription = 'Новости и обновления кулинарной соцсети GigaChef';

// Получаем новости (закрепленные первыми)
$news = db_all(
    "SELECT n.*, u.display_name, u.avatar_url, u.avatar_emoji,
            (SELECT COUNT(*) FROM news_comments WHERE news_id = n.id) as comments_count
     FROM news n
     LEFT JOIN users u ON u.email = n.author_email
     WHERE n.is_published = 1
     ORDER BY n.is_pinned DESC, n.created_at DESC
     LIMIT 50"
);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <div class="text-center mb-8">
        <span class="inline-block px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-bold mb-3">📰 Новости</span>
        <h1 class="text-3xl font-extrabold text-gray-800">Новости GigaChef</h1>
        <p class="text-gray-500 mt-2">Последние обновления и анонсы</p>
    </div>

    <?php if (empty($news)): ?>
        <div class="text-center py-16">
            <div class="text-6xl mb-4">📰</div>
            <p class="text-gray-400">Новостей пока нет</p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($news as $item): 
                $newsUrl = url('news/' . $item['id']);
                $newsImage = !empty($item['image_url']) ? $item['image_url'] : '/uploads/static/static.jpg';
                $avgRating = $item['rating_count'] > 0 ? round($item['rating_sum'] / $item['rating_count'], 1) : 0;
            ?>
            <article class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition <?= $item['is_pinned'] ? 'border-2 border-amber-300' : '' ?>" data-aos="fade-up">
                <?php if ($item['is_pinned']): ?>
                    <div class="bg-gradient-to-r from-amber-400 to-orange-400 text-white px-4 py-1.5 text-xs font-bold flex items-center gap-2">
                        📌 Закрепленная новость
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($item['image_url'])): ?>
                <a href="<?= $newsUrl ?>" class="block relative aspect-[16/9] overflow-hidden">
                    <img src="<?= e($item['image_url']) ?>" 
                         onerror="this.src='/uploads/static/static.jpg'"
                         class="w-full h-full object-cover hover:scale-105 transition duration-500"
                         alt="<?= e($item['title']) ?>"
                         loading="lazy">
                </a>
                <?php endif; ?>
                
                <div class="p-5">
                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                        <span>📅 <?= date('d.m.Y', strtotime($item['created_at'])) ?></span>
                        <span>•</span>
                        <span>👁️ <?= (int)$item['views_count'] ?> просмотров</span>
                        <span>•</span>
                        <span>💬 <?= (int)$item['comments_count'] ?> комментариев</span>
                        <?php if ($avgRating > 0): ?>
                        <span>•</span>
                        <span>⭐ <?= $avgRating ?> (<?= (int)$item['rating_count'] ?>)</span>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="text-xl font-bold text-gray-800 mb-2">
                        <a href="<?= $newsUrl ?>" class="hover:text-amber-600 transition">
                            <?= e($item['title']) ?>
                        </a>
                    </h2>
                    
                    <p class="text-gray-500 text-sm leading-relaxed clamp-3 mb-3">
                        <?= e(mb_substr(strip_tags($item['content']), 0, 300)) ?>...
                    </p>
                    
                    <a href="<?= $newsUrl ?>" class="inline-block text-amber-600 font-semibold text-sm hover:underline">
                        Читать полностью →
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>