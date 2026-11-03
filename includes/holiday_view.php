<?php
/**
 * GigaChef — «Праздники»: хелперы отображения для страниц
 * «Мои праздники» и «Праздник» (серверный рендер, удобен для печати).
 */
declare(strict_types=1);

require_once __DIR__ . '/holiday_tips_helpers.php';

function hv_plural(int $n, string $one, string $few, string $many): string
{
    $m10  = $n % 10;
    $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) return $one;
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) return $few;
    return $many;
}

function hv_guests_word(int $n): string
{
    return hv_plural($n, 'гость', 'гостя', 'гостей');
}

/** «сегодня», «завтра», «через 5 дней», «прошёл». */
function hv_when(?string $date): string
{
    if (!$date) return '';
    $d = (int)round((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($d === 0) return 'сегодня';
    if ($d === 1) return 'завтра';
    if ($d > 1)   return 'через ' . $d . ' ' . hv_plural($d, 'день', 'дня', 'дней');
    return 'прошёл';
}

/** Следующий незавершённый этап: [подпись, ссылка] или null, если всё готово. */
function hv_next_step(array $h, int $dishes, array $tips): ?array
{
    $id = (int)$h['id'];
    if ($dishes === 0) {
        return ['Составить меню', url('ai-holiday?id=' . $id)];
    }
    if (empty($tips['serving']) || empty($tips['drinks'])) {
        return ['Стол и напитки', url('ai-holiday/table?id=' . $id)];
    }
    if (empty($tips['decor'])) {
        return ['Оформление', url('ai-holiday/decor?id=' . $id)];
    }
    return null;
}

// ---------- мелкие блоки ----------

function hv_pills(array $items): void
{
    if (!$items) return;
    echo '<div class="flex flex-wrap gap-1.5 my-3">';
    foreach ($items as $c) {
        echo '<span class="hw-pill">' . e((string)$c) . '</span>';
    }
    echo '</div>';
}

function hv_bullets(array $items): void
{
    if (!$items) return;
    echo '<ul class="mt-3 space-y-1.5">';
    foreach ($items as $t) {
        echo '<li class="hw-text text-sm flex gap-2"><span class="hw-accent">•</span><span>' . e((string)$t) . '</span></li>';
    }
    echo '</ul>';
}

// ---------- советы ----------

function hv_render_serving(array $t): void
{
    if (!empty($t['style'])) echo '<p class="hw-text">' . e((string)$t['style']) . '</p>';
    hv_pills((array)($t['palette'] ?? []));

    echo '<div class="mt-2">';
    foreach ((array)($t['items'] ?? []) as $it) {
        echo '<div class="hw-row"><div class="flex flex-wrap items-center gap-2">'
           . '<span class="hw-title font-bold">' . e((string)($it['name'] ?? '')) . '</span>';
        if (!empty($it['qty'])) echo '<span class="hw-badge">' . e((string)$it['qty']) . '</span>';
        echo '</div><p class="hw-text text-sm mt-1">' . e((string)($it['advice'] ?? '')) . '</p></div>';
    }
    echo '</div>';

    if (!empty($t['layout'])) {
        echo '<p class="hw-text text-sm mt-3"><span class="hw-title font-bold">Расстановка. </span>'
           . e((string)$t['layout']) . '</p>';
    }
    hv_bullets((array)($t['tips'] ?? []));
}

function hv_render_drinks(array $t): void
{
    if (!empty($t['intro'])) echo '<p class="hw-text">' . e((string)$t['intro']) . '</p>';

    foreach ((array)($t['groups'] ?? []) as $g) {
        echo '<div class="mt-4"><h4 class="hw-title font-bold mb-2">' . e((string)($g['label'] ?? '')) . '</h4>';
        foreach ((array)($g['items'] ?? []) as $it) {
            echo '<div class="hw-row"><div class="flex flex-wrap items-center gap-2">'
               . '<span class="hw-title font-bold">' . e((string)($it['name'] ?? '')) . '</span>';
            if (!empty($it['total'])) echo '<span class="hw-badge">на всех: ' . e((string)$it['total']) . '</span>';
            if (!empty($it['per_guest_ml'])) echo '<span class="hw-sub text-xs">' . (int)$it['per_guest_ml'] . ' мл на гостя</span>';
            echo '</div>';
            if (!empty($it['note'])) echo '<p class="hw-text text-sm mt-1">' . e((string)$it['note']) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    if (!empty($t['note'])) echo '<p class="hw-sub text-xs mt-3">' . e((string)$t['note']) . '</p>';
    hv_bullets((array)($t['tips'] ?? []));
}

function hv_render_decor(array $t): void
{
    if (!empty($t['concept'])) echo '<p class="hw-text">' . e((string)$t['concept']) . '</p>';
    hv_pills((array)($t['palette'] ?? []));

    foreach ((array)($t['zones'] ?? []) as $z) {
        echo '<div class="hw-row"><h4 class="hw-title font-bold">' . e((string)($z['zone'] ?? '')) . '</h4>';
        hv_bullets((array)($z['ideas'] ?? []));
        echo '</div>';
    }

    if (!empty($t['diy'])) {
        echo '<h4 class="hw-title font-bold mt-4 mb-2">Сделать своими руками</h4>';
        foreach ((array)$t['diy'] as $d) {
            echo '<div class="hw-row"><div class="flex flex-wrap items-center gap-2">'
               . '<span class="hw-title font-bold">' . e((string)($d['title'] ?? '')) . '</span>';
            if (!empty($d['time_min'])) echo '<span class="hw-badge">' . (int)$d['time_min'] . ' мин</span>';
            echo '</div><p class="hw-text text-sm mt-1">' . e((string)($d['how'] ?? '')) . '</p></div>';
        }
    }

    if (!empty($t['shopping'])) {
        echo '<h4 class="hw-title font-bold mt-4 mb-1">Что докупить</h4>';
        hv_pills((array)$t['shopping']);
    }
    hv_bullets((array)($t['tips'] ?? []));
}

// ---------- блюдо меню ----------

function hv_render_dish(array $row): void
{
    $rec  = json_field($row['recipe_json'] ?? null);
    $time = (int)($rec['prep_time'] ?? 0) + (int)($rec['cook_time'] ?? 0);
    $cal  = (int)($rec['calories'] ?? 0);

    $meta = 'на ' . (int)$row['servings'] . ' порц.';
    if ($time > 0) $meta .= ' · ' . $time . ' мин';
    if ($cal > 0)  $meta .= ' · ' . $cal . ' ккал/порц.';

    echo '<details class="hw-dish hw-row"><summary class="flex flex-wrap items-center gap-2">'
       . '<span class="hw-title font-bold">' . e((string)$row['title']) . '</span>'
       . '<span class="hw-badge ' . ($row['source'] === 'db' ? 'hw-badge-ok' : '') . '">'
       . ($row['source'] === 'db' ? 'Из базы' : 'AI') . '</span>'
       . '<span class="hw-sub text-xs">' . e($meta) . '</span></summary><div class="mt-3">';

    if (!empty($rec['description'])) {
        echo '<p class="hw-text text-sm mb-3">' . e((string)$rec['description']) . '</p>';
    }

    $ings = (array)($rec['ingredients'] ?? []);
    if ($ings) {
        echo '<div class="hw-grid2">';
        foreach ($ings as $ing) {
            $qty = trim((string)($ing['amount'] ?? '') . ' ' . (string)($ing['unit'] ?? ''));
            echo '<div class="hw-ing"><span>' . e((string)($ing['name'] ?? '')) . '</span>'
               . '<span class="hw-sub">' . e($qty) . '</span></div>';
        }
        echo '</div>';
    }

    $steps = (array)($rec['steps'] ?? []);
    if ($steps) {
        echo '<ol class="mt-3 space-y-1.5 list-decimal pl-5">';
        foreach ($steps as $s) {
            $text = is_array($s) ? (string)($s['instruction'] ?? '') : (string)$s;
            if ($text === '') continue;
            echo '<li class="hw-text text-sm">' . e($text) . '</li>';
        }
        echo '</ol>';
    }

    // Работает внутри x-data="holidayDetail()": created[id] = slug рецепта на сайте
    $id = (int)$row['id'];
    echo '<div class="mt-3 flex flex-wrap gap-2">'
       . '<a class="hw-btn" x-show="created[' . $id . ']" style="display:none" target="_blank"'
       . ' :href="\'/recipe/\' + encodeURIComponent(created[' . $id . '] || \'\')">Открыть рецепт на сайте</a>'
       . '<button type="button" class="hw-btn" x-show="!created[' . $id . ']" :disabled="busy"'
       . ' @click="createRecipe(' . $id . ')">Создать рецепт на сайте</button>'
       . '</div>';
    echo '</div></details>';
}
