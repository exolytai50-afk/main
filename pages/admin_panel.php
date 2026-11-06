<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

function admin_count(string $table, string $where = ''): int {
    try {
        $sql = "SELECT COUNT(*) c FROM `{$table}`";
        if ($where) $sql .= " WHERE {$where}";
        $row = db_one($sql);
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$stats = [
    'users'       => admin_count('users'),
    'recipes'     => admin_count('recipes'),
    'ai_recipes'  => admin_count('recipes', 'is_ai_generated=1'),
    'comments'    => admin_count('comments'),
    'communities' => admin_count('communities'),
    'challenges'  => admin_count('challenges'),
    'subscriptions' => admin_count('subscriptions', "status='active'"),
];

// Последние действия
$latest = [
    'recipes'  => db_all('SELECT id, slug, title, created_at FROM recipes ORDER BY created_at DESC LIMIT 5'),
    'users'    => db_all('SELECT id, email, display_name, created_at FROM users ORDER BY created_at DESC LIMIT 5'),
    'comments' => db_all('SELECT c.id, c.text, c.created_at, r.title as recipe_title 
                          FROM comments c JOIN recipes r ON r.id = c.recipe_id 
                          ORDER BY c.created_at DESC LIMIT 5'),
];

$pageTitle = 'Админ-панель';
require_once __DIR__ . '/../includes/header.php';

$statCards = [
    ['admin/users',       'user',        'bg-blue-100 text-blue-600',       'Пользователи',      $stats['users'],         ''],
    ['admin/recipes',     'cookbook',    'bg-amber-100 text-amber-600',     'Рецепты',           $stats['recipes'],       $stats['ai_recipes'] . ' от AI'],
    ['admin/comments',    'quote',       'bg-emerald-100 text-emerald-600', 'Комментарии',       $stats['comments'],      ''],
    ['admin/communities', 'communities', 'bg-violet-100 text-violet-600',   'Сообщества',        $stats['communities'],   ''],
    ['admin/challenges',  'challenges',  'bg-rose-100 text-rose-600',       'Челленджи',         $stats['challenges'],    ''],
    ['admin/users',       'crown',       'bg-indigo-100 text-indigo-600',   'Активные подписки', $stats['subscriptions'], ''],
];
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('panel', 'home', 'Панель управления', APP_NAME . ' · v1.0'); ?>

    <!-- Статистика -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4 mb-8">
        <?php foreach ($statCards as $c): ?>
        <a href="<?= url($c[0]) ?>" class="bg-white rounded-2xl shadow-md p-4 hover:shadow-lg transition flex flex-col gap-3">
            <span class="w-10 h-10 rounded-xl inline-flex items-center justify-center <?= $c[2] ?>"><?= icon($c[1], 'gc-icon-md') ?></span>
            <span>
                <span class="block text-2xl font-extrabold text-gray-800 leading-none"><?= (int)$c[4] ?></span>
                <span class="block text-sm text-gray-500 mt-1"><?= e($c[3]) ?></span>
                <?php if ($c[5] !== ''): ?><span class="block text-xs text-gray-400"><?= e($c[5]) ?></span><?php endif; ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Разделы -->
    <h2 class="text-lg font-bold text-gray-800 mb-3">Разделы</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-8">
        <?php foreach (admin_nav_items() as $key => $item): if ($key === 'panel') continue; ?>
        <a href="<?= url($item[0]) ?>" class="bg-white rounded-2xl shadow-md p-4 hover:shadow-lg transition group flex items-center gap-4">
            <span class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 inline-flex items-center justify-center shrink-0 group-hover:bg-amber-100 transition">
                <?= icon($item[1], 'gc-icon-heading') ?>
            </span>
            <span class="min-w-0">
                <span class="block font-bold text-gray-800 group-hover:text-amber-600 transition"><?= e($item[2]) ?></span>
                <span class="block text-sm text-gray-400 truncate"><?= e($item[3]) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Последние события -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('cookbook', 'gc-icon-sm') ?> Последние рецепты</div>
            <?php foreach ($latest['recipes'] as $r): ?>
            <div class="flex justify-between gap-3 px-5 py-3 border-b border-gray-50 text-sm">
                <a href="<?= url('recipe/' . ($r['slug'] ?: $r['id'])) ?>" class="text-gray-700 hover:text-amber-600 truncate"><?= e($r['title']) ?></a>
                <span class="text-gray-400 shrink-0"><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$latest['recipes']): ?><div class="px-5 py-6 text-center text-sm text-gray-400">Пока пусто</div><?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('user', 'gc-icon-sm') ?> Новые пользователи</div>
            <?php foreach ($latest['users'] as $u): ?>
            <div class="flex justify-between gap-3 px-5 py-3 border-b border-gray-50 text-sm">
                <span class="text-gray-700 truncate"><?= e($u['display_name'] ?: $u['email']) ?></span>
                <span class="text-gray-400 shrink-0"><?= date('d.m.Y', strtotime($u['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$latest['users']): ?><div class="px-5 py-6 text-center text-sm text-gray-400">Пока пусто</div><?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('quote', 'gc-icon-sm') ?> Последние комментарии</div>
            <?php foreach ($latest['comments'] as $c): ?>
            <div class="px-5 py-3 border-b border-gray-50 text-sm">
                <div class="text-gray-700 truncate"><?= e(mb_strimwidth((string)$c['text'], 0, 80, '…')) ?></div>
                <div class="flex justify-between gap-3 text-xs text-gray-400 mt-0.5">
                    <span class="truncate"><?= e($c['recipe_title']) ?></span>
                    <span class="shrink-0"><?= date('d.m.Y', strtotime($c['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$latest['comments']): ?><div class="px-5 py-6 text-center text-sm text-gray-400">Пока пусто</div><?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
