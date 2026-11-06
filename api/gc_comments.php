<?php
/**
 * API комментариев (лента `feed` и посты сообществ `community`).
 *
 * GET  ?action=list&ctx=feed|community&id=ID[&before=CID]
 * GET  ?action=emojis
 * POST action=add    ctx,id,content[,image]
 * POST action=edit   ctx,cid,content[,image][,remove_image=1]
 * POST action=delete ctx,cid
 * POST action=like   ctx,cid
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gc_social.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function gc_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gc_fail(string $msg, int $code = 400): void
{
    gc_out(['ok' => false, 'error' => $msg], $code);
}

/** Защита от CSRF: запрос должен прийти с нашего же сайта. */
function gc_require_same_origin(): void
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        if (!empty($_SERVER[$h])) {
            $src = parse_url($_SERVER[$h], PHP_URL_HOST);
            $port = parse_url($_SERVER[$h], PHP_URL_PORT);
            $srcHost = $src . ($port ? ':' . $port : '');
            if (strcasecmp($srcHost, $host) !== 0 && strcasecmp((string)$src, preg_replace('/:\d+$/', '', $host)) !== 0) {
                gc_fail('Запрос отклонён', 403);
            }
            return;
        }
    }
}

try {
    gc_schema_ensure();

    $action = (string)($_REQUEST['action'] ?? '');
    $ctx    = (string)($_REQUEST['ctx'] ?? '');
    $viewer = gc_viewer();

    /* ───────── Смайлы (общие для всех страниц) ───────── */
    if ($action === 'emojis') {
        header('Cache-Control: public, max-age=300');
        $packs = [];
        $by    = [];
        try {
            $packs = db_all(
                "SELECT ep.id, ep.name,
                        (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC, ei.id ASC LIMIT 1) AS first_emoji
                 FROM emoji_packs ep WHERE ep.is_active = 1 ORDER BY ep.sort_order ASC, ep.id ASC"
            );
            $imgs = db_all(
                "SELECT ei.id, ei.pack_id, ei.code, ei.image_url
                 FROM emoji_images ei JOIN emoji_packs ep ON ep.id = ei.pack_id
                 WHERE ep.is_active = 1 ORDER BY ei.pack_id, ei.sort_order ASC, ei.id ASC"
            );
            foreach ($imgs as $i) {
                $by[(int)$i['pack_id']][] = ['id' => (int)$i['id'], 'code' => $i['code'], 'image_url' => $i['image_url']];
            }
        } catch (Throwable $e) {
            $packs = [];
            $by    = [];
        }
        gc_out(['ok' => true, 'packs' => $packs, 'by_pack' => (object)$by]);
    }

    if (!gc_ctx($ctx)) gc_fail('Неизвестный контекст');

    /* ───────── Список комментариев ───────── */
    if ($action === 'list') {
        $id = (int)($_GET['id'] ?? 0);
        $before = (int)($_GET['before'] ?? 0);
        $acc = gc_parent_access($ctx, $id);
        if (!$acc['exists'])   gc_fail('Запись не найдена', 404);
        if (!$acc['can_view']) gc_fail('Нет доступа', 403);

        $limit = $before > 0 ? GC_COMMENTS_NEXT_PAGE : GC_COMMENTS_FIRST_PAGE;
        $res   = gc_fetch_comments($ctx, $id, $limit, $before);
        $o = ['moderator' => $acc['moderator'], 'owner_email' => $acc['owner_email'], 'owner_badge' => $ctx === 'community' ? 'Владелец' : 'Автор'];
        $total = gc_count($ctx, $id);
        gc_out([
            'ok'        => true,
            'html'      => gc_render_comment_list($res['rows'], $o),
            'remaining' => $res['remaining'],
            'total'     => $total,
            'first_id'  => $res['rows'] ? (int)$res['rows'][0]['id'] : 0,
        ]);
    }

    /* Все остальные действия — только POST от залогиненного пользователя */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') gc_fail('Метод не поддерживается', 405);
    gc_require_same_origin();
    if (!$viewer['logged']) gc_fail('Войдите, чтобы продолжить', 401);
    $cfg = gc_ctx($ctx);

    /* ───────── Добавить ───────── */
    if ($action === 'add') {
        $id  = (int)($_POST['id'] ?? 0);
        $acc = gc_parent_access($ctx, $id);
        if (!$acc['exists']) gc_fail('Запись не найдена', 404);
        if (!$acc['can_comment']) {
            gc_fail($ctx === 'community' ? 'Комментировать могут только участники сообщества' : 'Нет доступа', 403);
        }

        // антифлуд: не больше 10 комментариев в минуту
        $r = db_one(
            "SELECT COUNT(*) AS n FROM {$cfg['table']} WHERE user_email COLLATE utf8mb4_unicode_ci = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$viewer['email']]
        );
        if ((int)($r['n'] ?? 0) >= 10) gc_fail('Слишком часто. Подождите минуту.', 429);

        $clean = gc_sanitize((string)($_POST['content'] ?? ''));
        if ($clean['len'] > GC_COMMENT_MAX_CHARS) gc_fail('Комментарий слишком длинный (максимум ' . GC_COMMENT_MAX_CHARS . ' символов)');

        $img = gc_save_image($_FILES['image'] ?? null, 'comments');
        if (!$img['ok']) gc_fail($img['error']);
        if ($clean['html'] === '' && !$img['url']) gc_fail('Напишите комментарий или прикрепите фото');

        $cid = db_insert(
            "INSERT INTO {$cfg['table']} ({$cfg['fk']}, user_email, content, image_url) VALUES (?, ?, ?, ?)",
            [$id, $viewer['email'], $clean['html'], $img['url']]
        );
        if (!$cid) {
            $m   = db_one("SELECT MAX(id) AS m FROM {$cfg['table']} WHERE user_email COLLATE utf8mb4_unicode_ci = ?", [$viewer['email']]);
            $cid = (int)($m['m'] ?? 0);
        }
        $row = gc_fetch_comment($ctx, (int)$cid);
        if (!$row) gc_fail('Не удалось сохранить комментарий', 500);

        $o = ['moderator' => $acc['moderator'], 'owner_email' => $acc['owner_email'], 'owner_badge' => $ctx === 'community' ? 'Владелец' : 'Автор'];
        unset($GLOBALS['__gc_counts'][$ctx][$id]);
        gc_out(['ok' => true, 'html' => gc_render_comment($row, $o), 'count' => gc_count($ctx, $id), 'id' => (int)$cid]);
    }

    /* Для остальных действий нужен существующий комментарий */
    $cid = (int)($_POST['cid'] ?? 0);
    $row = gc_fetch_comment($ctx, $cid);
    if (!$row) gc_fail('Комментарий не найден', 404);
    $parentId = (int)$row['parent_id'];
    $acc      = gc_parent_access($ctx, $parentId);
    $isAuthor = $row['user_email'] === $viewer['email'];
    $o = ['moderator' => $acc['moderator'], 'owner_email' => $acc['owner_email'], 'owner_badge' => $ctx === 'community' ? 'Владелец' : 'Автор'];

    /* ───────── Изменить ───────── */
    if ($action === 'edit') {
        if (!$isAuthor) gc_fail('Можно изменять только свои комментарии', 403);

        $clean = gc_sanitize((string)($_POST['content'] ?? ''));
        if ($clean['len'] > GC_COMMENT_MAX_CHARS) gc_fail('Комментарий слишком длинный (максимум ' . GC_COMMENT_MAX_CHARS . ' символов)');

        $newUrl  = $row['image_url'];
        $oldUrl  = $row['image_url'];
        $removed = !empty($_POST['remove_image']);
        $up = gc_save_image($_FILES['image'] ?? null, 'comments');
        if (!$up['ok']) gc_fail($up['error']);
        if ($up['url'])      $newUrl = $up['url'];
        elseif ($removed)    $newUrl = null;

        if ($clean['html'] === '' && !$newUrl) {
            if ($up['url']) gc_delete_image($up['url']);
            gc_fail('Комментарий не может быть пустым');
        }
        db_exec("UPDATE {$cfg['table']} SET content = ?, image_url = ?, is_edited = 1 WHERE id = ?", [$clean['html'], $newUrl, $cid]);
        if ($oldUrl && $oldUrl !== $newUrl) gc_delete_image($oldUrl);

        gc_out(['ok' => true, 'html' => gc_render_comment(gc_fetch_comment($ctx, $cid), $o)]);
    }

    /* ───────── Удалить ───────── */
    if ($action === 'delete') {
        if (!$isAuthor && !$acc['moderator']) gc_fail('Нет прав на удаление', 403);
        db_exec("DELETE FROM {$cfg['table']} WHERE id = ?", [$cid]);
        if ($ctx === 'feed') {
            db_exec('DELETE FROM feed_comment_likes WHERE comment_id = ?', [$cid]);
        } else {
            db_exec("DELETE FROM community_votes WHERE target_type = 'comment' AND target_id = ?", [$cid]);
        }
        gc_delete_image($row['image_url'] ?? null);
        unset($GLOBALS['__gc_counts'][$ctx][$parentId]);
        gc_out(['ok' => true, 'count' => gc_count($ctx, $parentId)]);
    }

    /* ───────── Лайк (переключатель) ───────── */
    if ($action === 'like') {
        if (!$acc['can_view']) gc_fail('Нет доступа', 403);
        $mine = !empty($row['my_like']);
        if ($ctx === 'feed') {
            if ($mine) {
                db_exec('DELETE FROM feed_comment_likes WHERE comment_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?', [$cid, $viewer['email']]);
            } else {
                db_exec('INSERT IGNORE INTO feed_comment_likes (comment_id, user_email) VALUES (?, ?)', [$cid, $viewer['email']]);
            }
        } else {
            if ($mine) {
                db_exec("DELETE FROM community_votes WHERE target_type = 'comment' AND target_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?", [$cid, $viewer['email']]);
            } else {
                db_exec(
                    "INSERT INTO community_votes (target_type, target_id, user_email, vote) VALUES ('comment', ?, ?, 1)
                     ON DUPLICATE KEY UPDATE vote = 1",
                    [$cid, $viewer['email']]
                );
            }
        }
        $fresh = gc_fetch_comment($ctx, $cid);
        gc_out(['ok' => true, 'liked' => !empty($fresh['my_like']), 'likes' => (int)($fresh['like_count'] ?? 0)]);
    }

    gc_fail('Неизвестное действие');
} catch (Throwable $e) {
    error_log('gc_comments.php: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gc_out(['ok' => false, 'error' => 'Ошибка сервера. Попробуйте позже.'], 500);
}
