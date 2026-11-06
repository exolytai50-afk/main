<?php
/**
 * GigaChef — общая библиотека «комментарии + посты сообществ».
 *
 * Подключается страницами (home.php, feed.php, community_detail.php),
 * компонентом components/feed-item.php и API (api/gc_*.php).
 *
 * Требует из config.php: db_one(), db_all(), db_exec(), db_insert(),
 * url(), icon(), render_avatar(), is_logged_in(), current_user(), ROOT_DIR.
 * Совместимо с PHP 7.4+.
 */
if (defined('GC_SOCIAL_LIB')) {
    return;
}
define('GC_SOCIAL_LIB', 1);

if (!defined('GC_COMMENT_MAX_CHARS')) define('GC_COMMENT_MAX_CHARS', 2000);
if (!defined('GC_POST_MAX_CHARS'))    define('GC_POST_MAX_CHARS', 10000);
if (!defined('GC_IMAGE_MAX_BYTES'))   define('GC_IMAGE_MAX_BYTES', 5 * 1024 * 1024);
if (!defined('GC_COMMENTS_FIRST_PAGE')) define('GC_COMMENTS_FIRST_PAGE', 5);
if (!defined('GC_COMMENTS_NEXT_PAGE'))  define('GC_COMMENTS_NEXT_PAGE', 15);
if (!defined('GC_POSTS_PER_PAGE'))      define('GC_POSTS_PER_PAGE', 10);

/* ═════════════════════════════════════════════════════════════
   БАЗОВЫЕ УТИЛИТЫ
   ═════════════════════════════════════════════════════════════ */

function gc_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** «1 комментарий / 2 комментария / 5 комментариев» */
function gc_plural(int $n, array $forms): string
{
    $n  = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $forms[2];
    if ($n1 > 1 && $n1 < 5) return $forms[1];
    if ($n1 === 1)          return $forms[0];
    return $forms[2];
}

function gc_comments_label(int $n): string
{
    return $n > 0
        ? $n . ' ' . gc_plural($n, ['комментарий', 'комментария', 'комментариев'])
        : 'Комментировать';
}

/** Короткое «человеческое» время: «5 мин назад», «вчера в 14:05», «3 окт в 09:12» */
function gc_time(?string $dt): string
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 45)   return 'только что';
    if ($diff < 3600) return max(1, (int)round($diff / 60)) . ' мин назад';
    $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $hm     = date('H:i', $ts);
    $today  = date('Y-m-d');
    $d      = date('Y-m-d', $ts);
    if ($d === $today)                                   return 'сегодня в ' . $hm;
    if ($d === date('Y-m-d', strtotime('-1 day')))       return 'вчера в ' . $hm;
    $m = $months[(int)date('n', $ts) - 1];
    if (date('Y', $ts) === date('Y'))                    return (int)date('j', $ts) . ' ' . $m . ' в ' . $hm;
    return (int)date('j', $ts) . ' ' . $m . ' ' . date('Y', $ts);
}

function gc_viewer(): array
{
    static $v = null;
    if ($v !== null) return $v;
    $logged = function_exists('is_logged_in') && is_logged_in();
    $u      = $logged ? current_user() : null;
    $v = [
        'logged'   => $logged && !empty($u['email']),
        'email'    => (string)($u['email'] ?? ''),
        'is_admin' => (($u['role'] ?? '') === 'admin'),
        'user'     => is_array($u) ? $u : [],
    ];
    return $v;
}

function gc_user_url(string $email): string
{
    return url('user/' . urlencode($email));
}

function gc_user_name(array $row): string
{
    $n = trim((string)($row['display_name'] ?? ''));
    return $n !== '' ? $n : 'Пользователь';
}

function gc_ava(array $row, string $size = 'md'): string
{
    $img = 'gc-ava gc-ava-' . $size;
    return render_avatar(
        [
            'avatar_url'   => $row['avatar_url']   ?? null,
            'avatar_emoji' => $row['avatar_emoji'] ?? null,
            'gender'       => $row['gender']       ?? null,
        ],
        $img . ' gc-ava-img',
        $img . ' gc-ava-fb',
        'text-sm'
    );
}

/* ═════════════════════════════════════════════════════════════
   СХЕМА БД (самоустанавливается один раз; тот же SQL лежит в migrations/)
   ═════════════════════════════════════════════════════════════ */

function gc_add_column(string $table, string $col, string $definition): void
{
    $cols = array_column(db_all("SHOW COLUMNS FROM `{$table}`"), 'Field');
    if (!in_array($col, $cols, true)) {
        db_exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
    }
}

function gc_schema_ensure(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $marker = ROOT_DIR . '/uploads/.gc_social_v1';
    if (is_file($marker)) return;

    try {
        db_exec("CREATE TABLE IF NOT EXISTS `feed_comments` (
            `id` int NOT NULL AUTO_INCREMENT,
            `activity_id` int NOT NULL,
            `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `is_edited` tinyint(1) NOT NULL DEFAULT '0',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_activity` (`activity_id`,`id`),
            KEY `idx_user` (`user_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_exec("CREATE TABLE IF NOT EXISTS `feed_comment_likes` (
            `comment_id` int NOT NULL,
            `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`comment_id`,`user_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        gc_add_column('community_post_comments', 'image_url', "varchar(500) DEFAULT NULL");
        gc_add_column('community_post_comments', 'is_edited', "tinyint(1) NOT NULL DEFAULT '0'");
        gc_add_column('community_posts', 'is_edited', "tinyint(1) NOT NULL DEFAULT '0'");
        gc_add_column('community_posts', 'is_pinned', "tinyint(1) NOT NULL DEFAULT '0'");

        @file_put_contents($marker, date('c'));
    } catch (Throwable $e) {
        error_log('gc_schema_ensure: ' . $e->getMessage());
    }
}

/* ═════════════════════════════════════════════════════════════
   САНИТАЙЗЕР ТЕКСТА (contenteditable → безопасный «текст + смайлы»)
   Хранится: экранированный текст с \n и <img class="inline-emoji"> (только из emoji_images).
   ═════════════════════════════════════════════════════════════ */

function gc_strip_host(string $src): string
{
    return preg_replace('#^https?://[^/]+#i', '', trim($src));
}

function gc_emoji_map(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        foreach (db_all('SELECT image_url, code FROM emoji_images') as $r) {
            $url = (string)$r['image_url'];
            $map[$url]                = (string)$r['code'];
            $map[gc_strip_host($url)] = (string)$r['code'];
        }
    } catch (Throwable $e) {
        // таблицы нет — работаем без кастомных смайлов
    }
    return $map;
}

/** Превращает текст в HTML; http(s)-ссылки становятся кликабельными. */
function gc_text_to_html(string $text, bool $linkify): string
{
    if (!$linkify) return gc_e($text);
    $parts = preg_split('~(https?://[^\s<>"\']+)~iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out   = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $out .= gc_e($part);
            continue;
        }
        $tail = '';
        if (preg_match('~[\.\,\;\:\!\?\)\]]+$~u', $part, $m)) {
            $tail = $m[0];
            $part = substr($part, 0, -strlen($tail));
        }
        $label = mb_strlen($part) > 60 ? mb_substr($part, 0, 57) . '…' : $part;
        $out  .= '<a href="' . gc_e($part) . '" target="_blank" rel="noopener nofollow ugc">' . gc_e($label) . '</a>' . gc_e($tail);
    }
    return $out;
}

function gc_walk_nodes(DOMNode $node, array $emoji, string &$out, int &$len, bool $linkify): void
{
    foreach ($node->childNodes as $c) {
        if ($c->nodeType === XML_TEXT_NODE) {
            $t = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], (string)$c->nodeValue);
            if ($t === '') continue;
            $out .= gc_text_to_html($t, $linkify);
            $len += mb_strlen($t);
            continue;
        }
        if ($c->nodeType !== XML_ELEMENT_NODE) continue;

        $tag = strtolower($c->nodeName);
        if ($tag === 'script' || $tag === 'style') continue;

        if ($tag === 'br') {
            $out .= "\n";
            $len++;
            continue;
        }
        if ($tag === 'img') {
            /** @var DOMElement $c */
            $src = trim($c->getAttribute('src'));
            $key = isset($emoji[$src]) ? $src : (isset($emoji[gc_strip_host($src)]) ? gc_strip_host($src) : null);
            if ($key !== null) {
                $out .= '<img src="' . gc_e($src) . '" alt="' . gc_e($emoji[$key]) . '" class="inline-emoji" draggable="false">';
                $len++;
            }
            continue;
        }
        $block = in_array($tag, ['div', 'p', 'li', 'ul', 'ol', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre'], true);
        if ($block && $out !== '' && substr($out, -1) !== "\n") $out .= "\n";
        gc_walk_nodes($c, $emoji, $out, $len, $linkify);
        if ($block && $out !== '' && substr($out, -1) !== "\n") $out .= "\n";
    }
}

/**
 * @return array{html:string,len:int}  html === '' — значит пусто.
 */
function gc_sanitize(string $input, bool $linkify = false): array
{
    $input = trim($input);
    if ($input === '') return ['html' => '', 'len' => 0];

    $len = 0;
    $out = '';

    if (strpos($input, '<') === false && strpos($input, '&') === false) {
        $t   = str_replace(["\r\n", "\r"], "\n", $input);
        $out = gc_text_to_html($t, $linkify);
        $len = mb_strlen($t);
    } else {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div id="gc-root">' . $input . '</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('gc-root');
        if (!$root) {
            $xp   = new DOMXPath($dom);
            $list = $xp->query('//*[@id="gc-root"]');
            $root = $list && $list->length ? $list->item(0) : null;
        }
        if ($root) {
            gc_walk_nodes($root, gc_emoji_map(), $out, $len, $linkify);
        }
    }

    $out = preg_replace("/[ \t]+\n/u", "\n", $out);
    $out = preg_replace("/\n{3,}/", "\n\n", $out);
    $out = trim($out, " \t\r\n");
    if ($out === '') return ['html' => '', 'len' => 0];
    return ['html' => $out, 'len' => $len];
}

/** Для вывода: повторно чистим (защита от «старого» грязного HTML) и делаем ссылки. */
function gc_render_text(?string $stored): string
{
    $r = gc_sanitize((string)$stored, true);
    return $r['html'];
}

/* ═════════════════════════════════════════════════════════════
   ЗАГРУЗКА ИЗОБРАЖЕНИЙ
   ═════════════════════════════════════════════════════════════ */

/**
 * @return array{ok:bool,url:?string,error?:string}
 * Если файл не передан — ok=true, url=null.
 */
function gc_save_image(?array $f, string $subdir): array
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'url' => null];
    }
    $err = (int)$f['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'url' => null, 'error' => 'Файл слишком большой (максимум 5 МБ)'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => null, 'error' => 'Не удалось загрузить файл'];
    }
    if ((int)$f['size'] > GC_IMAGE_MAX_BYTES) {
        return ['ok' => false, 'url' => null, 'error' => 'Файл слишком большой (максимум 5 МБ)'];
    }
    $tmp = (string)$f['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'url' => null, 'error' => 'Некорректная загрузка'];
    }

    $info = @getimagesize($tmp);
    if (!$info) {
        return ['ok' => false, 'url' => null, 'error' => 'Файл не является изображением'];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime    = (string)$info['mime'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'url' => null, 'error' => 'Допустимы JPG, PNG, WebP и GIF'];
    }
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $real = finfo_file($fi, $tmp);
            finfo_close($fi);
            if ($real !== $mime) {
                return ['ok' => false, 'url' => null, 'error' => 'Файл не является изображением'];
            }
        }
    }
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w < 1 || $h < 1 || $w * $h > 40000000) {
        return ['ok' => false, 'url' => null, 'error' => 'Слишком большое разрешение изображения'];
    }

    $rel = '/uploads/gc/' . $subdir . '/' . date('Y/m');
    $dir = ROOT_DIR . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'url' => null, 'error' => 'Не удалось сохранить файл'];
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;

    $saved = false;
    // Перекодируем через GD: убираем EXIF/метаданные, поворачиваем по ориентации, ужимаем до 1920px
    if ($mime !== 'image/gif' && function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($tmp));
        if ($src) {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $ex  = @exif_read_data($tmp);
                $ori = (int)($ex['Orientation'] ?? 1);
                $ang = [3 => 180, 6 => -90, 8 => 90][$ori] ?? 0;
                if ($ang) {
                    $rot = imagerotate($src, $ang, 0);
                    if ($rot) { imagedestroy($src); $src = $rot; }
                }
            }
            $sw = imagesx($src);
            $sh = imagesy($src);
            $max = 1920;
            if ($sw > $max || $sh > $max) {
                $k   = $max / max($sw, $sh);
                $nw  = max(1, (int)round($sw * $k));
                $nh  = max(1, (int)round($sh * $k));
                $dst = imagecreatetruecolor($nw, $nh);
                if ($mime !== 'image/jpeg') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
                imagedestroy($src);
                $src = $dst;
            } elseif ($mime !== 'image/jpeg') {
                imagesavealpha($src, true);
            }
            if ($mime === 'image/jpeg')      $saved = imagejpeg($src, $dest, 86);
            elseif ($mime === 'image/png')   $saved = imagepng($src, $dest, 6);
            elseif (function_exists('imagewebp')) $saved = imagewebp($src, $dest, 85);
            imagedestroy($src);
        }
    }
    if (!$saved) {
        $saved = move_uploaded_file($tmp, $dest);
    }
    if (!$saved) {
        return ['ok' => false, 'url' => null, 'error' => 'Не удалось сохранить файл'];
    }
    @chmod($dest, 0644);
    return ['ok' => true, 'url' => $rel . '/' . $name];
}

function gc_delete_image(?string $url): void
{
    if (!$url) return;
    if (!preg_match('#^/uploads/gc/(comments|posts)/[A-Za-z0-9/_.\-]+$#', $url) || strpos($url, '..') !== false) return;
    $p = ROOT_DIR . $url;
    if (is_file($p)) @unlink($p);
}

/* ═════════════════════════════════════════════════════════════
   КОНТЕКСТЫ КОММЕНТАРИЕВ: feed (activity_feed) / community (community_posts)
   ═════════════════════════════════════════════════════════════ */

function gc_ctx(string $ctx): ?array
{
    if ($ctx === 'feed') {
        return [
            'table' => 'feed_comments',
            'fk'    => 'activity_id',
            'like_count' => "(SELECT COUNT(*) FROM feed_comment_likes l WHERE l.comment_id = c.id)",
            'like_mine'  => "(SELECT COUNT(*) FROM feed_comment_likes l WHERE l.comment_id = c.id AND l.user_email = ?)",
        ];
    }
    if ($ctx === 'community') {
        return [
            'table' => 'community_post_comments',
            'fk'    => 'post_id',
            'like_count' => "(SELECT COUNT(*) FROM community_votes l WHERE l.target_type = 'comment' AND l.target_id = c.id AND l.vote = 1)",
            'like_mine'  => "(SELECT COUNT(*) FROM community_votes l WHERE l.target_type = 'comment' AND l.target_id = c.id AND l.vote = 1 AND l.user_email = ?)",
        ];
    }
    return null;
}

/** Комментарии: последние $limit (id < $before), по возрастанию. @return array{rows:array,remaining:int} */
function gc_fetch_comments(string $ctx, int $parentId, int $limit, int $before = 0): array
{
    $cfg = gc_ctx($ctx);
    if (!$cfg) return ['rows' => [], 'remaining' => 0];
    $v      = gc_viewer();
    $limit  = max(1, min(50, $limit));
    $params = [$v['email'], $parentId];
    $where  = "c.{$cfg['fk']} = ?";
    if ($before > 0) {
        $where   .= ' AND c.id < ?';
        $params[] = $before;
    }
    $rows = db_all(
        "SELECT c.id, c.user_email, c.content, c.image_url, c.is_edited, c.created_at,
                u.display_name, u.avatar_url, u.avatar_emoji, u.gender,
                {$cfg['like_count']} AS like_count, {$cfg['like_mine']} AS my_like
         FROM {$cfg['table']} c
         LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = c.user_email COLLATE utf8mb4_unicode_ci
         WHERE {$where}
         ORDER BY c.id DESC
         LIMIT {$limit}",
        // порядок параметров: like_mine (?), затем where
        $params
    );
    $rows = array_reverse($rows);
    $remaining = 0;
    if ($rows) {
        $first = (int)$rows[0]['id'];
        $r = db_one("SELECT COUNT(*) AS n FROM {$cfg['table']} WHERE {$cfg['fk']} = ? AND id < ?", [$parentId, $first]);
        $remaining = (int)($r['n'] ?? 0);
    }
    return ['rows' => $rows, 'remaining' => $remaining];
}

function gc_fetch_comment(string $ctx, int $id): ?array
{
    $cfg = gc_ctx($ctx);
    if (!$cfg) return null;
    $v = gc_viewer();
    $row = db_one(
        "SELECT c.id, c.{$cfg['fk']} AS parent_id, c.user_email, c.content, c.image_url, c.is_edited, c.created_at,
                u.display_name, u.avatar_url, u.avatar_emoji, u.gender,
                {$cfg['like_count']} AS like_count, {$cfg['like_mine']} AS my_like
         FROM {$cfg['table']} c
         LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = c.user_email COLLATE utf8mb4_unicode_ci
         WHERE c.id = ?",
        [$v['email'], $id]
    );
    return $row ?: null;
}

/** Предзагрузка счётчиков комментариев для пачки постов (один запрос на страницу). */
function gc_prefetch_counts(string $ctx, array $rows, string $idKey = 'id'): void
{
    $cfg = gc_ctx($ctx);
    if (!$cfg) return;
    $ids = [];
    foreach ($rows as $r) {
        if (!empty($r[$idKey])) $ids[(int)$r[$idKey]] = true;
    }
    $ids = array_keys($ids);
    if (!$ids) return;
    foreach ($ids as $id) {
        $GLOBALS['__gc_counts'][$ctx][$id] = 0;
    }
    try {
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $list = db_all("SELECT {$cfg['fk']} AS pid, COUNT(*) AS n FROM {$cfg['table']} WHERE {$cfg['fk']} IN ({$ph}) GROUP BY {$cfg['fk']}", $ids);
        foreach ($list as $r) {
            $GLOBALS['__gc_counts'][$ctx][(int)$r['pid']] = (int)$r['n'];
        }
    } catch (Throwable $e) {
        error_log('gc_prefetch_counts: ' . $e->getMessage());
        foreach ($ids as $id) unset($GLOBALS['__gc_counts'][$ctx][$id]);
    }
}

function gc_count(string $ctx, int $id): int
{
    if (isset($GLOBALS['__gc_counts'][$ctx][$id])) {
        return (int)$GLOBALS['__gc_counts'][$ctx][$id];
    }
    $cfg = gc_ctx($ctx);
    if (!$cfg) return 0;
    try {
        $r = db_one("SELECT COUNT(*) AS n FROM {$cfg['table']} WHERE {$cfg['fk']} = ?", [$id]);
        return $GLOBALS['__gc_counts'][$ctx][$id] = (int)($r['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/* ═════════════════════════════════════════════════════════════
   ПРАВА
   ═════════════════════════════════════════════════════════════ */

function gc_is_member(int $communityId, string $email, ?string $ownerEmail = null): bool
{
    if ($email === '') return false;
    if ($ownerEmail !== null && $ownerEmail === $email) return true;
    $r = db_one(
        'SELECT id FROM community_members WHERE community_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?',
        [$communityId, $email]
    );
    return (bool)$r;
}

function gc_are_friends(string $a, string $b): bool
{
    if ($a === '' || $b === '') return false;
    $r = db_one(
        "SELECT id FROM friendships WHERE status = 'accepted'
           AND ((user_email = ? AND friend_email = ?) OR (user_email = ? AND friend_email = ?)) LIMIT 1",
        [$a, $b, $b, $a]
    );
    return (bool)$r;
}

/**
 * Определяет родителя комментария и права текущего пользователя.
 * @return array{exists:bool,can_view:bool,can_comment:bool,moderator:bool,owner_email:string,community_id:int}
 */
function gc_parent_access(string $ctx, int $id): array
{
    $v   = gc_viewer();
    $res = ['exists' => false, 'can_view' => false, 'can_comment' => false, 'moderator' => false, 'owner_email' => '', 'community_id' => 0];

    if ($ctx === 'feed') {
        $a = db_one('SELECT id, user_email, visibility FROM activity_feed WHERE id = ?', [$id]);
        if (!$a) return $res;
        $res['exists']      = true;
        $res['owner_email'] = (string)$a['user_email'];
        $isOwner            = $v['logged'] && $v['email'] === $res['owner_email'];
        $vis                = (string)($a['visibility'] ?? 'public');
        if ($vis === 'public' || $isOwner) {
            $res['can_view'] = true;
        } elseif ($vis === 'friends' && $v['logged']) {
            $res['can_view'] = gc_are_friends($v['email'], $res['owner_email']);
        }
        $res['can_comment'] = $v['logged'] && $res['can_view'];
        $res['moderator']   = $v['logged'] && ($isOwner || $v['is_admin']);
        return $res;
    }

    if ($ctx === 'community') {
        $p = db_one(
            'SELECT p.id, p.community_id, c.author_email
             FROM community_posts p JOIN communities c ON c.id = p.community_id WHERE p.id = ?',
            [$id]
        );
        if (!$p) return $res;
        $res['exists']       = true;
        $res['can_view']     = true;
        $res['owner_email']  = (string)($p['author_email'] ?? '');
        $res['community_id'] = (int)$p['community_id'];
        $res['can_comment']  = $v['logged'] && gc_is_member((int)$p['community_id'], $v['email'], $res['owner_email']);
        $res['moderator']    = $v['logged'] && ($v['email'] === $res['owner_email'] || $v['is_admin']);
        return $res;
    }
    return $res;
}

/* ═════════════════════════════════════════════════════════════
   РЕНДЕР: КОММЕНТАРИИ
   ═════════════════════════════════════════════════════════════ */

/**
 * @param array $o ['moderator'=>bool]  — владелец поста/сообщества (может удалять чужие)
 */
function gc_render_comment(array $c, array $o = []): string
{
    $v     = gc_viewer();
    $email = (string)$c['user_email'];
    $mine  = $v['logged'] && $v['email'] === $email;
    $mod   = !empty($o['moderator']);
    $name  = gc_user_name($c);
    $liked = !empty($c['my_like']);
    $likes = (int)($c['like_count'] ?? 0);
    $text  = gc_render_text($c['content'] ?? '');
    $badge = (!empty($o['owner_email']) && $o['owner_email'] === $email) ? ($o['owner_badge'] ?? '') : '';

    ob_start(); ?>
<div class="gc-c" id="gc-comment-<?= (int)$c['id'] ?>" data-gc-cid="<?= (int)$c['id'] ?>" data-gc-author="<?= gc_e($name) ?>">
  <a class="gc-c-ava" href="<?= gc_e(gc_user_url($email)) ?>"><?= gc_ava($c, 'sm') ?></a>
  <div class="gc-c-main">
    <div class="gc-c-bubble">
      <div class="gc-c-head">
        <a class="gc-c-name" href="<?= gc_e(gc_user_url($email)) ?>"><?= gc_e($name) ?></a>
        <?php if ($badge !== ''): ?><span class="gc-badge"><?= gc_e($badge) ?></span><?php endif; ?>
      </div>
      <div class="gc-c-text" data-gc-text><?= $text ?></div>
      <?php if (!empty($c['image_url'])): ?>
        <a class="gc-c-img" href="<?= gc_e($c['image_url']) ?>" data-gc-zoom><img src="<?= gc_e($c['image_url']) ?>" alt="" loading="lazy"></a>
      <?php endif; ?>
    </div>
    <div class="gc-c-meta">
      <span class="gc-c-time"><?= gc_e(gc_time($c['created_at'] ?? null)) ?><?= !empty($c['is_edited']) ? ' · изменено' : '' ?></span>
      <?php if ($v['logged']): ?>
        <button type="button" class="gc-link" data-gc-reply>Ответить</button>
      <?php endif; ?>
      <?php if ($mine): ?>
        <button type="button" class="gc-link" data-gc-edit>Изменить</button>
      <?php endif; ?>
      <?php if ($mine || $mod): ?>
        <button type="button" class="gc-link gc-link-danger" data-gc-del>Удалить</button>
      <?php endif; ?>
      <button type="button" class="gc-like <?= $liked ? 'is-on' : '' ?>" data-gc-like aria-pressed="<?= $liked ? 'true' : 'false' ?>" <?= $v['logged'] ? '' : 'disabled' ?>>
        <?= icon('like') ?><span data-gc-like-n><?= $likes > 0 ? $likes : '' ?></span>
      </button>
    </div>
  </div>
</div>
<?php
    return trim(ob_get_clean());
}

function gc_render_comment_list(array $rows, array $o = []): string
{
    $html = '';
    foreach ($rows as $r) {
        $html .= gc_render_comment($r, $o) . "\n";
    }
    return $html;
}

/** Редактор (используется и для комментариев, и для постов). */
function gc_render_editor(string $placeholder, array $o = []): string
{
    $v = gc_viewer();
    ob_start(); ?>
<div class="gc-compose" data-gc-compose>
  <?php if (empty($o['no_avatar'])): ?><span class="gc-compose-ava"><?= gc_ava($v['user'], 'sm') ?></span><?php endif; ?>
  <div class="gc-compose-box">
    <div class="gc-input" contenteditable="true" role="textbox" aria-multiline="true"
         aria-label="<?= gc_e($placeholder) ?>" data-placeholder="<?= gc_e($placeholder) ?>" data-gc-input></div>
    <div class="gc-preview" data-gc-preview hidden>
      <img alt="" data-gc-preview-img>
      <button type="button" class="gc-preview-x" data-gc-rm-file aria-label="Убрать фото">×</button>
    </div>
    <div class="gc-compose-tools">
      <button type="button" class="gc-tool" data-gc-emoji-btn aria-label="Смайлы" aria-expanded="false">
        <span class="gc-ic-smile"><?= icon('happy') ?></span>
        <span class="gc-ic-kb" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M6 9.5h.01M9.5 9.5h.01M13 9.5h.01M16.5 9.5h.01M6 13h.01M18 13h.01M9 15.5h6"/></svg></span>
      </button>
      <label class="gc-tool" aria-label="Прикрепить фото" title="Прикрепить фото">
        <?= icon('image') ?>
        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden data-gc-file>
      </label>
      <?php if (!empty($o['cancel'])): ?>
        <button type="button" class="gc-btn gc-btn-ghost" data-gc-cancel>Отмена</button>
      <?php endif; ?>
      <button type="button" class="gc-send" data-gc-send disabled aria-label="<?= gc_e($o['send_label'] ?? 'Отправить') ?>">
        <?php if (!empty($o['send_text'])): ?><span><?= gc_e($o['send_text']) ?></span><?php else: ?><?= icon('send') ?><?php endif; ?>
      </button>
    </div>
  </div>
</div>
<?php
    return trim(ob_get_clean());
}

/**
 * Свёрнутая панель под постом: счётчик + «Написать комментарий…»; по клику раскрывается.
 * @param array $o ['can_comment'=>bool,'moderator_email'=>string,'owner_badge'=>string,'hint'=>string]
 */
function gc_render_comments_shell(string $ctx, int $parentId, int $count, array $o = []): string
{
    $v   = gc_viewer();
    $can = !empty($o['can_comment']);
    ob_start(); ?>
<div class="gc-cwrap" data-gc-shell data-gc-ctx="<?= gc_e($ctx) ?>" data-gc-id="<?= $parentId ?>"
     data-gc-count="<?= $count ?>" data-gc-owner="<?= gc_e($o['owner_email'] ?? '') ?>"
     data-gc-badge="<?= gc_e($o['owner_badge'] ?? '') ?>">
  <div class="gc-cbar">
    <button type="button" class="gc-cbar-count" data-gc-toggle aria-expanded="false">
      <?= icon('chats') ?><span data-gc-count-label><?= gc_e(gc_comments_label($count)) ?></span>
    </button>
    <?php if ($v['logged']): ?>
      <button type="button" class="gc-cbar-fake" data-gc-toggle data-gc-focus>
        <span class="gc-cbar-ava"><?= gc_ava($v['user'], 'xs') ?></span>
        <span class="gc-cbar-ph">Написать комментарий…</span>
      </button>
    <?php endif; ?>
  </div>

  <div class="gc-cpanel" data-gc-panel hidden>
    <div class="gc-cmore-wrap" data-gc-more-wrap hidden>
      <button type="button" class="gc-link gc-cmore" data-gc-more>Показать предыдущие комментарии <span data-gc-more-n></span></button>
    </div>
    <div class="gc-clist" data-gc-list aria-live="polite"></div>
    <div class="gc-cempty" data-gc-empty hidden>Пока нет комментариев. Будьте первым!</div>
    <div class="gc-cloading" data-gc-loading hidden>Загрузка…</div>

    <?php if ($can): ?>
      <?= gc_render_editor('Написать комментарий…') ?>
    <?php elseif ($v['logged'] && !empty($o['member_gate'])): ?>
      <?php /* страница сообщества: редактор есть всегда, CSS прячет его пока нет членства (вступление без перезагрузки) */ ?>
      <?= gc_render_editor('Написать комментарий…') ?>
      <div class="gc-hint gc-hint-join"><?= gc_e($o['hint'] ?? 'Вступите в сообщество, чтобы комментировать') ?></div>
    <?php elseif (!$v['logged']): ?>
      <div class="gc-hint">
        <a href="<?= gc_e(url('login')) ?>">Войдите</a>, чтобы комментировать
      </div>
    <?php else: ?>
      <div class="gc-hint"><?= gc_e($o['hint'] ?? 'Комментарии недоступны') ?></div>
    <?php endif; ?>
  </div>
</div>
<?php
    return trim(ob_get_clean());
}

/* ═════════════════════════════════════════════════════════════
   РЕНДЕР: ПОСТЫ СООБЩЕСТВА
   ═════════════════════════════════════════════════════════════ */

function gc_fetch_community_posts(int $communityId, int $limit, int $offset, bool $ownerOnly = false, string $ownerEmail = ''): array
{
    $v      = gc_viewer();
    $limit  = max(1, min(30, $limit));
    $offset = max(0, $offset);
    $params = [$v['email'], $communityId];
    $where  = 'p.community_id = ?';
    if ($ownerOnly) {
        $where   .= ' AND p.user_email COLLATE utf8mb4_unicode_ci = ?';
        $params[] = $ownerEmail;
    }
    $rows = db_all(
        "SELECT p.id, p.community_id, p.user_email, p.content, p.photo_url, p.image_url, p.sticker_url,
                p.created_at, p.is_edited, p.is_pinned,
                u.display_name, u.avatar_url, u.avatar_emoji, u.gender,
                (SELECT COUNT(*) FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.vote = 1)  AS like_count,
                (SELECT COUNT(*) FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.vote = -1) AS dislike_count,
                (SELECT x.vote FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.user_email = ? LIMIT 1) AS my_vote
         FROM community_posts p
         LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = p.user_email COLLATE utf8mb4_unicode_ci
         WHERE {$where}
         ORDER BY p.is_pinned DESC, p.id DESC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    gc_prefetch_counts('community', $rows);
    return $rows;
}

function gc_fetch_community_post(int $postId): ?array
{
    $v = gc_viewer();
    $row = db_one(
        "SELECT p.id, p.community_id, p.user_email, p.content, p.photo_url, p.image_url, p.sticker_url,
                p.created_at, p.is_edited, p.is_pinned,
                u.display_name, u.avatar_url, u.avatar_emoji, u.gender,
                (SELECT COUNT(*) FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.vote = 1)  AS like_count,
                (SELECT COUNT(*) FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.vote = -1) AS dislike_count,
                (SELECT x.vote FROM community_votes x WHERE x.target_type = 'post' AND x.target_id = p.id AND x.user_email = ? LIMIT 1) AS my_vote
         FROM community_posts p
         LEFT JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = p.user_email COLLATE utf8mb4_unicode_ci
         WHERE p.id = ?",
        [$v['email'], $postId]
    );
    if ($row) gc_prefetch_counts('community', [$row]);
    return $row ?: null;
}

/**
 * @param array $cx ['owner_email'=>string,'is_member'=>bool,'moderator'=>bool,'slug'=>string]
 */
function gc_render_community_post(array $p, array $cx): string
{
    $v       = gc_viewer();
    $id      = (int)$p['id'];
    $email   = (string)$p['user_email'];
    $mine    = $v['logged'] && $v['email'] === $email;
    $isOwner = !empty($cx['owner_email']) && $cx['owner_email'] === $email;
    $mod     = !empty($cx['moderator']);
    $photo   = !empty($p['photo_url']) ? $p['photo_url'] : ($p['image_url'] ?? '');
    $text    = gc_render_text($p['content'] ?? '');
    $plain   = trim(strip_tags($text));
    $long    = mb_strlen($plain) > 420 || substr_count($text, "\n") > 7;
    $count   = gc_count('community', $id);
    $my      = (int)($p['my_vote'] ?? 0);
    $likes   = (int)($p['like_count'] ?? 0);
    $dislikes = (int)($p['dislike_count'] ?? 0);
    $name    = gc_user_name($p);

    ob_start(); ?>
<article class="gc-post <?= !empty($p['is_pinned']) ? 'is-pinned' : '' ?>" id="gc-post-<?= $id ?>"
         data-gc-post data-post-id="<?= $id ?>" data-gc-pinned="<?= !empty($p['is_pinned']) ? 1 : 0 ?>">
  <?php if (!empty($p['is_pinned'])): ?>
    <div class="gc-pinned"><?= icon('pin') ?> <span>Закреплённая запись</span></div>
  <?php endif; ?>

  <header class="gc-post-head">
    <a href="<?= gc_e(gc_user_url($email)) ?>" class="gc-post-ava"><?= gc_ava($p, 'md') ?></a>
    <div class="gc-post-who">
      <a href="<?= gc_e(gc_user_url($email)) ?>" class="gc-post-name"><?= gc_e($name) ?></a>
      <?php if ($isOwner): ?><span class="gc-badge">Владелец</span><?php endif; ?>
      <a class="gc-post-time" href="#gc-post-<?= $id ?>" data-gc-permalink><?= gc_e(gc_time($p['created_at'] ?? null)) ?><?= !empty($p['is_edited']) ? ' · изменено' : '' ?></a>
    </div>
    <?php if ($mine || $mod): ?>
    <div class="gc-menu" data-gc-menu>
      <button type="button" class="gc-menu-btn" data-gc-menu-btn aria-label="Действия с записью" aria-haspopup="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
      <div class="gc-menu-list" data-gc-menu-list hidden>
        <?php if ($mine): ?><button type="button" data-gc-post-edit><?= icon('pen') ?> Изменить</button><?php endif; ?>
        <?php if (!empty($cx['is_owner'])): ?>
          <button type="button" data-gc-post-pin><?= icon('pin') ?> <span><?= !empty($p['is_pinned']) ? 'Открепить' : 'Закрепить' ?></span></button>
        <?php endif; ?>
        <button type="button" class="is-danger" data-gc-post-del><?= icon('trash') ?> Удалить</button>
      </div>
    </div>
    <?php endif; ?>
  </header>

  <div class="gc-post-body" data-gc-post-body>
    <?php if ($text !== ''): ?>
      <div class="gc-post-text <?= $long ? 'is-clamped' : '' ?>" data-gc-post-text><?= $text ?></div>
      <?php if ($long): ?><button type="button" class="gc-link gc-readmore" data-gc-readmore>Показать полностью…</button><?php endif; ?>
    <?php endif; ?>
    <?php if ($photo): ?>
      <a class="gc-post-img" href="<?= gc_e($photo) ?>" data-gc-zoom><img src="<?= gc_e($photo) ?>" alt="" loading="lazy"></a>
    <?php endif; ?>
    <?php if (!empty($p['sticker_url'])): ?>
      <img class="gc-post-sticker" src="<?= gc_e($p['sticker_url']) ?>" alt="" loading="lazy">
    <?php endif; ?>
  </div>

  <div class="gc-post-actions">
    <button type="button" class="gc-act <?= $my === 1 ? 'is-like' : '' ?>" data-gc-vote="1" <?= $v['logged'] ? '' : 'disabled' ?> aria-pressed="<?= $my === 1 ? 'true' : 'false' ?>">
      <?= icon('like') ?><span data-gc-likes><?= $likes > 0 ? $likes : '' ?></span>
    </button>
    <button type="button" class="gc-act <?= $my === -1 ? 'is-dislike' : '' ?>" data-gc-vote="-1" <?= $v['logged'] ? '' : 'disabled' ?> aria-pressed="<?= $my === -1 ? 'true' : 'false' ?>">
      <?= icon('dislike') ?><span data-gc-dislikes><?= $dislikes > 0 ? $dislikes : '' ?></span>
    </button>
    <button type="button" class="gc-act gc-act-right" data-gc-share title="Скопировать ссылку на запись">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>
      <span>Поделиться</span>
    </button>
  </div>

  <?= gc_render_comments_shell('community', $id, $count, [
        'can_comment'  => !empty($cx['is_member']),
        'owner_email'  => $cx['owner_email'] ?? '',
        'owner_badge'  => 'Владелец',
        'hint'         => 'Вступите в сообщество, чтобы комментировать',
        'member_gate'  => true,
  ]) ?>
</article>
<?php
    return trim(ob_get_clean());
}

function gc_render_community_posts(array $rows, array $cx): string
{
    $html = '';
    foreach ($rows as $r) {
        $html .= gc_render_community_post($r, $cx) . "\n";
    }
    return $html;
}


/* ═════════════════════════════════════════════════════════════
   ПОДКЛЮЧЕНИЕ ФРОНТЕНДА (CSS + конфиг + шаблон редактора + JS). Вызывать 1 раз внизу страницы.
   ═════════════════════════════════════════════════════════════ */
function gc_social_assets(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;

    $v   = gc_viewer();
    $css = '/assets/css/gc-social.css';
    $js  = '/assets/js/social/gc-social.js';
    $cv  = @filemtime(ROOT_DIR . $css) ?: 1;
    $jv  = @filemtime(ROOT_DIR . $js) ?: 1;

    ob_start(); ?>
<link rel="stylesheet" href="<?= gc_e(url(ltrim($css, '/'))) ?>?v=<?= (int)$cv ?>">
<script>
window.GC_SOCIAL = <?= json_encode([
    'comments' => url('api/gc_comments.php'),
    'posts'    => url('api/gc_community_posts.php'),
    'logged'   => $v['logged'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<?php if ($v['logged']): ?>
<template id="gc-editor-template"><?= gc_render_editor('Изменить…', ['cancel' => true, 'no_avatar' => true, 'send_text' => 'Сохранить', 'send_label' => 'Сохранить']) ?></template>
<?php endif; ?>
<script src="<?= gc_e(url(ltrim($js, '/'))) ?>?v=<?= (int)$jv ?>" defer></script>
<?php
    return ob_get_clean();
}
