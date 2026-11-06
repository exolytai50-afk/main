<?php
/**
 * GET /api/search.php?q=...&difficulty=...&category=...&tags[group]=slug1,slug2&sort=...
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$q          = trim((string)($_GET['q'] ?? ''));
$difficulty = trim((string)($_GET['difficulty'] ?? ''));
$category   = trim((string)($_GET['category'] ?? ''));
$sort       = trim((string)($_GET['sort'] ?? 'popular'));
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = min(60, max(1, (int)($_GET['per_page'] ?? 24)));
$offset     = ($page - 1) * $perPage;

$tagFilters = $_GET['tags'] ?? [];       // ['cuisine'=>'italyanskaya', 'time'=>'do-30-minut']

$conditions = [];
$params     = [];
$joins      = [];
$joinParams = [];   // ← ОБЯЗАТЕЛЬНО объявляем

/* ─── Поиск по тексту ─── */
if ($q !== '') {
    $conditions[] = '(r.title LIKE ? OR r.description LIKE ?)';
    $params[]     = "%{$q}%";
    $params[]     = "%{$q}%";
}

/* ─── Сложность ─── */
if ($difficulty !== '') {
    $conditions[] = 'r.difficulty = ?';
    $params[]     = $difficulty;
}

/* ─── Категория (по slug) ─── */
if ($category !== '') {
    $cat = db_one(
        'SELECT id FROM recipe_categories WHERE slug = ? AND is_active = 1',
        [$category]
    );
    if ($cat) {
        $conditions[] = 'r.category_id = ?';
        $params[]     = (int)$cat['id'];
    } else {
        // Категория не найдена — пустой результат
        json_response([
            'items' => [], 'recipes' => [], 'total' => 0,
            'hasMore' => false, 'page' => 1, 'total_pages' => 0,
        ]);
    }
}

/* ─── Теги по группам: tags[group]=slug1,slug2 ─── */
if (is_array($tagFilters)) {
    foreach ($tagFilters as $group => $slugStr) {
        // Защита от инъекции в имя группы (только [a-z_])
        $group = preg_replace('/[^a-z_]/', '', (string)$group);
        if ($group === '') continue;

        $slugs = array_filter(array_map('trim', explode(',', (string)$slugStr)));
        if (empty($slugs)) continue;

        $ph = implode(',', array_fill(0, count($slugs), '?'));

        // INNER JOIN — рецепт должен иметь хотя бы один из выбранных тегов
        // (OR внутри группы, AND между группами — так как каждый JOIN добавляет условие)
        $joins[] = "INNER JOIN recipe_tag_links l_{$group}
                    ON l_{$group}.recipe_id = r.id
                    AND l_{$group}.tag_id IN (
                        SELECT id FROM recipe_tags
                        WHERE slug IN ({$ph})
                          AND tag_group = ?
                          AND is_active = 1
                    )";

        foreach ($slugs as $s) $joinParams[] = $s;
        $joinParams[] = $group;
    }
}

$where      = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$joinSql    = $joins ? ' ' . implode(' ', $joins) : '';
$allParams  = array_merge($joinParams, $params);

$orderBy = match ($sort) {
    'new'           => 'r.created_at DESC',
    'calories_asc'  => 'r.calories ASC',
    'calories_desc' => 'r.calories DESC',
    'time_asc'      => '(r.prep_time + r.cook_time) ASC',
    default         => 'r.likes_count DESC, r.views_count DESC',
};

try {
    /* ─── COUNT ─── */
    $totalRow = db_one(
        "SELECT COUNT(DISTINCT r.id) AS cnt
         FROM recipes r
         {$joinSql}
         {$where}",
        $allParams
    );
    $total = (int)($totalRow['cnt'] ?? 0);

    /* ─── SELECT ─── */
    $recipes = db_all(
        "SELECT DISTINCT r.id, r.slug, r.title, r.description, r.cuisine, r.difficulty,
                r.prep_time, r.cook_time, r.servings, r.calories,
                r.image_url, r.is_ai_generated, r.likes_count, r.views_count,
                r.proteins, r.fats, r.carbs,
                u.display_name AS author_name,
                u.avatar_url   AS author_avatar_url,
                u.gender       AS author_gender,
                r.author_email AS author_email,
                c.id           AS category_id,
                c.slug         AS category_slug,
                c.name         AS category_name,
                c.icon_svg     AS category_icon
         FROM recipes r
         {$joinSql}
         LEFT JOIN users u ON u.email = r.author_email
         LEFT JOIN recipe_categories c ON c.id = r.category_id
         {$where}
         ORDER BY {$orderBy}
         LIMIT {$perPage} OFFSET {$offset}",
        $allParams
    );

    /* ─── Теги карточек + SVG ─── */
    if (!empty($recipes)) {
        $ids = array_column($recipes, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));

        $tagRows = db_all(
            "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
             FROM recipe_tag_links l
             JOIN recipe_tags t ON t.id = l.tag_id
             WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
             ORDER BY t.tag_group, t.sort_order, t.name",
            $ids
        );

        $byRecipe = [];
        foreach ($tagRows as $row) {
            $byRecipe[(int)$row['recipe_id']][] = [
                'id'            => (int)$row['id'],
                'slug'          => $row['slug'],
                'name'          => $row['name'],
                'icon_svg'      => $row['icon_svg'],
                'icon_svg_html' => !empty($row['icon_svg'])
                    ? icon($row['icon_svg'], 'gc-icon-xs', 0.9)
                    : '',
                'tag_group'     => $row['tag_group'],
            ];
        }
        foreach ($recipes as &$r) {
            $r['tags_list'] = $byRecipe[(int)$r['id']] ?? [];
        }
        unset($r);
    }

    $hasMore = ($offset + count($recipes)) < $total;

    json_response([
        'items'       => $recipes,
        'recipes'     => $recipes,
        'total'       => $total,
        'hasMore'     => $hasMore,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ]);
} catch (Throwable $e) {
    error_log('[search] ' . $e->getMessage());
    json_response([
        'error'   => APP_DEBUG ? $e->getMessage() : 'Ошибка поиска',
        'items'   => [],
        'recipes' => [],
        'total'   => 0,
        'hasMore' => false,
    ], 500);
}