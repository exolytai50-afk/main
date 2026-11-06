<?php
/**
 * API постов сообщества.
 *
 * GET  ?action=list&community_id=ID[&offset=N][&filter=all|owner]
 * POST action=create  community_id,content[,image]
 * POST action=update  post_id,content[,image][,remove_image=1]
 * POST action=delete  post_id
 * POST action=pin     post_id            (только владелец сообщества)
 * POST action=vote    post_id,vote=1|-1  (повторный клик снимает голос)
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

function gc_require_same_origin(): void
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        if (!empty($_SERVER[$h])) {
            $src  = parse_url($_SERVER[$h], PHP_URL_HOST);
            $port = parse_url($_SERVER[$h], PHP_URL_PORT);
            $srcHost = $src . ($port ? ':' . $port : '');
            if (strcasecmp($srcHost, $host) !== 0 && strcasecmp((string)$src, preg_replace('/:\d+$/', '', $host)) !== 0) {
                gc_fail('Запрос отклонён', 403);
            }
            return;
        }
    }
}

/** Права на конкретное сообщество. */
function gc_community_access(int $communityId): ?array
{
    $c = db_one('SELECT id, author_email, slug FROM communities WHERE id = ?', [$communityId]);
    if (!$c) return null;
    $v     = gc_viewer();
    $owner = (string)($c['author_email'] ?? '');
    $isOwner = $v['logged'] && $v['email'] === $owner;
    return [
        'community'   => $c,
        'owner_email' => $owner,
        'is_owner'    => $isOwner,
        'is_member'   => $v['logged'] && gc_is_member($communityId, $v['email'], $owner),
        'moderator'   => $isOwner || ($v['logged'] && $v['is_admin']),
    ];
}

function gc_cx(array $acc): array
{
    return [
        'owner_email' => $acc['owner_email'],
        'is_member'   => $acc['is_member'],
        'moderator'   => $acc['moderator'],
        'is_owner'    => $acc['is_owner'],
    ];
}

try {
    gc_schema_ensure();
    $action = (string)($_REQUEST['action'] ?? '');
    $viewer = gc_viewer();

    /* ───────── Список (подгрузка при скролле) ───────── */
    if ($action === 'list') {
        $cid = (int)($_GET['community_id'] ?? 0);
        $acc = gc_community_access($cid);
        if (!$acc) gc_fail('Сообщество не найдено', 404);

        $offset    = max(0, (int)($_GET['offset'] ?? 0));
        $ownerOnly = (($_GET['filter'] ?? 'all') === 'owner');
        $per       = GC_POSTS_PER_PAGE;
        $rows      = gc_fetch_community_posts($cid, $per + 1, $offset, $ownerOnly, $acc['owner_email']);
        $hasMore   = count($rows) > $per;
        $rows      = array_slice($rows, 0, $per);

        gc_out([
            'ok'          => true,
            'html'        => gc_render_community_posts($rows, gc_cx($acc)),
            'has_more'    => $hasMore,
            'next_offset' => $offset + count($rows),
            'count'       => count($rows),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') gc_fail('Метод не поддерживается', 405);
    gc_require_same_origin();
    if (!$viewer['logged']) gc_fail('Войдите, чтобы продолжить', 401);

    /* ───────── Создать ───────── */
    if ($action === 'create') {
        $cid = (int)($_POST['community_id'] ?? 0);
        $acc = gc_community_access($cid);
        if (!$acc) gc_fail('Сообщество не найдено', 404);
        if (!$acc['is_member']) gc_fail('Публиковать записи могут только участники сообщества', 403);

        $r = db_one(
            "SELECT COUNT(*) AS n FROM community_posts WHERE user_email COLLATE utf8mb4_unicode_ci = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$viewer['email']]
        );
        if ((int)($r['n'] ?? 0) >= 5) gc_fail('Слишком часто. Подождите минуту.', 429);

        $clean = gc_sanitize((string)($_POST['content'] ?? ''));
        if ($clean['len'] > GC_POST_MAX_CHARS) gc_fail('Запись слишком длинная (максимум ' . GC_POST_MAX_CHARS . ' символов)');

        $img = gc_save_image($_FILES['image'] ?? null, 'posts');
        if (!$img['ok']) gc_fail($img['error']);
        if ($clean['html'] === '' && !$img['url']) gc_fail('Напишите текст или прикрепите фото');

        $pid = db_insert(
            'INSERT INTO community_posts (community_id, user_email, content, type, photo_url) VALUES (?, ?, ?, ?, ?)',
            [$cid, $viewer['email'], $clean['html'], $img['url'] ? 'photo' : 'text', $img['url']]
        );
        if (!$pid) {
            $m   = db_one('SELECT MAX(id) AS m FROM community_posts WHERE user_email COLLATE utf8mb4_unicode_ci = ?', [$viewer['email']]);
            $pid = (int)($m['m'] ?? 0);
        }
        $post = gc_fetch_community_post((int)$pid);
        if (!$post) gc_fail('Не удалось сохранить запись', 500);
        gc_out(['ok' => true, 'html' => gc_render_community_post($post, gc_cx($acc)), 'id' => (int)$pid]);
    }

    /* Для остальных действий нужен пост */
    $pid  = (int)($_POST['post_id'] ?? 0);
    $post = gc_fetch_community_post($pid);
    if (!$post) gc_fail('Запись не найдена', 404);
    $acc = gc_community_access((int)$post['community_id']);
    if (!$acc) gc_fail('Сообщество не найдено', 404);
    $isAuthor = $post['user_email'] === $viewer['email'];

    /* ───────── Изменить ───────── */
    if ($action === 'update') {
        if (!$isAuthor) gc_fail('Можно изменять только свои записи', 403);

        $clean = gc_sanitize((string)($_POST['content'] ?? ''));
        if ($clean['len'] > GC_POST_MAX_CHARS) gc_fail('Запись слишком длинная (максимум ' . GC_POST_MAX_CHARS . ' символов)');

        $oldUrl = !empty($post['photo_url']) ? $post['photo_url'] : ($post['image_url'] ?? null);
        $newUrl = $oldUrl;
        $up = gc_save_image($_FILES['image'] ?? null, 'posts');
        if (!$up['ok']) gc_fail($up['error']);
        if ($up['url'])                         $newUrl = $up['url'];
        elseif (!empty($_POST['remove_image'])) $newUrl = null;

        if ($clean['html'] === '' && !$newUrl) {
            if ($up['url']) gc_delete_image($up['url']);
            gc_fail('Запись не может быть пустой');
        }
        db_exec(
            "UPDATE community_posts SET content = ?, photo_url = ?, image_url = NULL, is_edited = 1 WHERE id = ?",
            [$clean['html'], $newUrl, $pid]
        );
        if ($oldUrl && $oldUrl !== $newUrl) gc_delete_image($oldUrl);
        gc_out(['ok' => true, 'html' => gc_render_community_post(gc_fetch_community_post($pid), gc_cx($acc))]);
    }

    /* ───────── Удалить (автор или владелец сообщества) ───────── */
    if ($action === 'delete') {
        if (!$isAuthor && !$acc['moderator']) gc_fail('Нет прав на удаление', 403);

        // картинки комментариев, лайки комментариев, комментарии
        $comments = db_all('SELECT id, image_url FROM community_post_comments WHERE post_id = ?', [$pid]);
        foreach ($comments as $cm) {
            gc_delete_image($cm['image_url'] ?? null);
            db_exec("DELETE FROM community_votes WHERE target_type = 'comment' AND target_id = ?", [(int)$cm['id']]);
        }
        db_exec('DELETE FROM community_post_comments WHERE post_id = ?', [$pid]);
        db_exec("DELETE FROM community_votes WHERE target_type = 'post' AND target_id = ?", [$pid]);
        db_exec('DELETE FROM community_posts WHERE id = ?', [$pid]);
        gc_delete_image(!empty($post['photo_url']) ? $post['photo_url'] : ($post['image_url'] ?? null));
        gc_out(['ok' => true]);
    }

    /* ───────── Закрепить / открепить (только владелец) ───────── */
    if ($action === 'pin') {
        if (!$acc['is_owner']) gc_fail('Закреплять записи может только владелец сообщества', 403);
        $new = empty($post['is_pinned']) ? 1 : 0;
        if ($new) {
            db_exec('UPDATE community_posts SET is_pinned = 0 WHERE community_id = ?', [(int)$post['community_id']]);
        }
        db_exec('UPDATE community_posts SET is_pinned = ? WHERE id = ?', [$new, $pid]);
        gc_out(['ok' => true, 'pinned' => (bool)$new]);
    }

    /* ───────── Голос за запись ───────── */
    if ($action === 'vote') {
        $vote = (int)($_POST['vote'] ?? 0);
        if ($vote !== 1 && $vote !== -1) gc_fail('Некорректный голос');
        $cur = (int)($post['my_vote'] ?? 0);
        if ($cur === $vote) {
            db_exec("DELETE FROM community_votes WHERE target_type = 'post' AND target_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?", [$pid, $viewer['email']]);
            $my = 0;
        } else {
            db_exec(
                "INSERT INTO community_votes (target_type, target_id, user_email, vote) VALUES ('post', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE vote = VALUES(vote)",
                [$pid, $viewer['email'], $vote]
            );
            $my = $vote;
        }
        $fresh = gc_fetch_community_post($pid);
        gc_out([
            'ok'       => true,
            'my_vote'  => $my,
            'likes'    => (int)($fresh['like_count'] ?? 0),
            'dislikes' => (int)($fresh['dislike_count'] ?? 0),
        ]);
    }

    gc_fail('Неизвестное действие');
} catch (Throwable $e) {
    error_log('gc_community_posts.php: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gc_out(['ok' => false, 'error' => 'Ошибка сервера. Попробуйте позже.'], 500);
}
