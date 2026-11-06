<?php
/**
 * POST /api/holiday_manage.php   (JSON, заголовок X-CSRF-Token)
 *
 *   rename  — {holiday_id, title}
 *   delete  — {holiday_id}           (блюда и советы удаляются каскадом)
 *   status  — {holiday_id, status: draft|ready}
 *   email   — {holiday_id, email?}   письмо со списком покупок; не больше 5 в сутки
 *   create_recipe — {holiday_id, item_id, force?}  создать на сайте полноценный рецепт из блюда AI
 *   link_recipe   — {holiday_id, item_id, recipe_id}  привязать к блюду уже имеющийся свой рецепт
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_shopping.php';
require_once __DIR__ . '/../includes/recipe_publisher.php';

const HM_MAIL_LIMIT      = 5;
const HM_RECIPES_PER_DAY = 40;

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

/** Сколько писем пользователь уже отправил сегодня (счётчик в ai_usage). */
function hm_mail_used(string $identifier): int
{
    try {
        $row = db_one(
            'SELECT SUM(used_count) AS total FROM ai_usage WHERE identifier = ? AND feature = ? AND used_date = ?',
            [$identifier, 'holiday_mail', date('Y-m-d')]
        );
        return (int)($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function hm_mail_count(string $identifier): void
{
    try {
        db_exec(
            'INSERT INTO ai_usage (identifier, feature, used_date, used_count) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE used_count = used_count + 1',
            [$identifier, 'holiday_mail', date('Y-m-d')]
        );
    } catch (Throwable $e) {
        error_log('hm_mail_count: ' . $e->getMessage());
    }
}

/** Суточный счётчик по любой функции (общая таблица ai_usage). */
function hm_usage_get(string $identifier, string $feature): int
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

function hm_usage_inc(string $identifier, string $feature): void
{
    try {
        db_exec(
            'INSERT INTO ai_usage (identifier, feature, used_date, used_count) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE used_count = used_count + 1',
            [$identifier, $feature, date('Y-m-d')]
        );
    } catch (Throwable $e) {
        error_log('hm_usage_inc: ' . $e->getMessage());
    }
}

// ---------- доступ ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') hfail('Только POST', 405);
if (!is_logged_in())                       hfail('Требуется вход', 401);
if (!csrf_check())                         hfail('Сессия устарела, обновите страницу', 403);

ini_set('display_errors', '0');

$user      = current_user();
$userId    = (int)$user['id'];
$userEmail = (string)$user['email'];
$in        = json_input();
$action    = (string)($in['action'] ?? '');

try {
    switch ($action) {

        case 'rename':
            $h     = h_load($in, $userId);
            $title = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 120);
            if (mb_strlen($title) < 3) hfail('Название слишком короткое');
            db_exec('UPDATE holidays SET title = ? WHERE id = ?', [$title, (int)$h['id']]);
            json_response(['ok' => true, 'title' => $title]);

        case 'date':
            $h    = h_load($in, $userId);
            $date = trim((string)($in['event_date'] ?? ''));
            if ($date !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $date);
                if (!$d || $d->format('Y-m-d') !== $date) hfail('Некорректная дата');
            } else {
                $date = null;
            }
            db_exec('UPDATE holidays SET event_date = ? WHERE id = ?', [$date, (int)$h['id']]);
            json_response(['ok' => true, 'event_date' => $date]);

        case 'delete':
            $h = h_load($in, $userId);
            db_exec('DELETE FROM holidays WHERE id = ? AND user_id = ?', [(int)$h['id'], $userId]);
            json_response(['ok' => true]);

        case 'status':
            $h  = h_load($in, $userId);
            $st = ((string)($in['status'] ?? '') === 'ready') ? 'ready' : 'draft';
            db_exec('UPDATE holidays SET status = ?, stage = IF(? = \'ready\', 4, stage) WHERE id = ?', [$st, $st, (int)$h['id']]);
            json_response(['ok' => true, 'status' => $st]);

        case 'email':
            $h  = h_load($in, $userId);
            $to = trim((string)($in['email'] ?? ''));
            if ($to === '') $to = $userEmail;
            if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
                hfail('Некорректный email');
            }

            $groups = hs_groups((int)$h['id']);
            if (!$groups) hfail('Список покупок пуст: сначала составьте меню', 409);

            if (hm_mail_used($userEmail) >= HM_MAIL_LIMIT) {
                hfail('Лимит писем на сегодня исчерпан (' . HM_MAIL_LIMIT . '). Скачайте CSV или напечатайте список.', 429);
            }

            $subject = '=?UTF-8?B?' . base64_encode('Список покупок: ' . $h['title']) . '?=';
            $headers = "MIME-Version: 1.0\r\n"
                     . "Content-Type: text/html; charset=utf-8\r\n"
                     . "From: GigaChef <no-reply@gigachef.ru>\r\n"
                     . "Reply-To: no-reply@gigachef.ru\r\n";

            $sent = @mail($to, $subject, hs_email_html($h, $groups), $headers, '-fno-reply@gigachef.ru');
            if (!$sent) hfail('Не удалось отправить письмо', 502);

            hm_mail_count($userEmail);
            json_response(['ok' => true, 'message' => 'Список отправлен на ' . $to . '. Проверьте папку «Спам».']);

        case 'create_recipe':
            $h    = h_load($in, $userId);
            $item = db_one(
                'SELECT * FROM holiday_recipes WHERE id = ? AND holiday_id = ?',
                [(int)($in['item_id'] ?? 0), (int)$h['id']]
            );
            if (!$item) hfail('Блюдо не найдено', 404);

            // рецепт на сайте уже есть
            if (!empty($item['recipe_id'])) {
                $ex = db_one('SELECT id, slug FROM recipes WHERE id = ?', [(int)$item['recipe_id']]);
                if ($ex) {
                    json_response(['ok' => true, 'already' => true, 'recipe_id' => (int)$ex['id'], 'slug' => (string)$ex['slug']]);
                }
            }

            if (hm_usage_get($userEmail, 'holiday_recipe') >= HM_RECIPES_PER_DAY) {
                hfail('Дневной лимит создания рецептов исчерпан. Продолжите завтра.', 429);
            }

            // повод и диета праздника переходят в теги рецепта
            $tagMap    = rc_tag_map();
            $requested = [];
            if (($tagMap[(string)$h['occasion_slug']] ?? '') === 'holiday') {
                $requested['holiday'] = (string)$h['occasion_slug'];
            }
            foreach (json_field($h['diet_json'] ?? null) as $s) {
                if (($tagMap[(string)$s] ?? '') === 'diet') { $requested['diet'] = (string)$s; break; }
            }

            $res = rp_create_recipe(json_field($item['recipe_json']), $userId, $userEmail, [
                'force'     => !empty($in['force']),
                'requested' => $requested,
                'points'    => 20,
                'label'     => 'Создание рецепта из праздника',
            ]);
            if (!$res['ok']) {
                json_response(['ok' => true, 'duplicate' => $res['duplicate']]);
            }

            db_exec(
                'UPDATE holiday_recipes SET recipe_id = ?, recipe_slug = ? WHERE id = ?',
                [$res['recipe_id'], $res['slug'], (int)$item['id']]
            );
            hm_usage_inc($userEmail, 'holiday_recipe');

            json_response([
                'ok'             => true,
                'recipe_id'      => $res['recipe_id'],
                'slug'           => $res['slug'],
                'category_saved' => $res['category_saved'],
            ]);

        case 'link_recipe':
            $h    = h_load($in, $userId);
            $item = db_one(
                'SELECT id FROM holiday_recipes WHERE id = ? AND holiday_id = ?',
                [(int)($in['item_id'] ?? 0), (int)$h['id']]
            );
            if (!$item) hfail('Блюдо не найдено', 404);

            $rec = db_one(
                'SELECT id, slug FROM recipes WHERE id = ? AND author_email = ?',
                [(int)($in['recipe_id'] ?? 0), $userEmail]
            );
            if (!$rec) hfail('Рецепт не найден', 404);

            db_exec(
                'UPDATE holiday_recipes SET recipe_id = ?, recipe_slug = ? WHERE id = ?',
                [(int)$rec['id'], (string)$rec['slug'], (int)$item['id']]
            );
            json_response(['ok' => true, 'slug' => (string)$rec['slug']]);

        default:
            hfail('Неизвестное действие');
    }
} catch (Throwable $e) {
    error_log('[holiday_manage] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    hfail(APP_DEBUG || is_admin() ? 'Ошибка: ' . $e->getMessage() : 'Не удалось выполнить запрос', 500);
}
