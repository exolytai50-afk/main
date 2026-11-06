<?php
/**
 * JSON API: чтение рецептов + фьюжн-рецепты
 *   GET /api/recipes.php                 — список (лимит 20)
 *   GET /api/recipes.php?id=5            — один рецепт
 *   GET /api/recipes.php?trending=1      — по лайкам
 *   GET /api/recipes.php?season=Осень    — сезонные
 *   GET /api/recipes.php?q=борщ          — поиск
 *   GET /api/recipes.php?type=fusion     — фьюжн-рецепты
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

try {
    $id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $type = trim((string)($_GET['type'] ?? 'regular')); // regular | fusion | all

    // ============================================================
    //  FUSION: один рецепт по ID
    // ============================================================
    if ($id > 0 && $type === 'fusion') {
        $recipe = db_one('SELECT * FROM fusion_recipes WHERE id = ?', [$id]);
        if (!$recipe) json_response(['error' => 'Фьюжн-рецепт не найден'], 404);

        // Парсим JSON-поля
        foreach (['ingredients', 'steps', 'tips', 'recipe_json'] as $f) {
            $recipe[$f] = json_field($recipe[$f]);
        }

        // Достаём полные данные из recipe_json (если есть)
        if (!empty($recipe['recipe_json']) && is_array($recipe['recipe_json'])) {
            $recipe = array_merge($recipe['recipe_json'], $recipe);
        }

        json_response(['recipe' => $recipe, 'type' => 'fusion']);
    }

    // ============================================================
    //  FUSION: список фьюжн-рецептов
    // ============================================================
    if ($type === 'fusion') {
        $user = function_exists('current_user') ? current_user() : null;
        $userId = $user['id'] ?? 0;

        $limit      = min(40, max(1, (int)($_GET['limit'] ?? 20)));
        $offset     = max(0, (int)($_GET['offset'] ?? 0));
        $savedOnly  = !empty($_GET['saved']);
        $cuisine    = trim((string)($_GET['cuisine'] ?? ''));
        $q          = trim((string)($_GET['q'] ?? ''));

        $where  = 'user_id = :uid';
        $params = [':uid' => $userId];

        if ($savedOnly) {
            $where .= ' AND is_saved = 1';
        }
        if ($cuisine !== '') {
            $where .= ' AND (cuisine_a = :cuisine OR cuisine_b = :cuisine)';
            $params[':cuisine'] = $cuisine;
        }
        if ($q !== '') {
            $where .= ' AND (title LIKE :q OR description LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "SELECT id, title, description, cuisine_a, cuisine_b, cuisine_a_name, cuisine_b_name,
                       dish_type, difficulty, cook_time, servings, calories, image_url,
                       rating, is_saved, created_at
                FROM fusion_recipes
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Общее количество
        $stmtCount = db()->prepare("SELECT COUNT(*) FROM fusion_recipes WHERE {$where}");
        foreach ($params as $k => $v) {
            $stmtCount->bindValue($k, $v);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();

        json_response([
            'recipes' => $rows,
            'count'   => count($rows),
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'type'    => 'fusion'
        ]);
    }

    // ============================================================
    //  REGULAR: один рецепт по ID (существующая логика)
    // ============================================================
    if ($id > 0) {
        $recipe = db_one('SELECT * FROM recipes WHERE id = ?', [$id]);
        if (!$recipe) json_response(['error' => 'Рецепт не найден'], 404);
        db_exec('UPDATE recipes SET views_count = views_count + 1 WHERE id = ?', [$id]);
        foreach (['diet_type','ingredients','steps','tags','season'] as $f) {
            $recipe[$f] = json_field($recipe[$f]);
        }
        json_response(['recipe' => $recipe, 'type' => 'regular']);
    }

        // ============================================================
    //  REGULAR: список с фильтрами по категории и тегам
    // ============================================================
    $limit  = min(40, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $where  = ['1=1'];
    $params = [];
    $order  = 'r.created_at DESC';

    // Сортировка
    if (!empty($_GET['trending'])) {
        $order = 'r.likes_count DESC';
    }

    // Фильтр по кухне (legacy)
    if (!empty($_GET['cuisine'])) {
        $where[] = 'r.cuisine = ?';
        $params[] = $_GET['cuisine'];
    }

    // Фильтр по категории (новое)
    $categorySlug = trim((string)($_GET['category'] ?? ''));
    if ($categorySlug !== '') {
        $where[] = 'c.slug = ?';
        $params[] = $categorySlug;
    }

    // Фильтр по тегам
    $tagSlugs = $_GET['tags'] ?? ($_GET['tag'] ?? []);
    if (is_string($tagSlugs)) $tagSlugs = [$tagSlugs];
    $tagSlugs = array_values(array_filter(array_map('strval', (array)$tagSlugs)));
    $tagMode  = $_GET['tag_mode'] ?? 'and';   // and | or

    if (!empty($tagSlugs)) {
        $ph = implode(',', array_fill(0, count($tagSlugs), '?'));

        if ($tagMode === 'or') {
            $where[] = "EXISTS (
                SELECT 1 FROM recipe_tag_links l
                JOIN recipe_tags t ON t.id = l.tag_id
                WHERE l.recipe_id = r.id AND t.slug IN ({$ph})
            )";
            $params = array_merge($params, $tagSlugs);
        } else {
            // AND: все теги должны присутствовать
            $where[] = "(
                SELECT COUNT(DISTINCT t.slug) FROM recipe_tag_links l
                JOIN recipe_tags t ON t.id = l.tag_id
                WHERE l.recipe_id = r.id AND t.slug IN ({$ph})
            ) = ?";
            $params = array_merge($params, $tagSlugs, [count($tagSlugs)]);
        }
    }

    // Поиск по тексту
    if (!empty($_GET['q'])) {
        $where[] = '(r.title LIKE ? OR r.description LIKE ?)';
        $like = '%' . $_GET['q'] . '%';
        $params[] = $like;
        $params[] = $like;
    }

    // Сезон (legacy)
    if (!empty($_GET['season'])) {
        $where[] = 'JSON_CONTAINS(r.season, ?)';
        $params[] = json_encode($_GET['season'], JSON_UNESCAPED_UNICODE);
    }

    $whereSql = implode(' AND ', $where);

    $rows = db_all(
        "SELECT r.id, r.title, r.slug, r.description, r.cuisine,
                r.difficulty, r.prep_time, r.cook_time, r.servings,
                r.calories, r.image_url, r.tags, r.season,
                r.likes_count, r.views_count, r.is_ai_generated,
                c.slug     AS category_slug,
                c.name     AS category_name,
                c.icon_svg AS category_icon
         FROM recipes r
         LEFT JOIN recipe_categories c ON c.id = r.category_id
         WHERE {$whereSql}
         ORDER BY {$order}
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );

    // Загрузка тегов
    if (!empty($rows)) {
        $ids = array_column($rows, 'id');
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
            $byRecipe[$row['recipe_id']][] = [
                'id'        => (int)$row['id'],
                'slug'      => $row['slug'],
                'name'      => $row['name'],
                'icon_svg'  => $row['icon_svg'],
                'tag_group' => $row['tag_group'],
            ];
        }
        foreach ($rows as &$r) {
            $r['tags']      = json_field($r['tags']);
            $r['season']    = json_field($r['season']);
            $r['tags_list'] = $byRecipe[$r['id']] ?? [];
        }
        unset($r);
    }

    json_response([
        'recipes' => $rows,
        'count'   => count($rows),
        'limit'   => $limit,
        'offset'  => $offset,
        'type'    => 'regular',
    ]);
    return;   // ← важно, чтобы не пойти в старую логику ниже

} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'], 500);
}