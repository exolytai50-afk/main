<?php
/**
 * GigaChef — генератор кулинарных книг: контекст, поиск в приложении, AI-план и AI-рецепт.
 * Повторяет подход «Праздников»: 1 запрос на действие, slug'и сверяются с БД.
 */
declare(strict_types=1);

require_once __DIR__ . '/cookbook_helpers.php';
require_once __DIR__ . '/recipe_classifier.php';
require_once __DIR__ . '/holiday_helpers.php';
require_once __DIR__ . '/recipe_publisher.php';
/** Потолок AI-запросов на одну кулинарную книгу. */
const COOKBOOK_UNITS = ['г','кг','мл','л','шт','ч.л.','ст.л.','стакан','щепотка','пучок','зубчик','по вкусу'];

// ============================================================
//  ОБЩИЕ УТИЛИТЫ (нормализация названий, лимит времени, блокировка книги)
// ============================================================

/** Ключ для сравнения названий: регистр, «ё», знаки препинания, лишние пробелы не важны. */
function cb_title_key(string $t): string
{
    $t = mb_strtolower(trim(strip_tags($t)));
    $t = str_replace('ё', 'е', $t);
    $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;
    return trim($t);
}

/** Множества id и названий, уже лежащих в книге. */
function cb_book_keys(array $recipes): array
{
    $ids = [];
    $titles = [];
    foreach ($recipes as $r) {
        if (!is_array($r)) continue;
        if (!empty($r['created_recipe_id'])) $ids[(int)$r['created_recipe_id']] = true;
        $k = cb_title_key((string)($r['title'] ?? ''));
        if ($k !== '') $titles[$k] = true;
    }
    return ['ids' => $ids, 'titles' => $titles];
}

/** «До 30 минут» / «Более 1 часа» → ['max'=>30] / ['min'=>60] */
function cb_parse_time_text(string $s): array
{
    $s = mb_strtolower($s);
    if (!preg_match('/(\d+)/u', $s, $m)) return [];
    $n = (int)$m[1];
    if (preg_match('/\d+\s*(?:час|ч(?![\p{L}]))/u', $s)) $n *= 60;
    if ($n <= 0) return [];
    if (preg_match('/(?<![\p{L}])(?:более|свыше|дольше|от)(?![\p{L}])|>/u', $s)) return ['min' => $n];
    return ['max' => $n];
}

/**
 * Лимит общего времени (prep_time + cook_time) по slug тега группы «time».
 * @return array{max:int,min:int}  0 = без ограничения
 */
function cb_time_limit(string $slug): array
{
    static $cache = [];
    $out = ['max' => 0, 'min' => 0];
    if ($slug === '') return $out;
    if (isset($cache[$slug])) return $cache[$slug];

    $parsed = [];
    try {
        $name = db_one("SELECT name FROM recipe_tags WHERE slug = ? AND tag_group = 'time' LIMIT 1", [$slug]);
        if ($name && !empty($name['name'])) $parsed = cb_parse_time_text((string)$name['name']);
    } catch (Throwable $e) {}

    if (!$parsed) {
        if (preg_match('/(?:^|-)(?:do|upto|max)-?(\d+)/i', $slug, $m)) {
            $parsed = ['max' => (int)$m[1] * (preg_match('/chas|hour/i', $slug) ? 60 : 1)];
        } elseif (preg_match('/(?:^|-)(?:bolee|more|min)-?(\d+)/i', $slug, $m)) {
            $parsed = ['min' => (int)$m[1] * (preg_match('/chas|hour/i', $slug) ? 60 : 1)];
        }
    }
    $out['max'] = (int)($parsed['max'] ?? 0);
    $out['min'] = (int)($parsed['min'] ?? 0);
    return $cache[$slug] = $out;
}

/** Условие «рецепт опубликован» с учётом реальных колонок таблицы. */
function cb_published_sql(string $alias = 'r'): string
{
    $cols = holiday_recipe_columns();
    if (isset($cols['is_published'])) return "{$alias}.is_published = 1";
    if (isset($cols['is_public']))    return "{$alias}.is_public = 1";
    return '1=1';
}

/** Название категории по slug ('' если нет). */
function cb_category_name(string $slug): string
{
    if ($slug === '') return '';
    $r = db_one('SELECT name FROM recipe_categories WHERE slug = ? AND is_active = 1', [$slug]);
    return $r ? (string)$r['name'] : '';
}

/** Название тега по slug и группе. */
function cb_tag_name(string $slug, string $group): string
{
    if ($slug === '') return '';
    $r = db_one('SELECT name FROM recipe_tags WHERE slug = ? AND tag_group = ? AND is_active = 1', [$slug, $group]);
    return $r ? (string)$r['name'] : '';
}

/** Запись для recipes_json по рецепту из БД (показывается как «существующий», не как AI). */
function cb_book_entry_from_row(array $r): array
{
    $e = [
        'title'             => (string)$r['title'],
        'created_recipe_id' => (int)$r['id'],
        'created_slug'      => (string)($r['slug'] ?? ''),
        'created_at'        => date('Y-m-d H:i:s'),
    ];
    foreach (['description', 'cuisine', 'difficulty'] as $f) {
        if (isset($r[$f]) && $r[$f] !== '') $e[$f] = (string)$r[$f];
    }
    foreach (['prep_time', 'cook_time', 'servings', 'calories'] as $f) {
        if (isset($r[$f])) $e[$f] = (int)$r[$f];
    }
    return $e;
}

/** Один процесс одновременно меняет recipes_json книги (параллельные AI-запросы). */
function cb_with_book_lock(int $bookId, callable $fn)
{
    $key = 'cbbook_' . $bookId;
    $got = (int)db_value('SELECT GET_LOCK(?, 15)', [$key]);
    if (!$got) throw new RuntimeException('Книга занята, повторите попытку');
    try {
        return $fn();
    } finally {
        db_value('SELECT RELEASE_LOCK(?)', [$key]);
    }
}

// ============================================================
//  ПОИСК В ПРИЛОЖЕНИИ (без AI)
// ============================================================

/** Корень слова для LIKE: «курица» → «кури», «борщ» остаётся «борщ». */
function cb_word_stem(string $w): string
{
    $len = mb_strlen($w);
    if ($len >= 7) return mb_substr($w, 0, $len - 2);
    if ($len >= 5) return mb_substr($w, 0, $len - 1);
    return $w;
}

function cb_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/**
 * Разбор ключевых слов: «суп курица, борщ» → [['суп','кури'], ['борщ']]
 * Запятая/«;» — альтернативы (ИЛИ), пробел внутри фразы — «И».
 */
function cb_keyword_terms(string $keywords): array
{
    $terms = [];
    foreach (preg_split('/[,;\n]+/u', mb_strtolower($keywords)) ?: [] as $chunk) {
        $words = [];
        foreach (preg_split('/\s+/u', trim($chunk)) ?: [] as $w) {
            $w = preg_replace('/[^\p{L}\p{N}-]+/u', '', $w) ?? '';
            if (mb_strlen($w) >= 2) $words[] = cb_word_stem($w);
        }
        if ($words) $terms[] = array_slice($words, 0, 4);
        if (count($terms) >= 6) break;
    }
    return $terms;
}

/**
 * Ищет готовые рецепты по ключевым словам (обязательны) + категория, кухня, диета, время.
 * Сортировка: сначала совпадения в названии, затем по популярности.
 * Дубли по названию и уже имеющиеся в книге рецепты исключаются.
 *
 * @param array $tags  category, cuisine, diet, time (slug'и)
 * @param array<int,int|string> $excludeIds
 * @param array<string,true>    $excludeTitleKeys  cb_title_key() названий, уже лежащих в книге
 * @return array<int,array>  строки recipes (только нужные поля)
 */
function search_recipes_for_cookbook(string $keywords, array $tags, int $limit, array $excludeIds = [], string $notes = '', array $excludeTitleKeys = []): array
{
    $limit = max(1, min(10, $limit));
    $cols  = holiday_recipe_columns();
    $terms = cb_keyword_terms($keywords);
    if (!$terms) return [];

    $where  = [cb_published_sql('r')];
    $params = [];

    // Ключевые слова: (терм1 ИЛИ терм2 ...), внутри терма все слова обязательны
    $hasMetaKw = isset($cols['meta_keywords']);
    $orParts = [];
    $score   = [];
    $scoreParams = [];
    foreach ($terms as $words) {
        $andT = [];   // в названии
        $andAny = []; // в названии/описании
        $tp = [];
        $ap = [];
        foreach ($words as $w) {
            $like = '%' . cb_like_escape($w) . '%';
            $andT[]   = 'r.title LIKE ?';
            $tp[]     = $like;
            $any      = '(r.title LIKE ? OR r.description LIKE ?' . ($hasMetaKw ? ' OR r.meta_keywords LIKE ?' : '') . ')';
            $andAny[] = $any;
            $ap[]     = $like; $ap[] = $like; if ($hasMetaKw) $ap[] = $like;
        }
        $orParts[] = '(' . implode(' AND ', $andAny) . ')';
        foreach ($ap as $p) $params[] = $p;
        $score[]   = '(CASE WHEN ' . implode(' AND ', $andT) . ' THEN 3 ELSE 0 END)';
        foreach ($tp as $p) $scoreParams[] = $p;
    }
    $where[] = '(' . implode(' OR ', $orParts) . ')';

    // Категория
    $category = (string)($tags['category'] ?? '');
    if ($category !== '') {
        $where[]  = 'r.category_id = (SELECT id FROM recipe_categories WHERE slug = ? AND is_active = 1)';
        $params[] = $category;
    }

    // Кухня / диета (теги)
    foreach (['cuisine', 'diet'] as $grp) {
        $slug = (string)($tags[$grp] ?? '');
        if ($slug === '') continue;
        $where[]  = 'EXISTS (SELECT 1 FROM recipe_tag_links l JOIN recipe_tags t ON t.id = l.tag_id
                             WHERE l.recipe_id = r.id AND t.slug = ? AND t.tag_group = ?)';
        $params[] = $slug;
        $params[] = $grp;
    }

    // Время: prep_time + cook_time не больше выбранного
    $lim = cb_time_limit((string)($tags['time'] ?? ''));
    if ($lim['max'] > 0 || $lim['min'] > 0) {
        $where[] = '(COALESCE(r.prep_time,0) + COALESCE(r.cook_time,0)) > 0';
    }
    if ($lim['max'] > 0) {
        $where[]  = '(COALESCE(r.prep_time,0) + COALESCE(r.cook_time,0)) <= ?';
        $params[] = $lim['max'];
    }
    if ($lim['min'] > 0) {
        $where[]  = '(COALESCE(r.prep_time,0) + COALESCE(r.cook_time,0)) > ?';
        $params[] = $lim['min'];
    }

    $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
    if ($excludeIds) {
        $where[] = 'r.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        foreach ($excludeIds as $id) $params[] = $id;
    }

    $fields = 'r.id, r.slug, r.title, r.description, r.cuisine, r.difficulty, r.prep_time, r.cook_time, r.servings, r.calories, r.likes_count';
    $rows = db_all(
        "SELECT {$fields}, (" . implode(' + ', $score) . ') AS cb_score
         FROM recipes r WHERE ' . implode(' AND ', $where) . '
         ORDER BY cb_score DESC, r.likes_count DESC, r.created_at DESC
         LIMIT ' . ($limit * 4),
        array_merge($scoreParams, $params)
    );

    // дубли названий (в БД бывают одинаковые) и то, что уже в книге
    $seen = $excludeTitleKeys;
    $out  = [];
    foreach ($rows as $r) {
        $k = cb_title_key((string)$r['title']);
        if ($k === '' || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/**
 * AI-поиск в приложении: сопоставляет тему с категориями и тегами БД.
 * Если ничего не находит — возвращает пустой массив.
 */
function cb_ai_search_db(string $theme, int $limit, array $excludeIds = []): array
{
    // 1. Получаем категории ВМЕСТЕ С КОЛИЧЕСТВОМ рецептов
    $cats = db_all("
        SELECT c.id, c.name, COUNT(r.id) AS cnt
        FROM recipe_categories c
        LEFT JOIN recipes r ON r.category_id = c.id
        WHERE c.is_active = 1
        GROUP BY c.id
        HAVING cnt > 0
        ORDER BY cnt DESC
    ");
    if (!$cats) return [];

    // 2. Формируем список для AI с указанием количества
    $catList = [];
    foreach ($cats as $c) {
        $catList[] = $c['name'] . ' (' . $c['cnt'] . ' рецептов)';
    }

    $prompt = "Пользователь ищет рецепты по теме: «{$theme}».\n"
        . "Вот доступные категории блюд в нашей базе (название и сколько рецептов в ней):\n- " 
        . implode("\n- ", $catList) . "\n\n"
        . "Выбери ОДНУ или ДВЕ категории, которые лучше всего подходят под тему. "
        . "ВАЖНО: выбирай только категории, в которых есть рецепты. "
        . "Например, если тема 'Ужины', а категории 'Ужины' в базе нет (или она пуста), "
        . "выбери 'Горячие основные блюда' или 'Блюда в духовке / запеканки'. "
        . "Ответь строго в JSON: {\"categories\":[\"Название категории\"]}. "
        . "Если не можешь подобрать — верни {\"categories\":[]}.";

    $schema = '{"categories":["Название"]}';
    
    try {
        $data = yandex_gpt_json($prompt, $schema, 1, 300, 0.1);
    } catch (Throwable $e) {
        error_log('[cb_ai_search_db] AI error: ' . $e->getMessage());
        return [];
    }

    $selectedNames = $data['categories'] ?? [];
    if (!is_array($selectedNames) || empty($selectedNames)) return [];

    // 3. Находим ID категорий (частичное совпадение)
    $ids = [];
    foreach ($selectedNames as $selName) {
        $selLower = mb_strtolower(trim($selName));
        // Убираем " (X рецептов)" из ответа AI, если он это вернул
        $selLower = preg_replace('/\s*\(\d+\s*рецептов?\)/u', '', $selLower);
        
        foreach ($cats as $c) {
            $catLower = mb_strtolower($c['name']);
            if ($catLower === $selLower 
                || mb_strpos($catLower, $selLower) !== false 
                || mb_strpos($selLower, $catLower) !== false) {
                $ids[] = (int)$c['id'];
                break;
            }
        }
    }
    
    if (empty($ids)) {
        error_log('[cb_ai_search_db] AI вернул: ' . json_encode($selectedNames) . ', но ID не найдены');
        return [];
    }

    // 4. Вытаскиваем рецепты
    $where = ['r.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
    $params = $ids;

    $cols = holiday_recipe_columns();
    if (isset($cols['is_published']))  $where[] = 'r.is_published = 1';
    elseif (isset($cols['is_public'])) $where[] = 'r.is_public = 1';

    if ($excludeIds) {
        $where[] = 'r.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        foreach ($excludeIds as $id) $params[] = (int)$id;
    }

    return db_all(
        'SELECT r.* FROM recipes r WHERE ' . implode(' AND ', $where) . '
         ORDER BY r.likes_count DESC, r.created_at DESC LIMIT ' . ($limit * 3),
        $params
    );
}

/** Сразу привязывает найденные рецепты к книге (created_recipe_id/created_slug). */
function link_recipes_to_cookbook(int $bookId, array $recipes, array $rows): array
{
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;

    $added = 0;
    foreach ($recipes as &$rec) {
        if (!empty($rec['created_recipe_id'])) continue;
        $title = mb_strtolower(trim((string)($rec['title'] ?? '')));

        foreach ($byId as $rid => $row) {
            if (mb_strtolower(trim((string)$row['title'])) !== $title) continue;
            $rec['created_recipe_id'] = $rid;
            $rec['created_slug']      = (string)($row['slug'] ?? '');
            $rec['created_at']        = date('Y-m-d H:i:s');
            $added++;
            break;
        }
    }
    unset($rec);

    if ($added > 0) update_cookbook_recipes($bookId, $recipes);
    return ['recipes' => $recipes, 'added' => $added];
}

// ============================================================
//  AI: ПЛАН И РЕЦЕПТ
// ============================================================

/** Контекст книги одной строкой. */
function cb_context_line(array $book, string $theme, array $tags, string $notes): string
{
    $map   = rc_tag_names('cuisine') + rc_tag_names('diet') + rc_tag_names('restriction');
    $parts = ['Книга: ' . $theme];

    foreach ($tags as $group => $slug) {
        $slug = (string)$slug;
        if ($slug === '') continue;
        $name    = $map[$slug] ?? $slug;
        $parts[] = $group . ': ' . $name;
    }
    if ($notes !== '') $parts[] = 'пожелания: ' . $notes;

    return implode('; ', $parts);
}

/**
 * Контекст ограничений для AI из выбора пользователя.
 * @return array{theme:string,category:string,category_name:string,cuisine_name:string,diet_name:string,max:int,min:int,notes:string}
 */
function cb_build_ctx(string $theme, array $tags, string $notes): array
{
    $cat  = (string)($tags['category'] ?? '');
    $time = cb_time_limit((string)($tags['time'] ?? ''));
    return [
        'theme'         => $theme,
        'category'      => $cat,
        'category_name' => cb_category_name($cat),
        'cuisine_name'  => cb_tag_name((string)($tags['cuisine'] ?? ''), 'cuisine'),
        'diet_name'     => cb_tag_name((string)($tags['diet'] ?? ''), 'diet'),
        'max'           => $time['max'],
        'min'           => $time['min'],
        'notes'         => $notes,
    ];
}

/** Короткая строка требований для промпта (экономит токены). */
function cb_ctx_requirements(array $ctx): string
{
    $p = [];
    if (!empty($ctx['category_name'])) $p[] = 'категория: ' . $ctx['category_name'];
    if (!empty($ctx['cuisine_name']))  $p[] = 'кухня: ' . $ctx['cuisine_name'];
    if (!empty($ctx['diet_name']))     $p[] = 'диета: ' . $ctx['diet_name'];
    if (!empty($ctx['max']))           $p[] = 'общее время (prep_time+cook_time) СТРОГО не более ' . (int)$ctx['max'] . ' мин';
    if (!empty($ctx['min']))           $p[] = 'общее время более ' . (int)$ctx['min'] . ' мин';
    if (!empty($ctx['notes']))         $p[] = 'пожелания: ' . $ctx['notes'];
    return $p ? implode('; ', $p) : '';
}

/**
 * ОДИН дешёвый запрос: названия блюд (только строки, без полей) — задаёт уникальность набора.
 * Просит на 2 больше нужного, чтобы после отсева дублей не делать повторный запрос.
 *
 * @param array<string,true> $existingTitles  ключи cb_title_key()
 * @param array<int,string>  $avoidTitles     примеры названий, которых не повторять (до 20)
 * @return array<int,array{title:string}>
 */
function cb_ai_plan(array $book, string $theme, int $count, array $tags, string $notes, array $existingTitles, array $ctx = [], array $avoidTitles = []): array
{
    $count = max(1, min(8, $count));
    if (!$ctx) $ctx = cb_build_ctx($theme, $tags, $notes);
    $ask = $count + 2;

    $req   = cb_ctx_requirements($ctx);
    $avoid = $avoidTitles ? ' Уже есть, не повторяй: ' . implode('; ', array_slice($avoidTitles, 0, 20)) . '.' : '';
    $prompt = "Кулинарная книга «{$theme}». Предложи {$ask} РАЗНЫХ блюд для неё."
        . ($req !== '' ? " Требования: {$req}." : '')
        . $avoid
        . ' Только короткие названия блюд (2–5 слов). Ответ — JSON.';

    $data = yandex_gpt_json($prompt, '{"dishes":["название блюда"]}', 1, 400, 0.4);

    $list  = is_array($data['dishes'] ?? null) ? $data['dishes'] : [];
    $queue = [];
    $seen  = $existingTitles;

    foreach ($list as $item) {
        $t = is_array($item) ? (string)($item['title'] ?? $item['name'] ?? '') : (string)$item;
        $t = preg_replace('/^[\s"\'«»\d.)\-]+|[\s"\'«».]+$/u', '', strip_tags($t));
        if (mb_strlen($t) < 3) continue;
        $t = mb_substr($t, 0, 120);
        $k = cb_title_key($t);
        if ($k === '' || isset($seen[$k])) continue;
        $seen[$k] = true;
        $queue[]  = ['title' => $t];
        if (count($queue) >= $ask) break;
    }
    return $queue;
}

/** Валидирует время рецепта по ограничениям. */
function cb_time_violation(array $recipe, array $ctx): string
{
    $total = (int)($recipe['prep_time'] ?? 0) + (int)($recipe['cook_time'] ?? 0);
    if (!empty($ctx['max']) && $total > (int)$ctx['max']) return "{$total} мин при лимите {$ctx['max']}";
    if (!empty($ctx['min']) && $total <= (int)$ctx['min']) return "{$total} мин, нужно более {$ctx['min']}";
    return '';
}

/**
 * ОДИН запрос: полный рецепт (при нарушении лимита времени — ровно один повтор).
 * Санитизация + классификация в коде.
 */
function cb_ai_recipe(array $book, string $theme, array $tags, string $title, string $notes = '', array $ctx = []): array
{
    if (!$ctx) $ctx = cb_build_ctx($theme, $tags, $notes);
    $req = cb_ctx_requirements($ctx);

    $base = "Рецепт блюда «{$title}» для книги «{$theme}»."
        . ($req !== '' ? " Требования: {$req}." : '')
        . ' Порций: 2. amount — число. unit из списка: ' . implode(', ', COOKBOOK_UNITS) . '.'
        . ' До 12 ингредиентов и 8 коротких шагов. КБЖУ на 1 порцию.';

    $schema = '{"description":"1 предложение","cuisine":"","difficulty":"Легко|Средне|Сложно",'
        . '"prep_time":0,"cook_time":0,"servings":2,'
        . '"calories":0,"proteins":0,"fats":0,"carbs":0,'
        . '"ingredients":[{"name":"","amount":"","unit":""}],'
        . '"steps":[{"instruction":""}]}';

    $build = function (string $prompt) use ($schema, $title, $tags, $ctx): array {
        $data = yandex_gpt_json($prompt, $schema, 1, 1800, 0.4);
        if (isset($data['recipes'][0]) && is_array($data['recipes'][0])) $data = $data['recipes'][0];
        return cb_finalize_ai_recipe($data, $title, $tags, $ctx);
    };

    $recipe = $build($base);
    $viol   = cb_time_violation($recipe, $ctx);
    if ($viol !== '') {
        $recipe = $build($base . " ВНИМАНИЕ: прошлый вариант — {$viol}. Упрости блюдо и уложись в лимит времени.");
        $viol   = cb_time_violation($recipe, $ctx);
        if ($viol !== '') throw new RuntimeException('AI не уложился во время: ' . $viol);
    }
    return $recipe;
}

/** Санитизация ответа AI, классификация, нормализация. */
function cb_finalize_ai_recipe(array $data, string $title, array $tags, array $ctx): array
{
    if (empty($data['ingredients']) || empty($data['steps'])) {
        throw new RuntimeException('AI вернул неполный рецепт');
    }

    $data['title']    = $title;
    $data['servings'] = max(1, (int)($data['servings'] ?? 2));

    $clean = [];
    foreach ((array)$data['ingredients'] as $ing) {
        if (!is_array($ing)) continue;
        $name = trim(strip_tags((string)($ing['name'] ?? '')));
        if ($name === '') continue;
        $unit = mb_strtolower(trim((string)($ing['unit'] ?? 'шт')));
        if (!in_array($unit, COOKBOOK_UNITS, true)) $unit = 'шт';
        $clean[] = [
            'name'   => mb_substr($name, 0, 120),
            'amount' => is_numeric($ing['amount'] ?? null) ? (string)$ing['amount'] : trim(strip_tags((string)($ing['amount'] ?? ''))),
            'unit'   => $unit,
        ];
        if (count($clean) >= 12) break;
    }
    $data['ingredients'] = $clean;

    $steps = [];
    foreach ((array)$data['steps'] as $s) {
        $txt = is_array($s) ? (string)($s['instruction'] ?? '') : (string)$s;
        $txt = trim(strip_tags($txt));
        if ($txt === '') continue;
        $steps[] = ['order' => count($steps) + 1, 'instruction' => mb_substr($txt, 0, 400)];
        if (count($steps) >= 8) break;
    }
    $data['steps'] = $steps;
    if (!$clean || !$steps) throw new RuntimeException('AI вернул неполный рецепт');

    // классификация в КОДЕ — AI slug'и не выдумывает; выбранная пользователем категория главнее
    $requested = array_filter(['category' => $ctx['category'] ?? ''] + $tags, fn($v) => is_string($v) && $v !== '');
    $cls = classify_recipe($data, ['requested' => $requested]);
    $data['category_slug'] = !empty($ctx['category']) ? $ctx['category'] : $cls['category_slug'];
    $data['tag_slugs']     = $cls['tag_slugs'];
    $data['classified_by'] = 'engine';

    return normalize_recipe($data);
}
