<?php
/**
 * GigaChef — Детальный просмотр кулинарной книги
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/cookbook_helpers.php';
require_once __DIR__ . '/../includes/recipe_guard.php';
require_once __DIR__ . '/../includes/ads.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$aiAdBundle = ad_bundle_json('ai_action');

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$userEmail = $user['email'];

$bookId = (int)($_GET['id'] ?? 0);
$slug   = trim((string)($_GET['slug'] ?? ''));

if ($slug) {
    $book = db_one('SELECT * FROM cookbooks WHERE slug = ?', [$slug]);
} elseif ($bookId) {
    $book = db_one('SELECT * FROM cookbooks WHERE id = ?', [$bookId]);
} else {
    http_response_code(404);
    die('Книга не найдена');
}

if (!$book) {
    http_response_code(404);
    die('Книга не найдена');
}

$scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/cookbook/' . $book['slug'];

$shareData = [
    'title'       => $book['title'] ?? 'Кулинарная книга',
    'url'         => $pageUrl,
    'description' => mb_substr(strip_tags($book['description'] ?? ''), 0, 200),
    'image'       => !empty($book['cover_url']) ? $book['cover_url'] : url('assets/icon/chef_256.png'),
];

$isOwner = ((int)($book['author_id'] ?? 0) === $userId) 
        || ($book['author_email'] === $userEmail);

/* ─── Автор книги: подгружаем из users ─── */
$bookAuthor = null;
if (!empty($book['author_id'])) {
    $bookAuthor = db_one('SELECT id, display_name, email, avatar_url, gender FROM users WHERE id = ? LIMIT 1', [(int)$book['author_id']]);
} elseif (!empty($book['author_email'])) {
    $bookAuthor = db_one('SELECT id, display_name, email, avatar_url, gender FROM users WHERE email = ? LIMIT 1', [$book['author_email']]);
}

if (!$bookAuthor) {
    $bookAuthor = [
        'id'           => (int)($book['author_id'] ?? 0),
        'display_name' => $book['author_email'] ?? 'Автор',
        'email'        => $book['author_email'] ?? '',
        'avatar_url'   => null,
        'gender'       => null,
    ];
}

$recipes = json_field($book['recipes_json']);

// Статусы и обогащение (как было)
foreach ($recipes as $idx => &$recipe) {
    if (!empty($recipe['created_recipe_id'])) {
        $recipe['_status'] = [
            'exists' => true,
            'recipe' => ['id' => $recipe['created_recipe_id'], 'slug' => $recipe['created_slug'], 'title' => $recipe['title']]
        ];
        continue;
    }
    $recipe['_status'] = check_cookbook_recipe_status($recipe, $userId);
}
unset($recipe);

$createdRecipeIds = [];
foreach ($recipes as $r) {
    if (!empty($r['_status']['exists']) && !empty($r['_status']['recipe']['id'])) {
        $createdRecipeIds[] = (int)$r['_status']['recipe']['id'];
    }
}

$categoriesByRecipe = [];
$tagsByRecipe = [];

if (!empty($createdRecipeIds)) {
    $ph = implode(',', array_fill(0, count($createdRecipeIds), '?'));
    $catRows = db_all("SELECT r.id AS recipe_id, c.id AS category_id, c.slug AS category_slug, c.name AS category_name, c.icon_svg AS category_icon FROM recipes r LEFT JOIN recipe_categories c ON c.id = r.category_id AND c.is_active = 1 WHERE r.id IN ({$ph})", $createdRecipeIds);
    foreach ($catRows as $row) {
        if (empty($row['category_id'])) continue;
        $catIconHtml = !empty($row['category_icon']) ? icon($row['category_icon'], 'gc-icon-xs', 1) : '';
        $categoriesByRecipe[(int)$row['recipe_id']] = ['id' => (int)$row['category_id'], 'slug' => $row['category_slug'], 'name' => $row['category_name'], 'icon_svg_html' => $catIconHtml];
    }

    $tagRows = db_all("SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group FROM recipe_tag_links l JOIN recipe_tags t ON t.id = l.tag_id WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1 ORDER BY t.tag_group, t.sort_order, t.name", $createdRecipeIds);
    foreach ($tagRows as $row) {
        $tagIconHtml = !empty($row['icon_svg']) ? icon($row['icon_svg'], 'gc-icon-xs', 1) : '';
        $tagsByRecipe[(int)$row['recipe_id']][] = ['id' => (int)$row['id'], 'slug' => $row['slug'], 'name' => $row['name'], 'icon_svg_html' => $tagIconHtml, 'tag_group' => $row['tag_group']];
    }
}

$allCuisineTags = db_all("SELECT id, slug, name, icon_svg FROM recipe_tags WHERE tag_group = 'cuisine' AND is_active = 1") ?: [];
$cuisineTagByName = [];
foreach ($allCuisineTags as $t) $cuisineTagByName[mb_strtolower($t['name'])] = $t;

foreach ($recipes as &$r) {
    $recipeId = (int)($r['_status']['recipe']['id'] ?? 0);
    $r['_category'] = $recipeId && isset($categoriesByRecipe[$recipeId]) ? $categoriesByRecipe[$recipeId] : null;
    if ($recipeId && !empty($tagsByRecipe[$recipeId])) {
        $r['_tags'] = $tagsByRecipe[$recipeId];
    } else {
        $r['_tags'] = [];
        $cuisineName = trim((string)($r['cuisine'] ?? ''));
        if ($cuisineName !== '' && isset($cuisineTagByName[mb_strtolower($cuisineName)])) {
            $t = $cuisineTagByName[mb_strtolower($cuisineName)];
            $r['_tags'][] = ['id' => (int)$t['id'], 'slug' => $t['slug'], 'name' => $t['name'], 'icon_svg_html' => icon($t['icon_svg'], 'gc-icon-xs', 1), 'tag_group' => 'cuisine'];
        }
    }
}
unset($r);

$detailCategories = array_map(fn($c) => ['slug' => $c['slug'], 'name' => $c['name']], get_all_categories());

$pageTitle = $book['title'] . ' — GigaChef';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl { display: inline-block; vertical-align: -0.125em; flex-shrink: 0; }
</style>

<section class="max-w-5xl mx-auto px-4 sm:px-6 py-8" x-data="cookbookDetail()" x-init="bookId = <?= (int)$book['id'] ?>">
    
    <!-- Заголовок -->
    <div class="bg-gradient-to-br from-violet-200 to-purple-200 rounded-3xl p-6 sm:p-8 mb-8 relative">
        <div class="flex flex-col sm:flex-row items-start justify-between gap-4 w-full">
            <div class="flex-1 w-full min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-800 break-words leading-tight" x-show="!editMode" x-text="title"></h1>
                <div x-show="editMode" style="display:none" class="space-y-2 w-full mt-2">
                    <input type="text" x-model="editTitle" class="w-full px-4 py-2 rounded-xl border-2 border-white/50 bg-white/80 text-xl font-bold outline-none focus:border-violet-400">
                    <textarea x-model="editDescription" rows="2" class="w-full px-4 py-2 rounded-xl border-2 border-white/50 bg-white/80 text-sm resize-none outline-none focus:border-violet-400"></textarea>
                    <div class="flex gap-2">
                        <button @click="saveBookInfo()" class="inline-flex items-center gap-1 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm font-bold hover:bg-emerald-600 transition"><?= icon('check', 'gc-icon-sm') ?> Сохранить</button>
                        <button @click="editMode=false" class="px-4 py-2 rounded-lg bg-white/70 text-gray-600 text-sm font-bold hover:bg-white transition">Отмена</button>
                    </div>
                </div>
                <p class="text-gray-600 mt-3 text-sm sm:text-base break-words" x-show="!editMode" x-text="description"></p>
                <div class="flex flex-wrap gap-x-4 gap-y-2 mt-4 text-xs sm:text-sm text-gray-600 items-center">
                    <span class="inline-flex items-center gap-1.5"><?= icon('openbook', 'gc-icon-xs') ?> <span x-text="recipes.length"></span> <span>рецептов</span></span>
                    <span class="inline-flex items-center gap-2"><?= render_avatar(['avatar_url' => $bookAuthor['avatar_url'] ?? null, 'gender' => $bookAuthor['gender'] ?? null], 'w-6 h-6 rounded-full object-cover shrink-0', 'w-6 h-6 rounded-full bg-white/70 flex items-center justify-center text-violet-600 shrink-0', 'text-xs') ?> <span class="truncate max-w-[150px]"><?= e($bookAuthor['display_name'] ?: $bookAuthor['email']) ?></span></span>
                    <span class="inline-flex items-center gap-1"><?= icon('time', 'gc-icon-xs') ?> <span><?= date('d.m.Y', strtotime($book['created_at'])) ?></span></span>
                    <?php if ($book['is_public']): ?><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/70 text-xs font-bold text-emerald-700"><?= icon('check', 'gc-icon-xs') ?> Публичная</span><?php endif; ?>
                </div>
            </div>
            <?php if ($isOwner): ?>
            <div class="flex flex-row sm:flex-col gap-2 w-full sm:w-auto shrink-0 mt-2 sm:mt-0">
                <button @click="editMode=!editMode; editTitle=title; editDescription=description" x-show="!editMode" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-4 py-2 rounded-xl bg-white/70 text-gray-700 text-sm font-bold hover:bg-white transition shadow-sm"><?= icon('pen', 'gc-icon-sm') ?> <span>Редактировать</span></button>
                <button @click="deleteBook()" x-show="!editMode" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-4 py-2 rounded-xl bg-rose-100 text-rose-700 text-sm font-bold hover:bg-rose-200 transition shadow-sm"><?= icon('trash', 'gc-icon-sm') ?> <span>Удалить</span></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Панель управления -->
    <?php if ($isOwner): ?>
    <div class="bg-white rounded-2xl shadow-md p-5 mb-6">
        
        <!-- Инфо-блок -->
        <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-xl mb-4 border border-gray-100 leading-relaxed">
            <b>Как работает:</b> сначала ищем готовые рецепты в базе по <b>ключевым словам</b> (обязательно). Если их не хватает — AI доберёт недостающие по названию книги, категории, времени и пожеланиям.
        </div>

        <!-- Фильтры -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Категория</label>
                <select x-model="filterTags.category" class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300 bg-white">
                    <option value="">Любая</option>
                    <template x-for="c in categories" :key="c.slug"><option :value="c.slug" x-text="c.name"></option></template>
                </select>
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Кухня</label>
                <select x-model="filterTags.cuisine" class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300 bg-white">
                    <option value="">Любая</option>
                    <template x-for="t in allTags.filter(t => t.tag_group === 'cuisine')" :key="t.slug"><option :value="t.slug" x-text="t.name"></option></template>
                </select>
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Диета</label>
                <select x-model="filterTags.diet" class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300 bg-white">
                    <option value="">Любая</option>
                    <template x-for="t in allTags.filter(t => t.tag_group === 'diet')" :key="t.slug"><option :value="t.slug" x-text="t.name"></option></template>
                </select>
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Общее время <span class="font-normal text-gray-400">(не больше)</span></label>
                <select x-model="filterTags.time" class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300 bg-white">
                    <option value="">Любое</option>
                    <template x-for="t in allTags.filter(t => t.tag_group === 'time')" :key="t.slug"><option :value="t.slug" x-text="t.name"></option></template>
                </select>
            </div>
        </div>

        <!-- Ключевые слова, пожелания, количество -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="min-w-0 sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Ключевые слова <span class="text-rose-500">*</span></label>
                <input type="text" x-model="keywords" maxlength="200" placeholder="Например: суп, курица, борщ"
                       class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300">
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Пожелания</label>
                <input type="text" x-model="notes" maxlength="300" placeholder="Например: без глютена"
                       class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300">
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Сколько добавить</label>
                <select x-model="count" class="w-full h-10 px-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-violet-300 bg-white">
                    <option value="3">+3 рецепта</option>
                    <option value="5">+5 рецептов</option>
                </select>
            </div>
        </div>

        <!-- Кнопки -->
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-gray-100">
            <button type="button" @click="addRecipes()" :disabled="loading || !keywords.trim()" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-violet-100 text-violet-700 font-bold text-sm hover:bg-violet-200 transition disabled:opacity-50 h-10">
                <?= icon('aigenerator', 'gc-icon-sm') ?> 
                <span x-text="loading ? (stepText || 'Добавляем…') : 'Добавить рецепты'"></span>
            </button>
            
            <button @click="createAllRecipes()" :disabled="loading || !pendingIndexes.length" x-show="pendingIndexes.length > 0" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-blue-100 text-blue-700 font-bold text-sm hover:bg-blue-200 transition disabled:opacity-50 h-10">
                <?= icon('plus', 'gc-icon-sm') ?> 
                <span x-text="'Создать (' + pendingIndexes.length + ')'"></span>
            </button>

            <a href="<?= url('create-recipe?book_id=' . (int)$book['id']) ?>" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-blue-100 text-blue-700 font-bold text-sm hover:bg-blue-200 transition h-10">
                <?= icon('plus', 'gc-icon-sm') ?> 
                <span>Вручную</span>
            </a>
            
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-share-modal'))" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200 transition h-10">
                <?= icon('share', 'gc-icon-sm') ?> 
                <span>Поделиться</span>
            </button>
            
            <span class="text-sm text-gray-500 sm:ml-auto w-full sm:w-auto text-center sm:text-right" x-text="'Всего: ' + recipes.length"></span>
        </div>
    </div>
    
    <!-- Рекламный блок -->
    <div x-show="adVisible" style="display:none" class="mt-6 ai-ad-pause-container">
        <div class="ai-ad-pause-header flex justify-between items-center">
            <span class="inline-flex items-center gap-1.5">
                <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                <span>Подождите, идёт обработка...</span>
            </span>
            <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md text-xs">
                <span x-text="adTimer"></span> сек
            </span>
        </div>
        <div x-html="adHtml" class="p-4"></div>
        <div x-show="!adHtml" class="p-4 text-sm text-gray-400 text-center">Реклама загружается…</div>
    </div>

    <p x-show="error" style="display:none" class="text-rose-500 text-sm mb-3" x-text="error"></p>
    <?php endif; ?>

    <!-- Список рецептов -->
    <div class="space-y-4">
        <template x-for="(recipe, index) in recipes" :key="index">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-3 mb-3">
                        <div class="flex-1 w-full min-w-0">
                            <h2 class="text-lg sm:text-xl font-bold text-gray-800 break-words leading-tight mb-2"><span class="text-violet-400 mr-1" x-text="(index + 1) + '.'"></span> <span x-text="recipe.title || 'Без названия'"></span></h2>
                            
                            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                <template x-if="recipe._category"><a :href="'/category/' + recipe._category.slug" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"><template x-if="recipe._category.icon_svg_html"><span x-html="recipe._category.icon_svg_html"></span></template><span x-text="recipe._category.name"></span></a></template>
                                <template x-if="recipe.cuisine && !(recipe._tags && recipe._tags.some(t => t.tag_group === 'cuisine'))"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] bg-gray-100 text-gray-600"><span x-text="recipe.cuisine"></span></span></template>
                                <template x-for="tag in (recipe._tags || [])" :key="tag.id"><a :href="'/tag/' + tag.slug" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-700 hover:bg-amber-100 transition" :title="tag.tag_group"><template x-if="tag.icon_svg_html"><span x-html="tag.icon_svg_html"></span></template><span x-text="tag.name"></span></a></template>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold" :class="{'bg-emerald-100 text-emerald-700': recipe.difficulty === 'Легко','bg-amber-100 text-amber-700': recipe.difficulty === 'Средне','bg-rose-100 text-rose-700': recipe.difficulty === 'Сложно'}" x-text="recipe.difficulty || 'Средне'"></span>
                            </div>

                            <template x-if="recipe._status && recipe._status.exists"><div class="mt-1 p-2 bg-emerald-50 rounded-lg text-xs inline-flex items-center gap-2 border border-emerald-100"><span class="font-bold text-emerald-700"><?= icon('check', 'gc-icon-xs') ?> Создан:</span> <a :href="'/recipe/' + (recipe._status.recipe.slug || recipe._status.recipe.id)" class="text-emerald-800 underline font-semibold truncate max-w-[200px]" x-text="recipe._status.recipe.title"></a></div></template>
                            <template x-if="recipe._status && !recipe._status.exists && recipe._status.duplicate"><div class="mt-1 p-2 bg-amber-50 rounded-lg text-xs inline-flex items-center gap-2 border border-amber-100"><span class="font-bold text-amber-700"><?= icon('info', 'gc-icon-xs') ?> Похожий:</span> <a :href="'/recipe/' + (recipe._status.duplicate.slug || recipe._status.duplicate.id)" class="text-amber-800 underline font-semibold truncate max-w-[200px]" x-text="recipe._status.duplicate.title"></a> <span class="text-amber-600" x-text="'(' + recipe._status.duplicate.similarity + '%)'"></span></div></template>
                            <template x-if="recipe._status && !recipe._status.exists && !recipe._status.duplicate"><div class="mt-1 p-1.5 bg-blue-50 rounded-lg text-xs inline-flex items-center gap-1 text-blue-600 font-medium"><?= icon('info', 'gc-icon-xs') ?> Не найден в приложении</div></template>
                        </div>
                        
                        <?php if ($isOwner): ?>
                        <div class="flex items-center gap-1.5 shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
                            <template x-if="!recipe._status?.exists"><button @click="createRecipe(index)" :disabled="loading" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-3 py-2 sm:py-1.5 rounded-lg bg-blue-100 text-blue-700 text-xs font-bold hover:bg-blue-200 transition disabled:opacity-50"><?= icon('plus', 'gc-icon-xs') ?> <span>Создать</span></button></template>
                            <template x-if="recipe._status?.exists"><a :href="'/recipe/' + (recipe._status.recipe.slug || recipe._status.recipe.id)" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-3 py-2 sm:py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold hover:bg-emerald-200 transition"><?= icon('openbook', 'gc-icon-xs') ?> <span>Открыть</span></a></template>
                            <button @click="removeRecipe(index)" class="w-8 h-8 sm:w-auto sm:h-auto sm:px-2.5 sm:py-1.5 flex items-center justify-center rounded-lg bg-rose-100 text-rose-600 hover:bg-rose-200 transition shrink-0" title="Удалить"><?= icon('trash', 'gc-icon-xs') ?></button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <template x-if="recipe.description"><p class="text-gray-600 text-sm mb-4 break-words" x-text="recipe.description"></p></template>
                    
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500 mb-4">
                        <!-- ИСПРАВЛЕНО: общее время -->
                        <span class="inline-flex items-center gap-1"><?= icon('time', 'gc-icon-xs') ?> <span x-text="((recipe.prep_time||0) + (recipe.cook_time||0)) + ' мин'"></span></span>
                        <span class="inline-flex items-center gap-1"><?= icon('rest', 'gc-icon-xs') ?> <span x-text="(recipe.servings||2) + ' порц.'"></span></span>
                        <span class="inline-flex items-center gap-1"><?= icon('lightning', 'gc-icon-xs') ?> <span x-text="(recipe.calories||0) + ' ккал'"></span></span>
                    </div>
                    
                    <template x-if="recipe.ingredients && recipe.ingredients.length">
                        <div class="mb-4">
                            <h4 class="font-bold text-gray-700 text-sm mb-2 inline-flex items-center gap-1"><?= icon('openbook', 'gc-icon-xs') ?> Ингредиенты</h4>
                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(ing, i) in recipe.ingredients" :key="i"><li class="text-sm text-gray-600 flex justify-between border-b border-gray-50 pb-1"><span class="break-words pr-2" x-text="ing.name"></span><span class="text-gray-400 shrink-0" x-text="((ing.amount||'') + ' ' + (ing.unit||'')).trim()"></span></li></template>
                            </ul>
                        </div>
                    </template>
                    
                    <template x-if="recipe.steps && recipe.steps.length">
                        <div>
                            <h4 class="font-bold text-gray-700 text-sm mb-2 inline-flex items-center gap-1"><?= icon('whattocook', 'gc-icon-xs') ?> Приготовление</h4>
                            <ol class="space-y-2">
                                <template x-for="(step, i) in recipe.steps" :key="i"><li class="flex gap-2 text-sm text-gray-600"><span class="font-bold text-violet-400 shrink-0" x-text="((step.order || i + 1)) + '.'"></span><span class="break-words" x-text="step.instruction || step"></span></li></template>
                            </ol>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        
        <template x-if="recipes.length === 0">
            <div class="text-center py-16 text-gray-400">
                <div class="mb-4 flex justify-center"><span class="gc-icon-xl" style="display:inline-flex;width:4em;height:4em"><?= icon('cookbook', 'gc-icon-xl', 4) ?></span></div>
                <p class="text-lg">В книге пока нет рецептов</p>
                <?php if ($isOwner): ?><p class="text-sm mt-2">Добавьте рецепты через AI или вручную</p><?php endif; ?>
            </div>
        </template>
    </div>
    
    <div class="mt-8 text-center">
        <a href="<?= url('my-cookbooks') ?>" class="inline-flex items-center gap-1 text-violet-600 font-semibold hover:underline"><?= icon('back') ?> <span>Назад к моим книгам</span></a>
    </div>
</section>

<script src="<?= e(url('assets/js/ad-pause.js')) ?>?v=7"></script>
<script src="<?= e(url('assets/js/cookbook-fill.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/cookbook-fill.js') ?>"></script>
<script>
window.__AI_AD__ = <?= json_encode($aiAdBundle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function cookbookDetail() {
    return {
        bookId: <?= (int)$book['id'] ?>,
        title: <?= json_encode($book['title']) ?>,
        description: <?= json_encode($book['description'] ?? '') ?>,
        editMode: false,
        editTitle: '',
        editDescription: '',
        recipes: <?= json_encode(array_map(function($r) {
            if (!empty($r['difficulty'])) {
                $d = mb_strtolower(trim($r['difficulty']));
                if (in_array($d, ['easy', 'легко'])) $r['difficulty'] = 'Легко';
                elseif (in_array($d, ['medium', 'средне', 'средний'])) $r['difficulty'] = 'Средне';
                elseif (in_array($d, ['hard', 'сложно', 'сложный'])) $r['difficulty'] = 'Сложно';
                else $r['difficulty'] = 'Средне';
            } else { $r['difficulty'] = 'Средне'; }
            return $r;
        }, $recipes), JSON_UNESCAPED_UNICODE) ?>,
        
        loading: false,
        stepText: '',
        error: '',
        notes: '',
        keywords: '',
        count: '5',
        filterTags: { category: '', cuisine: '', diet: '', time: '' },
        categories: <?= json_encode($detailCategories, JSON_UNESCAPED_UNICODE) ?>,
        allTags: <?= json_encode(array_merge(
            array_map(fn($t) => ['slug' => $t['slug'], 'name' => $t['name'], 'tag_group' => 'cuisine'], get_all_tags_by_group()['cuisine'] ?? []),
            array_map(fn($t) => ['slug' => $t['slug'], 'name' => $t['name'], 'tag_group' => 'diet'], get_all_tags_by_group()['diet'] ?? []),
            array_map(fn($t) => ['slug' => $t['slug'], 'name' => $t['name'], 'tag_group' => 'time'], get_all_tags_by_group()['time'] ?? [])
        ), JSON_UNESCAPED_UNICODE) ?>,
        
        ...gcAdPause(10000),

        get pendingIndexes() {
            return this.recipes.map((r, i) => (r._status && r._status.exists) ? -1 : i).filter(i => i >= 0);
        },

        api(action, payload = {}) { return cbFill.api(action, payload); },

        async addRecipes() {
            if (this.loading) return;
            const kw = this.keywords.trim();
            if (!kw) { this.error = 'Укажите ключевые слова'; return; }
            this.error = ''; this.loading = true;
            try {
                const r = await this.adHold(cbFill.fillBook({
                    bookId: this.bookId, keywords: kw, tags: Object.assign({}, this.filterTags),
                    notes: this.notes, count: parseInt(this.count) || 3,
                    onStep: t => { this.stepText = t; }
                }));
                if (r.total > 0) { location.reload(); return; }
                this.error = r.errors.length ? ('❌ ' + r.errors[0]) : 'Новых рецептов не найдено: все подходящие уже есть в книге. Измените запрос.';
            } catch (e) { this.error = '❌ ' + e.message; }
            finally { this.loading = false; this.stepText = ''; }
        },

        async createRecipe(index) {
            if (this.loading) return; this.loading = true;
            try { await this.adHold((async () => { await this.api('create_recipe', { book_id: this.bookId, index, publish: true }); location.reload(); })()); }
            catch (e) { this.error = e.message; }
            finally { this.loading = false; }
        },

        async createAllRecipes() {
            if (this.loading) return; this.loading = true;
            try { await this.adHold((async () => { for (const idx of this.pendingIndexes.slice()) { try { await this.api('create_recipe', { book_id: this.bookId, index: idx, publish: true }); } catch(e){} } location.reload(); })()); }
            finally { this.loading = false; }
        },

        async saveBookInfo() {
            this.error = '';
            try {
                const r = await this.api('update_book', { book_id: this.bookId, title: this.editTitle, description: this.editDescription });
                this.title = r.title; this.description = r.description; this.editMode = false;
            } catch (e) { this.error = '❌ ' + e.message; }
        },
        async deleteBook() {
            if (!confirm('Удалить книгу «' + this.title + '»?')) return;
            try {
                const res = await fetch('/api/cookbook_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                    body: JSON.stringify({ action: 'delete', book_id: this.bookId })
                });
                const d = await res.json();
                if (!d.ok) throw new Error(d.error || 'Не удалось удалить');
                location.href = '/my-cookbooks?v=' + Date.now();
            } catch (e) { this.error = '❌ ' + e.message; }
        },
        async removeRecipe(index) {
            if (!confirm('Убрать рецепт из книги?')) return;
            try { await this.api('remove_recipe', { book_id: this.bookId, index }); this.recipes.splice(index, 1); }
            catch (e) { this.error = '❌ ' + e.message; }
        }
    }
}
</script>
<?php require __DIR__ . '/../components/share-block.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>