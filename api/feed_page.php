<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gc_social.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];
$scope     = $_GET['scope'] ?? 'all';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$params = [];
$where  = '';

switch ($scope) {
    case 'friends':
        $where  = "(af.user_email IN (SELECT friend_email FROM friendships WHERE user_email = ? AND status = 'accepted') OR af.user_email = ?)
                   AND (af.visibility = 'public' OR af.visibility = 'friends' OR af.user_email = ?)";
        $params = [$userEmail, $userEmail, $userEmail];
        break;
    case 'ai':
        $where  = "af.type IN ('ai_recipe','fusion_generate','fusion_save','calorie_scan') AND (af.visibility = 'public' OR af.user_email = ?)";
        $params = [$userEmail];
        break;
    case 'challenges':
        $where  = "af.type IN ('challenge_joined','challenge_submitted','challenge_completed') AND (af.visibility = 'public' OR af.user_email = ?)";
        $params = [$userEmail];
        break;
    default:
        $where  = "(af.visibility = 'public' OR (af.visibility = 'friends' AND af.user_email IN (SELECT friend_email FROM friendships WHERE user_email = ? AND status = 'accepted')) OR af.user_email = ?)";
        $params = [$userEmail, $userEmail];
}

$sql = "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
               r.title AS recipe_title, r.slug AS recipe_slug, r.image_url AS recipe_image,
               r.description AS recipe_description, r.prep_time, r.cook_time, r.servings, r.calories, r.likes_count,
               c.slug     AS category_slug,
               c.name     AS category_name,
               c.icon_svg AS category_icon
        FROM activity_feed af
        JOIN users u ON u.email = af.user_email
        LEFT JOIN recipes r ON af.reference_type = 'recipe' AND r.id = af.reference_id
        LEFT JOIN recipe_categories c ON c.id = r.category_id
        WHERE {$where}
        ORDER BY af.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

$items = db_all($sql, $params);

// Загружаем теги для всех рецептов одним запросом
$feedRecipeIds = [];
foreach ($items as $it) {
    if (!empty($it['recipe_slug']) && !empty($it['reference_id']) && $it['reference_type'] === 'recipe') {
        $feedRecipeIds[] = (int)$it['reference_id'];
    }
}
if (!empty($feedRecipeIds)) {
    $ph = implode(',', array_fill(0, count($feedRecipeIds), '?'));
    $rows = db_all(
        "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l
         JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
         ORDER BY t.tag_group, t.sort_order, t.slug",
        $feedRecipeIds
    );
    $byRecipe = [];
    foreach ($rows as $row) {
        $byRecipe[$row['recipe_id']][] = [
            'id'        => (int)$row['id'],
            'slug'      => $row['slug'],
            'name'      => $row['name'],
            'icon_svg'  => $row['icon_svg'],
            'tag_group' => $row['tag_group'],
        ];
    }
    foreach ($items as &$it) {
        $rid = (int)($it['reference_id'] ?? 0);
        $it['tags_list'] = $byRecipe[$rid] ?? [];
    }
    unset($it);
}

// Комментарии: схема БД + счётчики для всей порции одним запросом
gc_schema_ensure();
gc_prefetch_counts('feed', $items);

ob_start();
foreach ($items as $item) {
    include __DIR__ . '/../components/feed-item.php'; // карточка + комментарии
}
$html = ob_get_clean();

echo json_encode([
    'ok'      => true,
    'html'    => $html,
    'hasMore' => count($items) === $perPage,
    'page'    => $page,
], JSON_UNESCAPED_UNICODE);