<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userEmail = current_user()['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $entryDate = $input['entry_date'] ?? '';
    $weight = (float)($input['weight'] ?? 0);
    
    if (empty($entryDate) || $weight <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Некорректные данные']);
        exit;
    }
    
    // Обновляем или вставляем запись
    db_exec(
        'INSERT INTO diet_weight (user_email, entry_date, weight) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weight = VALUES(weight)',
        [$userEmail, $entryDate, $weight]
    );
    
    $entries = db_all('SELECT * FROM diet_weight WHERE user_email = ? ORDER BY entry_date DESC LIMIT 30', [$userEmail]);
    
    echo json_encode(['ok' => true, 'entries' => $entries]);
    exit;
}