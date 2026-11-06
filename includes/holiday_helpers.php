<?php
/**
 * GigaChef — хелперы раздела «Праздники».
 *
 * Подключать после config.php:
 *   require_once __DIR__ . '/../includes/holiday_helpers.php';
 *
 * Зависит от: functions.php (json_field, get_all_tags_by_group),
 *             cookbook_helpers.php (normalize_recipe и др.),
 *             yandex.php (yandex_gpt_json).
 */
declare(strict_types=1);

require_once __DIR__ . '/cookbook_helpers.php';

// ============================================================
//  СПРАВОЧНИКИ
// ============================================================

/** Разделы стола: подпись, иконка, категории рецептов в базе. */
const HOLIDAY_COURSES = [
    'appetizer' => ['label' => 'Закуски', 'icon' => 'appetizer', 'cats' => ['zakuski-i-kanape']],
    'salad'     => ['label' => 'Салаты',  'icon' => 'salad',     'cats' => ['salaty-i-holodnye']],
    'hot'       => ['label' => 'Горячее', 'icon' => 'main',      'cats' => ['goryachie-osnovnye', 'duhovka-i-zapecanki', 'gril-i-barbekyu']],
    'side'      => ['label' => 'Гарниры', 'icon' => 'side',      'cats' => ['garniry']],
    'dessert'   => ['label' => 'Десерты', 'icon' => 'dessert',   'cats' => ['deserty']],
];

const HOLIDAY_BUDGETS = [
    'eco'     => ['label' => 'Эконом',      'short' => 'Простые продукты',    'hint' => 'простые доступные продукты, без деликатесов'],
    'mid'     => ['label' => 'Средний',     'short' => 'Золотая середина',    'hint' => 'обычные продукты, один-два акцентных ингредиента'],
    'festive' => ['label' => 'Праздничный', 'short' => 'Деликатесы и эффект', 'hint' => 'деликатесы и эффектная подача допустимы'],
];

const HOLIDAY_AGES = [
    'adults' => 'Взрослые',
    'kids'   => 'Дети',
    'mixed'  => 'Взрослые и дети',
];

/** Максимум AI-запросов на один праздник (защита от перебора через API). */
const HOLIDAY_MAX_AI_CALLS = 25;

/**
 * Поводы: теги группы holiday из базы + поводы без тега (ищем по категориям).
 * @return array<string,array{slug:string,name:string,icon:string,tag:bool}>
 */
function holiday_occasions(): array
{
    static $list = null;
    if ($list !== null) return $list;

    $list = [];
    foreach (get_all_tags_by_group()['holiday'] ?? [] as $t) {
        $list[$t['slug']] = [
            'slug' => $t['slug'],
            'name' => $t['name'],
            'icon' => !empty($t['icon_svg']) ? $t['icon_svg'] : 'holiday',
            'tag'  => true,
        ];
    }

    $extras = [
        'zvanyj-uzhin'           => ['Званый ужин', 'dinner'],
        'druzheskaya-vecherinka' => ['Дружеская вечеринка', 'friends'],
    ];
    foreach ($extras as $slug => $meta) {
        if (!isset($list[$slug])) {
            $list[$slug] = ['slug' => $slug, 'name' => $meta[0], 'icon' => $meta[1], 'tag' => false];
        }
    }
    return $list;
}

/** Диеты и ограничения для чипов формы. */
function holiday_diet_options(): array
{
    $by  = get_all_tags_by_group();
    $out = [];
    foreach (['diet' => 'Диета', 'restriction' => 'Без чего'] as $group => $label) {
        $tags = [];
        foreach ($by[$group] ?? [] as $t) {
            $tags[] = ['slug' => $t['slug'], 'name' => $t['name']];
        }
        if ($tags) $out[] = ['key' => $group, 'label' => $label, 'tags' => $tags];
    }
    return $out;
}

/** slug => название для диет и ограничений. */
function holiday_diet_map(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (holiday_diet_options() as $g) {
        foreach ($g['tags'] as $t) $map[$t['slug']] = $t['name'];
    }
    return $map;
}

// ============================================================
//  ЧТЕНИЕ / ФОРМАТ ДЛЯ КЛИЕНТА
// ============================================================

function holiday_find_owned(int $id, int $userId): ?array
{
    return db_one('SELECT * FROM holidays WHERE id = ? AND user_id = ?', [$id, $userId]);
}

function holiday_public(array $h): array
{
    return [
        'id'            => (int)$h['id'],
        'slug'          => $h['slug'],
        'title'         => $h['title'],
        'occasion_slug' => $h['occasion_slug'],
        'occasion_name' => $h['occasion_name'],
        'event_date'    => $h['event_date'],
        'guests'        => (int)$h['guests'],
        'budget'        => $h['budget'],
        'age_group'     => $h['age_group'],
        'plan'          => json_field($h['plan_json'] ?? null),
        'stage'         => (int)$h['stage'],
    ];
}

function holiday_item_public(array $row): array
{
    $rec = json_field($row['recipe_json'] ?? null);
    return [
        'id'                => (int)$row['id'],
        'course'            => $row['course'],
        'source'            => $row['source'],
        'recipe_id'         => $row['recipe_id'] !== null ? (int)$row['recipe_id'] : null,
        'slug'              => $row['recipe_slug'],
        'title'             => $row['title'],
        'servings'          => (int)$row['servings'],
        'description'       => mb_substr((string)($rec['description'] ?? ''), 0, 160),
        'total_time'        => (int)($rec['prep_time'] ?? 0) + (int)($rec['cook_time'] ?? 0),
        'calories'          => (int)($rec['calories'] ?? 0),
        'difficulty'        => (string)($rec['difficulty'] ?? 'Средне'),
        'ingredients_count' => count((array)($rec['ingredients'] ?? [])),
    ];
}

function holiday_items(int $holidayId): array
{
    $rows = db_all('SELECT * FROM holiday_recipes WHERE holiday_id = ? ORDER BY id', [$holidayId]);
    return array_map('holiday_item_public', $rows);
}

function holiday_insert_item(
    int $holidayId, string $course, string $source,
    ?int $recipeId, ?string $slug, array $rec, int $sort = 0
): int {
    return db_insert(
        'INSERT INTO holiday_recipes
            (holiday_id, course, source, recipe_id, recipe_slug, title, servings, sort_order, recipe_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [
            $holidayId, $course, $source, $recipeId, $slug,
            mb_substr((string)$rec['title'], 0, 200),
            (int)$rec['servings'], $sort,
            json_encode($rec, JSON_UNESCAPED_UNICODE),
        ]
    );
}

// ============================================================
//  ПЛАН МЕНЮ
// ============================================================

/** Сколько блюд нужно в каждом разделе. */
function holiday_course_plan(int $guests, string $budget, string $age, string $occasion): array
{
    if ($guests <= 4) {
        $p = ['appetizer' => 2, 'salad' => 3, 'hot' => 2, 'side' => 1, 'dessert' => 1];
    } elseif ($guests <= 10) {
        $p = ['appetizer' => 3, 'salad' => 4, 'hot' => 3, 'side' => 1, 'dessert' => 1];
    } else {
        $p = ['appetizer' => 4, 'salad' => 5, 'hot' => 4, 'side' => 1, 'dessert' => 2];
    }

    if ($budget === 'eco') {
        $p['appetizer'] = min($p['appetizer'], 2);
        $p['dessert']   = 1;
    } elseif ($budget === 'festive') {
        $p['appetizer'] += 1;
        $p['dessert']    = min(3, $p['dessert'] + 1);
        $p['side']       = max(1, $p['side']);
    }

    if ($occasion === 'romanticheskij-uzhin') {
        $p = ['appetizer' => 1, 'salad' => 1, 'hot' => 1, 'side' => 0, 'dessert' => 1];
    }
    return $p;
}

/**
 * На сколько порций готовить одно блюдо раздела:
 * чем больше блюд в разделе, тем меньшая доля гостей берёт каждое.
 */
function holiday_dish_servings(int $guests, int $dishesInCourse): int
{
    $share = $dishesInCourse <= 1 ? 1.0 : ($dishesInCourse === 2 ? 0.65 : 0.5);
    return max(2, (int)ceil($guests * $share));
}

// ============================================================
//  ПОИСК ГОТОВЫХ РЕЦЕПТОВ В БАЗЕ
// ============================================================

function holiday_recipe_columns(): array
{
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    try {
        foreach (db_all('SHOW COLUMNS FROM recipes') as $c) $cols[$c['Field']] = true;
    } catch (Throwable $e) {
        error_log('holiday_recipe_columns: ' . $e->getMessage());
    }
    return $cols;
}

/**
 * Подбирает рецепты для раздела праздника с помощью AI.
 * AI анализирует повод, гостей и бюджет, и выбирает, в каких категориях искать.
 * Если в базе нет подходящих — возвращает [] (тогда запустится генерация).
 */
function holiday_search_recipes(array $h, string $course, int $limit, array $excludeIds = []): array
{
    $limit = max(1, min(10, $limit));
    $cats  = HOLIDAY_COURSES[$course]['cats'] ?? [];
    if (!$cats || $limit <= 0) return [];

    // 1. Формируем описание праздника
    $budget = HOLIDAY_BUDGETS[$h['budget']] ?? HOLIDAY_BUDGETS['mid'];
    $theme  = 'Повод: ' . $h['occasion_name']
            . '. Гостей: ' . (int)$h['guests']
            . '. Бюджет: ' . $budget['label']
            . '. Раздел: ' . (HOLIDAY_COURSES[$course]['label'] ?? $course);
    if (!empty($h['notes'])) $theme .= '. Пожелания: ' . $h['notes'];

    // 2. Запрашиваем AI, чтобы выбрать конкретные подкатегории
    $catNames = [];
    foreach ($cats as $slug) {
        $catNames[] = $slug;
    }
    // Превращаем slug'и в названия (для промпта)
    $catRows = db_all("SELECT slug, name FROM recipe_categories WHERE slug IN ('" . implode("','", $cats) . "') AND is_active = 1");
    $catList = [];
    foreach ($catRows as $r) {
        $catList[] = $r['name'];
    }
    if (!$catList) return [];

    $prompt = "Пользователь составляет меню для праздника.\n"
        . "{$theme}.\n"
        . "Нужно подобрать рецепты для раздела «" . HOLIDAY_COURSES[$course]['label'] . "».\n"
        . "Доступные категории блюд:\n- " . implode("\n- ", $catList) . "\n\n"
        . "Выбери ОДНУ или ДВЕ категории из этого списка, которые лучше всего подходят. "
        . "Если для этого раздела нет подходящих категорий, верни пустой массив.";

    $schema = '{"categories":["Название"]}';
    
    try {
        $data = yandex_gpt_json($prompt, $schema, 1, 300, 0.1);
    } catch (Throwable $e) {
        error_log('[holiday_search_recipes] AI error: ' . $e->getMessage());
        $data = ['categories' => []];
    }

    $selectedNames = $data['categories'] ?? [];
    if (!is_array($selectedNames) || empty($selectedNames)) {
        // Если AI не выбрал — берем ВСЕ категории раздела
        $selectedNames = $catList;
    }

    // 3. Находим ID выбранных категорий
    $ids = [];
    foreach ($selectedNames as $sel) {
        $selLower = mb_strtolower(trim($sel));
        foreach ($catRows as $r) {
            if (mb_strtolower($r['name']) === $selLower || mb_strpos(mb_strtolower($r['name']), $selLower) !== false) {
                $ids[] = (int)db_value("SELECT id FROM recipe_categories WHERE slug = ?", [$r['slug']]);
                break;
            }
        }
    }
    if (empty($ids)) {
        // Если не нашли — берем все
        foreach ($catRows as $r) {
            $ids[] = (int)db_value("SELECT id FROM recipe_categories WHERE slug = ?", [$r['slug']]);
        }
    }
    $ids = array_unique($ids);
    if (empty($ids)) return [];

    // 4. Вытаскиваем рецепты из этих категорий
    $cols = holiday_recipe_columns();
    $where = ['r.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
    $params = $ids;

    if (isset($cols['is_published']))  $where[] = 'r.is_published = 1';
    elseif (isset($cols['is_public'])) $where[] = 'r.is_public = 1';

    if ($excludeIds) {
        $where[] = 'r.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        foreach ($excludeIds as $id) $params[] = (int)$id;
    }

    $rows = db_all(
        'SELECT r.* FROM recipes r WHERE ' . implode(' AND ', $where) . '
         ORDER BY RAND() LIMIT ' . ($limit * 4),
        $params
    );

    // 5. Сортируем по бюджету
    foreach ($rows as &$r) {
        $r['_ing'] = count(json_field($r['ingredients'] ?? null));
    }
    unset($r);
    if ($h['budget'] === 'eco')         usort($rows, fn($a, $b) => $a['_ing'] <=> $b['_ing']);
    elseif ($h['budget'] === 'festive') usort($rows, fn($a, $b) => $b['_ing'] <=> $a['_ing']);

    return array_slice($rows, 0, $limit);
}

// ============================================================
//  МАСШТАБИРОВАНИЕ РЕЦЕПТА ПОД ЧИСЛО ПОРЦИЙ
// ============================================================

function holiday_fmt_num(float $v): string
{
    if     ($v >= 20) $v = round($v / 5) * 5;
    elseif ($v >= 5)  $v = round($v);
    else              $v = round($v * 2) / 2;
    if ($v <= 0) $v = 0.5;
    return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
}

/** «200», «1/2», «1 1/2», «2-3» → пересчитанное значение; «по вкусу» и пустое не трогаем. */
function holiday_scale_amount(string $amount, float $factor): string
{
    $a = trim(str_replace(',', '.', $amount));
    if ($a === '' || abs($factor - 1.0) < 0.01) return $amount;

    if (preg_match('/^(\d+(?:\.\d+)?)\s*[-–]\s*(\d+(?:\.\d+)?)$/u', $a, $m)) {
        return holiday_fmt_num((float)$m[1] * $factor) . '–' . holiday_fmt_num((float)$m[2] * $factor);
    }
    if (preg_match('/^(?:(\d+)\s+)?(\d+)\/(\d+)$/', $a, $m) && (int)$m[3] > 0) {
        return holiday_fmt_num(((int)$m[1] + (int)$m[2] / (int)$m[3]) * $factor);
    }
    if (is_numeric($a)) {
        return holiday_fmt_num((float)$a * $factor);
    }
    return $amount;
}

/** Приводит строку из recipes к компактному виду и пересчитывает ингредиенты. */
function holiday_scale_recipe(array $r, int $servings): array
{
    $base   = max(1, (int)($r['servings'] ?? 2));
    $factor = $servings / $base;

    $ings = normalize_ingredients(json_field($r['ingredients'] ?? null));
    foreach ($ings as &$i) {
        $i['amount'] = holiday_scale_amount((string)$i['amount'], $factor);
    }
    unset($i);

    return [
        'title'       => trim((string)$r['title']),
        'description' => trim((string)($r['description'] ?? '')),
        'cuisine'     => trim((string)($r['cuisine'] ?? '')),
        'difficulty'  => normalize_difficulty((string)($r['difficulty'] ?? 'medium')),
        'prep_time'   => (int)($r['prep_time'] ?? 0),
        'cook_time'   => (int)($r['cook_time'] ?? 0),
        'servings'    => $servings,
        'calories'    => (int)($r['calories'] ?? 0),
        'proteins'    => (float)($r['proteins'] ?? 0),
        'fats'        => (float)($r['fats'] ?? 0),
        'carbs'       => (float)($r['carbs'] ?? 0),
        'ingredients' => $ings,
        'steps'       => normalize_steps(json_field($r['steps'] ?? null)),
    ];
}

// ============================================================
//  AI: КОНТЕКСТ И ПРОМПТЫ (компактные, по одному на действие)
// ============================================================

/** Одна строка контекста — общая для всех этапов. */
function holiday_context_line(array $h): string
{
    $b   = HOLIDAY_BUDGETS[$h['budget']] ?? HOLIDAY_BUDGETS['mid'];
    $map = holiday_diet_map();

    $dietNames = [];
    foreach (json_field($h['diet_json'] ?? null) as $slug) {
        if (isset($map[$slug])) $dietNames[] = $map[$slug];
    }

    $parts = [
        'Повод: ' . $h['occasion_name'],
        'гостей: ' . (int)$h['guests'],
        'бюджет: ' . $b['label'] . ' (' . $b['hint'] . ')',
        'гости: ' . mb_strtolower(HOLIDAY_AGES[$h['age_group']] ?? 'Взрослые'),
    ];
    if ($dietNames)         $parts[] = 'учесть: ' . implode(', ', $dietNames);
    if (!empty($h['notes'])) $parts[] = 'пожелания: ' . $h['notes'];

    return implode('; ', $parts);
}

/**
 * ОДИН запрос: названия блюд для незакрытых мест меню (без рецептов).
 * @param array<string,int> $need  course => сколько блюд нужно
 * @return array<int,array{course:string,title:string}>
 */
function holiday_ai_menu_gaps(array $h, array $need, array $existingTitles): array
{
    $lines = [];
    $shape = [];
    foreach ($need as $course => $n) {
        $lines[] = $course . ' (' . HOLIDAY_COURSES[$course]['label'] . ') — ' . $n . ' шт.';
        $shape[] = '"' . $course . '":["название"]';
    }

    $prompt = 'Составь меню праздничного стола. ' . holiday_context_line($h) . ".\n"
        . ($existingTitles ? 'Уже в меню: ' . implode('; ', $existingTitles) . ".\n" : '')
        . "Нужны ТОЛЬКО названия блюд (без рецептов) по разделам:\n" . implode("\n", $lines) . "\n"
        . 'Блюда разные, подходят к поводу, бюджету и гостям, готовятся дома. Названия на русском, до 6 слов.';
    $schema = '{"courses":{' . implode(',', $shape) . '}}';

    $data    = yandex_gpt_json($prompt, $schema, 1, 700, 0.6);
    $courses = is_array($data['courses'] ?? null) ? $data['courses'] : $data;

    $seen  = array_map('mb_strtolower', $existingTitles);
    $queue = [];
    foreach ($need as $course => $n) {
        $list = $courses[$course] ?? [];
        if (!is_array($list)) $list = [$list];

        $taken = 0;
        foreach ($list as $t) {
            if ($taken >= $n) break;
            if (is_array($t)) $t = $t['title'] ?? $t['name'] ?? '';
            $t = preg_replace('/^[\s"\'«»]+|[\s"\'«».]+$/u', '', strip_tags((string)$t));
            if (mb_strlen($t) < 3) continue;
            $t = mb_substr($t, 0, 120);
            if (in_array(mb_strtolower($t), $seen, true)) continue;

            $seen[]  = mb_strtolower($t);
            $queue[] = ['course' => $course, 'title' => $t];
            $taken++;
        }
    }
    return $queue;
}

/** ОДИН запрос: полный рецепт одного блюда сразу на нужное число порций. */
function holiday_ai_recipe(array $h, string $course, string $title, int $servings): array
{
    $prompt = 'Рецепт блюда «' . $title . '» (' . HOLIDAY_COURSES[$course]['label'] . '). '
        . holiday_context_line($h) . ".\n"
        . "Порций: {$servings}. Количества ингредиентов считай ровно на {$servings} порций. "
        . 'amount — только число (дроби записывай десятичными), unit — одно из: '
        . 'г, кг, мл, л, шт, ч.л., ст.л., стакан, щепотка, пучок, зубчик, по вкусу. '
        . 'Не более 12 ингредиентов и 8 коротких шагов. calories, proteins, fats, carbs — на 1 порцию.';

    $schema = '{"title":"","description":"1-2 предложения","cuisine":"кухня",'
        . '"difficulty":"Легко|Средне|Сложно","prep_time":0,"cook_time":0,"servings":0,'
        . '"calories":0,"proteins":0,"fats":0,"carbs":0,'
        . '"ingredients":[{"name":"","amount":"","unit":""}],'
        . '"steps":[{"order":1,"instruction":"","tip":""}]}';

    $data = yandex_gpt_json($prompt, $schema, 1, 2500, 0.4);
    if (isset($data['recipes'][0]) && is_array($data['recipes'][0])) {
        $data = $data['recipes'][0];
    }
    if (empty($data['ingredients']) || empty($data['steps'])) {
        throw new RuntimeException('AI вернул неполный рецепт');
    }

    $data['title']    = $title;
    $data['servings'] = $servings;
    return normalize_recipe($data);
}
