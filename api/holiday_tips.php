<?php
/**
 * POST /api/holiday_tips.php   (JSON, заголовок X-CSRF-Token)
 *
 *   get       — все советы праздника (serving, drinks, decor)
 *   generate  — ОДИН AI-запрос на один раздел: serving | drinks | decor.
 *               Повторный вызов перезаписывает раздел («Обновить»).
 *
 * Лимит 'holiday' тратится при создании праздника (api/holiday.php);
 * здесь действует только потолок AI-запросов на один праздник.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/holiday_tips_helpers.php';

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

function h_ai_charge(array $h): void
{
    if ((int)$h['ai_calls'] >= HOLIDAY_MAX_AI_CALLS) {
        hfail('Лимит AI-запросов для этого праздника исчерпан', 429);
    }
    db_exec('UPDATE holidays SET ai_calls = ai_calls + 1 WHERE id = ?', [(int)$h['id']]);
}

function h_ai_error(Throwable $e): void
{
    error_log('[holiday_tips] ' . $e->getMessage());
    hfail(APP_DEBUG || is_admin() ? $e->getMessage() : 'AI временно недоступен, попробуйте ещё раз', 502);
}

// ---------- доступ ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') hfail('Только POST', 405);
if (!is_logged_in())                       hfail('Требуется вход', 401);
if (!csrf_check())                         hfail('Сессия устарела, обновите страницу', 403);

ini_set('display_errors', '0');
@set_time_limit(120);

$userId = (int)current_user()['id'];
$in     = json_input();
$action = (string)($in['action'] ?? '');

try {
    switch ($action) {

        case 'get':
            $h = h_load($in, $userId);
            json_response(['ok' => true, 'tips' => ht_load_tips((int)$h['id'])]);

        case 'generate':
            $h       = h_load($in, $userId);
            $section = (string)($in['section'] ?? '');
            if (!isset(HOLIDAY_TIP_SECTIONS[$section])) hfail('Неизвестный раздел');

            $menu = ht_menu_by_course((int)$h['id']);
            if (!$menu && $section !== 'decor') {
                hfail('Сначала составьте меню', 409);
            }

            h_ai_charge($h);
            try {
                $content = ht_generate($h, $section, $menu);
            } catch (Throwable $e) {
                h_ai_error($e);
            }

            ht_save((int)$h['id'], $section, $content);
            db_exec(
                'UPDATE holidays SET stage = GREATEST(stage, ?) WHERE id = ?',
                [HOLIDAY_TIP_SECTIONS[$section]['stage'], (int)$h['id']]
            );
            json_response(['ok' => true, 'content' => $content]);

        default:
            hfail('Неизвестное действие');
    }
} catch (Throwable $e) {
    error_log('[holiday_tips] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    hfail(APP_DEBUG || is_admin() ? 'Ошибка: ' . $e->getMessage() : 'Не удалось выполнить запрос', 500);
}
