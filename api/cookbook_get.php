<?php
/**
 * GigaChef — Получение книги или списка книг
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/cookbook_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('is_logged_in') || !is_logged_in()) {
    send_json(['ok' => false, 'error' => 'Необходима авторизация'], 401);
}

$user = current_user();
$userId = (int)$user['id'];

// Список книг
if (!empty($_GET['list'])) {
    $books = get_user_cookbooks($userId);
    
    $result = [];
    foreach ($books as $b) {
        $recipes = json_field($b['recipes_json']);
        $result[] = [
            'id' => (int)$b['id'],
            'title' => $b['title'],
            'slug' => $b['slug'],
            'recipe_count' => count($recipes),
            'is_public' => (int)$b['is_public'],
        ];
    }
    
    send_json(['ok' => true, 'books' => $result]);
}

// Одна книга
$bookId = (int)($_GET['book_id'] ?? $_GET['id'] ?? 0);

if (!$bookId) {
    send_json(['ok' => false, 'error' => 'Не указана книга'], 400);
}

// Проверяем права: либо владелец, либо книга публичная
$email = get_user_email($userId);
$book = db_one(
    'SELECT * FROM cookbooks WHERE id = ? AND (author_email = ? OR is_public = 1)',
    [$bookId, $email]
);

if (!$book) {
    send_json(['ok' => false, 'error' => 'Книга не найдена'], 404);
}

$book['recipes'] = json_field($book['recipes_json']);
unset($book['recipes_json']);

send_json(['ok' => true, 'book' => $book]);