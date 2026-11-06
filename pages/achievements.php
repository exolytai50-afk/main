<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];

// Все достижения
$achievements = achievements_definitions();
uasort($achievements, function ($a, $b) {
    return ($b['points_reward'] ?? 0) <=> ($a['points_reward'] ?? 0);
});

// Полученные пользователем
$unlocked = db_all(
    'SELECT achievement_code FROM user_achievements WHERE user_email = ?',
    [$userEmail]
);
$unlockedCodes = array_column($unlocked, 'achievement_code');

// Статистика пользователя
$stats = db_one('SELECT * FROM user_stats WHERE user_id = ?', [$user['id']]) ?? [];
$recipesCount = (int)($stats['recipes_created'] ?? 0);
$points = (int)($stats['total_points'] ?? 0);
$level = (int)($stats['level'] ?? 1);

// Загружаем мета-теги для страницы
$meta = get_page_meta('/achievements');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем заголовок страницы
$pageTitle = $GLOBALS['pageTitle'] ?? 'Достижения — награды и рейтинг | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Достижения';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Система достижений GigaChef. Получайте награды за кулинарные успехи и активность.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 inline-flex items-center gap-2" data-aos="fade-up"><?= icon('achievements') ?><?= e($pageH1) ?></h1>

    <!-- Прогресс -->
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl shadow-md p-6 mb-8" data-aos="fade-up">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div>
                <div class="text-3xl font-extrabold text-amber-600"><?= $level ?></div>
                <div class="text-xs text-gray-500"><?= icon('leader') ?> Уровень</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-amber-600"><?= $points ?></div>
                <div class="text-xs text-gray-500"><?= icon('star') ?> Очков</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-amber-600"><?= $recipesCount ?></div>
                <div class="text-xs text-gray-500"><?= icon('cookbook') ?> Рецептов</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-amber-600"><?= count($unlockedCodes) ?>/<?= count($achievements) ?></div>
                <div class="text-xs text-gray-500"><?= icon('achievements') ?> Достижений</div>
            </div>
        </div>
        
        <!-- Прогресс-бар уровня -->
        <div class="mt-6">
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span><?= icon('lightning') ?> Уровень <?= $level ?></span>
                <span>Уровень <?= $level + 1 ?></span>
            </div>
            <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-amber-300 to-orange-300 rounded-full" 
                     style="width: <?= min(100, ($points % 100)) ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Список достижений -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php 
        $counter = 0;
        foreach ($achievements as $ach): 
            $isUnlocked = in_array($ach['code'], $unlockedCodes);
            $counter++;
        ?>
            <!-- Карточка достижения -->
            <div class="bg-white rounded-2xl shadow-md p-4 text-center transition <?= $isUnlocked ? '' : 'opacity-50' ?>" data-aos="fade-up">
                <div class="text-4xl mb-2"><?= achievement_icon($ach) ?></div>
                <div class="font-bold text-gray-800 text-sm"><?= e($ach['title']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= e($ach['description']) ?></div>
                <div class="mt-2">
                    <?php if ($isUnlocked): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                            <?= icon('check') ?> Получено
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-400 text-xs font-bold">
                            <?= icon('lock') ?>
                            +<?= (int)$ach['points_reward'] ?><?= icon('star') ?> очков
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
            // Вставляем рекламу после каждой 4-й карточки
            if ($counter % 4 == 0): 
                $adCode = get_ad_code('achievements_inline');
            ?>
                <!-- Рекламная карточка (нейтральная заглушка) -->
                <div class="bg-gray-50 rounded-2xl shadow-sm p-4 text-center flex flex-col justify-center items-center min-h-[160px] border border-gray-100" data-aos="fade-up">
                    <div class="text-[10px] uppercase tracking-widest text-gray-400 mb-2 font-bold">Реклама</div>
                    <?php if (!empty($adCode) && strpos($adCode, 'Место для рекламы') === false): ?>
                        <div class="w-full flex justify-center">
                            <?= $adCode ?>
                        </div>
                    <?php else: ?>
                        <!-- Визуальная заглушка, если рекламы нет -->
                        <div class="text-gray-300 mb-2">
                            <?= icon('grid', 'gc-icon-md', 2) ?>
                        </div>
                        <div class="text-xs text-gray-400 font-semibold">Здесь могла быть ваша реклама</div>
                        <div class="text-[10px] text-gray-300 mt-1">Станьте партнером GigaChef</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>