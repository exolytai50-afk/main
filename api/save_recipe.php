<?php
/**
 * POST /api/save_recipe.php — сохранение рецепта с защитой от дублирования.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/recipe_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}
if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход (401)'], 401);
}

$in = json_input();
$title = trim((string)($in['title'] ?? ''));
if ($title === '') {
    json_response(['error' => 'Не указано название'], 400);
}

$user = current_user();
$userId = (int)$user['id'];
$ingredients = (array)($in['ingredients'] ?? []);

// ============================================================
//  ПРОВЕРКА НА ДУБЛИКАТ
// ============================================================
$duplicate = check_recipe_duplicate($title, $ingredients, $userId, 0.85);

if ($duplicate) {
    log_recipe_duplicate_attempt('save_recipe', $userId, $title, $duplicate);
    
    json_response([
        'ok'           => false,
        'error'        => 'Рецепт уже существует: «' . $duplicate['title'] . '» (совпадение ' . $duplicate['similarity'] . '%)',
        'duplicate'    => $duplicate,
        'action'       => 'open_existing',
        'redirect_url' => '/recipe/' . ($duplicate['slug'] ?: $duplicate['id']),
    ], 409);
}

// ============================================================
//  СОХРАНЕНИЕ
// ============================================================
$allowedCuisine = ['Русская','Итальянская','Японская','Мексиканская','Индийская','Тайская',
    'Французская','Грузинская','Средиземноморская','Американская','Китайская','Корейская','Другая'];
// Принимаем любой вариант (латиница/кириллица/разный род) — нормализуем к канону.
// 'Легко' / 'Средне' / 'Сложно' — тоже примутся и превратятся в easy/medium/hard.
$difficulty = difficulty_normalize($in['difficulty'] ?? 'medium');

$cuisine    = in_array($in['cuisine'] ?? '', $allowedCuisine, true) ? $in['cuisine'] : 'Другая';

function generateSlug(string $title): string
{
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo',
        'Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M',
        'Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U',
        'Ф'=>'F','Х'=>'H','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch',
        'Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
        ' '=>'-','_'=>'-',','=>'','.'=>'','!'=>'','?'=>'','"'=>'',"'"=>'',
        '('=>'',')'=>'',':'=>'',';'=>'','/'=>'','\\'=>''
    ];
    
    $slug = strtr($title, $translit);
    $slug = mb_strtolower($slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (empty($slug)) $slug = 'recipe';
    $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    
    return $slug;
}

$slug = generateSlug($title);

$attempts = 0;
while (db_one('SELECT id FROM recipes WHERE slug = ?', [$slug]) && $attempts < 10) {
    $slug = generateSlug($title . '-' . ($attempts + 1));
    $attempts++;
}

if ($attempts >= 10) {
    $slug = generateSlug($title) . '-' . time();
}

$je = fn($v) => json_encode(array_values((array)($v ?? [])), JSON_UNESCAPED_UNICODE);

$imageUrl = trim((string)($in['image_url'] ?? ''));
if (empty($imageUrl) || $imageUrl === 'null') {
    $imageUrl = '/uploads/static/static.jpg';
}

try {
    // ============================================================
    //  АДАПТИВНАЯ ВСТАВКА (учитывает наличие столбцов)
    // ============================================================
    $existingColumns = [];
    try {
        $cols = db_all("SHOW COLUMNS FROM recipes");
        foreach ($cols as $c) {
            $existingColumns[$c['Field']] = true;
        }
    } catch (Throwable $e) {}
    
    // Нормализация шагов
    $steps = [];
    foreach ((array)($in['steps'] ?? []) as $i => $step) {
        if (is_array($step)) {
            $instruction = trim((string)($step['instruction'] ?? $step['text'] ?? ''));
            if ($instruction === '') continue;
            $steps[] = [
                'order' => (int)($step['order'] ?? $i + 1),
                'instruction' => $instruction,
                'tip' => trim((string)($step['tip'] ?? '')),
                'duration' => isset($step['timer_minutes']) ? (int)$step['timer_minutes'] : null,
                'timer_minutes' => (int)($step['timer_minutes'] ?? 0),
            ];
        } else {
            $steps[] = ['order' => $i + 1, 'instruction' => (string)$step, 'tip' => ''];
        }
    }
    
    // Нормализация ингредиентов
    $normalizedIngredients = [];
    foreach ($ingredients as $ing) {
        if (is_string($ing)) {
            $normalizedIngredients[] = ['name' => trim($ing), 'amount' => '', 'unit' => ''];
        } elseif (is_array($ing)) {
            $name = trim((string)($ing['name'] ?? ''));
            if ($name === '') continue;
            $normalizedIngredients[] = [
                'name' => $name,
                'amount' => trim((string)($ing['amount'] ?? '')),
                'unit' => trim((string)($ing['unit'] ?? '')),
            ];
        }
    }
    
    $carbs = max(0, (float)($in['carbs'] ?? 0));
    $breadUnits = $carbs > 0 ? round($carbs / 12, 2) : 0;
    $glycemicLoad = $carbs > 0 ? round((50 * $carbs) / 100, 2) : 0;
    
    // ============================================================
    //  ФИНАЛЬНАЯ ПРОВЕРКА ТЕГОВ ПЕРЕД СОХРАНЕНИЕМ (как в кроне)
    // ============================================================
    
    // 1. Собираем строку из ингредиентов
    $allIngs = '';
    foreach ($normalizedIngredients as $ing) {
        $allIngs .= mb_strtolower($ing['name'] . ' ', 'UTF-8');
    }
    $titleLower = mb_strtolower($title, 'UTF-8');

    // 2. Проверяем аллергены
    $hasGluten  = preg_match('/(мука|хлеб|макарон|пшениц|рожь|ячмен|булк|тесто|вермишел)/ui', $allIngs);
    $hasChicken = preg_match('/(кур|куриц|куриное|курятин|крыл|бедр|груд|окороч|цыпл)/ui', $allIngs);
    $hasLactose = preg_match('/(молоко|сыр|сливк|масло|кефир|сметан|творог)/ui', $allIngs);

    // 3. Достаем текущие теги, которые прислал AI (или генератор)
    $tagSlugs = array_values(array_filter(array_map('strval', (array)($in['tag_slugs'] ?? []))));
    $currentSlugs = $tagSlugs;

    // 4. Добавляем обязательные, если AI забыл
    if (!$hasGluten && !in_array('bezglyutenovaya', $currentSlugs)) {
        $tagSlugs[] = 'bezglyutenovaya';
    }
    if (!$hasChicken && !in_array('bez-kuricy', $currentSlugs)) {
        $tagSlugs[] = 'bez-kuricy';
    }
    if (!$hasLactose && !in_array('bezlaktoznaya', $currentSlugs)) {
        $tagSlugs[] = 'bezlaktoznaya';
    }

    // 5. Убираем ложный bez-kuricy, если курица ВСЁ-ТАКИ ЕСТЬ
    if ($hasChicken && in_array('bez-kuricy', $tagSlugs)) {
        $tagSlugs = array_filter($tagSlugs, function($s) { return $s !== 'bez-kuricy'; });
    }
    
    // 6. Убираем ложный novyj-god, если это не праздничное блюдо
    $hasHolidayKeyword = preg_match('/(оливье|сельд|мандарин|кулич|индейк|гусь|тыкв|пасх|блин)/ui', $titleLower);
    if (!$hasHolidayKeyword && in_array('novyj-god', $tagSlugs)) {
        $tagSlugs = array_filter($tagSlugs, function($s) { return $s !== 'novyj-god'; });
    }
    
    // 7. Исправляем сезон для окрошки
    if (mb_strpos($titleLower, 'окрошк') !== false || mb_strpos($titleLower, 'гаспачо') !== false) {
        $tagSlugs = array_filter($tagSlugs, function($s) {
            return !in_array($s, ['letnie', 'zimnie', 'vesennie', 'osennie', 'kruglogodichnye']);
        });
        $tagSlugs[] = 'letnie';
    }

    // Обновляем $in, чтобы дальше в $candidateData пошли уже исправленные теги
    $in['tag_slugs'] = array_values(array_unique($tagSlugs));
    
    $candidateData = [
        'slug'            => $slug,
        'title'           => mb_substr($title, 0, 200),
        'description'     => trim((string)($in['description'] ?? '')),
        'cuisine'         => $cuisine,
        'diet_type'       => $je($in['diet_type'] ?? []),
        'difficulty'      => $difficulty,
        'prep_time'       => max(0, (int)($in['prep_time'] ?? 0)),
        'cook_time'       => max(0, (int)($in['cook_time'] ?? 0)),
        'servings'        => max(1, (int)($in['servings'] ?? 1)),
        'calories'        => max(0, (int)($in['calories'] ?? 0)),
        'proteins'        => max(0, (int)($in['proteins'] ?? 0)),
        'fats'            => max(0, (int)($in['fats'] ?? 0)),
        'carbs'           => max(0, (int)($in['carbs'] ?? 0)),
        'bread_units'     => $breadUnits,
        'glycemic_index'  => 50,
        'glycemic_load'   => $glycemicLoad,
        'ingredients'     => $je($normalizedIngredients),
        'steps'           => $je($steps),
        'image_url'       => $imageUrl,
        'tags'            => $je($in['tags'] ?? []),
        'season'          => $je($in['season'] ?? []),
        'is_ai_generated' => !empty($in['is_ai_generated']) ? 1 : 0,
        'author_id'       => $userId,
        'author_email'    => $user['email'],
        'video_url'       => trim((string)($in['video_url'] ?? '')), // сохраняем строку vk:owner:id
        'video_owner_id'  => isset($in['video_owner_id']) ? (int)$in['video_owner_id'] : null,
        'video_id'        => isset($in['video_id']) ? (int)$in['video_id'] : null,
    ];
    
    // Фильтруем по существующим столбцам
    $insertData = [];
    foreach ($candidateData as $col => $val) {
        if (isset($existingColumns[$col])) {
            $insertData[$col] = $val;
        }
    }
    
    // Строим INSERT
    $cols = array_keys($insertData);
    $placeholders = array_fill(0, count($cols), '?');
    
    if (isset($existingColumns['created_at']) && !in_array('created_at', $cols, true)) {
        $cols[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    
    $sql = "INSERT INTO `recipes` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    
    $params = [];
    foreach ($cols as $col) {
        if ($col === 'created_at' && !isset($insertData[$col])) continue;
        $params[] = $insertData[$col];
    }
    
    db_exec($sql, $params);
    $id = (int)db()->lastInsertId();

    $savedRecipe = db_one('SELECT id, slug, title FROM recipes WHERE id = ?', [$id]);
    if (!$savedRecipe) {
        throw new RuntimeException('Рецепт не сохранился в БД');
    }
    
        // ============================================================
    //  СОХРАНЕНИЕ КАТЕГОРИИ И ТЕГОВ
    // ============================================================
    
    $categorySlug = trim((string)($in['category_slug'] ?? ''));
    $tagSlugs     = $in['tag_slugs'] ?? [];
    
    // Нормализация: массив строк
    if (is_string($tagSlugs)) {
        $tagSlugs = [$tagSlugs];
    }
    $tagSlugs = array_values(array_filter(array_map('strval', (array)$tagSlugs)));
    
    // Если AI не передал — попробуем классифицировать
    if ($categorySlug === '' || empty($tagSlugs)) {
        try {
            require_once ROOT_DIR . '/includes/yandex.php';
            
            $classified = classifyRecipeWithAI([
                'title'       => $savedRecipe['title'],
                'description' => $in['description'] ?? '',
                'ingredients' => $normalizedIngredients,
                'steps'       => $steps,
                'prep_time'   => (int)($in['prep_time'] ?? 0),
                'cook_time'   => (int)($in['cook_time'] ?? 0),
            ]);
            
            if ($classified) {
                if ($categorySlug === '' && !empty($classified['category_slug'])) {
                    $categorySlug = (string)$classified['category_slug'];
                }
                if (empty($tagSlugs) && !empty($classified['tags'])) {
                    $tagSlugs = array_values(array_filter(array_map('strval', $classified['tags'])));
                }
            }
        } catch (Throwable $e) {
            error_log('[save_recipe] classify fallback failed: ' . $e->getMessage());
        }
    }
    
    // 1. Записываем категорию
    if ($categorySlug !== '') {
        $catId = db_value(
            "SELECT id FROM recipe_categories WHERE slug = ? AND is_active = 1",
            [$categorySlug]
        );
        if ($catId) {
            db_exec(
                "UPDATE recipes SET category_id = ?, ai_classified = 1, 
                 ai_classified_at = NOW(), ai_category_confidence = 1.0 
                 WHERE id = ?",
                [(int)$catId, (int)$id]
            );
        } else {
            error_log("[save_recipe] unknown category_slug: {$categorySlug}");
        }
    }
    
    // 2. Записываем теги
    if (!empty($tagSlugs)) {
        $linkedCount = 0;
        foreach (array_unique($tagSlugs) as $tagSlug) {
            $tid = db_value(
                "SELECT id FROM recipe_tags WHERE slug = ? AND is_active = 1",
                [(string)$tagSlug]
            );
            if (!$tid) continue;
            
            db_exec(
                "INSERT IGNORE INTO recipe_tag_links 
                 (recipe_id, tag_id, confidence, source) 
                 VALUES (?, ?, 1.00, 'ai')",
                [(int)$id, (int)$tid]
            );
            $linkedCount++;
        }
        
        // Обновляем usage_count для затронутых тегов
        if ($linkedCount > 0) {
            db_exec(
                "UPDATE recipe_tags t SET usage_count = 
                 (SELECT COUNT(*) FROM recipe_tag_links WHERE tag_id = t.id)"
            );
        }
    }

    // Обновляем статистику
    // Инкремент счётчика рецептов (отдельно от очков)
db_exec(
    'UPDATE user_stats SET recipes_created = recipes_created + 1 WHERE user_id = ?',
    [$userId]
);

// Начисление очков через единую функцию (пишет в points_log)
add_user_points(
    $userId,
    10,
    !empty($in['is_ai_generated']) ? 'ai_recipe' : 'recipe_create',
    (!empty($in['is_ai_generated']) ? 'AI-рецепт: ' : 'Создал рецепт: ') . $savedRecipe['title']
);

    // Активность
    $activityType = !empty($in['is_ai_generated']) ? 'ai_recipe' : 'recipe_created';
    try {
        add_activity($user['email'], $activityType, $id, 'recipe', 'Опубликовал рецепт: ' . $savedRecipe['title']);
    } catch (Throwable $e) {
        error_log('add_activity error: ' . $e->getMessage());
    }

    // Достижения
    try {
        check_achievements($user['email']);
    } catch (Throwable $e) {
        error_log('check_achievements error: ' . $e->getMessage());
    }

    // Уведомляем друзей
    try {
        $friends = db_all(
            "SELECT friend_email FROM friendships WHERE user_email = ? AND status = 'accepted'",
            [$user['email']]
        );
        
        foreach ($friends as $friend) {
            send_notification(
                $friend['friend_email'],
                '📖 Новый рецепт от друга',
                ($user['display_name'] ?? 'Друг') . ' опубликовал рецепт: ' . $savedRecipe['title'],
                'recipe'
            );
        }
    } catch (Throwable $e) {
        error_log('notify friends error: ' . $e->getMessage());
    }
    
    // ============================================================
    //  ДОБАВЛЕНИЕ В КНИГУ (если пришли из книги)
    // ============================================================
    $bookId = (int)($in['book_id'] ?? 0);
    if ($bookId > 0) {
        try {
            // Загружаем книгу (только если это книга этого пользователя)
            $book = db_one(
                "SELECT id, recipes_json FROM cookbooks WHERE id = ? AND (author_id = ? OR author_email = ?)",
                [$bookId, $userId, $user['email']]
            );
            
            if ($book) {
                $bookRecipes = json_field($book['recipes_json']);
                
                // Формируем структуру рецепта для JSON книги
                $newBookRecipe = [
                    'title'             => $savedRecipe['title'],
                    'description'       => $in['description'] ?? '',
                    'cuisine'           => $cuisine,
                    'difficulty'        => difficulty_label($difficulty), // 'Легко', 'Средне' и т.д.
                    'prep_time'         => (int)($in['prep_time'] ?? 0),
                    'cook_time'         => (int)($in['cook_time'] ?? 0),
                    'servings'          => (int)($in['servings'] ?? 2),
                    'calories'          => (int)($in['calories'] ?? 0),
                    'proteins'          => (int)($in['proteins'] ?? 0),
                    'fats'              => (int)($in['fats'] ?? 0),
                    'carbs'             => (int)($in['carbs'] ?? 0),
                    'ingredients'       => $normalizedIngredients,
                    'steps'             => $steps,
                    'image_url'         => $imageUrl,
                    'created_recipe_id' => (int)$savedRecipe['id'], // Связка с реальным рецептом
                    'created_slug'      => $savedRecipe['slug'],    // Слаг для открытия
                ];
                
                // Проверяем, нет ли уже такого рецепта в книге (по ID)
                $exists = false;
                foreach ($bookRecipes as $br) {
                    if (isset($br['created_recipe_id']) && (int)$br['created_recipe_id'] === (int)$savedRecipe['id']) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $bookRecipes[] = $newBookRecipe;
                    
                    db_exec(
                        "UPDATE cookbooks SET recipes_json = ?, updated_at = NOW() WHERE id = ?",
                        [json_encode($bookRecipes, JSON_UNESCAPED_UNICODE), $bookId]
                    );
                }
            }
        } catch (Throwable $e) {
            error_log('save_recipe add_to_book error: ' . $e->getMessage());
            // Не прерываем сохранение рецепта, если не удалось добавить в книгу
        }
    }

    json_response([
        'ok'           => true,
        'id'           => (int)$savedRecipe['id'],
        'slug'         => $savedRecipe['slug'],
        'title'        => $savedRecipe['title'],
        'message'      => 'Рецепт успешно сохранён',
        'redirect_url' => '/recipe/' . $savedRecipe['slug'],
    ]);
    
} catch (Throwable $ex) {
    error_log('Save recipe error: ' . $ex->getMessage());
    json_response([
        'ok'    => false,
        'error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сохранения',
    ], 500);
}