<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    try {
        db_exec("CREATE TABLE IF NOT EXISTS `challenge_comments` (
            `id` int NOT NULL AUTO_INCREMENT,
            `submission_id` int NOT NULL,
            `user_email` varchar(255) NOT NULL,
            `comment` text NOT NULL,
            `sticker_url` varchar(500) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_submission` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Добавляем недостающие колонки, если таблица уже существовала
        $cols = db_all("SHOW COLUMNS FROM challenge_comments");
        $colNames = array_column($cols, 'Field');
        if (!in_array('sticker_url', $colNames)) {
            db_exec("ALTER TABLE challenge_comments ADD COLUMN sticker_url varchar(500) DEFAULT NULL AFTER comment");
        }
    } catch (Throwable $e) {
        error_log('Challenge comments table init error: ' . $e->getMessage());
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '') === 'PUT') {
        $method = 'PUT';
    }

    // GET: комментарии к работе
    if ($method === 'GET') {
        $submissionId = (int)($_GET['submission_id'] ?? 0);
        if ($submissionId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указана работа'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $comments = db_all(
            'SELECT cc.*, u.display_name, u.gender, u.avatar_url
             FROM challenge_comments cc
             LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(cc.user_email USING utf8mb4)
             WHERE cc.submission_id = ?
             ORDER BY cc.created_at ASC',
            [$submissionId]
        );
        echo json_encode(['ok' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST: добавить комментарий
    if ($method === 'POST' && ($_POST['action'] ?? '') !== 'edit_comment') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($submissionId <= 0 || $comment === '') {
            echo json_encode(['ok' => false, 'error' => 'Пустой комментарий'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $user = current_user();
        $id = db_insert(
            'INSERT INTO challenge_comments (submission_id, user_email, comment, sticker_url)
             VALUES (?, CONVERT(? USING utf8mb4), CONVERT(? USING utf8mb4), NULL)',
            [$submissionId, $user['email'], $comment]
        );
        echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST: редактировать комментарий
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
        $comment = db_one('SELECT * FROM challenge_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($comment['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор комментария'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        db_exec(
            'UPDATE challenge_comments SET comment = CONVERT(? USING utf8mb4) WHERE id = ?',
            [$content, $commentId]
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // DELETE: удалить комментарий
    if ($method === 'DELETE') {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $commentId = (int)($_GET['id'] ?? 0);
        if ($commentId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Не указан комментарий'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $comment = db_one('SELECT * FROM challenge_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($comment['user_email'] !== current_user()['email'] && !is_admin()) {
            echo json_encode(['ok' => false, 'error' => 'Вы не автор комментария'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        db_exec('DELETE FROM challenge_comments WHERE id = ?', [$commentId]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $ex) {
    error_log('Challenge comments error: ' . $ex->getMessage());
    echo json_encode(['ok' => false, 'error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'], JSON_UNESCAPED_UNICODE);
    exit;
}
