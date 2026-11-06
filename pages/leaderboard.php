<?php
require_once __DIR__ . '/../config/config.php';
$meta = get_page_meta('/leaderboard');
if ($meta) apply_meta($meta);

$pageTitle = $GLOBALS['pageTitle'] ?? 'Таблица лидеров — рейтинг кулинаров | GigaChef';
$pageH1    = $GLOBALS['pageH1']    ?? 'Таблица лидеров';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Рейтинг лучших кулинаров GigaChef. Топ пользователей по очкам, рецептам и активности.';

$GLOBALS['pageH1']          = $pageH1;
$GLOBALS['pageTitle']       = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

$top = db_all(
    'SELECT s.total_points, s.level, s.recipes_created,
            u.display_name, u.avatar_url, u.gender, u.email, u.id
     FROM user_stats s
     JOIN users u ON u.id = s.user_id
     WHERE u.role = "user"
     ORDER BY s.total_points DESC
     LIMIT 50'
);

/* ============================================================
 * Данные для блока «Как начисляются очки?»
 * ============================================================ */

/**
 * Справочник активностей: reason => [icon_name, label, points, color]
 * Теперь используем имена иконок из вашей системы.
 */
$activityPointsMap = [
    'recipe_create'    => ['icon_name' => 'chef',       'label' => 'Создание рецепта',   'points' => 10, 'color' => 'text-amber-600'],
    'recipe_save'      => ['icon_name' => 'save',       'label' => 'Сохранение рецепта', 'points' => 5,  'color' => 'text-emerald-600'],
    'review'           => ['icon_name' => 'star',       'label' => 'Отзыв',              'points' => 5,  'color' => 'text-yellow-600'],
    'favorite'         => ['icon_name' => 'bookmark',   'label' => 'Избранное',          'points' => 1,  'color' => 'text-rose-500'],
    'community'        => ['icon_name' => 'communities','label' => 'Сообщество',         'points' => 5,  'color' => 'text-blue-600'],
    'challenge_win'    => ['icon_name' => 'challenges', 'label' => 'Победа в челлендже', 'points' => 50, 'color' => 'text-emerald-600'],
    'ai_recipe'        => ['icon_name' => 'ai',         'label' => 'AI-рецепт',          'points' => 10, 'color' => 'text-violet-600'],
    'fusion_generate'  => ['icon_name' => 'flask',      'label' => 'Создание фьюжн',     'points' => 5,  'color' => 'text-purple-600'],
    'fusion_save'      => ['icon_name' => 'diskette',   'label' => 'Сохранение фьюжн',   'points' => 10, 'color' => 'text-emerald-600'],
    'cookbook_created' => ['icon_name' => 'cookbook',   'label' => 'Кулинарная книга',   'points' => 15, 'color' => 'text-blue-600'],
];

/* Количество получений каждой активности текущим пользователем */
$activityCounts = [];
$currentUserId = is_logged_in() ? (int)current_user()['id'] : 0;

if ($currentUserId > 0) {
    try {
        $rows = db_all(
            'SELECT reason, COUNT(*) AS cnt
             FROM points_log
             WHERE user_id = ? AND points > 0
             GROUP BY reason',
            [$currentUserId]
        );
        foreach ($rows as $r) {
            $activityCounts[$r['reason']] = (int)$r['cnt'];
        }
    } catch (Throwable $e) {
        // Таблица может отсутствовать
    }
}

/* Достижения пользователя (получено / нет) */
$userAchievements = [];
if ($currentUserId > 0 && function_exists('get_user_achievements')) {
    try {
        $userAchievements = get_user_achievements(current_user()['email']);
    } catch (Throwable $e) {
        $userAchievements = [];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 inline-flex items-center gap-2" data-aos="fade-up">
        <?= icon('leader', 'gc-icon-xl') ?>
        <?= e($pageH1) ?>
    </h1>

    <div class="bg-white rounded-3xl shadow-md overflow-hidden" data-aos="fade-up" data-aos-delay="100">
        <?php foreach ($top as $i => $u):
            $medal = icon('medal', 'gc-icon-sm') . ' #' . ($i + 1);
            $isCurrentUser = is_logged_in() && current_user()['id'] == $u['id'];
            $points = (int)$u['total_points'];
        ?>
            <button type="button"
                    onclick="openPointsModal(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($u['display_name'] ?: 'Пользователь'), ENT_QUOTES, 'UTF-8') ?>, <?= $points ?>)"
                    class="w-full rounded-3xl text-left flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-4
                           <?= $i < 3 ? 'bg-amber-50/50' : '' ?>
                           <?= $isCurrentUser ? 'ring-2 ring-amber-300 ring-inset' : '' ?>
                           border-b border-gray-50 hover:bg-gray-50/70 transition cursor-pointer">

                <span class="w-7 sm:w-8 text-center font-extrabold shrink-0 <?= $i < 3 ? 'text-xl' : 'text-gray-400 text-sm' ?>">
                    <?= $medal ?>
                </span>

                <?= render_avatar(
    [
        'avatar_url' => $u['avatar_url'] ?? null,
        'gender'     => $u['gender']     ?? null,
    ],
    'w-10 h-10 rounded-full object-cover ring-2 ring-amber-100 shrink-0',
    'w-10 h-10 rounded-full bg-amber-100 ring-2 ring-amber-100 flex items-center justify-center text-amber-600 shrink-0',
    'text-xl'
) ?>

                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-800 truncate">
                        <?= e($u['display_name'] ?: 'Пользователь') ?>
                        <?php if ($isCurrentUser): ?>
                            <span class="text-xs text-amber-600 ml-1">(вы)</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400 truncate">
                        Уровень <?= (int)$u['level'] ?> ·
                        <?= (int)$u['recipes_created'] ?> рецептов
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <div class="font-extrabold text-amber-600">
                        <?= number_format($points, 0, '.', ' ') ?>
                    </div>
                    <div class="text-xs text-gray-400">очков</div>
                </div>

                <span class="text-gray-300 shrink-0 hidden sm:inline" aria-hidden="true">›</span>
            </button>
        <?php endforeach; ?>

        <?php if (!$top): ?>
            <div class="text-center py-16 text-gray-400">
                Рейтинг пуст. Будьте первым!
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         Информация о начислении очков: Активности + Достижения
         ============================================================ -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- ===================== АКТИВНОСТИ ===================== -->
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl p-5 text-sm text-gray-600">
            <h3 class="font-bold text-amber-800 mb-3 flex items-center gap-2">
                <?= icon('lightning', 'gc-icon-lg') ?> Активности
            </h3>
            <ul class="space-y-2">
                <?php foreach ($activityPointsMap as $reason => $meta): ?>
                    <?php $cnt = $activityCounts[$reason] ?? 0; ?>
                    <li class="flex items-center gap-2">
                        <!-- Теперь вызываем icon() с именем иконки -->
                        <span class="shrink-0"><?= icon($meta['icon_name'], 'gc-icon-md') ?></span>
                        <span class="flex-1 min-w-0">
                            <?= e($meta['label']) ?>
                            <span class="font-semibold <?= $meta['color'] ?>">+<?= (int)$meta['points'] ?></span>
                        </span>
                        <span class="shrink-0 text-xs px-2 py-0.5 rounded-full
                            <?= $cnt > 0 ? 'bg-amber-100 text-amber-700 font-semibold' : 'bg-gray-100 text-gray-400' ?>">
                            <?= $cnt > 0 ? '×' . $cnt : '—' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($currentUserId === 0): ?>
                <p class="text-xs text-gray-400 mt-3">
                    Войдите, чтобы видеть свою статистику по активностям.
                </p>
            <?php endif; ?>
        </div>

        <!-- ===================== ДОСТИЖЕНИЯ ===================== -->
        <div class="bg-gradient-to-r from-violet-50 to-purple-50 rounded-2xl p-5 text-sm text-gray-600">
            <h3 class="font-bold text-violet-800 mb-3 flex items-center gap-2">
                <?= icon('medal', 'gc-icon-lg') ?> Достижения
            </h3>

            <?php if ($currentUserId === 0): ?>
                <p class="text-xs text-gray-400">
                    Войдите, чтобы отслеживать получение достижений.
                </p>
            <?php elseif (!$userAchievements): ?>
                <p class="text-xs text-gray-400">
                    Список достижений пока недоступен.
                </p>
            <?php else: ?>
                <ul class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($userAchievements as $ach): ?>
                        <li class="flex items-center gap-2">
                            <!-- Вызываем achievement_icon, которая внутри использует icon() -->
                            <span class="shrink-0"><?= achievement_icon($ach) ?></span>
                            <span class="flex-1 min-w-0 truncate" title="<?= e($ach['description']) ?>">
                                <?= e($ach['title']) ?>
                                <span class="font-semibold text-violet-600">+<?= (int)$ach['points'] ?></span>
                            </span>
                            <?php if (!empty($ach['unlocked'])): ?>
                                <span class="shrink-0 text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold"
                                      title="Получено <?= e($ach['unlocked_at'] ?? '') ?>">
                                    ✓ Получено
                                </span>
                            <?php else: ?>
                                <span class="shrink-0 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">
                                    Не получено
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Модальное окно: история начислений -->
<div id="pointsModal"
     class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/60 p-4"
     onclick="if (event.target === this) closePointsModal()">

    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col"
         onclick="event.stopPropagation()">

        <div class="flex items-center justify-between p-4 sm:p-5 border-b border-gray-100">
            <div class="min-w-0">
                <h3 class="font-extrabold text-gray-800 text-lg truncate" id="pointsModalTitle">Очки активности</h3>
                <p class="text-xs text-gray-400" id="pointsModalSubtitle"></p>
            </div>
            <button type="button" onclick="closePointsModal()"
                    class="text-gray-400 hover:text-gray-600 text-2xl w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition"
                    aria-label="Закрыть">×</button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-5" id="pointsModalBody">
            <div class="text-center text-gray-400 py-8">
                <div class="animate-spin inline-block w-6 h-6 border-2 border-amber-200 border-t-amber-500 rounded-full"></div>
                <p class="mt-2 text-sm">Загрузка…</p>
            </div>
        </div>
    </div>
</div>
<script>
// ─── Заменяем огромные SVG на имена иконок для JS ───
// В JS мы не можем напрямую вызвать PHP-функцию icon(), поэтому используем window.gcIcon(name)
const POINTS_ICONS = {
    sleeping:   <?= json_encode(icon('rest', 'gc-icon-xl', 5)) ?>,
    empty:      <?= json_encode(icon('file', 'gc-icon-md', 1.5)) ?>,
    sparkle:    <?= json_encode(icon('sparkle', 'gc-icon-md')) ?>,
    save:       <?= json_encode(icon('diskette', 'gc-icon-md')) ?>,
    star:       <?= json_encode(icon('star', 'gc-icon-md')) ?>,
    bookmark:   <?= json_encode(icon('bookmark', 'gc-icon-md')) ?>,
    users:      <?= json_encode(icon('communities', 'gc-icon-md')) ?>,
    trophy:     <?= json_encode(icon('challenges', 'gc-icon-md')) ?>,
    medal:      <?= json_encode(icon('medal', 'gc-icon-md')) ?>,
    robot:      <?= json_encode(icon('ai', 'gc-icon-md')) ?>,
    flask:      <?= json_encode(icon('flask', 'gc-icon-md')) ?>,
    disk:       <?= json_encode(icon('diskette', 'gc-icon-md')) ?>,
    book:       <?= json_encode(icon('cookbook', 'gc-icon-md')) ?>,
    clapper:    <?= json_encode(icon('video', 'gc-icon-md')) ?>,
    warning:    <?= json_encode(icon('warning', 'gc-icon-md')) ?>,
    footprint:  <?= json_encode(icon('steps', 'gc-icon-md')) ?>
};

function openPointsModal(userId, displayName, points) {
    const modal = document.getElementById('pointsModal');
    const title = document.getElementById('pointsModalTitle');
    const subtitle = document.getElementById('pointsModalSubtitle');
    const body = document.getElementById('pointsModalBody');

    title.textContent = displayName;
    subtitle.textContent = points + ' очков всего';

    body.innerHTML = `
        <div class="text-center text-gray-400 py-8">
            <div class="animate-spin inline-block w-6 h-6 border-2 border-amber-200 border-t-amber-500 rounded-full"></div>
            <p class="mt-2 text-sm">Загрузка…</p>
        </div>`;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    if (points === 0) {
        body.innerHTML = `
            <div class="text-center py-10">
                <div class="flex justify-center mb-3 text-gray-300">${POINTS_ICONS.sleeping}</div>
                <p class="text-gray-500 font-semibold">У участника пока нет очков активности</p>
                <p class="text-xs text-gray-400 mt-2">Очки начисляются за рецепты, отзывы и победы в челленджах</p>
            </div>`;
        return;
    }

    fetch('/api/points_log.php?user_id=' + encodeURIComponent(userId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (!data.ok || !data.items || data.items.length === 0) {
            body.innerHTML = `
                <div class="text-center py-10">
                    <div class="flex justify-center mb-3 text-gray-300">${POINTS_ICONS.empty}</div>
                    <p class="text-gray-500">История начислений пуста</p>
                    <p class="text-xs text-gray-400 mt-2">Скорее всего, очки начислялись до введения журнала</p>
                </div>`;
            return;
        }

        const reasonMeta = {
            recipe_create:    { icon: POINTS_ICONS.sparkle,  label: 'Создание рецепта',   color: 'text-amber-600' },
            recipe_save:      { icon: POINTS_ICONS.save,     label: 'Сохранение рецепта', color: 'text-emerald-600' },
            review:           { icon: POINTS_ICONS.star,     label: 'Отзыв',              color: 'text-yellow-600' },
            favorite:         { icon: POINTS_ICONS.bookmark, label: 'Избранное',          color: 'text-rose-500' },
            community:        { icon: POINTS_ICONS.users,    label: 'Сообщество',         color: 'text-blue-600' },
            challenge_win:    { icon: POINTS_ICONS.trophy,   label: 'Победа',             color: 'text-emerald-600' },
            achievement:      { icon: POINTS_ICONS.medal,    label: 'Достижение',         color: 'text-amber-600' },
            ai_recipe:        { icon: POINTS_ICONS.robot,    label: 'AI-рецепт',          color: 'text-violet-600' },
            fusion_generate:  { icon: POINTS_ICONS.flask,    label: 'Создание фьюжн',     color: 'text-purple-600' },
            fusion_save:      { icon: POINTS_ICONS.disk,     label: 'Сохранение фьюжн',   color: 'text-emerald-600' },
            cookbook_created: { icon: POINTS_ICONS.book,     label: 'Кулинарная книга',   color: 'text-blue-600' },
            challenge_join:   { icon: POINTS_ICONS.clapper,  label: 'Челлендж',           color: 'text-green-600' },
            test_reason:      { icon: POINTS_ICONS.footprint, label: 'Тестовая запись',   color: 'text-gray-600' },
        };

        let html = '<div class="space-y-2">';
        data.items.forEach(function (item) {
            const meta = reasonMeta[item.reason] || { icon: POINTS_ICONS.medal, label: item.reason, color: 'text-gray-600' };
            const date = new Date(item.created_at.replace(' ', 'T')).toLocaleDateString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });
            
            html += `
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 hover:bg-gray-100/70 transition">
                    <span class="shrink-0 flex items-center justify-center text-xl mt-0.5">${meta.icon}</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-gray-800">${meta.label}</div>
                        <div class="text-xs text-gray-500 truncate">${item.description || ''}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">${date}</div>
                    </div>
                    <div class="font-extrabold shrink-0 ${meta.color}">+${item.points}</div>
                </div>`;
        });
        html += '</div>';
        body.innerHTML = html;
    })
    .catch(e => {
        console.error(e);
        body.innerHTML = `
            <div class="text-center py-10">
                <div class="flex justify-center mb-3 text-rose-400">${POINTS_ICONS.warning}</div>
                <p class="text-gray-500">Не удалось загрузить историю</p>
            </div>`;
    });
}

function closePointsModal() {
    const modal = document.getElementById('pointsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePointsModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>