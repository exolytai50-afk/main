<?php
/**
 * pages/recipe_detail.php
 *
 * Детальная страница рецепта с иконками из includes/icon-names.php
 */

require_once __DIR__ . '/../config/config.php';

/* ─── Валидация входных данных ─── */
$id = (int)($_GET['id'] ?? 0);
$slug = preg_replace('/[^a-z0-9_-]/i', '', trim((string)($_GET['slug'] ?? '')));

if ($id === 0 && $slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$recipeSql = "SELECT r.*, 
                     u.display_name AS author_name,
                     u.avatar_url   AS author_avatar_url,
                     u.avatar_emoji AS author_avatar_emoji,
                     u.gender       AS author_gender,
                     u.id           AS author_user_id
              FROM recipes r
              LEFT JOIN users u ON u.email = r.author_email
              WHERE r.%s = ?";

if ($id > 0) {
    $recipe = db_one(sprintf($recipeSql, 'id'), [$id]);
} elseif ($slug !== '') {
    $recipe = db_one(sprintf($recipeSql, 'slug'), [$slug]);
}

if (!$recipe) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$id = (int)$recipe['id'];
if (empty($recipe['slug'])) {
    $recipe['slug'] = (string)$id;
}

db_exec('UPDATE recipes SET views_count = views_count + 1 WHERE id = ?', [$id]);

$ings = json_field($recipe['ingredients']);

/* Функция конвертации единиц измерения */
function convertUnitToRussian($unit) {
    if (empty($unit)) return '';
    $unit = trim((string)$unit);
    $lowerUnit = mb_strtolower($unit);
    $unitMap = [
        'g' => 'г', 'gr' => 'г', 'gram' => 'г', 'grams' => 'г',
        'kg' => 'кг', 'kilogram' => 'кг', 'kilograms' => 'кг',
        'ml' => 'мл', 'milliliter' => 'мл', 'milliliters' => 'мл',
        'l' => 'л', 'liter' => 'л', 'liters' => 'л',
        'tbsp' => 'ст.л.', 'tablespoon' => 'ст.л.', 'tablespoons' => 'ст.л.',
        'tsp' => 'ч.л.', 'teaspoon' => 'ч.л.', 'teaspoons' => 'ч.л.',
        'cup' => 'стакан', 'cups' => 'стакана',
        'pcs' => 'шт', 'pc' => 'шт', 'piece' => 'шт', 'pieces' => 'шт',
        'pinch' => 'щепотка', 'clove' => 'зубчик', 'cloves' => 'зубчика',
        'slice' => 'ломтик', 'slices' => 'ломтика',
        'can' => 'банка', 'bunch' => 'пучок',
        'sprig' => 'веточка', 'sprigs' => 'веточки',
        'stalk' => 'стебель', 'stalks' => 'стебля',
        'stick' => 'палочка', 'sticks' => 'палочки',
        'to_taste' => 'по вкусу', 'to taste' => 'по вкусу',
        'handful' => 'горсть', 'glass' => 'стакан', 'glasses' => 'стакана',
        'drop' => 'капля', 'drops' => 'капли',
    ];
    if (isset($unitMap[$lowerUnit])) return $unitMap[$lowerUnit];
    if (strpos($lowerUnit, 'tbsp') !== false) return 'ст.л.';
    if (strpos($lowerUnit, 'tsp') !== false) return 'ч.л.';
    if (strpos($lowerUnit, 'cup') !== false) return 'стакан';
    if (strpos($lowerUnit, 'piece') !== false || strpos($lowerUnit, 'pcs') !== false) return 'шт';
    return $unit;
}

function convertAmountToRussian($amount) {
    if (empty($amount)) return '';
    $amount = trim((string)$amount);
    $lowerAmount = mb_strtolower($amount);
    $amountMap = [
        'to_taste' => 'по вкусу', 'to taste' => 'по вкусу',
        'a_pinch' => 'щепотка', 'a_pinch_of' => 'щепотка',
        'some' => 'немного', 'a_few' => 'несколько',
        'half' => 'половина', 'quarter' => 'четверть',
        'a_handful' => 'горсть', 'a_handful_of' => 'горсть',
    ];
    if (isset($amountMap[$lowerAmount])) return $amountMap[$lowerAmount];
    $amount = preg_replace('/(\d+)\s*(g|gr|gram|grams)\b/i', '$1 г', $amount);
    $amount = preg_replace('/(\d+)\s*(kg|kilogram|kilograms)\b/i', '$1 кг', $amount);
    $amount = preg_replace('/(\d+)\s*(ml|milliliter|milliliters)\b/i', '$1 мл', $amount);
    $amount = preg_replace('/(\d+)\s*(l|liter|liters)\b/i', '$1 л', $amount);
    $amount = preg_replace('/(\d+)\s*(tbsp|tablespoon|tablespoons)\b/i', '$1 ст.л.', $amount);
    $amount = preg_replace('/(\d+)\s*(tsp|teaspoon|teaspoons)\b/i', '$1 ч.л.', $amount);
    return $amount;
}

foreach ($ings as &$ing) {
    if (is_array($ing)) {
        if (isset($ing['unit']))   $ing['unit']   = convertUnitToRussian($ing['unit']);
        if (isset($ing['amount'])) $ing['amount'] = convertAmountToRussian($ing['amount']);
        if (isset($ing['amount']) && is_string($ing['amount'])) {
            $ing['amount'] = preg_replace('/(\d+)\s*(g|gr|gram|grams)\b/i', '$1 г', $ing['amount']);
            $ing['amount'] = preg_replace('/(\d+)\s*(kg|kilogram|kilograms)\b/i', '$1 кг', $ing['amount']);
            $ing['amount'] = preg_replace('/(\d+)\s*(ml|milliliter|milliliters)\b/i', '$1 мл', $ing['amount']);
            $ing['amount'] = preg_replace('/(\d+)\s*(l|liter|liters)\b/i', '$1 л', $ing['amount']);
        }
    }
}
unset($ing);

$steps  = json_field($recipe['steps']);
$tags   = json_field($recipe['tags']);          
$diet   = json_field($recipe['diet_type']);
$total  = (int)$recipe['prep_time'] + (int)$recipe['cook_time'];
$hasVideo = !empty($recipe['video_url']);

/* ─── НОВОЕ: загрузка категории из recipe_categories ─── */
$category = null;
if (!empty($recipe['category_id'])) {
    $category = db_one(
        'SELECT id, slug, name, icon_svg, description
         FROM recipe_categories
         WHERE id = ? AND is_active = 1',
        [(int)$recipe['category_id']]
    );
}

/* ─── НОВОЕ: загрузка тегов из recipe_tag_links ─── */
$recipeTags = [];
if (!empty($id)) {
    $recipeTags = db_all(
        "SELECT t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l
         JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id = ? AND t.is_active = 1
         ORDER BY t.tag_group, t.sort_order, t.name",
        [$id]
    );
}

/* ─── НОВОЕ: группировка тегов по tag_group для удобного вывода ─── */
$tagsByGroup = [];
foreach ($recipeTags as $t) {
    $tagsByGroup[$t['tag_group']][] = $t;
}

/* ─── НОВОЕ: определение тега-кухни для HERO и JSON-LD ─── */
$cuisineTag = null;
foreach ($recipeTags as $t) {
    if ($t['tag_group'] === 'cuisine') { $cuisineTag = $t; break; }
}

/* ─── Загрузка смайлов ─── */
$emojiPacks = [];
try {
    $emojiPacks = db_all(
        "SELECT ep.*, (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji
         FROM emoji_packs ep WHERE ep.is_active = 1 ORDER BY ep.sort_order ASC"
    );
} catch (Throwable $e) { $emojiPacks = []; }

$allEmojis = [];
try {
    $allEmojis = db_all(
        "SELECT ei.* FROM emoji_images ei JOIN emoji_packs ep ON ep.id = ei.pack_id WHERE ep.is_active = 1 ORDER BY ei.pack_id, ei.sort_order ASC"
    );
} catch (Throwable $e) { $allEmojis = []; }

$emojisByPack = [];
foreach ($allEmojis as $emoji) {
    $emojisByPack[$emoji['pack_id']][] = $emoji;
}

/* ─── Автоматические мета-данные ─── */
$cuisineLabel = $cuisineTag['name'] ?? $recipe['cuisine'] ?? 'домашняя кухня';

$pageTitle = $recipe['title'] . ' — рецепт с фото | GigaChef';
$pageDescription = mb_substr(strip_tags($recipe['description'] ?? ''), 0, 160);
if (mb_strlen($pageDescription) < 50) {
    $pageDescription = 'Рецепт «' . $recipe['title'] . '» на GigaChef — ' .
        $cuisineLabel . ', ' .
        (int)$recipe['calories'] . ' ккал, ' . $total . ' минут.';
}
$pageImage = !empty($recipe['image_url']) ? $recipe['image_url'] : url('assets/icon/chef_256.png');
$scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/recipe/' . ($recipe['slug'] ?? $id);
$pageKeywords = $recipe['title'] . ', рецепт, ' . $cuisineLabel . ', GigaChef';

/* ─── Похожие рецепты (файловый кэш 24ч, с категориями и тегами) ─── */
$similarRecipes = [];
$cacheDir  = ROOT_DIR . '/logs/cache';
$cacheFile = $cacheDir . '/similar_' . $id . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cached) && !empty($cached)) {
        $similarRecipes = $cached;
    }
}

if (empty($similarRecipes)) {
    $similarRecipes = [];

    // 1) По общим тегам (приоритет — cuisine)
    if (!empty($recipeTags)) {
        $tagIds = array_column($recipeTags, 'id');
        $ph     = implode(',', array_fill(0, count($tagIds), '?'));

        $similarRecipes = db_all(
            "SELECT DISTINCT r.id, r.slug, r.title, r.image_url, r.cuisine, r.difficulty,
                    r.prep_time, r.cook_time, r.servings, r.calories,
                    r.likes_count, r.views_count,
                    c.id       AS category_id,
                    c.slug     AS category_slug,
                    c.name     AS category_name,
                    c.icon_svg AS category_icon
             FROM recipes r
             INNER JOIN recipe_tag_links l ON l.recipe_id = r.id
             LEFT JOIN recipe_categories c ON c.id = r.category_id
             WHERE r.id != ? AND l.tag_id IN ({$ph})
             ORDER BY r.likes_count DESC, r.views_count DESC
             LIMIT 4",
            array_merge([$id], $tagIds)
        );
    }

    // 2) Добираем по категории
    if (count($similarRecipes) < 4 && !empty($category)) {
        $existingIds = array_column($similarRecipes, 'id');
        $placeholders = $existingIds
            ? implode(',', array_fill(0, count($existingIds), '?'))
            : null;

        $sql = "SELECT r.id, r.slug, r.title, r.image_url, r.cuisine, r.difficulty,
                       r.prep_time, r.cook_time, r.servings, r.calories,
                       r.likes_count, r.views_count,
                       c.id       AS category_id,
                       c.slug     AS category_slug,
                       c.name     AS category_name,
                       c.icon_svg AS category_icon
                FROM recipes r
                LEFT JOIN recipe_categories c ON c.id = r.category_id
                WHERE r.id != ? AND r.category_id = ?"
             . ($placeholders ? " AND r.id NOT IN ({$placeholders})" : '')
             . " ORDER BY r.likes_count DESC, r.views_count DESC LIMIT ?";

        $params = array_merge([$id, (int)$category['id']], $existingIds, [4 - count($similarRecipes)]);
        $extra = db_all($sql, $params);
        $similarRecipes = array_merge($similarRecipes, $extra);
    }

    // 3) Добираем популярными
    if (count($similarRecipes) < 4) {
        $existingIds = array_column($similarRecipes, 'id');
        $placeholders = $existingIds
            ? implode(',', array_fill(0, count($existingIds), '?'))
            : null;

        $sql = "SELECT r.id, r.slug, r.title, r.image_url, r.cuisine, r.difficulty,
                       r.prep_time, r.cook_time, r.servings, r.calories,
                       r.likes_count, r.views_count,
                       c.id       AS category_id,
                       c.slug     AS category_slug,
                       c.name     AS category_name,
                       c.icon_svg AS category_icon
                FROM recipes r
                LEFT JOIN recipe_categories c ON c.id = r.category_id
                WHERE r.id != ?"
             . ($placeholders ? " AND r.id NOT IN ({$placeholders})" : '')
             . " ORDER BY r.likes_count DESC, r.views_count DESC LIMIT ?";

        $params = array_merge([$id], $existingIds, [4 - count($similarRecipes)]);
        $extra = db_all($sql, $params);
        $similarRecipes = array_merge($similarRecipes, $extra);
    }

    // Подгрузка тегов для каждой карточки
    if (!empty($similarRecipes)) {
        $simIds = array_column($similarRecipes, 'id');
        $ph     = implode(',', array_fill(0, count($simIds), '?'));

        $tagRows = db_all(
            "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
             FROM recipe_tag_links l
             JOIN recipe_tags t ON t.id = l.tag_id
             WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
             ORDER BY t.tag_group, t.sort_order, t.slug",
            $simIds
        );

        $byRecipe = [];
        foreach ($tagRows as $row) {
            $byRecipe[(int)$row['recipe_id']][] = [
                'id'        => (int)$row['id'],
                'slug'      => $row['slug'],
                'name'      => $row['name'],
                'icon_svg'  => $row['icon_svg'],
                'tag_group' => $row['tag_group'],
            ];
        }
        foreach ($similarRecipes as &$r) {
            $r['tags_list'] = $byRecipe[(int)$r['id']] ?? [];
        }
        unset($r);
    }

    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($cacheFile, json_encode($similarRecipes, JSON_UNESCAPED_UNICODE));
}

/* ─── Гибридная логика встраивания видео ─── */
$videoEmbedUrl = null;
$videoStatusMessage = '';

if (!empty($recipe['video_owner_id']) && !empty($recipe['video_id'])) {
    // --- 1. Обработка видео, загруженного на VK ---
    $ownerId = (int)$recipe['video_owner_id'];
    $videoId = (int)$recipe['video_id'];
    
    // Кэшируем результат на 1 час
    $vkCacheFile = $cacheDir . '/vk_video_' . $ownerId . '_' . $videoId . '.json';
    $vkData = null;
    
    if (file_exists($vkCacheFile) && (time() - filemtime($vkCacheFile)) < 3600) {
        $vkData = json_decode(file_get_contents($vkCacheFile), true);
    }
    
    // Если в кэше нет данных — делаем запрос к VK API
    if (!$vkData) {
        $vkParams = [
            'access_token' => VK_COMMUNITY_TOKEN,
            'videos'       => "{$ownerId}_{$videoId}",
            'v'            => '5.199'
        ];
        $ch = curl_init('https://api.vk.com/method/video.get');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($vkParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $vkData = json_decode($response, true);
        
        // Сохраняем в кэш (только если нет ошибки)
        if (!isset($vkData['error'])) {
            if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
            file_put_contents($vkCacheFile, json_encode($vkData));
        }
    }

    // Разбираем ответ от VK
    if (isset($vkData['response']['items'][0])) {
        $videoItem = $vkData['response']['items'][0];
        
        if (!empty($videoItem['processing'])) {
            $videoStatusMessage = 'Видео еще обрабатывается на серверах VK. Пожалуйста, зайдите позже.';
        } elseif (!empty($videoItem['player'])) {
            // Если VK отдал готовый player URL
            $videoEmbedUrl = $videoItem['player'];
        } elseif (isset($videoItem['hash'])) {
            // Если есть хэш, формируем стандартную ссылку для iframe
            $videoEmbedUrl = "https://vk.com/video_ext.php?oid={$ownerId}&id={$videoId}&hash={$videoItem['hash']}";
        } else {
            $videoStatusMessage = 'Видео доступно, но формат встраивания не поддерживается.';
        }
    } else {
        $errorMsg = $vkData['error']['error_msg'] ?? 'Неизвестная ошибка';
        $videoStatusMessage = 'Не удалось получить данные о видео из VK. ' . ($errorMsg !== 'Unknown error' ? e($errorMsg) : '');
    }

} elseif (!empty($recipe['video_url'])) {
    // --- 2. Обработка старых прямых ссылок (Rutube, YouTube, Vimeo) ---
    $url = $recipe['video_url'];
    
    // Rutube
    if (preg_match('/rutube\.ru\/(?:video|shorts|live)\/([a-zA-Z0-9_-]+)/i', $url, $m)) {
        $videoEmbedUrl = "https://rutube.ru/play/embed/{$m[1]}/";
    } 
    // YouTube
    elseif (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $url, $m)) {
        $videoEmbedUrl = "https://www.youtube.com/embed/{$m[1]}";
    } 
    // Vimeo
    elseif (preg_match('/vimeo\.com\/(\d+)/i', $url, $m)) {
        $videoEmbedUrl = "https://player.vimeo.com/video/{$m[1]}";
    } 
    // Если это уже готовая embed-ссылка
    elseif (str_contains($url, 'embed') || str_contains($url, 'play/embed')) {
        $videoEmbedUrl = $url;
    } 
    // Если ссылка неизвестного формата — просто выводим на нее ссылку
    else {
        $videoStatusMessage = 'Видео доступно по ссылке: <a href="' . e($url) . '" target="_blank" class="text-blue-500 underline hover:text-blue-700">Открыть видео</a>';
    }
}

/* ─── Сохранённые рецепты ─── */
$isSaved = false;
if (is_logged_in()) {
    $savedCheck = db_one('SELECT id FROM favorites WHERE user_email = ? AND recipe_id = ?', [current_user()['email'], $id]);
    $isSaved = $savedCheck !== null;
}

/* ─── Голоса за рецепт (лайк/дизлайк) ─── */
$voteData = ['like_count' => (int)($recipe['likes_count'] ?? 0), 'dislike_count' => 0, 'my_vote' => 0];
if (is_logged_in()) {
    try {
        $voteRow = db_one(
            "SELECT
                (SELECT COUNT(*) FROM chat_votes WHERE target_type='recipe' AND target_id=? AND vote=1) as like_count,
                (SELECT COUNT(*) FROM chat_votes WHERE target_type='recipe' AND target_id=? AND vote=-1) as dislike_count,
                (SELECT vote FROM chat_votes WHERE target_type='recipe' AND target_id=? AND user_email=CONVERT(? USING utf8mb4)) as my_vote",
            [$id, $id, $id, current_user()['email']]
        );
        if ($voteRow) {
            $voteData['like_count']    = (int)$voteRow['like_count'];
            $voteData['dislike_count'] = (int)$voteRow['dislike_count'];
            $voteData['my_vote']       = (int)$voteRow['my_vote'];
        }
    } catch (Throwable $e) {
        error_log('Recipe vote load error: ' . $e->getMessage());
    }
}

/* ─── Шаринг ─── */
$defaultImage = '/uploads/static/static.jpg';
$imageUrl = !empty($recipe['image_url']) ? $recipe['image_url'] : $defaultImage;
$shareUrl = urlencode($pageUrl);
$shareTitle = urlencode($recipe['title'] . ' — GigaChef');
$shareDesc = urlencode(mb_substr(strip_tags($recipe['description'] ?? ''), 0, 200));
$shareImage = urlencode($imageUrl);

/* ─── Адаптации для диабетиков ─── */
$diabetesAdaptations = [];
$hasAdaptation = false;
if (is_logged_in()) {
    $diabetesAdaptations = db_all(
        'SELECT * FROM diabetes_adapted_recipes WHERE original_recipe_id = ? AND user_email = ? AND is_hidden = 0 ORDER BY created_at DESC',
        [$id, current_user()['email']]
    );
    $hasAdaptation = count($diabetesAdaptations) > 0;
}
$diabetesTypes = [
    'type1' => 'Диабет 1 типа', 'type2' => 'Диабет 2 типа',
    'gestational' => 'Гестационный диабет', 'prediabetes' => 'Преддиабет',
];
$adaptedTypes = [];
foreach ($diabetesAdaptations as $adapt) {
    $type = $adapt['diabetes_type'] ?? 'type2';
    if (!isset($adaptedTypes[$type])) $adaptedTypes[$type] = $adapt;
}
$preparedAdaptations = [];
foreach ($diabetesAdaptations as $adapt) {
    $rawContent = $adapt['adapted_content'] ?? '';
    $decoded = null; $notes = '';
    if (is_string($rawContent)) {
        $decoded = json_decode($rawContent, true);
        if (!is_array($decoded)) $decoded = $rawContent;
    } elseif (is_array($rawContent)) { $decoded = $rawContent; }
    if (is_array($decoded)) $notes = $decoded['notes'] ?? '';
    if (empty($notes)) $notes = $adapt['notes'] ?? '';
    $preparedAdaptations[] = [
        'id' => $adapt['id'], 'diabetes_type' => $adapt['diabetes_type'] ?? 'type2',
        'content' => $decoded, 'notes' => $notes, 'created_at' => $adapt['created_at']
    ];
}
$selectedAdaptationId = (int)($_GET['adapt_id'] ?? 0);
$selectedAdaptation = null;
if ($selectedAdaptationId > 0) {
    foreach ($preparedAdaptations as $adapt) {
        if ($adapt['id'] === $selectedAdaptationId) { $selectedAdaptation = $adapt; break; }
    }
}
if (!$selectedAdaptation && !empty($preparedAdaptations)) {
    $selectedAdaptation = $preparedAdaptations[0];
}

/* ─── Данные для блока «Поделиться» ─── */
$shareData = [
    'title'       => $recipe['title'],
    'url'         => $pageUrl,
    'description' => mb_substr(strip_tags($recipe['description'] ?? ''), 0, 200),
    'image'       => !empty($recipe['image_url']) ? $recipe['image_url'] : url('assets/icon/chef_256.png'),
];

require_once __DIR__ . '/../includes/header.php';

echo '<link rel="stylesheet" href="/assets/css/reviews.css">';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script src="<?= url('assets/js/api-client.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/ai/nutrition-chart.js') ?>"></script>
<script src="<?= url('assets/js/ai/ingredient-substitute.js') ?>"></script>
<script src="<?= url('assets/js/ai/recipe-assistant.js') ?>"></script>
<script src="<?= url('assets/js/recipe/reviews.js') ?>?v=11"></script>

<script>
	window.DEFAULT_AVATAR_SVG_MALE = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
	window.DEFAULT_AVATAR_SVG_FEMALE = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
	window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;

	window.getAvatarSvg = function(gender) {
		if (gender === 'male') return window.DEFAULT_AVATAR_SVG_MALE;
		if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
		return window.DEFAULT_AVATAR_SVG_NEUTRAL;
	};
</script>

<style>
	/* Кнопки голосования в HERO */
	.recipe-vote-btn {
		display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
		border-radius: 10px; font-size: 14px; font-weight: 600; transition: all 0.15s ease;
		cursor: pointer; border: 1px solid transparent; background: rgba(255, 255, 255, 0.15);
		color: rgba(255, 255, 255, 0.9);
	}
	.recipe-vote-btn:hover { background: rgba(255, 255, 255, 0.25); }
	.recipe-vote-btn-active-like { color: #16a34a; background: rgba(220, 252, 231, 0.9); border-color: rgba(22, 163, 74, 0.3); }
	.recipe-vote-btn-active-like:hover { background: rgba(187, 247, 160, 0.95); }
	.recipe-vote-btn-active-dislike { color: #dc2626; background: rgba(254, 226, 226, 0.9); border-color: rgba(220, 38, 38, 0.3); }
	.recipe-vote-btn-active-dislike:hover { background: rgba(254, 202, 202, 0.95); }
	.recipe-vote-icon, .vote-icon-wrapper { display: flex; align-items: center; justify-content: center; transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
	.recipe-vote-btn.anim-click .recipe-vote-icon { animation: thumb-pop 0.35s ease; }

	/* Компактные кнопки голосования */
	.vote-btn { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; transition: all 0.15s ease; cursor: pointer; border: 1px solid transparent; background: transparent; color: #9ca3af; }
	.vote-btn:hover { background: #f3f4f6; color: #6b7280; }
	.vote-btn-active-like { color: #16a34a !important; background: #dcfce7 !important; border-color: #86efac !important; }
	.vote-btn-active-dislike { color: #dc2626 !important; background: #fee2e2 !important; border-color: #fca5a5 !important; }
	.vote-btn.anim-click .vote-icon-wrapper { animation: thumb-pop 0.35s ease; }

	@keyframes thumb-pop { 0% { transform: scale(1); } 40% { transform: scale(1.3); } 70% { transform: scale(0.95); } 100% { transform: scale(1); } }
	[x-cloak] { display: none !important; }
	.emoji-container { word-break: break-word; }
	.recipe-page svg { display: block; }
	.recipe-page button svg { pointer-events: none; }
	.inline-emoji { display: inline-block; width: 1.5em; height: 1.5em; vertical-align: middle; margin: 0 2px; object-fit: contain; }
	.emoji-pick-btn { width: 32px !important; height: 32px !important; flex-shrink: 0; }
	.emoji-pick-btn img { width: 100% !important; height: 100% !important; object-fit: contain; }
</style>

<!-- Canonical + OG -->
<link rel="canonical" href="<?= e($pageUrl) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e($imageUrl) ?>">
<meta property="og:type" content="article">
<meta property="og:url" content="<?= e($pageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($imageUrl) ?>">

<!-- JSON-LD -->
<script type="application/ld+json">
	{
		"@context": "https://schema.org",
		"@type": "Recipe",
		"name": "<?= e($recipe['title']) ?>",
		"description": "<?= e($pageDescription) ?>",
		"image": "<?= e($imageUrl) ?>",
		"author": { "@type": "Person", "name": "<?= e($recipe['author_name'] ?: 'GigaChef') ?>", "url": "<?= e($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/user/' . urlencode($recipe['author_email'] ?? '')) ?>" },
		"prepTime": "PT<?= (int)$recipe['prep_time'] ?>M",
		"cookTime": "PT<?= (int)$recipe['cook_time'] ?>M",
		"totalTime": "PT<?= $total ?>M",
		"recipeYield": "<?= (int)$recipe['servings'] ?> servings",
		"recipeCategory": "<?= e($category['name'] ?? $recipe['cuisine'] ?? '') ?>",
		"recipeCuisine": "<?= e($cuisineTag['name'] ?? $recipe['cuisine'] ?? '') ?>",
		"keywords": "<?= e(implode(', ', array_map(fn($t) => $t['name'], $recipeTags))) ?>",
		"nutrition": { "@type": "NutritionInformation", "calories": "<?= (int)$recipe['calories'] ?> calories", "proteinContent": "<?= (int)$recipe['proteins'] ?> g", "fatContent": "<?= (int)$recipe['fats'] ?> g", "carbohydrateContent": "<?= (int)$recipe['carbs'] ?> g" }
	}
</script>

<!-- Мини-навбар -->
<header class="top-0 inset-x-0 z-50 backdrop-blur-md bg-white/70 shadow-sm" role="banner">
	<div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-end">
		<div class="flex items-center gap-1 sm:gap-2">
			<button type="button" 
                    onclick="window.dispatchEvent(new CustomEvent('open-add-to-cookbook', { detail: { recipeId: <?= $id ?>, recipeTitle: <?= htmlspecialchars(json_encode($recipe['title']), ENT_QUOTES, 'UTF-8') ?> } }))" 
                    class="p-2 rounded-lg hover:bg-amber-50 transition" 
                    title="В кулинарную книгу">
				<?= icon('openbook', 'gc-icon-sm') ?>
			</button>		    
			<button type="button" onclick="window.dispatchEvent(new CustomEvent('open-share-modal'))" class="p-2 rounded-lg hover:bg-amber-50 transition" title="Поделиться" aria-label="Поделиться рецептом"><?= icon('share', 'gc-icon-sm') ?></button>
			<button onclick="window.print()" class="p-2 rounded-lg hover:bg-amber-50 transition" title="Печать" aria-label="Распечатать рецепт"><?= icon('printer', 'gc-icon-sm') ?></button>
			<button onclick="saveRecipe(<?= $id ?>, this)" class="p-2 rounded-lg save-btn transition <?= $isSaved ? 'text-amber-500' : 'text-gray-400' ?>" title="<?= $isSaved ? 'Убрать из избранного' : 'Сохранить в избранное' ?>" aria-label="<?= $isSaved ? 'Убрать из избранного' : 'Сохранить в избранное' ?>" id="save-btn-<?= $id ?>">
				<svg class="w-5 h-5" fill="<?= $isSaved ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
			</button>
		</div>
	</div>
</header>

<!-- HERO -->
<section class="relative h-[50vh] min-h-[340px] w-full overflow-hidden" role="img" aria-label="Изображение блюда: <?= e($recipe['title']) ?>">
	<img src="<?= e($imageUrl) ?>" onerror="this.src='<?= $defaultImage ?>'" class="absolute inset-0 w-full h-full object-cover" alt="<?= e($recipe['title']) ?>" loading="lazy">
	<div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
	<div class="absolute bottom-0 inset-x-0 p-6 sm:p-10 max-w-5xl mx-auto">
		<div class="flex flex-wrap gap-2 mb-3">
			<?php if ($category): ?>
			<a href="<?= url('category/' . $category['slug']) ?>" class="px-3 py-1 rounded-full text-xs font-bold bg-white/90 text-gray-700 hover:bg-white hover:shadow-md transition inline-flex items-center gap-1.5">
				<?php if (!empty($category['icon_svg'])): ?><?= icon($category['icon_svg'], 'gc-icon-xs', 1) ?><?php endif; ?>
				<span><?= e($category['name']) ?></span>
			</a>
			<?php elseif ($cuisineTag): ?>
			<a href="<?= url('tag/' . $cuisineTag['slug']) ?>" class="px-3 py-1 rounded-full text-xs font-bold bg-white/90 text-gray-700 hover:bg-white hover:shadow-md transition inline-flex items-center gap-1.5">
				<?php if (!empty($cuisineTag['icon_svg'])): ?><?= icon($cuisineTag['icon_svg'], 'gc-icon-xs', 1) ?><?php endif; ?>
				<span><?= e($cuisineTag['name']) ?></span>
			</a>
			<?php endif; ?>
			<span class="px-3 py-1 rounded-full text-xs font-bold <?= difficulty_color($recipe['difficulty']) ?>"><?= e(difficulty_label($recipe['difficulty'])) ?></span>
			<?php if ($recipe['is_ai_generated']): ?>
			<span class="px-3 py-1 rounded-full text-xs font-bold bg-violet-200 text-violet-800 inline-flex items-center gap-1"><?= icon('ai', 'gc-icon-xs', 1) ?> AI</span>
			<?php endif; ?>
			<?php if ($hasAdaptation): ?>
			<span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-200 text-emerald-800 inline-flex items-center gap-1" title="Рецепт адаптирован для диабетиков"><?= icon('diabetes', 'gc-icon-xs', 1) ?> Адаптирован</span>
			<?php endif; ?>
		</div>
		<h1 class="text-3xl sm:text-5xl font-extrabold text-white leading-tight"><?= e($recipe['title']) ?></h1>
		<?php if (!empty($recipe['author_name']) || !empty($recipe['author_email'])): ?>
		<div class="flex items-center gap-3 mt-3 text-white/90">
			<?php if (!empty($recipe['author_avatar_url'])): ?>
			<img src="<?= e($recipe['author_avatar_url']) ?>" class="w-8 h-8 rounded-full object-cover ring-2 ring-white/40" alt="Аватар автора">
			<?php else: ?>
			<span class="w-8 h-8 rounded-full bg-white/20 ring-2 ring-white/40 flex items-center justify-center text-white/90"><?= default_avatar_svg($recipe['author_gender'] ?? null) ?></span>
			<?php endif; ?>
			<a href="<?= url('user/' . urlencode($recipe['author_email'])) ?>" class="text-sm font-semibold text-white/90 hover:text-white transition"><?= e($recipe['author_name'] ?: 'Пользователь') ?></a>
		</div>
		<?php endif; ?>
		<div class="flex flex-wrap gap-4 mt-4 text-white/90 text-sm font-semibold items-center">
			<span class="inline-flex items-center gap-1"><?= icon('time', 'gc-icon-sm', 1) ?> <?= format_time($total) ?></span>
			<span class="inline-flex items-center gap-1"><?= icon('dinner', 'gc-icon-sm', 1) ?> <?= (int)$recipe['servings'] ?> порций</span>
			<span class="inline-flex items-center gap-1"><?= icon('flame', 'gc-icon-sm', 1) ?> <?= (int)$recipe['calories'] ?> ккал</span>

			<div x-data="{
                myVote: <?= (int)$voteData['my_vote'] ?>,
                likeCount: <?= (int)$voteData['like_count'] ?>,
                dislikeCount: <?= (int)$voteData['dislike_count'] ?>,
                recipeId: <?= (int)$id ?>,
                isLoggedIn: <?= is_logged_in() ? 'true' : 'false' ?>,
                loginUrl: '<?= url('login') ?>',
                async vote(val, event) {
                    if (!this.isLoggedIn) { window.location.href = this.loginUrl; return; }
                    if (event && event.currentTarget) {
                        var btn = event.currentTarget;
                        btn.classList.add('anim-click');
                        setTimeout(function(){ btn.classList.remove('anim-click'); }, 350);
                    }
                    var pv = this.myVote, pl = this.likeCount, pd = this.dislikeCount;
                    if (pv === val) {
                        this.myVote = 0;
                        if (val === 1) this.likeCount = Math.max(0, pl - 1);
                        if (val === -1) this.dislikeCount = Math.max(0, pd - 1);
                    } else {
                        this.myVote = val;
                        if (val === 1) { this.likeCount = pl + 1; if (pv === -1) this.dislikeCount = Math.max(0, pd - 1); }
                        else { this.dislikeCount = pd + 1; if (pv === 1) this.likeCount = Math.max(0, pl - 1); }
                    }
                    try {
                        var fd = new FormData();
                        fd.append('recipe_id', this.recipeId);
                        fd.append('vote', val);
                        var res = await fetch('/api/recipe_votes.php', { method: 'POST', body: fd });
                        var data = await res.json();
                        if (data.ok) { this.myVote = data.my_vote; this.likeCount = data.like_count; this.dislikeCount = data.dislike_count; }
                        else { this.myVote = pv; this.likeCount = pl; this.dislikeCount = pd; if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error'); }
                    } catch (e) { this.myVote = pv; this.likeCount = pl; this.dislikeCount = pd; if (typeof toast === 'function') toast('Ошибка соединения', 'error'); }
                }
            }" class="flex gap-2 items-center">
				<button type="button" @click="vote(1, $event)" :class="myVote === 1 ? 'recipe-vote-btn-active-like' : ''" class="recipe-vote-btn" :aria-label="myVote === 1 ? 'Убрать лайк' : 'Нравится'">
					<span class="recipe-vote-icon">
						<svg class="w-5 h-5" :fill="myVote === 1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
							<path d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
						</svg>
					</span>
					<span x-text="likeCount"></span>
				</button>
				<button type="button" @click="vote(-1, $event)" :class="myVote === -1 ? 'recipe-vote-btn-active-dislike' : ''" class="recipe-vote-btn" :aria-label="myVote === -1 ? 'Убрать дизлайк' : 'Не нравится'">
					<span class="recipe-vote-icon">
						<svg class="w-5 h-5" :fill="myVote === -1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
							<path transform="scale(1,-1) translate(0,-16)" d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
						</svg>
					</span>
					<span x-text="dislikeCount"></span>
				</button>
			</div>
		</div>
	</div>
</section>

<main class="recipe-page max-w-5xl mx-auto px-4 sm:px-6 py-8" x-data="recipePage()" x-cloak>
	<p class="text-gray-600 text-lg mb-4"><?= e($recipe['description']) ?></p>

<?php if (!empty($recipeTags)): ?>
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($recipeTags as $t): ?>
        <a href="<?= url('tag/' . $t['slug']) ?>" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 transition" title="<?= e($t['tag_group']) ?>">
            <?php if (!empty($t['icon_svg'])): ?><?= icon($t['icon_svg'], 'gc-icon-xs', 1) ?><?php endif; ?>
            <span><?= e($t['name']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

	<!-- Табы -->
	<div class="flex gap-1 overflow-x-auto no-scrollbar border-b border-gray-200 mb-6" role="tablist" aria-label="Разделы рецепта">
		<?php
        $tabs = [
            'ingredients' => icon('cooking', 'gc-icon-xs', 1) . ' Ингредиенты',
            'steps'       => icon('chef', 'gc-icon-xs', 1) . ' Шаги',
            'video'       => icon('video', 'gc-icon-xs', 1) . ' Видео',
            'nutrition'   => icon('chart', 'gc-icon-xs', 1) . ' Питание',
            'reviews'     => icon('star', 'gc-icon-xs', 1) . ' Отзывы',
        ];
        foreach ($tabs as $key => $label): ?>
		<button @click="switchTab('<?= $key ?>')" :class="tab==='<?= $key ?>' ? 'border-amber-400 text-amber-700' : 'border-transparent text-gray-400'" class="px-4 py-3 border-b-2 font-semibold text-sm whitespace-nowrap transition inline-flex items-center gap-1.5" role="tab" :aria-selected="tab==='<?= $key ?>' ? 'true' : 'false'" aria-controls="panel-<?= $key ?>" :id="'tab-<?= $key ?>'" data-tab="<?= $key ?>"><?= $label ?></button>
		<?php endforeach; ?>
	</div>

	<!-- ВКЛАДКА: ИНГРЕДИЕНТЫ -->
	<div x-show="tab==='ingredients'" x-transition role="tabpanel" aria-labelledby="tab-ingredients" id="panel-ingredients" x-data="shoppingList()">
		<div class="flex items-center justify-between mb-4">
			<h2 class="text-xl font-extrabold text-gray-800">Ингредиенты</h2>
			<button @click="openShoppingList = true" class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-bold hover:bg-emerald-200 transition inline-flex items-center gap-1.5">
				<?= icon('cart', 'gc-icon-sm', 1) ?> Список покупок
			</button>
		</div>
		<ul class="bg-white rounded-2xl shadow-md divide-y divide-gray-50">
			<?php foreach ($ings as $ing):
                if (is_array($ing)) {
                    $ingName = $ing['name'] ?? $ing['title'] ?? $ing['ingredient'] ?? '';
                    $ingAmount = $ing['amount'] ?? $ing['quantity'] ?? $ing['count'] ?? $ing['value'] ?? '';
                    $ingUnit = $ing['unit'] ?? $ing['measure'] ?? $ing['measurement'] ?? $ing['type'] ?? '';
                    if (empty($ingName)) {
                        foreach ($ing as $key => $value) {
                            if (is_string($value) && !empty($value) && !in_array($key, ['id', 'recipe_id', 'sort_order'])) { $ingName = $value; break; }
                        }
                    }
                    if (empty($ingAmount)) {
                        foreach ($ing as $key => $value) {
                            if (is_numeric($value) && !in_array($key, ['id', 'recipe_id', 'sort_order'])) { $ingAmount = $value; break; }
                        }
                    }
                    $isOptional = !empty($ing['is_optional']) || !empty($ing['optional']);
                    if (empty($ingName) && !empty($ing[0]) && is_string($ing[0])) $ingName = $ing[0];
                } else {
                    $ingName = $ing; $ingAmount = ''; $ingUnit = ''; $isOptional = false;
                }
                $ingUnit = convertUnitToRussian($ingUnit);
                $ingAmount = convertAmountToRussian($ingAmount);
                $quantityStr = trim($ingAmount . ' ' . $ingUnit);
            ?>
			<li class="flex items-center gap-3 px-5 py-3.5">
				<span class="text-emerald-400"><?= icon('dot', 'gc-icon-xs', 1) ?></span>
				<span class="text-gray-700 flex-1">
					<?= e($ingName) ?>
					<?php if ($isOptional): ?><span class="text-xs text-gray-400">(по желанию)</span><?php endif; ?>
				</span>
				<?php if ($quantityStr !== '' && $quantityStr !== ' '): ?>
				<span class="ml-auto font-semibold text-gray-500 whitespace-nowrap"><?= e($quantityStr) ?></span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>

		<div x-show="openShoppingList" x-transition style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" @click.self="openShoppingList = false" @keydown.escape.window="openShoppingList = false">
			<div class="bg-white rounded-3xl shadow-xl w-full max-w-md max-h-[85vh] flex flex-col" @click.stop>
				<div class="flex items-center justify-between p-5 border-b border-gray-100">
					<h3 class="font-extrabold text-gray-800 inline-flex items-center gap-1.5"><?= icon('cart', 'gc-icon-sm', 1) ?> Список покупок</h3>
					<button @click="openShoppingList = false" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
				</div>
				<div class="flex-1 overflow-y-auto p-5 space-y-2">
					<template x-for="item in items" :key="item.id">
						<div class="flex items-center gap-2 py-1 group">
							<input type="checkbox" x-model="item.checked" class="w-5 h-5 rounded accent-emerald-500">
							<span x-show="!item.editing" @dblclick="startEdit(item)" :class="item.checked ? 'line-through text-gray-400' : 'text-gray-700'" class="flex-1 text-sm cursor-pointer" x-text="item.text"></span>
							<input x-show="item.editing" style="display:none" x-model="item.text" @keydown.enter="saveEdit(item)" @blur="saveEdit(item)" class="flex-1 text-sm px-2 py-1 rounded border border-emerald-300 outline-none">
							<button @click="remove(item.id)" class="text-gray-300 hover:text-rose-400 opacity-0 group-hover:opacity-100 transition">×</button>
						</div>
					</template>
					<p x-show="!items.length" class="text-gray-400 text-sm text-center py-4">Список пуст</p>
				</div>
				<div class="p-5 border-t border-gray-100 space-y-3">
					<div class="flex gap-2">
						<input type="text" x-model="newItem" @keydown.enter="add()" placeholder="Добавить продукт…" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 outline-none text-sm">
						<button @click="add()" class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm">+</button>
					</div>
					<button @click="copyList()" class="w-full py-2 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold hover:bg-gray-200 transition inline-flex items-center justify-center gap-1.5"><?= icon('copy', 'gc-icon-sm', 1) ?> Скопировать список</button>
					<div class="flex gap-2">
						<input type="email" x-model="email" placeholder="Ваш email" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 outline-none text-sm">
						<button @click="sendEmail()" :disabled="sending || !email" class="px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm hover:bg-emerald-200 transition disabled:opacity-60 inline-flex items-center gap-1.5">
							<span x-show="!sending" class="inline-flex items-center gap-1"><?= icon('mail', 'gc-icon-sm', 1) ?> Отправить</span>
							<span x-show="sending" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?></span>
						</button>
					</div>
					<p class="text-xs text-gray-400 text-center">Осталось купить: <span x-text="remaining"></span> из <span x-text="items.length"></span></p>
				</div>
			</div>
		</div>
		<?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
	</div>

	<!-- ВКЛАДКА: ШАГИ -->
	<div x-show="tab==='steps'" x-transition style="display:none" role="tabpanel" aria-labelledby="tab-steps" id="panel-steps">
		<h2 class="text-xl font-extrabold text-gray-800 mb-4">Приготовление</h2>
		<div class="space-y-4">
			<?php foreach ($steps as $i => $step):
                $stepMinutes = 5;
                if (is_array($step)) $stepMinutes = (int)($step['timer_minutes'] ?? $step['minutes'] ?? $step['time'] ?? 5);
                $stepMinutes = max(1, min(180, $stepMinutes));
                $stepText = is_array($step) ? ($step['instruction'] ?? $step['description'] ?? $step['text'] ?? '') : (string)$step;
                $stepTip = is_array($step) ? ($step['tip'] ?? '') : '';
            ?>
			<div class="bg-white rounded-2xl shadow-md p-5" x-data="stepTimer(<?= $stepMinutes ?>)">
				<div class="flex gap-4">
					<span class="shrink-0 w-9 h-9 rounded-full bg-amber-100 text-amber-700 font-extrabold flex items-center justify-center"><?= $i + 1 ?></span>
					<div class="flex-1 min-w-0">
						<p class="text-gray-700"><?= e($stepText) ?></p>
						<?php if (!empty($stepTip)): ?>
						<p class="text-sm text-amber-600 mt-2 bg-amber-50 rounded-lg px-3 py-2 inline-flex items-start gap-1.5"><?= icon('bulb', 'gc-icon-sm', 1) ?> <?= e($stepTip) ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-gray-50 p-3">
					<span class="font-mono text-xl font-bold text-gray-700 min-w-[64px]" x-text="display">00:00</span>
					<div class="flex gap-2">
						<button type="button" @click="toggle()" class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 font-bold text-sm hover:bg-amber-200 transition inline-flex items-center gap-1">
							<span x-show="!running" class="inline-flex items-center"><?= icon('play', 'gc-icon-sm', 1) ?></span>
							<span x-show="running" style="display:none" class="inline-flex items-center"><?= icon('pause', 'gc-icon-sm', 1) ?></span>
							<span x-text="running ? 'Пауза' : 'Старт'"></span>
						</button>
						<button type="button" @click="reset()" class="px-3 py-1.5 rounded-lg bg-gray-200 text-gray-700 font-semibold text-sm hover:bg-gray-300 transition inline-flex items-center gap-1"><?= icon('refresh', 'gc-icon-sm', 1) ?> Сброс</button>
					</div>
					<div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
						<div class="h-full bg-amber-400 transition-all" :style="`width:${progress}%`"></div>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
	</div>

    <!-- ВКЛАДКА: ВИДЕО -->
    <div x-show="tab==='video'" x-transition style="display:none" role="tabpanel" aria-labelledby="tab-video" id="panel-video" x-data="videoUpload(<?= $id ?>)">
        
        <?php if ($videoEmbedUrl): ?>
            <!-- Видео готово, показываем плеер -->
            <div class="space-y-4 mb-6">
                <div class="aspect-video rounded-2xl overflow-hidden shadow-md bg-black">
                    <iframe src="<?= e($videoEmbedUrl) ?>" class="w-full h-full" frameborder="0" allow="clipboard-write; autoplay; fullscreen" webkitallowfullscreen mozallowfullscreen allowfullscreen title="Видео: <?= e($recipe['title']) ?>"></iframe>
                </div>
            </div>
        <?php elseif ($videoStatusMessage): ?>
            <!-- Видео есть, но еще обрабатывается -->
            <div class="bg-gray-100 rounded-2xl p-6 text-center mb-6">
                <p class="text-gray-500"><?= e($videoStatusMessage) ?></p>
            </div>
        <?php endif; ?>

        <!-- Блок загрузки нового видео -->
        <?php if (is_logged_in()): ?>
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-1.5"><?= icon('upload', 'gc-icon-sm', 1) ?> Загрузить видео через VK</h3>
			<div class="space-y-4">
				<div>
					<label class="block text-sm font-semibold text-gray-600 mb-2">Название видео</label>
					<input type="text" x-model="videoTitle" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm" placeholder="Название видео">
				</div>
				<div>
					<label class="block text-sm font-semibold text-gray-600 mb-2">Описание</label>
					<textarea x-model="videoDescription" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm resize-none" placeholder="Описание видео"></textarea>
				</div>
				<div>
					<label class="block text-sm font-semibold text-gray-600 mb-2">Видеофайл</label>
					<label class="block border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-amber-300 transition">
						<input type="file" accept="video/*" class="hidden" @change="handleVideoFile($event)">
						<div class="text-gray-400">
							<div class="text-3xl mb-1"><?= icon('video', 'gc-icon-xl', 1) ?></div>
							<div class="text-sm" x-text="videoFileName || 'Выберите видеофайл'"></div>
						</div>
					</label>
				</div>
				<div x-show="uploadProgress > 0 && uploadProgress < 100" class="w-full bg-gray-200 rounded-full h-2">
					<div class="bg-amber-400 h-2 rounded-full transition-all" :style="`width: ${uploadProgress}%`"></div>
				</div>
				<button @click="uploadVideo()" :disabled="uploading || !videoFile" class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-1.5">
					<span x-show="!uploading" class="inline-flex items-center gap-1"><?= icon('upload', 'gc-icon-sm', 1) ?> Загрузить видео</span>
					<span x-show="uploading" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?> Загрузка...</span>
				</button>
				<p x-show="error" style="display:none" class="text-rose-500 text-sm" x-text="error"></p>
				<p x-show="success" style="display:none" class="text-emerald-600 text-sm" x-text="success"></p>
			</div>
		</div>

        <!-- Блок вставки видео по ссылке -->
        <div class="bg-white rounded-2xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4 inline-flex items-center gap-1.5"><?= icon('link', 'gc-icon-sm', 1) ?> Вставить видео по ссылке</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Ссылка на видео (Rutube, YouTube, Vimeo)</label>
                    <input type="url" x-model="videoUrlInput" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm" placeholder="https://rutube.ru/video/...">
                </div>
                <button @click="saveVideoUrl()" :disabled="savingUrl || !videoUrlInput" class="w-full py-3 rounded-xl bg-gradient-to-r from-blue-300 to-indigo-300 text-blue-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-1.5">
                    <span x-show="!savingUrl" class="inline-flex items-center gap-1"><?= icon('save', 'gc-icon-sm', 1) ?> Сохранить ссылку</span>
                    <span x-show="savingUrl" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?> Сохранение...</span>
                </button>
                <p x-show="urlError" style="display:none" class="text-rose-500 text-sm" x-text="urlError"></p>
                <p x-show="urlSuccess" style="display:none" class="text-emerald-600 text-sm" x-text="urlSuccess"></p>
            </div>
        </div>
		<?php endif; ?>
		<?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
	</div>

	<!-- ВКЛАДКА: ПИТАНИЕ -->
	<div x-show="tab==='nutrition'" x-transition style="display:none" role="tabpanel" aria-labelledby="tab-nutrition" id="panel-nutrition" x-data="diabetesCalculator()">
		<h2 class="text-xl font-extrabold text-gray-800 mb-4">Пищевая ценность на порцию</h2>
		<div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center mb-8">
			<div class="bg-white rounded-2xl shadow-md p-6"><canvas id="nutritionChart" height="240" role="img" aria-label="Диаграмма БЖУ"></canvas></div>
			<div class="grid grid-cols-2 gap-3">
				<div class="bg-amber-50 rounded-xl p-4 text-center"><div class="text-3xl font-extrabold text-amber-600"><?= (int)$recipe['calories'] ?></div><div class="text-sm text-gray-500">ккал</div></div>
				<div class="bg-emerald-50 rounded-xl p-4 text-center"><div class="text-3xl font-extrabold text-emerald-600"><?= (int)$recipe['proteins'] ?> г</div><div class="text-sm text-gray-500">белки</div></div>
				<div class="bg-rose-50 rounded-xl p-4 text-center"><div class="text-3xl font-extrabold text-rose-500"><?= (int)$recipe['fats'] ?> г</div><div class="text-sm text-gray-500">жиры</div></div>
				<div class="bg-violet-50 rounded-xl p-4 text-center"><div class="text-3xl font-extrabold text-violet-600"><?= (int)$recipe['carbs'] ?> г</div><div class="text-sm text-gray-500">углеводы</div></div>
			</div>
		</div>
		<?php $recipeCarbs = (int)($recipe['carbs'] ?? 0); ?>
        <?php if ($recipeCarbs > 0): ?>
            <div class="mt-8">
                <h3 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-1.5"><?= icon('diabetes', 'gc-icon-md', 1) ?> Калькулятор для диабетиков</h3>
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-bold text-gray-700 mb-3">Хлебные единицы (ХЕ)</h4>
                            <div class="bg-amber-50 rounded-xl p-4">
                                <div class="text-4xl font-extrabold text-amber-600" x-text="totalXE.toFixed(1)"></div>
                                <div class="text-sm text-gray-500 mt-1">ХЕ на порцию</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-700 mb-3">Гликемическая нагрузка (ГН)</h4>
                            <div class="bg-violet-50 rounded-xl p-4">
                                <div class="text-4xl font-extrabold" :class="totalGL < 10 ? 'text-emerald-600' : totalGL < 20 ? 'text-amber-600' : 'text-rose-600'" x-text="totalGL.toFixed(1)"></div>
                                <div class="text-sm text-gray-500 mt-1"><span x-text="glClassification"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 bg-blue-50 rounded-xl p-4">
                        <h4 class="font-bold text-blue-700 mb-2 inline-flex items-center gap-1.5"><?= icon('bulb', 'gc-icon-sm', 1) ?> Рекомендации</h4>
                        <p class="text-sm text-blue-600" x-text="recommendation"></p>
                    </div>
                    <div class="mt-4 text-xs text-gray-400 inline-flex items-start gap-1.5"><?= icon('warning', 'gc-icon-xs', 1) ?> Расчёты приблизительны и не заменяют консультацию врача. Значения указаны на одну порцию.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="mt-8">
                <h3 class="text-xl font-extrabold text-gray-800 mb-4 inline-flex items-center gap-1.5"><?= icon('diabetes', 'gc-icon-md', 1) ?> Калькулятор для диабетиков</h3>
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100 rounded-2xl p-6 flex items-start gap-4">
                    <div class="text-4xl shrink-0" aria-hidden="true"><?= icon('steak', 'gc-icon-xl', 1) ?></div>
                    <div>
                        <h4 class="font-bold text-gray-800 mb-1">В этом блюде нет углеводов</h4>
                        <p class="text-sm text-gray-600 mb-2">Основу рецепта составляют белки и жиры (мясо, рыба, яйца, масло, специи). Хлебные единицы (ХЕ) и гликемическая нагрузка (ГН) для такого блюда не рассчитываются — они просто равны нулю.</p>
                        <p class="text-xs text-emerald-700 bg-white/70 rounded-lg px-3 py-2 inline-flex items-center gap-1.5"><?= icon('check', 'gc-icon-xs', 1) ?> Блюдо подходит для диабетиков без ограничений по углеводам</p>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-400 inline-flex items-start gap-1.5"><?= icon('warning', 'gc-icon-xs', 1) ?> Расчёт носит справочный характер. При сомнениях проконсультируйтесь с врачом.</div>
            </div>
        <?php endif; ?>
        <?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
	</div>

	<!-- ВКЛАДКА: ОТЗЫВЫ -->
	<div x-show="tab==='reviews'" x-transition style="display:none" role="tabpanel" aria-labelledby="tab-reviews" id="panel-reviews" x-data="reviews(<?= $id ?>)" x-init="init()">

		<div class="flex items-center gap-4 mb-6">
			<div class="text-4xl font-extrabold text-amber-500" x-text="avg ? avg.toFixed(1) : '—'" aria-live="polite"></div>
			<div>
				<div class="text-amber-400 text-lg" x-text="stars(avg)" aria-hidden="true"></div>
				<div class="text-sm text-gray-400"><span x-text="count"></span> отзывов</div>
			</div>
		</div>

		<!-- Форма отзыва -->
		<?php if (is_logged_in()): ?>
		<div class="bg-white rounded-2xl shadow-md p-5 mb-6">
			<h3 class="font-bold text-gray-800 mb-3" x-text="editingReviewId ? 'Редактировать отзыв' : 'Оставить отзыв'"></h3>
			<div class="flex gap-1 mb-3 text-2xl" role="radiogroup" aria-label="Оценка">
				<template x-for="s in 5" :key="s">
					<button @click="form.rating = s" type="button" class="transition hover:scale-110" :class="s <= form.rating ? 'text-amber-400' : 'text-gray-200'" :aria-label="s + ' звёзд'" role="radio" :aria-checked="form.rating === s ? 'true' : 'false'">★</button>
				</template>
			</div>
			
			<!-- Общий контейнер для поля и панели эмодзи -->
			<div class="relative w-full">
				<!-- ПАНЕЛЬ ЭМОДЗИ -->
				<div x-show="showEmoji" x-transition style="display:none" 
					 class="fixed sm:absolute inset-x-0 bottom-0 sm:bottom-full sm:left-0 sm:right-0 mb-0 sm:mb-2 z-[100] bg-white sm:rounded-2xl shadow-xl border-t sm:border border-gray-200 flex flex-col overflow-hidden h-[45vh] max-h-[380px] sm:h-auto sm:max-h-[300px]">
					
					<!-- Верхняя строка с паками -->
					<div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
						<template x-for="pack in emojiPacks" :key="pack.id">
							<button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" 
									:class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" 
									class="shrink-0 w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
								<img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false">
								<span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-5 h-5 text-gray-400"><?= icon('happy') ?></span>
							</button>
						</template>
					</div>
					
					<!-- Сетка эмодзи -->
					<div class="flex-1 overflow-y-auto p-2 min-h-0">
						<div class="grid grid-cols-7 sm:grid-cols-10 gap-1.5">
							<template x-for="emoji in getActiveEmojis()" :key="emoji.id">
								<button @mousedown.prevent @click="insertEmoji(emoji)" type="button" 
										class="aspect-square rounded-lg hover:bg-amber-50 transition flex items-center justify-center overflow-hidden p-0.5">
									<img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
								</button>
							</template>
						</div>
					</div>
				</div>

				<!-- Поле ввода -->
				<div id="review-editor" x-ref="editor" contenteditable="true" @input="onEditorInput" @paste="onCommentPaste" 
					 class="w-full min-h-[48px] max-h-[160px] px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto" 
					 :data-placeholder="'Поделитесь впечатлениями…'" 
					 :class="editorEmpty ? 'before:content-[attr(data-placeholder)] before:text-gray-400 before:pointer-events-none' : ''"></div>
			</div>

			<div x-show="reviewPhotoPreview" style="display:none" class="mt-2 relative">
				<img :src="reviewPhotoPreview" class="w-full max-h-48 object-contain rounded-xl border border-gray-200">
				<button @click="removeReviewPhoto()" class="absolute top-2 right-2 bg-white/90 rounded-full p-1.5 text-rose-500 shadow-md"><?= icon('close', 'gc-icon-sm', 1) ?></button>
			</div>

			<!-- Кнопки -->
			<div class="flex items-center gap-2 mt-3">
				<!-- Мобильная кнопка (переключатель) -->
				<button type="button" @mousedown.prevent @click="toggleEmojiPanelMobile()" 
						class="sm:hidden w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center shrink-0" 
						:aria-label="showEmoji ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'" :aria-expanded="showEmoji ? 'true' : 'false'">
					<template x-if="!showEmoji"><?= icon('happy', 'gc-icon-md', 1) ?></template>
					<template x-if="showEmoji"><?= icon('keyboard', 'gc-icon-sm') ?></template>
				</button>

				<!-- Десктопная кнопка -->
				<button type="button" @mousedown.prevent @click.stop="toggleEmojiPanelMobile()" 
						class="hidden sm:flex w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 transition items-center justify-center shrink-0" 
						:class="showEmoji ? 'text-amber-500' : 'text-gray-500'">
					<?= icon('happy', 'gc-icon-md', 1) ?>
				</button>

				<button @click="$refs.reviewPhotoInput.click()" type="button" class="w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center text-xl shrink-0" title="Добавить фото"><?= icon('camera', 'gc-icon-md', 1) ?></button>
				<input type="file" x-ref="reviewPhotoInput" accept="image/*" class="hidden" @change="handleReviewPhoto($event)">
				
				<button @click="submit()" :disabled="sending || isEditorEmpty()" class="ml-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60">
					<span x-show="!sending" x-text="editingReviewId ? 'Сохранить' : 'Отправить'"></span>
					<span x-show="sending" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?> Отправка...</span>
				</button>
				<button x-show="editingReviewId" @click="cancelEdit()" type="button" class="px-3 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-bold hover:bg-gray-200">Отмена</button>
			</div>
		</div>
		<?php else: ?>
		<div class="bg-gray-50 rounded-2xl p-5 mb-6 text-center">
			<p class="text-gray-500 text-sm"><a href="<?= url('login') ?>" class="text-amber-600 font-semibold hover:underline">Войдите</a>, чтобы оставить отзыв</p>
		</div>
		<?php endif; ?>

		<!-- Список отзывов -->
		<div class="space-y-6">
			<template x-for="review in comments" :key="review.id">
				<div class="bg-white rounded-2xl shadow-sm p-5">
					<div class="flex items-center justify-between mb-3">
						<div class="flex items-center gap-3">
							<template x-if="review.avatar_url">
								<img :src="review.avatar_url" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Аватар автора отзыва">
							</template>
							<template x-if="!review.avatar_url">
								<span class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0 text-amber-600" x-html="window.getAvatarSvg(review.gender)"></span>
							</template>
							<div>
								<span class="font-bold text-gray-700 text-sm block" x-text="review.author_name || 'Гость'"></span>
								<span class="text-xs text-gray-400" x-text="window.formatDate(review.created_at)"></span>
							</div>
						</div>
						<div class="flex items-center gap-3">
							<span class="text-amber-400 text-sm" x-text="window.stars(review.rating)"></span>
							<div x-show="review.is_owner" class="flex gap-1">
								<button @click="window.editReview(review)" type="button" class="text-xs text-blue-600 hover:text-blue-800"><?= icon('pen', 'gc-icon-xs', 1) ?></button>
								<button @click="window.deleteReview(review.id)" type="button" class="text-xs text-rose-500 hover:text-rose-700"><?= icon('trash', 'gc-icon-xs', 1) ?></button>
							</div>
						</div>
					</div>

					<div class="flex gap-4">
						<div x-show="review.photo_url" class="shrink-0 w-32 h-32 sm:w-40 sm:h-40">
							<img :src="review.photo_url" class="w-full h-full object-cover rounded-xl border border-gray-100 cursor-pointer" @click="openImageModal(review.photo_url)">
						</div>
						<div class="flex-1 min-w-0">
							<div class="text-gray-600 text-sm leading-relaxed" x-html="window.renderEmojiText(review.text)"></div>
						</div>
					</div>

					<div class="flex gap-2 mt-3">
						<button type="button" @click="voteReview(review.id, 1, $event)" :class="rvMyVote(review.id) === 1 ? 'vote-btn-active-like' : ''" class="vote-btn text-gray-500" :aria-label="rvMyVote(review.id) === 1 ? 'Убрать лайк' : 'Нравится'">
							<span class="vote-icon-wrapper">
								<svg class="w-5 h-5" :fill="rvMyVote(review.id) === 1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
									<path d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
								</svg>
							</span>
							<span x-text="rvLikes(review.id)"></span>
						</button>
						<button type="button" @click="voteReview(review.id, -1, $event)" :class="rvMyVote(review.id) === -1 ? 'vote-btn-active-dislike' : ''" class="vote-btn text-gray-500" :aria-label="rvMyVote(review.id) === -1 ? 'Убрать дизлайк' : 'Не нравится'">
							<span class="vote-icon-wrapper">
								<svg class="w-5 h-5" :fill="rvMyVote(review.id) === -1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
									<path transform="scale(1,-1) translate(0,-16)" d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
								</svg>
							</span>
							<span x-text="rvDislikes(review.id)"></span>
						</button>
					</div>

					<!-- Комментарии к отзыву -->
					<div class="mt-4 border-t border-gray-100 pt-3">
						<div class="space-y-2">
							<template x-for="comment in review.comments" :key="comment.id">
								<div :style="'margin-left:' + (comment.depth * 24) + 'px'">
									<div class="bg-gray-50 rounded-lg p-3">
										<template x-if="comment.parent_id && getParentComment(review.comments, comment.parent_id)">
											<div class="mb-2 flex items-start gap-2 px-2 py-2 bg-white rounded-lg border-l-2 border-blue-300">
												<template x-if="getParentComment(review.comments, comment.parent_id).avatar_url">
													<img :src="getParentComment(review.comments, comment.parent_id).avatar_url" class="w-5 h-5 rounded-full object-cover shrink-0 mt-0.5" alt="Аватар">
												</template>
												<template x-if="!getParentComment(review.comments, comment.parent_id).avatar_url">
													<span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center shrink-0 mt-0.5 text-blue-600" x-html="window.getAvatarSvg(getParentComment(review.comments, comment.parent_id).gender)"></span>
												</template>
												<div class="flex-1 min-w-0">
													<span class="text-[10px] font-semibold text-blue-700 block mb-0.5" x-text="getParentComment(review.comments, comment.parent_id).author_name || 'Гость'"></span>
													<div class="text-xs text-gray-500 italic line-clamp-2" x-html="getParentComment(review.comments, comment.parent_id).text || ''"></div>
												</div>
											</div>
										</template>

										<div class="flex items-center gap-2 mb-1">
											<template x-if="comment.avatar_url">
												<img :src="comment.avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0" alt="Аватар комментатора">
											</template>
											<template x-if="!comment.avatar_url">
												<span class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center shrink-0 text-amber-600" x-html="window.getAvatarSvg(comment.gender)"></span>
											</template>
											<span class="text-xs font-semibold text-gray-700" x-text="comment.author_name || 'Гость'"></span>
											<span class="text-[10px] text-gray-400 ml-auto" x-text="window.formatDate(comment.created_at)"></span>
										</div>

										<p class="text-sm text-gray-600" x-html="window.renderEmojiText(comment.text)"></p>

										<div class="flex gap-2 mt-2 items-center flex-wrap">
											<button type="button" @click="voteComment(comment.id, 1, $event)" :class="cvMyVote(comment.id) === 1 ? 'vote-btn-active-like' : ''" class="vote-btn text-gray-400" style="font-size:12px; padding:4px 8px;" :aria-label="cvMyVote(comment.id) === 1 ? 'Убрать лайк' : 'Нравится'">
												<span class="vote-icon-wrapper">
													<svg class="w-4 h-4" :fill="cvMyVote(comment.id) === 1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
														<path d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
													</svg>
												</span>
												<span x-text="cvLikes(comment.id)"></span>
											</button>
											<button type="button" @click="voteComment(comment.id, -1, $event)" :class="cvMyVote(comment.id) === -1 ? 'vote-btn-active-dislike' : ''" class="vote-btn text-gray-400" style="font-size:12px; padding:4px 8px;" :aria-label="cvMyVote(comment.id) === -1 ? 'Убрать дизлайк' : 'Не нравится'">
												<span class="vote-icon-wrapper">
													<svg class="w-4 h-4" :fill="cvMyVote(comment.id) === -1 ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 16 16" stroke-width="1.3" stroke-linecap="round">
														<path transform="scale(1,-1) translate(0,-16)" d="m4.43 7.11s-1.8-0.0984-2.08 0.0146c-1.57 1.49-1.31 5.81-0.0811 7.13 0.681 0.132 1.54-0.02 2.1 0.0186 0 0 0.352-0.366 0.552-0.529 0.0625-0.0505-0.269-0.883-0.322-1.09m2.48-11.6c0.18-0.114 0.462-0.154 0.676-0.146 2.88-0.0798 2.2 3.27 1.84 4.56 1.51 0.0505 3-0.102 4.34-0.00532 1.6 0.34 1.61 2.2 0.431 3.29 0.62 1.12 0.367 2.3-0.682 3-0.202 1.43-0.944 2.24-2.48 2.51-1.22 0.0279-2.74 0.0266-3.69 0.0106-1.39 0.0332-2.82-0.767-2.96-1.94-0.287-1.24-0.125-2.92 0.00798-3.55 0.219-1.04 0.978-2 1.2-2.71 0.698-1.59 1.17-3.29 1.32-5.01" />
													</svg>
												</span>
												<span x-text="cvDislikes(comment.id)"></span>
											</button>
											<button @click="startReply(comment, review.id)" type="button" class="text-xs text-blue-500 hover:text-blue-700">Ответить</button>
											<template x-if="comment.is_owner">
												<div class="flex gap-2">
													<button @click="startEditComment(comment)" type="button" class="text-xs text-gray-500 hover:text-gray-700"><?= icon('pen', 'gc-icon-xs', 1) ?></button>
													<button @click="window.deleteComment(comment.id)" type="button" class="text-xs text-rose-400 hover:text-rose-600"><?= icon('trash', 'gc-icon-xs', 1) ?></button>
												</div>
											</template>
										</div>

										<!-- Редактирование комментария -->
										<div x-show="editingCommentId === comment.id" style="display:none" class="mt-2 relative w-full">
											<!-- ПАНЕЛЬ ЭМОДЗИ -->
											<div x-show="showEditEmojiFor === comment.id" x-transition style="display:none" 
												 class="fixed sm:absolute inset-x-0 bottom-0 sm:bottom-full sm:left-0 sm:right-0 mb-0 sm:mb-2 z-[100] bg-white sm:rounded-2xl shadow-xl border-t sm:border border-gray-200 flex flex-col overflow-hidden h-[45vh] max-h-[380px] sm:h-auto sm:max-h-[300px]">
												<div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
													<template x-for="pack in emojiPacks" :key="pack.id">
														<button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" :class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" class="shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
															<img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false"><span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-4 h-4 text-gray-400"><?= icon('happy') ?></span>
														</button>
													</template>
												</div>
												<div class="flex-1 overflow-y-auto p-2 min-h-0" style="max-height: 200px;">
													<div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
														<template x-for="emoji in getActiveEmojis()" :key="emoji.id">
															<button @mousedown.prevent @click="insertEmojiToEdit(emoji, comment.id)" type="button" class="aspect-square rounded-lg hover:bg-amber-50 transition flex items-center justify-center overflow-hidden p-0.5">
																<img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
															</button>
														</template>
													</div>
												</div>
											</div>

											<!-- Поле ввода -->
											<div :id="'edit-editor-' + comment.id" contenteditable="true" @input="editedCommentText = $event.target.innerHTML" @paste="onCommentPaste" class="w-full min-h-[60px] px-3 py-2 rounded-lg border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto"></div>

											<!-- Кнопки -->
											<div class="flex items-center gap-2 mt-2">
												<!-- Мобильная кнопка переключения -->
												<button type="button" @mousedown.prevent @click="toggleEditEmoji(comment.id)"
														class="sm:hidden w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center shrink-0"
														:aria-label="showEditEmojiFor === comment.id ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'"
														:aria-expanded="showEditEmojiFor === comment.id ? 'true' : 'false'">
													<template x-if="showEditEmojiFor !== comment.id"><?= icon('happy', 'gc-icon-sm', 1) ?></template>
													<template x-if="showEditEmojiFor === comment.id"><?= icon('keyboard', 'gc-icon-sm') ?></template>
												</button>

												<!-- Десктопная кнопка переключения -->
												<button type="button" @mousedown.prevent @click="toggleEditEmoji(comment.id)"
														class="hidden sm:flex w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition items-center justify-center shrink-0"
														:class="showEditEmojiFor === comment.id ? 'text-amber-500' : 'text-gray-400'">
													<?= icon('happy', 'gc-icon-sm', 1) ?>
												</button>

												<button @click="updateComment(comment.id)" :disabled="!editedCommentText.trim() || editedCommentText.trim() === '<br>'" class="ml-auto px-3 py-1 bg-blue-500 text-white rounded-lg text-xs disabled:opacity-50">Сохранить</button>
												<button @click="cancelEditComment()" type="button" class="px-3 py-1 bg-gray-200 text-gray-600 rounded-lg text-xs">Отмена</button>
											</div>
										</div>
									</div>
								</div>
							</template>
						</div>

						<!-- Форма нового комментария -->
						<div class="mt-3 relative w-full">
							<!-- ПАНЕЛЬ ЭМОДЗИ -->
							<div x-show="showCommentEmojiFor === review.id" x-transition style="display:none" 
								 class="fixed sm:absolute inset-x-0 bottom-0 sm:bottom-full sm:left-0 sm:right-0 mb-0 sm:mb-2 z-[100] bg-white sm:rounded-2xl shadow-xl border-t sm:border border-gray-200 flex flex-col overflow-hidden h-[45vh] max-h-[380px] sm:h-auto sm:max-h-[300px]">
								
								<div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
									<template x-for="pack in emojiPacks" :key="pack.id">
										<button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" :class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" class="shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
											<img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false"><span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-4 h-4 text-gray-400"><?= icon('happy') ?></span>
										</button>
									</template>
								</div>
								
								<div class="flex-1 overflow-y-auto p-2 min-h-0" style="max-height: 200px;">
									<div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
										<template x-for="emoji in getActiveEmojis()" :key="emoji.id">
											<button @mousedown.prevent @click="insertEmojiToComment(emoji, review.id)" type="button" class="aspect-square rounded-lg hover:bg-amber-50 transition flex items-center justify-center overflow-hidden p-0.5">
												<img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
											</button>
										</template>
									</div>
								</div>
							</div>

							<!-- Поле ввода -->
							<div :id="'comment-editor-' + review.id" contenteditable="true" 
								 @input="onCommentInput($event, review.id)" @paste="onCommentPaste" 
								 class="w-full min-h-[48px] max-h-[160px] px-3 py-2 rounded-lg border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto" 
								 data-placeholder="Написать комментарий…" 
								 :class="!hasCommentText(review.id) ? 'before:content-[attr(data-placeholder)] before:text-gray-400 before:pointer-events-none' : ''"></div>
							
							<!-- Кнопки -->
							<div class="flex items-center gap-2 mt-2">
								<!-- Мобильная кнопка -->
								<button @mousedown.prevent @click="toggleCommentEmoji(review.id)" type="button" 
										class="sm:hidden w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition flex items-center justify-center text-xl" 
										:class="showCommentEmojiFor === review.id ? 'text-amber-500' : 'text-gray-400'">
									<template x-if="showCommentEmojiFor !== review.id"><?= icon('happy', 'gc-icon-md', 1) ?></template>
									<template x-if="showCommentEmojiFor === review.id"><?= icon('keyboard', 'gc-icon-sm') ?></template>
								</button>

								<!-- Десктопная кнопка -->
								<button @mousedown.prevent @click="toggleCommentEmoji(review.id)" type="button" 
										class="hidden sm:flex w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition items-center justify-center text-xl" 
										:class="showCommentEmojiFor === review.id ? 'text-amber-500' : 'text-gray-400'">
									<?= icon('happy', 'gc-icon-md', 1) ?>
								</button>

								<button type="button" :disabled="!hasCommentText(review.id)" @click="addComment(review)" class="ml-auto px-4 py-1.5 bg-amber-100 text-amber-700 rounded-lg text-sm font-bold disabled:opacity-50">Отправить</button>
							</div>
						</div>

						<!-- Форма ответа на комментарий -->
						<template x-if="replyingTo && replyingReviewId === review.id">
                            <div class="mt-3 pl-6 relative w-full">
                                <div class="bg-blue-50 rounded-lg p-3 relative">
                                    <template x-if="getQuotedComment(review.comments, replyingTo)">
                                        <div class="mb-2 flex items-start gap-2.5 px-3 py-2.5 bg-white/70 rounded-lg border-l-2 border-blue-300">
                                            <template x-if="getQuotedComment(review.comments, replyingTo)?.avatar_url">
                                                <img :src="getQuotedComment(review.comments, replyingTo).avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0 mt-0.5" alt="Аватар">
                                            </template>
                                            <template x-if="!getQuotedComment(review.comments, replyingTo)?.avatar_url">
                                                <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center shrink-0 mt-0.5 text-blue-600" x-html="window.getAvatarSvg(getQuotedComment(review.comments, replyingTo)?.gender)"></span>
                                            </template>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-xs font-semibold text-blue-700 block mb-0.5" x-text="getQuotedComment(review.comments, replyingTo)?.author_name || 'Гость'"></span>
                                                <div class="text-xs text-gray-600 italic line-clamp-3" x-html="getQuotedComment(review.comments, replyingTo)?.text || ''"></div>
                                            </div>
                                        </div>
                                    </template>
            
									<!-- ПАНЕЛЬ ЭМОДЗИ -->
									<div x-show="showReplyEmojiFor === review.id" x-transition style="display:none" 
										 class="fixed sm:absolute inset-x-0 bottom-0 sm:bottom-full sm:left-0 sm:right-0 mb-0 sm:mb-2 z-[100] bg-white sm:rounded-2xl shadow-xl border-t sm:border border-gray-200 flex flex-col overflow-hidden h-[45vh] max-h-[380px] sm:h-auto sm:max-h-[300px]">
										<div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0 no-scrollbar">
											<template x-for="pack in emojiPacks" :key="pack.id">
												<button @mousedown.prevent @click="activeEmojiPack = pack.id" type="button" :class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" class="shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 transition">
													<img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" draggable="false"><span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-4 h-4 text-gray-400"><?= icon('happy') ?></span>
												</button>
											</template>
										</div>
										<div class="flex-1 overflow-y-auto p-2 min-h-0" style="max-height: 200px;">
											<div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
												<template x-for="emoji in getActiveEmojis()" :key="emoji.id">
													<button @mousedown.prevent @click="insertEmojiToReply(emoji, review.id)" type="button" class="aspect-square rounded-lg hover:bg-amber-50 transition flex items-center justify-center overflow-hidden p-0.5">
														<img :src="emoji.image_url" class="w-full h-full object-contain pointer-events-none" :alt="emoji.code" draggable="false">
													</button>
												</template>
											</div>
										</div>
									</div>

									<!-- Поле ввода -->
									<div :id="'reply-editor-' + review.id" contenteditable="true" 
										 @input="replyText = $event.target.innerHTML" @paste="onCommentPaste" 
										 class="w-full min-h-[48px] max-h-[160px] px-3 py-2 rounded-lg border border-blue-200 outline-none focus:border-blue-300 text-sm overflow-y-auto" 
										 data-placeholder="Ваш ответ…" 
										 :class="!replyText.trim() || replyText.trim() === '<br>' ? 'before:content-[attr(data-placeholder)] before:text-gray-400 before:pointer-events-none' : ''"></div>
									
									<!-- Кнопки -->
									<div class="flex items-center gap-2 mt-2">
										<!-- Мобильная кнопка -->
										<button @mousedown.prevent @click="toggleReplyEmoji(review.id)" type="button" 
												class="sm:hidden w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 transition flex items-center justify-center text-xl" 
												:class="showReplyEmojiFor === review.id ? 'text-amber-500' : 'text-gray-400'">
											<template x-if="showReplyEmojiFor !== review.id"><?= icon('happy', 'gc-icon-md', 1) ?></template>
											<template x-if="showReplyEmojiFor === review.id"><?= icon('keyboard', 'gc-icon-sm') ?></template>
										</button>

										<!-- Десктопная кнопка -->
										<button @mousedown.prevent @click="toggleReplyEmoji(review.id)" type="button" 
												class="hidden sm:flex w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 transition items-center justify-center text-xl" 
												:class="showReplyEmojiFor === review.id ? 'text-amber-500' : 'text-gray-400'">
											<?= icon('happy', 'gc-icon-md', 1) ?>
										</button>

										<button @click="addReply(review.id, replyingTo)" :disabled="!replyText.trim() || replyText.trim() === '<br>'" class="ml-auto px-3 py-1 bg-blue-500 text-white rounded-lg text-xs disabled:opacity-50">Ответить</button>
										<button @click="cancelReply()" type="button" class="px-3 py-1 bg-gray-200 text-gray-600 rounded-lg text-xs">Отмена</button>
									</div>
								</div>
							</div>
						</template>
					</div>
				</div>
			</template>

			<p x-show="!comments.length" style="display:none" class="text-gray-400 text-center py-6">Пока нет отзывов. Будьте первым!</p>
		</div>

		<!-- Модальное окно изображения -->
		<div x-show="showImageModal" x-transition.opacity style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4" @click.self="closeImageModal()" @keydown.escape.window="closeImageModal()">
			<div class="relative max-w-5xl max-h-[90vh] w-full">
				<button @click="closeImageModal()" class="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300 transition" aria-label="Закрыть"><?= icon('close', 'gc-icon-lg', 1) ?></button>
				<img :src="modalImageUrl" class="w-full h-auto max-h-[85vh] object-contain rounded-xl shadow-2xl" alt="Просмотр изображения">
				<a :href="modalImageUrl" target="_blank" class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/90 text-gray-700 rounded-lg text-sm font-semibold hover:bg-white">Открыть в новой вкладке</a>
			</div>
		</div>
		<?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
	</div>

	<!-- Похожие рецепты -->
	<?php if (!empty($similarRecipes)): ?>
	<section class="mt-10 border-t border-gray-200 pt-8" aria-label="Похожие рецепты">
		<h2 class="text-2xl font-extrabold text-gray-800 mb-6 inline-flex items-center gap-1.5">
			<?= icon('openbook', 'gc-icon-lg', 1) ?> Похожие рецепты
		</h2>
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
			<?php foreach ($similarRecipes as $similar):
    $similarUrl = !empty($similar['slug']) ? url('recipe/' . $similar['slug']) : url('recipe?id=' . (int)$similar['id']);
    $rawImageUrl = $similar['image_url'] ?? '';
    if (empty($rawImageUrl) || $rawImageUrl === 'null') { $similarImage = $defaultImage; $hasImage = false; }
    elseif (str_starts_with($rawImageUrl, '/uploads/avatar/')) { $similarImage = $rawImageUrl; $hasImage = true; }
    elseif (str_starts_with($rawImageUrl, '/uploads/static/') && $rawImageUrl !== $defaultImage) { $similarImage = $defaultImage; $hasImage = false; }
    elseif (str_starts_with($rawImageUrl, 'http')) { $similarImage = $rawImageUrl; $hasImage = true; }
    else { $similarImage = $defaultImage; $hasImage = false; }
    $badgeLabel = ''; $badgeUrl = ''; $badgeIcon = null;
    if (!empty($similar['category_slug']) && !empty($similar['category_name'])) {
        $badgeLabel = $similar['category_name']; $badgeUrl = url('category/' . $similar['category_slug']); $badgeIcon = $similar['category_icon'] ?? null;
    } else {
        foreach (($similar['tags_list'] ?? []) as $t) {
            if (($t['tag_group'] ?? '') === 'cuisine') { $badgeLabel = $t['name']; $badgeUrl = url('tag/' . $t['slug']); $badgeIcon = $t['icon_svg'] ?? null; break; }
        }
        if ($badgeLabel === '' && !empty($similar['cuisine'])) { $badgeLabel = $similar['cuisine']; }
    }
?>
<article class="relative bg-white rounded-2xl shadow-md hover:shadow-lg transition group flex flex-col">
    <a href="<?= $similarUrl ?>" class="block aspect-[4/3] overflow-hidden relative bg-gradient-to-br from-amber-100 to-orange-100 shrink-0 rounded-t-2xl">
        <?php if ($hasImage): ?>
            <img src="<?= e($similarImage) ?>" onerror="this.onerror=null; this.src='<?= e($defaultImage) ?>';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="<?= e($similar['title']) ?>" loading="lazy">
        <?php else: ?>
            <img src="<?= e($defaultImage) ?>" class="w-full h-full object-cover" alt="<?= e($similar['title']) ?>" loading="lazy">
        <?php endif; ?>
    </a>
    <?php if ($badgeLabel !== ''): ?>
        <?php if ($badgeUrl !== ''): ?>
            <span role="link" tabindex="0" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='<?= e($badgeUrl) ?>';" onkeydown="if(event.key==='Enter'){event.preventDefault();window.location.href='<?= e($badgeUrl) ?>';}" class="absolute top-2 left-2 z-10 px-2 py-0.5 rounded-lg text-xs font-bold bg-white/90 text-gray-700 hover:bg-white hover:shadow-md transition cursor-pointer inline-flex items-center gap-1">
                <?php if (!empty($badgeIcon)): ?><?= icon($badgeIcon, 'gc-icon-xs', 0.9) ?><?php endif; ?>
                <span><?= e($badgeLabel) ?></span>
            </span>
        <?php else: ?>
            <span class="absolute top-2 left-2 z-10 px-2 py-0.5 rounded-lg text-xs font-bold bg-white/90 text-gray-700"><?= e($badgeLabel) ?></span>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($similar['difficulty'])): ?>
        <span class="absolute bottom-2 right-2 z-10 px-2 py-0.5 rounded-lg text-xs font-bold <?= e(difficulty_color($similar['difficulty'])) ?>"><?= e(difficulty_label($similar['difficulty'])) ?></span>
    <?php endif; ?>
    <a href="<?= $similarUrl ?>" class="p-3 flex flex-col flex-1 group-hover:text-amber-600 transition rounded-b-2xl">
        <h3 class="font-bold text-gray-800 text-sm leading-snug clamp-2 mb-2"><?= e($similar['title']) ?></h3>
        <div class="flex gap-3 text-xs text-gray-400 mt-auto">
            <span class="flex items-center gap-1"><?= icon('time', 'gc-icon-xs', 1) ?> <?= (int)($similar['prep_time'] ?? 0) + (int)($similar['cook_time'] ?? 0) ?> мин</span>
            <span class="flex items-center gap-1"><?= icon('flame', 'gc-icon-xs', 1) ?> <?= (int)($similar['calories'] ?? 0) ?> ккал</span>
        </div>
    </a>
</article>
<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<!-- Адаптация для диабетиков -->
	<section class="mt-8" x-data="diabetesAdapter(<?= $id ?>)" aria-label="Адаптация для диабетиков">
		<div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl shadow-md p-6">
			<div class="flex items-center justify-between mb-4 flex-wrap gap-3">
				<h2 class="text-xl font-extrabold text-gray-800 inline-flex items-center gap-1.5"><?= icon('diabetes', 'gc-icon-md', 1) ?> Адаптация для диабетиков</h2>
				<?php if (is_admin() && $hasAdaptation): ?>
				<button type="button" @click="resetAllAdaptations()" :disabled="loading" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 transition disabled:opacity-60 inline-flex items-center gap-1"><?= icon('trash', 'gc-icon-xs', 1) ?> Сбросить все</button>
				<?php endif; ?>
			</div>
			<?php if ($hasAdaptation && $selectedAdaptation): ?>
			<div class="bg-white rounded-xl p-4 mb-4">
				<div class="flex items-center gap-2 mb-3">
					<span class="px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold">Единая адаптация</span>
					<span class="text-xs text-gray-400"><?= date('d.m.Y H:i', strtotime($selectedAdaptation['created_at'])) ?></span>
				</div>
				<h3 class="font-bold text-gray-800 mb-3">Адаптированные ингредиенты:</h3>
				<ul class="space-y-2 mb-4">
					<?php foreach ($selectedAdaptation['content']['ingredients'] as $ing): ?>
					<li class="flex justify-between text-sm">
						<span class="text-gray-700"><?= e($ing['name'] ?? '') ?><?php if (!empty($ing['replacement_note'])): ?><span class="text-xs text-emerald-600 ml-1">(<?= e($ing['replacement_note']) ?>)</span><?php endif; ?></span>
						<span class="font-semibold text-gray-500"><?= e(($ing['amount'] ?? '') . ' ' . ($ing['unit'] ?? '')) ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<div class="mt-4" x-data="{ activeType: '<?= $selectedAdaptation['diabetes_type'] ?>' }">
					<h4 class="font-bold text-gray-700 text-sm mb-3 inline-flex items-center gap-1.5"><?= icon('document', 'gc-icon-sm', 1) ?> Рекомендации по типу диабета:</h4>
					<div class="flex flex-wrap gap-2 mb-3">
						<?php foreach ($diabetesTypes as $typeKey => $typeLabel): ?>
						<button type="button" @click="activeType = '<?= $typeKey ?>'" :class="activeType === '<?= $typeKey ?>' ? 'diabetes-tab-active' : 'diabetes-tab'" class="px-3 py-1.5 rounded-xl text-xs font-bold transition"><?= $typeLabel ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ($diabetesTypes as $typeKey => $typeLabel): ?>
					<div x-show="activeType === '<?= $typeKey ?>'" x-cloak>
						<?php $recs = $selectedAdaptation['content']['recommendations'][$typeKey] ?? null; if ($recs): ?>
						<div class="bg-blue-50 rounded-xl p-4">
							<h5 class="font-bold text-blue-700 text-sm mb-2"><?= e($recs['title'] ?? $typeLabel) ?></h5>
							<ul class="space-y-1.5">
								<?php foreach ($recs['items'] as $item): ?>
								<li class="text-sm text-blue-600 flex items-start gap-2"><span><?= icon('dot', 'gc-icon-xs', 1) ?></span><span><?= e($item) ?></span></li>
								<?php endforeach; ?>
							</ul>
							<?php if (!empty($recs['warning'])): ?>
							<div class="mt-3 bg-amber-50 rounded-lg p-3"><p class="text-xs text-amber-600 inline-flex items-start gap-1.5"><?= icon('warning', 'gc-icon-xs', 1) ?> <?= e($recs['warning']) ?></p></div>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php if (!empty($selectedAdaptation['content']['general_notes'])): ?>
				<div class="mt-4 bg-amber-50 rounded-lg p-3">
					<h4 class="font-bold text-amber-700 mb-2 inline-flex items-center gap-1.5"><?= icon('bulb', 'gc-icon-sm', 1) ?> Общие примечания:</h4>
					<p class="text-sm text-amber-600 whitespace-pre-line"><?= e($selectedAdaptation['content']['general_notes']) ?></p>
				</div>
				<?php endif; ?>
			</div>
			<?php else: ?>
			<p class="text-gray-500 text-sm mb-4">Создайте адаптацию, чтобы получить рекомендации для разных типов диабета.</p>
			<?php endif; ?>
			<div class="mt-4">
				<?php if (!$hasAdaptation): ?>
				<button type="button" @click="createAdaptation()" :disabled="loading" class="px-5 py-2.5 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-600 transition disabled:opacity-60">
					<span x-show="!loading"><?= icon('sparkle', 'gc-icon-sm', 1) ?> Создать адаптацию</span><span x-show="loading" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?> Создание...</span>
				</button>
				<?php else: ?>
				<button type="button" @click="createAdaptation(true)" :disabled="loading" class="px-5 py-2.5 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm hover:bg-emerald-200 transition disabled:opacity-60">
					<span x-show="!loading"><?= icon('refresh', 'gc-icon-sm', 1) ?> Пересоздать адаптацию</span><span x-show="loading" style="display:none"><?= icon('hourglass', 'gc-icon-sm', 1) ?> Обновление...</span>
				</button>
				<?php endif; ?>
				<p x-show="error" style="display:none" class="text-rose-500 text-sm mt-2" x-text="error" role="alert"></p>
				<p x-show="success" style="display:none" class="text-emerald-600 text-sm mt-2" x-text="success" role="status"></p>
			</div>
			<div class="mt-4 text-xs text-gray-400 inline-flex items-start gap-1.5"><?= icon('warning', 'gc-icon-xs', 1) ?> Адаптация не является медицинской рекомендацией. Проконсультируйтесь с врачом перед изменением рациона.</div>
		</div>
	</section>
	<?php $adPlacement = 'recipe_ingredients_bottom'; include __DIR__ . '/../components/ad-banner.php'; ?>
</main>
<!-- ✅ Модальное окно "Добавить в книгу" -->
<div x-data="addToCookbook()" 
     @open-add-to-cookbook.window="open($event.detail)" 
     x-show="isOpen" 
     x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="isOpen = false">
    
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden" @click.away="isOpen = false">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800 text-lg">Добавить в книгу</h3>
            <button @click="isOpen = false" class="text-gray-400 hover:text-gray-600"><?= icon('close', 'gc-icon-sm') ?></button>
        </div>

        <div class="p-5 max-h-[60vh] overflow-y-auto space-y-3">
            <p class="text-sm text-gray-500 mb-2">Выберите существующую книгу или создайте новую:</p>
            
            <!-- Список книг -->
            <template x-for="book in books" :key="book.id">
                <button @click="addToBook(book.id)" :disabled="loading"
                        class="w-full flex items-center justify-between p-3 rounded-xl border border-gray-200 hover:border-amber-400 hover:bg-amber-50 transition text-left group">
                    <div>
                        <div class="font-semibold text-gray-800 text-sm" x-text="book.title"></div>
                        <div class="text-xs text-gray-400" 
                            x-text="book.recipe_count + ' ' + 
                                 (book.recipe_count % 10 === 1 && book.recipe_count % 100 !== 11 ? 'рецепт' : 
                                 (book.recipe_count % 10 >= 2 && book.recipe_count % 10 <= 4 && (book.recipe_count % 100 < 10 || book.recipe_count % 100 >= 20) ? 'рецепта' : 'рецептов'))">
                        </div>
                    </div>
                    <div class="text-amber-500 opacity-0 group-hover:opacity-100 transition">
                        <?= icon('plus', 'gc-icon-sm') ?>
                    </div>
                </button>
            </template>

            <!-- Создание новой книги (как в плеере) -->
            <div class="pt-3 border-t border-gray-100">
                <div x-show="!creatingNew" @click="creatingNew = true" class="cursor-pointer flex items-center gap-2 text-amber-600 font-semibold text-sm hover:underline">
                    <?= icon('plus', 'gc-icon-sm') ?> Создать новую книгу
                </div>
                
                <div x-show="creatingNew" class="space-y-2 mt-2" style="display:none">
                    <input type="text" x-model="newBookTitle" placeholder="Название книги..." class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-amber-400">
                    <div class="flex gap-2">
                        <button @click="createAndAdd()" :disabled="loading || !newBookTitle.trim()" class="flex-1 py-2 rounded-xl bg-amber-400 text-white font-bold text-sm disabled:opacity-50">
                            Создать и добавить
                        </button>
                        <button @click="creatingNew = false" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm">Отмена</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ Логика (Alpine.js) -->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('addToCookbook', () => ({
            isOpen: false,
            books: [],
            loading: false,
            creatingNew: false,
            newBookTitle: '',
            recipeId: 0,
            recipeTitle: '',
            toastMsg: '',

            async open(detail) {
                this.recipeId = detail.recipeId;
                this.recipeTitle = detail.recipeTitle;
                this.isOpen = true;
                this.creatingNew = false;
                this.newBookTitle = '';
                await this.loadBooks();
            },

            async loadBooks() {
                try {
                    const res = await fetch('/api/cookbook_get.php?list=1'); // API для списка книг
                    const data = await res.json();
                    if (data.ok && data.books) {
                        this.books = data.books;
                    }
                } catch (e) {
                    console.error('Ошибка загрузки книг', e);
                }
            },

            async addToBook(bookId) {
                this.loading = true;
                try {
                    const res = await fetch('/api/cookbook_add_recipes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            book_id: bookId, 
                            recipe_id: this.recipeId,
                            recipe_title: this.recipeTitle 
                        })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        if (typeof toast === 'function') toast('Рецепт добавлен в книгу', 'success');
                        this.isOpen = false;
                    } else {
                        alert(data.error || 'Ошибка добавления');
                    }
                } catch (e) {
                    alert('Ошибка соединения');
                } finally {
                    this.loading = false;
                }
            },

            async createAndAdd() {
                this.loading = true;
                try {
                    // 1. Создаем книгу (API должен вернуть id)
                    const createRes = await fetch('/api/cookbook_manage.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'create', title: this.newBookTitle.trim() })
                    });
                    const createData = await createRes.json();
                    
                    if (createData.ok && createData.id) {
                        // 2. Сразу добавляем туда рецепт
                        await this.addToBook(createData.id);
                    } else {
                        alert(createData.error || 'Не удалось создать книгу');
                    }
                } catch (e) {
                    alert('Ошибка при создании');
                } finally {
                    this.loading = false;
                }
            }
        }));
    });
</script>
<script>
	window.__CSRF_TOKEN__        = '<?= csrf_token() ?>';
    window.__RECIPE_ID__         = <?= (int)$id ?>;
    window.__RECIPE_TITLE__      = <?= json_encode($recipe['title']) ?>;
    window.__RECIPE_CARBS__      = <?= (int)($recipe['carbs'] ?? 0) ?>;
    window.__RECIPE_SERVINGS__   = <?= max(1, (int)($recipe['servings'] ?? 1)) ?>;
    window.__RECIPE_PROTEINS__   = <?= (int)($recipe['proteins'] ?? 0) ?>;
    window.__RECIPE_FATS__       = <?= (int)($recipe['fats'] ?? 0) ?>;
    window.__RECIPE_LIKES_COUNT__= <?= (int)($recipe['likes_count'] ?? 0) ?>;
    window.__RECIPE_VOTE_DATA__  = <?= json_encode($voteData, JSON_UNESCAPED_UNICODE) ?>;
    window.__IS_LOGGED_IN__      = <?= is_logged_in() ? 'true' : 'false' ?>;

	window.__RECIPE_INGREDIENTS__ = <?= json_encode(array_map(function($ing) {
		if (is_array($ing)) {
			if (isset($ing['name'])) {
				$ing['name'] = preg_replace('/(\d+)\s*(g|gr|gram|grams)\b/i', '$1 г', $ing['name']);
				$ing['name'] = preg_replace('/(\d+)\s*(kg|kilogram|kilograms)\b/i', '$1 кг', $ing['name']);
				$ing['name'] = preg_replace('/(\d+)\s*(ml|milliliter|milliliters)\b/i', '$1 мл', $ing['name']);
				$ing['name'] = preg_replace('/(\d+)\s*(l|liter|liters)\b/i', '$1 л', $ing['name']);
			}
			if (isset($ing['amount'])) {
				$ing['amount'] = preg_replace('/(\d+)\s*(g|gr|gram|grams)\b/i', '$1 г', $ing['amount']);
				$ing['amount'] = preg_replace('/(\d+)\s*(kg|kilogram|kilograms)\b/i', '$1 кг', $ing['amount']);
				$ing['amount'] = preg_replace('/(\d+)\s*(ml|milliliter|milliliters)\b/i', '$1 мл', $ing['amount']);
				$ing['amount'] = preg_replace('/(\d+)\s*(l|liter|liters)\b/i', '$1 л', $ing['amount']);
				$ing['amount'] = preg_replace('/(\d+)\s*(tbsp|tablespoon|tablespoons)\b/i', '$1 ст.л.', $ing['amount']);
				$ing['amount'] = preg_replace('/(\d+)\s*(tsp|teaspoon|teaspoons)\b/i', '$1 ч.л.', $ing['amount']);
			}
			if (isset($ing['unit'])) {
				$unitMap = [
					'g' => 'г', 'gr' => 'г', 'gram' => 'г', 'grams' => 'г',
					'kg' => 'кг', 'kilogram' => 'кг', 'kilograms' => 'кг',
					'ml' => 'мл', 'milliliter' => 'мл', 'milliliters' => 'мл',
					'l' => 'л', 'liter' => 'л', 'liters' => 'л',
					'tbsp' => 'ст.л.', 'tablespoon' => 'ст.л.', 'tablespoons' => 'ст.л.',
					'tsp' => 'ч.л.', 'teaspoon' => 'ч.л.', 'teaspoons' => 'ч.л.',
					'pcs' => 'шт', 'pc' => 'шт', 'piece' => 'шт', 'pieces' => 'шт',
					'cup' => 'стакан', 'cups' => 'стакана',
					'pinch' => 'щепотка', 'clove' => 'зубчик', 'cloves' => 'зубчика',
					'slice' => 'ломтик', 'slices' => 'ломтика',
					'stalk' => 'стебель', 'stalks' => 'стебля',
					'stick' => 'палочка', 'sticks' => 'палочки',
				];
				$lowerUnit = mb_strtolower(trim($ing['unit']));
				if (isset($unitMap[$lowerUnit])) $ing['unit'] = $unitMap[$lowerUnit];
			}
		}
		return $ing;
	}, $ings), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> ;

	window.__EMOJI_PACKS__ = <?= json_encode($emojiPacks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?> ;
	window.__EMOJIS_BY_PACK__ = <?= json_encode($emojisByPack, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?> ;
</script>

<!-- Скрипты -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script src="<?= url('assets/js/api-client.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/ai/nutrition-chart.js') ?>"></script>
<script src="<?= url('assets/js/ai/ingredient-substitute.js') ?>"></script>
<script src="<?= url('assets/js/ai/recipe-assistant.js') ?>"></script>

<?php require __DIR__ . '/../components/share-block.php'; ?>
<script src="<?= url('assets/js/recipe/recipe-detail.js') ?>?v=9"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>