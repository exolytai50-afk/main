<?php
/**
 * GigaChef — «Праздники», этапы 2–3: сервировка, напитки, декор.
 *
 * Каждый раздел = ОДИН компактный AI-запрос. Ответ проходит очистку
 * (структура, длины, strip_tags) и сохраняется в holiday_tips.
 * Объёмы напитков считаем в коде (мл на гостя × число гостей), а не доверяем модели.
 */
declare(strict_types=1);

require_once __DIR__ . '/holiday_helpers.php';

const HOLIDAY_TIP_SECTIONS = [
    'serving' => ['label' => 'Сервировка стола',     'stage' => 2],
    'drinks'  => ['label' => 'Напитки',              'stage' => 2],
    'decor'   => ['label' => 'Оформление помещения', 'stage' => 3],
];

// ============================================================
//  ОЧИСТКА
// ============================================================

function ht_str($v, int $max = 200): string
{
    return mb_substr(trim(strip_tags(is_scalar($v) ? (string)$v : '')), 0, $max);
}

/** Список строк: допускает элементы-строки и объекты {text|title}. */
function ht_list($v, int $maxItems, int $maxLen): array
{
    if (!is_array($v)) return [];
    $out = [];
    foreach ($v as $x) {
        if (is_array($x)) $x = $x['text'] ?? $x['title'] ?? $x['name'] ?? '';
        $s = ht_str($x, $maxLen);
        if ($s !== '') $out[] = $s;
        if (count($out) >= $maxItems) break;
    }
    return $out;
}

/** Список объектов; пары «название => текст» превращает в объекты. */
function ht_rows($v): array
{
    if (!is_array($v)) return [];
    $rows = [];
    foreach ($v as $k => $x) {
        if (is_array($x))                     $rows[] = $x;
        elseif (is_string($k) && is_string($x)) $rows[] = ['name' => $k, 'advice' => $x];
    }
    return $rows;
}

function ht_fmt_volume(int $ml): string
{
    if ($ml <= 0) return '';
    if ($ml < 1000) return '≈ ' . (int)(ceil($ml / 50) * 50) . ' мл';
    $l = ceil($ml / 100) / 10;
    return '≈ ' . str_replace('.', ',', rtrim(rtrim(number_format($l, 1, '.', ''), '0'), '.')) . ' л';
}

// ============================================================
//  КОНТЕКСТ
// ============================================================

/** Меню праздника: подпись раздела => названия блюд. */
function ht_menu_by_course(int $holidayId): array
{
    $byCourse = [];
    foreach (db_all('SELECT course, title FROM holiday_recipes WHERE holiday_id = ? ORDER BY id', [$holidayId]) as $r) {
        $byCourse[$r['course']][] = $r['title'];
    }
    $out = [];
    foreach (HOLIDAY_COURSES as $key => $c) {
        if (!empty($byCourse[$key])) $out[$c['label']] = $byCourse[$key];
    }
    return $out;
}

function ht_menu_line(array $menu): string
{
    $parts = [];
    foreach ($menu as $label => $titles) {
        $parts[] = $label . ': ' . implode('; ', $titles);
    }
    return mb_substr(implode('. ', $parts), 0, 700);
}

function ht_season(?string $date): string
{
    if (!$date) return '';
    $m = (int)date('n', strtotime($date));
    return match (true) {
        $m === 12 || $m <= 2 => 'зима',
        $m <= 5              => 'весна',
        $m <= 8              => 'лето',
        default              => 'осень',
    };
}

// ============================================================
//  ХРАНЕНИЕ
// ============================================================

function ht_save(int $holidayId, string $section, array $content): void
{
    db_exec(
        'INSERT INTO holiday_tips (holiday_id, section, content_json, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), updated_at = NOW()',
        [$holidayId, $section, json_encode($content, JSON_UNESCAPED_UNICODE)]
    );
}

function ht_load_tips(int $holidayId): array
{
    $out = ['serving' => null, 'drinks' => null, 'decor' => null];
    foreach (db_all('SELECT section, content_json FROM holiday_tips WHERE holiday_id = ?', [$holidayId]) as $r) {
        if (array_key_exists($r['section'], $out)) {
            $data = json_field($r['content_json']);
            $out[$r['section']] = $data ?: null;
        }
    }
    return $out;
}

// ============================================================
//  ГЕНЕРАЦИЯ (один запрос на раздел)
// ============================================================

function ht_generate(array $h, string $section, array $menu): array
{
    $ctx  = holiday_context_line($h);
    $line = ht_menu_line($menu);

    return match ($section) {
        'serving' => ht_gen_serving($h, $ctx, $line),
        'drinks'  => ht_gen_drinks($h, $ctx, $line),
        'decor'   => ht_gen_decor($h, $ctx, $line),
        default   => throw new InvalidArgumentException('Неизвестный раздел'),
    };
}

// ---------- сервировка ----------

function ht_gen_serving(array $h, string $ctx, string $menuLine): array
{
    $guests = (int)$h['guests'];

    $prompt = "Придумай сервировку праздничного стола. {$ctx}.\n"
        . ($menuLine !== '' ? "Меню: {$menuLine}.\n" : '')
        . "Дай общий стиль и палитру (3-4 цвета), затем позиции сервировки с количеством на {$guests} гостей: "
        . 'Скатерть и салфетки; Посуда; Приборы; Бокалы и стаканы; Центр стола; Свет и свечи; Подача блюд. '
        . 'К каждой позиции — короткий практичный совет с учётом бюджета (в эконом-варианте без дорогих покупок). '
        . 'Затем как расставить стол и 3 коротких совета.';

    $schema = '{"style":"1-2 предложения","palette":["цвет"],'
        . '"items":[{"name":"Скатерть и салфетки","qty":"например: 1 скатерть и 8 салфеток","advice":"1-2 предложения"}],'
        . '"layout":"2 предложения","tips":["совет"]}';

    return ht_clean_serving(yandex_gpt_json($prompt, $schema, 1, 1600, 0.6));
}

function ht_clean_serving(array $d): array
{
    $items = [];
    foreach (ht_rows($d['items'] ?? []) as $r) {
        $name   = ht_str($r['name'] ?? '', 60);
        $advice = ht_str($r['advice'] ?? ($r['tip'] ?? ''), 260);
        if ($name === '' || $advice === '') continue;
        $items[] = ['name' => $name, 'qty' => ht_str($r['qty'] ?? '', 90), 'advice' => $advice];
        if (count($items) >= 8) break;
    }
    if (!$items) throw new RuntimeException('AI вернул пустой ответ');

    return [
        'style'   => ht_str($d['style'] ?? '', 300),
        'palette' => ht_list($d['palette'] ?? [], 5, 30),
        'items'   => $items,
        'layout'  => ht_str($d['layout'] ?? '', 400),
        'tips'    => ht_list($d['tips'] ?? [], 5, 220),
    ];
}

// ---------- напитки ----------

function ht_gen_drinks(array $h, string $ctx, string $menuLine): array
{
    $age  = (string)$h['age_group'];
    $rule = $age === 'kids'
        ? 'Гости — дети: только безалкогольные напитки, alcohol оставь пустым массивом.'
        : ($age === 'mixed'
            ? 'Есть дети: алкоголь только для взрослых, безалкогольных напитков должно быть больше.'
            : 'Алкоголь допустим.');

    $prompt = "Подбери напитки к праздничному столу. {$ctx}.\n"
        . ($menuLine !== '' ? "Меню: {$menuLine}.\n" : '')
        . $rule . "\n"
        . 'Нужно: alcohol 2-3 (если допустимо), soft 2-3, hot 1-2. '
        . 'К каждому напитку: note — с какими блюдами из меню сочетается (до 12 слов), '
        . 'per_guest_ml — сколько мл на одного гостя (число). Затем 3 коротких совета по подаче.';

    $item   = '[{"name":"","note":"","per_guest_ml":0}]';
    $schema = '{"intro":"1 предложение","alcohol":' . $item . ',"soft":' . $item . ',"hot":' . $item . ',"tips":["совет"]}';

    return ht_clean_drinks(yandex_gpt_json($prompt, $schema, 1, 1400, 0.6), $h);
}

function ht_clean_drinks(array $d, array $h): array
{
    $guests = (int)$h['guests'];
    $age    = (string)$h['age_group'];

    $labels = ['alcohol' => 'Алкогольные', 'soft' => 'Безалкогольные', 'hot' => 'Горячие'];
    // Доля гостей, которые пьют: для смешанной компании алкоголь считаем на ≈60%
    $factor = ['alcohol' => $age === 'kids' ? 0.0 : ($age === 'mixed' ? 0.6 : 1.0), 'soft' => 1.0, 'hot' => 1.0];

    $groups = [];
    foreach ($labels as $key => $label) {
        if ($factor[$key] <= 0) continue;

        $items = [];
        foreach (ht_rows($d[$key] ?? []) as $r) {
            $name = ht_str($r['name'] ?? '', 80);
            if ($name === '') continue;

            $ml = max(50, min(800, (int)($r['per_guest_ml'] ?? 150)));
            $items[] = [
                'name'         => $name,
                'note'         => ht_str($r['note'] ?? ($r['advice'] ?? ''), 160),
                'per_guest_ml' => $ml,
                'total'        => ht_fmt_volume((int)round($ml * $guests * $factor[$key])),
            ];
            if (count($items) >= 4) break;
        }
        if ($items) $groups[] = ['key' => $key, 'label' => $label, 'items' => $items];
    }
    if (!$groups) throw new RuntimeException('AI вернул пустой ответ');

    return [
        'intro'  => ht_str($d['intro'] ?? '', 300),
        'groups' => $groups,
        'tips'   => ht_list($d['tips'] ?? [], 5, 220),
        'note'   => $age === 'mixed' ? 'Алкоголь рассчитан на взрослых (≈ 60% гостей).' : '',
    ];
}

// ---------- декор ----------

function ht_gen_decor(array $h, string $ctx, string $menuLine): array
{
    $season = ht_season($h['event_date'] ?? null);
    $kids   = $h['age_group'] !== 'adults';

    $prompt = "Придумай оформление помещения для праздника. {$ctx}"
        . ($season !== '' ? "; сезон: {$season}" : '') . ".\n"
        . 'Нужно: общая идея и палитра (3-4 цвета); идеи по зонам (Вход; Стол; Комната; Свет и музыка'
        . ($kids ? '; Развлечения' : '') . ') по 2-3 идеи; 2-3 поделки своими руками с временем в минутах; '
        . 'список, что докупить; 3 коротких совета. '
        . 'Учитывай бюджет: в эконом-варианте — в основном поделки и вещи, что есть дома.';

    $schema = '{"concept":"1-2 предложения","palette":["цвет"],'
        . '"zones":[{"zone":"Вход","ideas":["идея"]}],'
        . '"diy":[{"title":"","how":"1-2 предложения","time_min":0}],'
        . '"shopping":["что докупить"],"tips":["совет"]}';

    return ht_clean_decor(yandex_gpt_json($prompt, $schema, 1, 1800, 0.7));
}

function ht_clean_decor(array $d): array
{
    $zones = [];
    foreach ((array)($d['zones'] ?? []) as $k => $x) {
        if (!is_array($x)) continue;

        if (isset($x['ideas']) || isset($x['zone'])) {
            $zone  = ht_str($x['zone'] ?? $x['name'] ?? '', 40);
            $ideas = ht_list($x['ideas'] ?? [], 4, 200);
        } elseif (is_string($k)) {            // {"Вход": ["идея", ...]}
            $zone  = ht_str($k, 40);
            $ideas = ht_list($x, 4, 200);
        } else {
            continue;
        }
        if ($zone !== '' && $ideas) $zones[] = ['zone' => $zone, 'ideas' => $ideas];
        if (count($zones) >= 6) break;
    }
    if (!$zones) throw new RuntimeException('AI вернул пустой ответ');

    $diy = [];
    foreach (ht_rows($d['diy'] ?? []) as $r) {
        $title = ht_str($r['title'] ?? $r['name'] ?? '', 80);
        $how   = ht_str($r['how'] ?? $r['advice'] ?? '', 260);
        if ($title === '' || $how === '') continue;
        $diy[] = ['title' => $title, 'how' => $how, 'time_min' => max(0, min(240, (int)($r['time_min'] ?? 0)))];
        if (count($diy) >= 4) break;
    }

    return [
        'concept'  => ht_str($d['concept'] ?? '', 300),
        'palette'  => ht_list($d['palette'] ?? [], 5, 30),
        'zones'    => $zones,
        'diy'      => $diy,
        'shopping' => ht_list($d['shopping'] ?? [], 10, 60),
        'tips'     => ht_list($d['tips'] ?? [], 5, 220),
    ];
}
