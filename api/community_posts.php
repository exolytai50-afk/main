<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Создаём таблицу комментариев
    try {
        db_exec("CREATE TABLE IF NOT EXISTS `community_post_comments` (
            `id` int NOT NULL AUTO_INCREMENT,
            `post_id` int NOT NULL,
            `user_email` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_post_id` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    // Добавляем колонку sticker_url если её нет
    try {
        db_exec("ALTER TABLE community_posts ADD COLUMN sticker_url VARCHAR(512) DEFAULT NULL");
    } catch (Throwable $e) {}

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '') === 'PUT') {
        $method = 'PUT';
    }

    // ===== GET: комментарии к посту =====
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'comments') {
        $postId = (int)($_GET['post_id'] ?? 0);
        if ($postId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указан пост'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $comments = db_all(
            'SELECT c.*, u.display_name, u.avatar_emoji, u.avatar_url
             FROM community_post_comments c
             LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(c.user_email USING utf8mb4)
             WHERE c.post_id = ?
             ORDER BY c.created_at ASC',
            [$postId]
        );
        echo json_encode(['ok' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== GET: список постов =====
    if ($method === 'GET') {
        $communityId = (int)($_GET['community_id'] ?? 0);
        if ($communityId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указано сообщество'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $posts = db_all(
            'SELECT cp.*, u.display_name, u.avatar_emoji, u.avatar_url,
                   (SELECT COUNT(*) FROM community_post_comments WHERE post_id = cp.id) as comment_count
             FROM community_posts cp
             LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(cp.user_email USING utf8mb4)
             WHERE cp.community_id = ?
             ORDER BY cp.created_at DESC
             LIMIT 50',
            [$communityId]
        );
        echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== POST: создать комментарий =====
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'comment') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        if ($postId <= 0 || $content === '') {
            echo json_encode(['ok' => false, 'error' => 'Пустой комментарий'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $user = current_user();
        $id = db_insert(
            'INSERT INTO community_post_comments (post_id, user_email, content)
             VALUES (?, CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4))',
            [$postId, $user['email'], $content]
        );
        echo json_encode([
            'ok' => true,
            'id' => $id,
            'author_name' => $user['display_name'] ?? 'Пользователь'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== POST: редактировать комментарий =====
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'edit_comment') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        if ($commentId <= 0 || $content === '') {
            echo json_encode(['ok' => false, 'error' => 'Пустой комментарий'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $comment = db_one('SELECT * FROM community_post_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($comment['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор комментария'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        db_exec(
            'UPDATE community_post_comments SET content = CONVERT(? USING utf8mb4) WHERE id = ?',
            [$content, $commentId]
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== POST: создать пост =====
    if ($method === 'POST') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $communityId = (int)($_POST['community_id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        $stickerUrl = trim((string)($_POST['sticker_url'] ?? ''));

        $photoUrl = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            if ($file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = ROOT_DIR . '/uploads/community/' . $communityId;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (!$extension) $extension = 'jpg';
                $fileName = 'post_' . time() . '_' . uniqid() . '.' . $extension;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
                    $photoUrl = '/uploads/community/' . $communityId . '/' . $fileName;
                }
            }
        }

        if ($communityId <= 0 || ($content === '' && !$photoUrl && $stickerUrl === '')) {
            echo json_encode(['ok' => false, 'error' => 'Заполните сообщение или добавьте фото'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $user = current_user();
        $id = db_insert(
            'INSERT INTO community_posts (community_id, user_email, title, content, type, photo_url, sticker_url)
             VALUES (?, CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4), ?, ?, ?)',
            [
                $communityId,
                $user['email'],
                mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200),
                $content,
                $photoUrl ? 'photo' : ($stickerUrl ? 'sticker' : 'text'),
                $photoUrl,
                $stickerUrl !== '' ? $stickerUrl : null
            ]
        );
        echo json_encode([
            'ok' => true,
            'id' => $id,
            'author_name' => $user['display_name'] ?? 'Пользователь'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== PUT: редактировать пост =====
    if ($method === 'PUT') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        $photoRemoved = ($_POST['photo_removed'] ?? '0') === '1';

        if ($postId <= 0 || $content === '') {
            echo json_encode(['ok' => false, 'error' => 'Заполните сообщение'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $post = db_one('SELECT * FROM community_posts WHERE id = ?', [$postId]);
        if (!$post) {
            echo json_encode(['ok' => false, 'error' => 'Пост не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($post['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор поста'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $photoUrl = $post['photo_url'];
        if ($photoRemoved && $photoUrl) {
            $photoPath = ROOT_DIR . $photoUrl;
            if (file_exists($photoPath)) @unlink($photoPath);
            $photoUrl = null;
        }
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            if ($file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = ROOT_DIR . '/uploads/community/' . $post['community_id'];
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (!$extension) $extension = 'jpg';
                $fileName = 'post_' . time() . '_' . uniqid() . '.' . $extension;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
                    if ($photoUrl && file_exists(ROOT_DIR . $photoUrl)) @unlink(ROOT_DIR . $photoUrl);
                    $photoUrl = '/uploads/community/' . $post['community_id'] . '/' . $fileName;
                }
            }
        }
        db_exec(
            'UPDATE community_posts SET content = CONVERT(? USING utf8mb4), photo_url = ? WHERE id = ?',
            [$content, $photoUrl, $postId]
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== DELETE: удалить пост =====
    if ($method === 'DELETE' && ($_GET['action'] ?? '') !== 'comment') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $postId = (int)($_GET['id'] ?? 0);
        if ($postId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указан пост'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $post = db_one('SELECT * FROM community_posts WHERE id = ?', [$postId]);
        if (!$post) {
            echo json_encode(['ok' => false, 'error' => 'Пост не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($post['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор поста'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!empty($post['photo_url'])) {
            $photoPath = ROOT_DIR . $post['photo_url'];
            if (file_exists($photoPath)) @unlink($photoPath);
        }
        db_exec('DELETE FROM community_post_comments WHERE post_id = ?', [$postId]);
        db_exec('DELETE FROM community_posts WHERE id = ?', [$postId]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== DELETE: удалить комментарий =====
    if ($method === 'DELETE' && ($_GET['action'] ?? '') === 'comment') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $commentId = (int)($_GET['id'] ?? 0);
        if ($commentId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указан комментарий'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $comment = db_one('SELECT * FROM community_post_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($comment['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор комментария'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        db_exec('DELETE FROM community_post_comments WHERE id = ?', [$commentId]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $ex) {
    error_log('Community posts error: ' . $ex->getMessage());
    echo json_encode(['ok' => false, 'error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'], JSON_UNESCAPED_UNICODE);
    exit;
}
