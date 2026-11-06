<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $newsId = (int)($_GET['news_id'] ?? 0);
    
    $comments = db_all(
        "SELECT c.id, c.text, c.created_at, c.user_email, u.display_name
         FROM news_comments c
         LEFT JOIN users u ON u.email = c.user_email
         WHERE c.news_id = ?
         ORDER BY c.created_at DESC",
        [$newsId]
    );
    
    echo json_encode(['comments' => $comments]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        echo json_encode(['error' => 'Требуется авторизация']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $newsId = (int)($input['news_id'] ?? 0);
    $text = trim($input['text'] ?? '');
    
    if ($newsId <= 0 || empty($text)) {
        echo json_encode(['error' => 'Некорректные данные']);
        exit;
    }
    
    db_exec('INSERT INTO news_comments (news_id, user_email, text) VALUES (?,?,?)', 
        [$newsId, current_user()['email'], $text]);
    
    echo json_encode(['ok' => true]);
    exit;
}