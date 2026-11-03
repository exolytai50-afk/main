<?php
/**
 * GigaChef — «Праздники»: общий список покупок по всем блюдам меню.
 *
 * Одинаковые продукты суммируются (кг/г и л/мл приводятся к базовой единице),
 * «по вкусу» не дублируется, если у продукта есть точное количество.
 * Категории магазина — по ключевым словам (эвристика, без AI).
 */
declare(strict_types=1);

require_once __DIR__ . '/holiday_helpers.php';
require_once __DIR__ . '/holiday_view.php';

/** Порядок показа групп. */
const HS_GROUP_ORDER = ['veg', 'meat', 'fish', 'dairy', 'grocery', 'spice', 'other'];

/** Порядок проверки ключевых слов (специфичное раньше общего). */
const HS_MATCH_ORDER = ['dairy', 'fish', 'meat', 'veg', 'spice', 'grocery'];

const HS_CATEGORIES = [
    'veg'     => ['Овощи, фрукты и зелень', [
        'картофел', 'морков', 'лук', 'чеснок', 'помидор', 'томат', 'огурц', 'капуст', 'болгарск', 'свекл',
        'кабач', 'баклажан', 'зелен', 'укроп', 'петрушк', 'кинза', 'базилик', 'шпинат', 'лимон', 'апельсин',
        'яблок', 'груш', 'банан', 'ягод', 'клубник', 'авокадо', 'гриб', 'шампиньон', 'редис', 'имбир', 'тыкв',
        'кукуруз', 'оливк', 'маслин', 'мандарин', 'виноград', 'гранат', 'киви', 'ананас', 'мят', 'салат', 'сельдерей',
    ]],
    'meat'    => ['Мясо и птица', [
        'говяд', 'свинин', 'курин', 'куриц', 'индейк', 'баранин', 'фарш', 'мяс', 'окорок', 'грудк', 'бедр',
        'колбас', 'ветчин', 'бекон', 'сосиск', 'утк', 'гус', 'крыл', 'рёбр', 'ребр', 'телятин',
    ]],
    'fish'    => ['Рыба и морепродукты', [
        'рыб', 'лосос', 'сёмг', 'семг', 'форел', 'тунец', 'сельд', 'селёд', 'креветк', 'кальмар', 'мидии',
        'краб', 'икр', 'треск', 'судак', 'минтай', 'скумбри', 'шпрот', 'морепродукт',
    ]],
    'dairy'   => ['Молочное и яйца', [
        'молок', 'сливк', 'сметан', 'творог', 'сыр', 'сливочн', 'кефир', 'йогурт', 'яйц', 'моцарел',
        'пармезан', 'маскарпоне', 'рикотт', 'брынз', 'ряженк',
    ]],
    'grocery' => ['Бакалея', [
        'мук', 'сахар', 'рис', 'греч', 'макарон', 'спагетти', 'паст', 'круп', 'овсян', 'хлеб', 'батон',
        'сухар', 'дрожж', 'разрыхлит', 'крахмал', 'орех', 'миндал', 'изюм', 'шоколад', 'какао', 'мёд', 'мед ',
        'фасол', 'горох', 'нут', 'чечевиц', 'консерв', 'желатин', 'печенье', 'бисквит', 'вафл', 'джем',
        'варенье', 'сироп', 'лапш', 'тортиль', 'лаваш', 'панировоч',
    ]],
    'spice'   => ['Специи, соусы и масла', [
        'соль', 'сол ', 'перец', 'специ', 'паприк', 'куркум', 'корица', 'ванил', 'мускат', 'лавров', 'орегано',
        'тимьян', 'розмарин', 'соус', 'кетчуп', 'майонез', 'горчиц', 'уксус', 'масл', 'соев', 'приправ',
    ]],
    'other'   => ['Прочее', []],
];

// ============================================================
//  РАЗБОР КОЛИЧЕСТВ И ЕДИНИЦ
// ============================================================

/** «200», «1/2», «1 1/2», «2,5», «2–3» → число (диапазон = верхняя граница); иначе null. */
function hs_parse_amount(string $a): ?float
{
    $a = trim(str_replace(',', '.', $a));
    if ($a === '') return null;

    if (preg_match('/^(\d+(?:\.\d+)?)\s*[-–]\s*(\d+(?:\.\d+)?)$/u', $a, $m)) {
        return max((float)$m[1], (float)$m[2]);
    }
    if (preg_match('/^(?:(\d+)\s+)?(\d+)\/(\d+)$/', $a, $m) && (int)$m[3] > 0) {
        return (int)$m[1] + (int)$m[2] / (int)$m[3];
    }
    return is_numeric($a) ? (float)$a : null;
}

function hs_unit(string $u): string
{
    $u = mb_strtolower(trim($u));
    static $map = [
        'г' => 'г', 'гр' => 'г', 'гр.' => 'г', 'грамм' => 'г', 'граммов' => 'г',
        'кг' => 'кг', 'килограмм' => 'кг',
        'мл' => 'мл', 'миллилитр' => 'мл',
        'л' => 'л', 'литр' => 'л', 'литра' => 'л',
        'шт' => 'шт', 'шт.' => 'шт', 'штука' => 'шт', 'штуки' => 'шт', 'штук' => 'шт',
        'ч.л.' => 'ч.л.', 'ч.л' => 'ч.л.', 'чайная ложка' => 'ч.л.', 'чайных ложки' => 'ч.л.',
        'ст.л.' => 'ст.л.', 'ст.л' => 'ст.л.', 'столовая ложка' => 'ст.л.', 'столовых ложки' => 'ст.л.',
        'стакан' => 'стакан', 'стакана' => 'стакан', 'стаканов' => 'стакан',
        'щепотка' => 'щепотка', 'щепотки' => 'щепотка',
        'пучок' => 'пучок', 'пучка' => 'пучок',
        'зубчик' => 'зубчик', 'зубчика' => 'зубчик', 'зубчиков' => 'зубчик',
        'по вкусу' => 'по вкусу',
    ];
    return $map[$u] ?? $u;
}

function hs_norm_name(string $n): string
{
    $n = mb_strtolower(trim($n));
    $n = preg_replace('/\([^)]*\)/u', '', $n);
    $n = preg_replace('/[,;].*$/u', '', $n);
    $n = preg_replace('/\s+/u', ' ', $n);
    return trim($n, " .");
}

function hs_cap(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function hs_num(float $v): string
{
    $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    return str_replace('.', ',', $s);
}

/** Сумма в базовых единицах → красивое количество для покупки (округляем вверх). */
function hs_format(float $sum, string $unit): array
{
    if ($unit === 'г' || $unit === 'мл') {
        $v = ceil($sum / 5) * 5;
        if ($v >= 1000) return [hs_num($v / 1000), $unit === 'г' ? 'кг' : 'л'];
        return [hs_num($v), $unit];
    }
    if ($unit === 'шт') return [hs_num(ceil($sum)), 'шт'];
    return [hs_num(max(0.5, ceil($sum * 2) / 2)), $unit];
}

function hs_category(string $name): string
{
    $n = mb_strtolower($name);

    // масла (но не сливочное и не маслины)
    if (mb_strpos($n, 'масл') !== false && mb_strpos($n, 'маслин') === false && mb_strpos($n, 'сливочн') === false) {
        return 'spice';
    }
    foreach (HS_MATCH_ORDER as $key) {
        foreach (HS_CATEGORIES[$key][1] as $kw) {
            if (mb_strpos($n, $kw) !== false) return $key;
        }
    }
    return 'other';
}

// ============================================================
//  СБОРКА СПИСКА
// ============================================================

/** Плоский список: name, text, used (в каких блюдах), cat. */
function hs_build_list(int $holidayId): array
{
    $rows = db_all('SELECT title, recipe_json FROM holiday_recipes WHERE holiday_id = ? ORDER BY id', [$holidayId]);

    $acc = [];
    foreach ($rows as $row) {
        $rec = json_field($row['recipe_json']);
        foreach ((array)($rec['ingredients'] ?? []) as $ing) {
            if (!is_array($ing)) continue;

            $name = hs_norm_name((string)($ing['name'] ?? ''));
            if ($name === '') continue;

            $unit  = hs_unit((string)($ing['unit'] ?? ''));
            $amt   = hs_parse_amount((string)($ing['amount'] ?? ''));
            $taste = $unit === 'по вкусу' || $amt === null || $amt <= 0;
            if (!$taste && $unit === '') $unit = 'шт';

            $family = $unit;
            $base   = $amt;
            if ($unit === 'кг')     { $family = 'г';  $base = $amt !== null ? $amt * 1000 : null; }
            elseif ($unit === 'л')  { $family = 'мл'; $base = $amt !== null ? $amt * 1000 : null; }

            $key = $name . '|' . ($taste ? '~' : $family);
            if (!isset($acc[$key])) {
                $acc[$key] = ['name' => $name, 'unit' => $family, 'sum' => 0.0, 'taste' => $taste, 'used' => []];
            }
            if (!$taste) $acc[$key]['sum'] += (float)$base;
            $acc[$key]['used'][(string)$row['title']] = true;
        }
    }

    // «по вкусу» лишнее, если у продукта есть точное количество
    foreach ($acc as $key => $a) {
        if (!$a['taste']) continue;
        foreach ($acc as $k2 => $b) {
            if (!$b['taste'] && $b['name'] === $a['name']) {
                $acc[$k2]['used'] += $a['used'];
                unset($acc[$key]);
                break;
            }
        }
    }

    $items = [];
    foreach ($acc as $a) {
        if ($a['taste']) {
            $text = 'по вкусу';
        } else {
            [$num, $u] = hs_format($a['sum'], $a['unit']);
            $text = trim($num . ' ' . $u);
        }
        $items[] = [
            'name' => hs_cap($a['name']),
            'text' => $text,
            'used' => array_map('strval', array_keys($a['used'])),
            'cat'  => hs_category($a['name']),
        ];
    }
    return $items;
}

/** Список по группам магазина: [{key,label,items:[{name,text,used}]}]. */
function hs_groups(int $holidayId): array
{
    $by = [];
    foreach (hs_build_list($holidayId) as $it) {
        $by[$it['cat']][] = ['name' => $it['name'], 'text' => $it['text'], 'used' => $it['used']];
    }

    $out = [];
    foreach (HS_GROUP_ORDER as $key) {
        if (empty($by[$key])) continue;
        usort($by[$key], fn($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));
        $out[] = ['key' => $key, 'label' => HS_CATEGORIES[$key][0], 'items' => $by[$key]];
    }
    return $out;
}

// ============================================================
//  ПИСЬМО
// ============================================================

function hs_email_html(array $h, array $groups): string
{
    $title = e((string)$h['title']);
    $meta  = (int)$h['guests'] . ' ' . hv_guests_word((int)$h['guests']);

    $rows = '';
    foreach ($groups as $g) {
        $rows .= '<tr><td style="padding:16px 22px 6px;font-size:13px;font-weight:800;color:#be123c;text-transform:uppercase;letter-spacing:.04em">'
              . e((string)$g['label']) . '</td></tr>';
        foreach ($g['items'] as $it) {
            $rows .= '<tr><td style="padding:9px 22px;border-bottom:1px solid #f3f4f6;font-size:15px;color:#374151">&#9744; '
                  . e((string)$it['name']) . ' — <b>' . e((string)$it['text']) . '</b></td></tr>';
        }
    }

    return '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Список покупок</title></head>'
        . '<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif">'
        . '<table width="100%" cellspacing="0" cellpadding="0" style="padding:32px 10px"><tr><td align="center">'
        . '<table width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden">'
        . '<tr><td style="background:#f59e0b;padding:22px;color:#fff">'
        . '<div style="font-size:22px;font-weight:800">Список покупок</div>'
        . '<div style="font-size:14px;margin-top:4px">' . $title . ' · ' . e($meta) . '</div></td></tr>'
        . $rows
        . '<tr><td style="padding:22px;text-align:center;background:#f9fafb;color:#9ca3af;font-size:12px">'
        . 'Приятного праздника! GigaChef</td></tr>'
        . '</table></td></tr></table></body></html>';
}
