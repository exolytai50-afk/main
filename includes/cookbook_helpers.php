<?php
/**
 * GigaChef — Хелперы для работы с кулинарными книгами
 * 
 * ВАЖНО: часть функций (ХЕ, ГН, ГИ) уже объявлена в includes/functions.php.
 * Здесь мы НЕ дублируем их, а только используем через fallback-проверки.
 */
declare(strict_types=1);

const COOKBOOK_MAX_AI_CALLS = 20;

// ============================================================
//  СЛОЖНОСТЬ
// ============================================================

if (!function_exists('normalize_difficulty')) {
    /**
     * Приводит сложность к русскому названию:
     *  easy / Easy → Легко
     *  medium      → Средне
     *  hard        → Сложно
     */
    function normalize_difficulty(string $difficulty): string
    {
        $map = [
            'easy'   => 'Легко',
            'medium' => 'Средне',
            'hard'   => 'Сложно',
            'Легко'  => 'Легко',
            'Средне' => 'Средне',
            'Сложно' => 'Сложно',
            'Мастер-класс' => 'Мастер-класс',
        ];
        $key = mb_strtolower(trim($difficulty));
        return $map[$key] ?? 'Средне';
    }
}

// ============================================================
//  ГИ (локальный fallback, если в functions.php нет)
// ============================================================

if (!function_exists('estimate_glycemic_index')) {
    /**
     * Средний ГИ для набора ингредиентов.
     * Если в functions.php есть get_glycemic_index — используем его.
     */
    function estimate_glycemic_index(array $ingredients): int
    {
        $totalGI = 0;
        $count = 0;
        
        foreach ($ingredients as $ing) {
            $name = mb_strtolower(trim((string)($ing['name'] ?? $ing)));
            if ($name === '') continue;
            
            // Если в functions.php уже есть get_glycemic_index — используем
            if (function_exists('get_glycemic_index')) {
                $gi = (int)get_glycemic_index($name);
                if ($gi > 0) {
                    $totalGI += $gi;
                    $count++;
                }
                continue;
            }
            
            // Fallback: прямой запрос в БД
            try {
                $product = db_one(
                    'SELECT glycemic_index FROM gi_products 
                     WHERE LOWER(product_name) = ? OR LOWER(product_name) LIKE ? LIMIT 1',
                    [$name, '%' . $name . '%']
                );
                if ($product && !empty($product['glycemic_index'])) {
                    $totalGI += (int)$product['glycemic_index'];
                    $count++;
                }
            } catch (Throwable $e) {
                // Таблицы может не быть
            }
        }
        
        return $count > 0 ? (int)round($totalGI / $count) : 50;
    }
}

// ============================================================
//  ШАГИ
// ============================================================

if (!function_exists('extract_duration_minutes')) {
    /**
     * Извлекает длительность из текста шага.
     * "Готовить 10 минут" → 10
     * "Варить 1 час" → 60
     * "Обжаривать 5-7 мин" → 7
     */
    function extract_duration_minutes(string $text): ?int
    {
        if (preg_match('/(\d+)\s*(час|ч)\w*\s*(\d+)?\s*(минут|мин)?/ui', $text, $m)) {
            $h = (int)$m[1];
            $min = isset($m[3]) ? (int)$m[3] : 0;
            return $h * 60 + $min;
        }
        if (preg_match('/(\d+)\s*[-–—]\s*(\d+)\s*(минут|мин)/ui', $text, $m)) {
            return (int)$m[2];
        }
        if (preg_match('/(\d+)\s*(минут|мин)/ui', $text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/(\d+)\s*(час|ч)/ui', $text, $m)) {
            return (int)$m[1] * 60;
        }
        return null;
    }
}

if (!function_exists('normalize_steps')) {
    /**
     * Приводит шаги к единому формату:
     * [{order, instruction, tip, duration}, ...]
     */
    function normalize_steps($steps): array
{
    if (!is_array($steps)) return [];
    
    $out = [];
    $idx = 1;
    
    foreach ($steps as $step) {
        // Строка
        if (is_string($step)) {
            $duration = extract_duration_minutes($step);
            $out[] = [
                'order'         => $idx++,
                'instruction'   => trim($step),
                'tip'           => '',
                'duration'      => $duration,
                // ✅ ДОБАВЛЕНО: дублируем в timer_minutes для совместимости
                'timer_minutes' => $duration ?? 5,
            ];
            continue;
        }
        
        // Массив
        if (is_array($step)) {
            $instruction = trim((string)(
                $step['instruction'] 
                ?? $step['text'] 
                ?? $step['step'] 
                ?? ''
            ));
            
            if ($instruction === '') continue;
            
            // Ищем duration во всех вариантах
            $duration = $step['duration'] 
                     ?? $step['timer_minutes'] 
                     ?? $step['minutes'] 
                     ?? $step['time'] 
                     ?? extract_duration_minutes($instruction);
            
            // Если ничего не нашли — дефолт 5 минут
            if ($duration === null || $duration <= 0) {
                $duration = 5;
            }
            
            $out[] = [
                'order'         => (int)($step['order'] ?? $idx),
                'instruction'   => $instruction,
                'tip'           => trim((string)($step['tip'] ?? '')),
                'duration'      => (int)$duration,
                // ✅ ДОБАВЛЕНО: дублируем в timer_minutes для совместимости
                'timer_minutes' => (int)$duration,
            ];
            $idx++;
        }
    }
    
    return $out;
}
}

// ============================================================
//  ИНГРЕДИЕНТЫ
// ============================================================

if (!function_exists('normalize_ingredient')) {
    function normalize_ingredient($ing): array
    {
        if (is_string($ing)) {
            return ['name' => trim($ing), 'amount' => '', 'unit' => ''];
        }
        if (!is_array($ing)) {
            return ['name' => '', 'amount' => '', 'unit' => ''];
        }
        return [
            'name'   => trim((string)($ing['name'] ?? '')),
            'amount' => trim((string)($ing['amount'] ?? '')),
            'unit'   => trim((string)($ing['unit'] ?? '')),
        ];
    }
}

if (!function_exists('normalize_ingredients')) {
    function normalize_ingredients(array $ingredients): array
    {
        return array_values(array_filter(
            array_map('normalize_ingredient', $ingredients),
            fn($i) => $i['name'] !== ''
        ));
    }
}

if (!function_exists('ingredients_hash')) {
    function ingredients_hash(array $ingredients): string
    {
        $names = array_map(function ($ing) {
            $name = mb_strtolower(trim((string)($ing['name'] ?? $ing)));
            $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
            $name = preg_replace('/\s+/u', ' ', $name);
            return trim($name);
        }, $ingredients);
        
        $names = array_filter($names);
        sort($names);
        
        return md5(implode('|', $names));
    }
}

if (!function_exists('ingredients_similarity')) {
    function ingredients_similarity(array $a, array $b): float
    {
        $norm = function (array $ings): array {
            $out = [];
            foreach ($ings as $ing) {
                $name = mb_strtolower(trim((string)($ing['name'] ?? $ing)));
                $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
                $name = trim(preg_replace('/\s+/u', ' ', $name));
                if ($name !== '') $out[$name] = true;
            }
            return $out;
        };
        
        $A = $norm($a);
        $B = $norm($b);
        
        if (!$A || !$B) return 0.0;
        
        $intersection = count(array_intersect_key($A, $B));
        $union = count($A + $B);
        
        return $union > 0 ? $intersection / $union : 0.0;
    }
}

// ============================================================
//  ПОЛНАЯ НОРМАЛИЗАЦИЯ РЕЦЕПТА
// ============================================================

if (!function_exists('normalize_recipe')) {
    /**
     * Приводит любой рецепт к единому формату + считает ХЕ/ГН.
     * 
     * Использует calculate_bread_units() и calculate_glycemic_load()
     * из includes/functions.php, если они там есть.
     */
    function normalize_recipe(array $recipe): array
    {
        // 1. Ингредиенты
        $ingredients = normalize_ingredients((array)($recipe['ingredients'] ?? []));
        
        // 2. Шаги
        $steps = normalize_steps($recipe['steps'] ?? []);
        
        // 3. Пересчёт cook_time как суммы времени шагов
        $stepTotal = 0;
        foreach ($steps as $s) {
            $stepTotal += (int)($s['duration'] ?? 0);
        }
        
        $prepTime = max(0, (int)($recipe['prep_time'] ?? 0));
        $cookTime = max(0, (int)($recipe['cook_time'] ?? 0));
        if ($stepTotal > 0 && $cookTime < $stepTotal) {
            $cookTime = $stepTotal;
        }
        
        // 4. БЖУ
$proteins = (float)($recipe['proteins'] ?? 0);
$fats     = (float)($recipe['fats'] ?? 0);
$carbs    = (float)($recipe['carbs'] ?? 0);
$calories = (int)($recipe['calories'] ?? 0);
$servings = max(1, (int)($recipe['servings'] ?? 2));

// ============================================================
//  FALLBACK: если AI не вернул БЖУ, но указал калории —
//  рассчитываем по стандартным пропорциям:
//    Белки:   25% калорий (1 г = 4 ккал)
//    Жиры:    30% калорий (1 г = 9 ккал)
//    Углеводы: 45% калорий (1 г = 4 ккал)
// ============================================================
if ($proteins <= 0 && $fats <= 0 && $carbs <= 0 && $calories > 0) {
    $proteins = round(($calories * 0.25) / 4, 1);
    $fats     = round(($calories * 0.30) / 9, 1);
    $carbs    = round(($calories * 0.45) / 4, 1);
}

// Если вообще ничего нет — используем минимальные значения
if ($calories <= 0) {
    $calories = 200; // дефолт
    if ($proteins <= 0) $proteins = 12.5;
    if ($fats <= 0)     $fats     = 6.7;
    if ($carbs <= 0)    $carbs    = 22.5;
}
        
        // 5. ХЕ — используем функцию из functions.php
        $breadUnits = 0;
        if (function_exists('calculate_bread_units')) {
            $breadUnits = calculate_bread_units($carbs);
        } else {
            $breadUnits = round($carbs / 12, 2);
        }
        
        // 6. ГИ и ГН
        $gi = 50;
        if (function_exists('estimate_glycemic_index')) {
            $gi = estimate_glycemic_index($ingredients);
        }
        
        $glycemicLoad = 0;
        if (function_exists('calculate_glycemic_load')) {
            $glycemicLoad = calculate_glycemic_load($gi, $carbs);
        } else {
            $glycemicLoad = round(($gi * $carbs) / 100, 2);
        }
        
        return [
    'title'          => trim((string)($recipe['title'] ?? 'Без названия')),
    'description'    => trim((string)($recipe['description'] ?? '')),
    'cuisine'        => trim((string)($recipe['cuisine'] ?? 'Другая')),
    'difficulty'     => normalize_difficulty((string)($recipe['difficulty'] ?? 'medium')),
    'prep_time'      => $prepTime,
    'cook_time'      => $cookTime,
    'servings'       => $servings,
    'calories'       => $calories,
    'proteins'       => $proteins,
    'fats'           => $fats,
    'carbs'          => $carbs,
    'bread_units'    => $breadUnits,
    'glycemic_index' => $gi,
    'glycemic_load'  => $glycemicLoad,
    'ingredients'    => $ingredients,
    'steps'          => $steps,
    'tips'           => array_values((array)($recipe['tips'] ?? [])),

    // НОВОЕ:
    'category_slug'  => trim((string)($recipe['category_slug'] ?? '')),
    'tag_slugs'      => array_values(array_filter(
        array_map('strval', (array)($recipe['tag_slugs'] ?? []))
    )),
];
    }
}

// ============================================================
//  ПОИСК ДУБЛИКАТОВ
// ============================================================

if (!function_exists('find_similar_recipe')) {
    function find_similar_recipe(array $ingredients, ?int $userId = null, float $threshold = 0.7): ?array
    {
        if (empty($ingredients)) return null;
        
        try {
            $checkIngredients = normalize_ingredients($ingredients);
            $userEmail = null;
            
            if ($userId) {
                $u = db_one('SELECT email FROM users WHERE id = ?', [$userId]);
                $userEmail = $u['email'] ?? null;
            }
            
            $params = [];
            $where = "1=1";
            if ($userEmail) {
                $where .= " AND author_email = ?";
                $params[] = $userEmail;
            }
            
            $rows = db_all(
                "SELECT id, title, slug, ingredients, image_url, author_email 
                 FROM recipes 
                 WHERE {$where}
                 ORDER BY created_at DESC 
                 LIMIT 200",
                $params
            );
            
            foreach ($rows as $row) {
                $rowIngredients = json_field($row['ingredients']);
                if (empty($rowIngredients)) continue;
                
                $similarity = ingredients_similarity($checkIngredients, $rowIngredients);
                
                if ($similarity >= $threshold) {
                    return [
                        'id'         => (int)$row['id'],
                        'title'      => $row['title'],
                        'slug'       => $row['slug'],
                        'image_url'  => $row['image_url'],
                        'similarity' => round($similarity * 100),
                        'source'     => 'recipes'
                    ];
                }
            }
            
            if ($userId) {
                $fusionRows = db_all(
                    "SELECT id, title, ingredients, image_url 
                     FROM fusion_recipes 
                     WHERE user_id = ?
                     ORDER BY created_at DESC 
                     LIMIT 200",
                    [$userId]
                );
                
                foreach ($fusionRows as $row) {
                    $rowIngredients = json_field($row['ingredients']);
                    if (empty($rowIngredients)) continue;
                    
                    $similarity = ingredients_similarity($checkIngredients, $rowIngredients);
                    
                    if ($similarity >= $threshold) {
                        return [
                            'id'         => (int)$row['id'],
                            'title'      => $row['title'],
                            'image_url'  => $row['image_url'],
                            'similarity' => round($similarity * 100),
                            'source'     => 'fusion'
                        ];
                    }
                }
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log('find_similar_recipe error: ' . $e->getMessage());
            return null;
        }
    }
}

// ============================================================
//  РАБОТА С КНИГАМИ
// ============================================================

if (!function_exists('get_user_email')) {
    function get_user_email(int $userId): ?string
    {
        static $cache = [];
        if (isset($cache[$userId])) return $cache[$userId];
        
        $u = db_one('SELECT email FROM users WHERE id = ?', [$userId]);
        return $cache[$userId] = ($u['email'] ?? null);
    }
}

if (!function_exists('find_user_cookbook')) {
    function find_user_cookbook(int $userId, $bookId = null, $slug = null): ?array
    {
        try {
            $email = get_user_email($userId);
            if (!$email) return null;
            
            if ($bookId) {
                return db_one(
                    'SELECT * FROM cookbooks WHERE id = ? AND author_email = ?',
                    [(int)$bookId, $email]
                );
            }
            if ($slug) {
                return db_one(
                    'SELECT * FROM cookbooks WHERE slug = ? AND author_email = ?',
                    [$slug, $email]
                );
            }
            return null;
        } catch (Throwable $e) {
            error_log('find_user_cookbook error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('get_user_cookbooks')) {
    function get_user_cookbooks(int $userId): array
    {
        try {
            $email = get_user_email($userId);
            if (!$email) return [];
            
            return db_all(
                'SELECT id, title, slug, description, recipes_json, is_public, likes_count, created_at, updated_at
                 FROM cookbooks 
                 WHERE author_email = ?
                 ORDER BY updated_at DESC, created_at DESC',
                [$email]
            );
        } catch (Throwable $e) {
            error_log('get_user_cookbooks error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('update_cookbook_recipes')) {
    function update_cookbook_recipes(int $bookId, array $recipes): bool
    {
        try {
            db_exec(
                'UPDATE cookbooks SET recipes_json = ?, updated_at = NOW() WHERE id = ?',
                [json_encode($recipes, JSON_UNESCAPED_UNICODE), $bookId]
            );
            return true;
        } catch (Throwable $e) {
            error_log('update_cookbook_recipes error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('add_recipe_to_cookbook')) {
    function add_recipe_to_cookbook(int $bookId, array $newRecipe): array
    {
        $book = db_one('SELECT recipes_json FROM cookbooks WHERE id = ?', [$bookId]);
        if (!$book) {
            return ['ok' => false, 'error' => 'Книга не найдена'];
        }
        
        $recipes = json_field($book['recipes_json']);
        
        $newHash = ingredients_hash($newRecipe['ingredients'] ?? []);
        foreach ($recipes as $existing) {
            if (ingredients_hash($existing['ingredients'] ?? []) === $newHash) {
                return ['ok' => false, 'error' => 'Рецепт с такими ингредиентами уже есть в книге'];
            }
        }
        
        $recipes[] = $newRecipe;
        
        if (!update_cookbook_recipes($bookId, $recipes)) {
            return ['ok' => false, 'error' => 'Не удалось обновить книгу'];
        }
        
        return ['ok' => true, 'count' => count($recipes)];
    }
}

if (!function_exists('remove_recipe_from_cookbook')) {
    function remove_recipe_from_cookbook(int $bookId, int $index): array
    {
        $book = db_one('SELECT recipes_json FROM cookbooks WHERE id = ?', [$bookId]);
        if (!$book) {
            return ['ok' => false, 'error' => 'Книга не найдена'];
        }
        
        $recipes = json_field($book['recipes_json']);
        
        if (!isset($recipes[$index])) {
            return ['ok' => false, 'error' => 'Рецепт не найден'];
        }
        
        array_splice($recipes, $index, 1);
        
        if (!update_cookbook_recipes($bookId, $recipes)) {
            return ['ok' => false, 'error' => 'Не удалось обновить книгу'];
        }
        
        return ['ok' => true, 'count' => count($recipes)];
    }
}

if (!function_exists('create_cookbook')) {
    function create_cookbook(int $userId, string $userEmail, string $title, string $description = '', array $recipes = []): int
    {
        $slug = generate_slug($title) . '-' . substr(md5(uniqid((string)$userId, true)), 0, 6);
        
        $attempts = 0;
        while (db_one('SELECT id FROM cookbooks WHERE slug = ?', [$slug]) && $attempts < 5) {
            $slug = generate_slug($title) . '-' . substr(md5(uniqid((string)$userId, true)), 0, 8);
            $attempts++;
        }
        
        db_insert(
            'INSERT INTO cookbooks 
             (author_email, title, slug, description, recipes_json, is_public, likes_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())',
            [
                $userEmail,
                $title,
                $slug,
                $description,
                json_encode($recipes, JSON_UNESCAPED_UNICODE)
            ]
        );
        
        return (int)db()->lastInsertId();
    }
}

// ============================================================
//  SLUG (только если в functions.php нет)
// ============================================================

if (!function_exists('generate_slug')) {
    function generate_slug(string $string): string
    {
        $translit = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
        ];
        $string = strtr($string, $translit);
        $string = mb_strtolower($string);
        $string = preg_replace('/[^a-z0-9]+/u', '-', $string);
        $slug = trim($string, '-');
        return $slug !== '' ? $slug : 'book-' . time();
    }
}
/** Книга пользователя (по id или slug). */
function cookbook_find_owned(int $id, int $userId): ?array
{
    return db_one(
        'SELECT * FROM cookbooks WHERE id = ? AND (author_id = ? OR author_email = ?)',
        [$id, $userId, (string)(current_user()['email'] ?? '')]
    );
}

/** Публичное представление книги. */
function cookbook_public(array $b): array
{
    return [
        'id'          => (int)$b['id'],
        'slug'        => (string)$b['slug'],
        'title'       => (string)$b['title'],
        'description' => (string)($b['description'] ?? ''),
        'theme'       => (string)($b['theme'] ?? ''),
        'is_public'   => (int)$b['is_public'],
        'ai_calls'    => (int)($b['ai_calls'] ?? 0),
    ];
}

/** Атомарный инкремент ai_calls. Бросает RuntimeException при превышении. */
function cb_ai_charge(int $bookId): void
{
    $n = db_exec(
        'UPDATE cookbooks SET ai_calls = ai_calls + 1 WHERE id = ? AND ai_calls < ?',
        [$bookId, COOKBOOK_MAX_AI_CALLS]
    );
    if ($n <= 0) {
        throw new RuntimeException('Лимит AI-запросов для этой книги исчерпан (' . COOKBOOK_MAX_AI_CALLS . ')');
    }
}

/** Уже присутствующие названия (lower) и recipe_id. */
function cb_existing(array $recipes): array
{
    $titles = [];
    $ids    = [];
    foreach ($recipes as $r) {
        $t = mb_strtolower(trim((string)($r['title'] ?? '')));
        if ($t !== '') $titles[$t] = true;
        if (!empty($r['created_recipe_id'])) $ids[(int)$r['created_recipe_id']] = true;
    }
    return ['titles' => $titles, 'ids' => $ids];
}