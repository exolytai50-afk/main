<?php
/**
 * GigaChef — AI-генерация рецептов для кулинарных книг.
 *
 * Конвейер (по одному короткому запросу на шаг):
 *   1. cb_plan_titles()      — ОДИН запрос: названия блюд (с запасом) + тип + главный продукт.
 *   2. cb_filter_candidates()— код отсеивает дубли с книгой и между собой.
 *   3. cb_generate_recipe()  — ОДИН запрос на каждое блюдо: только рецепт, без таксономии.
 *   4. rc_violations()       — код проверяет диету/«без …»/время; при нарушении один повтор.
 *   5. classify_recipe()     — категория и теги (правила + БД, AI только в крайнем случае).
 *   6. cb_append_recipe()    — запись в книгу на сервере, в транзакции, с повторной проверкой дублей.
 *
 * Клиент не получает права писать рецепты в книгу «как есть»: рецепт на сервер приходит
 * только из этой цепочки. План подписан HMAC-токеном (без сессий и новых таблиц).
 */
declare(strict_types=1);

require_once __DIR__ . '/cookbook_helpers.php';
require_once __DIR__ . '/recipe_guard.php';
require_once __DIR__ . '/recipe_classifier.php';

/** Рецептов, которые один пользователь может сгенерировать в книги за сутки. */
const CB_RECIPES_PER_DAY = 60;

/** Максимум рецептов в книге. */
const CB_BOOK_MAX = 200;

/** Слова, которые не отличают одно блюдо от другого. */
const CB_FILLER_STEMS = ['для', 'под', 'без', 'над', 'при', 'про', 'или', 'дома', 'клас', 'прос', 'быст', 'вкус', 'нежн', 'сочн', 'хруст', 'аром'];

/** Основы «слов-типов»: суп, салат, соус… (сравниваем тип блюда отдельно). */
const CB_HEAD_STEMS = ['сала', 'суп', 'соус', 'пиро', 'рагу', 'запе', 'десе', 'каша', 'паст', 'пицц', 'торт', 'пюре', 'буль', 'кокт', 'напи'];

// ============================================================
//  УСЛОВИЯ ГЕНЕРАЦИИ
// ============================================================

/**
 * Теги из формы → проверенные по БД. Принимает slug или название ("Русская").
 * @return array{tags:array<string,array{slug:string,name:string}>,extra:string[]}
 */
function cb_requested_tags($in): array
{
    $out = ['tags' => [], 'extra' => []];
    if (!is_array($in)) return $out;

    foreach (['cuisine', 'holiday', 'diet', 'restriction', 'season', 'time'] as $group) {
        $raw = $in[$group] ?? '';
        if (!is_scalar($raw)) continue;
        $v = trim((string)$raw);
        if ($v === '') continue;

        $names = rc_tag_names($group);
        if (isset($names[$v])) {
            $out['tags'][$group] = ['slug' => $v, 'name' => $names[$v]];
            continue;
        }
        $found = false;
        foreach ($names as $slug => $name) {
            if (rc_norm($name) === rc_norm($v)) {
                $out['tags'][$group] = ['slug' => $slug, 'name' => $name];
                $found = true;
                break;
            }
        }
        if (!$found) $out['extra'][] = mb_substr(trim(strip_tags($v)), 0, 40);
    }
    return $out;
}

function cb_constraint_line(array $req, string $notes): string
{
    $labels = [
        'cuisine' => 'кухня', 'holiday' => 'повод', 'diet' => 'тип питания',
        'restriction' => 'ограничение', 'season' => 'сезон', 'time' => 'время приготовления',
    ];
    $parts = [];
    foreach ($req['tags'] as $group => $t) {
        $parts[] = ($labels[$group] ?? $group) . ': ' . $t['name'];
    }
    foreach ($req['extra'] as $x) $parts[] = $x;
    if ($notes !== '') $parts[] = 'пожелания: ' . $notes;
    return implode('; ', $parts);
}

function cb_type_to_category(string $type): string
{
    return RC_TYPE_TO_CATEGORY[mb_strtolower(trim($type))] ?? '';
}

// ============================================================
//  ДУБЛИ
// ============================================================

function cb_stems(string $title): array
{
    $out = [];
    foreach (rc_tokens($title) as $t) {
        if (mb_strlen($t) < 3) continue;
        $stem = mb_substr($t, 0, 4);
        if (in_array($stem, CB_FILLER_STEMS, true) || in_array($stem, CB_HEAD_STEMS, true)) continue;
        $out[$stem] = true;
    }
    return array_keys($out);
}

function cb_heads(string $title): array
{
    $out = [];
    foreach (rc_tokens($title) as $t) {
        $stem = mb_substr($t, 0, 4);
        if (in_array($stem, CB_HEAD_STEMS, true)) $out[$stem] = true;
    }
    $heads = array_keys($out);
    sort($heads);
    return $heads;
}

/**
 * Похожи ли два названия.
 *   «Борщ» ≈ «Борщ украинский», «Салат Цезарь» ≈ «Салат Цезарь с курицей»,
 *   но «Куриный суп» ≠ «Куриные котлеты» и «Салат с тунцом» ≠ «Тунец на гриле».
 */
function cb_titles_similar(string $a, string $b): bool
{
    if (normalize_title_for_compare($a) === normalize_title_for_compare($b)) return true;

    $sa = cb_stems($a);
    $sb = cb_stems($b);
    if (!$sa || !$sb) return false;

    $common = count(array_intersect($sa, $sb));
    if ($common === 0) return false;

    $ha = cb_heads($a);
    $hb = cb_heads($b);
    $min = min(count($sa), count($sb));
    $max = max(count($sa), count($sb));

    if ($min >= 2) {
        return ($common / $min) >= 0.75;
    }
    if ($max === 1) {                       // по одному смысловому слову с каждой стороны
        return $ha === $hb || !$ha || !$hb;
    }
    return $max <= 2 && $ha === $hb;       // «Борщ» и «Борщ украинский»
}

function cb_titles_equal(string $a, string $b): bool
{
    return normalize_title_for_compare($a) === normalize_title_for_compare($b);
}

/** Оставляет только блюда, не похожие на уже имеющиеся и друг на друга. */
function cb_filter_candidates(array $cands, array $existingTitles, int $limit): array
{
    $kept = [];
    $pool = $existingTitles;
    foreach ($cands as $c) {
        $dup = false;
        foreach ($pool as $t) {
            if (cb_titles_similar($c['title'], (string)$t)) { $dup = true; break; }
        }
        if ($dup) continue;

        $kept[] = $c;
        $pool[] = $c['title'];
        if (count($kept) >= $limit) break;
    }
    return $kept;
}

// ============================================================
//  AI: ПЛАН БЛЮД
// ============================================================

/**
 * ОДИН запрос: названия блюд. Возвращает [['title','type','main'], ...].
 */
function cb_plan_titles(string $theme, int $want, string $constraints, array $existing): array
{
    $prompt = "Придумай названия блюд для кулинарной книги. Тема: «{$theme}»."
        . ($constraints !== '' ? " Условия: {$constraints}." : '') . "\n"
        . ($existing ? 'Уже есть в книге (не повторять и не придумывать похожие): ' . implode('; ', array_slice($existing, 0, 40)) . ".\n" : '')
        . "Нужно {$want} разных блюд, заметно отличающихся основным продуктом и способом приготовления. "
        . 'Для каждого укажи type (одно из: суп, салат, закуска, горячее, гарнир, десерт, выпечка, завтрак, соус, напиток) '
        . 'и main — главный ингредиент одним словом. Названия на русском, до 6 слов, только названия без рецептов.';
    $schema = '{"dishes":[{"title":"название","type":"горячее","main":"курица"}]}';

    $data = yandex_gpt_json($prompt, $schema, 1, 900, 0.8);
    $list = $data['dishes'] ?? $data['recipes'] ?? (isset($data[0]) ? $data : []);

    $out  = [];
    $seen = [];
    foreach ((array)$list as $d) {
        if (is_string($d)) $d = ['title' => $d];
        if (!is_array($d)) continue;

        $title = preg_replace('/^[\s"\'«»]+|[\s"\'«».]+$/u', '', strip_tags((string)($d['title'] ?? $d['name'] ?? '')));
        $title = mb_substr((string)$title, 0, 120);
        if (mb_strlen($title) < 3) continue;

        $key = normalize_title_for_compare($title);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $out[] = [
            'title' => $title,
            'type'  => mb_strtolower(mb_substr(trim(strip_tags((string)($d['type'] ?? ''))), 0, 20)),
            'main'  => mb_strtolower(mb_substr(trim(strip_tags((string)($d['main'] ?? ''))), 0, 40)),
        ];
    }
    return $out;
}

// ============================================================
//  AI: РЕЦЕПТ
// ============================================================

/** ОДИН запрос: полный рецепт блюда. Таксономии в запросе нет. */
function cb_generate_recipe(string $title, string $constraints, int $servings, array $violations = []): array
{
    $prompt = "Рецепт блюда «{$title}»."
        . ($constraints !== '' ? " Условия: {$constraints}." : '') . "\n"
        . ($violations
            ? 'ВАЖНО: в прошлом варианте нарушены условия (' . implode(', ', $violations) . ') — полностью исключи такие продукты.' . "\n"
            : '')
        . "Порций: {$servings}. Количества ингредиентов считай ровно на {$servings} порц. "
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

/**
 * Рецепт + проверка условий (с одним повтором) + классификация.
 * @param array $plan    данные из токена плана
 * @param array $planned ['title','type','main']
 */
function cb_build_recipe(array $plan, array $planned): array
{
    $requested = (array)($plan['r'] ?? []);            // группа => slug
    $servings  = max(1, (int)($plan['sv'] ?? 2));
    $constr    = (string)($plan['c'] ?? '');

    $check = array_values(array_filter([
        $requested['diet'] ?? null,
        $requested['restriction'] ?? null,
        $requested['time'] ?? null,
    ]));

    $recipe = cb_generate_recipe($planned['title'], $constr, $servings);
    $bad    = $check ? rc_violations($recipe, $check) : [];

    if ($bad) {                                         // один повтор с точным указанием, что убрать
        $recipe = cb_generate_recipe($planned['title'], $constr, $servings, $bad);
        $bad    = rc_violations($recipe, $check);
    }

    $cls = classify_recipe($recipe, [
        'requested'     => $requested,
        'hint_category' => cb_type_to_category((string)($planned['type'] ?? '')),
    ]);

    $recipe['category_slug'] = $cls['category_slug'];
    $recipe['tag_slugs']     = $cls['tag_slugs'];
    $recipe['classified_by'] = 'engine';
    if ($bad) $recipe['warnings'] = $bad;               // условия не удалось выдержать
    return $recipe;
}

// ============================================================
//  ЗАПИСЬ В КНИГУ
// ============================================================

/**
 * Дописывает рецепт в книгу в транзакции; перед записью заново ищет дубли.
 * @return array ['count'=>int] или ['dup'=>string] (название похожего блюда)
 */
function cb_append_recipe(int $bookId, array $recipe): array
{
    return db_transaction(function () use ($bookId, $recipe): array {
        $row = db_one('SELECT recipes_json FROM cookbooks WHERE id = ? FOR UPDATE', [$bookId]);
        if (!$row) throw new RuntimeException('Книга не найдена');

        $recipes = json_field($row['recipes_json']);
        if (count($recipes) >= CB_BOOK_MAX) throw new RuntimeException('В книге уже ' . CB_BOOK_MAX . ' рецептов');

        foreach ($recipes as $r) {
            $t = (string)($r['title'] ?? '');
            if (cb_titles_similar((string)$recipe['title'], $t)
                || ingredients_similarity((array)$recipe['ingredients'], (array)($r['ingredients'] ?? [])) >= 0.8) {
                return ['dup' => $t];
            }
        }

        $recipes[] = $recipe;
        db_exec(
            'UPDATE cookbooks SET recipes_json = ?, updated_at = NOW() WHERE id = ?',
            [json_encode($recipes, JSON_UNESCAPED_UNICODE), $bookId]
        );
        return ['count' => count($recipes)];
    });
}

// ============================================================
//  ТОКЕН ПЛАНА (HMAC, без сессий)
// ============================================================

function cb_secret(): string
{
    return (defined('CAPTCHA_SECRET') ? (string)CAPTCHA_SECRET : '') . '|cookbook-ai';
}

function cb_token_encode(array $payload): string
{
    $b64 = rtrim(strtr(base64_encode((string)json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    return $b64 . '.' . hash_hmac('sha256', $b64, cb_secret());
}

function cb_token_decode(string $token): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    if (!hash_equals(hash_hmac('sha256', $parts[0], cb_secret()), $parts[1])) return null;

    $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
    $data = $json !== false ? json_decode($json, true) : null;
    return is_array($data) ? $data : null;
}

// ============================================================
//  СУТОЧНЫЙ СЧЁТЧИК (общая таблица ai_usage)
// ============================================================

function cb_usage_get(string $identifier, string $feature): int
{
    try {
        $row = db_one(
            'SELECT SUM(used_count) AS total FROM ai_usage WHERE identifier = ? AND feature = ? AND used_date = ?',
            [$identifier, $feature, date('Y-m-d')]
        );
        return (int)($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function cb_usage_inc(string $identifier, string $feature): void
{
    try {
        db_exec(
            'INSERT INTO ai_usage (identifier, feature, used_date, used_count) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE used_count = used_count + 1',
            [$identifier, $feature, date('Y-m-d')]
        );
    } catch (Throwable $e) {
        error_log('cb_usage_inc: ' . $e->getMessage());
    }
}
