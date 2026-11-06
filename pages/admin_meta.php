<?php
/**
 * GigaChef — Управление мета-тегами
 * 
 * Источник списка страниц: config/meta_pages.php
 * Только ручное редактирование. Без автогенерации и автопоиска.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_once __DIR__ . '/../includes/sitemap_scanner.php';
require_admin();

// ============================================================
//  СОЗДАНИЕ / МИГРАЦИЯ ТАБЛИЦ
// ============================================================
try {
    db_exec("CREATE TABLE IF NOT EXISTS `page_meta` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `page_path` varchar(255) NOT NULL,
        `page_name` varchar(255) NOT NULL,
        `meta_title` varchar(255) DEFAULT NULL,
        `meta_description` text DEFAULT NULL,
        `h1` varchar(255) DEFAULT NULL,
        `meta_keywords` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `page_path` (`page_path`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Проверяем/добавляем created_at и updated_at если их нет
    $cols = db_all("SHOW COLUMNS FROM page_meta");
    $colNames = array_column($cols, 'Field');
    
    if (!in_array('created_at', $colNames, true)) {
        db_exec("ALTER TABLE page_meta ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('updated_at', $colNames, true)) {
        db_exec("ALTER TABLE page_meta ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // Добавляем колонки в recipes и recipe_categories
    $columnsToAdd = [
        'meta_title'       => 'varchar(255) DEFAULT NULL',
        'meta_description' => 'text DEFAULT NULL',
        'h1'               => 'varchar(255) DEFAULT NULL',
        'meta_keywords'    => 'text DEFAULT NULL',
    ];

    foreach (['recipes', 'recipe_categories', 'recipe_tags'] as $table) {
        try {
            $existingCols = db_all("SHOW COLUMNS FROM {$table}");
            $existingNames = array_column($existingCols, 'Field');
            foreach ($columnsToAdd as $col => $type) {
                if (!in_array($col, $existingNames, true)) {
                    db_exec("ALTER TABLE {$table} ADD COLUMN `{$col}` {$type}");
                }
            }
        } catch (Throwable $e) {
            error_log("admin_meta migration {$table}: " . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('admin_meta init error: ' . $e->getMessage());
}

$pageTitle = 'Управление мета-тегами';
$success = '';
$error = '';

// ============================================================
//  ОБРАБОТКА POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ---- Синхронизация страниц ----
    if ($action === 'sync_pages') {
        try {
            $result = sync_pages_with_db(true, true);
            
            $success = sprintf(
                'Whitelist: %d страниц. Добавлено: %d. Обновлено: %d. Пропущено: %d.',
                $result['whitelist_count'],
                $result['added'],
                $result['updated'],
                $result['skipped']
            );
            
            if (!empty($result['errors'])) {
                $error = 'Ошибки (' . count($result['errors']) . '): '
                       . implode('; ', array_slice($result['errors'], 0, 3));
            }
        } catch (Throwable $e) {
            $error = 'Ошибка синхронизации: ' . $e->getMessage();
        }
    }

    // ---- Очистить от мусора (удалить всё, чего нет в whitelist) ----
    elseif ($action === 'cleanup_pages') {
        try {
            $result = sync_pages_with_db(false, false);
            $success = sprintf(
                'Удалено лишних записей: %d. Обновлено: %d.',
                $result['removed'] ?? 0,
                $result['updated'] ?? 0
            );
        } catch (Throwable $e) {
            $error = 'Ошибка очистки: ' . $e->getMessage();
        }
    }

    // ---- Сохранить мета страницы ----
    elseif ($action === 'save_page_meta') {
        $pagePath = trim($_POST['page_path'] ?? '');
        $pageName = trim($_POST['page_name'] ?? '');

        if ($pagePath && $pageName) {
            try {
                $existing = db_one("SELECT id FROM page_meta WHERE page_path = ?", [$pagePath]);

                if ($existing) {
                    db_exec("UPDATE page_meta SET page_name=?, meta_title=?, meta_description=?, h1=?, meta_keywords=?, updated_at=NOW() WHERE page_path=?",
                        [$pageName, trim($_POST['meta_title'] ?? ''), trim($_POST['meta_description'] ?? ''), trim($_POST['h1'] ?? ''), trim($_POST['meta_keywords'] ?? ''), $pagePath]);
                } else {
                    db_exec("INSERT INTO page_meta (page_path, page_name, meta_title, meta_description, h1, meta_keywords, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())",
                        [$pagePath, $pageName, trim($_POST['meta_title'] ?? ''), trim($_POST['meta_description'] ?? ''), trim($_POST['h1'] ?? ''), trim($_POST['meta_keywords'] ?? '')]);
                }
                $success = 'Мета-теги страницы сохранены';
            } catch (Throwable $e) {
                $error = 'Ошибка: ' . $e->getMessage();
            }
        }
    }

    // ---- Сохранить мета категории ----
    elseif ($action === 'save_category_meta') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            try {
                db_exec("UPDATE recipe_categories SET meta_title=?, meta_description=?, h1=?, meta_keywords=? WHERE id=?",
                    [trim($_POST['meta_title'] ?? ''), trim($_POST['meta_description'] ?? ''), trim($_POST['h1'] ?? ''), trim($_POST['meta_keywords'] ?? ''), $categoryId]);
                $success = 'Мета-теги категории сохранены';
            } catch (Throwable $e) {
                $error = 'Ошибка: ' . $e->getMessage();
            }
        }
    }

    // ---- Сохранить мета рецепта ----
    elseif ($action === 'save_recipe_meta') {
        $recipeId = (int)($_POST['recipe_id'] ?? 0);
        if ($recipeId > 0) {
            try {
                db_exec("UPDATE recipes SET meta_title=?, meta_description=?, h1=?, meta_keywords=? WHERE id=?",
                    [trim($_POST['meta_title'] ?? ''), trim($_POST['meta_description'] ?? ''), trim($_POST['h1'] ?? ''), trim($_POST['meta_keywords'] ?? ''), $recipeId]);
                $success = 'Мета-теги рецепта сохранены';
            } catch (Throwable $e) {
                $error = 'Ошибка: ' . $e->getMessage();
            }
        }
    }
    
    // ---- Сохранить мета тега ----
elseif ($action === 'save_tag_meta') {
    $tagId = (int)($_POST['tag_id'] ?? 0);
    if ($tagId > 0) {
        try {
            db_exec(
                "UPDATE recipe_tags
                 SET meta_title = ?, meta_description = ?, h1 = ?, meta_keywords = ?
                 WHERE id = ?",
                [
                    trim($_POST['meta_title'] ?? ''),
                    trim($_POST['meta_description'] ?? ''),
                    trim($_POST['h1'] ?? ''),
                    trim($_POST['meta_keywords'] ?? ''),
                    $tagId
                ]
            );
            $success = 'Мета-теги тега сохранены';
        } catch (Throwable $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    }
}

    // ---- Удалить мета страницы ----
    elseif ($action === 'delete_page_meta') {
        $pagePath = trim($_POST['page_path'] ?? '');
        if ($pagePath) {
            try {
                db_exec("DELETE FROM page_meta WHERE page_path = ?", [$pagePath]);
                $success = 'Запись удалена';
            } catch (Throwable $e) {
                $error = 'Ошибка: ' . $e->getMessage();
            }
        }
    }
}

// ============================================================
//  ДАННЫЕ ДЛЯ ОТОБРАЖЕНИЯ
// ============================================================
$allPages = get_all_pages_for_meta();

$categories = [];
try {
    $categories = db_all('SELECT * FROM recipe_categories WHERE is_active=1 ORDER BY name');
} catch (Throwable $e) {}

// ---- ТЕГИ ----
$tagsByGroup = [];
try {
    $tagRows = db_all(
        "SELECT id, slug, name, icon_svg, tag_group,
                meta_title, meta_description, h1, meta_keywords
         FROM recipe_tags
         WHERE is_active = 1
         ORDER BY tag_group, sort_order, name"
    );
    foreach ($tagRows as $t) {
        $tagsByGroup[$t['tag_group']][] = $t;
    }
} catch (Throwable $e) {
    $tagsByGroup = [];
}

// Общее число тегов
$totalTags = 0;
foreach ($tagsByGroup as $arr) $totalTags += count($arr);

$recipesList = db_all("
    SELECT id, title, meta_title, meta_description, h1, meta_keywords 
    FROM recipes 
    ORDER BY id DESC 
    LIMIT 100
");

$totalInDb = 0;
try {
    $totalInDb = (int)db_one("SELECT COUNT(*) as cnt FROM page_meta")['cnt'];
} catch (Throwable $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" x-data="{ tab: 'pages' }">

    <?php admin_top('meta', 'tags', 'Мета-теги', 'Title, H1, description и keywords для SEO'); ?>
    <?php admin_flash($success, $error); ?>

    <!-- Синхронизация -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl shadow-md p-6 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><?= icon('document', 'gc-icon-md') ?> Список страниц</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Источник: <code class="text-xs bg-white px-1.5 py-0.5 rounded">config/meta_pages.php</code>
                    — только SEO-страницы. Рецепты и категории — отдельные вкладки.
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Сейчас в whitelist: <strong><?= count($allPages) ?></strong>,
                    в БД: <strong><?= $totalInDb ?></strong>
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <form method="POST">
                    <input type="hidden" name="action" value="sync_pages">
                    <button type="submit" class="<?= admin_cls('btn') ?> bg-blue-500 text-white hover:bg-blue-600">
                        <?= icon('refresh', 'gc-icon-sm') ?> Синхронизировать
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Удалить все записи, которых нет в whitelist?')">
                    <input type="hidden" name="action" value="cleanup_pages">
                    <button type="submit" class="<?= admin_cls('btn') ?> bg-rose-100 text-rose-700 hover:bg-rose-200">
                        <?= icon('trash', 'gc-icon-sm') ?> Очистить мусор
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Вкладки -->
    <?php admin_subtabs([
        ['pages',      'document', 'Страницы',   count($allPages)],
        ['categories', 'list',     'Категории',  count($categories)],
        ['tags',       'tags',     'Теги',       (int)$totalTags],
        ['recipes',    'cookbook', 'Рецепты',    count($recipesList)],
    ]); ?>

    <!-- СТРАНИЦЫ -->
    <div x-show="tab === 'pages'" x-transition>
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                <?= icon('document', 'gc-icon-sm') ?> Страницы из whitelist
                <span class="text-sm font-normal text-gray-400">(нажмите на карандаш для редактирования)</span>
            </div>

            <?php if (empty($allPages)): ?>
                <div class="p-8 text-center text-gray-400">
                    <p class="mb-3">Whitelist пуст. Проверьте файл <code>config/meta_pages.php</code>.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="sync_pages">
                        <button type="submit" class="<?= admin_cls('btn') ?> bg-blue-500 text-white hover:bg-blue-600">
                            <?= icon('refresh', 'gc-icon-sm') ?> Попробовать синхронизацию
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <?php foreach ($allPages as $page): 
                    $hasMeta = !empty($page['meta_title']) || !empty($page['meta_description']);
                ?>
                <div class="border-b border-gray-50 p-4" x-data="{ editing: false }">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2 flex-wrap">
                                <?= e($page['page_name']) ?>
                                <?php if ($hasMeta): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center gap-1"><?= icon('check', 'gc-icon-xs') ?> Мета заполнены</span>
                                <?php else: ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Пусто</span>
                                <?php endif; ?>
                            </h3>
                            <span class="text-xs text-gray-400"><?= e($page['page_path']) ?></span>
                        </div>
                        <button @click="editing = !editing" class="<?= admin_cls('ibtn ibtn-edit') ?> bg-amber-50 shrink-0" aria-label="Редактировать" title="Редактировать">
                            <span x-show="!editing" class="inline-flex"><?= icon('edit', 'gc-icon-sm') ?></span>
                            <span x-show="editing" style="display:none" class="inline-flex"><?= icon('close', 'gc-icon-sm') ?></span>
                        </button>
                    </div>

                    <form method="POST" x-show="editing" style="display:none" class="mt-3 space-y-3">
                        <input type="hidden" name="action" value="save_page_meta">
                        <input type="hidden" name="page_path" value="<?= e($page['page_path']) ?>">
                        <input type="hidden" name="page_name" value="<?= e($page['page_name']) ?>">

                        <div>
                            <label class="<?= admin_cls('label') ?>">Title</label>
                            <input type="text" name="meta_title"
                                   value="<?= e($page['meta_title'] ?? '') ?>"
                                   placeholder="<?= e($page['page_name']) ?> | GigaChef"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">H1</label>
                            <input type="text" name="h1"
                                   value="<?= e($page['h1'] ?? '') ?>"
                                   placeholder="<?= e($page['page_name']) ?>"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">Description</label>
                            <textarea name="meta_description" rows="2"
                                      placeholder="Краткое описание страницы"
                                      class="<?= admin_cls('input') ?>"><?= e($page['meta_description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">Keywords</label>
                            <input type="text" name="meta_keywords"
                                   value="<?= e($page['meta_keywords'] ?? '') ?>"
                                   placeholder="через, запятую"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="<?= admin_cls('btn btn-success') ?>"><?= icon('diskette', 'gc-icon-sm') ?> Сохранить</button>
                            <button type="button" @click="editing = false" class="<?= admin_cls('btn btn-ghost') ?>">Отмена</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- КАТЕГОРИИ -->
    <div x-show="tab === 'categories'" x-transition style="display:none">
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2">
                <?= icon('list', 'gc-icon-sm') ?> Категории рецептов (<?= count($categories) ?>)
            </div>

            <?php if (empty($categories)): ?>
                <div class="p-8 text-center text-gray-400">Категории не найдены в <code>recipe_categories</code></div>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <div class="border-b border-gray-50 p-4" x-data="{ editing: false }">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex-1 min-w-0">
                            <?php
                                $catIcon = (string)($cat['icon'] ?? '');
                                $catIconIsName = ($catIcon !== '' && preg_match('/^[a-z0-9_\-]+$/i', $catIcon));
                            ?>
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <?php if ($catIconIsName): ?><?= icon($catIcon, 'gc-icon-sm') ?><?php elseif ($catIcon !== ''): ?><span><?= e($catIcon) ?></span><?php else: ?><?= icon('dinner', 'gc-icon-sm') ?><?php endif; ?>
                                <span><?= e($cat['name']) ?></span>
                            </h3>
                            <?php if (!empty($cat['slug'])): ?>
                                <span class="text-xs text-gray-400">/category/<?= e($cat['slug']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button @click="editing = !editing" class="<?= admin_cls('ibtn ibtn-edit') ?> bg-amber-50 shrink-0" aria-label="Редактировать" title="Редактировать">
                            <span x-show="!editing" class="inline-flex"><?= icon('edit', 'gc-icon-sm') ?></span>
                            <span x-show="editing" style="display:none" class="inline-flex"><?= icon('close', 'gc-icon-sm') ?></span>
                        </button>
                    </div>

                    <form method="POST" x-show="editing" style="display:none" class="mt-3 space-y-3">
                        <input type="hidden" name="action" value="save_category_meta">
                        <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">

                        <div>
                            <label class="<?= admin_cls('label') ?>">Title</label>
                            <input type="text" name="meta_title" value="<?= e($cat['meta_title'] ?? '') ?>"
                                   placeholder="<?= e($cat['name']) ?> — рецепты | GigaChef"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">H1</label>
                            <input type="text" name="h1" value="<?= e($cat['h1'] ?? '') ?>"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">Description</label>
                            <textarea name="meta_description" rows="2"
                                      class="<?= admin_cls('input') ?>"><?= e($cat['meta_description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="<?= admin_cls('label') ?>">Keywords</label>
                            <input type="text" name="meta_keywords" value="<?= e($cat['meta_keywords'] ?? '') ?>"
                                   class="<?= admin_cls('input') ?>">
                        </div>
                        <button type="submit" class="<?= admin_cls('btn btn-success') ?>"><?= icon('diskette', 'gc-icon-sm') ?> Сохранить</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ТЕГИ -->
<div x-show="tab === 'tags'" x-transition style="display:none">
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2 flex-wrap">
            <?= icon('tags', 'gc-icon-sm') ?> Теги (<?= (int)$totalTags ?>)
            <span class="text-sm font-normal text-gray-400">(сгруппированы по типу)</span>
        </div>

        <?php if (empty($tagsByGroup)): ?>
            <div class="p-8 text-center text-gray-400">
                Теги не найдены в <code>recipe_tags</code>
            </div>
        <?php else: ?>

            <?php
            // Человекочитаемые названия групп — используем функцию, если есть
            $groupLabels = [
                'cuisine'     => ['globe',        'Кухня'],
                'holiday'     => ['holiday',      'Повод'],
                'diet'        => ['diet',         'Тип питания'],
                'restriction' => ['restrictions', 'Ограничения'],
                'season'      => ['season',       'Сезон'],
                'time'        => ['time',         'Время готовки'],
                'difficulty'  => ['settings',     'Сложность'],
            ];
            ?>

            <?php foreach ($tagsByGroup as $group => $tags): ?>
                <div class="border-b border-gray-100">
                    <!-- Заголовок группы -->
                    <div class="px-5 py-3 bg-gray-50 flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 text-sm flex items-center gap-2">
                            <?php if (isset($groupLabels[$group])): ?><?= icon($groupLabels[$group][0], 'gc-icon-sm') ?><?php endif; ?>
                            <?= e($groupLabels[$group][1] ?? $group) ?>
                            <span class="text-xs text-gray-400 font-normal ml-1">
                                (<?= count($tags) ?>)
                            </span>
                        </h3>
                        <span class="text-[10px] text-gray-400 uppercase tracking-wider">
                            tag_group = <code class="bg-white px-1 rounded"><?= e($group) ?></code>
                        </span>
                    </div>

                    <!-- Теги группы -->
                    <?php foreach ($tags as $tag): ?>
                    <div class="border-b border-gray-50 p-4" x-data="{ editing: false }">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-800 text-sm flex items-center gap-2 flex-wrap">
                                    <?php if (!empty($tag['icon_svg'])): ?>
                                        <?= icon($tag['icon_svg'], 'gc-icon-xs', 1) ?>
                                    <?php endif; ?>
                                    <span><?= e($tag['name']) ?></span>
                                    <?php if (!empty($tag['meta_title']) || !empty($tag['meta_description'])): ?>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 inline-flex" title="Мета заполнены"><?= icon('check', 'gc-icon-xs') ?></span>
                                    <?php else: ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500" title="Мета не заполнены">пусто</span>
                                    <?php endif; ?>
                                </h4>
                                <span class="text-xs text-gray-400">
                                    /tag/<?= e($tag['slug']) ?>
                                </span>
                            </div>
                            <button @click="editing = !editing" class="<?= admin_cls('ibtn ibtn-edit') ?> bg-amber-50 shrink-0" aria-label="Редактировать" title="Редактировать">
                                <span x-show="!editing" class="inline-flex"><?= icon('edit', 'gc-icon-sm') ?></span>
                                <span x-show="editing" style="display:none" class="inline-flex"><?= icon('close', 'gc-icon-sm') ?></span>
                            </button>
                        </div>

                        <form method="POST" x-show="editing" style="display:none" class="mt-3 space-y-3">
                            <input type="hidden" name="action" value="save_tag_meta">
                            <input type="hidden" name="tag_id" value="<?= (int)$tag['id'] ?>">

                            <div>
                                <label class="<?= admin_cls('label') ?>">Title</label>
                                <input type="text" name="meta_title"
                                       value="<?= e($tag['meta_title'] ?? '') ?>"
                                       placeholder="<?= e($tag['name']) ?> — рецепты | GigaChef"
                                       class="<?= admin_cls('input') ?>">
                            </div>
                            <div>
                                <label class="<?= admin_cls('label') ?>">H1</label>
                                <input type="text" name="h1"
                                       value="<?= e($tag['h1'] ?? '') ?>"
                                       placeholder="<?= e($tag['name']) ?>"
                                       class="<?= admin_cls('input') ?>">
                            </div>
                            <div>
                                <label class="<?= admin_cls('label') ?>">Description</label>
                                <textarea name="meta_description" rows="2"
                                          placeholder="Рецепты с тегом «<?= e($tag['name']) ?>»..."
                                          class="<?= admin_cls('input') ?>"><?= e($tag['meta_description'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="<?= admin_cls('label') ?>">Keywords</label>
                                <input type="text" name="meta_keywords"
                                       value="<?= e($tag['meta_keywords'] ?? '') ?>"
                                       placeholder="через, запятую"
                                       class="<?= admin_cls('input') ?>">
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" class="<?= admin_cls('btn btn-success') ?>">
                                    <?= icon('diskette', 'gc-icon-sm') ?> Сохранить
                                </button>
                                <button type="button" @click="editing = false" class="<?= admin_cls('btn btn-ghost') ?>">
                                    Отмена
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

    <!-- РЕЦЕПТЫ -->
    <div x-show="tab === 'recipes'" x-transition style="display:none">
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2">
                <?= icon('cookbook', 'gc-icon-sm') ?> Рецепты (последние 100)
            </div>

            <?php foreach ($recipesList as $recipe): ?>
            <div class="border-b border-gray-50 p-4" x-data="{ editing: false }">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h3 class="font-bold text-gray-800 text-sm flex-1 min-w-0 truncate">
                        #<?= (int)$recipe['id'] ?> — <?= e($recipe['title']) ?>
                    </h3>
                    <button @click="editing = !editing" class="<?= admin_cls('ibtn ibtn-edit') ?> bg-amber-50 shrink-0" aria-label="Редактировать" title="Редактировать">
                        <span x-show="!editing" class="inline-flex"><?= icon('edit', 'gc-icon-sm') ?></span>
                        <span x-show="editing" style="display:none" class="inline-flex"><?= icon('close', 'gc-icon-sm') ?></span>
                    </button>
                </div>

                <form method="POST" x-show="editing" style="display:none" class="mt-3 space-y-3">
                    <input type="hidden" name="action" value="save_recipe_meta">
                    <input type="hidden" name="recipe_id" value="<?= (int)$recipe['id'] ?>">

                    <div>
                        <label class="<?= admin_cls('label') ?>">Title</label>
                        <input type="text" name="meta_title" value="<?= e($recipe['meta_title'] ?? '') ?>"
                               class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">H1</label>
                        <input type="text" name="h1" value="<?= e($recipe['h1'] ?? '') ?>"
                               class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">Description</label>
                        <textarea name="meta_description" rows="2"
                                  class="<?= admin_cls('input') ?>"><?= e($recipe['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">Keywords</label>
                        <input type="text" name="meta_keywords" value="<?= e($recipe['meta_keywords'] ?? '') ?>"
                               class="<?= admin_cls('input') ?>">
                    </div>
                    <button type="submit" class="<?= admin_cls('btn btn-success') ?>"><?= icon('diskette', 'gc-icon-sm') ?> Сохранить</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>