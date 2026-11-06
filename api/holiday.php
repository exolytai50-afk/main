<?php
/**
 * POST /api/holiday.php   (JSON, заголовок X-CSRF-Token)
 *
 * Этап 1 — меню праздника. Каждое действие = один короткий запрос.
 *
 *   create   — создать праздник (черновик). Тратит 1 единицу лимита 'holiday'.
 *   plan     — найти готовые рецепты в базе, вернуть, чего не хватает.   (без AI)
 *   suggest  — ОДИН AI-запрос: названия блюд для недостающих мест.
 *   recipe   — ОДИН AI-запрос: полный рецепт одного блюда.
 *   remove   — убрать блюдо из меню.                                      (без AI)
 *   get      — состояние праздника (для продолжения черновика).           (без AI)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/ai_guard.php';
require_once __DIR__ . '/../includes/holiday_helpers.php';

// ---------- вспомогательные ----------

function hfail(string $msg, int $code = 400): void
{
    json_response(['ok' => false, 'error' => $msg], $code);
}

function h_load(array $in, int $userId): array
{
    $id = (int)($in['holiday_id'] ?? 0);
    $h  = $id > 0 ? holiday_find_owned($id, $userId) : null;
    if (!$h) hfail('Праздник не найден', 404);
    return $h;
}

/** Проверяет потолок AI-запросов на праздник и увеличивает счётчик. */
function h_ai_charge(array $h): void
{
    if ((int)$h['ai_calls'] >= HOLIDAY_MAX_AI_CALLS) {
        hfail('Лимит AI-запросов для этого праздника исчерпан', 429);
    }
    db_exec('UPDATE holidays SET ai_calls = ai_calls + 1 WHERE id = ?', [(int)$h['id']]);
}

function h_ai_error(Throwable $e): void
{
    error_log('[holiday] ' . $e->getMessage());
    hfail(APP_DEBUG ? $e->getMessage() : 'AI временно недоступен, попробуйте ещё раз', 502);
}

function h_counts(int $holidayId): array
{
    $out = [];
    foreach (db_all('SELECT course, COUNT(*) AS n FROM holiday_recipes WHERE holiday_id = ? GROUP BY course', [$holidayId]) as $r) {
        $out[$r['course']] = (int)$r['n'];
    }
    return $out;
}

/** course => сколько блюд ещё не хватает до плана. */
function h_missing(array $h): array
{
    $plan   = json_field($h['plan_json'] ?? null);
    $counts = h_counts((int)$h['id']);
    $out    = [];
    foreach ($plan as $course => $n) {
        $left = (int)$n - ($counts[$course] ?? 0);
        if ($left > 0 && isset(HOLIDAY_COURSES[$course])) $out[$course] = $left;
    }
    return $out;
}

// ---------- проверки доступа ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST')  hfail('Только POST', 405);
if (!is_logged_in())                        hfail('Требуется вход', 401);
if (!csrf_check())                          hfail('Сессия устарела, обновите страницу', 403);

@set_time_limit(120);

$user      = current_user();
$userId    = (int)$user['id'];
$userEmail = (string)$user['email'];
$in        = json_input();
$action    = (string)($in['action'] ?? '');

// ---------- действия ----------

switch ($action) {

    // ========================================================
    case 'create':
    // ========================================================
        $occasions = holiday_occasions();
        $occSlug   = trim((string)($in['occasion'] ?? ''));

        if ($occSlug === 'custom') {
            $occName = mb_substr(trim(strip_tags((string)($in['custom_name'] ?? ''))), 0, 60);
            if (mb_strlen($occName) < 3) hfail('Опишите повод (минимум 3 символа)');
        } elseif (isset($occasions[$occSlug])) {
            $occName = $occasions[$occSlug]['name'];
        } else {
            hfail('Выберите повод');
        }

        $guests = max(1, min(30, (int)($in['guests'] ?? 4)));

        $budget = (string)($in['budget'] ?? 'mid');
        if (!isset(HOLIDAY_BUDGETS[$budget])) $budget = 'mid';

        $age = (string)($in['age_group'] ?? 'adults');
        if (!isset(HOLIDAY_AGES[$age])) $age = 'adults';

        $date = trim((string)($in['event_date'] ?? ''));
        if ($date !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) hfail('Некорректная дата');
        } else {
            $date = null;
        }

        $dietIn = array_values(array_filter((array)($in['diet'] ?? []), 'is_string'));
        $diet   = array_slice(array_values(array_intersect($dietIn, array_keys(holiday_diet_map()))), 0, 8);

        $notes = mb_substr(trim(strip_tags((string)($in['notes'] ?? ''))), 0, 300);

        $cnt = (int)db_value('SELECT COUNT(*) FROM holidays WHERE user_id = ?', [$userId]);
        if ($cnt >= 50) hfail('Достигнут предел в 50 праздников. Удалите ненужные.');

        try {
            ai_guard('holiday');
        } catch (RuntimeException $e) {
            hfail($e->getMessage(), 429);
        }

        $title = $occName . ($date ? ' ' . date('d.m.Y', strtotime($date)) : '');
        $base  = generate_slug($title);
        $slug  = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        for ($i = 0; $i < 5 && db_one('SELECT id FROM holidays WHERE slug = ?', [$slug]); $i++) {
            $slug = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }

        $plan = holiday_course_plan($guests, $budget, $age, $occSlug);

        $id = db_insert(
            'INSERT INTO holidays
                (user_id, author_email, slug, title, occasion_slug, occasion_name, event_date,
                 guests, budget, age_group, diet_json, notes, plan_json, stage, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'draft\', NOW(), NOW())',
            [
                $userId, $userEmail, $slug, $title, $occSlug, $occName, $date,
                $guests, $budget, $age,
                json_encode($diet, JSON_UNESCAPED_UNICODE), $notes,
                json_encode($plan, JSON_UNESCAPED_UNICODE),
            ]
        );

        json_response(['ok' => true, 'holiday' => holiday_public(holiday_find_owned($id, $userId))]);

    // ========================================================
    case 'plan':
    // ========================================================
        $h    = h_load($in, $userId);
        $plan = json_field($h['plan_json'] ?? null);

        db_exec('DELETE FROM holiday_recipes WHERE holiday_id = ?', [(int)$h['id']]);

        $exclude = [];
        foreach ($plan as $course => $n) {
            $n = (int)$n;
            if ($n <= 0 || !isset(HOLIDAY_COURSES[$course])) continue;

            $servings = holiday_dish_servings((int)$h['guests'], $n);
            $found    = holiday_search_recipes($h, $course, $n, $exclude);

            $sort = 0;
            foreach ($found as $r) {
                $exclude[] = (int)$r['id'];
                holiday_insert_item(
                    (int)$h['id'], $course, 'db',
                    (int)$r['id'], (string)($r['slug'] ?? ''),
                    holiday_scale_recipe($r, $servings), $sort++
                );
            }
        }

        json_response([
            'ok'      => true,
            'items'   => holiday_items((int)$h['id']),
            'missing' => (object)h_missing($h),
        ]);

    // ========================================================
    case 'suggest':
    // ========================================================
        $h       = h_load($in, $userId);
        $missing = h_missing($h);
        if (!$missing) json_response(['ok' => true, 'queue' => []]);

        $titles = array_column(db_all('SELECT title FROM holiday_recipes WHERE holiday_id = ?', [(int)$h['id']]), 'title');

        h_ai_charge($h);
        try {
            $queue = holiday_ai_menu_gaps($h, $missing, $titles);
        } catch (Throwable $e) {
            h_ai_error($e);
        }
        json_response(['ok' => true, 'queue' => $queue]);

    // ========================================================
    case 'recipe':
    // ========================================================
        $h      = h_load($in, $userId);
        $course = (string)($in['course'] ?? '');
        $title  = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 150);

        if (!isset(HOLIDAY_COURSES[$course]) || mb_strlen($title) < 3) hfail('Неверные параметры');

        $plan   = json_field($h['plan_json'] ?? null);
        $counts = h_counts((int)$h['id']);
        $slots  = (int)($plan[$course] ?? 0);

        if (($counts[$course] ?? 0) >= $slots) hfail('В этом разделе уже достаточно блюд', 409);
        if (db_one('SELECT id FROM holiday_recipes WHERE holiday_id = ? AND LOWER(title) = ?', [(int)$h['id'], mb_strtolower($title)])) {
            hfail('Такое блюдо уже в меню', 409);
        }

        h_ai_charge($h);
        try {
            $rec = holiday_ai_recipe($h, $course, $title, holiday_dish_servings((int)$h['guests'], $slots));
        } catch (Throwable $e) {
            h_ai_error($e);
        }

        $itemId = holiday_insert_item((int)$h['id'], $course, 'ai', null, null, $rec, $counts[$course] ?? 0);
        $row    = db_one('SELECT * FROM holiday_recipes WHERE id = ?', [$itemId]);

        json_response(['ok' => true, 'item' => holiday_item_public($row)]);

    // ========================================================
    case 'remove':
    // ========================================================
        $itemId = (int)($in['item_id'] ?? 0);
        $row = db_one(
            'SELECT hr.id FROM holiday_recipes hr
             JOIN holidays h ON h.id = hr.holiday_id
             WHERE hr.id = ? AND h.user_id = ?',
            [$itemId, $userId]
        );
        if (!$row) hfail('Блюдо не найдено', 404);

        db_exec('DELETE FROM holiday_recipes WHERE id = ?', [$itemId]);
        json_response(['ok' => true]);

    // ========================================================
    case 'get':
    // ========================================================
        $h = h_load($in, $userId);
        json_response([
            'ok'      => true,
            'holiday' => holiday_public($h),
            'items'   => holiday_items((int)$h['id']),
            'missing' => (object)h_missing($h),
        ]);

    // ========================================================
    default:
        hfail('Неизвестное действие');
}
