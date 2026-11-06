<?php
// components/activity-card.php

/**
 * Карточка активности. Ожидает:
 *   $item — строка из activity_feed с полями recipe_* (после JOIN).
 */
if (empty($item)) return;
require_once __DIR__ . '/../includes/activity.php';

$icon          = activity_icon($item['type'] ?? '');
$itemUserEmail = $item['user_email'] ?? '';
$itemUserUrl   = $itemUserEmail ? url('user/' . urlencode($itemUserEmail)) : '#';
$createdAt     = !empty($item['created_at']) ? date('d.m.Y H:i', strtotime($item['created_at'])) : '';

$hasRecipe = !empty($item['recipe_title']);
$recipeUrl = $hasRecipe
    ? url('recipe/' . ($item['recipe_slug'] ?? $item['reference_id']))
    : null;

$defaultImg = '/uploads/static/static.jpg';
$recipeImg  = !empty($item['recipe_image']) ? $item['recipe_image'] : $defaultImg;

$tagsList = (!empty($item['tags_list']) && is_array($item['tags_list']))
    ? array_slice($item['tags_list'], 0, 3)
    : [];
?>

<div class="activity-card-item bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition"
     data-activity-id="<?= (int)($item['id'] ?? 0) ?>"
     data-created-at="<?= e($item['created_at'] ?? '') ?>">

    <!-- Шапка: автор -->
    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-50">
        <?= render_avatar(
            $item,
            'w-8 h-8 rounded-full object-cover ring-1 ring-amber-200',
            'w-8 h-8 rounded-full bg-amber-100 ring-1 ring-amber-200 flex items-center justify-center text-amber-600',
            'text-sm'
        ) ?>

        <div class="flex-1 min-w-0">
            <a href="<?= $itemUserUrl ?>" class="font-bold text-gray-800 hover:text-amber-600 transition text-sm truncate block">
                <?= e($item['display_name'] ?? 'Пользователь') ?>
            </a>
            <div class="text-[11px] text-gray-400"><?= e($createdAt) ?></div>
        </div>

        <span class="text-gray-400 shrink-0 inline-flex items-center" aria-hidden="true"><?= $icon ?></span>
    </div>

    <!-- Тело -->
    <?php if ($hasRecipe): ?>

        <!-- ОДНА <a> вокруг превью + текста. БЕЗ ВЛОЖЕННЫХ <a>! -->
        <a href="<?= $recipeUrl ?>" class="flex items-start gap-4 p-4 hover:bg-gray-50/50 transition">
            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden shrink-0 bg-gray-100">
                <img src="<?= e($recipeImg) ?>"
                     onerror="this.src='<?= $defaultImg ?>'"
                     class="w-full h-full object-cover"
                     alt="<?= e($item['recipe_title']) ?>"
                     loading="lazy">
            </div>

            <div class="flex-1 min-w-0">
                <?php if (!empty($item['category_name'])): ?>
                    <div class="text-[11px] text-gray-400 mb-1">
                        <?= e($item['category_name']) ?>
                    </div>
                <?php endif; ?>

                <h2 class="font-bold text-gray-800 text-sm sm:text-base leading-snug clamp-2 hover:text-amber-600 transition">
                    <?= e($item['recipe_title']) ?>
                </h2>

                <?php if (!empty($item['recipe_description'])): ?>
                    <p class="text-gray-500 text-xs sm:text-sm mt-1 line-clamp-2 leading-relaxed">
                        <?= e($item['recipe_description']) ?>
                    </p>
                <?php endif; ?>

                <div class="flex flex-wrap gap-2 sm:gap-3 mt-2 text-[11px] sm:text-xs text-gray-400">
                    <span class="inline-flex items-center gap-1"><?= icon('clock', '', 1.0) ?> <?= (int)($item['prep_time'] ?? 0) + (int)($item['cook_time'] ?? 0) ?> мин</span>
                    <span class="inline-flex items-center gap-1"><?= icon('flame', '', 1.0) ?> <?= (int)($item['calories'] ?? 0) ?> ккал</span>
                    <span class="inline-flex items-center gap-1"><?= icon('rest', '', 1.0) ?> <?= (int)($item['servings'] ?? 1) ?> порц.</span>
                    <span class="inline-flex items-center gap-1"><?= icon('heart', '', 1.0) ?> <?= (int)($item['likes_count'] ?? 0) ?></span>
                </div>
            </div>
        </a>

        <!-- Теги — ВНЕ <a> рецепта. Лежат отдельным блоком. -->
        <?php if (!empty($tagsList)): ?>
            <div class="px-4 pb-4 flex flex-wrap gap-1">
                <?php foreach ($tagsList as $tag): ?>
                    <a href="<?= url('tag/' . e($tag['slug'])) ?>"
                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 hover:bg-amber-100 transition">
                        <span><?= e($tag['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="px-4 py-4">
            <p class="text-gray-700 text-sm leading-relaxed">
                <?= e($item['message'] ?? 'Новая активность') ?>
            </p>
        </div>
    <?php endif; ?>
</div>