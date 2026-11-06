<?php
/**
 * GET  /api/comments.php?recipe_id=5 — список отзывов
 * POST /api/comments.php             — { recipe_id, text, rating }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rid = (int)($_GET['recipe_id'] ?? 0);
        
        // JOIN с users для получения display_name
        $rows = db_all(
            'SELECT c.id, c.text, c.rating, c.created_at,
                    COALESCE(u.display_name, c.user_email, "Гость") as author_name
             FROM comments c
             LEFT JOIN users u ON u.email = c.user_email
             WHERE c.recipe_id = ?
             ORDER BY c.created_at DESC',
            [$rid]
        );
        
        $avg = db_one(
            'SELECT AVG(rating) a, COUNT(*) c 
             FROM comments 
             WHERE recipe_id = ? AND rating > 0',
            [$rid]
        );
        
        json_response([
            'comments' => $rows,
            'avg'      => round((float)($avg['a'] ?? 0), 1),
            'count'    => (int)($avg['c'] ?? 0),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in   = json_input();
        $rid  = (int)($in['recipe_id'] ?? 0);
        $text = trim((string)($in['text'] ?? ''));
        $rate = max(0, min(5, (int)($in['rating'] ?? 0)));
        
        if ($rid <= 0 || ($text === '' && $rate === 0)) {
            json_response(['error' => 'Заполните отзыв или поставьте оценку'], 400);
        }

        // Используем email авторизованного пользователя или 'guest'
        $userEmail = is_logged_in() ? current_user()['email'] : 'guest@anonymous.ru';
        
        $id = db_insert(
            'INSERT INTO comments (recipe_id, user_email, text, rating) 
             VALUES (?, ?, ?, ?)',
            [$rid, $userEmail, $text, $rate]
        );

        // Начисляем очки автору
        if (is_logged_in()) {
            $userId = (int) current_user()['id'];
            db_exec(
                'UPDATE user_stats 
                 SET total_points = total_points + 5 
                 WHERE user_id = ?',
                [$userId]
            );
        }
        
        json_response(['ok' => true, 'id' => $id]);
    }

    json_response(['error' => 'Метод не поддерживается'], 405);
} catch (Throwable $ex) {
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'
    ], 500);
}