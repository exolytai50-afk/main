<?php
/**
 * GigaChef — единый классификатор рецептов: категория + теги.
 *
 * Принцип: всё, что можно решить кодом, решает код.
 *   • время и сложность  — из чисел рецепта;
 *   • диета и «без …»    — по составу (ключевые слова + исключения);
 *   • сезон, повод       — по названию;
 *   • кухня              — из выбора пользователя или поля «cuisine» (по названию тега в БД);
 *   • категория          — по правилам названия; если правила не сработали —
 *                          один крошечный запрос к AI: «выбери номер из списка».
 * Все slug'и сверяются с recipe_categories / recipe_tags в БД — выдумать их нельзя.
 *
 * Использование:
 *   require_once __DIR__ . '/recipe_classifier.php';
 *   $r = classify_recipe($recipe, ['requested' => ['diet' => 'veganskaya'], 'hint_category' => '']);
 *   // $r = ['category_slug' => ..., 'tag_slugs' => [...], 'confidence' => 1.0, 'source' => 'rule']
 */
declare(strict_types=1);

/** Категории-«приёмы пищи» и редкие: правилами не назначаем, AI не предлагаем. */
const RC_AI_EXCLUDE = ['uzhiny', 'obedy', 'detskoe-menyu', 'zagotovki-polufabrikaty', 'konservaciya'];

const RC_COCKTAIL_WORDS = ['коктейл', 'мохито', 'спритц', 'дайкири', 'негрони', 'космополитен', 'пина колада', 'белый русский', 'маргарита'];

/**
 * Слова-маркеры состава. Синтаксис ключа:
 *   'слово'  — начало слова ('говяд' → говядина, говяжий);
 *   '=слово' — слово целиком ('=сыр').
 * ё всегда сравнивается как е.
 */
const RC_WORDS = [
    'meat'    => ['говяд', 'свинин', 'баранин', 'телятин', 'фарш', 'курин', 'куриц', 'цыпл', 'индейк', 'утк', 'утин',
                  '=гусь', 'гусин', 'окорок', 'грудк', 'бекон', 'колбас', 'сосиск', 'ветчин', '=сало', 'мяс', 'ребр'],
    'red'     => ['говяд', 'свинин', 'баранин', 'телятин', 'фарш', 'бекон', 'колбас', 'сосиск', 'ветчин', '=сало', 'ребр', 'окорок'],
    'pork'    => ['свинин', 'бекон', '=сало', 'ветчин', 'окорок'],
    'beef'    => ['говяд', 'телятин'],
    'chicken' => ['курин', 'куриц', 'цыпл'],
    'fish'    => ['рыб', 'лосос', 'семг', 'форел', 'тунец', 'тунц', 'сельд', 'треск', 'судак', 'минта', 'скумбри',
                  'шпрот', 'анчоус', 'икр', 'сайр', 'горбуш', 'палтус', 'дорад', 'сибас'],
    'seafood' => ['креветк', 'кальмар', 'мидии', '=краб', 'крабов', 'устриц', 'осьминог', 'морепродукт', 'гребешк'],
    'dairy'   => ['молок', 'молоч', 'сливк', 'сметан', 'творог', 'творожн', '=сыр', 'сыра', 'сыры', 'сыров', 'сырок',
                  'сливочн', 'кефир', 'йогурт', 'моцарел', 'пармезан', 'маскарпоне', 'рикотт', 'брынз', 'ряженк',
                  'простокваш', 'мороженое'],
    'egg'     => ['яйц', 'яичн', 'майонез', 'меренг'],
    'gluten'  => ['мук', 'макарон', 'спагетти', 'лапш', 'пенне', 'вермишел', 'фузилли', 'феттучин', 'хлеб', 'батон',
                  'багет', 'сухар', 'панировочн', '=манка', 'манн', 'булгур', 'кускус', 'ячмен', 'перловк', 'пшениц',
                  'семолин', 'тест', 'лаваш', 'тортиль', 'пельмен', 'вареник', 'крекер', 'печенье', 'бисквит', 'вафл',
                  'булочк', 'тост', 'пиво', 'солод', 'сейтан'],
    'nuts'    => ['орех', 'миндал', 'фундук', 'кешью', 'арахис', 'фисташк', 'грецк', 'кедров', 'пекан'],
    'soy'     => ['соев', 'тофу', 'эдамаме', 'мисо', 'темпе'],
    'honey'   => ['=мед', '=меда', '=медом', 'медов'],
    'alcohol' => ['водк', '=ром', 'виски', 'джин', '=вино', 'вина', 'вине', 'винн', 'шампанск', 'просекко', 'текил',
                  'ликер', 'коньяк', 'пиво', 'бренди', 'вермут', 'мартини', 'аперол', 'сидр', 'самбук', 'абсент', 'бурбон'],
];

/**
 * Категории по названию. Порядок = приоритет (конкретное раньше общего).
 * Сначала проверяется ПЕРВОЕ слово названия («Заправка для салата» — соус, а не салат).
 */
const RC_CATEGORY_RULES = [
    ['detskoe-menyu',         ['детск']],
    ['napitki-bezalkogolnye', ['=лимонад', '=компот', '=морс', 'смузи', '=кисель', '=квас', '=чай', '=кофе', '=какао', '=напиток', '=фреш', '=латте', '=капучино']],
    ['alkogolnye-napitki',    ['глинтвейн', 'настойк', 'ликер', '=грог', '=сангрия', '=вино', '=пиво', '=сидр', '=пунш']],
    ['supy-i-bulony',         ['суп', 'борщ', '=щи', '=щей', 'окрошк', '=уха', '=ухи', 'солянк', 'похлебк', 'шурпа', 'шулюм', 'бульон', 'гаспачо', 'рассольник', 'харчо', 'лагман', 'минестроне', 'том ям', 'холодник']],
    ['sousy-dipy-zapravki',   ['=соус', 'соусы', 'майонез', 'кетчуп', 'песто', '=дип', 'заправк', 'аджик', 'гуакамоле', 'хумус', 'тапенад', 'аиоли', 'бешамель', 'чимичурри', '=сальса', 'подлив']],
    ['salaty-i-holodnye',     ['салат', 'винегрет', 'оливье', 'цезарь', 'под шубой']],
    ['deserty',               ['торт', 'пирожн', 'мороженое', 'чизкейк', 'тирамису', 'пудинг', 'панна', '=желе', 'мусс', 'сорбет', 'парфе', 'брауни', 'эклер', 'кекс', 'маффин', 'капкейк', 'шарлотк', 'профитрол', 'трюфел', 'конфет', '=суфле', 'десерт', 'фондан', 'меренг', 'пахлав', 'зефир', 'мармелад', 'печенье', 'печенья']],
    ['vypechka-i-hleb',       ['пирог', 'пирожк', '=хлеб', 'булочк', 'булк', 'круассан', '=блин', 'блины', 'блинчик', 'оладь', 'ватрушк', 'лепешк', 'фокач', 'чебурек', 'самс', 'кулич', 'пончик', 'бублик', 'багет', 'пышк', 'хачапури', 'расстегай', 'кныш', 'слойк', 'тесто']],
    ['zavtraki-i-branchi',    ['омлет', 'яичниц', 'глазунь', 'шакшук', '=каша', '=каши', 'овсянк', 'сырник', '=тост', 'тосты', 'гранол', 'мюсли', 'завтрак', 'скрэмбл', 'творожник', 'драник']],
    ['gril-i-barbekyu',       ['шашлык', 'барбекю', '=гриль', '=гриле', 'грилл', 'мангал', 'люля', 'кебаб', 'на углях']],
    ['fastfud-i-stritfud',    ['бургер', 'шаурм', 'шаверм', 'хот-дог', 'хот дог', '=фри', 'наггетс', 'донер', 'буррито', 'кесадил', '=тако', 'начос', 'сэндвич', 'сендвич']],
    ['vo-frityure',           ['фритюр']],
    ['na-paru',               ['на пару', 'паров', 'пароварк']],
    ['konservaciya',          ['консервац', 'соленья', 'соленые', 'маринован', 'закатк', 'варенье', '=джем', 'квашен', 'засолк', 'на зиму']],
    ['zakuski-i-kanape',      ['канапе', 'тарталетк', 'бутерброд', 'брускетт', 'закуск', 'рулетик', 'суши', 'ролл', 'сашими', 'паштет', 'намазк', 'фаршированные яйца', 'яйца фаршированные', 'карпаччо', 'холодец', 'заливное', 'кростини', 'пинчос', 'тапас', 'нарезк', 'шпажк', '=гренки']],
    ['goryachie-osnovnye',    ['лазань', 'пицц', 'карбонар', 'ризотто', 'плов', '=паста', 'спагетти', 'пенне', 'стейк', 'жаркое', '=рагу', 'гуляш', 'котлет', 'тефтел', 'отбивн', 'бефстроганов', 'шницел', 'макарон', '=азу', 'пельмен', 'вареник', 'манты', 'хинкали']],
    ['garniry',               ['пюре', 'гарнир', 'гречк', 'булгур', 'кускус', 'полент', '=рис', 'по-деревенски', 'по-селянски']],
    ['duhovka-i-zapecanki',   ['запеканк', 'гратен', 'в горшочк', 'в духовке', 'запечен', 'в рукаве', 'в фольге']],
];

/** Тип блюда из плана (type) → категория. Используется как подсказка, если правила не сработали. */
const RC_TYPE_TO_CATEGORY = [
    'суп' => 'supy-i-bulony', 'салат' => 'salaty-i-holodnye', 'закуска' => 'zakuski-i-kanape',
    'горячее' => 'goryachie-osnovnye', 'гарнир' => 'garniry', 'десерт' => 'deserty',
    'выпечка' => 'vypechka-i-hleb', 'завтрак' => 'zavtraki-i-branchi', 'соус' => 'sousy-dipy-zapravki',
    'напиток' => 'napitki-bezalkogolnye',
];

// ============================================================
//  СПРАВОЧНИКИ ИЗ БД (единственный источник правды)
// ============================================================

/** slug => название активных категорий. */
function rc_categories(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (get_all_categories() as $c) $m[(string)$c['slug']] = (string)$c['name'];
    return $m;
}

/** slug => группа для всех активных тегов. */
function rc_tag_map(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (get_all_tags_by_group() as $group => $tags) {
        foreach ($tags as $t) $m[(string)$t['slug']] = (string)$group;
    }
    return $m;
}

/** slug => название тегов одной группы. */
function rc_tag_names(string $group): array
{
    $out = [];
    foreach (get_all_tags_by_group()[$group] ?? [] as $t) $out[(string)$t['slug']] = (string)$t['name'];
    return $out;
}

// ============================================================
//  РАЗБОР ТЕКСТА
// ============================================================

function rc_norm(string $s): string
{
    return str_replace('ё', 'е', mb_strtolower($s));
}

function rc_tokens(string $s): array
{
    $parts = preg_split('/[^\p{L}\p{N}]+/u', rc_norm($s)) ?: [];
    return array_values(array_filter($parts, fn($t) => $t !== ''));
}

/** Совпадает ли текст хотя бы с одним ключом (синтаксис ключей — см. RC_WORDS). */
function rc_match_title(string $title, array $keywords): bool
{
    $low    = rc_norm($title);
    $tokens = rc_tokens($title);

    foreach ($keywords as $kw) {
        $kw = rc_norm((string)$kw);
        if ($kw === '') continue;

        if ($kw[0] === '=') {
            if (in_array(mb_substr($kw, 1), $tokens, true)) return true;
        } elseif (mb_strpos($kw, ' ') !== false || mb_strpos($kw, '-') !== false) {
            if (mb_strpos($low, $kw) !== false) return true;
        } else {
            foreach ($tokens as $t) {
                if (mb_strpos($t, $kw) === 0) return true;
            }
        }
    }
    return false;
}

/** Названия ингредиентов в нижнем регистре; принимает массив или JSON-строку. */
function rc_ingredient_names($ingredients): array
{
    $list = is_array($ingredients) ? $ingredients : json_field($ingredients);
    $out  = [];
    foreach ($list as $i) {
        $n = is_array($i) ? (string)($i['name'] ?? '') : (string)$i;
        $n = rc_norm(trim($n));
        if ($n !== '') $out[] = $n;
    }
    return $out;
}

/** Ложные срабатывания: «кокосовое молоко» — не молочное, «рисовая мука» — без глютена и т.п. */
function rc_exception(string $name, string $group): bool
{
    $n   = rc_norm($name);
    $has = function (array $subs) use ($n): bool {
        foreach ($subs as $s) if (mb_strpos($n, $s) !== false) return true;
        return false;
    };
    return match ($group) {
        'dairy'            => $has(['кокос', 'миндальн', 'соев', 'овсян', 'рисов', 'растительн', 'сыро']),
        'gluten'           => $has(['рисов', 'кукуруз', 'миндальн', 'гречнев', 'кокосов', 'нутов', 'картофельн', 'безглют']),
        'fish', 'seafood'  => $has(['кабачков', 'баклажанн', 'грибн']),
        'red'              => $has(['курин', 'куриц', 'индей', 'индюш']),
        default            => false,
    };
}

function rc_has(array $names, string $group): bool
{
    $words = RC_WORDS[$group] ?? [];
    foreach ($names as $n) {
        if (rc_match_title($n, $words) && !rc_exception($n, $group)) return true;
    }
    return false;
}

// ============================================================
//  КАТЕГОРИЯ
// ============================================================

function rc_category_by_rules(string $title, array $names): ?string
{
    $cats = rc_categories();
    $low  = rc_norm($title);

    // коктейли: алкогольный или нет — решает состав
    if (mb_strpos($low, 'пицц') === false && rc_match_title($title, RC_COCKTAIL_WORDS)) {
        $slug = rc_has($names, 'alcohol') ? 'koktejli-alkogolnye' : 'koktejli-bezalkogolnye';
        return isset($cats[$slug]) ? $slug : null;
    }

    // 1) по первому слову: «Заправка для салата» — соус
    $tokens = rc_tokens($title);
    if ($tokens) {
        foreach (RC_CATEGORY_RULES as [$slug, $kws]) {
            if (isset($cats[$slug]) && rc_match_title($tokens[0], $kws)) return $slug;
        }
    }
    // 2) по всему названию, в порядке приоритета
    foreach (RC_CATEGORY_RULES as [$slug, $kws]) {
        if (isset($cats[$slug]) && rc_match_title($title, $kws)) return $slug;
    }
    return null;
}

/** Один короткий запрос: AI выбирает НОМЕР из списка (выдумать slug не может). */
function rc_ai_category(array $recipe, array $names): ?string
{
    if (!function_exists('yandex_gpt_json')) return null;

    $list = [];
    $map  = [];
    $i    = 1;
    foreach (rc_categories() as $slug => $name) {
        if (in_array($slug, RC_AI_EXCLUDE, true)) continue;
        $map[$i] = $slug;
        $list[]  = $i . '. ' . $name;
        $i++;
    }
    if (!$map) return null;

    $title  = trim((string)($recipe['title'] ?? ''));
    $ings   = implode(', ', array_slice($names, 0, 6));
    $prompt = "Определи тип блюда. Блюдо: «{$title}». Основные ингредиенты: {$ings}.\n"
        . "Выбери ОДИН номер из списка по типу блюда (суп, салат, десерт, напиток и т.д.), а не по времени суток:\n"
        . implode("\n", $list) . "\nОтветь номером.";

    $data = yandex_gpt_json($prompt, '{"category":1}', 1, 100, 0.1);

    $v = $data['category'] ?? null;
    if (is_numeric($v) && isset($map[(int)$v])) return $map[(int)$v];
    if (is_string($v) && isset(rc_categories()[$v]) && !in_array($v, RC_AI_EXCLUDE, true)) return $v;
    return null;
}

// ============================================================
//  ТЕГИ
// ============================================================

function rc_cuisine_by_name(string $name): ?string
{
    $strip = fn(string $s): string => trim((string)preg_replace('/\s*кухн\p{L}*/u', '', rc_norm($s)));

    $n = $strip($name);
    if ($n === '' || in_array($n, ['другая', 'другое', 'разная', 'международная'], true)) return null;

    $names = rc_tag_names('cuisine');
    foreach ($names as $slug => $tagName) {
        if ($strip($tagName) === $n) return $slug;
    }
    if (mb_strlen($n) >= 5) {
        foreach ($names as $slug => $tagName) {
            if (mb_strpos(rc_norm($tagName), mb_substr($n, 0, 5)) === 0) return $slug;
        }
    }
    return null;
}

function rc_season(string $title): string
{
    if (rc_match_title($title, ['окрошк', 'гаспачо', 'холодник', 'мороженое', '=лимонад', 'смузи', 'арбуз', '=дыня', 'охлажд'])) {
        return 'letnie';
    }
    if (rc_match_title($title, ['борщ', '=щи', 'солянк', 'рассольник', 'харчо', 'шурпа', 'глинтвейн', 'жаркое', 'тушен', 'пельмен', '=грог'])) {
        return 'zimnie';
    }
    return 'kruglogodichnye';
}

function rc_holiday(string $title): ?string
{
    static $map = [
        'novyj-god'      => ['оливье', 'под шубой', 'новогодн'],
        'rozhdestvo'     => ['рождествен', 'кутья', 'сочиво'],
        'pasxa'          => ['кулич', 'пасх'],
        'maslenica'      => ['масленичн'],
        'helloween'      => ['хэллоуин', 'хеллоуин'],
        'svadba'         => ['свадебн'],
        'den-rozhdeniya' => ['именинн', 'день рождения'],
    ];
    foreach ($map as $slug => $kws) {
        if (rc_match_title($title, $kws)) return $slug;
    }
    return null;
}

/**
 * Какие условия (диета / «без …» / время) нарушены в готовом рецепте.
 * @param string[] $slugs  slug'и тегов, которые запросил пользователь
 * @return string[]        человекочитаемые нарушения; пусто — всё соблюдено
 */
function rc_violations(array $recipe, array $slugs): array
{
    $names = rc_ingredient_names($recipe['ingredients'] ?? []);

    $rules = [
        'vegetarianskaya'         => [['meat', 'мясо'], ['fish', 'рыба'], ['seafood', 'морепродукты']],
        'veganskaya'              => [['meat', 'мясо'], ['fish', 'рыба'], ['seafood', 'морепродукты'], ['dairy', 'молочные продукты'], ['egg', 'яйца'], ['honey', 'мёд']],
        'bezglyutenovaya'         => [['gluten', 'глютен']],
        'bezlaktoznaya'           => [['dairy', 'молочные продукты']],
        'bez-molochnyx-produktov' => [['dairy', 'молочные продукты']],
        'bez-orexov'              => [['nuts', 'орехи']],
        'bez-soi'                 => [['soy', 'соя']],
        'bez-yaic'                => [['egg', 'яйца']],
        'bez-moreproduktov'       => [['seafood', 'морепродукты']],
        'bez-ryby'                => [['fish', 'рыба']],
        'bez-krasnogo-myasa'      => [['red', 'красное мясо']],
        'bez-svininy'             => [['pork', 'свинина']],
        'bez-govyadiny'           => [['beef', 'говядина']],
        'bez-kuricy'              => [['chicken', 'курица']],
    ];
    $limits = ['do-15-minut' => 15, 'do-30-minut' => 30, 'do-60-minut' => 60];

    $bad = [];
    foreach ($slugs as $slug) {
        foreach ($rules[$slug] ?? [] as [$group, $label]) {
            if (rc_has($names, $group)) $bad[$label] = true;
        }
        if (isset($limits[$slug])) {
            $total = (int)($recipe['prep_time'] ?? 0) + (int)($recipe['cook_time'] ?? 0);
            if ($total > $limits[$slug]) $bad['время приготовления больше ' . $limits[$slug] . ' мин'] = true;
        }
    }
    return array_keys($bad);
}

// ============================================================
//  ГЛАВНАЯ ФУНКЦИЯ
// ============================================================

/**
 * @param array $recipe  title, ingredients, prep_time, cook_time, difficulty, cuisine …
 * @param array $opts    requested     => [группа => slug]  — теги, которые запросил пользователь
 *                       hint_category => slug              — подсказка (например, из type плана)
 *                       hint_cuisine  => slug
 *                       allow_ai      => bool (по умолчанию true)
 * @return array{category_slug:string,tag_slugs:string[],confidence:float,source:string}
 */
function classify_recipe(array $recipe, array $opts = []): array
{
    $title     = trim((string)($recipe['title'] ?? ''));
    $names     = rc_ingredient_names($recipe['ingredients'] ?? []);
    $cats      = rc_categories();
    $tagMap    = rc_tag_map();
    $requested = (array)($opts['requested'] ?? []);

    // ---------- категория ----------
    $source   = 'rule';
    $category = rc_category_by_rules($title, $names);

    $hint = (string)($opts['hint_category'] ?? '');
    if ($category === null && $hint !== '' && isset($cats[$hint]) && !in_array($hint, RC_AI_EXCLUDE, true)) {
        $category = $hint;
        $source   = 'hint';
    }
    if ($category === null && ($opts['allow_ai'] ?? true)) {
        try {
            $category = rc_ai_category($recipe, $names);
            $source   = 'ai';
        } catch (Throwable $e) {
            error_log('[classify_recipe] AI: ' . $e->getMessage());
        }
    }
    if ($category === null || !isset($cats[$category])) {
        $category = isset($cats['goryachie-osnovnye']) ? 'goryachie-osnovnye' : (string)array_key_first($cats);
        $source   = 'default';
    }

    // ---------- теги ----------
    $tags = [];
    $add  = function (?string $slug) use (&$tags, $tagMap): void {
        if ($slug && isset($tagMap[$slug]) && !in_array($slug, $tags, true)) $tags[] = $slug;
    };

    // время и сложность — из чисел
    $total = (int)($recipe['prep_time'] ?? 0) + (int)($recipe['cook_time'] ?? 0);
    if ($total > 0) {
        $add($total <= 15 ? 'do-15-minut' : ($total <= 30 ? 'do-30-minut' : ($total <= 60 ? 'do-60-minut' : 'bolee-chasa')));
    }
    $level = difficulty_normalize((string)($recipe['difficulty'] ?? ''));
    $add(['easy' => 'dlya-novichkov', 'medium' => 'srednij-uroven', 'hard' => 'dlya-profi'][$level] ?? null);

    // кухня: выбор пользователя → поле рецепта → подсказка
    $cuisine = $requested['cuisine'] ?? null;
    if (!$cuisine) $cuisine = rc_cuisine_by_name((string)($recipe['cuisine'] ?? ''));
    if (!$cuisine && !empty($opts['hint_cuisine'])) $cuisine = (string)$opts['hint_cuisine'];
    if ($cuisine && ($tagMap[$cuisine] ?? '') === 'cuisine') $add($cuisine);

    // диета — только по составу (не больше трёх)
    if ($names) {
        $diet = [];
        if (!rc_has($names, 'meat') && !rc_has($names, 'fish') && !rc_has($names, 'seafood')) {
            $vegan  = !rc_has($names, 'dairy') && !rc_has($names, 'egg') && !rc_has($names, 'honey');
            $diet[] = $vegan ? 'veganskaya' : 'vegetarianskaya';
        }
        if (!rc_has($names, 'gluten')) $diet[] = 'bezglyutenovaya';
        if (!rc_has($names, 'dairy') && !in_array('veganskaya', $diet, true)) $diet[] = 'bezlaktoznaya';
        foreach (array_slice($diet, 0, 3) as $d) $add($d);
    }

    // запрошенные диета/ограничение — только если рецепт их реально соблюдает
    $check = array_values(array_filter([$requested['diet'] ?? null, $requested['restriction'] ?? null]));
    foreach ($check as $slug) {
        if (!rc_violations($recipe, [$slug])) $add($slug);
    }

    // сезон и повод
    $season = $requested['season'] ?? rc_season($title);
    $add($season);
    $add($requested['holiday'] ?? rc_holiday($title));

    return [
        'category_slug' => $category,
        'tag_slugs'     => array_slice($tags, 0, 8),
        'confidence'    => ['rule' => 1.0, 'hint' => 0.8, 'ai' => 0.8, 'default' => 0.5][$source],
        'source'        => $source,
    ];
}
