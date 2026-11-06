<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Проверяем авторизацию для POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = is_logged_in() ? current_user()['email'] : '';

// Создаём или обновляем таблицу отзывов
try {
    // Проверяем наличие поля photo_url
    $columns = db_all("SHOW COLUMNS FROM recipe_reviews");
    $columnNames = array_column($columns, 'Field');
    
    if (!in_array('photo_url', $columnNames)) {
        db_exec("ALTER TABLE recipe_reviews ADD COLUMN photo_url varchar(500) DEFAULT NULL AFTER text");
    }
    
    db_exec("CREATE TABLE IF NOT EXISTS `recipe_reviews` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `recipe_id` int(11) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `rating` tinyint(1) NOT NULL DEFAULT 5,
        `text` text NOT NULL,
        `photo_url` varchar(500) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_recipe` (`recipe_id`),
        INDEX `idx_user` (`user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Error creating/updating recipe_reviews table: ' . $e->getMessage());
}

// GET — получение отзывов
// GET — получение отзывов с комментариями
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipeId = (int)($_GET['recipe_id'] ?? 0);
    
    if ($recipeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // Получаем отзывы
        $reviews = db_all(
            "SELECT r.id, r.recipe_id, r.user_email, r.rating, r.text, r.photo_url, r.created_at,
                    u.display_name as author_name, u.avatar_url, u.avatar_emoji, u.gender,
                    (r.user_email = ?) as is_owner
             FROM recipe_reviews r
             LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(r.user_email USING utf8mb4)
             WHERE r.recipe_id = ?
             ORDER BY r.created_at DESC",
            [$userEmail, $recipeId]
        );
        
        // Получаем все комментарии для этих отзывов
        $comments = [];
        if (!empty($reviews)) {
            $reviewIds = array_column($reviews, 'id');
            $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
            
            $commentsData = db_all(
    "SELECT c.id, c.review_id, c.parent_id, c.text, c.created_at,
            u.display_name as author_name,
            u.avatar_url,
            u.avatar_emoji, u.gender,
            (c.user_email = ?) as is_owner
     FROM recipe_comments c
     LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) = CONVERT(c.user_email USING utf8mb4)
     WHERE c.review_id IN ({$placeholders})
     ORDER BY c.review_id, COALESCE(c.parent_id, c.id), c.created_at ASC",
    array_merge([$userEmail], $reviewIds)
);

            
            // Группируем комментарии по отзывам
            foreach ($commentsData as $comment) {
                $reviewId = (int)$comment['review_id'];
                if (!isset($comments[$reviewId])) {
                    $comments[$reviewId] = [];
                }
                $comment['depth'] = 0;
                $comments[$reviewId][] = $comment;
            }
        }
        
        // Добавляем комментарии к отзывам
        foreach ($reviews as &$review) {
            $reviewId = (int)$review['id'];
            $review['comments'] = $comments[$reviewId] ?? [];
        }
        unset($review);
        
        // Вычисляем средний рейтинг
        $avgRating = 0;
        $reviewCount = count($reviews);
        if ($reviewCount > 0) {
            $total = 0;
            foreach ($reviews as $review) {
                $total += (int)$review['rating'];
            }
            $avgRating = round($total / $reviewCount, 1);
        }
        
        echo json_encode([
            'ok' => true,
            'comments' => $reviews,
            'avg' => $avgRating,
            'count' => $reviewCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Get reviews error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при получении отзывов'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST — отправка/обновление отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipeId = (int)($_POST['recipe_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $text = trim((string)($_POST['text'] ?? ''));
    
    if ($recipeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (empty($text) && empty($_FILES['photo']['name'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Текст отзыва или фото обязательны'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }
    
    $recipe = db_one('SELECT id FROM recipes WHERE id = ?', [$recipeId]);
    if (!$recipe) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Рецепт не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $photoUrl = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = ROOT_DIR . '/uploads/reviews/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Недопустимый формат изображения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $filename = uniqid('review_') . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
            $photoUrl = '/uploads/reviews/' . $filename;
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Ошибка загрузки файла'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    try {
        $existingReview = db_one(
            'SELECT id, photo_url FROM recipe_reviews WHERE recipe_id = ? AND user_email = ?',
            [$recipeId, $userEmail]
        );
        
        if ($existingReview) {
            db_exec(
                'UPDATE recipe_reviews SET rating = ?, text = ?, photo_url = ? WHERE id = ?',
                [$rating, $text, $photoUrl ?? $existingReview['photo_url'], $existingReview['id']]
            );
            $reviewId = $existingReview['id'];
            
            if ($photoUrl && $existingReview['photo_url'] && $existingReview['photo_url'] !== $photoUrl) {
                $oldPath = ROOT_DIR . $existingReview['photo_url'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } else {
            $reviewId = db_insert(
                'INSERT INTO recipe_reviews (recipe_id, user_email, rating, text, photo_url) VALUES (?, ?, ?, ?, ?)',
                [$recipeId, $userEmail, $rating, $text, $photoUrl]
            );
            // Начисляем очки только за новый отзыв
    $userId = (int)current_user()['id'];
    add_user_points($userId, 5, 'review', 'Отзыв на рецепт #' . $recipeId);
        }
        
        echo json_encode([
            'ok' => true,
            'review_id' => $reviewId,
            'message' => 'Отзыв сохранён',
            'photo_url' => $photoUrl
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Submit review error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при сохранении отзыва'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// DELETE — удаление отзыва
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $reviewId = (int)($_GET['id'] ?? 0);
    
    if ($reviewId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан id отзыва'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $review = db_one('SELECT * FROM recipe_reviews WHERE id = ?', [$reviewId]);
        
        if (!$review) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Отзыв не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $canDelete = is_admin() || ($review['user_email'] === $userEmail);
        if (!$canDelete) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Нет прав на удаление'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($review['photo_url']) {
            $photoPath = ROOT_DIR . $review['photo_url'];
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }
        
        db_exec('DELETE FROM recipe_reviews WHERE id = ?', [$reviewId]);
        
        echo json_encode(['ok' => true, 'message' => 'Отзыв удалён'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Delete review error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при удалении отзыва'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;