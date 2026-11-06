<?php
/**
 * DELETE /api/delete_cookbook.php?id=123
 * Удаляет книгу рецептов
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Метод не поддерживается'], 405);
}

if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход'], 401);
}

$id = (int)($_GET['id'] ?? 0);
$user = current_user();

if ($id <= 0) {
    json_response(['error' => 'Не указан ID книги'], 400);
}

try {
    // Проверяем, что книга принадлежит пользователю
    $book = db_one(
        'SELECT id, author_email FROM cookbooks WHERE id = ?',
        [$id]
    );
    
    if (!$book) {
        json_response(['error' => 'Книга не найдена'], 404);
    }
    
    if ($book['author_email'] !== $user['email'] && !is_admin()) {
        json_response(['error' => 'Нет прав на удаление'], 403);
    }
    
    db_exec('DELETE FROM cookbooks WHERE id = ?', [$id]);
    
    json_response(['ok' => true, 'message' => 'Книга удалена']);
    
} catch (Throwable $ex) {
    json_response(['error' => $ex->getMessage()], 500);
}