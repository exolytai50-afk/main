<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$slug    = trim((string)($_GET['slug'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

if ($slug === '') {
    json_response(['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
}

$category = db_one('SELECT * FROM recipe_categories WHERE slug = ? AND is_active = 1', [$slug]);
if (!$category) {
    json_response(['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
}

$categoryId = (int)$category['id'];

$totalRow = db_one("SELECT COUNT(*) AS cnt FROM recipes WHERE category_id = ?", [$categoryId]);
$total    = (int)($totalRow['cnt'] ?? 0);

$recipes = db_all(
    "SELECT r.id, r.slug, r.title, r.description, r.cuisine, r.difficulty,
            r.prep_time, r.cook_time, r.servings, r.calories,
            r.image_url, r.is_ai_generated, r.likes_count, r.views_count,
            u.display_name AS author_name,
            u.avatar_url   AS author_avatar_url,
            u.gender       AS author_gender,
            r.author_email AS author_email,
            c.id           AS category_id,
            c.slug         AS category_slug,
            c.name         AS category_name,
            c.icon_svg     AS category_icon
     FROM recipes r
     LEFT JOIN users u ON u.email = r.author_email
     LEFT JOIN recipe_categories c ON c.id = r.category_id
     WHERE r.category_id = ?
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?",
    [$categoryId, $perPage, $offset]
);

if (!empty($recipes)) {
    $ids = array_column($recipes, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $tagRows = db_all(
        "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l
         JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
         ORDER BY t.tag_group, t.sort_order, t.slug",
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
                ? icon($row['icon_svg'], 'gc-icon-md', 1.25)
                : '',
            'tag_group'     => $row['tag_group'],
        ];
    }
    foreach ($recipes as &$r) {
        $r['tags_list'] = $byRecipe[(int)$r['id']] ?? [];
    }
    // Обогащаем карточки HTML иконки категории
    foreach ($recipes as &$r) {
        $r['category_icon_svg_html'] = !empty($r['category_icon'])
            ? icon($r['category_icon'], 'gc-icon-md', 1.25)
            : '';
    }
    unset($r);
}

json_response([
    'items'       => $recipes,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => (int)ceil($total / $perPage),
]);