<?php
/**
 * GigaChef — единая публикация рецепта из AI-данных (кулинарные книги, праздники).
 *
 * Одна точка, которая:
 *   • проверяет дубли у пользователя;
 *   • классифицирует рецепт (recipe_classifier.php) или берёт готовую классификацию;
 *   • вставляет строку в recipes только в существующие колонки;
 *   • ПИШЕТ КАТЕГОРИЮ И ТЕГИ и проверяет, что категория действительно записалась;
 *   • начисляет баллы и пишет активность.
 *
 * Использование:
 *   $res = rp_create_recipe($recipeArray, $userId, $userEmail, ['force' => false]);
 *   if (!$res['ok']) { $res['duplicate'] ... }  else { $res['recipe_id'], $res['slug'] }
 */
declare(strict_types=1);

require_once __DIR__ . '/cookbook_helpers.php';
require_once __DIR__ . '/recipe_guard.php';
require_once __DIR__ . '/recipe_classifier.php';

/** Тип активности при создании рецепта (как в cookbook_recipe_create.php). */
const RP_ACTIVITY_TYPE = 'recipe_create';

function rp_columns(): array
{
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    foreach (db_all('SHOW COLUMNS FROM recipes') as $c) $cols[$c['Field']] = true;
    return $cols;
}

/** slug => id */
function rp_category_ids(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (get_all_categories() as $c) $m[(string)$c['slug']] = (int)$c['id'];
    return $m;
}

/** slug => id */
function rp_tag_ids(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (get_all_tags_by_group() as $tags) {
        foreach ($tags as $t) $m[(string)$t['slug']] = (int)$t['id'];
    }
    return $m;
}

/**
 * Записывает категорию и теги рецепта и проверяет результат.
 * @return array{category:bool,tags:int}
 */
function rp_apply_classification(int $recipeId, string $categorySlug, array $tagSlugs): array
{
    $cols   = rp_columns();
    $result = ['category' => false, 'tags' => 0];

    // ---------- категория ----------
    $catId = rp_category_ids()[$categorySlug] ?? 0;
    if ($catId > 0) {
        $old = (int)db_value('SELECT category_id FROM recipes WHERE id = ?', [$recipeId]);

        $set = ['category_id = ?'];
        if (isset($cols['ai_classified']))          $set[] = 'ai_classified = 1';
        if (isset($cols['ai_classified_at']))       $set[] = 'ai_classified_at = NOW()';
        if (isset($cols['ai_category_confidence'])) $set[] = 'ai_category_confidence = 1.0';

        db_exec('UPDATE recipes SET ' . implode(', ', $set) . ' WHERE id = ?', [$catId, $recipeId]);

        $result['category'] = ((int)db_value('SELECT category_id FROM recipes WHERE id = ?', [$recipeId]) === $catId);

        if ($result['category'] && $old !== $catId) {
            db_exec('UPDATE recipe_categories SET recipe_count = recipe_count + 1 WHERE id = ?', [$catId]);
            if ($old > 0) {
                db_exec('UPDATE recipe_categories SET recipe_count = GREATEST(recipe_count - 1, 0) WHERE id = ?', [$old]);
            }
        }
    }
    if (!$result['category']) {
        error_log("[recipe_publisher] категория не записана: recipe_id={$recipeId}, slug='{$categorySlug}'");
    }

    // ---------- теги ----------
    $ids = rp_tag_ids();
    foreach (array_unique(array_map('strval', $tagSlugs)) as $slug) {
        $tid = $ids[$slug] ?? 0;
        if ($tid <= 0) continue;

        $n = db_exec(
            "INSERT IGNORE INTO recipe_tag_links (recipe_id, tag_id, confidence, source) VALUES (?, ?, 1.00, 'ai')",
            [$recipeId, $tid]
        );
        if ($n > 0) {
            $result['tags']++;
            db_exec('UPDATE recipe_tags SET usage_count = usage_count + 1 WHERE id = ?', [$tid]);
        }
    }
    return $result;
}

/**
 * Создаёт рецепт в таблице recipes.
 *
 * @param array $opts publish   bool   опубликовать (по умолчанию true)
 *                    force     bool   создать, даже если найден похожий рецепт
 *                    requested array  [группа => slug] теги, которые нужно учесть (повод, диета)
 *                    points    int    баллы за создание (по умолчанию 20; 0 — не начислять)
 *                    label     string подпись в журнале баллов
 * @return array ['ok'=>true, 'recipe_id', 'slug', 'url', 'category_slug', 'tag_slugs', 'category_saved']
 *            или ['ok'=>false, 'duplicate'=>[id,title,slug,similarity,...]]
 */
function rp_create_recipe(array $recipe, int $userId, string $userEmail, array $opts = []): array
{
    $publish = (bool)($opts['publish'] ?? true);
    $rec     = normalize_recipe($recipe);
    $title   = (string)$rec['title'];

    if ($title === '' || $title === 'Без названия') {
        throw new RuntimeException('Не удалось определить название рецепта');
    }

    if (empty($opts['force'])) {
        $dup = check_recipe_duplicate($title, (array)$rec['ingredients'], $userId, 0.75);
        if ($dup) return ['ok' => false, 'duplicate' => $dup];
    }

    // ---------- классификация ----------
    if (($recipe['classified_by'] ?? '') === 'engine' && !empty($recipe['category_slug'])) {
        $category = (string)$recipe['category_slug'];
        $tags     = array_values(array_filter(array_map('strval', (array)($recipe['tag_slugs'] ?? []))));
    } else {
        $cls = classify_recipe($rec, [
            'requested'     => (array)($opts['requested'] ?? []),
            'hint_category' => (string)($recipe['category_slug'] ?? ''),
        ]);
        $category = $cls['category_slug'];
        $tags     = $cls['tag_slugs'];
    }

    // ---------- slug ----------
    $base = generate_slug($title);
    $slug = $base . '-' . substr(md5(uniqid((string)$userId, true)), 0, 6);
    for ($i = 0; $i < 10 && db_one('SELECT id FROM recipes WHERE slug = ?', [$slug]); $i++) {
        $slug = $base . '-' . substr(md5(uniqid((string)$userId, true)), 0, 8);
    }

    // ---------- вставка (только существующие колонки) ----------
    $cols = rp_columns();
    $data = [
        'title'           => $title,
        'slug'            => $slug,
        'description'     => (string)($rec['description'] ?? ''),
        'cuisine'         => ($rec['cuisine'] ?? '') !== '' ? $rec['cuisine'] : 'Другая',
        'difficulty'      => (string)($rec['difficulty'] ?? 'Средне'),
        'prep_time'       => max(0, (int)($rec['prep_time'] ?? 0)),
        'cook_time'       => max(0, (int)($rec['cook_time'] ?? 0)),
        'servings'        => max(1, (int)($rec['servings'] ?? 2)),
        'calories'        => max(0, (int)($rec['calories'] ?? 0)),
        'proteins'        => max(0, (float)($rec['proteins'] ?? 0)),
        'fats'            => max(0, (float)($rec['fats'] ?? 0)),
        'carbs'           => max(0, (float)($rec['carbs'] ?? 0)),
        'bread_units'     => (float)($rec['bread_units'] ?? 0),
        'glycemic_index'  => (int)($rec['glycemic_index'] ?? 50),
        'glycemic_load'   => (float)($rec['glycemic_load'] ?? 0),
        'ingredients'     => json_encode($rec['ingredients'] ?? [], JSON_UNESCAPED_UNICODE),
        'steps'           => json_encode($rec['steps'] ?? [], JSON_UNESCAPED_UNICODE),
        'image_url'       => '/uploads/static/static.jpg',
        'author_id'       => $userId,
        'author_email'    => $userEmail,
        'is_ai_generated' => 1,
        'is_published'    => $publish ? 1 : 0,
        'is_public'       => $publish ? 1 : 0,
        'likes_count'     => 0,
        'views_count'     => 0,
    ];

    $insert = [];
    foreach ($data as $col => $val) {
        if (isset($cols[$col])) $insert[$col] = $val;
    }
    if (!isset($insert['title'], $insert['slug'])) {
        throw new RuntimeException('В таблице recipes нет обязательных колонок title/slug');
    }

    $names        = array_keys($insert);
    $placeholders = array_fill(0, count($names), '?');
    $params       = array_values($insert);
    if (isset($cols['created_at'])) {
        $names[]        = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $recipeId = db_insert(
        'INSERT INTO `recipes` (`' . implode('`, `', $names) . '`) VALUES (' . implode(', ', $placeholders) . ')',
        $params
    );
    if ($recipeId <= 0) throw new RuntimeException('Рецепт не сохранился в БД');

    // ---------- категория и теги ----------
    $applied = rp_apply_classification($recipeId, $category, $tags);

    // ---------- баллы и активность (не критично) ----------
    $points = (int)($opts['points'] ?? 20);
    if ($points > 0 && function_exists('add_user_points')) {
        try {
            add_user_points($userId, $points, 'recipe_create', (string)($opts['label'] ?? 'Создание рецепта'));
        } catch (Throwable $e) {
            error_log('[recipe_publisher] points: ' . $e->getMessage());
        }
    }
    if (function_exists('add_activity')) {
        try {
            add_activity($userEmail, RP_ACTIVITY_TYPE, $recipeId, 'recipe', 'Создал рецепт: ' . $title);
        } catch (Throwable $e) {
            error_log('[recipe_publisher] activity: ' . $e->getMessage());
        }
    }

    return [
        'ok'             => true,
        'recipe_id'      => $recipeId,
        'slug'           => $slug,
        'url'            => url('recipe/' . $slug),
        'category_slug'  => $category,
        'tag_slugs'      => $tags,
        'category_saved' => $applied['category'],
    ];
}
