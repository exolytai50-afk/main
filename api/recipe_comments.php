<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Проверяем авторизацию
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];

// Создаём таблицу комментариев
try {
    db_exec("CREATE TABLE IF NOT EXISTS `recipe_comments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `review_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `text` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_review` (`review_id`),
        INDEX `idx_parent` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating recipe_comments table: ' . $e->getMessage());
}

// POST — добавление комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_input();
    $reviewId = (int)($input['review_id'] ?? 0);
    $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' ? (int)$input['parent_id'] : null;
    $text = trim($input['text'] ?? '');
    
    if ($reviewId <= 0 || empty($text)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $commentId = db_insert(
            'INSERT INTO recipe_comments (review_id, user_email, parent_id, text) VALUES (?, ?, ?, ?)',
            [$reviewId, $userEmail, $parentId, $text]
        );
        
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'message' => 'Комментарий добавлен'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Add comment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при добавлении комментария'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// PUT — обновление комментария
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_input();
    $commentId = (int)($input['comment_id'] ?? 0);
    $text = trim($input['text'] ?? '');
    
    if ($commentId <= 0 || empty($text)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $comment = db_one('SELECT * FROM recipe_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($comment['user_email'] !== $userEmail && !is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Нет прав на редактирование'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        db_exec('UPDATE recipe_comments SET text = ? WHERE id = ?', [$text, $commentId]);
        
        echo json_encode(['ok' => true, 'message' => 'Комментарий обновлён'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при обновлении комментария'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// DELETE — удаление комментария
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $commentId = (int)($_GET['id'] ?? 0);
    
    if ($commentId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан id комментария'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $comment = db_one('SELECT * FROM recipe_comments WHERE id = ?', [$commentId]);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Комментарий не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($comment['user_email'] !== $userEmail && !is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Нет прав на удаление'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        db_exec('DELETE FROM recipe_comments WHERE id = ? OR parent_id = ?', [$commentId, $commentId]);
        
        echo json_encode(['ok' => true, 'message' => 'Комментарий удалён'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при удалении комментария'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;