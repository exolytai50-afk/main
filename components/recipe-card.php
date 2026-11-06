<?php
/**
 * components/recipe-card.php
 *
 * Универсальная карточка рецепта.
 *
 * Поддерживает 3 типа:
 *  - regular  — обычный рецепт (из таблицы recipes)
 *  - ai       — AI-сгенерированный рецепт (is_ai_generated = 1)
 *  - fusion   — фьюжн-рецепт (есть cuisine_a / cuisine_b)
 *
 * ─── Ожидаемые поля в $recipe ────────────────────────────────────────
 *
 * Обязательные:
 *   id, title
 *
 * Обычные (regular / ai):
 *   slug, description, image_url
 *   prep_time, cook_time, servings, calories
 *   difficulty, cuisine
 *   likes_count, views_count, is_ai_generated
 *   author_email, author_name, author_avatar_url, author_gender
 *
 * Новые (после миграции категорий и тегов):
 *   category_id, category_slug, category_name, category_icon
 *   ai_classified
 *   tags_list  — массив:
 *                [ ['slug'=>'russkaya','name'=>'Русская','icon_svg'=>'russian','tag_group'=>'cuisine'], ... ]
 *
 * Фьюжн:
 *   cuisine_a, cuisine_a_name, cuisine_b, cuisine_b_name, rating
 *
 * Если tags_list отсутствует — используется старая логика с JSON-полем tags.
 */

// ============================================================
//  ЗАЩИТА: если $recipe не передан — ничего не рендерим
// ============================================================
if (!isset($recipe) || !is_array($recipe) || empty($recipe['id'])) {
    return;
}

// ============================================================
//  ОПРЕДЕЛЯЕМ ТИП РЕЦЕПТА
// ============================================================
$recipeType = $recipe['recipe_type']
    ?? $recipe['type']
    ?? (!empty($recipe['cuisine_a']) ? 'fusion' : 'regular');

// ============================================================
//  ФЬЮЖН-КАРТОЧКА
// ============================================================
if ($recipeType === 'fusion'):
    $imageUrl = !empty($recipe['image_url'])
        ? recipe_image_url($recipe['image_url'])
        : null;

    $hasImage = !empty($imageUrl) && $imageUrl !== '/uploads/static/static.jpg';
?>
<article data-aos="fade-up"
         class="fusion-card group bg-white rounded-2xl shadow-md hover:shadow-xl overflow-hidden flex flex-col border border-purple-100 transition-all"
         data-recipe-id="<?= (int)$recipe['id'] ?>"
         data-recipe-type="fusion">

    <!-- Обложка -->
    <div class="relative aspect-[4/3] overflow-hidden">
        <?php if ($hasImage): ?>
            <img src="<?= e($imageUrl) ?>"
                 alt="<?= e($recipe['title']) ?>"
                 loading="lazy"
                 onerror="this.style.display='none'; this.parentElement.classList.add('fusion-gradient');"
        <?php else: ?>
    <img src="/uploads/static/static.jpg"
         alt="<?= e($recipe['title']) ?>"
         loading="lazy"
         class="w-full h-full object-cover">
<?php endif; ?>

        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-white/90 backdrop-blur-sm text-purple-700 shadow-sm flex items-center gap-1.5">
            <?= icon('fusion', 'gc-icon-xs', 1) ?>
            <span>Фьюжн</span>
        </span>

        <?php if (!empty($recipe['rating'])): ?>
            <span class="absolute bottom-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-yellow-400 text-yellow-900 shadow-sm flex items-center gap-1">
                <?= icon('star', 'gc-icon-xs', 0.9) ?>
                <span><?= number_format((float)$recipe['rating'], 1) ?></span>
            </span>
        <?php endif; ?>

        <?php if (!empty($recipe['difficulty'])): ?>
            <span class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold <?= e(difficulty_color($recipe['difficulty'])) ?> shadow-sm">
                <?= e(difficulty_label($recipe['difficulty'])) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="p-4 flex flex-col flex-1">
        <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-2">
            <span><?= e($recipe['cuisine_a_name'] ?? '') ?></span>
            <span class="text-purple-500 font-bold">+</span>
            <span><?= e($recipe['cuisine_b_name'] ?? '') ?></span>
        </div>

        <h2 class="font-bold text-gray-800 leading-snug clamp-2 mb-2 text-base sm:text-lg">
            <a href="<?= url('fusion?id=' . (int)$recipe['id']) ?>"
               class="hover:text-purple-600 transition">
                <?= e($recipe['title']) ?>
            </a>
        </h2>

        <p class="text-sm text-gray-500 clamp-2 mb-3 flex-1">
            <?= e($recipe['description'] ?? '') ?>
        </p>

        <div class="flex items-center gap-3 text-xs text-gray-400 mb-3">
            <?php if (!empty($recipe['cook_time'])): ?>
                <span class="flex items-center gap-1">
                    <?= icon('time', 'gc-icon-xs', 0.9) ?>
                    <span><?= (int)$recipe['cook_time'] ?> мин</span>
                </span>
            <?php endif; ?>
            <?php if (!empty($recipe['servings'])): ?>
                <span class="flex items-center gap-1">
                    <?= icon('check', 'gc-icon-xs', 0.9) ?>
                    <span><?= (int)$recipe['servings'] ?> порц.</span>
                </span>
            <?php endif; ?>
            <?php if (!empty($recipe['calories'])): ?>
                <span class="flex items-center gap-1">
                    <?= icon('lightning', 'gc-icon-xs', 0.9) ?>
                    <span><?= (int)$recipe['calories'] ?> ккал</span>
                </span>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
            <a href="<?= url('fusion?id=' . (int)$recipe['id']) ?>"
               class="text-sm font-semibold text-purple-600 hover:text-purple-800 transition">
                Открыть →
            </a>

            <div class="flex items-center gap-1">
                <?php if (!empty($recipe['is_saved'])): ?>
                    <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">
                        ✓ Сохранено
                    </span>
                <?php else: ?>
                    <button onclick="saveFusionRecipe(<?= (int)$recipe['id'] ?>, this)"
                            class="p-2 rounded-lg hover:bg-purple-50 text-gray-400 transition"
                            title="Сохранить в книгу">
                        <?= icon('bookmark', 'gc-icon-sm', 1.2) ?>
                    </button>
                <?php endif; ?>

                <button onclick="shareFusionRecipe(<?= (int)$recipe['id'] ?>)"
                        class="p-2 rounded-lg hover:bg-purple-50 text-gray-400 transition"
                        title="Поделиться">
                    <?= icon('share', 'gc-icon-sm', 1.2) ?>
                </button>
            </div>
        </div>
    </div>
</article>

<?php
// ============================================================
//  СТАНДАРТНАЯ КАРТОЧКА (обычные и AI-рецепты)
// ============================================================
else:

    // ---- Изображение ----
    $defaultImage = '/uploads/static/static.jpg';
    $rawImageUrl  = $recipe['image_url'] ?? '';

    if (empty($rawImageUrl) || $rawImageUrl === 'null') {
        $imageUrl = $defaultImage;
        $hasImage = false;
    } elseif (str_starts_with($rawImageUrl, '/uploads/avatar/')) {
        $imageUrl = $rawImageUrl;
        $hasImage = true;
    } elseif (str_starts_with($rawImageUrl, '/uploads/static/') && $rawImageUrl !== $defaultImage) {
        $imageUrl = $defaultImage;
        $hasImage = false;
    } elseif (str_starts_with($rawImageUrl, 'http')) {
        $imageUrl = $rawImageUrl;
        $hasImage = true;
    } else {
        $imageUrl = $defaultImage;
        $hasImage = false;
    }

    // ---- URL рецепта ----
    $recipeUrl = !empty($recipe['slug'])
        ? url('recipe/' . $recipe['slug'])
        : url('recipe?id=' . (int)$recipe['id']);

    // ---- Метаданные ----
    $totalTime  = (int)($recipe['prep_time'] ?? 0) + (int)($recipe['cook_time'] ?? 0);
    $servings   = (int)($recipe['servings'] ?? 0);
    $likes      = (int)($recipe['likes_count'] ?? 0);
    $views      = (int)($recipe['views_count'] ?? 0);
    $calories   = (int)($recipe['calories'] ?? 0);
    $difficulty = $recipe['difficulty'] ?? '';

    // ---- Теги ----
    // Приоритет 1: tags_list (новые теги из recipe_tag_links)
    // Приоритет 2: старая логика с JSON-полем tags
    $renderTags = [];

    if (!empty($recipe['tags_list']) && is_array($recipe['tags_list'])) {
        foreach (array_slice($recipe['tags_list'], 0, 4) as $t) {
            $renderTags[] = [
                'type'     => 'link',
                'slug'     => $t['slug'] ?? '',
                'name'     => $t['name'] ?? ($t['slug'] ?? ''),
                'icon_svg' => $t['icon_svg'] ?? null,
                'group'    => $t['tag_group'] ?? '',
            ];
        }
    } else {
        $legacyTags = json_field($recipe['tags'] ?? null);
        foreach (array_slice($legacyTags, 0, 2) as $tag) {
            $renderTags[] = [
                'type' => 'plain',
                'name' => (string)$tag,
            ];
        }
    }

    // ---- Категория ----
    $hasCategory = !empty($recipe['category_slug']) && !empty($recipe['category_name']);

?>
<article data-aos="fade-up"
         class="recipe-card group bg-white rounded-2xl shadow-md hover:shadow-lg overflow-hidden flex flex-col"
         data-recipe-id="<?= (int)$recipe['id'] ?>"
         data-recipe-type="<?= !empty($recipe['is_ai_generated']) ? 'ai' : 'regular' ?>">

    <!-- Обложка -->
    <a href="<?= $recipeUrl ?>"
       class="block relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-amber-100 to-orange-100">

        <?php if ($hasImage): ?>
            <img src="<?= e($imageUrl) ?>"
                 alt="<?= e($recipe['title']) ?>"
                 loading="lazy"
                 onerror="this.style.display='none'; this.parentElement.classList.add('with-fallback');"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
        <?php endif; ?>

        <?php if (!$hasImage): ?>
    <img src="/uploads/static/static.jpg"
         alt="<?= e($recipe['title']) ?>"
         loading="lazy"
         class="w-full h-full object-cover">
<?php endif; ?>

        <?php if (!empty($recipe['is_ai_generated'])): ?>
            <span class="absolute top-3 left-3 px-2 py-1 rounded-lg text-xs font-bold bg-violet-100/90 text-violet-700 ai-pulse flex items-center gap-1">
                <?= icon('lightning', 'gc-icon-xs', 0.9) ?>
                <span>AI</span>
            </span>
        <?php endif; ?>

        <?php if ($difficulty !== ''): ?>
            <span class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-bold <?= e(difficulty_color($difficulty)) ?>">
                <?= e(difficulty_label($difficulty)) ?>
            </span>
        <?php endif; ?>
    </a>

    <!-- Контент -->
    <div class="p-4 flex flex-col flex-1">

        <!-- Категория -->
        <?php if ($hasCategory): ?>
            <a href="<?= url('category/' . e($recipe['category_slug'])) ?>"
               class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-600 transition mb-2 self-start"
               onclick="event.stopPropagation()">
                <?php if (!empty($recipe['category_icon'])): ?>
                    <?= icon($recipe['category_icon'], 'gc-icon-xs', 1) ?>
                <?php endif; ?>
                <span><?= e($recipe['category_name']) ?></span>
            </a>
        <?php else: ?>
            <!-- Fallback: старая логика с текстовой кухней -->
            <div class="text-xs text-gray-400 mb-2">
                <?= e($recipe['cuisine'] ?? 'Другая') ?>
            </div>
        <?php endif; ?>

        <!-- Верхняя строка: лайки -->
        <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
            <span></span>
            <span class="flex items-center gap-1" title="Лайки">
                <?= icon('star', 'gc-icon-xs', 0.85) ?>
                <span><?= $likes ?></span>
            </span>
        </div>

        <!-- Автор -->
        <?php if (!empty($recipe['author_name']) || !empty($recipe['author_avatar_url'])): ?>
            <a href="<?= url('user/' . urlencode($recipe['author_email'] ?? '')) ?>"
               class="flex items-center gap-2 mb-2 group/author"
               onclick="event.stopPropagation()">
                <?= render_avatar(
                    [
                        'avatar_url' => $recipe['author_avatar_url'] ?? null,
                        'gender'     => $recipe['author_gender']     ?? null,
                    ],
                    'w-6 h-6 rounded-full object-cover shrink-0',
                    'w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shrink-0',
                    'text-xs'
                ) ?>
                <span class="text-xs text-gray-500 truncate group-hover/author:text-amber-600 transition">
                    <?= e($recipe['author_name'] ?? 'Пользователь') ?>
                </span>
            </a>
        <?php endif; ?>

        <!-- Название -->
        <h2 class="font-bold text-gray-800 leading-snug clamp-2 mb-1 text-base sm:text-lg">
            <a href="<?= $recipeUrl ?>" class="hover:text-amber-600 transition">
                <?= e($recipe['title']) ?>
            </a>
        </h2>

        <!-- Описание -->
        <p class="text-sm text-gray-500 clamp-2 mb-3 flex-1">
            <?= e($recipe['description'] ?? '') ?>
        </p>

        <!-- Метаданные: время, порции, калории -->
        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
            <?php if ($totalTime > 0): ?>
                <span class="flex items-center gap-1">
                    <?= icon('time', 'gc-icon-xs', 0.9) ?>
                    <span><?= format_time($totalTime) ?></span>
                </span>
            <?php endif; ?>

            <?php if ($servings > 0): ?>
                <span class="flex items-center gap-1">
                    <?= icon('check', 'gc-icon-xs', 0.9) ?>
                    <span><?= $servings ?> порц.</span>
                </span>
            <?php endif; ?>

            <?php if ($calories > 0): ?>
                <span class="flex items-center gap-1">
                    <?= icon('lightning', 'gc-icon-xs', 0.9) ?>
                    <span><?= $calories ?> ккал</span>
                </span>
            <?php endif; ?>
        </div>

        <!-- Нижняя панель: теги + кнопка сохранения -->
        <div class="flex items-center justify-between gap-2 mt-auto pt-2 border-t border-gray-100">

            <div class="flex flex-wrap gap-1 min-w-0 overflow-hidden">
                <?php foreach ($renderTags as $tag): ?>
                    <?php if ($tag['type'] === 'link'): ?>
                        <a href="<?= url('tag/' . e($tag['slug'])) ?>"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 hover:bg-amber-100 transition shrink-0"
                           onclick="event.stopPropagation()"
                           title="<?= e($tag['group']) ?>">
                            <?php if (!empty($tag['icon_svg'])): ?>
                                <?= icon($tag['icon_svg'], 'gc-icon-xs', 0.85) ?>
                            <?php endif; ?>
                            <span><?= e($tag['name']) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 shrink-0">
                            #<?= e($tag['name']) ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <button onclick="saveRecipe(<?= (int)$recipe['id'] ?>, this)"
                    class="p-2 rounded-lg hover:bg-rose-50 text-gray-400 transition save-btn shrink-0"
                    title="Сохранить"
                    data-check-saved="<?= (int)$recipe['id'] ?>">
                <?= icon('bookmark', 'gc-icon-sm', 1.2) ?>
            </button>
        </div>
    </div>
</article>

<?php endif; ?>