<?php
/**
 * GigaChef — Централизованный контроль дублирования рецептов
 * 
 * Единая точка правды для проверки существующих рецептов.
 * Используется во всех точках создания: create_recipe, ai_generator,
 * cookbook_detail, fusion (вариатор).
 * 
 * Защита от:
 *  - создания рецептов с одинаковым названием у одного пользователя
 *  - создания рецептов с одинаковым набором ингредиентов (75%+ совпадение)
 *  - дублирования между recipes, fusion_recipes и cookbooks
 */
declare(strict_types=1);

// ============================================================
//  НОРМАЛИЗАЦИЯ НАЗВАНИЯ
// ============================================================

function normalize_title_for_compare(string $title): string
{
    $title = mb_strtolower(trim($title));
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);
    $title = preg_replace('/\s+/u', ' ', $title);
    return trim($title);
}

/**
 * Ключевые слова из названия (без стоп-слов типа "детская", "острая")
 */
function extract_title_keywords(string $title): array
{
    $stopWords = [
        'детская', 'детский', 'острая', 'острый', 'веганская', 'веганский',
        'кето', 'праздничная', 'праздничный', 'быстрая', 'быстрый',
        'низкокалорийная', 'низкокалорийный', 'средиземноморская', 'азиатская',
        'версия', 'стиль', 'блюдо', 'рецепт'
    ];
    
    $normalized = normalize_title_for_compare($title);
    $words = explode(' ', $normalized);
    $words = array_filter($words, fn($w) => !in_array($w, $stopWords) && mb_strlen($w) > 2);
    
    return array_values($words);
}

// ============================================================
//  ХЕШ ИНГРЕДИЕНТОВ
// ============================================================

function recipe_ingredients_hash(array $ingredients): string
{
    $names = [];
    foreach ($ingredients as $ing) {
        $name = is_string($ing) ? $ing : ($ing['name'] ?? '');
        $name = mb_strtolower(trim((string)$name));
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        if ($name !== '') $names[] = $name;
    }
    sort($names);
    return md5(implode('|', $names));
}

// ============================================================
//  СРАВНЕНИЕ ИНГРЕДИЕНТОВ
// ============================================================

function compare_ingredients(array $a, array $b): float
{
    $norm = function (array $ings): array {
        $out = [];
        foreach ($ings as $ing) {
            $name = is_string($ing) ? $ing : ($ing['name'] ?? '');
            $name = mb_strtolower(trim((string)$name));
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

// ============================================================
//  ПОИСК ДУБЛИКАТОВ
// ============================================================

/**
 * Полная проверка: есть ли похожий рецепт
 * 
 * @param string   $title        Название
 * @param array    $ingredients  Ингредиенты
 * @param int|null $userId       Ограничить поиск рецептами пользователя
 * @param float    $threshold    Порог совпадения (0..1)
 * @return array|null            Информация о дубликате или null
 */
function check_recipe_duplicate(
    string $title,
    array $ingredients = [],
    ?int $userId = null,
    float $threshold = 0.75
): ?array {
    if (empty($title)) return null;
    
    $normTitle = normalize_title_for_compare($title);
    $titleKeywords = extract_title_keywords($title);
    $ingHash = !empty($ingredients) ? recipe_ingredients_hash($ingredients) : '';
    
    try {
        $params = [];
        $where = "1=1";
        if ($userId) {
            $where .= " AND (author_id = ? OR author_email = (SELECT email FROM users WHERE id = ?))";
            $params[] = $userId;
            $params[] = $userId;
        }
        
        $rows = db_all(
            "SELECT id, title, slug, ingredients, image_url, author_email, created_at
             FROM recipes 
             WHERE {$where}
             ORDER BY created_at DESC 
             LIMIT 300",
            $params
        );
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($rows as $row) {
            // 1. Точное совпадение названия
            if (normalize_title_for_compare($row['title']) === $normTitle) {
                return [
                    'id'         => (int)$row['id'],
                    'title'      => $row['title'],
                    'slug'       => $row['slug'],
                    'image_url'  => $row['image_url'],
                    'created_at' => $row['created_at'],
                    'reason'     => 'exact_title',
                    'similarity' => 100,
                    'source'     => 'recipes',
                ];
            }
            
            // 2. Совпадение по ключевым словам
            $rowKeywords = extract_title_keywords($row['title']);
            if (empty($titleKeywords) || empty($rowKeywords)) continue;
            
            $common = array_intersect($titleKeywords, $rowKeywords);
            $keywordSim = count($common) / max(count($titleKeywords), count($rowKeywords));
            
            // 3. Совпадение по ингредиентам
            $ingSim = 0;
            if ($ingHash && !empty($row['ingredients'])) {
                $rowIng = json_decode($row['ingredients'], true) ?: [];
                $rowIngHash = recipe_ingredients_hash($rowIng);
                if ($rowIngHash === $ingHash) {
                    $ingSim = 1.0;
                } else {
                    $ingSim = compare_ingredients($ingredients, $rowIng);
                }
            }
            
            // Комбинированный скор
            $score = ($keywordSim * 0.4) + ($ingSim * 0.6);
            
            if ($score > $bestScore && $score >= $threshold) {
                $bestScore = $score;
                $bestMatch = [
                    'id'         => (int)$row['id'],
                    'title'      => $row['title'],
                    'slug'       => $row['slug'],
                    'image_url'  => $row['image_url'],
                    'created_at' => $row['created_at'],
                    'reason'     => $ingSim >= 0.85 ? 'ingredients' : 'title_keywords',
                    'similarity' => round($score * 100),
                    'source'     => 'recipes',
                ];
            }
        }
        
        return $bestMatch;
        
    } catch (Throwable $e) {
        error_log('check_recipe_duplicate error: ' . $e->getMessage());
        return null;
    }
}

// ============================================================
//  ПРОВЕРКА ДЛЯ КНИГ
// ============================================================

/**
 * Проверяет, существует ли рецепт из книги РЕАЛЬНО в recipes
 * 
 * @return array [
 *   'exists'    => bool,
 *   'recipe'    => array|null,
 *   'duplicate' => array|null,
 *   'message'   => string,
 * ]
 */
function check_cookbook_recipe_status(array $bookRecipe, ?int $userId = null): array
{
    // 1. Если есть created_recipe_id — рецепт уже создан
    if (!empty($bookRecipe['created_recipe_id'])) {
        $existing = db_one(
            'SELECT id, title, slug, image_url FROM recipes WHERE id = ?',
            [(int)$bookRecipe['created_recipe_id']]
        );
        
        if ($existing) {
            return [
                'exists'    => true,
                'recipe'    => $existing,
                'duplicate' => null,
                'message'   => 'Рецепт уже создан в приложении',
            ];
        }
    }
    
    // 2. Ищем по названию + ингредиентам
    $duplicate = check_recipe_duplicate(
        (string)($bookRecipe['title'] ?? ''),
        (array)($bookRecipe['ingredients'] ?? []),
        $userId,
        0.75
    );
    
    if ($duplicate) {
        return [
            'exists'    => false,
            'recipe'    => null,
            'duplicate' => $duplicate,
            'message'   => 'Найден похожий рецепт: ' . $duplicate['title']
                         . ' (совпадение ' . $duplicate['similarity'] . '%)',
        ];
    }
    
    return [
        'exists'    => false,
        'recipe'    => null,
        'duplicate' => null,
        'message'   => 'Рецепт новый, можно создавать',
    ];
}

// ============================================================
//  ЛОГИРОВАНИЕ
// ============================================================

function log_recipe_duplicate_attempt(
    string $source,
    int $userId,
    string $title,
    ?array $duplicate
): void {
    try {
        db_insert(
            'INSERT INTO recipe_duplicate_log 
             (source, user_id, title, duplicate_id, duplicate_title, similarity, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $source,
                $userId,
                mb_substr($title, 0, 255),
                $duplicate['id'] ?? null,
                $duplicate['title'] ?? null,
                $duplicate['similarity'] ?? null,
            ]
        );
    } catch (Throwable $e) {
        error_log('log_recipe_duplicate_attempt: ' . $e->getMessage());
    }
}