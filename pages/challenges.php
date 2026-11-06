<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/challenges');
if ($meta) {
    apply_meta($meta);
}

$pageTitle = $GLOBALS['pageTitle'] ?? 'Кулинарные челленджи — соревнования | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Челленджи';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Участвуйте в кулинарных челленджах GigaChef.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';

// Получаем челленджи с реальным количеством участников
$challenges = db_all(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM challenge_participants cp WHERE cp.challenge_id = c.id) as real_participants_count
     FROM challenges c 
     WHERE c.status = 'active' 
       AND (c.ends_at >= CURDATE() OR c.ends_at IS NULL)
     ORDER BY c.ends_at ASC"
);

// ID челленджей, в которых участвует пользователь
$joinedChallengeIds = [];
if (is_logged_in()) {
    $userEmail = current_user()['email'];
    $joined = db_all(
        'SELECT challenge_id FROM challenge_participants WHERE user_email = ?',
        [$userEmail]
    );
    $joinedChallengeIds = array_column($joined, 'challenge_id');
}

// Хелпер нормализации сложности (на случай, если в БД значения на русском)
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

$diffMap = [
    'easy'    => ['Лёгкий',  'bg-emerald-100 text-emerald-700'],
    'medium'  => ['Средний', 'bg-amber-100 text-amber-700'],
    'hard'    => ['Сложный', 'bg-rose-100 text-rose-600'],
    'unknown' => ['Средний', 'bg-gray-100 text-gray-600'],
];
?>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10" 
         x-data="challengesPage()">
    
    <div class="mb-6 text-center" data-aos="fade-up">
        <h1 class="text-3xl font-extrabold text-gray-800 inline-flex items-center justify-center gap-2">
            <?= icon('challenges') ?>
            <span><?= e($pageH1) ?></span>
        </h1>
        <p class="text-gray-500 mt-1">Участвуйте, готовьте и зарабатывайте баллы.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($challenges as $i => $ch):
        $diffKey = challenge_difficulty_normalize($ch['difficulty'] ?? null);
        $d = $diffMap[$diffKey] ?? $diffMap['unknown'];
        $daysLeft = !empty($ch['ends_at']) ? max(0, (int)ceil((strtotime($ch['ends_at']) - time()) / 86400)) : 0;
        $challengeId = (int)$ch['id'];
        $isJoined = in_array($challengeId, $joinedChallengeIds);
        $participantsCount = (int)($ch['real_participants_count'] ?? 0);
        $challengeSlug = !empty($ch['slug']) ? $ch['slug'] : $challengeId;

        // Fallback обложка
        $defaultCovers = [
            'express'  => '/uploads/static/challenge-express.jpg',
            'standard' => '/uploads/static/challenge-standard.jpg',
            'long'     => '/uploads/static/challenge-long.jpg',
            'month'    => '/uploads/static/challenge-month.jpg',
        ];
        $category = $ch['category'] ?? 'standard';
        if (!in_array($category, ['express','standard','long','month'], true)) {
            $category = 'standard';
        }
        $cover = !empty($ch['cover_image']) ? $ch['cover_image'] : $defaultCovers[$category];

        // Бейдж длительности
        $durationBadge = [
            'express'  => '3 дня',
            'standard' => '7 дней',
            'long'     => '14 дней',
            'month'    => '30 дней',
        ][$category] ?? '';
    ?>
        <a href="<?= url('challenge/' . $challengeSlug) ?>"
           class="group bg-white rounded-3xl shadow-md hover:shadow-xl overflow-hidden transition-all flex flex-col"
           data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 60 ?>">

            <!-- Обложка 16:9 -->
            <div class="relative w-full" style="aspect-ratio: 16/9;">
                <img src="<?= e($cover) ?>" alt="<?= e($ch['title']) ?>"
                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>

                <!-- Бейджи поверх -->
                <div class="absolute top-3 left-3 flex gap-2 flex-wrap">
                    <span class="px-2 py-1 rounded-lg text-xs font-bold bg-white/90 backdrop-blur-sm <?= $d[1] ?>">
                        <?= $d[0] ?>
                    </span>
                    <span class="px-2 py-1 rounded-lg text-xs font-bold bg-white/90 backdrop-blur-sm text-gray-700">
                        <?= $durationBadge ?>
                    </span>
                    <?php if ($daysLeft > 0): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-white/90 backdrop-blur-sm text-gray-700">
                            <?= icon('hourglass') ?>
                            <span><?= $daysLeft ?> дн.</span>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="absolute top-3 right-3">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-amber-400 text-amber-900">
                        <span>+<?= (int)($ch['reward_points'] ?? 0) ?></span>
                        <?= icon('star') ?>
                    </span>
                </div>
            </div>

            <!-- Контент -->
            <div class="p-5 flex-1 flex flex-col">
                <h3 class="font-extrabold text-gray-800 text-lg leading-tight mb-2 group-hover:text-amber-600 transition">
                    <?= e($ch['title']) ?>
                </h3>
                <p class="text-gray-500 text-sm line-clamp-2 mb-3">
                    <?= e($ch['description']) ?>
                </p>

                <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                        <?= icon('friends') ?>
                        <span><?= $participantsCount ?></span>
                    </span>

                    <?php if ($isJoined): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600">
                            <?= icon('check') ?>
                            <span>Открыть</span>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600">
                            <span>Участвовать</span>
                            <?= icon('right') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
    </div>

    <?php if (!$challenges): ?>
        <div class="text-center py-16 text-gray-400" data-aos="fade-up">
            Активных челленджей нет.
        </div>
    <?php endif; ?>
</section>

<!-- SVG-иконка успеха (вынесена в HTML, используется тостом) -->
<template id="tpl-party-icon"><?= icon('balloons') ?></template>

<!-- Стили для кастомного тоста (рендерит SVG) -->
<style>
#gc-toast-root{position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.gc-toast{pointer-events:auto;display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1rem;border-radius:.75rem;font-weight:700;font-size:.875rem;box-shadow:0 4px 16px rgba(0,0,0,.12);background:#fff;color:#1f2937;opacity:0;transform:translateY(-8px);transition:opacity .2s,transform .2s}
.gc-toast--in{opacity:1;transform:translateY(0)}
.gc-toast--success{background:#ecfdf5;color:#065f46}
.gc-toast--error{background:#fef2f2;color:#991b1b}
</style>
<div id="gc-toast-root" aria-live="polite" aria-atomic="true"></div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('challengesPage', () => ({
        joinedIds: <?= json_encode($joinedChallengeIds) ?>,
        joiningId: null,
        
        // Кастомный тост с SVG. Если есть глобальная toast() — используем её (текстом),
        // иначе — рендерим собственный тост с иконкой из <template id="tpl-party-icon">.
        showToast(message, type = 'success', withIcon = false) {
            if (typeof toast === 'function') {
                // Внешняя toast() не рендерит SVG — передаём чистый текст
                toast(message, type);
                return;
            }
            const root = document.getElementById('gc-toast-root');
            if (!root) { alert(message); return; }
            const el = document.createElement('div');
            el.className = 'gc-toast gc-toast--' + (type === 'error' ? 'error' : 'success');
            if (withIcon) {
                const tpl = document.getElementById('tpl-party-icon');
                if (tpl) el.appendChild(tpl.content.cloneNode(true));
            }
            const textNode = document.createElement('span');
            textNode.textContent = message;
            el.appendChild(textNode);
            root.appendChild(el);
            requestAnimationFrame(() => el.classList.add('gc-toast--in'));
            setTimeout(() => {
                el.classList.remove('gc-toast--in');
                setTimeout(() => el.remove(), 220);
            }, 3000);
        },
        
        async joinChallenge(id) {
            if (this.joiningId !== null) return;
            
            this.joiningId = id;
            
            try {
                const response = await fetch('/api/challenges.php?join=' + id, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({})
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    if (!this.joinedIds.includes(id)) {
                        this.joinedIds.push(id);
                    }
                    
                    this.showToast('Вы участвуете!', 'success', true);
                    
                    // Перезагружаем страницу для обновления
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    throw new Error(data.error || 'Ошибка');
                }
            } catch (e) {
                console.error('Join error:', e);
                
                this.showToast(e.message || 'Ошибка соединения', 'error', false);
            } finally {
                this.joiningId = null;
            }
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>